<?php
/**
 * ONE-TIME UNLOCK SCRIPT — DELETE THIS FILE AFTER USE
 */
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db  = Database::getInstance();
$msg = [];

// 1. Clear all login attempt lockouts
$db->query("DELETE FROM login_attempts");
$msg[] = '✅ Login lockout cleared — all failed attempts deleted.';

// 2. Reset admin password to: Admin@1234
$newHash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare("UPDATE users SET password_hash = :h WHERE role = 'admin'");
$stmt->execute([':h' => $newHash]);
$msg[] = '✅ Admin password reset to: <strong>Admin@1234</strong>';

// 3. Show admin users
$rows = $db->query("SELECT id, name, email, role FROM users WHERE role = 'admin'")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Unlock Admin</title>
  <style>
    body { font-family: sans-serif; background: #111; color: #fff; padding: 40px; }
    .box { background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 10px; padding: 32px; max-width: 500px; margin: 0 auto; }
    h2 { color: #F8C417; margin-bottom: 20px; }
    p  { margin: 8px 0; font-size: 15px; }
    .creds { background: #0D0D0D; border: 1px solid #333; border-radius: 6px; padding: 16px; margin: 20px 0; font-family: monospace; }
    .creds span { color: #F8C417; }
    .warn { background: rgba(220,53,69,0.15); border: 1px solid rgba(220,53,69,0.4); color: #ff6b6b; border-radius: 6px; padding: 12px 16px; margin-top: 20px; font-size: 13px; }
    a.btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #F8C417; color: #000; font-weight: 700; border-radius: 6px; text-decoration: none; }
  </style>
</head>
<body>
<div class="box">
  <h2>🔓 Admin Unlock</h2>
  <?php foreach ($msg as $m): ?>
  <p><?= $m ?></p>
  <?php endforeach; ?>

  <div class="creds">
    Email: &nbsp;<span>admin@duhnfragrances.com</span><br>
    Password: <span>Admin@1234</span>
  </div>

  <?php if ($rows): ?>
  <p style="color:#888;font-size:13px">Admin account: <?= htmlspecialchars($rows[0]['name']) ?> &lt;<?= htmlspecialchars($rows[0]['email']) ?>&gt;</p>
  <?php endif; ?>

  <div class="warn">
    ⚠️ <strong>Delete this file immediately after logging in!</strong><br>
    Path: <code>admin/unlock.php</code>
  </div>

  <a class="btn" href="/admin/login.php">→ Go to Login</a>
</div>
</body>
</html>
