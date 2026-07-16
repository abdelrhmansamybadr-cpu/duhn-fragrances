<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/products.php');
    exit;
}

// Fetch images to delete files
$imgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :id");
$imgs->execute([':id' => $id]);

try {
    $db->beginTransaction();

    // Delete pivot records
    $db->prepare("DELETE FROM product_collections WHERE product_id = :id")->execute([':id' => $id]);

    // Delete image files
    foreach ($imgs->fetchAll() as $img) {
        $path = __DIR__ . '/../../' . ltrim($img['image_url'], '/');
        if (file_exists($path)) @unlink($path);
    }
    $db->prepare("DELETE FROM product_images WHERE product_id = :id")->execute([':id' => $id]);

    // Delete cart items referencing this product
    $db->prepare("DELETE FROM cart_items WHERE product_id = :id")->execute([':id' => $id]);

    // Delete the product
    $db->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $id]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
}

header('Location: /admin/products.php?deleted=1');
exit;
