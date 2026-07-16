<?php
/**
 * DUHN FRAGRANCES — Save Inspiration Page Content (AJAX)
 * Accepts: JSON { id, content, cta_text, cta_url }
 * Returns: JSON { ok } or { ok:false, error }
 */
// Inline session check — NEVER redirect (would break AJAX JSON)
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated — please log in again']);
    exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id      = (int)($body['id'] ?? 0);
$content = $body['content']  ?? '';
$ctaText = trim($body['cta_text'] ?? '');
$ctaUrl  = trim($body['cta_url']  ?? '');

if ($id < 1 || $id > 3) {
    echo json_encode(['ok' => false, 'error' => 'Invalid post ID (must be 1–3)']);
    exit;
}

try {
    $db = Database::getInstance();

    $upsert = $db->prepare(
        "INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );

    $upsert->execute([':k' => "inspo_{$id}_page_body",    ':v' => $content]);
    if ($ctaText !== '') $upsert->execute([':k' => "inspo_{$id}_page_cta_text", ':v' => $ctaText]);
    if ($ctaUrl  !== '') $upsert->execute([':k' => "inspo_{$id}_page_cta_url",  ':v' => $ctaUrl]);

    // Handle publish toggle
    $publish = $body['publish'] ?? null;
    if ($publish === true)  $upsert->execute([':k' => "inspo_{$id}_mode", ':v' => 'page']);
    if ($publish === false) $upsert->execute([':k' => "inspo_{$id}_mode", ':v' => 'url']);
    // If publish not sent but content exists → auto-activate page mode
    if ($publish === null && trim($content) !== '') {
        $upsert->execute([':k' => "inspo_{$id}_mode", ':v' => 'page']);
    }

    // Return current mode so UI can update
    $modeRow = $db->prepare("SELECT `value` FROM `settings` WHERE `key` = :k");
    $modeRow->execute([':k' => "inspo_{$id}_mode"]);
    $currentMode = $modeRow->fetchColumn() ?: 'page';

    echo json_encode(['ok' => true, 'mode' => $currentMode]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
