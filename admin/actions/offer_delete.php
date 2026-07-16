<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $db = Database::getInstance();
    $db->prepare("DELETE FROM offers WHERE id = :id")->execute([':id' => $id]);
}
header('Location: /admin/offers.php?deleted=1');
exit;
