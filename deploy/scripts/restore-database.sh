#!/usr/bin/env bash
# ==============================================================================
# Database Restore Script: PALCOM AI Scheduler
# Safe restoration for Disaster Recovery from compressed backup archive
# ==============================================================================
set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Penggunaan: $0 <path_to_backup_file.gz>"
    echo "Contoh:     $0 /var/backups/palcom-ai-scheduler/scheduler_sqlite_20261011_020000.sqlite.gz"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "[ERROR] File backup tidak ditemukan pada: ${BACKUP_FILE}"
    exit 1
fi

echo "======================================================================"
echo "    PERINGATAN: PROSES PEMULIHAN DATABASE (DISASTER RECOVERY)        "
echo "    File sumber: ${BACKUP_FILE}                                       "
echo "======================================================================"
echo "Proses ini akan menimpa data database operasional saat ini."
read -p "Apakah Anda yakin ingin melanjutkan? (ketik 'YA' untuk konfirmasi): " CONFIRM

if [ "$CONFIRM" != "YA" ]; then
    echo "Pemulihan dibatalkan oleh pengguna."
    exit 0
fi

# 1. Masuk ke maintenance mode
php artisan down || true

DB_CONN="$(php artisan tinker --execute="echo config('database.default');" 2>/dev/null || echo "sqlite")"

if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="$(php artisan tinker --execute="echo config('database.connections.sqlite.database');" 2>/dev/null || echo "database/database.sqlite")"

    echo "[RESTORE] Memulihkan SQLite database ke ${DB_FILE}..."
    gunzip -c "$BACKUP_FILE" > "$DB_FILE"
    chmod 664 "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
    echo "[RESTORE] Sukses memulihkan database SQLite."

elif [ "$DB_CONN" = "mysql" ]; then
    DB_HOST="$(php artisan tinker --execute="echo config('database.connections.mysql.host');" 2>/dev/null)"
    DB_PORT="$(php artisan tinker --execute="echo config('database.connections.mysql.port');" 2>/dev/null)"
    DB_NAME="$(php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>/dev/null)"
    DB_USER="$(php artisan tinker --execute="echo config('database.connections.mysql.username');" 2>/dev/null)"
    DB_PASS="$(php artisan tinker --execute="echo config('database.connections.mysql.password');" 2>/dev/null)"

    echo "[RESTORE] Memulihkan MySQL database ${DB_NAME}..."
    MYSQL_PWD="$DB_PASS" gunzip -c "$BACKUP_FILE" | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME"
    echo "[RESTORE] Sukses memulihkan database MySQL."
fi

# 2. Bersihkan cache aplikasi
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet

# 3. Buka kembali aplikasi
php artisan up

echo "[RESTORE] Selesai! Database berhasil dipulihkan dan aplikasi telah kembali online."
