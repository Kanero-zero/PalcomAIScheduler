#!/usr/bin/env bash
# ==============================================================================
# Production Rollback Script: PALCOM AI Scheduler
# Emergency procedure to restore previous release and clear cache
# ==============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/palcom-ai-scheduler}"

echo "======================================================================"
echo "    MEMULAI EMERGENCY ROLLBACK: PALCOM AI SCHEDULER                  "
echo "======================================================================"

cd "$APP_DIR"

# 1. Masuk ke maintenance mode
echo "[1/6] Mengaktifkan maintenance mode..."
php artisan down || true

# 2. Revert Git commit sebelumnya
echo "[2/6] Membatalkan rilis ke commit sebelumnya (git reset --hard HEAD~1)..."
git reset --hard HEAD~1

# 3. Re-install Composer dependencies
echo "[3/6] Memulihkan dependensi Composer..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 4. Re-compile assets
echo "[4/6] Mengompilasi ulang aset..."
npm ci --silent
npm run build --silent

# 5. Clear & re-warm cache
echo "[5/6] Membersihkan dan memuat ulang cache aplikasi..."
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan queue:restart --quiet || true

# 6. Buka kembali aplikasi
echo "[6/6] Mematikan maintenance mode..."
php artisan up

echo "======================================================================"
echo "    ROLLBACK SELESAI: APLIKASI KEMBALI KE VERSI SEBELUMNYA           "
echo "======================================================================"
