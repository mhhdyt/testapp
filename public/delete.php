<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

csrf_verify();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    flash('error', 'ID produk tidak valid.');
    redirect('index.php');
}

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT name, image FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('index.php');
}

$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
ImageService::delete($product['image'], UPLOAD_PATH);

flash('success', 'Produk "' . $product['name'] . '" dihapus.');
redirect('index.php');
