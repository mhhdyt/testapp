<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$id      = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$errors  = [];
$product = ['sku' => '', 'name' => '', 'category' => '', 'price' => '', 'stock' => '0', 'description' => '', 'image' => null];

try {
    $pdo = Database::connection();
} catch (PDOException $e) {
    render_header('Produk', 'form.php');
    render_db_error($e);
    render_footer();
    exit;
}

if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('error', 'Produk tidak ditemukan.');
        redirect('index.php');
    }
    $product = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Upload melebihi post_max_size membuat $_POST kosong
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Data terlalu besar (melebihi post_max_size ' . ini_get('post_max_size') . ').';
    }

    $input = [
        'sku'         => mb_strtoupper(trim((string) ($_POST['sku'] ?? ''))),
        'name'        => trim((string) ($_POST['name'] ?? '')),
        'category'    => trim((string) ($_POST['category'] ?? '')),
        'price'       => trim((string) ($_POST['price'] ?? '')),
        'stock'       => trim((string) ($_POST['stock'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ];

    if (!mb_check_encoding(implode('', $input), 'UTF-8')) {
        $errors[] = 'Input mengandung karakter yang bukan UTF-8.';
        $input    = array_map(static fn (string $v) => mb_scrub($v, 'UTF-8'), $input);
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9\-]{1,31}$/', $input['sku'])) {
        $errors[] = 'SKU wajib 2–32 karakter: huruf, angka, atau tanda minus.';
    }
    if ($input['name'] === '' || mb_strlen($input['name']) > 150) {
        $errors[] = 'Nama wajib diisi, maksimal 150 karakter.';
    }
    if (mb_strlen($input['category']) > 50) {
        $errors[] = 'Kategori maksimal 50 karakter.';
    }
    // Harga disimpan sebagai string agar presisi DECIMAL/bcmath terjaga
    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $input['price'])) {
        $errors[] = 'Harga harus angka ≥ 0 dengan maksimal 2 desimal (pakai titik).';
    }
    if (filter_var($input['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]) === false) {
        $errors[] = 'Stok harus bilangan bulat ≥ 0.';
    }
    if (mb_strlen($input['description']) > 2000) {
        $errors[] = 'Deskripsi maksimal 2000 karakter.';
    }

    $newImage = null;
    if ($errors === [] && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImage = ImageService::storeUpload($_FILES['image'], UPLOAD_PATH);
        } catch (Throwable $e) {
            $errors[] = 'Gambar: ' . $e->getMessage();
        }
    }

    if ($errors === []) {
        $oldImage = $product['image'] ?? null;
        $image    = $newImage ?? (!empty($_POST['remove_image']) ? null : $oldImage);

        $params = [
            'sku'         => $input['sku'],
            'name'        => $input['name'],
            'category'    => $input['category'] !== '' ? $input['category'] : null,
            'price'       => $input['price'],
            'stock'       => (int) $input['stock'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'image'       => $image,
        ];

        try {
            if ($id === null) {
                $pdo->prepare('INSERT INTO products (sku, name, category, price, stock, description, image)
                               VALUES (:sku, :name, :category, :price, :stock, :description, :image)')
                    ->execute($params);
                flash('success', 'Produk "' . $input['name'] . '" ditambahkan (ID ' . $pdo->lastInsertId() . ').');
            } else {
                $pdo->prepare('UPDATE products SET sku = :sku, name = :name, category = :category, price = :price,
                               stock = :stock, description = :description, image = :image WHERE id = :id')
                    ->execute($params + ['id' => $id]);
                flash('success', 'Produk "' . $input['name'] . '" diperbarui.');
            }

            if ($oldImage !== $image) {
                ImageService::delete($oldImage, UPLOAD_PATH);
            }
            redirect('index.php');
        } catch (PDOException $e) {
            ImageService::delete($newImage, UPLOAD_PATH);
            if (($e->errorInfo[1] ?? null) === 1062) {
                $errors[] = 'SKU "' . $input['sku'] . '" sudah dipakai produk lain.';
            } else {
                throw $e;
            }
        }
    }

    $product = array_merge($product, $input);
}

$title = $id === null ? 'Tambah Produk' : 'Edit Produk';
render_header($title, $id === null ? 'form.php' : '');
?>

<div class="page-head">
    <h1><?= e($title) ?></h1>
    <a class="btn btn-light" href="index.php">&larr; Kembali</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form" action="form.php<?= $id !== null ? '?id=' . $id : '' ?>">
    <?= csrf_field() ?>
    <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="grid">
        <label>SKU
            <input name="sku" required maxlength="32" value="<?= e($product['sku']) ?>" placeholder="ATK-001" style="text-transform:uppercase">
        </label>
        <label>Kategori
            <input name="category" maxlength="50" value="<?= e($product['category'] ?? '') ?>" placeholder="Alat Tulis" list="cats">
            <datalist id="cats"><option>Alat Tulis</option><option>Elektronik</option><option>Kebersihan</option><option>Pantry</option></datalist>
        </label>
    </div>

    <label>Nama produk
        <input name="name" required maxlength="150" value="<?= e($product['name']) ?>">
    </label>

    <div class="grid">
        <label>Harga (Rp)
            <input name="price" type="number" min="0" step="0.01" required value="<?= e($product['price']) ?>">
        </label>
        <label>Stok
            <input name="stock" type="number" min="0" step="1" required value="<?= e($product['stock']) ?>">
        </label>
    </div>

    <label>Deskripsi
        <textarea name="description" rows="3" maxlength="2000"><?= e($product['description'] ?? '') ?></textarea>
    </label>

    <label>Gambar (JPG/PNG/WEBP/GIF, maks <?= e(env('UPLOAD_MAX_MB', '5')) ?> MB — diproses dengan <?= e(ImageService::engine()) ?>)
        <input name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
    </label>

    <?php if (!empty($product['image'])): ?>
        <div class="current-image">
            <img src="uploads/<?= e($product['image']) ?>" alt="">
            <label class="check"><input type="checkbox" name="remove_image" value="1"> Hapus gambar ini</label>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button class="btn" type="submit">Simpan</button>
        <a class="btn btn-light" href="index.php">Batal</a>
    </div>
</form>

<?php render_footer(); ?>
