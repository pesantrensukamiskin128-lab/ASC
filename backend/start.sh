#!/bin/sh
set -e

echo "=== SIAKAD Backend — Railway Start Script ==="

# Pastikan storage dan bootstrap/cache writable
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Jalankan migrasi database
echo "→ Running migrations..."
php artisan migrate --force

# Buat storage symlink (diabaikan jika gagal — Railway punya persistent volume)
echo "→ Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

# Clear dan rebuild cache (diperlukan jika APP_URL berubah)
echo "→ Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Starting PHP server on port ${PORT:-8080} ==="
exec php -S 0.0.0.0:${PORT:-8080} -t public
