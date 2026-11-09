<?php

declare(strict_types=1);

/**
 * Grafik batang stok (PNG) yang digambar dengan GD, tanpa library tambahan.
 */
define('APP_NO_SESSION', true);
require dirname(__DIR__) . '/src/bootstrap.php';

$rows  = [];
$error = null;
try {
    $rows = Database::connection()
        ->query('SELECT name, stock FROM products ORDER BY stock DESC, name LIMIT 12')
        ->fetchAll();
} catch (Throwable $e) {
    error_log('[testapp] chart: ' . $e->getMessage());
    $error = 'Database tidak dapat diakses';
}

$rowH   = 26;
$labelW = 200;
$width  = 760;
$top    = 16;
$height = max(80, $top * 2 + max(1, count($rows)) * $rowH);

$img   = imagecreatetruecolor($width, $height);
$bg    = (int) imagecolorallocate($img, 255, 255, 255);
$text  = (int) imagecolorallocate($img, 31, 41, 55);
$muted = (int) imagecolorallocate($img, 156, 163, 175);
$bar   = (int) imagecolorallocate($img, 37, 99, 235);
$low   = (int) imagecolorallocate($img, 220, 38, 38);
$grid  = (int) imagecolorallocate($img, 229, 231, 235);
imagefill($img, 0, 0, $bg);

if ($error !== null || $rows === []) {
    imagestring($img, 4, 16, 30, $error ?? 'Belum ada data', $muted);
} else {
    $max    = max(1, (int) max(array_column($rows, 'stock')));
    $barMax = $width - $labelW - 70;

    for ($i = 0; $i <= 4; $i++) {
        $x = $labelW + (int) ($barMax * $i / 4);
        imageline($img, $x, $top - 6, $x, $height - $top + 4, $grid);
    }

    foreach ($rows as $i => $r) {
        $y     = $top + $i * $rowH;
        $stock = (int) $r['stock'];
        $w     = (int) round($barMax * $stock / $max);
        // Font bawaan GD hanya ASCII, jadi nama di-transliterasi dengan intl
        $label = mb_strimwidth(ascii((string) $r['name']), 0, 26, '..');

        imagestring($img, 3, 10, $y + 5, $label, $text);
        imagefilledrectangle($img, $labelW, $y + 4, $labelW + max(2, $w), $y + $rowH - 6, $stock < 20 ? $low : $bar);
        imagestring($img, 3, $labelW + max(2, $w) + 8, $y + 5, number_format($stock, 0, ',', '.'), $text);
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-store');
imagepng($img);
