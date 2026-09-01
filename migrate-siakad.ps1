# ============================================================
# Script Migrasi Data SIAKAD ke ASC
# Jalankan di PowerShell: .\migrate-siakad.ps1
# ============================================================

# ============================================================
# KONFIGURASI — SESUAIKAN INI
# ============================================================
$BACKEND_URL = "https://asc-production-9627.up.railway.app/"  # Ganti dengan URL Railway backend Anda
$API_KEY     = "SIAKAD-MIGRATE-2026-ASC"
$SQL_FILE    = "$PSScriptRoot\_referensi\siakadstai_siakad.sql"
# ============================================================

$headers = @{ "X-Migration-Key" = $API_KEY }

function Write-Step($text) {
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host " $text" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
}

function Write-Ok($text)    { Write-Host "  ✓ $text" -ForegroundColor Green }
function Write-Err($text)   { Write-Host "  ✗ $text" -ForegroundColor Red }
function Write-Info($text)  { Write-Host "  → $text" -ForegroundColor Yellow }

# ============================================================
# CEK FILE SQL
# ============================================================
Write-Step "CEK FILE SQL"
if (Test-Path $SQL_FILE) {
    $size = [math]::Round((Get-Item $SQL_FILE).Length / 1MB, 1)
    Write-Ok "File ditemukan: $SQL_FILE ($size MB)"
} else {
    Write-Err "File tidak ditemukan: $SQL_FILE"
    exit 1
}

# ============================================================
# CEK KONEKSI KE BACKEND
# ============================================================
Write-Step "CEK KONEKSI BACKEND"
try {
    $resp = Invoke-WebRequest -Uri "$BACKEND_URL/api/institution/public" -TimeoutSec 10 -UseBasicParsing
    Write-Ok "Backend terhubung: $BACKEND_URL"
} catch {
    Write-Err "Tidak bisa terhubung ke backend: $BACKEND_URL"
    Write-Info "Pastikan URL sudah benar dan Railway sudah di-deploy."
    exit 1
}

# ============================================================
# MENU
# ============================================================
Write-Host ""
Write-Host "Pilih aksi:" -ForegroundColor White
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
Write-Host ""

$pilihan = Read-Host "Masukkan nomor pilihan"

switch ($pilihan) {

    "1" {
        Write-Step "UPLOAD FILE SQL"
        Write-Info "Mengupload file... (mungkin butuh 1-5 menit untuk file 22MB)"
        try {
            # Buat multipart form data
            $form = @{ sql_file = Get-Item $SQL_FILE }
            $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/upload" `
                -Method POST -Headers $headers -Form $form -TimeoutSec 300
            Write-Ok "Upload berhasil!"
            Write-Info "Ukuran: $($resp.size_mb) MB"
        } catch {
            Write-Err "Upload gagal: $($_.Exception.Message)"
            if ($_.Exception.Response) {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                Write-Err $reader.ReadToEnd()
            }
        }
    }

    "2" {
        Write-Step "DRY-RUN (SIMULASI)"
        Write-Info "Menjalankan simulasi..."
        try {
            $body = '{"table":"all"}' | ConvertFrom-Json
            $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/dry-run" `
                -Method POST -Headers ($headers + @{"Content-Type"="application/json"}) `
                -Body '{"table":"all"}' -TimeoutSec 300
            Write-Ok "Dry-run selesai!"
            Write-Host $resp.output
        } catch {
            Write-Err "Error: $($_.Exception.Message)"
        }
    }

    "3" {
        Write-Step "MIGRASI FAKULTAS"
        Invoke-Migration "faculties"
    }

    "4" {
        Write-Step "MIGRASI PROGRAM STUDI"
        Invoke-Migration "study_programs"
    }

    "5" {
        Write-Step "MIGRASI SEMESTER"
        Invoke-Migration "semesters"
    }

    "6" {
        Write-Step "MIGRASI DOSEN"
        Invoke-Migration "lecturers"
    }

    "7" {
        Write-Step "MIGRASI MAHASISWA"
        Write-Info "Ini yang paling lama, harap sabar..."
        Invoke-Migration "students"
    }

    "8" {
        Write-Step "MIGRASI SEMUA"
        $tables = @("faculties", "study_programs", "semesters", "lecturers", "students")
        foreach ($t in $tables) {
            Write-Info "Migrasi: $t"
            Invoke-Migration $t
            Start-Sleep 2
        }
        Write-Ok "Semua migrasi selesai!"
    }

    "9" {
        Write-Step "CLEANUP"
        try {
            $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/cleanup" `
                -Method DELETE -Headers $headers -TimeoutSec 30
            Write-Ok $resp.message
        } catch {
            Write-Err "Error: $($_.Exception.Message)"
        }
    }

    "0" {
        Write-Host "Keluar." -ForegroundColor Gray
        exit 0
    }

    default {
        Write-Err "Pilihan tidak valid."
    }
}

Write-Host ""
Write-Host "Selesai." -ForegroundColor Green

function Invoke-Migration($table) {
    try {
        $body = "{`"table`": `"$table`"}"
        $resp = Invoke-RestMethod -Uri "$BACKEND_URL/api/migration/run" `
            -Method POST `
            -Headers ($headers + @{"Content-Type"="application/json"}) `
            -Body $body -TimeoutSec 300
        Write-Ok "Migrasi '$table' selesai!"
        Write-Host $resp.output
    } catch {
        Write-Err "Gagal migrasi '$table': $($_.Exception.Message)"
        if ($_.Exception.Response) {
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                Write-Err $reader.ReadToEnd()
            } catch {}
        }
    }
}
