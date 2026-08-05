#!/bin/sh
set -e

echo "=== Al-Jawami Smart Campus (ASC) Backend — Railway Start Script ==="

# Pastikan storage dan bootstrap/cache writable
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Bersihkan config cache lama (dari build time) dan rebuild dengan env Railway
echo "→ Clearing old config cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache dengan env vars yang sudah terload dari Railway
echo "→ Caching config with runtime env..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Jalankan migrasi database (setelah config ter-cache dengan DB yang benar)
echo "→ Running migrations..."
php artisan migrate --force

# Buat storage symlink (diabaikan jika gagal — Railway punya persistent volume)
echo "→ Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

echo "=== Starting PHP server on port ${PORT:-8080} ==="
exec php -S 0.0.0.0:${PORT:-8080} -t public
