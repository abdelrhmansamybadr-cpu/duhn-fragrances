<?php
/**
 * DUHN FRAGRANCES — Page Builder image upload
 * Accepts: POST pb_image (file), inspo_n (1|2|3)
 * Returns: JSON { url } or { error }
 */
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pb_image']['tmp_name'])) {
    echo json_encode(['error' => 'No file received']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$tmp     = $_FILES['pb_image']['tmp_name'];
$mime    = mime_content_type($tmp);
$size    = $_FILES['pb_image']['size'] ?? 0;

if (!in_array($mime, $allowed)) {
    echo json_encode(['error' => 'File type not allowed (jpg/png/webp/gif only)']);
    exit;
}
if ($size > 8 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large (max 8 MB)']);
    exit;
}

$ext  = match($mime) {
    'image/webp' => 'webp',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$dir  = __DIR__ . '/../../public/images/inspo-blocks/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$name = 'pb_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
$dest = $dir . $name;

if (!move_uploaded_file($tmp, $dest)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

echo json_encode(['url' => '/public/images/inspo-blocks/' . $name]);
