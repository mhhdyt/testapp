<?php

declare(strict_types=1);

/**
 * Cek sistem dari terminal.
 *   sudo -u www-data php8.3 /var/www/testapp/bin/check.php
 * Jalankan sebagai www-data agar uji izin folder uploads sesuai dengan php-fpm.
 * Exit code 1 jika ada pengecekan yang GAGAL.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$results = (new SystemCheck())->run();
$color   = stream_isatty(STDOUT);
$paint   = static fn (string $s, string $code) => $color ? "\033[{$code}m{$s}\033[0m" : $s;
$label   = [
    SystemCheck::OK   => $paint(' OK  ', '32'),
    SystemCheck::WARN => $paint('WARN ', '33'),
    SystemCheck::FAIL => $paint('FAIL ', '31;1'),
];

$group = null;
foreach ($results as $r) {
    if ($r['group'] !== $group) {
        $group = $r['group'];
        echo PHP_EOL, $paint("== {$group} ==", '1'), PHP_EOL;
    }
    printf("[%s] %-18s %s\n", $label[$r['status']], $r['name'], $r['detail']);
}

$overall = SystemCheck::overall($results);
echo PHP_EOL, 'Hasil: ', $label[$overall], PHP_EOL;
echo 'Catatan: CLI memakai php.ini dari /etc/php/8.3/cli; nilai batas upload di web (fpm) bisa berbeda.', PHP_EOL;

exit($overall === SystemCheck::FAIL ? 1 : 0);
