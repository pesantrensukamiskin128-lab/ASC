# Panduan Deploy SIAKAD di Hostinger

## Persyaratan

- Hostinger paket **Business** atau **Cloud** (butuh PHP 8.3+, MySQL, SSH access)
- Domain sudah terhubung ke Hostinger
- SSH access aktif (aktifkan via hPanel → Advanced → SSH Access)

---

## Struktur Deploy

```
public_html/
├── api/              ← Backend Laravel (subfolder public)
├── index.html        ← Frontend Vue (build result)
├── assets/           ← Frontend assets
└── .htaccess         ← Routing

storage -> /home/user/storage   ← Symlink
```

Pendekatan: **Frontend di root domain**, **Backend di subdomain/subfolder `api`**.

Opsi yang direkomendasikan: 
- Frontend: `siakad.domain.ac.id`
- Backend API: `api.siakad.domain.ac.id` (subdomain)

---

## Langkah 1: Persiapan Lokal

### Build Frontend

```bash
cd frontend
npm run build
```

Hasil build ada di folder `frontend/dist/`.

### Konfigurasi Frontend Environment

Buat file `frontend/.env.production`:
```env
VITE_API_URL=https://api.siakad.domain.ac.id/api
VITE_APP_NAME="SIAT Al-Jawami"
```

Lalu build ulang:
```bash
npm run build
```

### Persiapan Backend

```bash
cd backend

# Pastikan .env.production siap
cp .env .env.production
```

Edit `.env.production`:
```env
APP_NAME="SIAT Al-Jawami"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.siakad.domain.ac.id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_siakad
DB_USERNAME=u123456789_siakad
DB_PASSWORD=PasswordKuatAnda123!

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=siakad.domain.ac.id
```

---

## Langkah 2: Buat Database di Hostinger

1. Login ke **hPanel** → **Databases** → **MySQL Databases**
2. Buat database baru (catat nama, username, password)
3. Nama biasanya format: `u123456789_siakad`

---

## Langkah 3: Upload Backend

### Opsi A: Via SSH (Direkomendasikan)

```bash
# SSH ke Hostinger
ssh u123456789@domain.ac.id -p 65002

# Buat folder backend di luar public_html
mkdir -p ~/siakad-backend
```

Upload file backend (tanpa `vendor`, `node_modules`):
```bash
# Dari lokal, gunakan rsync atau scp
rsync -avz --exclude='vendor' --exclude='node_modules' --exclude='.env' \
  backend/ u123456789@domain.ac.id:~/siakad-backend/ -e "ssh -p 65002"
```

### Opsi B: Tanpa SSH (via File Manager + Terminal hPanel)

Jika SSH tidak tersedia atau tidak familiar, gunakan cara ini:

#### 1. Persiapan di Lokal

```bash
cd backend

# Install vendor dulu di lokal (WAJIB, karena tanpa SSH tidak bisa jalankan composer)
composer install --no-dev --optimize-autoloader

# Buat file .env production
# (edit sesuai data database Hostinger)

# Generate key di lokal
php artisan key:generate

# Compress seluruh folder backend jadi ZIP
# KECUALIKAN: node_modules, .git, tests, storage/logs/*
```

Buat ZIP yang berisi:
```
siakad-backend.zip
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/         (struktur folder saja, tanpa isi logs)
├── vendor/          (WAJIB ikut karena tidak bisa composer install)
├── .env             (sudah dikonfigurasi production)
├── artisan
├── composer.json
└── composer.lock
```

> **Penting:** Folder `vendor/` HARUS ikut di-upload karena tanpa SSH tidak bisa jalankan `composer install` di server.

#### 2. Upload via File Manager

1. Login **hPanel** → **Files** → **File Manager**
2. Navigasi ke `/home/u123456789/` (di atas `public_html`)
3. Klik **Upload** → pilih `siakad-backend.zip`
4. Tunggu upload selesai (bisa 50-100MB tergantung vendor)
5. Klik kanan file ZIP → **Extract** → extract ke folder `siakad-backend`
6. Pastikan struktur folder benar: `/home/u123456789/siakad-backend/artisan` harus ada

#### 3. Jalankan Migration via Hostinger Terminal

hPanel → **Advanced** → **Terminal** (atau **PHP command**):

Jika hPanel punya fitur Terminal web:
```bash
cd ~/siakad-backend
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

**Jika tidak ada Terminal**, buat file sementara untuk migration:

Buat file `public_html/migrate.php`:
```php
<?php
// HAPUS FILE INI SETELAH SELESAI!
chdir('/home/u123456789/siakad-backend');
require __DIR__ . '/../siakad-backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../siakad-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->call('migrate', ['--force' => true]);
echo '<pre>' . $kernel->output() . '</pre>';
echo '<hr>';
$kernel->call('db:seed', ['--force' => true]);
echo '<pre>' . $kernel->output() . '</pre>';
echo '<hr>';
$kernel->call('storage:link');
echo '<pre>' . $kernel->output() . '</pre>';
echo '<br><b>SELESAI! HAPUS FILE INI SEKARANG!</b>';
```

Akses `https://domain.ac.id/migrate.php` di browser, lalu **HAPUS file tersebut segera** setelah selesai.

#### 4. Set Permission via File Manager

1. File Manager → navigasi ke `siakad-backend/storage`
2. Klik kanan → **Permissions** → set `775` (recursive)
3. Lakukan hal sama untuk `siakad-backend/bootstrap/cache`

#### 5. Symlink public (via .htaccess)

Karena tanpa SSH tidak bisa buat symlink, gunakan .htaccess redirect:

Buat folder subdomain `api.siakad.domain.ac.id` di hPanel → Subdomains.
Di folder subdomain tersebut, buat file `.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_URI} !^/siakad-backend/public/
    RewriteRule ^(.*)$ /siakad-backend/public/$1 [L]
</IfModule>
```

Atau lebih simpel — set **Document Root** subdomain langsung ke `/siakad-backend/public` via hPanel jika tersedia.

---

### Install Dependencies di Server (hanya untuk Opsi A - SSH)

```bash
cd ~/siakad-backend

# Upload .env.production sebagai .env
# (upload manual atau via File Manager hPanel)

# Install composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Install dependencies
php composer.phar install --no-dev --optimize-autoloader

# Generate key
php artisan key:generate

# Migrate database
php artisan migrate --force

# Seed data awal
php artisan db:seed --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link
php artisan storage:link
```

---

## Langkah 4: Konfigurasi Subdomain API

### Buat Subdomain

1. hPanel → **Domains** → **Subdomains**
2. Tambah subdomain: `api.siakad.domain.ac.id`
3. Arahkan document root ke: `/home/u123456789/siakad-backend/public`

Atau jika tidak bisa set custom document root, buat symlink:
```bash
# Hapus folder subdomain default
rm -rf ~/domains/api.siakad.domain.ac.id/public_html

# Symlink ke Laravel public
ln -s ~/siakad-backend/public ~/domains/api.siakad.domain.ac.id/public_html
```

### Buat .htaccess di Backend Public

File `siakad-backend/public/.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# CORS Headers
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "https://siakad.domain.ac.id"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header set Access-Control-Allow-Credentials "false"
</IfModule>
```

---

## Langkah 5: Upload Frontend

### Upload Build Result

Upload isi folder `frontend/dist/` ke `public_html/` domain utama:

```bash
rsync -avz frontend/dist/ u123456789@domain.ac.id:~/public_html/ -e "ssh -p 65002"
```

### Buat .htaccess untuk SPA Routing

File `public_html/.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

---

## Langkah 6: SSL Certificate

1. hPanel → **SSL** → **Install SSL**
2. Aktifkan SSL untuk kedua domain:
   - `siakad.domain.ac.id`
   - `api.siakad.domain.ac.id`
3. Force HTTPS: hPanel → **Domains** → **Force HTTPS** → Enable

---

## Langkah 7: Konfigurasi PHP

hPanel → **Advanced** → **PHP Configuration**:

- PHP Version: **8.3**
- Extensions aktifkan: `zip`, `gd`, `mbstring`, `pdo_mysql`, `exif`
- `memory_limit`: 256M
- `upload_max_filesize`: 10M
- `post_max_size`: 12M
- `max_execution_time`: 300

---

## Langkah 8: Cron Job (Queue & Scheduler)

hPanel → **Advanced** → **Cron Jobs**:

```bash
# Laravel Scheduler (setiap menit)
* * * * * cd /home/u123456789/siakad-backend && php artisan schedule:run >> /dev/null 2>&1

# Queue Worker (setiap 5 menit, restart jika ada job)
*/5 * * * * cd /home/u123456789/siakad-backend && php artisan queue:work --stop-when-empty --max-time=240 >> /dev/null 2>&1
```

---

## Langkah 9: Verifikasi

1. Buka `https://siakad.domain.ac.id` → halaman login harus tampil
2. Buka `https://api.siakad.domain.ac.id/api/institution/public` → response JSON
3. Login dengan akun superadmin → dashboard tampil
4. Buka `https://siakad.domain.ac.id/pmb` → halaman PMB publik tampil

---

## Update Deployment

### Update Backend
```bash
cd ~/siakad-backend
git pull origin main  # atau upload file baru

php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Update Frontend
```bash
# Dari lokal
cd frontend
npm run build

# Upload
rsync -avz dist/ u123456789@domain.ac.id:~/public_html/ -e "ssh -p 65002"
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| 500 Internal Server Error | Cek `storage/logs/laravel.log`, pastikan permission `storage/` 775 |
| CORS error | Pastikan `.env` CORS origins dan .htaccess header benar |
| Login gagal (401) | Pastikan `SANCTUM_STATEFUL_DOMAINS` sesuai domain frontend |
| File upload gagal | Cek `storage:link`, pastikan `storage/app/public` writable |
| Halaman blank (frontend) | Pastikan `.htaccess` SPA routing ada di public_html |
| PMB foto tidak tampil | Cek symlink storage dan CORS untuk path `/storage/*` |

### Fix Permission
```bash
cd ~/siakad-backend
chmod -R 775 storage bootstrap/cache
chown -R u123456789:u123456789 storage bootstrap/cache
```

---

## Alternatif: Single Domain (tanpa subdomain)

Jika tidak bisa buat subdomain, deploy backend di subfolder:

```
public_html/
├── api/          ← symlink ke siakad-backend/public
├── index.html    ← frontend
└── assets/
```

```bash
ln -s ~/siakad-backend/public ~/public_html/api
```

Frontend env:
```env
VITE_API_URL=/api/api
```

Backend `.env`:
```env
APP_URL=https://siakad.domain.ac.id/api
```
