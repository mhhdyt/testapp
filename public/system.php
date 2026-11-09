<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$results = (new SystemCheck())->run();
$overall = SystemCheck::overall($results);
$labels  = [SystemCheck::OK => 'OK', SystemCheck::WARN => 'PERINGATAN', SystemCheck::FAIL => 'GAGAL'];

$grouped = [];
foreach ($results as $r) {
    $grouped[$r['group']][] = $r;
}

render_header('Cek Sistem', 'system.php');
?>

<div class="page-head">
    <h1>Cek Sistem</h1>
    <span class="badge badge-<?= e($overall) ?> big"><?= e($labels[$overall]) ?></span>
</div>

<p class="muted">Menguji nginx + php8.3-fpm, setiap ekstensi PHP yang terpasang, dan koneksi ke MariaDB
    (<code><?= e(Database::target()) ?></code>). Halaman ini menampilkan info server, jadi batasi aksesnya setelah pengujian selesai.</p>

<?php foreach ($grouped as $group => $items): ?>
    <h2><?= e($group) ?></h2>
    <div class="table-wrap">
    <table class="checks">
        <tbody>
        <?php foreach ($items as $r): ?>
            <tr>
                <td class="status"><span class="badge badge-<?= e($r['status']) ?>"><?= e($labels[$r['status']]) ?></span></td>
                <td class="name"><?= e($r['name']) ?></td>
                <td class="detail"><?= e($r['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endforeach; ?>

<?php render_footer(); ?>
