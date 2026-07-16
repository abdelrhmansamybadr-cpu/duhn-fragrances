<?php
/**
 * DUHN FRAGRANCES — Template save / delete
 * Accepts JSON: { action:'save'|'delete', name?, html?, id? }
 * Templates stored as JSON array in settings key 'page_templates'
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']); exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

$action = $body['action'] ?? '';

try {
    $db  = Database::getInstance();
    $row = $db->query("SELECT `value` FROM `settings` WHERE `key`='page_templates'")->fetchColumn();
    $templates = json_decode($row ?: '[]', true) ?: [];

    $up = $db->prepare(
        "INSERT INTO `settings`(`key`,`value`) VALUES('page_templates',:v)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    );

    if ($action === 'save') {
        $name = trim($body['name'] ?? '');
        $html = $body['html'] ?? '';
        if (!$name) { echo json_encode(['ok' => false, 'error' => 'Name required']); exit; }
        $newId = 'tpl_' . uniqid();
        $templates[] = ['id' => $newId, 'name' => $name, 'html' => $html, 'created' => date('d M Y')];
        $up->execute([':v' => json_encode($templates, JSON_UNESCAPED_UNICODE)]);
        echo json_encode(['ok' => true, 'id' => $newId, 'templates' => $templates]);

    } elseif ($action === 'delete') {
        $delId = $body['id'] ?? '';
        $templates = array_values(array_filter($templates, fn($t) => $t['id'] !== $delId));
        $up->execute([':v' => json_encode($templates, JSON_UNESCAPED_UNICODE)]);
        echo json_encode(['ok' => true]);

    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
