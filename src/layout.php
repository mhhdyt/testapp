<?php

declare(strict_types=1);

function render_header(string $title, string $active = ''): void
{
    $nav = [
        'index.php'  => 'Inventaris',
        'form.php'   => 'Tambah Produk',
        'system.php' => 'Cek Sistem',
        'health.php' => 'Health (JSON)',
    ];
    $app = env('APP_NAME', 'Test App');
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($app) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="index.php"><?= e($app) ?></a>
        <nav>
            <?php foreach ($nav as $href => $label): ?>
                <a href="<?= e($href) ?>" class="<?= $active === $href ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="wrap">
    <?php foreach (flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="wrap footer">
    PHP <?= e(PHP_VERSION) ?> (<?= e(PHP_SAPI) ?>) · <?= e($_SERVER['SERVER_SOFTWARE'] ?? '') ?> · <?= e(tanggal(new DateTimeImmutable())) ?>
</footer>
</body>
</html>
    <?php
}

function render_db_error(Throwable $e): void
{
    error_log('[testapp] DB: ' . $e->getMessage());
    ?>
    <div class="alert alert-error">
        <strong>Tidak bisa terhubung / query ke MariaDB</strong> (<?= e(Database::target()) ?>).<br>
        <?= APP_DEBUG ? e($e->getMessage()) : 'Set APP_DEBUG=true di .env untuk melihat detail, atau buka halaman Cek Sistem.' ?>
    </div>
    <?php
}
