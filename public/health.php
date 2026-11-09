<?php

declare(strict_types=1);

/**
 * Endpoint health check JSON untuk monitoring / load balancer.
 * 200 = sehat, 503 = database tidak dapat diakses.
 */
define('APP_NO_SESSION', true);
require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$t0 = hrtime(true);
try {
    $version = Database::connection()->query('SELECT VERSION()')->fetchColumn();
    $db      = ['status' => 'ok', 'version' => $version, 'latency_ms' => round((hrtime(true) - $t0) / 1e6, 2)];
} catch (Throwable $e) {
    error_log('[testapp] health: ' . $e->getMessage());
    $db = ['status' => 'error', 'message' => APP_DEBUG ? $e->getMessage() : 'koneksi database gagal'];
}

$missing = array_values(array_filter(
    array_keys(SystemCheck::EXTENSIONS),
    static fn (string $ext) => !extension_loaded($ext)
));

$healthy = $db['status'] === 'ok';
http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status'             => $healthy ? 'ok' : 'error',
    'app'                => env('APP_NAME', 'Test App'),
    'php'                => PHP_VERSION,
    'sapi'               => PHP_SAPI,
    'database'           => $db,
    'missing_extensions' => $missing,
    'time'               => date(DATE_ATOM),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
