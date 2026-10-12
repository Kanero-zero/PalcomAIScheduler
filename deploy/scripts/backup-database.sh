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
    DB_FILE="$(php artisan tinker --execute="echo config('database.connections.sqlite.database');" 2>/dev/null || echo "/var/lib/palcom-ai-scheduler/database.sqlite")"

    if [ -f "$DB_FILE" ]; then
        BACKUP_FILE="${BACKUP_DIR}/scheduler_sqlite_${TIMESTAMP}.sqlite"
        # Gunakan Online Backup API SQLite (melalui sqlite3 CLI atau PHP SQLite3 extension)
        # Dilarang menggunakan salin file mentah (cp) pada database WAL aktif untuk mencegah korupsi data
        if command -v sqlite3 >/dev/null 2>&1; then
            sqlite3 "$DB_FILE" ".backup '${BACKUP_FILE}'"
        elif php -r 'exit(extension_loaded("sqlite3") ? 0 : 1);' >/dev/null 2>&1; then
            php -r '
                $src = new SQLite3($argv[1], SQLITE3_OPEN_READONLY);
                $dest = new SQLite3($argv[2], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                if (! $src->backup($dest)) {
                    exit(1);
                }
                $dest->close();
                $src->close();
            ' "$DB_FILE" "$BACKUP_FILE"
        else
            echo "[BACKUP ERROR] Utilitas 'sqlite3' CLI maupun ekstensi PHP 'sqlite3' tidak tersedia untuk melakukan snapshot konsisten online!"
            exit 1
        fi

        if [ ! -s "$BACKUP_FILE" ]; then
            echo "[BACKUP ERROR] File snapshot database SQLite kosong atau gagal dibuat: ${BACKUP_FILE}"
            rm -f "$BACKUP_FILE"
            exit 1
        fi

        gzip -f "$BACKUP_FILE"
        echo "[BACKUP] Sukses: Database SQLite dicadangkan secara konsisten ke ${BACKUP_FILE}.gz"
    else
        echo "[BACKUP ERROR] File database SQLite tidak ditemukan pada ${DB_FILE}"
        exit 1
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
