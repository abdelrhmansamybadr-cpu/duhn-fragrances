<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db     = Database::getInstance();
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($id) {
    switch ($action) {
        case 'read':
            $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute([':id' => $id]);
            break;
        case 'delete':
            $db->prepare("DELETE FROM contact_messages WHERE id = :id")->execute([':id' => $id]);
            break;
    }
}

$back = $_GET['back'] ?? '/admin/contact.php';
if (!str_starts_with($back, '/admin/')) $back = '/admin/contact.php';
header('Location: ' . $back);
exit;
