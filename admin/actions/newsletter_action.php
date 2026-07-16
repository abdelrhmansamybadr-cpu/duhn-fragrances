<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db     = Database::getInstance();
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($id && $action === 'delete') {
    $db->prepare("DELETE FROM newsletter_subscribers WHERE id = :id")->execute([':id' => $id]);
}

header('Location: /admin/newsletter.php');
exit;
