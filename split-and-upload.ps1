# Split SQL file dan upload ke Railway per chunk
$BACKEND_URL = "https://asc-production-9627.up.railway.app"
$API_KEY = "SIAKAD-MIGRATE-2026-ASC"
$SQL_FILE = "C:\Users\User\Documents\Aplikasi\ASC\_referensi\siakadstai_siakad.sql"
$CHUNK_SIZE = 5MB  # 5MB per chunk (aman di bawah 8MB limit)
$TEMP_DIR = "$env:TEMP\siakad_chunks"

Write-Host "=== SPLIT & UPLOAD SQL ===" -ForegroundColor Cyan

# Buat temp dir
New-Item -ItemType Directory -Path $TEMP_DIR -Force | Out-Null

# Baca file dan split
Write-Host "Membaca file SQL ($([math]::Round((Get-Item $SQL_FILE).Length/1MB,1)) MB)..." -ForegroundColor Yellow
$bytes = [System.IO.File]::ReadAllBytes($SQL_FILE)
$totalBytes = $bytes.Length
$chunkBytes = [int]$CHUNK_SIZE
$numChunks = [math]::Ceiling($totalBytes / $chunkBytes)
Write-Host "Akan dibagi menjadi $numChunks bagian" -ForegroundColor Yellow

# Hapus chunks lama
Get-ChildItem $TEMP_DIR -Filter "chunk_*.sql" | Remove-Item

# Split
for ($i = 0; $i -lt $numChunks; $i++) {
    $start = $i * $chunkBytes
    $end = [math]::Min($start + $chunkBytes, $totalBytes)
    $chunk = $bytes[$start..($end-1)]
    $chunkFile = "$TEMP_DIR\chunk_$($i.ToString('000')).bin"
    [System.IO.File]::WriteAllBytes($chunkFile, $chunk)
    $sizeMB = [math]::Round($chunk.Length/1MB, 1)
    Write-Host "  Chunk $($i+1)/$numChunks: $sizeMB MB -> $chunkFile" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Mengupload $numChunks chunks ke Railway..." -ForegroundColor Yellow

# Upload setiap chunk
$successCount = 0
for ($i = 0; $i -lt $numChunks; $i++) {
    $chunkFile = "$TEMP_DIR\chunk_$($i.ToString('000')).bin"
    $chunkNum = $i + 1
    Write-Host "  Upload chunk $chunkNum/$numChunks..." -NoNewline
    
    $result = & curl.exe -X POST "$BACKEND_URL/api/migration/upload-chunk" `
        -H "X-Migration-Key: $API_KEY" `
        -H "X-Chunk-Number: $i" `
        -H "X-Total-Chunks: $numChunks" `
        -H "X-Total-Size: $totalBytes" `
        -F "chunk=@$chunkFile" `
        --max-time 60 --silent --show-error
    
    try {
        $json = $result | ConvertFrom-Json
        if ($json.success -or $json.message) {
            Write-Host " OK" -ForegroundColor Green
            $successCount++
        } else {
            Write-Host " Error: $result" -ForegroundColor Red
        }
    } catch {
        Write-Host " Response: $result" -ForegroundColor Yellow
        $successCount++
    }
}

Write-Host ""
if ($successCount -eq $numChunks) {
    Write-Host "Semua chunk berhasil diupload! ($successCount/$numChunks)" -ForegroundColor Green
    Write-Host ""
    Write-Host "Menggabungkan chunks di server..." -ForegroundColor Yellow
    
    $result = & curl.exe -X POST "$BACKEND_URL/api/migration/assemble-chunks" `
        -H "X-Migration-Key: $API_KEY" `
        -H "X-Total-Chunks: $numChunks" `
        -H "Content-Type: application/json" `
        --max-time 120 --silent --show-error
    
    Write-Host "Response: $result" -ForegroundColor Gray
    $json = $result | ConvertFrom-Json
    if ($json.message) {
        Write-Host $json.message -ForegroundColor Green
    }
} else {
    Write-Host "Hanya $successCount/$numChunks chunk berhasil. Coba lagi." -ForegroundColor Red
}

# Hapus temp files
Remove-Item $TEMP_DIR -Recurse -Force
Write-Host "Temp files dibersihkan." -ForegroundColor Gray
