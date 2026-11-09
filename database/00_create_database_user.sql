-- =============================================================================
-- Jalankan di SERVER MariaDB (bukan di server aplikasi) sebagai root/admin:
--     sudo mariadb < 00_create_database_user.sql
--
-- SEBELUM dijalankan, ganti:
--   10.0.0.20          -> IP server aplikasi (nginx + php8.3-fpm).
--                         Jika MariaDB di server yang sama, pakai 'localhost'.
--   GANTI_PASSWORD_KUAT -> password yang sama dengan DB_PASS di file .env
-- =============================================================================

CREATE DATABASE IF NOT EXISTS testapp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'testapp'@'10.0.0.20' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';

-- Hak akses minimum untuk aplikasi + membuat tabel dari schema.sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
    ON testapp.* TO 'testapp'@'10.0.0.20';

-- Cek hasil:
SHOW GRANTS FOR 'testapp'@'10.0.0.20';

-- Catatan untuk koneksi dari server lain:
--  * /etc/mysql/mariadb.conf.d/50-server.cnf -> bind-address = 0.0.0.0 (atau IP LAN server DB),
--    lalu: sudo systemctl restart mariadb
--  * Buka firewall hanya untuk IP server aplikasi, contoh UFW:
--    sudo ufw allow from 10.0.0.20 to any port 3306 proto tcp
