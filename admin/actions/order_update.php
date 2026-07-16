<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db     = Database::getInstance();
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$status = $_POST['status'] ?? $_GET['status'] ?? '';

$allowed = ['pending','confirmed','shipped','delivered','cancelled'];

if ($id && in_array($status, $allowed)) {
    $db->prepare("UPDATE orders SET status = :s, updated_at = NOW() WHERE id = :id")
       ->execute([':s' => $status, ':id' => $id]);
}

$back = $_POST['back'] ?? $_GET['back'] ?? '/admin/orders.php';
// Sanitize redirect
if (!str_starts_with($back, '/admin/')) {
    $back = '/admin/orders.php';
}
header('Location: ' . $back);
exit;
