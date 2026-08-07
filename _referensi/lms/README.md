# LMS STAI Yapata Al-Jawami Bandung

LMS berbasis PHP 8.3 dan MySQL/MariaDB yang dirancang untuk dipasang langsung pada cPanel. Aplikasi tidak membutuhkan Composer, Node.js, atau akses terminal pada server produksi.

## Modul yang tersedia

- Login dan dashboard berbasis peran: Super Administrator, Administrator Akademik, Dosen, Mahasiswa, Kaprodi, dan LPM/UPM.
- Data program studi, tahun akademik, mata kuliah, kelas, dosen pengampu, serta peserta kelas.
- Ruang kelas untuk materi, pertemuan, jurnal mengajar, token presensi, tugas, pengumpulan berkas, penilaian, dan buku nilai.
- CPMK dan pemetaan asesmen dasar berbasis OBE.
- Monitoring mutu pembelajaran dan ekspor laporan CSV.
- Pengumuman, kalender akademik, notifikasi, profil, pengaturan institusi, dan audit aktivitas.
- Impor pengguna melalui CSV.
- REST API dengan Bearer token untuk persiapan integrasi SIAKAD.
- Installer web yang membuat tabel, data institusi, program studi, akun administrator, dan data demonstrasi opsional.

## Persyaratan

- PHP 8.3 atau lebih baru.
- MySQL 5.7+/MariaDB 10.4+.
- Ekstensi PHP: PDO MySQL, mbstring, fileinfo, OpenSSL.
- Apache dengan `.htaccess`/mod_rewrite direkomendasikan.
- HTTPS wajib untuk penggunaan produksi.

Lihat `INSTALL.md` untuk pemasangan dan `API.md` untuk integrasi.

## Keamanan bawaan

- Kata sandi disimpan dengan `password_hash()`.
- PDO prepared statements dan CSRF token pada formulir.
- Cookie sesi HttpOnly, SameSite, dan Secure ketika HTTPS aktif.
- Pembatasan hak akses berbasis peran dan keanggotaan kelas.
- File unggahan disimpan di direktori terlindungi dan disajikan melalui pemeriksaan akses.
- Token API disimpan sebagai hash SHA-256; token asli hanya ditampilkan satu kali.
- Audit log untuk login, perubahan data, presensi, pengumpulan tugas, dan penilaian.

## Catatan produksi

Sebelum dipakai secara resmi, ganti seluruh akun demo, aktifkan SSL resmi, atur backup harian, dan lakukan uji penerimaan pengguna. Integrasi langsung dengan SIAKAD memerlukan pemetaan data dan kredensial API dari sistem SIAKAD.

