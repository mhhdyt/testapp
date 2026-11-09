-- =============================================================================
-- Skema + data contoh Test App Inventaris.
-- Dari server aplikasi (memakai mariadb-client):
--     mariadb -h IP_DB01 -u stguser -p app_stg < database/schema.sql   (staging)
-- atau otomatis lewat: sudo bash deploy/db-init.sh
-- Aman dijalankan berulang (IF NOT EXISTS / INSERT IGNORE).
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    sku         VARCHAR(32)   NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    category    VARCHAR(50)   NULL,
    price       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    stock       INT UNSIGNED  NOT NULL DEFAULT 0,
    description TEXT          NULL,
    image       VARCHAR(64)   NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_name (name),
    KEY idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO products (sku, name, category, price, stock, description) VALUES
    ('ATK-001', 'Kertas HVS A4 80gsm',        'Alat Tulis', 52500.00, 120, 'Isi 500 lembar per rim'),
    ('ATK-002', 'Pulpen Gel Hitam 0.5mm',     'Alat Tulis',  4500.00, 300, NULL),
    ('ATK-003', 'Map Plastik Kancing',        'Alat Tulis',  3250.00,  12, 'Warna campur'),
    ('ELK-001', 'Mouse Wireless',             'Elektronik', 85000.00,  25, 'Receiver USB nano, baterai AA'),
    ('ELK-002', 'Kabel HDMI 2 Meter',         'Elektronik', 37500.00,  40, NULL),
    ('ELK-003', 'Flashdisk 32GB USB 3.0',     'Elektronik', 69900.00,   8, NULL),
    ('KBR-001', 'Sabun Cuci Tangan 500ml',    'Kebersihan', 18750.00,  48, 'Aroma jeruk nipis'),
    ('PNT-001', 'Kopi Bubuk Robusta 250g',    'Pantry',     32000.00,  15, 'Kopi Lampung ☕'),
    ('PNT-002', 'Teh Celup Isi 25',           'Pantry',      8900.00,  70, NULL);
