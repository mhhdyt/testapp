<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$q        = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$products = [];
$dbError  = null;

try {
    $pdo = Database::connection();
    if ($q !== '') {
        // Native prepared statement tidak mengizinkan placeholder bernama yang sama dua kali
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $stmt = $pdo->prepare('SELECT * FROM products WHERE name LIKE :q1 OR sku LIKE :q2 OR category LIKE :q3 ORDER BY updated_at DESC, id DESC');
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);
    } else {
        $stmt = $pdo->query('SELECT * FROM products ORDER BY updated_at DESC, id DESC LIMIT 500');
    }
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e;
}

$sum = inventory_summary($products);

render_header('Inventaris', 'index.php');
?>

<div class="page-head">
    <h1>Inventaris Barang</h1>
    <a class="btn" href="form.php">+ Tambah Produk</a>
</div>

<?php if ($dbError !== null): ?>
    <?php render_db_error($dbError); ?>
<?php else: ?>

<section class="cards">
    <div class="card"><span>Jumlah produk</span><strong><?= e(angka($sum['items'])) ?></strong></div>
    <div class="card"><span>Total stok</span><strong><?= e(angka($sum['stock'])) ?></strong></div>
    <div class="card"><span>Nilai persediaan</span><strong><?= e(rupiah($sum['subtotal'])) ?></strong></div>
    <div class="card"><span>PPN 11%</span><strong><?= e(rupiah($sum['ppn'])) ?></strong></div>
    <div class="card accent"><span>Total + PPN</span><strong><?= e(rupiah($sum['total'])) ?></strong></div>
</section>

<div class="toolbar">
    <form method="get" class="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, SKU, atau kategori…">
        <button class="btn btn-light" type="submit">Cari</button>
        <?php if ($q !== ''): ?><a class="btn btn-light" href="index.php">Reset</a><?php endif; ?>
    </form>
    <div class="exports">
        Ekspor:
        <a class="btn btn-light" href="export.php?format=csv">CSV</a>
        <a class="btn btn-light" href="export.php?format=xml">XML</a>
        <a class="btn btn-light" href="export.php?format=zip">ZIP</a>
    </div>
</div>

<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th></th>
        <th>SKU</th>
        <th>Nama</th>
        <th>Kategori</th>
        <th class="num">Harga</th>
        <th class="num">Stok</th>
        <th class="num">Nilai</th>
        <th>Diperbarui</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if ($products === []): ?>
        <tr><td colspan="9" class="empty">Belum ada data<?= $q !== '' ? ' untuk pencarian "' . e($q) . '"' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($products as $p): ?>
        <tr>
            <td class="thumb">
                <?php if (!empty($p['image'])): ?>
                    <img src="uploads/<?= e($p['image']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="noimg"><?= e(mb_strtoupper(mb_substr((string) $p['name'], 0, 1))) ?></span>
                <?php endif; ?>
            </td>
            <td><code><?= e($p['sku']) ?></code></td>
            <td>
                <strong><?= e($p['name']) ?></strong>
                <?php if (!empty($p['description'])): ?>
                    <div class="muted"><?= e(mb_strimwidth((string) $p['description'], 0, 60, '…')) ?></div>
                <?php endif; ?>
            </td>
            <td><?= e($p['category'] ?? '-') ?></td>
            <td class="num"><?= e(rupiah($p['price'])) ?></td>
            <td class="num <?= (int) $p['stock'] < 20 ? 'low' : '' ?>"><?= e(angka($p['stock'])) ?></td>
            <td class="num"><?= e(rupiah(bcmul((string) $p['price'], (string) $p['stock'], 2))) ?></td>
            <td class="muted small"><?= e(tanggal($p['updated_at'], 'd MMM yyyy HH:mm')) ?></td>
            <td class="actions">
                <a class="btn btn-light btn-sm" href="form.php?id=<?= (int) $p['id'] ?>">Edit</a>
                <form method="post" action="delete.php" data-confirm="Hapus <?= e($p['name']) ?>?" onsubmit="return confirm(this.dataset.confirm)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($products !== []): ?>
<section class="chart">
    <h2>Stok per produk <small class="muted">(gambar PNG dibuat oleh GD)</small></h2>
    <img src="chart.php?v=<?= e((string) time()) ?>" alt="Grafik stok per produk" width="760">
</section>
<?php endif; ?>

<?php endif; ?>

<?php render_footer(); ?>
