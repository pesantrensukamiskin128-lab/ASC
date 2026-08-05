# User Guide: Alur Hibah Penelitian & Pengabdian kepada Masyarakat

Panduan ini berlaku untuk dua jenis pengajuan: **Hibah Penelitian** dan **Hibah Pengabdian kepada Masyarakat**.
Alurnya identik — perbedaan hanya pada pilihan jenis saat membuat pengajuan.

---

## Peran Pengguna

| Peran | Tugas Utama |
|-------|-------------|
| **Dosen (Ketua/Anggota)** | Membuat proposal, upload laporan, submit LPJ |
| **Mahasiswa (Anggota)** | Terdaftar sebagai anggota tim penelitian |
| **Ka.Prodi** | Mengetahui / menolak proposal sebelum ke LP2M |
| **Reviewer / Dosen Reviewer** | Menilai proposal yang masuk seleksi |
| **LP2M** | Mengelola seleksi, kontrak, monev, seminar, LPJ, publikasi |
| **Keuangan** | Mengalokasikan dan mencairkan dana per tahap |
| **Admin** | Akses penuh ke semua tahapan |

---

## Alur Lengkap Pengajuan

### Tahap 1 — Pembuatan Proposal (Dosen)

1. Login ke aplikasi, buka menu **Penelitian**.
2. Klik **Tambah Baru**.
3. Isi form:
   - **Judul** (wajib)
   - **Jenis**: pilih `Penelitian` atau `Pengabdian kepada Masyarakat`
   - **Periode** hibah yang sedang aktif (wajib)
   - **Abstrak** dan **Kata Kunci** (opsional, bisa diisi nanti)
   - **Anggota Dosen** — pilih dosen anggota tim (opsional)
   - **Anggota Mahasiswa** — pilih mahasiswa anggota tim (opsional)
   - **Link Proposal** (Google Drive) — opsional saat draft
4. Klik **Simpan**. Status menjadi `draft`.
5. Selama draft, ketua dapat mengedit semua informasi termasuk komposisi tim.

> Hanya dosen yang dapat membuat proposal. Mahasiswa hanya bisa ditambahkan sebagai anggota.

---

### Tahap 2 — Submit Proposal ke Ka.Prodi (Dosen)

1. Buka detail proposal, pastikan dokumen proposal sudah siap.
2. Klik **Ajukan ke Ka.Prodi**.
3. Isi **link Google Drive** dokumen proposal (wajib).
4. Klik **Submit**. Status berubah ke `review_kaprodi`.

---

### Tahap 3 — Review Ka.Prodi

1. Ka.Prodi login, buka menu **Penelitian**, cari proposal berstatus `review_kaprodi` dari prodinya.
2. Buka detail, pilih hasil review:
   - **Diketahui** → proposal diteruskan ke LP2M, status menjadi `seleksi_reviewer`
   - **Ditolak** → status kembali ke `submitted`, dosen dapat memperbaiki dan mengajukan ulang
3. Opsional: isi catatan untuk dosen pengusul.

---

### Tahap 4 — Seleksi & Review oleh Reviewer (LP2M)

#### 4a. LP2M menugaskan reviewer

1. LP2M login, buka menu **Review**.
2. Pilih proposal berstatus `seleksi_reviewer`.
3. Tugaskan **2–3 reviewer** sekaligus, pilih tahap `seleksi`.
4. Klik **Tugaskan Reviewer**.

#### 4b. Reviewer menilai proposal

1. Reviewer login, buka menu **Review** — hanya tampil proposal yang ditugaskan.
2. Buka detail proposal, isi form penilaian:
   - **Orisinalitas** (0–25)
   - **Metodologi** (0–25)
   - **Manfaat** (0–25)
   - **Kelayakan** (0–25)
   - **Catatan** (opsional)
   - **Rekomendasi**: `Lolos` / `Tidak Lolos` / `Revisi`
3. Klik **Simpan Review**. Nilai total dihitung otomatis (0–100).

#### 4c. Dosen upload revisi proposal *(jika direkomendasikan revisi)*

1. Dosen membuka detail proposal.
2. Jika ada rekomendasi `revisi` dari reviewer, tombol upload revisi akan aktif.
3. Tempel **link Google Drive** proposal yang sudah direvisi.
4. Klik **Simpan Revisi Proposal**.

#### 4d. LP2M menetapkan hasil seleksi

1. LP2M membuka detail proposal setelah semua reviewer selesai menilai.
2. Pilih hasil:
   - **Lolos** → status berubah ke `kontrak`
   - **Tidak Lolos** → status berubah ke `tidak_lolos`, proses selesai

---

### Tahap 5 — Penandatanganan Kontrak (LP2M)

1. LP2M membuka proposal berstatus `kontrak`.
2. Isi detail kontrak:
   - **Nomor Kontrak** (wajib)
   - **Total Dana** yang disetujui (wajib)
   - **Link Kontrak** (Google Drive, opsional)
3. Klik **Simpan Kontrak**. Status berubah ke `pelaksanaan_1`.

---

### Tahap 6 — Pencairan Dana Tahap I (Keuangan)

Dana dicairkan dalam **3 tahap** dengan proporsi: **50% – 30% – 20%**.

1. Keuangan login, buka menu **Pencairan Dana**.
2. Pilih penelitian yang sudah berkontrak.
3. Isi form alokasi:
   - **Tahap**: 1
   - **Jumlah** dana (nominal)
   - **Keterangan** (opsional)
4. Klik **Alokasi Dana** → status alokasi menjadi `alokasi`.
5. Setelah dana siap ditransfer, klik **Cairkan** dan upload bukti transfer (PDF/gambar, opsional).
6. Status pencairan menjadi `cair`. Status penelitian berubah ke `pelaksanaan_1`.

---

### Tahap 7 — Pelaksanaan & Laporan Kemajuan (Dosen)

1. Tim peneliti menjalankan kegiatan penelitian/pengabdian.
2. Setelah laporan kemajuan selesai, ketua atau anggota membuka detail penelitian.
3. Klik **Upload Laporan Kemajuan**.
4. Tempel **link Google Drive** dokumen laporan kemajuan.
5. Klik **Simpan**. Status berubah ke `monev`.

---

### Tahap 8 — Monitoring & Evaluasi / Monev (LP2M)

1. LP2M membuka detail penelitian berstatus `monev`.
2. Opsional: tugaskan reviewer untuk monev (tahap `monev`).
3. LP2M mencatat hasil monev:
   - **Lanjut** → status berubah ke `pelaksanaan_2`, dana Tahap II siap dicairkan
   - **Revisi** → status berubah ke `revisi_kemajuan`, dosen harus merevisi laporan kemajuan

#### Jika revisi laporan kemajuan:

1. Dosen membuka detail penelitian berstatus `revisi_kemajuan`.
2. Klik **Upload Revisi Laporan Kemajuan**, tempel link Google Drive.
3. Klik **Simpan**. Status kembali ke `monev` untuk diperiksa LP2M kembali.

---

### Tahap 9 — Pencairan Dana Tahap II (Keuangan)

Sama seperti Tahap I, dengan tahap = **2** (30% dari total dana).

Setelah dana cair, status penelitian otomatis berubah ke `pelaksanaan_2`.

---

### Tahap 10 — Laporan Akhir & Paper (Dosen)

1. Setelah pelaksanaan selesai, buka detail penelitian berstatus `pelaksanaan_2`.
2. Klik **Upload Laporan Akhir**.
3. Isi form:
   - **Link Laporan Akhir** (Google Drive, wajib)
   - **Link Paper** (Google Drive, opsional)
   - **Abstrak** dan **Daftar Pustaka** (opsional, untuk repository)
4. Klik **Simpan**. Status berubah ke `seminar`.

---

### Tahap 11 — Seminar Hasil (LP2M)

#### 11a. LP2M menetapkan jadwal seminar

1. LP2M membuka detail penelitian berstatus `seminar`.
2. Isi tanggal seminar, klik **Tetapkan Jadwal Seminar**.

#### 11b. LP2M mencatat hasil seminar

Setelah seminar berlangsung:

1. LP2M membuka detail penelitian.
2. Pilih hasil:
   - **Diterima** → status berubah ke `lpj`
   - **Revisi** → status berubah ke `revisi_seminar`, dosen harus upload laporan final

#### Jika revisi pasca seminar:

1. Dosen mengupload **file PDF laporan final** (wajib, maks. 20 MB) langsung ke sistem.
2. Opsional: upload juga **file PDF paper final** (maks. 10 MB).
3. Setelah upload berhasil, status otomatis berubah ke `lpj`.

> Laporan final dan paper final di tahap ini diupload sebagai file PDF langsung (bukan link).

---

### Tahap 12 — LPJ (Laporan Pertanggungjawaban Keuangan)

#### 12a. Dosen menyerahkan LPJ

1. Buka detail penelitian berstatus `lpj` atau `pelaksanaan_2`.
2. Klik **Upload LPJ**, tempel **link Google Drive** dokumen LPJ.
3. Klik **Simpan LPJ**. Menunggu pemeriksaan LP2M.

#### 12b. LP2M memeriksa LPJ

1. LP2M membuka detail penelitian.
2. Pilih hasil:
   - **Terima LPJ** → status berubah ke `selesai`
   - **Revisi LPJ** → isi catatan revisi, status berubah ke `revisi_lpj`

#### Jika revisi LPJ:

1. Dosen membuka detail penelitian berstatus `revisi_lpj`.
2. Lihat catatan dari LP2M.
3. Klik **Upload Revisi LPJ**, tempel link Google Drive LPJ yang sudah diperbaiki.
4. Status kembali ke `lpj` untuk diperiksa LP2M kembali.

---

### Tahap 13 — Pencairan Dana Tahap III (Keuangan)

Dana terakhir (20%) dapat dicairkan setelah LPJ diterima atau sesuai kebijakan.

Keuangan mengalokasikan dan mencairkan dengan tahap = **3**.

---

### Tahap 14 — Publikasi ke Repository (LP2M / Admin)

1. LP2M atau Admin membuka detail penelitian berstatus `selesai`.
2. Opsional: upload **foto cover** (JPG/PNG, maks. 2 MB).
3. Klik **Publikasikan ke Repository**.
4. Penelitian kini dapat diakses publik di halaman **Repository**.

---

## Ringkasan Alur Status

```
draft
  └→ review_kaprodi
       └→ submitted (ditolak Ka.Prodi, bisa ajukan ulang)
       └→ seleksi_reviewer
            └→ tidak_lolos (selesai)
            └→ kontrak
                 └→ pelaksanaan_1  ← Dana Tahap I cair (50%)
                      └→ monev
                           └→ revisi_kemajuan → monev (ulang)
                           └→ pelaksanaan_2  ← Dana Tahap II cair (30%)
                                └→ seminar
                                     └→ revisi_seminar → lpj
                                     └→ lpj
                                          └→ revisi_lpj → lpj (ulang)
                                          └→ selesai  ← Dana Tahap III cair (20%)
                                               └→ [dipublikasikan ke repository]
```

---

## Skema Pencairan Dana

| Tahap | Persentase | Pemicu |
|-------|-----------|--------|
| Tahap I | 50% | Setelah kontrak ditandatangani |
| Tahap II | 30% | Setelah monev dinyatakan lanjut |
| Tahap III | 20% | Setelah LPJ diterima |

---

## Catatan Penting

- **Link dokumen** (proposal, laporan kemajuan, laporan akhir, LPJ) menggunakan Google Drive — pastikan akses disetel **"Anyone with the link can view"**.
- **Laporan final & paper final** pasca revisi seminar diupload sebagai file **PDF langsung**, bukan link.
- Hanya **ketua atau anggota tim** yang dapat mengupload laporan dan LPJ.
- Ka.Prodi hanya dapat mereview penelitian dari **program studi miliknya**.
- Reviewer hanya dapat melihat dan menilai penelitian yang **ditugaskan kepadanya** oleh LP2M.
- Satu proposal dapat dinilai oleh **2–3 reviewer** secara bersamaan.
