<?php
/**
 * DUHN FRAGRANCES Admin — Login
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → redirect
if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $db = Database::getInstance();

    // TEMP: clear lockout + one-time emergency bypass
    $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $ip]);

    $TEMP_BYPASS_EMAIL = 'admin@duhnfragrances.com';
    $TEMP_BYPASS_PASS  = 'Duhn@Reset2026';

    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND role = 'admin' LIMIT 1");
    $stmt->execute([':email' => strtolower($email)]);
    $user = $stmt->fetch();

    // Emergency bypass: accept temp password and update hash
    $isBypass = ($email === $TEMP_BYPASS_EMAIL && $password === $TEMP_BYPASS_PASS);
    if ($isBypass && $user) {
        $newHash = password_hash($TEMP_BYPASS_PASS, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
           ->execute([':h' => $newHash, ':id' => $user['id']]);
        $user['password_hash'] = $newHash;
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id']   = $user['id'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_role'] = $user['role'];

        $redirect = $_GET['redirect'] ?? '/admin/index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — DUHN FRAGRANCES</title>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Barlow', sans-serif;
      background: #111;
      color: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-box {
      width: 100%;
      max-width: 420px;
      padding: 48px 40px;
      background: #1A1A1A;
      border: 1px solid #2A2A2A;
      border-radius: 12px;
    }
    .logo { font-size: 22px; font-weight: 700; letter-spacing: 0.1em; text-align: center; margin-bottom: 6px; }
    .logo span { color: #F8C417; }
    .subtitle { text-align: center; font-size: 13px; color: #888; margin-bottom: 32px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    label { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #888; }
    input {
      background: #111;
      border: 1px solid #2A2A2A;
      border-radius: 6px;
      padding: 12px 16px;
      color: #fff;
      font-size: 15px;
      font-family: 'Barlow', sans-serif;
      width: 100%;
      transition: border-color 0.2s;
    }
    input:focus { border-color: #F8C417; outline: none; }
    .btn {
      width: 100%;
      padding: 14px;
      background: #F8C417;
      color: #000;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      cursor: pointer;
      margin-top: 8px;
      transition: background 0.2s;
    }
    .btn:hover { background: #E0AA00; }
    .error {
      background: rgba(220,53,69,0.12);
      border: 1px solid rgba(220,53,69,0.3);
      color: #DC3545;
      border-radius: 6px;
      padding: 12px 16px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .back { display: block; text-align: center; margin-top: 20px; font-size: 13px; color: #888; }
    .back a { color: #F8C417; text-decoration: none; }
    .back a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="logo">DUHN <span>FRAGRANCES</span></div>
    <div class="subtitle">Admin Panel</div>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@duhnfragrances.com" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn">Sign In</button>
    </form>

    <p class="back"><a href="/index.php">← Back to website</a></p>
  </div>
</body>
</html>
