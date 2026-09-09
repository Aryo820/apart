#!/usr/bin/env bash
#
# Verifikasi hasil restore: schema lengkap + jumlah baris tabel inti cocok
# dengan backup sumber. Dipakai setelah ops/restore-db.sh (drill maupun
# insiden nyata) sebagai bukti restore menghasilkan aplikasi yang konsisten.
#
# Penggunaan:
#   RESTORE_DATABASE=apart_restore_drill SOURCE_DB=booking_apartemen ./ops/verify-restore.sh
#
# Membandingkan tabel inti transaksi (users, apartments, facilities,
# apartment_facility, bookings, payments). Schema diverifikasi lewat jumlah
# tabel yang ditemukan dump (migration).

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

RESTORE_DATABASE="${RESTORE_DATABASE:-}"
SOURCE_DB="${SOURCE_DB:-}"

if [ -z "$RESTORE_DATABASE" ] || [ -z "$SOURCE_DB" ]; then
    echo "Penggunaan: RESTORE_DATABASE=<db_hasil> SOURCE_DB=<db_sumber> $0" >&2
    exit 2
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

MYSQL_CMD=(mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --batch --skip-column-names)
export MYSQL_PWD="$DB_PASSWORD"

count_in() {
    local db="$1" table="$2"
    "${MYSQL_CMD[@]}" -e "SELECT COUNT(*) FROM \`${db}\`.\`${table}\`;" 2>/dev/null \
        || { echo "MISSING:${table}"; }
}

TABLES=(users apartments facilities apartment_facility bookings payments)
FAILED=0

echo "== Verifikasi schema =="
TABLE_COUNT_RESTORED="$("${MYSQL_CMD[@]}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${RESTORE_DATABASE}';")"
TABLE_COUNT_SOURCE="$("${MYSQL_CMD[@]}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${SOURCE_DB}';")"
echo "Tabel di ${RESTORE_DATABASE}: ${TABLE_COUNT_RESTORED} (sumber: ${TABLE_COUNT_SOURCE})"
if [ "$TABLE_COUNT_RESTORED" != "$TABLE_COUNT_SOURCE" ]; then
    echo "MISMATCH: jumlah tabel berbeda." >&2
    FAILED=1
fi

echo "== Verifikasi row count tabel inti =="
for table in "${TABLES[@]}"; do
    SRC="$(count_in "$SOURCE_DB" "$table")"
    DST="$(count_in "$RESTORE_DATABASE" "$table")"
    if [ "$SRC" = "MISSING:${table}" ] || [ "$DST" = "MISSING:${table}" ]; then
        echo "  [${table}] TIDAK ADA di salah satu DB (src=${SRC} dst=${DST})" >&2
        FAILED=1
        continue
    fi
    if [ "$SRC" != "$DST" ]; then
        echo "  [${table}] MISMATCH: sumber=${SRC} restore=${DST}" >&2
        FAILED=1
    else
        echo "  [${table}] OK: ${DST} baris"
    fi
done

# Integritas referensial benar-benar utuh: booking tanpa user/apartment
# atau payment tanpa booking tidak boleh ada.
echo "== Verifikasi integritas relasi =="
ORPHANS="$("${MYSQL_CMD[@]}" -e "SELECT COUNT(*) FROM \`${RESTORE_DATABASE}\`.bookings b LEFT JOIN \`${RESTORE_DATABASE}\`.users u ON u.id=b.user_id LEFT JOIN \`${RESTORE_DATABASE}\`.apartments a ON a.id=b.apartment_id WHERE u.id IS NULL OR a.id IS NULL;")"
echo "Booking orphan: ${ORPHANS}"
[ "$ORPHANS" != "0" ] && FAILED=1

ORPHAN_PAYMENTS="$("${MYSQL_CMD[@]}" -e "SELECT COUNT(*) FROM \`${RESTORE_DATABASE}\`.payments p LEFT JOIN \`${RESTORE_DATABASE}\`.bookings b ON b.id=p.booking_id WHERE b.id IS NULL;")"
echo "Payment orphan: ${ORPHAN_PAYMENTS}"
[ "$ORPHAN_PAYMENTS" != "0" ] && FAILED=1

unset MYSQL_PWD

if [ "$FAILED" -ne 0 ]; then
    echo "VERIFIKASI GAGAL — restore tidak konsisten." >&2
    exit 1
fi

echo "VERIFIKASI OK — restore konsisten dengan sumber."
