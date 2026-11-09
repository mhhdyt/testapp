# Test App Inventaris — nginx + PHP 8.3-FPM + MariaDB

Aplikasi contoh (PHP murni, tanpa framework/Composer) untuk menguji server yang dipasang dengan:

```bash
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-gd \
  php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath \
  php-imagick mariadb-client
```

Karena yang terpasang hanya **mariadb-client**, aplikasi diasumsikan terhubung ke **server MariaDB terpisah**
(jika MariaDB dipasang di server yang sama, cukup isi `DB_HOST=127.0.0.1`).

## Fitur & paket yang diuji

| Fitur aplikasi | Paket yang dipakai |
|---|---|
| CRUD produk (PDO, prepared statement, transaksi) | `php8.3-mysql` (pdo_mysql, mysqli, mysqlnd) |
| Halaman `/` dilayani nginx → FastCGI → php-fpm | `nginx`, `php8.3-fpm` |
| Upload gambar → resize & re-encode JPEG | `php-imagick` (otomatis pakai GD jika imagick tidak ada) |
| Grafik stok PNG (`chart.php`) | `php8.3-gd` |
| Format Rupiah, tanggal Indonesia, transliterasi | `php8.3-intl` |
| Nilai persediaan + PPN 11% presisi | `php8.3-bcmath` |
| Validasi/potong teks UTF-8 | `php8.3-mbstring` |
| Ekspor XML (XMLWriter, SimpleXML, DOM) | `php8.3-xml` |
| Ekspor ZIP (CSV + XML + gambar) | `php8.3-zip` |
| Uji koneksi keluar HTTPS | `php8.3-curl` |
| Uji koneksi & impor skema dari shell | `mariadb-client` (`deploy/db-init.sh`) |

Halaman **Cek Sistem** (`/system.php`) dan `bin/check.php` menjalankan uji fungsional untuk semua paket di atas,
termasuk INSERT → SELECT → ROLLBACK ke MariaDB dengan teks UTF-8/emoji dan nilai DECIMAL.

## Struktur

```
testapp/
├── public/                 ← document root nginx
│   ├── index.php           daftar produk, ringkasan, pencarian
│   ├── form.php            tambah/edit produk + upload gambar
│   ├── delete.php          hapus (POST + CSRF)
│   ├── export.php          ?format=csv|xml|zip
│   ├── chart.php           grafik PNG (GD)
│   ├── system.php          cek sistem lengkap (HTML)
│   ├── health.php          health check JSON (200 / 503)
│   ├── assets/app.css
│   └── uploads/            ditulis oleh www-data
├── src/                    kode aplikasi (di luar document root)
├── bin/check.php           cek sistem dari terminal
├── database/
│   ├── 00_create_database_user.sql   dijalankan di server MariaDB
│   └── schema.sql                    tabel + data contoh
├── deploy/
│   ├── deploy.sh           instal ke APP_DIR + konfigurasi nginx/php-fpm (ini + pool)
│   ├── db-init.sh          uji koneksi & impor skema via mariadb-client
│   ├── nginx/testapp.conf
│   └── php/99-custom.ini   → /etc/php/8.3/fpm/conf.d/99-custom.ini
└── .env.example
```

## Topologi server

| Server | Peran | Domain | Folder aplikasi | Database | User DB |
|---|---|---|---|---|---|
| SERVER 1 · web-01 (`10.10.14.7`) | produksi | `app.devyourtech.com` | `/var/www/app` | `app_prod` | `appuser` |
| SERVER 2 · db-01 | MariaDB | — | — | — | — |
| SERVER 3 · web-02 (`10.10.14.73`) | staging | `stg.devyourtech.com` | `/var/www/stg` | `app_stg` | `stguser` |

Web-01 dan web-02 sama-sama memakai RAM 2 GB, PHP 8.3 (bawaan Ubuntu 24.04), dan konfigurasi PHP-FPM yang sama:

- `99-custom.ini`: `upload_max_filesize`/`post_max_size` 64M, `memory_limit` 256M, `max_execution_time` 120;
- pool `www.conf`: `pm.max_children` 12, `pm.start_servers` 3, `pm.min_spare_servers` 2, `pm.max_spare_servers` 5.

Pada rilis Ubuntu lain, cek versi PHP dengan `ls /run/php/`, lalu jalankan deploy dengan `PHP_VER=<versi>`.

## Langkah deploy

### 1. Siapkan database (di db-01)

Jika langkah 8.3 (buat database produksi & staging) sudah dijalankan, lewati langkah ini.
Jika belum, salin `database/00_create_database_user.sql` ke db-01 lalu jalankan:

```bash
sudo mariadb < 00_create_database_user.sql
```

Agar web-01/web-02 bisa terhubung, pastikan `bind-address` di `/etc/mysql/mariadb.conf.d/50-server.cnf`
bukan `127.0.0.1`, dan firewall membuka port 3306 **hanya** untuk `10.10.14.7` dan `10.10.14.73`.

### 2. Upload aplikasi ke server web

Dari Windows (PowerShell), ke web-02 dan/atau web-01:

```powershell
scp -r "C:\Users\IT\Desktop\Deploy Srv\testapp" user@10.10.14.73:~/
scp -r "C:\Users\IT\Desktop\Deploy Srv\testapp" user@10.10.14.7:~/
```

### 3. Jalankan deploy

```bash
cd ~/testapp
# SERVER 3 · web-02 (staging)
sudo APP_DIR=/var/www/stg SERVER_NAME=stg.devyourtech.com bash deploy/deploy.sh
# SERVER 1 · web-01 (produksi)
sudo APP_DIR=/var/www/app SERVER_NAME=app.devyourtech.com bash deploy/deploy.sh
```

Script ini akan:

- mengecek semua paket;
- menyalin ke `APP_DIR`;
- membuat `.env`;
- mengatur izin file (kode milik root, `uploads/` milik www-data, `.env` 640);
- memasang `/etc/php/8.3/fpm/conf.d/99-custom.ini` (dan menghapus `99-testapp.ini` lama bila ada);
- mengatur pool PHP-FPM `pool.d/www.conf` (lewati dengan `TUNE_FPM_POOL=0`);
- memasang site nginx;
- menjalankan `nginx -t` dan `php-fpm8.3 -t`;
- me-restart php8.3-fpm dan me-reload nginx;
- menjalankan cek sistem.

### 4. Isi kredensial database, lalu impor skema

`.env` yang dibuat dari `.env.example` sudah berisi kredensial **staging**. Yang perlu diubah:

- `DB_HOST` → IP db-01 (di kedua server);
- **khusus web-01**: hapus tanda `#` pada 3 baris `DB_NAME`/`DB_USER`/`DB_PASS` produksi.

```bash
# web-02
sudo nano /var/www/stg/.env
sudo bash /var/www/stg/deploy/db-init.sh /var/www/stg   # uji koneksi + impor schema.sql
# web-01
sudo nano /var/www/app/.env
sudo bash /var/www/app/deploy/db-init.sh /var/www/app
```

### 5. Uji

Ganti `DOMAIN` dengan `stg.devyourtech.com` (web-02) atau `app.devyourtech.com` (web-01).
Ganti `APP_DIR` dengan `/var/www/stg` atau `/var/www/app`.

| URL / perintah | Hasil yang diharapkan |
|---|---|
| `http://DOMAIN/` | daftar 9 produk contoh, ringkasan Rupiah, grafik |
| `http://DOMAIN/system.php` | semua baris **OK**, batas upload `64M`, `memory_limit=256M` |
| `curl -i http://DOMAIN/health.php` | `HTTP/1.1 200` + JSON `"status": "ok"` |
| `sudo -u www-data php8.3 APP_DIR/bin/check.php` | `Hasil: OK` (exit code 0) |
| `curl -I http://DOMAIN/.env` | `403 Forbidden` |
| `grep -E '^pm\.' /etc/php/8.3/fpm/pool.d/www.conf` | `max_children = 12`, `start_servers = 3`, `min_spare_servers = 2`, `max_spare_servers = 5` |

Coba juga: tambah produk dengan gambar PNG/JPG, edit, hapus, dan ekspor CSV/XML/ZIP.

## Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `$'\r': command not found` saat menjalankan script | File tersimpan dengan CRLF (Windows). Jalankan `sed -i 's/\r$//' deploy/*.sh`. |
| Halaman "Welcome to nginx" | Site `default` masih aktif. Deploy ulang dengan `DISABLE_DEFAULT_SITE=1` atau isi `SERVER_NAME`. |
| `502 Bad Gateway` | php-fpm mati atau socket salah: `systemctl status php8.3-fpm`, `ls /run/php/`. |
| `SQLSTATE[HY000] [2002]` | Server DB tidak terjangkau: cek `bind-address`, firewall, `nc -zv DB_HOST 3306`. |
| `SQLSTATE[HY000] [1045] Access denied` | Password salah atau user dibuat untuk host lain. Cek `SELECT user,host FROM mysql.user;`. |
| `SSL is required, but the server does not support it` (db-init.sh) | Client MariaDB ≥ 11.4 mewajibkan SSL. Tambahkan `--skip-ssl` ke `args` di `db-init.sh`, atau aktifkan SSL di server DB. |
| Upload gagal "Folder upload tidak bisa ditulis" | `sudo chown -R www-data:www-data APP_DIR/public/uploads` |
| `413 Request Entity Too Large` | Naikkan `client_max_body_size` (nginx) dan `post_max_size`/`upload_max_filesize` (99-custom.ini). Saat ini keduanya 64M. |

Log: `/var/log/nginx/testapp.error.log` dan `journalctl -u php8.3-fpm`.
Untuk melihat detail error di browser sementara waktu, set `APP_DEBUG=true` di `.env`.

## Keamanan (sebelum dipakai di luar pengujian)

- `system.php` dan `health.php` menampilkan info server. Batasi aksesnya dengan blok `allow/deny` yang sudah
  disiapkan (dalam bentuk komentar) di `deploy/nginx/testapp.conf`, atau hapus setelah pengujian selesai.
- Aplikasi belum punya login. Jangan dibuka ke internet tanpa proteksi (mis. `auth_basic` nginx atau VPN).
- Pasang HTTPS (mis. `certbot --nginx`). Cookie session otomatis memakai flag `Secure` saat diakses lewat HTTPS.
- Yang sudah diterapkan:
  - prepared statement;
  - token CSRF;
  - escaping output;
  - validasi MIME upload dan re-encode gambar;
  - eksekusi PHP diblokir di `/uploads/`;
  - `.env` dan `src/` berada di luar document root.
