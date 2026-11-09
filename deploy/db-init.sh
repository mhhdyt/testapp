#!/usr/bin/env bash
# =============================================================================
# Uji koneksi ke MariaDB dengan mariadb-client, lalu impor database/schema.sql.
# Kredensial dibaca dari .env aplikasi.
#
# Pemakaian:  sudo bash /var/www/stg/deploy/db-init.sh [APP_DIR]
#   web-02 (staging) : sudo bash /var/www/stg/deploy/db-init.sh /var/www/stg
#   web-01 (produksi): sudo bash /var/www/app/deploy/db-init.sh /var/www/app
# =============================================================================
set -euo pipefail

APP_DIR="${1:-${APP_DIR:-/var/www/stg}}"
ENV_FILE="$APP_DIR/.env"
SCHEMA="$APP_DIR/database/schema.sql"

[[ -r "$ENV_FILE" ]] || { echo "Tidak bisa membaca $ENV_FILE (jalankan dengan sudo?)"; exit 1; }
[[ -r "$SCHEMA" ]]   || { echo "Tidak menemukan $SCHEMA"; exit 1; }
command -v mariadb >/dev/null || { echo "Perintah 'mariadb' tidak ada. sudo apt install -y mariadb-client"; exit 1; }

# Ambil nilai KEY dari .env (buang CRLF dan tanda kutip pembungkus)
get_env() {
    local line val re_dq='^"(.*)"$' re_sq="^'(.*)'\$"
    line="$(grep -E "^[[:space:]]*$1[[:space:]]*=" "$ENV_FILE" | tail -n 1 || true)"
    val="${line#*=}"
    val="${val%$'\r'}"
    val="$(printf '%s' "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    if [[ "$val" =~ $re_dq ]] || [[ "$val" =~ $re_sq ]]; then
        val="${BASH_REMATCH[1]}"
    fi
    printf '%s' "${val:-${2:-}}"
}

DB_HOST="$(get_env DB_HOST 127.0.0.1)"
DB_PORT="$(get_env DB_PORT 3306)"
DB_NAME="$(get_env DB_NAME testapp)"
DB_USER="$(get_env DB_USER testapp)"
DB_SOCKET="$(get_env DB_SOCKET)"

# Password lewat environment agar tidak terlihat di daftar proses (ps)
MYSQL_PWD="$(get_env DB_PASS)"
export MYSQL_PWD

args=(--user="$DB_USER" --connect-timeout=5 --default-character-set=utf8mb4)
if [[ -n "$DB_SOCKET" ]]; then
    args+=(--socket="$DB_SOCKET"); target="socket $DB_SOCKET"
else
    args+=(--host="$DB_HOST" --port="$DB_PORT"); target="$DB_HOST:$DB_PORT"
fi

echo "==> Uji koneksi: $DB_USER@$target"
if ! mariadb "${args[@]}" -e "SELECT VERSION() AS versi_server, CURRENT_USER() AS login_sebagai, @@hostname AS host_db;"; then
    cat <<EOF

Koneksi GAGAL. Periksa:
  - DB_HOST/DB_PORT/DB_USER/DB_PASS di $ENV_FILE
  - User dibuat untuk IP server ini (lihat database/00_create_database_user.sql):
      web-01 -> 'appuser'@'10.10.14.7'   web-02 -> 'stguser'@'10.10.14.73'
  - bind-address MariaDB & firewall port 3306 di db-01
  - Tes jaringan: nc -zv $DB_HOST $DB_PORT
EOF
    exit 1
fi

echo "==> Impor skema ke database '$DB_NAME'"
mariadb "${args[@]}" "$DB_NAME" < "$SCHEMA"

echo "==> Isi tabel"
mariadb "${args[@]}" "$DB_NAME" --table -e \
    "SELECT COUNT(*) AS jumlah_produk, SUM(stock) AS total_stok, SUM(price*stock) AS nilai_persediaan FROM products;"

echo "Selesai. Buka http://<IP-server>/ untuk melihat aplikasi."
