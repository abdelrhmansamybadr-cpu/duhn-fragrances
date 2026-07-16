<?php
/**
 * DUHN FRAGRANCES — Admin: SMTP diagnostic + test email
 * GET /admin/actions/test_email.php
 */
// Catch ALL PHP errors and return them as JSON so the browser can display them
// Also strip any BOM characters that sneaked in from included files
ob_start(function($buf) { return ltrim($buf, "\xEF\xBB\xBF"); });
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'message' => "❌ PHP Error [{$errno}]: {$errstr} in " . basename($errfile) . " line {$errline}"]);
    exit;
});
set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'message' => '❌ Exception: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ' line ' . $e->getLine()]);
    exit;
});

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

try {
    $db   = Database::getInstance();
    $rows = $db->query(
        "SELECT `key`, `value` FROM `settings`
         WHERE `key` IN (
            'notify_email','notify_from_name','notify_from_email',
            'smtp_host','smtp_port','smtp_user','smtp_pass'
         )"
    )->fetchAll();

    $s = [];
    foreach ($rows as $r) $s[$r['key']] = $r['value'];

    $to        = trim($s['notify_email']      ?? '');
    $fromName  = trim($s['notify_from_name']  ?? 'DUHN FRAGRANCES');
    $fromEmail = trim($s['notify_from_email'] ?? '');
    $smtpHost  = trim($s['smtp_host']         ?? '');
    $smtpPort  = (int)($s['smtp_port']         ?? 587);
    $smtpUser  = trim($s['smtp_user']          ?? '');
    $smtpPass  = trim($s['smtp_pass']          ?? '');

    // ── Preflight checks ─────────────────────────────────────────────
    if (!$to)       { echo json_encode(['ok'=>false,'message'=>'❌ "Send Notifications To" email is empty. Fill it and save first.']); exit; }
    if (!$smtpHost) { echo json_encode(['ok'=>false,'message'=>'❌ SMTP Host is empty. Enter smtp.gmail.com and save first.']); exit; }
    if (!$smtpUser) { echo json_encode(['ok'=>false,'message'=>'❌ SMTP Username is empty. Enter your Gmail address and save first.']); exit; }
    if (!$smtpPass) { echo json_encode(['ok'=>false,'message'=>'❌ SMTP Password is empty. Enter your App Password and save first.']); exit; }

    // ── Check OpenSSL is available ────────────────────────────────────
    if (!extension_loaded('openssl')) {
        echo json_encode(['ok'=>false,'message'=>'❌ OpenSSL PHP extension is disabled. Open XAMPP php.ini, find ;extension=openssl, remove the semicolon, restart Apache.']);
        exit;
    }

    // ── Step 1: Connect to SMTP host ──────────────────────────────────
    $ssl = ($smtpPort === 465);
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $dsn  = ($ssl ? 'ssl://' : '') . $smtpHost . ':' . $smtpPort;
    $sock = @stream_socket_client($dsn, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

    if (!$sock) {
        echo json_encode([
            'ok'      => false,
            'message' => "❌ Cannot connect to {$smtpHost}:{$smtpPort} — Error #{$errno}: {$errstr}\n\nThis usually means:\n• Your antivirus / Windows Firewall is blocking port 587\n• Try switching port to 465 in settings\n• Or test this on the live Hostinger server instead of XAMPP",
        ]);
        exit;
    }

    stream_set_timeout($sock, 15);

    $log  = [];
    $read = static function () use ($sock, &$log): string {
        $buf = '';
        while ($line = fgets($sock, 515)) {
            $buf .= $line;
            $log[] = '← ' . trim($line);
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $buf;
    };
    $write = static function (string $cmd) use ($sock, &$log): void {
        $log[] = '→ ' . ($cmd === base64_encode(base64_decode($cmd)) ? '(credentials hidden)' : $cmd);
        fwrite($sock, $cmd . "\r\n");
    };

    // ── Step 2: SMTP handshake ────────────────────────────────────────
    $greeting = $read();
    if (strpos($greeting, '220') === false) {
        fclose($sock);
        echo json_encode(['ok'=>false,'message'=>'❌ Bad greeting from SMTP server: ' . trim($greeting)]);
        exit;
    }

    $write("EHLO {$smtpHost}");
    $read();

    // ── Step 3: STARTTLS (port 587) ───────────────────────────────────
    if (!$ssl) {
        $write("STARTTLS");
        $tlsResp = $read();
        if (strpos($tlsResp, '220') === false) {
            fclose($sock);
            echo json_encode(['ok'=>false,'message'=>'❌ STARTTLS failed: ' . trim($tlsResp) . "\n\nTry switching port to 465 in settings."]);
            exit;
        }
        $crypto = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            fclose($sock);
            echo json_encode(['ok'=>false,'message'=>'❌ TLS handshake failed. Try port 465 instead of 587 in settings.']);
            exit;
        }
        $write("EHLO {$smtpHost}");
        $read();
    }

    // ── Step 4: AUTH LOGIN ────────────────────────────────────────────
    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($smtpUser));
    $read();
    $write(base64_encode($smtpPass));
    $authResp = $read();

    if (strpos($authResp, '235') === false) {
        fclose($sock);
        $hint = '';
        if (strpos($authResp, '535') !== false || strpos($authResp, '534') !== false) {
            $hint = "\n\n🔑 This is a wrong password error. For Gmail:\n• You must use an App Password (16 letters), NOT your Gmail login password.\n• Go to → myaccount.google.com/apppasswords\n• Make sure 2-Step Verification is ON first.";
        }
        echo json_encode(['ok'=>false,'message'=>'❌ Authentication failed: ' . trim($authResp) . $hint]);
        exit;
    }

    // ── Step 5: Send the test email ───────────────────────────────────
    $write("MAIL FROM:<{$smtpUser}>");
    $read();
    $write("RCPT TO:<{$to}>");
    $rcptResp = $read();

    if (strpos($rcptResp, '25') === false) {
        fclose($sock);
        echo json_encode(['ok'=>false,'message'=>'❌ Recipient rejected: ' . trim($rcptResp)]);
        exit;
    }

    $write("DATA");
    $read();

    $fromDisplay = $fromEmail ?: $smtpUser;
    $encFrom     = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encSubject  = '=?UTF-8?B?' . base64_encode('DUHN FRAGRANCES - Email Test') . '?=';
    $date        = date('r');
    $htmlBody    = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:32px;background:#f5f2ec;font-family:Arial,sans-serif">
  <table width="560" cellpadding="0" cellspacing="0" style="margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
    <tr><td style="background:#1a1a1a;padding:24px 32px">
      <p style="margin:0;font-size:20px;font-weight:700;color:#F8C417;letter-spacing:0.1em">DUHN FRAGRANCES</p>
    </td></tr>
    <tr><td style="padding:32px">
      <p style="font-size:18px;font-weight:700;color:#1a1a1a;margin:0 0 8px">Email configuration test</p>
      <p style="font-size:14px;color:#555;margin:0">Your SMTP configuration is correct. Order notification emails will be delivered to your inbox every time a customer places an order.</p>
    </td></tr>
    <tr><td style="background:#faf8f4;border-top:1px solid #f0ede6;padding:14px 32px;text-align:center">
      <p style="margin:0;font-size:11px;color:#bbb">DUHN FRAGRANCES - Egypt - Automated notification</p>
    </td></tr>
  </table>
</body></html>';

    $msgId = '<' . time() . '.' . mt_rand() . '@duhnfragrances.com>';
    $msg   = "Date: {$date}\r\n";
    $msg  .= "From: {$encFrom} <{$fromDisplay}>\r\n";
    $msg  .= "To: {$to}\r\n";
    $msg  .= "Reply-To: {$fromDisplay}\r\n";
    $msg  .= "Subject: {$encSubject}\r\n";
    $msg  .= "Message-ID: {$msgId}\r\n";
    $msg  .= "MIME-Version: 1.0\r\n";
    $msg  .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg  .= "Content-Transfer-Encoding: base64\r\n";
    $msg  .= "Auto-Submitted: auto-generated\r\n";
    $msg  .= "\r\n";
    $msg  .= chunk_split(base64_encode($htmlBody));
    $msg  .= "\r\n.";

    $write($msg);
    $dataResp = $read();
    $write("QUIT");
    fclose($sock);

    if (strpos($dataResp, '25') !== false) {
        echo json_encode([
            'ok'      => true,
            'message' => "✅ Test email sent to {$to} — check your inbox now!\n(Also check your Spam folder if you don't see it within 1 minute)",
        ]);
    } else {
        echo json_encode([
            'ok'      => false,
            'message' => '❌ Email was not accepted by the server: ' . trim($dataResp),
        ]);
    }

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => '❌ Unexpected error: ' . $e->getMessage()]);
}
