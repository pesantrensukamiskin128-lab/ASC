# Panduan Lengkap Bank Soal

## Daftar Isi
1. [Apa itu Bank Soal?](#apa-itu-bank-soal)
2. [Cara Mengakses](#cara-mengakses)
3. [Membuat Bank Soal](#membuat-bank-soal)
4. [Mengelola Soal](#mengelola-soal)
5. [Menggunakan Bank Soal di Ujian](#menggunakan-bank-soal-di-ujian)
6. [Tips & Best Practices](#tips--best-practices)
7. [FAQ](#faq)

---

## Apa itu Bank Soal?

**Bank Soal** adalah fitur untuk membuat dan mengelola kumpulan soal per mata kuliah yang dapat digunakan berulang kali untuk ujian. Dengan Bank Soal, Anda dapat:

- 📚 **Menyimpan soal** yang sering digunakan
- 🔄 **Reuse soal** untuk berbagai ujian
- 🤝 **Berbagi soal** dengan dosen lain
- 📊 **Kategorisasi soal** berdasarkan tingkat kesulitan dan tags
- ⚡ **Membuat ujian lebih cepat** dengan memilih dari bank soal

**Manfaat**:
- Efisiensi: Tidak perlu membuat soal dari awal setiap kali ujian
- Konsistensi: Soal terstandarisasi dan terorganisir
- Kolaborasi: Dosen bisa saling berbagi soal berkualitas
- Tracking: Mudah melihat soal apa saja yang sudah dibuat

---

## Cara Mengakses

### **Untuk DOSEN, ADMIN_AKADEMIK, atau SUPER_ADMIN**

#### **Cara 1: Melalui Sidebar**
1. Login ke sistem
2. Di sidebar kiri, cari menu **"Penilaian"**
3. Klik **"Bank Soal"**
4. Anda akan masuk ke halaman list Bank Soal

#### **Cara 2: Langsung via URL**
```
http://localhost:3000/bank-soal
```

**Permission Required**: `nilai.view`

⚠️ **Catatan**: 
- Hanya user dengan role DOSEN, ADMIN_AKADEMIK, atau SUPER_ADMIN yang bisa akses
- Bank Soal yang **tidak di-share** hanya bisa dilihat oleh pembuatnya atau SUPER_ADMIN
- Bank Soal yang **di-share** bisa dilihat dan digunakan oleh semua dosen

---

## Membuat Bank Soal

### **Langkah 1: Buat Bank Soal Baru**

1. Di halaman **Bank Soal**, klik tombol **"Buat Bank Soal"** (pojok kanan atas)

2. Isi form yang muncul:

   **a. Mata Kuliah** (wajib)
   - Pilih mata kuliah dari dropdown
   - Contoh: `HKB101 - Hukum Perbankan`
   
   **b. Judul** (wajib)
   - Beri nama yang deskriptif
   - **Contoh yang baik**:
     - `Bank Soal UTS Hukum Perbankan 2026`
     - `Soal Pilihan Ganda - Hukum Kontrak`
     - `Kumpulan Soal Esai - Analisis Kasus Perbankan`
   - **Hindari**:
     - `Soal 1`
     - `Test`
     - `Bank`
   
   **c. Deskripsi** (opsional)
   - Jelaskan isi dan tujuan bank soal ini
   - Contoh: `Kumpulan soal untuk ujian tengah semester, mencakup materi pertemuan 1-7 tentang dasar-dasar hukum perbankan dan sistem moneter`
   
   **d. Bagikan ke dosen lain** (opsional)
   - ✅ **Centang** jika ingin berbagi dengan dosen lain
   - Bank soal yang di-share bisa dilihat dan digunakan oleh semua dosen
   - ⬜ **Tidak centang** jika bank soal ini hanya untuk Anda

3. Klik **"Simpan"**

4. Bank soal berhasil dibuat! Sekarang Anda bisa menambahkan soal-soal ke dalamnya.

### **Langkah 2: Masuk ke Detail Bank Soal**

1. Di list Bank Soal, klik ikon **👁️ (mata)** pada bank soal yang baru dibuat
2. Anda akan masuk ke halaman detail bank soal
3. Di sini Anda akan melihat:
   - **Header**: Judul bank soal dan mata kuliah
   - **Statistik**: Total soal, jumlah soal per tingkat kesulitan
   - **Daftar Soal**: List semua soal yang sudah ada

---

## Mengelola Soal

### **Menambah Soal Baru**

1. Di halaman detail Bank Soal, klik **"Tambah Soal"**

2. Isi form soal:

#### **a. Informasi Umum**

| Field | Keterangan |
|-------|------------|
| **Tipe Soal** | - Pilihan Ganda<br>- Benar/Salah<br>- Esai<br>- Studi Kasus<br>- Upload File |
| **Tingkat Kesulitan** | - MUDAH (hijau)<br>- SEDANG (kuning)<br>- SULIT (merah) |
| **Skor** | Skor default untuk soal ini (misal: 1, 2, 5 poin) |

#### **b. Pertanyaan** (wajib)
- Ketik pertanyaan Anda
- Mendukung multi-line (bisa enter untuk baris baru)
- **Contoh**:
  ```
  Jelaskan pengertian Bank Sentral dan sebutkan 3 tugas utamanya dalam sistem moneter di Indonesia!
  ```

#### **c. Pilihan Jawaban** (untuk Pilihan Ganda)

- Secara default ada 4 pilihan (A, B, C, D)
- Klik **"+ Tambah Pilihan"** untuk menambah opsi
- Klik **🗑️ (trash)** untuk menghapus opsi (minimal 2 opsi)
- **Contoh**:
  ```
  A. Bank Indonesia
  B. Bank Mandiri
  C. Bank Dunia
  D. Bank Pembangunan Asia
  ```

#### **d. Jawaban Benar**

- **Untuk Pilihan Ganda**: Pilih jawaban yang benar dari dropdown
- **Untuk Benar/Salah**: Pilih "Benar" atau "Salah"
- **Untuk Esai/Studi Kasus**: Kosongkan (akan dinilai manual oleh dosen)
- **Untuk Upload File**: Kosongkan

#### **e. Penjelasan** (opsional tapi direkomendasikan)

- Berikan penjelasan atau pembahasan jawaban
- Akan ditampilkan ke mahasiswa setelah ujian selesai
- **Contoh**:
  ```
  Jawaban: A. Bank Indonesia
  
  Pembahasan: Bank Indonesia (BI) adalah bank sentral Republik Indonesia. 
  Tugas utama BI meliputi:
  1. Menetapkan dan melaksanakan kebijakan moneter
  2. Mengatur dan menjaga kelancaran sistem pembayaran
  3. Mengatur dan mengawasi perbankan
  ```

#### **f. Tags** (opsional)

- Gunakan tags untuk kategorisasi dan pencarian
- Ketik tag lalu tekan **Enter** atau klik tombol **+**
- Klik **×** untuk menghapus tag
- **Contoh tags**:
  - `bank-sentral`
  - `sistem-moneter`
  - `pertemuan-3`
  - `materi-utama`
  - `sering-keluar`

3. Klik **"Simpan"**

4. Soal berhasil ditambahkan! Ulangi untuk menambah soal lainnya.

---

### **Jenis-jenis Soal**

#### **1. Pilihan Ganda**
- Paling umum digunakan
- Auto-grading (sistem koreksi otomatis)
- Minimal 2 opsi, maksimal unlimited
- Mahasiswa pilih 1 jawaban yang benar

**Kapan digunakan?**
- Ujian objektif dengan jawaban pasti
- Tes pemahaman konsep
- Quiz cepat

#### **2. Benar / Salah**
- Versi sederhana dari pilihan ganda
- Hanya 2 opsi: Benar atau Salah
- Auto-grading

**Kapan digunakan?**
- Tes pemahaman statement/pernyataan
- Quick check understanding
- Bagian warm-up ujian

#### **3. Esai**
- Jawaban panjang/narasi
- Manual grading (dosen koreksi manual)
- Bisa multi-paragraph

**Kapan digunakan?**
- Ujian yang membutuhkan analisis mendalam
- Menilai kemampuan argumentasi
- Pemahaman komprehensif

#### **4. Studi Kasus**
- Mirip esai tapi biasanya disertai kasus/skenario
- Manual grading
- Biasanya pertanyaan lebih kompleks

**Kapan digunakan?**
- Problem-solving
- Aplikasi teori ke praktik
- Critical thinking

#### **5. Upload File**
- Mahasiswa upload file sebagai jawaban
- Bisa berupa dokumen, gambar, PDF, dll
- Manual grading

**Kapan digunakan?**
- Tugas desain/gambar
- Laporan lengkap
- Proyek akhir

---

### **Edit Soal**

1. Di daftar soal, klik ikon **✏️ (pensil)** pada soal yang ingin diedit
2. Form akan terbuka dengan data soal yang sudah ada
3. Ubah data yang perlu diubah
4. Klik **"Simpan"**

⚠️ **Perhatian**: Jika soal sudah digunakan di ujian yang sedang berlangsung, edit soal bisa mempengaruhi ujian tersebut. Sebaiknya edit sebelum ujian dimulai.

---

### **Hapus Soal**

1. Klik ikon **🗑️ (trash)** pada soal yang ingin dihapus
2. Konfirmasi: "Hapus soal ini?"
3. Klik **OK**
4. Soal terhapus

⚠️ **Hati-hati**: 
- Soal yang dihapus **tidak bisa dikembalikan**
- Jika soal sudah digunakan di ujian, data ujian tersebut bisa terganggu
- Sebaiknya backup dulu sebelum menghapus

---

### **Filter & Search**

Di halaman list Bank Soal, Anda bisa:

1. **Filter by Mata Kuliah**
   - Dropdown "Semua Mata Kuliah"
   - Pilih mata kuliah tertentu

2. **Search**
   - Ketik di kolom pencarian
   - Cari berdasarkan judul bank soal

---

### **Statistik Bank Soal**

Di halaman detail Bank Soal, Anda akan melihat card statistik:

| Card | Keterangan |
|------|------------|
| **Total Soal** | Jumlah total soal di bank ini (warna biru) |
| **Mudah** | Jumlah soal tingkat mudah (warna hijau) |
| **Sedang** | Jumlah soal tingkat sedang (warna kuning) |
| **Sulit** | Jumlah soal tingkat sulit (warna merah) |

**Tips**: Usahakan distribusi yang seimbang untuk ujian yang komprehensif.
- Contoh ideal: 40% Mudah, 40% Sedang, 20% Sulit

---

## Menggunakan Bank Soal di Ujian

### **Cara Pakai Bank Soal saat Buat Ujian**

1. Saat membuat ujian baru (di menu **Perkuliahan → Kelas → Ujian**)
2. Di bagian pembuatan soal ujian, akan ada opsi **"Ambil dari Bank Soal"**
3. Pilih Bank Soal yang ingin digunakan
4. Pilih soal-soal yang ingin dimasukkan ke ujian
5. Soal akan otomatis ter-copy ke ujian tersebut

### **Keuntungan Pakai Bank Soal**

✅ **Efisien**: Tidak perlu ketik soal dari awal
✅ **Konsisten**: Soal sudah tervalidasi dan berkualitas
✅ **Random**: Bisa ambil soal random dari bank untuk variasi
✅ **Reusable**: Soal bisa dipakai berkali-kali untuk ujian berbeda

---

## Tips & Best Practices

### **1. Penamaan yang Baik**

**✅ DO:**
- `Bank Soal UTS Hukum Kontrak Semester Genap 2026`
- `Pilihan Ganda - Materi Pertemuan 1-7`
- `Kumpulan Soal Esai Analisis Kasus`

**❌ DON'T:**
- `Bank 1`
- `Soal`
- `Test`

### **2. Organisasi Bank Soal**

**Strategi A: Per Jenis Ujian**
```
📚 Bank Soal UTS Hukum Perbankan 2026
📚 Bank Soal UAS Hukum Perbankan 2026
📚 Bank Soal Quiz Mingguan Hukum Perbankan
```

**Strategi B: Per Topik/Materi**
```
📚 Bank Soal - Sistem Moneter Indonesia
📚 Bank Soal - Perbankan Syariah
📚 Bank Soal - Analisis Kredit
```

**Strategi C: Per Tipe Soal**
```
📚 Bank Soal Pilihan Ganda - Hukum Perbankan
📚 Bank Soal Esai - Hukum Perbankan
📚 Bank Soal Studi Kasus - Hukum Perbankan
```

### **3. Gunakan Tags Secara Efektif**

**Contoh tags yang baik**:
- **By Materi**: `bab-1`, `sistem-moneter`, `bank-sentral`
- **By Difficulty**: sudah ada field terpisah, tapi bisa juga: `easy`, `medium`, `hard`
- **By Topic**: `konsep-dasar`, `perhitungan`, `analisis`, `kasus`
- **By Coverage**: `sering-keluar`, `materi-utama`, `materi-tambahan`
- **By Pertemuan**: `pertemuan-1`, `pertemuan-2`, dst

### **4. Distribusi Tingkat Kesulitan**

**Untuk ujian regular (UTS/UAS)**:
- 30-40% Mudah (soal dasar/konsep)
- 40-50% Sedang (aplikasi/pemahaman)
- 10-20% Sulit (analisis/sintesis)

**Untuk quiz**:
- 50% Mudah
- 40% Sedang
- 10% Sulit

### **5. Buat Penjelasan yang Detail**

Setiap soal sebaiknya punya penjelasan karena:
- ✅ Membantu mahasiswa belajar dari kesalahan
- ✅ Mengurangi pertanyaan "kenapa jawaban saya salah?"
- ✅ Meningkatkan kualitas pembelajaran

### **6. Review Berkala**

- Review bank soal setiap semester
- Update soal yang sudah outdated
- Hapus soal yang kurang relevan
- Tambah soal baru sesuai perkembangan materi

### **7. Kolaborasi dengan Dosen Lain**

- Centang **"Bagikan ke dosen lain"** untuk bank soal berkualitas
- Manfaatkan bank soal shared dari dosen lain
- Diskusi quality control soal bersama tim pengajar

### **8. Skor yang Proporsional**

**Contoh pemberian skor**:
- Pilihan Ganda: 1 poin per soal
- Benar/Salah: 0.5 - 1 poin per soal
- Esai pendek: 5-10 poin
- Esai panjang: 10-20 poin
- Studi Kasus: 15-30 poin

### **9. Backup Data**

⚠️ **PENTING**: 
- Export/backup bank soal secara berkala
- Simpan versi offline (screenshot atau export)
- Jangan mengandalkan sistem 100%

---

## FAQ

### **Q1: Siapa saja yang bisa mengakses Bank Soal?**
**A:** Hanya user dengan role:
- **DOSEN** (pembuat atau shared)
- **ADMIN_AKADEMIK**
- **SUPER_ADMIN**

Dan harus punya permission `nilai.view`.

---

### **Q2: Bagaimana jika saya ingin bank soal hanya untuk saya saja?**
**A:** Saat membuat bank soal, **jangan centang** opsi "Bagikan ke dosen lain". Bank soal akan bersifat privat dan hanya Anda (atau SUPER_ADMIN) yang bisa lihat.

---

### **Q3: Bisa tidak menggunakan soal dari bank soal dosen lain?**
**A:** **Bisa**, jika bank soal tersebut di-share (flag `is_shared = true`). Anda bisa lihat dan ambil soal dari bank tersebut untuk ujian Anda.

---

### **Q4: Berapa banyak soal yang bisa ditambahkan dalam 1 bank soal?**
**A:** **Unlimited**. Tapi untuk kemudahan manajemen, disarankan 20-50 soal per bank. Jika lebih banyak, pertimbangkan buat bank soal terpisah.

---

### **Q5: Bisa tidak edit soal setelah digunakan di ujian?**
**A:** **Bisa**, tapi **tidak disarankan** jika ujian sedang berlangsung. Edit bisa mempengaruhi ujian yang sedang berjalan. Sebaiknya edit sebelum ujian atau setelah ujian selesai.

---

### **Q6: Bagaimana cara menghapus bank soal?**
**A:** 
1. Di list Bank Soal
2. Klik ikon **🗑️ (trash)** pada bank yang ingin dihapus
3. Konfirmasi
4. Bank soal dan **semua soal di dalamnya** akan terhapus

⚠️ **Perhatian**: Tindakan ini **tidak bisa dibatalkan**. Pastikan backup dulu jika perlu.

---

### **Q7: Apakah soal di bank soal bisa digunakan berkali-kali?**
**A:** **Ya!** Itu justru tujuan utama Bank Soal. Anda bisa menggunakan soal yang sama untuk berbagai ujian berbeda.

---

### **Q8: Bagaimana cara mencari soal dengan tags tertentu?**
**A:** Saat ini belum ada fitur search by tags di halaman list. Tapi Anda bisa:
1. Masuk ke detail bank soal
2. Scroll untuk lihat soal-soal
3. Soal dengan tags yang sama akan ditampilkan dengan badge tags

**Future Enhancement**: Fitur filter/search by tags bisa ditambahkan.

---

### **Q9: Bisa tidak import soal dari Excel ke Bank Soal?**
**A:** Saat ini **belum tersedia**. Harus input manual satu per satu. Fitur import Excel bisa ditambahkan di versi berikutnya.

---

### **Q10: Bagaimana cara print/export bank soal?**
**A:** Saat ini belum ada fitur export built-in. Workaround:
1. Buka halaman detail bank soal
2. Print halaman (Ctrl+P)
3. Atau screenshot manual

**Future Enhancement**: Fitur export ke PDF/Word bisa ditambahkan.

---

### **Q11: Berapa skor maksimal yang bisa diset per soal?**
**A:** **Unlimited**. Tapi secara umum:
- Pilihan Ganda/Benar-Salah: 0.5 - 5 poin
- Esai/Studi Kasus: 5 - 50 poin

Sesuaikan dengan total skor ujian Anda.

---

### **Q12: Bisa tidak soal tanpa jawaban benar (untuk soal esai)?**
**A:** **Bisa**. Untuk soal tipe **Esai**, **Studi Kasus**, atau **Upload File**, field "Jawaban Benar" bisa dikosongkan karena akan dinilai manual oleh dosen.

---

### **Q13: Bagaimana cara mengurutkan soal dalam bank?**
**A:** Saat ini soal diurutkan berdasarkan urutan pembuatan (yang paling baru di bawah). Fitur drag-and-drop reorder bisa ditambahkan di versi berikutnya.

---

### **Q14: Apakah ada notifikasi jika bank soal di-share?**
**A:** Saat ini **belum ada** notifikasi otomatis. Dosen lain harus eksplor sendiri bank soal yang di-share.

---

### **Q15: Bisa tidak membatasi akses bank soal hanya untuk program studi tertentu?**
**A:** Saat ini belum ada fitur granular permission. Bank soal yang di-share bisa dilihat semua dosen. Fitur role-based sharing bisa ditambahkan nanti.

---

## Kesimpulan

**Bank Soal** adalah fitur powerful untuk:
- ✅ Menghemat waktu pembuatan ujian
- ✅ Menjaga kualitas dan konsistensi soal
- ✅ Memfasilitasi kolaborasi antar dosen
- ✅ Membangun repository soal yang berkualitas

**Workflow Ideal**:
```
1. Buat Bank Soal per mata kuliah/topik
   ↓
2. Isi dengan soal-soal berkualitas
   ↓
3. Kategorisasi dengan tags & difficulty
   ↓
4. Share jika ingin kolaborasi
   ↓
5. Gunakan saat buat ujian
   ↓
6. Review & update berkala
```

**Mulai Sekarang!**
1. Login sebagai Dosen
2. Menu **Penilaian → Bank Soal**
3. Klik **"Buat Bank Soal"**
4. Mulai bangun koleksi soal Anda!

---

**Selamat menggunakan Bank Soal! 📚✨**

Jika ada pertanyaan atau butuh bantuan, hubungi Admin Akademik atau IT Support.

---

**Last Updated**: Juli 2026  
**Version**: 1.0.0
