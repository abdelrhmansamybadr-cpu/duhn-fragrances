<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/collections.php');
    exit;
}

$col = $db->prepare("SELECT cover_image_url FROM collections WHERE id = :id");
$col->execute([':id' => $id]);
$col = $col->fetch();

if ($col) {
    // Delete cover image file
    if ($col['cover_image_url']) {
        $path = __DIR__ . '/../../' . ltrim($col['cover_image_url'], '/');
        if (file_exists($path)) @unlink($path);
    }

    $db->prepare("DELETE FROM product_collections WHERE collection_id = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM collections WHERE id = :id")->execute([':id' => $id]);
}

header('Location: /admin/collections.php?deleted=1');
exit;
