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
│   ├── deploy.sh           instal ke /var/www/testapp + konfigurasi nginx/php-fpm
│   ├── db-init.sh          uji koneksi & impor skema via mariadb-client
│   ├── nginx/testapp.conf
│   └── php/99-testapp.ini
└── .env.example
```

## Langkah deploy

### 1. Siapkan database (di server MariaDB)

Edit `database/00_create_database_user.sql`: ganti `10.0.0.20` dengan IP server aplikasi dan
`GANTI_PASSWORD_KUAT` dengan password. Lalu jalankan di server MariaDB:

```bash
sudo mariadb < 00_create_database_user.sql
```

Agar bisa diakses dari server lain, pastikan `bind-address` di `/etc/mysql/mariadb.conf.d/50-server.cnf`
bukan `127.0.0.1`, dan firewall membuka port 3306 **hanya** untuk IP server aplikasi.

### 2. Upload aplikasi ke server aplikasi

Dari Windows (PowerShell):

```powershell
scp -r "C:\Users\IT\Desktop\Deploy Srv\testapp" user@IP-SERVER:~/
```

### 3. Jalankan deploy

```bash
cd ~/testapp
sudo DISABLE_DEFAULT_SITE=1 bash deploy/deploy.sh
#   atau dengan domain:  sudo SERVER_NAME=testapp.kantor.local bash deploy/deploy.sh
```

Script ini akan:

- mengecek semua paket;
- menyalin ke `/var/www/testapp`;
- membuat `.env`;
- mengatur izin file (kode milik root, `uploads/` milik www-data, `.env` 640);
- memasang `99-testapp.ini` dan site nginx;
- menjalankan `nginx -t` dan `php-fpm8.3 -t`;
- me-reload layanan;
- menjalankan cek sistem.

### 4. Isi kredensial database, lalu impor skema

```bash
sudo nano /var/www/testapp/.env                # DB_HOST, DB_USER, DB_PASS, ...
sudo bash /var/www/testapp/deploy/db-init.sh   # uji koneksi + impor schema.sql
```

### 5. Uji

| URL / perintah | Hasil yang diharapkan |
|---|---|
| `http://IP-SERVER/` | daftar 9 produk contoh, ringkasan Rupiah, grafik |
| `http://IP-SERVER/system.php` | semua baris **OK** |
| `curl -i http://IP-SERVER/health.php` | `HTTP/1.1 200` + JSON `"status": "ok"` |
| `sudo -u www-data php8.3 /var/www/testapp/bin/check.php` | `Hasil: OK` (exit code 0) |
| `curl -I http://IP-SERVER/.env` | `403 Forbidden` |

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
| Upload gagal "Folder upload tidak bisa ditulis" | `sudo chown -R www-data:www-data /var/www/testapp/public/uploads` |
| `413 Request Entity Too Large` | Naikkan `client_max_body_size` (nginx) dan `post_max_size`/`upload_max_filesize` (99-testapp.ini). |

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
