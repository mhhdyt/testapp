#!/usr/bin/env bash
# =============================================================================
# Deploy Test App ke nginx + php8.3-fpm (Ubuntu/Debian).
#
# Pemakaian (di server, dari folder hasil upload):
#   sudo bash deploy/deploy.sh
#
# Variabel opsional:
#   APP_DIR=/var/www/testapp      lokasi instalasi
#   SERVER_NAME=_                 domain/IP untuk server_name nginx
#   PHP_VER=8.3                   versi PHP-FPM
#   DISABLE_DEFAULT_SITE=1        nonaktifkan site "default" nginx (agar bisa diakses via IP)
#
# Contoh: sudo SERVER_NAME=testapp.kantor.local bash deploy/deploy.sh
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/testapp}"
SERVER_NAME="${SERVER_NAME:-_}"
PHP_VER="${PHP_VER:-8.3}"
DISABLE_DEFAULT_SITE="${DISABLE_DEFAULT_SITE:-0}"
WEB_USER="www-data"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NGINX_CONF="/etc/nginx/sites-available/testapp.conf"
PHP_INI="/etc/php/${PHP_VER}/fpm/conf.d/99-testapp.ini"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '   \033[32m[OK]\033[0m %s\n' "$*"; }
warn() { printf '   \033[33m[!!]\033[0m %s\n' "$*"; }

[[ $EUID -eq 0 ]] || { echo "Jalankan dengan sudo/root."; exit 1; }

# -----------------------------------------------------------------------------
step "Cek paket yang dibutuhkan"
missing=()
for pkg in nginx "php${PHP_VER}-fpm" "php${PHP_VER}-mysql" "php${PHP_VER}-curl" "php${PHP_VER}-gd" \
           "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-zip" "php${PHP_VER}-intl" \
           "php${PHP_VER}-bcmath" php-imagick mariadb-client; do
    if dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "install ok installed"; then
        ok "$pkg"
    else
        warn "$pkg belum terpasang"
        missing+=("$pkg")
    fi
done
if (( ${#missing[@]} > 0 )); then
    echo "   Pasang dulu: sudo apt install -y ${missing[*]}"
    exit 1
fi

# -----------------------------------------------------------------------------
step "Salin aplikasi ke ${APP_DIR}"
mkdir -p "$APP_DIR"
if [[ "$SRC_DIR" != "$(cd "$APP_DIR" && pwd)" ]]; then
    # .env dan uploads di server tidak ditimpa
    tar -C "$SRC_DIR" --exclude='./.env' --exclude='./public/uploads' --exclude='./.git' -cf - . \
        | tar -C "$APP_DIR" -xf -
    ok "file disalin dari $SRC_DIR"
else
    ok "sumber = tujuan, lewati penyalinan"
fi

# Buang CRLF jika file sempat diedit/di-copy dari Windows
find "$APP_DIR" -type f \( -name '*.sh' -o -name '*.conf' -o -name '*.ini' -o -name '*.sql' -o -name '.env*' \) \
    -exec sed -i 's/\r$//' {} +

# -----------------------------------------------------------------------------
step "Konfigurasi .env"
if [[ ! -f "$APP_DIR/.env" ]]; then
    if [[ -f "$SRC_DIR/.env" ]]; then
        cp "$SRC_DIR/.env" "$APP_DIR/.env"
        ok ".env disalin dari folder sumber"
    else
        cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        warn ".env dibuat dari .env.example — WAJIB edit kredensial DB: sudo nano $APP_DIR/.env"
    fi
else
    ok ".env sudah ada, tidak diubah"
fi

# -----------------------------------------------------------------------------
step "Atur kepemilikan & izin file"
chown -R root:root "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 755 "$APP_DIR"/deploy/*.sh
chown root:"$WEB_USER" "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"
mkdir -p "$APP_DIR/public/uploads"
chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR/public/uploads"
chmod 755 "$APP_DIR/public/uploads"
ok "kode milik root (read-only bagi php-fpm), uploads milik $WEB_USER, .env 640 root:$WEB_USER"

# -----------------------------------------------------------------------------
step "Konfigurasi PHP-FPM ${PHP_VER}"
install -m 644 "$APP_DIR/deploy/php/99-testapp.ini" "$PHP_INI"
ok "$PHP_INI"
"php-fpm${PHP_VER}" -t

# -----------------------------------------------------------------------------
step "Konfigurasi nginx"
sed -e "s#/var/www/testapp#${APP_DIR}#g" \
    -e "s#server_name _;#server_name ${SERVER_NAME};#" \
    -e "s#php8\.3-fpm\.sock#php${PHP_VER}-fpm.sock#g" \
    "$APP_DIR/deploy/nginx/testapp.conf" > "$NGINX_CONF"
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/testapp.conf
ok "$NGINX_CONF (server_name ${SERVER_NAME})"

if [[ -e /etc/nginx/sites-enabled/default ]]; then
    if [[ "$DISABLE_DEFAULT_SITE" == "1" ]]; then
        rm -f /etc/nginx/sites-enabled/default
        ok "site default dinonaktifkan (file asli tetap di sites-available/default)"
    elif [[ "$SERVER_NAME" == "_" ]]; then
        warn "site 'default' nginx masih aktif: akses via IP akan menampilkan halaman Welcome nginx."
        warn "Jalankan ulang dengan DISABLE_DEFAULT_SITE=1 atau SERVER_NAME=<domain>."
    fi
fi
nginx -t

# -----------------------------------------------------------------------------
step "Reload layanan"
systemctl enable --quiet "php${PHP_VER}-fpm" nginx
systemctl reload-or-restart "php${PHP_VER}-fpm"
systemctl reload-or-restart nginx
ok "php${PHP_VER}-fpm & nginx di-reload"

# -----------------------------------------------------------------------------
step "Uji cepat"
if command -v "php${PHP_VER}" >/dev/null 2>&1; then
    sudo -u "$WEB_USER" "php${PHP_VER}" "$APP_DIR/bin/check.php" || warn "ada pengecekan GAGAL (lihat di atas)"
fi
if command -v curl >/dev/null 2>&1; then
    host_header="$SERVER_NAME"; [[ "$host_header" == "_" ]] && host_header="localhost"
    code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${host_header}" http://127.0.0.1/health.php || true)"
    if [[ "$code" == "200" ]]; then ok "GET /health.php -> HTTP 200"; else warn "GET /health.php -> HTTP ${code} (cek .env / koneksi DB)"; fi
fi

cat <<EOF

Selesai. Langkah berikutnya:
  1. Edit kredensial DB      : sudo nano ${APP_DIR}/.env
  2. Uji koneksi & impor tabel: sudo bash ${APP_DIR}/deploy/db-init.sh
  3. Buka di browser          : http://<IP-server>/  dan  http://<IP-server>/system.php
EOF
