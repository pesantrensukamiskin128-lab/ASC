# Panduan Migrasi Data Mahasiswa Eksisting ke SIAKAD

## Gambaran Umum

Dokumen ini menjelaskan cara memasukkan data mahasiswa eksisting (berbagai angkatan) beserta riwayat akademik yang sudah ditempuh ke dalam sistem SIAKAD baru.

---

## Urutan Migrasi (WAJIB BERURUTAN)

```
1. Master Data (Prodi, Mata Kuliah, Dosen, Semester)
   ↓
2. Data Mahasiswa
   ↓
3. Riwayat Akademik (Nilai/Transkrip)
   ↓
4. Data Keuangan (opsional)
   ↓
5. Verifikasi & Validasi
```

⚠️ **PENTING**: Urutan ini WAJIB diikuti karena ada dependensi foreign key.

---

## TAHAP 1: Master Data

Sebelum import mahasiswa, pastikan data berikut sudah ada:

### 1a. Program Studi
Sudah bisa diinput via UI: **Master Data → Program Studi**

### 1b. Semester
Buat semua semester historis yang diperlukan.
Contoh: Ganjil 2020/2021, Genap 2020/2021, dst sampai sekarang.

**Via UI**: Master Data → Semester

### 1c. Mata Kuliah
Import via Excel: **Master Data → Mata Kuliah → Import**

Format Excel:
| kode | nama | sks | semester | kode_prodi | jenis |
|------|------|-----|----------|------------|-------|
| HES101 | Pengantar Hukum | 3 | 1 | HES | Wajib |

### 1d. Dosen
Import via Excel: **SDM → Dosen → Import**

Format Excel:
| nidn | nama_lengkap | email | no_hp | kode_prodi | jabatan_akademik | status_kepegawaian |
|------|-------------|-------|-------|------------|------------------|-------------------|
| 0412067803 | Ahmad Fauzi | ahmad@univ.ac.id | 081xxx | HES | Lektor | Tetap |

---

## TAHAP 2: Import Data Mahasiswa

### Cara 1: Via Fitur Import Excel (Rekomendasi)

**Menu**: SDM → Mahasiswa → Import

**Format Excel** (download template dulu):

| nim | nama_lengkap | kode_prodi | jenis_kelamin | tempat_lahir | tanggal_lahir | email | no_hp | asal_sekolah | tahun_masuk | status | dosen_wali |
|-----|-------------|------------|---------------|--------------|---------------|-------|-------|-------------|-------------|--------|------------|
| 2020110001 | Ahmad Rizki | HES | Laki-laki | Bandung | 2002-05-15 | ahmad@mail.com | 081xxx | SMAN 1 Bandung | 2020 | Aktif | 0412067803 |
| 2021110015 | Siti Nuraini | HES | Perempuan | Jakarta | 2003-01-20 | siti@mail.com | 082xxx | MAN 2 Jakarta | 2021 | Aktif | 0412067803 |
| 2019110008 | Budi Santoso | HES | Laki-laki | Surabaya | 2001-11-30 | budi@mail.com | 083xxx | SMAN 5 Surabaya | 2019 | Lulus | 0412067803 |

**Kolom wajib**: nim, nama_lengkap, kode_prodi
**Kolom opsional**: sisanya

**Yang otomatis dibuat sistem**:
- Akun user (username = NIM, password = NIM)
- Role MAHASISWA
- Email default jika kosong: `{nim}@student.jawami.ac.id`

**Status yang valid**: `Aktif`, `Cuti`, `Lulus`, `DO`, `Mengundurkan Diri`

### Cara 2: Via Artisan Command (Untuk Data Besar)

Untuk migrasi massal dari sistem lama (ribuan record), gunakan custom seeder:

```bash
php artisan db:seed --class=MigrateExistingStudentsSeeder
```

(File seeder akan dibuat di bawah)

---

## TAHAP 3: Import Riwayat Akademik (Nilai/Transkrip)

Ini bagian paling penting — memasukkan nilai mata kuliah yang sudah ditempuh mahasiswa.

### Cara 1: Via Artisan Command (Rekomendasi untuk data besar)

Saya akan buatkan command khusus: `php artisan migrate:grades`

### Cara 2: Via API Endpoint Khusus

Endpoint: `POST /api/migrate/grades`

### Format Excel untuk Import Nilai:

| nim | kode_matakuliah | semester | nilai_huruf | nilai_angka | bobot |
|-----|----------------|----------|-------------|-------------|-------|
| 2020110001 | HES101 | Ganjil 2020/2021 | A | 85 | 4.00 |
| 2020110001 | HES102 | Ganjil 2020/2021 | B+ | 78 | 3.50 |
| 2020110001 | HES201 | Genap 2020/2021 | A- | 82 | 3.75 |
| 2021110015 | HES101 | Ganjil 2021/2022 | B | 72 | 3.00 |

**Kolom**:
- `nim`: NIM mahasiswa (harus sudah ada di sistem)
- `kode_matakuliah`: Kode MK (harus sudah ada di master)
- `semester`: Nama semester (harus match dengan data semester di sistem)
- `nilai_huruf`: A, A-, B+, B, B-, C+, C, D, E
- `nilai_angka`: Nilai numerik 0-100
- `bobot`: Grade point (4.00, 3.75, 3.50, 3.00, 2.75, 2.50, 2.00, 1.00, 0)

---

## TAHAP 4: Data Keuangan (Opsional)

Jika ingin memasukkan riwayat pembayaran:

| nim | semester | jenis_tagihan | jumlah | status | tanggal_bayar |
|-----|----------|---------------|--------|--------|---------------|
| 2020110001 | Ganjil 2020/2021 | SPP | 5000000 | LUNAS | 2020-09-01 |
| 2020110001 | Genap 2020/2021 | SPP | 5000000 | LUNAS | 2021-02-15 |

---

## Tools Migrasi yang Disediakan

### Tool 1: Import Mahasiswa via Excel

**Cara akses**: SDM → Mahasiswa → Import (tombol Import)

**Format Excel**:
```
| nim | nama_lengkap | kode_prodi | jenis_kelamin | tempat_lahir | tanggal_lahir | email | no_hp | asal_sekolah | tahun_masuk | status | dosen_wali |
```

**Yang otomatis dilakukan sistem**:
- Buat akun user (username=NIM, password=NIM)
- Assign role MAHASISWA
- Link ke program studi
- Link ke dosen wali (via NIDN)

---

### Tool 2: Import Nilai via Excel

**Cara akses**: Menu Akademik → (akan ditambahkan tombol Import Nilai)

**Atau via API**:
```
POST /api/grades/import
Content-Type: multipart/form-data
Body: file = grades.xlsx
```

**Format Excel** (download template dulu):
```
| nim | kode_matakuliah | semester | nilai_huruf | nilai_angka | bobot |
```

**Keterangan kolom**:
- `nim` = NIM mahasiswa (**wajib**, harus sudah ada di sistem)
- `kode_matakuliah` = Kode MK (**wajib**, harus sudah ada di master)
- `semester` = Nama semester exact match (**wajib**, contoh: "Ganjil 2020/2021")
- `nilai_huruf` = A, A-, B+, B, B-, C+, C, D, E (**wajib**)
- `nilai_angka` = Nilai 0-100 (opsional)
- `bobot` = Grade point (opsional, auto-calculate dari huruf)

**Konversi otomatis**:
| Huruf | Bobot |
|-------|-------|
| A | 4.00 |
| A- | 3.75 |
| B+ | 3.50 |
| B | 3.00 |
| B- | 2.75 |
| C+ | 2.50 |
| C | 2.00 |
| D | 1.00 |
| E | 0.00 |

---

### Tool 3: Artisan Command (untuk data besar)

```bash
# Import nilai dari file Excel
php artisan migrate:grades storage/app/migration/grades.xlsx
```

Command ini lebih cocok untuk volume besar (>1000 baris) karena:
- Progress bar
- Transaction (gagal semua = rollback)
- Error detail per baris
- Tidak perlu melalui HTTP/API

---

## LANGKAH DEMI LANGKAH: Migrasi Lengkap

### Skenario: Kampus punya 500 mahasiswa dari angkatan 2019-2024

#### Step 1: Persiapan Data

Kumpulkan data dari sistem lama ke format Excel:

**File 1: `mahasiswa.xlsx`**
- Semua data mahasiswa aktif + alumni
- Satu file besar, semua angkatan

**File 2: `nilai.xlsx`**
- Export semua nilai dari sistem lama
- Satu baris per mata kuliah per mahasiswa per semester

**File 3: `dosen.xlsx`**
- Data dosen lengkap

**File 4: `matakuliah.xlsx`**
- Semua mata kuliah

#### Step 2: Setup Master Data

1. **Login** sebagai SUPER_ADMIN
2. **Buat Tahun Akademik**: Master Data → Tahun Akademik
   - 2019/2020, 2020/2021, 2021/2022, 2022/2023, 2023/2024, 2024/2025, 2025/2026
3. **Buat Semester**: Master Data → Semester
   - Ganjil 2019/2020, Genap 2019/2020, ... (semua semester historis)
4. **Import Mata Kuliah**: Master Data → Mata Kuliah → Import
5. **Import Dosen**: SDM → Dosen → Import

#### Step 3: Import Mahasiswa

1. **SDM → Mahasiswa → Import**
2. Upload file `mahasiswa.xlsx`
3. Verifikasi hasil import
4. Cek jika ada error → perbaiki → re-import

#### Step 4: Import Nilai/Transkrip

1. Upload file `nilai.xlsx` via:
   - API: `POST /api/grades/import`
   - Atau Command: `php artisan migrate:grades storage/app/migration/nilai.xlsx`
2. Tunggu proses selesai
3. Verifikasi:
   - Cek transkrip beberapa mahasiswa sample
   - Pastikan IPK sesuai

#### Step 5: Verifikasi

1. **Cek sample mahasiswa**:
   - Buka detail mahasiswa random
   - Verifikasi data pribadi benar
   - Cek transkrip: jumlah MK, IPK, huruf mutu

2. **Cek statistik**:
   - Dashboard → total mahasiswa harus sesuai
   - Laporan → jumlah per prodi per angkatan benar

3. **Cek login mahasiswa**:
   - Login sebagai mahasiswa (NIM / NIM)
   - Verifikasi bisa lihat KHS/transkrip

---

## FAQ Migrasi

### Q: Bagaimana jika NIM duplikat?
**A**: Sistem menggunakan `updateOrCreate` — jika NIM sudah ada, data akan di-UPDATE, bukan error.

### Q: Bagaimana jika kode mata kuliah tidak cocok?
**A**: Baris tersebut akan di-skip dan masuk ke daftar error. Perbaiki kode di Excel lalu re-import.

### Q: Bagaimana jika semester belum ada?
**A**: Buat semua semester dulu sebelum import nilai. Nama harus exact match (contoh: "Ganjil 2020/2021").

### Q: Berapa batas ukuran file?
**A**: Maksimal 10MB. Untuk file lebih besar, pecah jadi beberapa file atau gunakan artisan command.

### Q: Bagaimana jika ada mahasiswa yang sudah lulus?
**A**: Set status = "Lulus" di kolom status saat import mahasiswa. Data nilai tetap bisa diimport.

### Q: Perlu import KRS juga?
**A**: **Tidak wajib** untuk migrasi awal. Yang penting adalah data mahasiswa dan nilai. KRS historis bisa diabaikan — KRS baru dikelola dari semester berjalan.

### Q: Bagaimana dengan data keuangan historis?
**A**: **Opsional**. Jika mau clean start, cukup mulai tagihan dari semester berjalan. Jika mau lengkap, bisa import via API atau seeder khusus.

### Q: Apa yang terjadi jika proses gagal di tengah?
**A**: 
- Via command (`migrate:grades`): menggunakan transaction, gagal = rollback semua
- Via API import: per-batch, yang gagal di-skip, yang berhasil tetap tersimpan

### Q: Bagaimana migrasi data mahasiswa yang sudah jadi alumni?
**A**: 
1. Import sebagai mahasiswa dengan status "Lulus"
2. Kemudian buat data alumni terpisah (via menu Alumni → Tambah Alumni → pilih dari data mahasiswa)
3. Atau buat seeder khusus yang otomatis buat alumni dari mahasiswa status Lulus

---

## Checklist Migrasi

- [ ] Backup database lama
- [ ] Siapkan file Excel semua data
- [ ] Buat tahun akademik (semua historis)
- [ ] Buat semester (semua historis)
- [ ] Import mata kuliah
- [ ] Import dosen
- [ ] Import mahasiswa (semua angkatan)
- [ ] Import nilai (semua semester)
- [ ] Verifikasi sample mahasiswa
- [ ] Verifikasi IPK/IPS benar
- [ ] Test login mahasiswa
- [ ] Test login dosen
- [ ] Laporan statistik sesuai
- [ ] Backup database baru
- [ ] GO LIVE! 🚀

---

## Estimasi Waktu

| Volume Data | Estimasi Waktu |
|------------|---------------|
| 100 mahasiswa | 30 menit |
| 500 mahasiswa | 1-2 jam |
| 1000 mahasiswa | 2-4 jam |
| 5000 mahasiswa | 4-8 jam |
| 10000 mahasiswa | 1 hari |

*Termasuk persiapan data, import, dan verifikasi*

---

## Kontak

Jika ada kendala saat migrasi:
- Cek error log: `storage/logs/laravel.log`
- Cek error import di response API
- Hubungi developer/IT Support

