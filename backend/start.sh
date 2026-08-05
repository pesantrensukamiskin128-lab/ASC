#!/bin/sh

echo "=== Al-Jawami Smart Campus (ASC) Backend — Railway Start Script ==="
echo "→ PORT=${PORT:-8080}"
echo "→ APP_ENV=${APP_ENV:-unknown}"
echo "→ DB_CONNECTION=${DB_CONNECTION:-unknown}"

# Pastikan storage dan bootstrap/cache writable
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Bersihkan config cache lama (dari build time)
echo "→ Clearing old config cache..."
php artisan config:clear  || true
php artisan route:clear   || true
php artisan view:clear    || true

# Rebuild cache dengan env vars Railway (runtime)
echo "→ Caching config..."
php artisan config:cache || echo "WARN: config:cache failed, continuing without cache"
php artisan route:cache  || echo "WARN: route:cache failed, continuing without cache"
php artisan view:cache   || echo "WARN: view:cache failed, continuing without cache"

# Jalankan migrasi — jika gagal, log warning tapi jangan crash server
echo "→ Running migrations..."
php artisan migrate --force || echo "WARN: migrate failed, check DB connection"

# Storage symlink
echo "→ Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

# Mulai server PHP — ini harus selalu jalan
APP_PORT="${PORT:-8080}"
echo "=== Starting PHP server on 0.0.0.0:${APP_PORT} ==="
exec php -S "0.0.0.0:${APP_PORT}" -t public
