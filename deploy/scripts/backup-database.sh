#!/usr/bin/env bash
# ==============================================================================
# Database Backup Script: PALCOM AI Scheduler
# Mendukung SQLite dan MySQL dengan kompresi gzip & retensi berkala
# ==============================================================================
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/palcom-ai-scheduler}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"

mkdir -p "$BACKUP_DIR"

echo "[BACKUP] Memulai pencadangan database pada ${TIMESTAMP}..."

# Baca konfigurasi DB dari .env jika ada
DB_CONN="$(php artisan tinker --execute="echo config('database.default');" 2>/dev/null || echo "sqlite")"

if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="$(php artisan tinker --execute="echo config('database.connections.sqlite.database');" 2>/dev/null || echo "database/database.sqlite")"

    if [ -f "$DB_FILE" ]; then
        BACKUP_FILE="${BACKUP_DIR}/scheduler_sqlite_${TIMESTAMP}.sqlite"
        # Salin file SQLite secara aman menggunakan perintah .backup SQLite jika sqlite3 terpasang, atau gzip copy
        if command -v sqlite3 >/dev/null 2>&1; then
            sqlite3 "$DB_FILE" ".backup '${BACKUP_FILE}'"
        else
            cp "$DB_FILE" "$BACKUP_FILE"
        fi
        gzip -f "$BACKUP_FILE"
        echo "[BACKUP] Sukses: Database SQLite dicadangkan ke ${BACKUP_FILE}.gz"
    else
        echo "[BACKUP WARN] File database SQLite tidak ditemukan pada ${DB_FILE}"
    fi

elif [ "$DB_CONN" = "mysql" ]; then
    if command -v mysqldump >/dev/null 2>&1; then
        DB_HOST="$(php artisan tinker --execute="echo config('database.connections.mysql.host');" 2>/dev/null)"
        DB_PORT="$(php artisan tinker --execute="echo config('database.connections.mysql.port');" 2>/dev/null)"
        DB_NAME="$(php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>/dev/null)"
        DB_USER="$(php artisan tinker --execute="echo config('database.connections.mysql.username');" 2>/dev/null)"
        DB_PASS="$(php artisan tinker --execute="echo config('database.connections.mysql.password');" 2>/dev/null)"

        BACKUP_FILE="${BACKUP_DIR}/scheduler_mysql_${TIMESTAMP}.sql.gz"
        MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_FILE"
        echo "[BACKUP] Sukses: Database MySQL dicadangkan ke ${BACKUP_FILE}"
    else
        echo "[BACKUP ERROR] mysqldump tidak ditemukan di server."
        exit 1
    fi
fi

# Rotasi dan pembersihan backup lama melebihi retensi (default 14 hari)
echo "[BACKUP] Membersihkan arsip backup yang berusia lebih dari ${RETENTION_DAYS} hari..."
find "$BACKUP_DIR" -type f -name "scheduler_*" -mtime "+${RETENTION_DAYS}" -delete || true

echo "[BACKUP] Selesai."
