-- =============================================================================
-- Jalankan di SERVER 2 · db-01 (bukan di web-01/web-02) sebagai root:
--     sudo mariadb < 00_create_database_user.sql
--
-- Isinya sama dengan langkah 8.3 "Buat Database Produksi dan Staging".
-- Jika langkah itu sudah dijalankan manual, file ini aman dijalankan ulang
-- (IF NOT EXISTS) atau dilewati.
--
--   Lingkungan  Domain               Server web              Database  User
--   Produksi    app.devyourtech.com  web-01 (10.10.14.7)     app_prod  appuser
--   Staging     stg.devyourtech.com  web-02 (10.10.14.73)    app_stg   stguser
--
-- Password di sini harus sama dengan DB_PASS di file .env server web masing-masing.
-- =============================================================================

-- Produksi (app.devyourtech.com, dari web-01)
CREATE DATABASE IF NOT EXISTS app_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'appuser'@'10.10.14.7' IDENTIFIED BY 'PasswordProd#2026';
GRANT ALL PRIVILEGES ON app_prod.* TO 'appuser'@'10.10.14.7';

-- Staging (stg.devyourtech.com, dari web-02)
CREATE DATABASE IF NOT EXISTS app_stg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'stguser'@'10.10.14.73' IDENTIFIED BY 'PasswordStg#2026';
GRANT ALL PRIVILEGES ON app_stg.* TO 'stguser'@'10.10.14.73';

FLUSH PRIVILEGES;

-- Cek hasil:
SHOW GRANTS FOR 'appuser'@'10.10.14.7';
SHOW GRANTS FOR 'stguser'@'10.10.14.73';

-- Catatan agar web-01/web-02 bisa terhubung ke db-01:
--  * /etc/mysql/mariadb.conf.d/50-server.cnf -> bind-address = 0.0.0.0 (atau IP LAN db-01),
--    lalu: sudo systemctl restart mariadb
--  * Buka port 3306 hanya untuk kedua server web, contoh UFW:
--    sudo ufw allow from 10.10.14.7  to any port 3306 proto tcp
--    sudo ufw allow from 10.10.14.73 to any port 3306 proto tcp
