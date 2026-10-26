#!/usr/bin/env bash
#
# Backup database lalu verifikasi bahwa hasil backup benar-benar dapat
# direstore ke database scratch.
#
# Script ini TIDAK mengubah database sumber. Database scratch dibuat dengan
# nama yang berbeda, dipakai untuk restore, lalu dihapus (kecuali
# KEEP_VERIFY_DB=1).
#
# Contoh lokal (Git Bash + XAMPP):
#   DB_DATABASE=koperasi_jabar \
#   DB_USERNAME=root \
#   BACKUP_DIR=/c/Users/mocha/AppData/Local/Temp/opencode/backup-test \
#   DUMP_BIN=/c/xampp/mysql/bin/mysqldump.exe \
#   CLIENT_BIN=/c/xampp/mysql/bin/mysql.exe \
#   bash scripts/db-backup-verify.sh
#
# Contoh produksi (kredensial lewat file, bukan argumen):
#   DB_CNF=/etc/koperasi/backup.cnf \
#   DB_DATABASE=koperasi_jabar \
#   BACKUP_DIR=/var/backups/koperasi \
#   bash scripts/db-backup-verify.sh
#
# Variabel:
#   DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   DB_CNF              File kredensial [client] (disarankan di produksi).
#   BACKUP_DIR          Direktori tujuan backup (wajib).
#   VERIFY              Default 1. Set 0 untuk hanya membuat backup.
#   VERIFY_DB           Nama database scratch. Default <db>_restore_check_<timestamp>.
#   KEEP_VERIFY_DB      Set 1 untuk mempertahankan database scratch.
#   DUMP_BIN/CLIENT_BIN Override binary (mis. mysqldump.exe di Windows).
#
set -euo pipefail

usage() {
    sed -n '2,32p' "$0" | sed 's/^# \{0,1\}//'
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

find_bin() {
    local override="${1:-}"
    shift || true

    if [ -n "$override" ]; then
        if [ -x "$override" ] || command -v "$override" >/dev/null 2>&1; then
            printf '%s\n' "$override"
            return 0
        fi

        fail "Binary tidak ditemukan: $override"
    fi

    local candidate
    for candidate in "$@"; do
        if command -v "$candidate" >/dev/null 2>&1; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    return 1
}

case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
esac

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_CNF="${DB_CNF:-}"
BACKUP_DIR="${BACKUP_DIR:-}"
VERIFY="${VERIFY:-1}"
VERIFY_DB="${VERIFY_DB:-}"
KEEP_VERIFY_DB="${KEEP_VERIFY_DB:-0}"

if [ -z "$DB_DATABASE" ]; then
    usage
    fail "DB_DATABASE wajib diisi."
fi

if [ -z "$BACKUP_DIR" ]; then
    usage
    fail "BACKUP_DIR wajib diisi."
fi

if [ -z "$DB_CNF" ] && [ -z "$DB_USERNAME" ]; then
    usage
    fail "Isi DB_CNF atau DB_USERNAME."
fi

if [ -n "$DB_CNF" ] && [ ! -r "$DB_CNF" ]; then
    fail "DB_CNF tidak dapat dibaca: $DB_CNF"
fi

DUMP_BIN="$(find_bin "${DUMP_BIN:-}" mariadb-dump mysqldump)" \
    || fail "mariadb-dump/mysqldump tidak ditemukan."
CLIENT_BIN="$(find_bin "${CLIENT_BIN:-}" mariadb mysql)" \
    || fail "mariadb/mysql client tidak ditemukan."
GZIP_BIN="$(find_bin "${GZIP_BIN:-}" gzip)" \
    || fail "gzip tidak ditemukan."
SHA_BIN="$(find_bin "${SHA_BIN:-}" sha256sum shasum)" \
    || fail "sha256sum/shasum tidak ditemukan."

AUTH_ARGS=()
if [ -n "$DB_CNF" ]; then
    AUTH_ARGS+=(--defaults-extra-file="$DB_CNF")
else
    AUTH_ARGS+=(--host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME")

    if [ -n "$DB_PASSWORD" ]; then
        # MYSQL_PWD menghindari password terlihat di daftar proses.
        export MYSQL_PWD="$DB_PASSWORD"
        printf 'PERINGATAN: DB_PASSWORD dipakai lewat MYSQL_PWD; produksi sebaiknya memakai DB_CNF.\n' >&2
    fi
fi

umask 077
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_file="$BACKUP_DIR/db-${DB_DATABASE}-${timestamp}.sql.gz"
tmp_file="${backup_file}.tmp"

if [ -e "$backup_file" ]; then
    fail "File backup sudah ada: $backup_file"
fi

printf 'Membuat backup %s ...\n' "$DB_DATABASE"

if ! "$DUMP_BIN" "${AUTH_ARGS[@]}" \
    --single-transaction \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" | "$GZIP_BIN" -c > "$tmp_file"; then
    rm -f "$tmp_file"
    fail "Backup gagal; file sementara dihapus."
fi

mv "$tmp_file" "$backup_file"

if [ "$(basename "$SHA_BIN")" = "shasum" ]; then
    "$SHA_BIN" -a 256 "$backup_file" > "${backup_file}.sha256"
else
    "$SHA_BIN" "$backup_file" > "${backup_file}.sha256"
fi

printf 'Backup: %s\n' "$backup_file"
printf 'Checksum: %s\n' "$(cut -d' ' -f1 "${backup_file}.sha256")"

if [ "$VERIFY" != "1" ]; then
    printf 'Verifikasi restore dilewati (VERIFY=%s).\n' "$VERIFY"
    exit 0
fi

if [ -z "$VERIFY_DB" ]; then
    VERIFY_DB="${DB_DATABASE}_restore_check_${timestamp//-/_}"
fi

if ! [[ "$VERIFY_DB" =~ ^[A-Za-z0-9_]+$ ]]; then
    fail "VERIFY_DB hanya boleh berisi huruf, angka, dan garis bawah."
fi

case "$VERIFY_DB" in
    "$DB_DATABASE"|mysql|information_schema|performance_schema|sys)
        fail "VERIFY_DB tidak boleh sama dengan database sumber atau database sistem."
        ;;
esac

cleanup() {
    if [ "$KEEP_VERIFY_DB" = "1" ]; then
        printf 'Database scratch dipertahankan: %s\n' "$VERIFY_DB"
        return
    fi

    "$CLIENT_BIN" "${AUTH_ARGS[@]}" \
        -e "DROP DATABASE IF EXISTS \`$VERIFY_DB\`;" >/dev/null 2>&1 || true
}
trap cleanup EXIT

printf 'Membuat database scratch %s ...\n' "$VERIFY_DB"

"$CLIENT_BIN" "${AUTH_ARGS[@]}" \
    -e "DROP DATABASE IF EXISTS \`$VERIFY_DB\`; CREATE DATABASE \`$VERIFY_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    >/dev/null

printf 'Restore ke database scratch ...\n'

# Normalisasi CRLF dari mysqldump Windows agar aman saat restore lintas OS.
# Dump Linux biasanya sudah LF; sed tidak mengubah isi data yang di-escape.
if ! "$GZIP_BIN" -dc "$backup_file" | sed 's/\r$//' | "$CLIENT_BIN" "${AUTH_ARGS[@]}" "$VERIFY_DB"; then
    fail "Restore gagal; backup tetap disimpan untuk investigasi."
fi

source_tables="$("$CLIENT_BIN" "${AUTH_ARGS[@]}" -N -B -e 'SHOW TABLES' "$DB_DATABASE" | sort)"
verify_tables="$("$CLIENT_BIN" "${AUTH_ARGS[@]}" -N -B -e 'SHOW TABLES' "$VERIFY_DB" | sort)"

if [ "$source_tables" != "$verify_tables" ]; then
    printf 'Daftar tabel sumber:\n%s\n' "$source_tables" >&2
    printf 'Daftar tabel hasil restore:\n%s\n' "$verify_tables" >&2
    fail "Daftar tabel hasil restore berbeda."
fi

table_count=0
mismatch=0

while IFS= read -r table; do
    [ -z "$table" ] && continue

    escaped_table="${table//\`/\`\`}"
    source_rows="$("$CLIENT_BIN" "${AUTH_ARGS[@]}" -N -B \
        -e "SELECT COUNT(*) FROM \`$escaped_table\`" "$DB_DATABASE")"
    verify_rows="$("$CLIENT_BIN" "${AUTH_ARGS[@]}" -N -B \
        -e "SELECT COUNT(*) FROM \`$escaped_table\`" "$VERIFY_DB")"

    if [ "$source_rows" != "$verify_rows" ]; then
        printf 'MISMATCH %s: sumber=%s restore=%s\n' \
            "$table" "$source_rows" "$verify_rows" >&2
        mismatch=1
    fi

    table_count=$((table_count + 1))
done <<< "$source_tables"

if [ "$mismatch" -ne 0 ]; then
    fail "Jumlah baris berbeda. Jalankan saat trafik rendah atau bandingkan manual."
fi

printf 'Verifikasi restore: OK (%s tabel, jumlah baris sama).\n' "$table_count"
printf 'Database scratch akan dihapus kecuali KEEP_VERIFY_DB=1.\n'
