#!/usr/bin/env bash
#
# Backup database ApartStay (MySQL) — full logical dump via mysqldump.
#
# CREDENTIALS: dibaca dari environment (DB_HOST, DB_PORT, DB_DATABASE,
# DB_USERNAME, DB_PASSWORD) atau file .env bila variabel belum ada. Password
# TIDAK pernah di-hardcode dan tidak pernah muncul di output/argv dump
# (MYSQL_PWD dipakai sehingga tidak ikut ps).
#
# Kegagalan apa pun (koneksi, dump, kompresi, penulisan file) keluar dengan
# exit code non-zero supaya cron/CI/maintenance window tahu backup gagal —
# backup yang "hijau tapi kosong" lebih berbahaya daripada gagal terlihat.
#
# Output TIDAK ditulis di bawah public/ dan tidak pernah di-commit (lihat
# .gitignore: /storage/backups).
#
# Penggunaan:
#   ./ops/backup-db.sh                  # backup DB dari .env
#   KEEP_LAST=14 ./ops/backup-db.sh     # simpan 14 file terakhir
#
# Untuk produksi: jalankan lewat cron harian (lihat ops/BACKUP.md), dan kirim
# file hasil ke tujuan offsite — backup lokal di server yang sama BUKAN
# disaster recovery.

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$APP_ROOT/storage/backups}"
KEEP_LAST="${KEEP_LAST:-14}"

cd "$APP_ROOT"

# Baca .env hanya untuk variabel yang belum ada di environment.
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
DB_DATABASE="$(env_value DB_DATABASE '')"
DB_USERNAME="$(env_value DB_USERNAME '')"
DB_PASSWORD="$(env_value DB_PASSWORD '')"

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    echo "ERROR: DB_DATABASE/DB_USERNAME kosong — tidak ada target backup." >&2
    exit 2
fi

if ! command -v mysqldump >/dev/null 2>&1; then
    echo "ERROR: mysqldump tidak ditemukan di PATH." >&2
    exit 3
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

STAMP="$(date -u +%Y%m%d-%H%M%S)"
FILENAME="apartstay-db-${STAMP}.sql.gz"
TARGET="$BACKUP_DIR/$FILENAME"

# --single-transaction: snapshot konsisten tanpa me-lock tabel (InnoDB).
# --routines/--triggers/--events: objek DB ikut ter-backup.
# --hex-blob: BLOB aman lintas versi.
echo "Backup ${DB_DATABASE}@${DB_HOST}:${DB_PORT} -> ${TARGET}"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --routines --triggers --events \
    --hex-blob \
    --quote-names \
    "$DB_DATABASE" | gzip -9 > "$TARGET"

# gzip exit code != 0 sudah dihentikan oleh pipefail; mysqldump gagal koneksi
# menghasilkan dump kosong — tolak file seperti itu secara eksplisit.
if [ ! -s "$TARGET" ]; then
    echo "ERROR: file backup kosong — dump gagal." >&2
    rm -f "$TARGET"
    exit 4
fi

SIZE="$(du -h "$TARGET" | cut -f1)"
echo "OK: ${TARGET} (${SIZE})"

# Rotasi lokal sederhana — retensi jangka panjang/adanya offsite adalah
# tanggung jawab runbook (ops/BACKUP.md), bukan rotasi lokal ini.
ls -1t "$BACKUP_DIR"/apartstay-db-*.sql.gz 2>/dev/null | tail -n +"$((KEEP_LAST + 1))" | while read -r old; do
    echo "Rotasi: hapus $old"
    rm -f "$old"
done
