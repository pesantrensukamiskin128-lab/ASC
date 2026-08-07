# Panduan Instalasi cPanel

## 1. Siapkan subdomain dan database

1. Buat subdomain, misalnya `lms.stai-aljawami.ac.id`.
2. Aktifkan SSL/HTTPS untuk subdomain tersebut.
3. Buat database MySQL melalui cPanel.
4. Buat pengguna database dan berikan **ALL PRIVILEGES** pada database LMS.
5. Catat host, port, nama database, username, dan password database. Pada beberapa hosting host MySQL bukan `localhost`; gunakan nilai yang diberikan penyedia hosting.

## 2. Unggah aplikasi

1. Buka File Manager cPanel.
2. Masuk ke document root subdomain LMS.
3. Unggah ZIP aplikasi dan ekstrak isinya langsung di document root. Pastikan `index.php`, `setup.php`, `.htaccess`, dan folder `app` berada di root, bukan di folder ganda.
4. Atur izin folder `config` dan `storage` menjadi dapat ditulis oleh PHP. Umumnya `755` sudah cukup; gunakan `775` jika konfigurasi server memerlukannya.

## 3. Jalankan installer

1. Buka `https://domain-lms/setup.php`.
2. Pastikan seluruh pemeriksaan server berwarna hijau.
3. Isi alamat LMS, data koneksi MySQL, serta akun administrator.
4. Centang data demonstrasi hanya jika ingin mencoba alur aplikasi sebelum memasukkan data sebenarnya.
5. Klik **Pasang LMS Sekarang** lalu masuk menggunakan akun administrator yang dibuat.

## 4. Pengaturan awal

1. Buka **Pengaturan Institusi** dan unggah logo resmi.
2. Periksa identitas, moto, alamat, website, warna, serta domain SIAKAD yang diizinkan.
3. Tetapkan tahun akademik aktif.
4. Masukkan pengguna melalui formulir atau impor CSV.
5. Tambahkan mata kuliah, buka kelas, tentukan dosen, dan masukkan peserta.
6. Jika data demo dipasang, hapus atau nonaktifkan akun demo sebelum produksi.

## 5. Pengamanan setelah instalasi

1. Hapus atau ubah nama `setup.php` setelah login berhasil.
2. Pastikan `config/config.php` tidak dapat diunduh dari browser.
3. Jangan membagikan token API melalui WhatsApp atau email biasa.
4. Gunakan kata sandi unik minimal 10 karakter untuk administrator.
5. Aktifkan backup harian database dan folder `storage/uploads`.
6. Simpan satu salinan backup di lokasi lain.

## Pemecahan masalah

### SQLSTATE[HY000] [2002]

Host MySQL kemungkinan tidak sesuai. Gunakan host database dari cPanel/penyedia hosting, bukan otomatis `localhost`.

### Error 500

- Pastikan PHP yang aktif adalah 8.3.
- Periksa ekstensi PDO MySQL, mbstring, fileinfo, dan OpenSSL.
- Pastikan `config` dan `storage` dapat ditulis.
- Baca `storage/logs/php-error.log` melalui File Manager.
- Jika server tidak mengizinkan directive tertentu di `.htaccess`, periksa Error Log cPanel dan hapus hanya directive yang disebutkan server.

### Halaman tampil tetapi tautan API 404

Pastikan `mod_rewrite` aktif dan file `.htaccess` ikut terunggah. Endpoint juga dapat diakses melalui `/api/index.php/v1/...` pada konfigurasi Apache tertentu.

