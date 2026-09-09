#!/usr/bin/env bash
#
# Restore database ApartStay dari file backup (output ops/backup-db.sh).
#
# GUARD KEAMANAN — restore menimpa seluruh database target:
#   1. Target DB harus eksplisit lewat RESTORE_DATABASE (bukan DB_DATABASE
#      .env: tidak boleh ada jalur "salah ketik lalu production hilang").
#   2. APP_ENV harus bukan 'production' KECUALI bila FORCE_PRODUCTION=1.
#   3. Tidak ada prompt interaktif — cocok untuk drill otomatis/CI; guard
#      di atas adalah gantinya.
#
# Penggunaan:
#   RESTORE_DATABASE=apart_restore_drill ./ops/restore-db.sh storage/backups/apartstay-db-....sql.gz
#   APP_ENV=production RESTORE_DATABASE=prod_db FORCE_PRODUCTION=1 ./ops/restore-db.sh file.sql.gz   # sadari risikonya
#
# Prasyarat: database target harus sudah dibuat (CREATE DATABASE) dan kosong
# atau bisa ditimpa. Skrip tidak pernah membuat/drop database otomatis.

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

if [ "$#" -ne 1 ]; then
    echo "Penggunaan: RESTORE_DATABASE=<db_target> $0 <file-backup.sql.gz>" >&2
    exit 2
fi

BACKUP_FILE="$1"
RESTORE_DATABASE="${RESTORE_DATABASE:-}"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: file backup tidak ditemukan: $BACKUP_FILE" >&2
    exit 2
fi

if [ -z "$RESTORE_DATABASE" ]; then
    echo "ERROR: RESTORE_DATABASE wajib diisi — nama database tujuan restore." >&2
    echo "       Jangan mengandalkan DB_DATABASE dari .env untuk restore." >&2
    exit 2
fi

APP_ENV_VALUE="${APP_ENV:-$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)}"
if [ "${APP_ENV_VALUE:-}" = "production" ] && [ "${FORCE_PRODUCTION:-0}" != "1" ]; then
    echo "ERROR: APP_ENV=production — restore ditolak tanpa FORCE_PRODUCTION=1." >&2
    exit 3
fi

env_value() {
    local key="$1" default="$2"
    if [ -n "${!key:-}" ]; then
        printf '%s' "${!key}"
    elif [ -f .env ] && grep -qE "^${key}=" .env; then
        grep -E "^${key}=" .env | head -n1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//; s/"$//'
    else
        printf '%s' "$default"
    fi
}

DB_HOST="$(env_value DB_HOST 127.0.0.1)"
DB_PORT="$(env_value DB_PORT 3306)"
DB_USERNAME="$(env_value DB_USERNAME '')"
DB_PASSWORD="$(env_value DB_PASSWORD '')"

if [ -z "$DB_USERNAME" ]; then
    echo "ERROR: DB_USERNAME kosong." >&2
    exit 2
fi

for cmd in mysql gunzip; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "ERROR: $cmd tidak ditemukan di PATH." >&2
        exit 3
    fi
done

echo "Restore ${BACKUP_FILE} -> ${RESTORE_DATABASE}@${DB_HOST}:${DB_PORT}"
START="$(date +%s)"

gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASSWORD" mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$RESTORE_DATABASE"

END="$(date +%s)"
echo "OK: restore selesai dalam $((END - START)) detik."
echo "Verifikasi: jalankan ops/verify-restore.sh RESTORE_DATABASE=${RESTORE_DATABASE}"
