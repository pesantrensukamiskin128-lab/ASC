# Panduan Lengkap Modul Penerimaan Mahasiswa Baru (PMB)

## Daftar Isi
1. [Untuk Admin](#untuk-admin)
2. [Untuk Calon Mahasiswa](#untuk-calon-mahasiswa)
3. [Alur Lengkap PMB](#alur-lengkap-pmb)
4. [FAQ](#faq)

---

## Untuk Admin

### A. Setup Awal PMB

#### 1. **Login sebagai Admin**
- Login dengan akun **SUPER_ADMIN** atau **ADMIN_AKADEMIK**
- User dengan permission `pmb.view` juga bisa akses

#### 2. **Buat Jalur Seleksi** (Opsional tapi direkomendasikan)
**Menu: PMB → Jalur Seleksi**

1. Klik **"Tambah Jalur"**
2. Isi form:
   - **Kode**: `REG`, `PRES`, `KHUS`, dll
   - **Nama**: `Reguler`, `Jalur Prestasi`, `Jalur Khusus`, dll
   - **Deskripsi**: Penjelasan singkat jalur
   - **Persyaratan**: Syarat khusus jalur ini
   - **Status**: Centang "Aktifkan jalur ini"
3. Klik **"Simpan"**

**Contoh Jalur**:
- Reguler: Jalur umum tanpa persyaratan khusus
- Prestasi: Memiliki prestasi akademik/non-akademik
- Khusus: Untuk anak guru, karyawan, dll

#### 3. **Buat Jenis Ujian Seleksi**
**Menu: PMB → Jenis Ujian**

1. Klik **"Tambah Jenis Ujian"**
2. Isi form:
   - **Kode**: `TPA`, `TBI`, `INT`, dll
   - **Nama**: `Tes Potensi Akademik`, `Tes Bahasa Inggris`, `Interview`, dll
   - **Bobot (%)**: Misalnya 40%, 30%, 30%
   - **KKM**: Nilai minimum lulus, misalnya 60
   - **Status**: Aktifkan
3. Klik **"Simpan"**

⚠️ **PENTING**: Total bobot semua jenis ujian harus = **100%**

**Contoh**:
| Jenis Ujian | Bobot | KKM |
|-------------|-------|-----|
| TPA         | 40%   | 60  |
| Tes Bahasa  | 30%   | 55  |
| Interview   | 30%   | 65  |
| **TOTAL**   | **100%** | - |

#### 4. **Buat Periode Pendaftaran**
**Menu: PMB → Periode PMB**

1. Klik **"Tambah Gelombang"**
2. Isi form:
   - **Tahun Akademik**: Pilih tahun akademik
   - **Nama Gelombang**: `Gelombang 1`, `Gelombang 2`, dll
   - **Periode Pendaftaran**:
     - Mulai: `2026-08-01`
     - Selesai: `2026-08-31`
   - **Tanggal Seleksi**: `2026-09-05`
   - **Tanggal Pengumuman**: `2026-09-10`
   - **Kuota**: `200` mahasiswa
   - **Biaya Pendaftaran**: `250000` (Rp 250.000)
   - **Status**: Centang "Aktifkan periode ini"
3. Klik **"Simpan"**

⚠️ **CATATAN**: Hanya boleh ada **1 periode aktif** di saat yang sama.

---

### B. Kelola Pendaftar

#### 1. **Melihat Dashboard PMB**
**Menu: PMB → Dashboard PMB**

Di dashboard, Anda akan melihat:
- **Statistik**: Total pendaftar, disubmit, menunggu verifikasi, terverifikasi, lulus, dll
- **Filter Periode**: Pilih gelombang untuk lihat statistik spesifik
- **Funnel Visualization**: Grafik alur pendaftaran
- **Quick Actions**: Tombol cepat ke menu lain

#### 2. **Melihat Daftar Pendaftar**
**Menu: PMB → Pendaftar**

Fitur:
- **Search**: Cari berdasarkan nama atau nomor pendaftaran
- **Filter Gelombang**: Filter berdasarkan periode
- **Filter Status**: Filter berdasarkan status (DRAFT, SUBMITTED, dll)

Kolom yang ditampilkan:
- No. Pendaftaran
- Nama
- Gelombang
- Jalur
- Pilihan Prodi 1
- Status Pembayaran
- Status Pendaftaran
- Aksi (Lihat Detail, Hapus)

#### 3. **Verifikasi & Proses Pendaftar**
**Menu: PMB → Pendaftar → (Klik ikon mata)**

##### **Tahap 1: Verifikasi Berkas**
Pendaftar dengan status **MENUNGGU_VERIFIKASI**:

1. Cek data pribadi, orang tua, pendidikan
2. Cek dokumen:
   - Pas foto (sudah upload?)
   - Link ijazah, KK, KTP (link valid?)
3. Cek status pembayaran (sudah lunas?)
4. Jika semua OK → Klik **"Verifikasi"**
   - Status berubah ke **TERVERIFIKASI**

##### **Tahap 2: Set Mengikuti Seleksi**
Pendaftar dengan status **TERVERIFIKASI**:

1. Jika sudah waktunya seleksi
2. Klik **"Set Mengikuti Seleksi"**
   - Status berubah ke **MENGIKUTI_SELEKSI**

##### **Tahap 3: Input Nilai Seleksi**
Pendaftar dengan status **MENGIKUTI_SELEKSI**:

1. Scroll ke bagian **"Nilai Seleksi"**
2. Isi nilai untuk setiap jenis ujian:
   - **Nilai**: Masukkan nilai 0-100
   - **Catatan** (opsional): Catatan tambahan
   - Nilai akan highlight **merah** jika < KKM
3. Klik **"Simpan Nilai"**
4. Setelah semua nilai diisi → Klik **"Hitung Nilai Akhir"**
   - Sistem akan hitung nilai akhir berdasarkan bobot
   - Muncul **rekomendasi**: LULUS, CADANGAN, atau TIDAK_LULUS

**Contoh Perhitungan**:
```
TPA       = 75  x 40% = 30
Tes Bahasa= 70  x 30% = 21
Interview = 80  x 30% = 24
─────────────────────────────
NILAI AKHIR        = 75

Jika passing grade = 65:
→ Rekomendasi: LULUS ✅
```

##### **Tahap 4: Keputusan Akhir**
Setelah nilai dihitung:

1. Review nilai akhir dan rekomendasi sistem
2. Klik:
   - **"Nyatakan Lulus"** → Status: **LULUS**
   - **"Nyatakan Tidak Lulus"** → Status: **TIDAK_LULUS**

##### **Tahap 5: Proses Daftar Ulang** (Khusus yang LULUS)
Pendaftar dengan status **LULUS**:

1. Muncul form "Proses Daftar Ulang"
2. Input **NIM** mahasiswa baru
3. Klik **"Proses Daftar Ulang"**
4. Sistem otomatis:
   - Buat data mahasiswa baru di tabel `students`
   - Set status ke **MAHASISWA_BARU**
   - Link data pendaftar dengan mahasiswa baru

---

### C. Tips untuk Admin

#### ✅ **Best Practices**
1. **Setup jenis ujian** sebelum buat periode
2. **Pastikan total bobot = 100%** untuk perhitungan akurat
3. **Verifikasi dokumen** dengan teliti sebelum approve
4. **Backup data** sebelum bulk actions
5. **Komunikasi** dengan pendaftar via email/WA untuk update status

#### ⚠️ **Perhatian**
- Jangan hapus pendaftar yang sudah jadi mahasiswa
- Jangan ubah bobot ujian saat sudah ada nilai terinput
- Pastikan periode tidak overlap
- Cek ulang NIM sebelum proses daftar ulang

---

## Untuk Calon Mahasiswa

### A. Registrasi & Login

#### 1. **Akses Landing Page**
1. Buka `http://[domain]/pmb`
2. Anda akan melihat:
   - Info periode pendaftaran aktif
   - Alur pendaftaran
   - Jalur yang tersedia
   - Biaya pendaftaran

#### 2. **Buat Akun**
1. Klik **"Daftar Sekarang"**
2. Isi form registrasi:
   - **Nama Lengkap**: Sesuai ijazah
   - **Email**: Email aktif
   - **Password**: Min. 8 karakter
   - **Konfirmasi Password**: Ulangi password
3. Klik **"Buat Akun & Lanjutkan"**
4. Auto-login → redirect ke formulir

#### 3. **Login** (Jika sudah punya akun)
1. Klik **"Sudah Punya Akun"** atau **"Masuk di sini"**
2. Isi email & password
3. Klik **"Masuk"**
4. Redirect ke:
   - **Formulir** (jika belum submit)
   - **Status** (jika sudah submit)

---

### B. Mengisi Formulir Pendaftaran

**Menu: Formulir Pendaftaran** (auto-redirect setelah register)

Formulir dibagi jadi **6 Step**:

#### **Step 1: Periode & Jalur**
- **Periode**: Pilih gelombang (biasanya auto-selected)
- **Jalur**: Pilih jalur (Reguler, Prestasi, Khusus)
- **Deskripsi Prestasi**: Jika jalur prestasi, tulis prestasi Anda

#### **Step 2: Data Pribadi**
- Nama lengkap, jenis kelamin, agama
- Tempat & tanggal lahir
- NIK, No. HP, Email
- Alamat lengkap (alamat, provinsi, kota, kecamatan, kode pos)

#### **Step 3: Data Orang Tua/Wali**
- **Ayah**: Nama, pekerjaan, no HP
- **Ibu**: Nama, pekerjaan, no HP
- **Wali** (opsional): Nama, pekerjaan, no HP

#### **Step 4: Riwayat Pendidikan**
- Nama sekolah asal
- Alamat sekolah
- Tahun lulus
- Nomor ijazah

#### **Step 5: Pilihan Program Studi**
- **Pilihan 1** (wajib): Prodi prioritas utama
- **Pilihan 2** (opsional): Prodi alternatif
- **Pilihan 3** (opsional): Prodi alternatif kedua

#### **Step 6: Dokumen**
- **Pas Foto**: Upload langsung (JPG/PNG, 3x4, max 2MB)
  - ⚠️ Simpan draft dulu baru bisa upload foto
- **Ijazah**: Masukkan link Google Drive
- **Kartu Keluarga**: Masukkan link Google Drive
- **KTP/Identitas**: Masukkan link Google Drive

**Tips Upload Dokumen**:
1. Scan dokumen dengan jelas
2. Upload ke Google Drive Anda
3. Set sharing: "Anyone with the link can view"
4. Copy link → paste di formulir

---

### C. Submit & Bayar

#### **1. Simpan Draft**
- Klik **"Simpan Draft"** kapan saja
- Data tersimpan, bisa lanjutkan nanti
- Status: **DRAFT**

#### **2. Submit Formulir**
1. Pastikan semua data sudah benar
2. Klik **"Submit Formulir"**
3. Konfirmasi: "Yakin submit? Setelah submit tidak bisa diubah"
4. Klik **OK**
5. Status berubah ke **SUBMITTED**
6. Redirect ke halaman **Status**

#### **3. Upload Pas Foto** (jika belum)
1. Di halaman Status, bagian "Pas Foto"
2. Klik **"Upload Foto"**
3. Pilih file foto
4. Tunggu upload selesai

#### **4. Konfirmasi Pembayaran**
1. Lakukan pembayaran biaya pendaftaran (lihat info di landing page)
2. Setelah bayar, di halaman Status:
   - Bagian **"Konfirmasi Pembayaran"**
   - Input bukti: `Transfer BCA 20/07/2026` atau link bukti transfer
   - Klik **"Konfirmasi Bayar"**
3. Status pembayaran: **Lunas**
4. Status pendaftaran: **MENUNGGU_VERIFIKASI**

---

### D. Cek Status & Seleksi

#### **1. Lihat Status Pendaftaran**
**Menu: Status** (atau `http://[domain]/pmb/status`)

**Progress Bar** menunjukkan tahapan:
1. Draft
2. Disubmit
3. Menunggu Verifikasi
4. Terverifikasi
5. Seleksi
6. Lulus
7. Mahasiswa Baru

**Info yang ditampilkan**:
- Nomor pendaftaran
- Nama
- Pilihan prodi
- Jalur & gelombang
- Status pembayaran

#### **2. Download Kartu Peserta**
Setelah status **TERVERIFIKASI** atau lebih:

1. Bagian **"Kartu Peserta"**
2. Klik **"Download Kartu Peserta (PDF)"**
3. Kartu ter-download otomatis
4. **Cetak kartu** → bawa saat seleksi

⚠️ **Wajib dibawa** saat mengikuti ujian seleksi!

#### **3. Ikuti Ujian Seleksi**
- Cek jadwal di kartu peserta atau email/WA dari panitia
- Datang sesuai jadwal dengan membawa:
  - Kartu peserta
  - KTP/identitas
  - Alat tulis
- Ikuti instruksi panitia

#### **4. Cek Pengumuman**
Setelah tanggal pengumuman:

1. Login ke `http://[domain]/pmb/status`
2. Lihat bagian **"Hasil Seleksi"**:
   - **Nilai Akhir**: Nilai total Anda
   - **Status**: LULUS / TIDAK LULUS
   - **Diterima di**: Program studi (jika lulus)

#### **5. Daftar Ulang** (Jika Lulus)
1. Setelah status **LULUS**, muncul info daftar ulang
2. Hubungi admin/panitia untuk proses daftar ulang
3. Setelah diproses, Anda mendapat **NIM**
4. Status berubah ke **MAHASISWA_BARU**
5. Anda resmi menjadi mahasiswa! 🎉

---

## Alur Lengkap PMB

```
┌─────────────────────────────────────────────────────────────────┐
│                   ALUR PENERIMAAN MAHASISWA BARU                │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐
│  1. REGISTER │  Calon mahasiswa buat akun
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  2. FORMULIR │  Isi data lengkap (6 step)
└──────┬───────┘  - Pribadi, ortu, pendidikan, pilihan prodi
       │          - Upload pas foto
       │          - Link dokumen (ijazah, KK, KTP)
       ▼
┌──────────────┐
│  3. SUBMIT   │  Submit formulir
└──────┬───────┘  Status: SUBMITTED
       │
       ▼
┌──────────────┐
│  4. BAYAR    │  Bayar biaya pendaftaran
└──────┬───────┘  Konfirmasi pembayaran
       │          Status: MENUNGGU_VERIFIKASI
       ▼
┌──────────────┐
│  5. VERIFIKASI│ Admin cek dokumen & data
└──────┬───────┘  Status: TERVERIFIKASI
       │
       ▼
┌──────────────┐
│  6. SELEKSI  │  - Download kartu peserta
└──────┬───────┘  - Ikuti ujian
       │          - Admin input nilai
       │          Status: MENGIKUTI_SELEKSI
       ▼
┌──────────────┐
│ 7. PENGUMUMAN│  Lihat hasil:
└──────┬───────┘  - LULUS → lanjut step 8
       │          - TIDAK LULUS → selesai ❌
       │
       ▼ (jika LULUS)
┌──────────────┐
│ 8. DAFTAR    │  Admin proses:
│    ULANG     │  - Input NIM
└──────┬───────┘  - Buat data mahasiswa
       │          Status: MAHASISWA_BARU
       ▼
┌──────────────┐
│ 9. MAHASISWA │  Resmi jadi mahasiswa! 🎓
│    BARU      │  Mulai kuliah semester depan
└──────────────┘
```

---

## FAQ

### Untuk Admin

**Q: Berapa periode yang bisa aktif bersamaan?**
A: Sebaiknya hanya 1 periode aktif untuk menghindari kebingungan pendaftar.

**Q: Bagaimana jika total bobot ujian tidak 100%?**
A: Sistem tetap hitung, tapi perhitungan tidak akurat. Muncul warning di halaman Jenis Ujian.

**Q: Bisa ubah nilai setelah disimpan?**
A: Bisa. Cukup input ulang nilai di halaman detail pendaftar, lalu klik "Simpan Nilai" lagi.

**Q: Bagaimana cara bulk approve banyak pendaftar?**
A: Saat ini belum ada fitur bulk. Harus approve satu per satu.

**Q: Bisa export data pendaftar ke Excel?**
A: Fitur ini bisa ditambahkan. Saat ini belum tersedia.

---

### Untuk Calon Mahasiswa

**Q: Lupa password, bagaimana?**
A: Saat ini belum ada fitur forgot password. Hubungi admin untuk reset.

**Q: Bisa ubah data setelah submit?**
A: Tidak bisa. Pastikan data benar sebelum submit. Jika ada kesalahan, hubungi admin.

**Q: Pas foto tidak bisa diupload?**
A: Pastikan sudah simpan draft formulir dulu. Format: JPG/PNG, max 2MB.

**Q: Link Google Drive error?**
A: Pastikan sharing setting: "Anyone with the link can view". Copy link yang benar.

**Q: Kartu peserta tidak bisa didownload?**
A: Kartu tersedia setelah status TERVERIFIKASI. Jika masih error, hubungi admin.

**Q: Tidak menerima notifikasi perubahan status?**
A: Saat ini notifikasi email belum otomatis. Cek status secara berkala di website.

**Q: Bisa daftar lagi jika tidak lulus?**
A: Bisa daftar lagi di periode/gelombang berikutnya dengan akun baru.

---

## Kontak & Support

Jika ada pertanyaan atau kendala:
- **Email**: admisi@institusi.ac.id
- **WhatsApp**: +62 812-3456-7890
- **Jam Operasional**: Senin-Jumat, 08:00-16:00 WIB

---

**Selamat menggunakan sistem PMB! Semoga sukses! 🎓**
