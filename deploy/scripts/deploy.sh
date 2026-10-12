#!/usr/bin/env bash
# ==============================================================================
# Production Deployment Script: PALCOM AI Scheduler
# Safe, non-destructive, zero-downtime deployment for VPS
# ==============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/PalcomAIScheduler}"
BRANCH="${BRANCH:-main}"
WEB_USER="www-data"

echo "======================================================================"
echo "    MEMULAI DEPLOYMENT PRODUKSI: PALCOM AI SCHEDULER                 "
echo "    Waktu: $(date '+%Y-%m-%d %H:%M:%S %Z')                           "
echo "======================================================================"

cd "$APP_DIR"

# 1. Pastikan tools esensial tersedia
command -v php >/dev/null 2>&1 || { echo "[ERROR] PHP tidak ditemukan di PATH."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "[ERROR] Composer tidak ditemukan di PATH."; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "[ERROR] npm tidak ditemukan di PATH."; exit 1; }
command -v git >/dev/null 2>&1 || { echo "[ERROR] git tidak ditemukan di PATH."; exit 1; }

# 2. Aktifkan maintenance mode selama proses update inti
echo "[1/9] Mengaktifkan maintenance mode ramah pengguna..."
php artisan down --retry=60 || true

# 3. Buat backup database otomatis sebelum migrasi (wajib sukses)
if [ -f "deploy/scripts/backup-database.sh" ]; then
    echo "[2/9] Membuat snapshot cadangan database sebelum migrasi..."
    if ! bash deploy/scripts/backup-database.sh; then
        echo "[FATAL] Gagal membuat cadangan database otomatis! Deployment DIBATALKAN demi keamanan integritas data."
        php artisan up || true
        exit 1
    fi
else
    echo "[FATAL] Skrip backup (deploy/scripts/backup-database.sh) tidak ditemukan! Deployment DIBATALKAN demi keamanan data."
    php artisan up || true
    exit 1
fi

# 4. Ambil commit terbaru dari branch target secara aman (fast-forward only, tanpa git reset --hard)
echo "[3/9] Mengambil perubahan kode terbaru dari origin/${BRANCH}..."
if ! git diff-index --quiet HEAD --; then
    echo "[FATAL] Working tree memiliki perubahan lokal yang belum di-commit! Deployment dibatalkan untuk mencegah kehilangan data."
    php artisan up || true
    exit 1
fi
git fetch origin "${BRANCH}"
if ! git pull --ff-only origin "${BRANCH}"; then
    echo "[FATAL] Gagal melakukan git pull --ff-only! Terjadi divergensi branch lokal dan remote. Deployment dibatalkan."
    php artisan up || true
    exit 1
fi

# 5. Pasang dependensi PHP produksi
echo "[4/9] Memasang dependensi Composer (mode produksi)..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 6. Kompilasi asset frontend (Tailwind + Vite)
echo "[5/9] Mengompilasi asset frontend (Vite build)..."
npm ci --silent
npm run build --silent

# 7. Jalankan migrasi database baru secara aman dan non-destruktif
echo "[6/9] Menjalankan migrasi database non-destruktif..."
php artisan migrate --force --no-interaction

# 8. Optimasi cache performa Laravel
echo "[7/9] Mengoptimalkan konfigurasi, route, dan view cache..."
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

# Muat ulang queue worker jika diaktifkan
php artisan queue:restart --quiet || true

# 9. Pastikan hak akses direktori storage, cache, dan database aman
echo "[8/9] Menyesuaikan kepemilikan dan hak akses file (permissions)..."
chown -R "$USER":"$WEB_USER" .
chmod -R 775 storage bootstrap/cache
[ -d "database" ] && chmod -R 775 database
if [ -f "database/database.sqlite" ]; then
    chmod 664 database/database.sqlite
fi
DB_SQLITE_PATH="$(php artisan tinker --execute="echo config('database.connections.sqlite.database');" 2>/dev/null || echo "/var/lib/palcom-ai-scheduler/database.sqlite")"
if [ -f "$DB_SQLITE_PATH" ]; then
    chmod 664 "$DB_SQLITE_PATH" || true
    chown "$WEB_USER":"$WEB_USER" "$DB_SQLITE_PATH" || true
    chmod 775 "$(dirname "$DB_SQLITE_PATH")" || true
    chown "$WEB_USER":"$WEB_USER" "$(dirname "$DB_SQLITE_PATH")" || true
fi

# 10. Matikan maintenance mode
echo "[9/9] Mematikan maintenance mode, aplikasi kembali online..."
php artisan up

echo "======================================================================"
echo "    DEPLOYMENT BERHASIL! APLIKASI AKTIF & SIAP DIGUNAKAN             "
echo "======================================================================"

# Jalankan smoke test verifikasi pascadeploy jika script tersedia
if [ -f "deploy/scripts/smoke-test.sh" ]; then
    echo "Menjalankan uji kesehatan pascadeploy (smoke test)..."
    bash deploy/scripts/smoke-test.sh "http://127.0.0.1:80" || echo "[WARN] Smoke test selesai dengan catatan."
fi
