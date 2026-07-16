<?php
/**
 * DUHN FRAGRANCES — Save/delete policy page custom templates
 * Accepts JSON: { action:'save'|'delete', tab, name?, html?, id? }
 * Stored in settings as policy_tpl_{tab}
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']); exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$body   = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

$allowed = ['about', 'shipping', 'exchange', 'refill'];
$tab     = $body['tab'] ?? '';
if (!in_array($tab, $allowed)) { echo json_encode(['ok' => false, 'error' => 'Invalid tab']); exit; }

$action  = $body['action'] ?? '';
$settKey = "policy_tpl_{$tab}";

try {
    $db  = Database::getInstance();
    $row = $db->query("SELECT `value` FROM `settings` WHERE `key`='{$settKey}'")->fetchColumn();
    $templates = json_decode($row ?: '[]', true) ?: [];
    $up = $db->prepare(
        "INSERT INTO `settings`(`key`,`value`) VALUES(:k,:v)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    );

    if ($action === 'save') {
        $name = trim($body['name'] ?? '');
        if (!$name) { echo json_encode(['ok' => false, 'error' => 'Name required']); exit; }
        $templates[] = ['id' => 'tpl_'.uniqid(), 'name' => $name, 'html' => $body['html'] ?? '', 'created' => date('d M Y')];
        $up->execute([':k' => $settKey, ':v' => json_encode($templates, JSON_UNESCAPED_UNICODE)]);
        echo json_encode(['ok' => true, 'templates' => $templates]);
    } elseif ($action === 'delete') {
        $templates = array_values(array_filter($templates, fn($t) => $t['id'] !== ($body['id'] ?? '')));
        $up->execute([':k' => $settKey, ':v' => json_encode($templates, JSON_UNESCAPED_UNICODE)]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
