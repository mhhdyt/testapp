<?php

declare(strict_types=1);

define('APP_NO_SESSION', true);
require dirname(__DIR__) . '/src/bootstrap.php';

$format   = (string) ($_GET['format'] ?? 'csv');
$products = Database::connection()->query('SELECT * FROM products ORDER BY sku')->fetchAll();
$filename = 'inventaris-' . date('Ymd-His');

header('Cache-Control: no-store');

switch ($format) {
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        echo Exporter::csv($products);
        break;

    case 'xml':
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
        echo Exporter::xml($products);
        break;

    case 'zip':
        $zip = Exporter::zip($products, UPLOAD_PATH);
        try {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '.zip"');
            header('Content-Length: ' . filesize($zip));
            readfile($zip);
        } finally {
            @unlink($zip);
        }
        break;

    default:
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Format harus csv, xml, atau zip.';
}
