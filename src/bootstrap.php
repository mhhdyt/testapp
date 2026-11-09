<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/public/uploads');

require BASE_PATH . '/src/Env.php';
require BASE_PATH . '/src/helpers.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/ImageService.php';
require BASE_PATH . '/src/Exporter.php';
require BASE_PATH . '/src/SystemCheck.php';
require BASE_PATH . '/src/layout.php';

Env::load(BASE_PATH . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));

define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('[testapp] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<h1>500 - Terjadi kesalahan</h1>';
        echo APP_DEBUG
            ? '<pre>' . e((string) $e) . '</pre>'
            : '<p>Detail tercatat di log PHP-FPM / nginx error log.</p>';
    });

    // Halaman seperti health.php dan chart.php tidak butuh session.
    if (!defined('APP_NO_SESSION')) {
        session_name('testapp_sid');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => is_https(),
            'use_strict_mode' => true,
        ]);
    }
}
