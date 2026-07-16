<?php
http_response_code(404);
require_once __DIR__ . '/api/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page Not Found — DUHN FRAGRANCES</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/style.css">
  <style>
    .notfound-wrap {
      min-height: 80vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 40px 20px;
    }
    .notfound-code {
      font-size: 120px;
      font-weight: 700;
      color: var(--accent);
      line-height: 1;
      margin-bottom: 16px;
      opacity: .15;
    }
    .notfound-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 12px;
    }
    .notfound-sub {
      color: var(--text-muted);
      font-size: 15px;
      margin-bottom: 32px;
      max-width: 360px;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/public/layout/header.php'; ?>

<div class="notfound-wrap">
  <div class="notfound-code">404</div>
  <h1 class="notfound-title">Page Not Found</h1>
  <p class="notfound-sub">The page you're looking for doesn't exist or has been moved. Let's get you back on track.</p>
  <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center">
    <a href="/" class="btn-gold">Back to Home</a>
    <a href="/collections.php" class="btn-outline">Browse Collections</a>
  </div>
</div>

<?php require_once __DIR__ . '/public/layout/footer.php'; ?>
</body>
</html>
