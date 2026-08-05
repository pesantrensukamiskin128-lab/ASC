#!/bin/sh

echo "=== Al-Jawami Smart Campus (ASC) Backend — Railway Start Script ==="
echo "→ PORT=${PORT:-8080}"
echo "→ APP_ENV=${APP_ENV:-unknown}"
echo "→ DB_CONNECTION=${DB_CONNECTION:-unknown}"

# ============================================================
# STORAGE INIT — wajib karena Railway Volume mount menimpa
# folder storage/ yang ada di image dengan volume kosong.
# Buat ulang struktur direktori yang dibutuhkan Laravel.
# ============================================================
echo "→ Initializing storage directories..."
mkdir -p storage/app/public/logos
mkdir -p storage/app/public/letterheads
mkdir -p storage/app/private
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set permission — jalankan sebagai root jadi tidak perlu sudo
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ============================================================
# CONFIG CACHE — clear dulu, rebuild dengan env Railway
# ============================================================
echo "→ Clearing old config cache..."
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true

echo "→ Rebuilding config cache..."
php artisan config:cache || echo "WARN: config:cache failed"
php artisan route:cache  || echo "WARN: route:cache failed"
php artisan view:cache   || echo "WARN: view:cache failed"

# ============================================================
# DATABASE
# ============================================================
echo "→ Running migrations..."
php artisan migrate --force || echo "WARN: migrate failed, check DB connection"

# Seed hanya jika tabel institutions kosong (deploy pertama)
echo "→ Checking if seed is needed..."
INST_COUNT=$(php artisan tinker --no-interaction --execute="echo \App\Models\Institution::count();" 2>/dev/null | tail -1 | tr -d '[:space:]')
if [ "$INST_COUNT" = "0" ] || [ -z "$INST_COUNT" ]; then
    echo "→ Running seeders (first deploy)..."
    php artisan db:seed --force || echo "WARN: seeder failed"
else
    echo "→ Seed skipped (data already exists)"
fi

# ============================================================
# STORAGE LINK — buat symlink public/storage -> storage/app/public
# Harus dilakukan setiap restart karena public/ ada di ephemeral layer
# ============================================================
echo "→ Creating storage link..."
php artisan storage:link --force || echo "WARN: storage:link failed"

# Verifikasi symlink
if [ -L "public/storage" ]; then
    echo "→ Storage link OK: $(readlink public/storage)"
else
    echo "WARN: Storage symlink not created, trying manual fallback..."
    ln -sfn /app/storage/app/public /app/public/storage || true
fi

# ============================================================
# START SERVER
# ============================================================
APP_PORT="${PORT:-8080}"
echo "=== Starting PHP server on 0.0.0.0:${APP_PORT} ==="
exec php -S "0.0.0.0:${APP_PORT}" -t public
