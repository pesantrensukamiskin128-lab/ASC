# ============================================================
# Script Migrasi Data SIAKAD ke ASC
# Jalankan: .\migrate-siakad.ps1
# ============================================================

# KONFIGURASI - SESUAIKAN INI
$BACKEND_URL = "https://asc-production-9627.up.railway.app"
$API_KEY = "SIAKAD-MIGRATE-2026-ASC"
$SQL_FILE = "$PSScriptRoot\_referensi\siakadstai_siakad.sql"

$headers = @{ "X-Migration-Key" = $API_KEY }

# Fungsi harus didefinisikan di atas sebelum dipakai
function RunMigration {
    param([string]$table)
    Write-Host ""
    Write-Host ("=== MIGRASI: " + $table + " ===") -ForegroundColor Cyan
    $jsonBody = '{"table":"' + $table + '"}'
    $jsonHeaders = $headers.Clone()
    $jsonHeaders["Content-Type"] = "application/json"
    try {
        $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/run" -Method POST -Headers $jsonHeaders -Body $jsonBody -TimeoutSec 300
        Write-Host ("Selesai: " + $table) -ForegroundColor Green
        Write-Host $resp.output
    } catch {
        Write-Host ("Gagal " + $table + " : " + $_.Exception.Message) -ForegroundColor Red
    }
}

# Cek file SQL
Write-Host ""
Write-Host "=== CEK FILE SQL ===" -ForegroundColor Cyan
if (Test-Path $SQL_FILE) {
    $size = [math]::Round((Get-Item $SQL_FILE).Length / 1MB, 1)
    Write-Host "OK - File ditemukan: $SQL_FILE ($size MB)" -ForegroundColor Green
} else {
    Write-Host "ERROR - File tidak ditemukan: $SQL_FILE" -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

# Cek koneksi backend
Write-Host ""
Write-Host "=== CEK KONEKSI BACKEND ===" -ForegroundColor Cyan
try {
    $test = Invoke-WebRequest -Uri "$BACKEND_URL/api/institution/public" -TimeoutSec 10 -UseBasicParsing
    Write-Host "OK - Backend terhubung: $BACKEND_URL" -ForegroundColor Green
} catch {
    Write-Host "ERROR - Tidak bisa terhubung ke: $BACKEND_URL" -ForegroundColor Red
    Write-Host "Pastikan URL sudah benar dan Railway sudah deploy." -ForegroundColor Yellow
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

# Menu
:mainloop while ($true) {
    Write-Host ""
    Write-Host "========== MENU MIGRASI ==========" -ForegroundColor White
    Write-Host "  1. Upload file SQL ke server"
    Write-Host "  2. Dry-run (simulasi, tidak ubah data)"
    Write-Host "  3. Migrasi Fakultas"
    Write-Host "  4. Migrasi Program Studi"
    Write-Host "  5. Migrasi Semester"
    Write-Host "  6. Migrasi Dosen"
    Write-Host "  7. Migrasi Mahasiswa"
    Write-Host "  8. Migrasi SEMUA (urutan otomatis)"
    Write-Host "  9. Cleanup (hapus file SQL dari server)"
    Write-Host "  0. Keluar"
    Write-Host "==================================" -ForegroundColor White
    Write-Host ""
    $pilihan = Read-Host "Masukkan nomor pilihan"

    if ($pilihan -eq "0") {
        Write-Host "Keluar." -ForegroundColor Gray
        break mainloop
    }
    elseif ($pilihan -eq "1") {
        Write-Host ""
        Write-Host "=== UPLOAD FILE SQL ===" -ForegroundColor Cyan
        Write-Host "Pilih metode upload:" -ForegroundColor Yellow
        Write-Host "  A. Download dari URL (Google Drive / Dropbox) - DIREKOMENDASIKAN"
        Write-Host "  B. Upload langsung dari komputer (max 8MB, tidak cocok file besar)"
        $metode = Read-Host "Pilih (A/B)"

        if ($metode -eq "A" -or $metode -eq "a") {
            Write-Host ""
            Write-Host "Cara upload ke Google Drive:" -ForegroundColor Cyan
            Write-Host "  1. Upload file SQL ke Google Drive"
            Write-Host "  2. Klik kanan file -> 'Dapatkan link' -> set ke 'Siapa saja yang memiliki link'"
            Write-Host "  3. Copy link, contoh: https://drive.google.com/file/d/FILE_ID/view"
            Write-Host "  4. Ubah URL menjadi: https://drive.google.com/uc?export=download&id=FILE_ID"
            Write-Host ""
            $url = Read-Host "Masukkan URL download langsung file SQL"
            if ($url) {
                Write-Host "Server Railway akan mendownload file dari URL..." -ForegroundColor Yellow
                Write-Host "(Proses ini berjalan di server, tunggu hingga selesai...)" -ForegroundColor Gray
                $jsonHeaders = $headers.Clone()
                $jsonHeaders["Content-Type"] = "application/json"
                $body = '{"url":"' + $url + '"}'
                try {
                    $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/download-from-url" -Method POST -Headers $jsonHeaders -Body $body -TimeoutSec 300
                    Write-Host ("Download selesai! Ukuran: " + $resp.size_mb + " MB") -ForegroundColor Green
                } catch {
                    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
                }
            }
        } else {
            Write-Host "Mengupload langsung via curl..." -ForegroundColor Yellow
            $result = & curl.exe -X POST "$BACKEND_URL/api/migration/upload" `
                -H "X-Migration-Key: $API_KEY" `
                -F "sql_file=@$SQL_FILE" `
                --max-time 300 --silent --show-error
            Write-Host ("Response: " + $result)
            try {
                $json = $result | ConvertFrom-Json
                if ($json.size_mb) { Write-Host ("Upload berhasil! Ukuran: " + $json.size_mb + " MB") -ForegroundColor Green }
            } catch {}
        }

        # Cek status file di server
        Write-Host ""
        Write-Host "Cek status file di server..." -ForegroundColor Gray
        try {
            $status = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/status" -Method GET -Headers $headers -TimeoutSec 10
            if ($status.exists) {
                Write-Host ("File SQL ada di server: " + $status.size_mb + " MB") -ForegroundColor Green
            } else {
                Write-Host "File SQL belum ada di server." -ForegroundColor Red
            }
        } catch {
            Write-Host ("Tidak bisa cek status: " + $_.Exception.Message) -ForegroundColor Yellow
        }
    }
    elseif ($pilihan -eq "2") {
        Write-Host ""
        Write-Host "=== DRY-RUN ===" -ForegroundColor Cyan
        $jsonBody = '{"table":"all"}'
        $jsonHeaders = $headers.Clone()
        $jsonHeaders["Content-Type"] = "application/json"
        try {
            $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/dry-run" -Method POST -Headers $jsonHeaders -Body $jsonBody -TimeoutSec 300
            Write-Host "Dry-run selesai!" -ForegroundColor Green
            Write-Host $resp.output
        } catch {
            Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
    elseif ($pilihan -eq "3") { RunMigration "faculties" }
    elseif ($pilihan -eq "4") { RunMigration "study_programs" }
    elseif ($pilihan -eq "5") { RunMigration "semesters" }
    elseif ($pilihan -eq "6") { RunMigration "lecturers" }
    elseif ($pilihan -eq "7") { RunMigration "students" }
    elseif ($pilihan -eq "8") {
        Write-Host ""
        Write-Host "=== MIGRASI SEMUA ===" -ForegroundColor Cyan
        foreach ($tbl in @("faculties","study_programs","semesters","lecturers","students")) {
            RunMigration $tbl
            Start-Sleep -Seconds 2
        }
        Write-Host "Semua migrasi selesai!" -ForegroundColor Green
    }
    elseif ($pilihan -eq "9") {
        Write-Host ""
        Write-Host "=== CLEANUP ===" -ForegroundColor Cyan
        try {
            $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/cleanup" -Method DELETE -Headers $headers -TimeoutSec 30
            Write-Host $resp.message -ForegroundColor Green
        } catch {
            Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
    else {
        Write-Host "Pilihan tidak valid." -ForegroundColor Yellow
    }
}

