#!/usr/bin/env bash
# ==============================================================================
# Production Rollback Script: PALCOM AI Scheduler
# Emergency procedure to restore previous release and clear cache
# ==============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/PalcomAIScheduler}"
TARGET_REF="${1:-HEAD~1}"

echo "======================================================================"
echo "    MEMULAI EMERGENCY ROLLBACK: PALCOM AI SCHEDULER                  "
echo "    Target Rollback: ${TARGET_REF}                                    "
echo "======================================================================"

cd "$APP_DIR"

# 1. Pastikan target Git ref valid sebelum melakukan aksi apa pun
git rev-parse --verify "${TARGET_REF}" >/dev/null 2>&1 || {
    echo "[ERROR] Commit target '${TARGET_REF}' tidak ditemukan dalam riwayat Git."
    exit 1
}

# 2. Masuk ke maintenance mode
echo "[1/6] Mengaktifkan maintenance mode..."
php artisan down || true

# 3. Alihkan kode ke commit target
echo "[2/6] Mengalihkan kode ke versi ${TARGET_REF}..."
git checkout "${TARGET_REF}" || {
    echo "[FATAL] Gagal beralih ke commit ${TARGET_REF}."
    php artisan up || true
    exit 1
}

# 4. Re-install Composer dependencies
echo "[3/6] Memulihkan dependensi Composer..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Re-compile assets
echo "[4/6] Mengompilasi ulang aset frontend (Vite)..."
npm ci --silent
npm run build --silent

# 6. Clear & re-warm cache
echo "[5/6] Membersihkan dan memuat ulang cache aplikasi..."
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan queue:restart --quiet || true

# 7. Buka kembali aplikasi
echo "[6/6] Mematikan maintenance mode, aplikasi kembali online..."
php artisan up

echo "======================================================================"
echo "    ROLLBACK KODE SELESAI: APLIKASI BERADA PADA ${TARGET_REF}        "
echo "======================================================================"
echo "[CATATAN DATABASE]:"
echo "Jika rilis yang di-rollback menyertakan migrasi database baru yang ingin dibatalkan:"
echo "1. Rollback migrasi: php artisan migrate:rollback --step=1 --force"
echo "2. Atau pulihkan snapshot backup penuh sebelum deploy:"
echo "   sudo bash deploy/scripts/restore-database.sh /var/backups/palcom-ai-scheduler/<file_backup>.sqlite.gz"
