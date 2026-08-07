# Panduan Deploy SIAKAD ke Railway

Railway adalah platform cloud modern yang mendukung deploy via GitHub dengan build otomatis.
Aplikasi ini terdiri dari **2 service** yang di-deploy terpisah:

| Service | Teknologi | Build |
|---|---|---|
| **Backend** | Laravel 13 + PHP 8.3 | Dockerfile |
| **Frontend** | Vue 3 + Vite | Dockerfile + Nginx |

> **Catatan penting storage:** Railway menggunakan ephemeral filesystem — file yang di-upload
> (logo, kop surat, dsb) akan **hilang saat redeploy** tanpa Volume. Baca bagian
> [Storage Persisten](#6-storage-persisten-volume) sebelum go-live.

---

## Prasyarat

- Akun Railway ([railway.com](https://railway.com)) — paket Hobby ($5/bulan) sudah cukup
- Repository GitHub (backend dan frontend bisa di-repo yang sama atau terpisah)
- Git sudah dikonfigurasi di lokal

---

## Struktur Repository

Pastikan struktur ini ada di GitHub:

```
SIAKAD/
├── backend/          ← Laravel (Dockerfile ada di sini)
│   ├── Dockerfile
│   ├── nixpacks.toml
│   ├── start.sh
│   └── ...
└── frontend/         ← Vue (Dockerfile ada di sini)
    ├── Dockerfile
    ├── nginx.conf
    └── ...
```

Jika repo terpisah, masing-masing root-nya langsung berisi `Dockerfile`.

---

## Langkah 1: Siapkan Repository GitHub

### 1.1 Pastikan file deploy sudah ada

File-file berikut sudah dibuat dan siap di-commit:

```
backend/
  ├── Dockerfile          ✅ sudah ada
  ├── nixpacks.toml       ✅ sudah ada
  ├── start.sh            ✅ sudah ada
  └── .env.railway        ✅ template env (jangan commit .env asli!)

frontend/
  ├── Dockerfile          ✅ sudah ada
  ├── nginx.conf          ✅ sudah ada
  └── .env.railway        ✅ template env
```

### 1.2 Generate APP_KEY untuk production

```bash
cd backend
php artisan key:generate --show
```

Simpan hasilnya (format: `base64:xxxx...`), akan dipakai di Railway Variables.

### 1.3 Commit dan push

```bash
git add backend/Dockerfile backend/nixpacks.toml backend/start.sh
git add frontend/Dockerfile frontend/nginx.conf
git add backend/config/cors.php
git commit -m "chore: add Railway deployment config"
git push origin main
```

---

## Langkah 2: Buat Project di Railway

1. Buka [railway.com](https://railway.com) → **New Project**
2. Pilih **"Deploy from GitHub repo"**
3. Authorize Railway ke GitHub, pilih repository SIAKAD
4. Railway akan mendeteksi kode — klik **"Add services"** → pilih folder `backend`
5. Klik **Deploy** (biarkan gagal dulu, kita set variabel dulu di langkah berikutnya)

---

## Langkah 3: Tambah MySQL Database

Di dalam project yang sama:

1. Klik **"+ New"** → **"Database"** → **"Add MySQL"**
2. Railway otomatis membuat MySQL dan mengisi variabel koneksi
3. Klik service MySQL → tab **"Variables"** → catat nilai:
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`

> Railway menyediakan **reference variables** sehingga backend bisa langsung pakai
> `${{MySQL.MYSQLHOST}}` tanpa harus copy-paste nilai manual.

---

## Langkah 4: Konfigurasi Backend Service

### 4.1 Set Root Directory (jika mono-repo)

Jika backend dan frontend ada di satu repo:

1. Klik service **backend** → **Settings**
2. **Source** → **Root Directory** → isi: `backend`
3. **Build** → pastikan **Builder** = `Dockerfile`

### 4.2 Set Environment Variables

Klik service backend → **Variables** → tambahkan satu per satu:

```
APP_NAME          = Al-Jawami Smart Campus
APP_ENV           = production
APP_DEBUG         = false
APP_KEY           = base64:HASIL_KEY_GENERATE_TADI
APP_URL           = https://AKAN_DIISI_SETELAH_DOMAIN_RAILWAY_DIBUAT

DB_CONNECTION     = mysql
DB_HOST           = ${{MySQL.MYSQLHOST}}
DB_PORT           = ${{MySQL.MYSQLPORT}}
DB_DATABASE       = ${{MySQL.MYSQLDATABASE}}
DB_USERNAME       = ${{MySQL.MYSQLUSER}}
DB_PASSWORD       = ${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER    = database
SESSION_LIFETIME  = 120
CACHE_STORE       = database
QUEUE_CONNECTION  = database
FILESYSTEM_DISK   = public

LOG_CHANNEL       = stderr
LOG_LEVEL         = warning

APP_LOCALE        = id
APP_FALLBACK_LOCALE = id

CORS_ALLOWED_ORIGINS = https://AKAN_DIISI_SETELAH_DOMAIN_FRONTEND_DIBUAT
```

> `APP_URL` dan `CORS_ALLOWED_ORIGINS` diisi setelah domain Railway dibuat di langkah 4.3.

### 4.3 Generate Domain Backend

1. Klik service backend → **Settings** → **Networking**
2. Klik **"Generate Domain"**
3. Catat URL yang dihasilkan, contoh: `siakad-backend.up.railway.app`
4. Kembali ke **Variables**, update:
   - `APP_URL` → `https://siakad-backend.up.railway.app`

### 4.4 Deploy Backend

Klik **"Deploy"** pada service backend. Monitor log di tab **Logs**:

```
→ Running migrations...
→ Creating storage link...
→ Caching config...
=== Starting PHP server on port 8080 ===
```

Jika berhasil, test endpoint:
```
https://siakad-backend.up.railway.app/api/institution/public
```
Harus mengembalikan JSON response.

---

## Langkah 5: Konfigurasi Frontend Service

### 5.1 Tambah Service Frontend

Di Railway project yang sama:

1. Klik **"+ New"** → **"GitHub Repo"** → pilih repo yang sama
2. Klik service baru → **Settings** → **Root Directory** → isi: `frontend`
3. **Build** → **Builder** = `Dockerfile`

### 5.2 Set Environment Variables Frontend

```
VITE_API_URL = https://siakad-backend.up.railway.app/api
```

> `VITE_API_URL` **harus di-set sebelum build** karena Vite meng-embed URL ini ke
> dalam bundle JavaScript saat proses build. Jika diubah, harus redeploy.

### 5.3 Generate Domain Frontend

1. Klik service frontend → **Settings** → **Networking** → **"Generate Domain"**
2. Catat URL, contoh: `siakad-frontend.up.railway.app`

### 5.4 Update CORS di Backend

Kembali ke service backend → **Variables**, update:

```
CORS_ALLOWED_ORIGINS = https://siakad-frontend.up.railway.app
```

Lalu klik **"Redeploy"** pada service backend agar CORS config ter-cache ulang.

### 5.5 Deploy Frontend

Klik **"Deploy"** pada service frontend. Monitor log hingga:

```
Successfully built
nginx started
```

Test di browser: `https://siakad-frontend.up.railway.app`

Halaman login harus tampil dengan logo institusi.

---

## Langkah 6: Storage Persisten (Volume)

> **WAJIB** jika ingin file upload (logo, kop surat, foto, dsb) bertahan saat redeploy.

Tanpa Volume, setiap kali Railway redeploy, filesystem bersih dan semua file upload hilang.

### 6.1 Tambah Volume ke Backend

1. Klik service backend → **"+ Add Volume"** (atau dari command palette: `⌘K` → "Add Volume")
2. **Mount Path**: `/app/storage`
3. Klik **"Add"**

### 6.2 Sinkronisasi Storage yang Sudah Ada

Setelah Volume terpasang, upload ulang file yang sudah ada (logo, kop surat):

**Opsi A — Via aplikasi langsung:**
- Login ke SIAKAD → Master Data → Institusi
- Upload ulang logo dan kop surat

**Opsi B — Via Railway CLI (untuk upload massal):**
```bash
# Install Railway CLI
npm install -g @railway/cli

# Login
railway login

# Upload file ke volume
railway volume cp ./storage/app/public/ /app/storage/app/public/
```

### 6.3 Verifikasi

Setelah upload logo, akses:
```
https://siakad-backend.up.railway.app/storage/logos/[nama-file].png
```
Harus menampilkan gambar, bukan 404.

---

## Langkah 7: Custom Domain (Opsional)

Jika punya domain sendiri (misal `siat.stai-aljawami.ac.id`):

### Backend
1. Service backend → **Settings** → **Networking** → **"Add Custom Domain"**
2. Masukkan `api.siat.stai-aljawami.ac.id`
3. Railway tampilkan nilai CNAME yang harus ditambahkan di DNS
4. Update variabel:
   - `APP_URL` → `https://api.siat.stai-aljawami.ac.id`
   - `CORS_ALLOWED_ORIGINS` → `https://siat.stai-aljawami.ac.id`
5. Update `VITE_API_URL` di service frontend → `https://api.siat.stai-aljawami.ac.id/api`
6. **Redeploy kedua service**

### Frontend
1. Service frontend → **Settings** → **Networking** → **"Add Custom Domain"**
2. Masukkan `siat.stai-aljawami.ac.id`
3. Tambahkan CNAME di DNS sesuai instruksi Railway

SSL/TLS otomatis diurus Railway (Let's Encrypt).

---

## Langkah 8: Seeder Data Awal (Pertama Kali)

Setelah deploy pertama berhasil, jalankan seeder via Railway CLI:

```bash
# Connect ke shell Railway
railway run --service backend php artisan db:seed --force
```

Atau via Railway dashboard → service backend → **"+ New"** → **"Run Command"**:
```
php artisan db:seed --force
```

---

## Update Deployment (Setelah Go-Live)

Setiap kali push ke branch `main`, Railway otomatis redeploy.

### Update Backend saja
```bash
git add backend/
git commit -m "fix: ..."
git push origin main
# Railway otomatis detect perubahan di folder backend dan rebuild
```

### Update Frontend saja
```bash
git add frontend/
git commit -m "feat: ..."
git push origin main
```

> Jika hanya update frontend dan `VITE_API_URL` tidak berubah,
> tidak perlu redeploy backend.

---

## Monitoring & Logs

- **Logs real-time**: Klik service → tab **"Logs"**
- **Metrics**: Klik service → tab **"Metrics"** (CPU, RAM, network)
- **Database**: Klik MySQL service → **"Data"** → bisa query langsung

### Cek error Laravel
Log Laravel ditulis ke stderr (sesuai `LOG_CHANNEL=stderr`), langsung terlihat di tab Logs Railway.

---

## Troubleshooting

| Masalah | Penyebab | Solusi |
|---|---|---|
| Build gagal "composer not found" | Dockerfile tidak terbuild | Cek Railway pakai Dockerfile, bukan Nixpacks |
| `php artisan migrate` gagal | DB_HOST salah | Pastikan pakai reference variable `${{MySQL.MYSQLHOST}}` |
| CORS error di browser | `CORS_ALLOWED_ORIGINS` belum diset | Isi dengan URL frontend Railway, redeploy backend |
| Logo tidak tampil | Volume belum dipasang / file belum di-upload | Pasang Volume di `/app/storage`, upload ulang logo |
| `storage:link` gagal | Normal di Railway (ephemeral) | File tetap bisa diakses via Volume mount |
| Frontend blank page | `VITE_API_URL` salah atau tidak di-set | Cek Variables frontend, redeploy |
| 500 error setelah deploy | Config cache belum diperbarui | `railway run php artisan config:clear` |
| Login gagal (401) | `APP_KEY` belum di-set | Set `APP_KEY` di Variables backend |
| Session error | `SESSION_DRIVER` salah | Pastikan `SESSION_DRIVER=database` dan migration sudah jalan |

---

## Ringkasan Variabel Railway

### Backend Service Variables

| Key | Nilai |
|---|---|
| `APP_NAME` | SIAT Al-Jawami |
| `APP_ENV` | production |
| `APP_DEBUG` | false |
| `APP_KEY` | `base64:...` (dari `php artisan key:generate --show`) |
| `APP_URL` | `https://xxx.up.railway.app` |
| `DB_CONNECTION` | mysql |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` | database |
| `CACHE_STORE` | database |
| `QUEUE_CONNECTION` | database |
| `FILESYSTEM_DISK` | public |
| `LOG_CHANNEL` | stderr |
| `LOG_LEVEL` | warning |
| `CORS_ALLOWED_ORIGINS` | `https://frontend-xxx.up.railway.app` |
| `APP_LOCALE` | id |
| `APP_FALLBACK_LOCALE` | id |

### Frontend Service Variables

| Key | Nilai |
|---|---|
| `VITE_API_URL` | `https://backend-xxx.up.railway.app/api` |

---

## Estimasi Biaya Railway

| Resource | Estimasi |
|---|---|
| Backend (PHP, ~256MB RAM) | ~$3–5/bulan |
| Frontend (Nginx, ~64MB RAM) | ~$1–2/bulan |
| MySQL (shared) | ~$1–3/bulan |
| Volume storage (1GB) | ~$0.25/bulan |
| **Total** | **~$5–10/bulan** |

Paket **Hobby ($5 credit/bulan)** biasanya sudah mencukupi untuk penggunaan awal.
