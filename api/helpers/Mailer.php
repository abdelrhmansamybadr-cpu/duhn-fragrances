<?php
/**
 * DUHN FRAGRANCES — Mailer Helper
 * Uses direct SMTP over PHP streams (no Composer / PHPMailer needed).
 * Works on XAMPP (local) and Hostinger (production).
 * Configure SMTP in Admin → Settings → Order Notification Emails.
 */
class Mailer
{
    // ── Public entry point ────────────────────────────────────────────
    public static function sendOrderNotification(array $order, array $items): bool
    {
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

            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

            $subject = "New Order #{$order['order_number']} - " . $order['customer_name'];
            $body    = self::buildEmailBody($order, $items);

            // Use SMTP if configured, else fall back to PHP mail()
            if ($smtpHost && $smtpUser && $smtpPass) {
                return self::smtpSend(
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass,
                    $fromEmail ?: $smtpUser, $fromName,
                    $to, $subject, $body
                );
            }

            // Fallback: PHP mail() — works on Hostinger without SMTP config
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <" . ($fromEmail ?: 'noreply@duhnfragrances.com') . ">\r\n";
            $headers .= "X-Priority: 1\r\n";
            return mail($to, $subject, $body, $headers);

        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Raw SMTP sender (no library needed) ──────────────────────────
    private static function smtpSend(
        string $host, int $port, string $user, string $pass,
        string $fromEmail, string $fromName,
        string $to, string $subject, string $htmlBody
    ): bool {
        try {
            $ssl = ($port === 465);

            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ]);

            $dsn  = ($ssl ? 'ssl://' : '') . $host . ':' . $port;
            $sock = @stream_socket_client($dsn, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
            if (!$sock) return false;

            stream_set_timeout($sock, 20);

            // Helper closures
            $read = static function () use ($sock): string {
                $buf = '';
                while ($line = fgets($sock, 515)) {
                    $buf .= $line;
                    if (isset($line[3]) && $line[3] === ' ') break;
                }
                return $buf;
            };

            $write = static function (string $cmd) use ($sock): void {
                fwrite($sock, $cmd . "\r\n");
            };

            $read(); // 220 greeting

            $write("EHLO {$host}");
            $read();

            // STARTTLS for port 587
            if (!$ssl) {
                $write("STARTTLS");
                $read();
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO {$host}");
                $read();
            }

            // AUTH LOGIN
            $write("AUTH LOGIN");
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $authResp = $read();

            if (strpos($authResp, '235') === false) {
                fclose($sock);
                return false;
            }

            $write("MAIL FROM:<{$fromEmail}>");
            $read();
            $write("RCPT TO:<{$to}>");
            $rcptResp = $read();

            if (strpos($rcptResp, '25') === false) {
                fclose($sock);
                return false;
            }

            $write("DATA");
            $read(); // 354

            $msgId   = '<' . time() . '.' . mt_rand() . '@duhnfragrances.com>';
            $encFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $encSubj = '=?UTF-8?B?' . base64_encode($subject)  . '?=';
            $date    = date('r'); // RFC 2822 date — required, missing = spam signal

            $msg  = "Date: {$date}\r\n";
            $msg .= "From: {$encFrom} <{$fromEmail}>\r\n";
            $msg .= "To: {$to}\r\n";
            $msg .= "Reply-To: {$fromEmail}\r\n";
            $msg .= "Subject: {$encSubj}\r\n";
            $msg .= "Message-ID: {$msgId}\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "Auto-Submitted: auto-generated\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split(base64_encode($htmlBody));
            $msg .= "\r\n.";

            $write($msg);
            $dataResp = $read();
            $write("QUIT");
            fclose($sock);

            return strpos($dataResp, '25') !== false;

        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Customer order confirmation email ────────────────────────────
    public static function sendOrderConfirmation(array $order, array $items): bool
    {
        $customerEmail = trim($order['customer_email'] ?? '');
        if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) return false;

        try {
            $db   = Database::getInstance();
            $rows = $db->query(
                "SELECT `key`, `value` FROM `settings`
                 WHERE `key` IN (
                    'notify_from_name','smtp_host','smtp_port','smtp_user','smtp_pass'
                 )"
            )->fetchAll();

            $s = [];
            foreach ($rows as $r) $s[$r['key']] = $r['value'];

            $fromName  = trim($s['notify_from_name'] ?? 'DUHN FRAGRANCES');
            $smtpHost  = trim($s['smtp_host']        ?? '');
            $smtpPort  = (int)($s['smtp_port']       ?? 587);
            $smtpUser  = trim($s['smtp_user']        ?? '');
            $smtpPass  = trim($s['smtp_pass']        ?? '');

            $subject = "Order Confirmed - #{$order['order_number']} - DUHN FRAGRANCES";
            $body    = self::buildConfirmationBody($order, $items);

            if ($smtpHost && $smtpUser && $smtpPass) {
                return self::smtpSend(
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass,
                    $smtpUser, $fromName,
                    $customerEmail, $subject, $body
                );
            }

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <noreply@duhnfragrances.com>\r\n";
            return mail($customerEmail, $subject, $body, $headers);

        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Customer confirmation email body ─────────────────────────────
    private static function buildConfirmationBody(array $order, array $items): string
    {
        $itemRows = '';
        foreach ($items as $item) {
            $name  = htmlspecialchars($item['product_name'] ?? ($item['name'] ?? ''));
            $qty   = (int)($item['quantity'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            $line  = (float)($item['line_total'] ?? ($price * $qty));
            $itemRows .= '
            <tr>
              <td style="padding:10px 14px;border-bottom:1px solid #e8e8e8;font-size:14px;color:#1a1a1a">'
                . $name . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #e8e8e8;text-align:center;font-size:14px;color:#555">'
                . $qty . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #e8e8e8;text-align:right;font-size:14px;font-weight:700;color:#1a1a1a">'
                . number_format($line, 0) . ' EGP</td>
            </tr>';
        }

        $discountRow = ((float)($order['discount'] ?? 0)) > 0
            ? '<tr>
                <td style="padding:4px 0;font-size:13px;color:#28a745">Promo Discount</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#28a745">-'
                  . number_format((float)$order['discount'], 0) . ' EGP</td>
               </tr>'
            : '';

        $deliveryText = ((float)($order['delivery_fee'] ?? 0)) > 0
            ? number_format((float)$order['delivery_fee'], 0) . ' EGP'
            : 'FREE';

        $payLabel = strtolower($order['payment_method'] ?? 'cod') === 'card'
            ? 'Credit/Debit Card'
            : 'Cash on Delivery';

        $customerName = htmlspecialchars($order['customer_name'] ?? 'Valued Customer');
        $orderNum     = htmlspecialchars($order['order_number']  ?? '');
        $address      = htmlspecialchars($order['delivery_address'] ?? '');
        $gov          = htmlspecialchars($order['governorate']      ?? '');

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">

      <tr><td style="background:#1a1a1a;padding:28px 32px">
        <p style="margin:0;font-size:22px;font-weight:700;color:#F8C417;letter-spacing:0.1em">DUHN FRAGRANCES</p>
        <p style="margin:4px 0 0;font-size:12px;color:#aaa;letter-spacing:0.08em">ORDER CONFIRMATION</p>
      </td></tr>

      <tr><td style="background:#fffdf5;border-bottom:3px solid #F8C417;padding:24px 32px">
        <p style="margin:0;font-size:14px;color:#555">Hello, <strong style="color:#1a1a1a">' . $customerName . '</strong></p>
        <p style="margin:8px 0 0;font-size:15px;color:#1a1a1a">Thank you for your order! We have received it and will be processing it shortly.</p>
      </td></tr>

      <tr><td style="padding:24px 32px 12px">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding-bottom:14px;width:50%;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Order Number</p>
              <p style="margin:0;font-size:18px;font-weight:700;color:#1a1a1a">#' . $orderNum . '</p>
            </td>
            <td style="padding-bottom:14px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Payment Method</p>
              <p style="margin:0;font-size:14px;color:#1a1a1a">' . htmlspecialchars($payLabel) . '</p>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-bottom:8px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Delivery Address</p>
              <p style="margin:0;font-size:14px;color:#555">' . $address . ', ' . $gov . '</p>
            </td>
          </tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 32px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:8px;overflow:hidden">
          <thead>
            <tr style="background:#f9f9f9">
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:left">Product</th>
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:center">Qty</th>
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:right">Total</th>
            </tr>
          </thead>
          <tbody>' . $itemRows . '</tbody>
        </table>
      </td></tr>

      <tr><td style="padding:0 32px 28px">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="right">
            <table cellpadding="0" cellspacing="0" style="min-width:220px">
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888">Subtotal</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#555">'
                  . number_format((float)($order['subtotal'] ?? 0), 0) . ' EGP</td>
              </tr>
              ' . $discountRow . '
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888">Delivery</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#555">' . $deliveryText . '</td>
              </tr>
              <tr>
                <td style="padding:10px 0 0;font-size:16px;font-weight:700;color:#1a1a1a;border-top:2px solid #e8e8e8">ORDER TOTAL</td>
                <td style="padding:10px 0 0;text-align:right;font-size:18px;font-weight:700;color:#1a1a1a;border-top:2px solid #e8e8e8">'
                  . number_format((float)($order['total'] ?? 0), 0) . ' EGP</td>
              </tr>
            </table>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="background:#fffdf5;border-top:1px solid #e8e8e8;padding:20px 32px;text-align:center">
        <p style="margin:0;font-size:13px;color:#555">Questions? Contact us and quote your order number.</p>
        <p style="margin:6px 0 0;font-size:11px;color:#aaa">DUHN FRAGRANCES &middot; Egypt &middot; This is an automated confirmation</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    // ── Welcome / newsletter promo email ─────────────────────────────
    public static function sendWelcomePromoEmail(string $toEmail, string $promoCode, string $discountMode = 'per_product', int $discountValue = 20): bool
    {
        try {
            $db   = Database::getInstance();
            $rows = $db->query(
                "SELECT `key`, `value` FROM `settings`
                 WHERE `key` IN ('notify_from_name','smtp_host','smtp_port','smtp_user','smtp_pass')"
            )->fetchAll();

            $s = [];
            foreach ($rows as $r) $s[$r['key']] = $r['value'];

            $fromName = trim($s['notify_from_name'] ?? 'DUHN FRAGRANCES');
            $smtpHost = trim($s['smtp_host']        ?? '');
            $smtpPort = (int)($s['smtp_port']       ?? 587);
            $smtpUser = trim($s['smtp_user']        ?? '');
            $smtpPass = trim($s['smtp_pass']        ?? '');

            if ($discountMode === 'percentage') {
                $subject = "Your {$discountValue}% Wallet Discount is Active — DUHN FRAGRANCES";
            } elseif ($discountMode === 'wallet_credit') {
                $subject = "Your {$discountValue} EGP Welcome Credit is Ready — DUHN FRAGRANCES";
            } else {
                $subject = "Your Welcome Code + Wallet Discount are Here — DUHN FRAGRANCES";
            }
            $body = self::buildWelcomeEmailBody($promoCode, $discountMode, $discountValue);

            if ($smtpHost && $smtpUser && $smtpPass) {
                return self::smtpSend(
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass,
                    $smtpUser, $fromName,
                    $toEmail, $subject, $body
                );
            }

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <noreply@duhnfragrances.com>\r\n";
            $headers .= "Auto-Submitted: auto-generated\r\n";
            return mail($toEmail, $subject, $body, $headers);

        } catch (Throwable $e) {
            return false;
        }
    }

    private static function buildWelcomeEmailBody(string $promoCode, string $discountMode = 'per_product', int $discountValue = 20): string
    {
        $shopUrl  = defined('APP_URL') ? APP_URL : 'https://duhnfragrances.com';
        $codeHtml = htmlspecialchars($promoCode);

        // Build mode-specific hero text and description
        if ($discountMode === 'percentage') {
            $heroLabel    = $discountValue . '% OFF every order';
            $heroBig      = 'Your <span style="color:#c9a227">' . $discountValue . '% Wallet Discount</span> is Active';
            $descHtml     = 'As a subscriber, <strong style="color:#1a1a1a">' . $discountValue . '% is automatically deducted</strong> from every order you place — no code needed for the discount.';
            $codeNote     = 'Plus use this one-time welcome code for an extra bonus at checkout:';
            $walletNote   = 'Wallet discount &middot; Applied automatically every order &middot; All fragrances';
        } elseif ($discountMode === 'wallet_credit') {
            $heroLabel    = $discountValue . ' EGP Welcome Credit';
            $heroBig      = 'Your <span style="color:#c9a227">' . $discountValue . ' EGP Credit</span> Awaits';
            $descHtml     = 'As a subscriber, you get <strong style="color:#1a1a1a">' . $discountValue . ' EGP off your first order total</strong> — automatically applied in the cart.';
            $codeNote     = 'Plus use this one-time welcome code for an extra bonus at checkout:';
            $walletNote   = 'One-time credit &middot; First order only &middot; Applied automatically';
        } else {
            // per_product (default)
            $heroLabel    = $discountValue . ' EGP off every new product';
            $heroBig      = 'Your <span style="color:#c9a227">' . $discountValue . ' EGP Wallet Discount</span> is Active';
            $descHtml     = 'As a subscriber, you save <strong style="color:#1a1a1a">' . $discountValue . ' EGP on each new product</strong> you buy — applied automatically in the cart every order.';
            $codeNote     = 'Plus use this one-time welcome code for an extra bonus at checkout:';
            $walletNote   = 'Wallet discount &middot; Per new product &middot; Applied automatically';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f2ec;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ec;padding:40px 0">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0"
           style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 28px rgba(0,0,0,0.09)">

      <!-- Header -->
      <tr><td style="background:#1a1a1a;padding:28px 36px;text-align:center">
        <p style="margin:0;font-size:22px;font-weight:700;color:#F8C417;letter-spacing:0.12em">DUHN FRAGRANCES</p>
        <p style="margin:5px 0 0;font-size:11px;color:#888;letter-spacing:0.1em;text-transform:uppercase">Welcome Gift</p>
      </td></tr>

      <!-- Hero strip -->
      <tr><td style="background:#fffdf5;border-bottom:3px solid #F8C417;padding:32px 36px;text-align:center">
        <p style="margin:0 0 8px;font-size:13px;color:#999;text-transform:uppercase;letter-spacing:0.1em">Thank you for subscribing</p>
        <p style="margin:0;font-size:26px;font-weight:700;color:#1a1a1a;line-height:1.3">' . $heroBig . '</p>
      </td></tr>

      <!-- Wallet benefit description -->
      <tr><td style="padding:28px 36px 8px;text-align:center">
        <p style="margin:0;font-size:14px;color:#555;line-height:1.6">' . $descHtml . '</p>
      </td></tr>

      <!-- Welcome promo code -->
      <tr><td style="padding:16px 36px 28px;text-align:center">
        <p style="margin:0 0 14px;font-size:13px;color:#888">' . $codeNote . '</p>
        <div style="display:inline-block;background:#1a1a1a;border-radius:10px;padding:18px 40px;margin:0 auto">
          <p style="margin:0;font-size:28px;font-weight:700;color:#F8C417;letter-spacing:0.18em;font-family:Courier New,monospace">'
             . $codeHtml . '</p>
        </div>
        <p style="margin:14px 0 0;font-size:12px;color:#aaa">Single-use &middot; No minimum order &middot; All fragrances</p>
        <p style="margin:6px 0 0;font-size:11px;color:#bbb">' . $walletNote . '</p>
      </td></tr>

      <!-- CTA -->
      <tr><td style="padding:8px 36px 36px;text-align:center">
        <a href="' . $shopUrl . '/collections.php"
           style="display:inline-block;padding:15px 44px;background:#F8C417;color:#1a1a1a;
                  font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;
                  letter-spacing:0.07em">
          SHOP NOW →
        </a>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#faf8f4;border-top:1px solid #f0ede6;padding:18px 36px;text-align:center">
        <p style="margin:0;font-size:11px;color:#bbb">
          DUHN FRAGRANCES &middot; Egypt &middot; This code is valid for one use only.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    // ── Order status update email to customer ────────────────────────
    public static function sendOrderStatusUpdate(array $order, array $items, string $newStatus): bool
    {
        $customerEmail = trim($order['customer_email'] ?? '');
        if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) return false;
        // Don't send on 'pending' — no news yet
        if ($newStatus === 'pending') return false;

        try {
            $db   = Database::getInstance();
            $rows = $db->query(
                "SELECT `key`, `value` FROM `settings`
                 WHERE `key` IN ('notify_from_name','smtp_host','smtp_port','smtp_user','smtp_pass')"
            )->fetchAll();

            $s = [];
            foreach ($rows as $r) $s[$r['key']] = $r['value'];

            $fromName = trim($s['notify_from_name'] ?? 'DUHN FRAGRANCES');
            $smtpHost = trim($s['smtp_host']        ?? '');
            $smtpPort = (int)($s['smtp_port']       ?? 587);
            $smtpUser = trim($s['smtp_user']        ?? '');
            $smtpPass = trim($s['smtp_pass']        ?? '');

            $subjects = [
                'confirmed' => "✅ Order #{$order['order_number']} Confirmed — DUHN FRAGRANCES",
                'shipped'   => "🚚 Your Order #{$order['order_number']} is On Its Way!",
                'delivered' => "🎉 Order #{$order['order_number']} Delivered — Enjoy Your Scent!",
                'cancelled' => "❌ Order #{$order['order_number']} Cancelled — DUHN FRAGRANCES",
            ];
            $subject = $subjects[$newStatus] ?? "Order #{$order['order_number']} Update — DUHN FRAGRANCES";
            $body    = self::buildStatusEmailBody($order, $items, $newStatus);

            if ($smtpHost && $smtpUser && $smtpPass) {
                return self::smtpSend(
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass,
                    $smtpUser, $fromName,
                    $customerEmail, $subject, $body
                );
            }

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <noreply@duhnfragrances.com>\r\n";
            return mail($customerEmail, $subject, $body, $headers);

        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Status update email body ─────────────────────────────────────
    private static function buildStatusEmailBody(array $order, array $items, string $status): string
    {
        $statusConfig = [
            'confirmed' => [
                'icon'    => '✅',
                'heading' => 'Order Confirmed!',
                'color'   => '#28a745',
                'message' => 'Great news! We\'ve received your order and our team is preparing your fragrances with care. You\'ll receive another update once your order is shipped.',
            ],
            'shipped' => [
                'icon'    => '🚚',
                'heading' => 'Your Order is On Its Way!',
                'color'   => '#007bff',
                'message' => 'Your DUHN FRAGRANCES order has been handed to our delivery partner and is on its way to you. Delivery typically takes 2–5 business days.',
            ],
            'delivered' => [
                'icon'    => '🎉',
                'heading' => 'Order Delivered — Enjoy!',
                'color'   => '#F8C417',
                'message' => 'Your order has been delivered! We hope you love your new fragrances. Don\'t forget to share your experience and leave a review.',
            ],
            'cancelled' => [
                'icon'    => '❌',
                'heading' => 'Order Cancelled',
                'color'   => '#dc3545',
                'message' => 'Your order has been cancelled. If you have any questions or believe this was an error, please contact us on WhatsApp or Instagram and we\'ll be happy to help.',
            ],
        ];

        $cfg          = $statusConfig[$status] ?? ['icon'=>'📦','heading'=>'Order Update','color'=>'#888','message'=>'Your order status has been updated.'];
        $customerName = htmlspecialchars($order['customer_name'] ?? 'Valued Customer');
        $orderNum     = htmlspecialchars($order['order_number']  ?? '');
        $shopUrl      = defined('APP_URL') ? APP_URL : 'https://duhnfragrances.com';

        $itemRows = '';
        foreach ($items as $item) {
            $name  = htmlspecialchars($item['product_name'] ?? ($item['name'] ?? ''));
            $qty   = (int)($item['quantity'] ?? 1);
            $line  = (float)($item['line_total'] ?? 0);
            $itemRows .= '
            <tr>
              <td style="padding:10px 14px;border-bottom:1px solid #eee;font-size:13px;color:#333">' . $name . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:center;font-size:13px;color:#666">×' . $qty . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:right;font-size:13px;font-weight:700;color:#1a1a1a">' . number_format($line, 0) . ' EGP</td>
            </tr>';
        }

        $ctaHtml = $status === 'delivered'
            ? '<a href="' . $shopUrl . '/collections.php"
                  style="display:inline-block;padding:13px 36px;background:#F8C417;color:#1a1a1a;font-size:13px;font-weight:700;text-decoration:none;border-radius:8px;letter-spacing:0.06em">
                SHOP AGAIN →
               </a>'
            : ($status === 'cancelled'
                ? '<a href="https://wa.me/201157879622"
                      style="display:inline-block;padding:13px 36px;background:#25D366;color:#fff;font-size:13px;font-weight:700;text-decoration:none;border-radius:8px">
                    💬 Contact Us on WhatsApp
                   </a>'
                : '');

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">

      <!-- Header -->
      <tr><td style="background:#1a1a1a;padding:26px 32px">
        <p style="margin:0;font-size:22px;font-weight:700;color:#F8C417;letter-spacing:0.1em">DUHN FRAGRANCES</p>
        <p style="margin:4px 0 0;font-size:11px;color:#888;letter-spacing:0.1em;text-transform:uppercase">ORDER UPDATE</p>
      </td></tr>

      <!-- Status Banner -->
      <tr><td style="background:' . $cfg['color'] . '22;border-bottom:3px solid ' . $cfg['color'] . ';padding:28px 32px;text-align:center">
        <p style="margin:0;font-size:36px;line-height:1">' . $cfg['icon'] . '</p>
        <p style="margin:10px 0 0;font-size:22px;font-weight:700;color:#1a1a1a">' . $cfg['heading'] . '</p>
        <p style="margin:4px 0 0;font-size:13px;color:#888">Order #' . $orderNum . '</p>
      </td></tr>

      <!-- Message -->
      <tr><td style="padding:24px 32px 8px">
        <p style="margin:0;font-size:14px;color:#444;line-height:1.7">
          Hello <strong>' . $customerName . '</strong>,<br><br>' . $cfg['message'] . '
        </p>
      </td></tr>

      <!-- Order Summary -->
      <tr><td style="padding:16px 32px 24px">
        <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Your Order Summary</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;border-radius:8px;overflow:hidden">
          <thead>
            <tr style="background:#f9f9f9">
              <th style="padding:9px 14px;font-size:11px;color:#888;text-transform:uppercase;text-align:left">Product</th>
              <th style="padding:9px 14px;font-size:11px;color:#888;text-transform:uppercase;text-align:center">Qty</th>
              <th style="padding:9px 14px;font-size:11px;color:#888;text-transform:uppercase;text-align:right">Total</th>
            </tr>
          </thead>
          <tbody>' . $itemRows . '</tbody>
          <tfoot>
            <tr style="background:#f9f9f9">
              <td colspan="2" style="padding:10px 14px;font-size:14px;font-weight:700;color:#1a1a1a">ORDER TOTAL</td>
              <td style="padding:10px 14px;font-size:16px;font-weight:700;color:#1a1a1a;text-align:right">' . number_format((float)($order['total'] ?? 0), 0) . ' EGP</td>
            </tr>
          </tfoot>
        </table>
      </td></tr>

      <!-- CTA -->
      ' . ($ctaHtml ? '<tr><td style="padding:4px 32px 28px;text-align:center">' . $ctaHtml . '</td></tr>' : '') . '

      <!-- Footer -->
      <tr><td style="background:#fafafa;border-top:1px solid #eee;padding:18px 32px;text-align:center">
        <p style="margin:0;font-size:12px;color:#aaa">Questions? WhatsApp us at <a href="https://wa.me/201157879622" style="color:#25D366">+20 115 787 9622</a></p>
        <p style="margin:6px 0 0;font-size:11px;color:#ccc">DUHN FRAGRANCES · Egypt · Automated notification</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    // ── Abandoned cart recovery email ─────────────────────────────────
    public static function sendAbandonedCartEmail(string $toEmail, string $customerName, array $cartItems, float $total): bool
    {
        try {
            $db   = Database::getInstance();
            $rows = $db->query(
                "SELECT `key`, `value` FROM `settings`
                 WHERE `key` IN ('notify_from_name','smtp_host','smtp_port','smtp_user','smtp_pass')"
            )->fetchAll();

            $s = [];
            foreach ($rows as $r) $s[$r['key']] = $r['value'];

            $fromName = trim($s['notify_from_name'] ?? 'DUHN FRAGRANCES');
            $smtpHost = trim($s['smtp_host']        ?? '');
            $smtpPort = (int)($s['smtp_port']       ?? 587);
            $smtpUser = trim($s['smtp_user']        ?? '');
            $smtpPass = trim($s['smtp_pass']        ?? '');

            $subject = '🛒 You left something behind — Complete Your DUHN Order';
            $body    = self::buildAbandonedCartBody($toEmail, $customerName, $cartItems, $total);

            if ($smtpHost && $smtpUser && $smtpPass) {
                return self::smtpSend(
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass,
                    $smtpUser, $fromName,
                    $toEmail, $subject, $body
                );
            }

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <noreply@duhnfragrances.com>\r\n";
            return mail($toEmail, $subject, $body, $headers);

        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Abandoned cart email body ────────────────────────────────────
    private static function buildAbandonedCartBody(string $email, string $name, array $items, float $total): string
    {
        $shopUrl     = defined('APP_URL') ? APP_URL : 'https://duhnfragrances.com';
        $displayName = $name ?: 'there';
        $itemRows    = '';

        foreach ($items as $item) {
            $iName  = htmlspecialchars($item['name'] ?? '');
            $qty    = (int)($item['quantity'] ?? 1);
            $price  = (float)($item['price'] ?? 0);
            $img    = htmlspecialchars($item['image'] ?? '');
            $imgTag = $img
                ? '<img src="' . $shopUrl . $img . '" width="52" height="52" style="object-fit:cover;border-radius:6px;display:block">'
                : '<div style="width:52px;height:52px;background:#f0ece6;border-radius:6px"></div>';
            $itemRows .= '
            <tr>
              <td style="padding:10px 14px;vertical-align:middle">' . $imgTag . '</td>
              <td style="padding:10px 4px;font-size:13px;color:#1a1a1a;font-weight:600">' . $iName . '</td>
              <td style="padding:10px 4px;text-align:center;font-size:13px;color:#666">×' . $qty . '</td>
              <td style="padding:10px 14px;text-align:right;font-size:13px;font-weight:700;color:#1a1a1a">' . number_format($price * $qty, 0) . ' EGP</td>
            </tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">

      <!-- Header -->
      <tr><td style="background:#1a1a1a;padding:26px 32px;text-align:center">
        <p style="margin:0;font-size:22px;font-weight:700;color:#F8C417;letter-spacing:0.1em">DUHN FRAGRANCES</p>
      </td></tr>

      <!-- Hero -->
      <tr><td style="background:#fffdf5;border-bottom:3px solid #F8C417;padding:30px 32px;text-align:center">
        <p style="margin:0;font-size:32px">🛒</p>
        <p style="margin:10px 0 6px;font-size:22px;font-weight:700;color:#1a1a1a">You left something behind!</p>
        <p style="margin:0;font-size:14px;color:#666;line-height:1.6">
          Hello <strong>' . htmlspecialchars($displayName) . '</strong>, your cart is waiting for you.<br>
          Your fragrances are almost yours — complete your order before they sell out.
        </p>
      </td></tr>

      <!-- Cart Items -->
      <tr><td style="padding:24px 32px 0">
        <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Items In Your Cart</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;border-radius:8px;overflow:hidden">
          <tbody>' . $itemRows . '</tbody>
          <tfoot>
            <tr style="background:#f9f9f9;border-top:2px solid #eee">
              <td colspan="3" style="padding:10px 14px;font-size:14px;font-weight:700;color:#1a1a1a">TOTAL</td>
              <td style="padding:10px 14px;text-align:right;font-size:16px;font-weight:700;color:#1a1a1a">' . number_format($total, 0) . ' EGP</td>
            </tr>
          </tfoot>
        </table>
      </td></tr>

      <!-- Urgency message -->
      <tr><td style="padding:20px 32px">
        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:14px 18px;text-align:center">
          <p style="margin:0;font-size:13px;color:#b8860b">
            ✨ <strong>Exclusive Offer:</strong> Buy any 2 fragrances and get 2 <strong>FREE</strong> — automatically applied at checkout!
          </p>
        </div>
      </td></tr>

      <!-- CTA -->
      <tr><td style="padding:8px 32px 32px;text-align:center">
        <a href="' . $shopUrl . '/collections.php"
           style="display:inline-block;padding:15px 48px;background:#F8C417;color:#1a1a1a;
                  font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;letter-spacing:0.06em">
          COMPLETE MY ORDER →
        </a>
        <p style="margin:14px 0 0;font-size:12px;color:#aaa">
          Need help? <a href="https://wa.me/201157879622" style="color:#25D366">WhatsApp us</a> anytime.
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#fafafa;border-top:1px solid #eee;padding:16px 32px;text-align:center">
        <p style="margin:0;font-size:11px;color:#ccc">
          DUHN FRAGRANCES · Egypt ·
          <a href="' . $shopUrl . '/collections.php" style="color:#F8C417">Shop Now</a>
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    // ── HTML email body builder ───────────────────────────────────────
    private static function buildEmailBody(array $order, array $items): string
    {
        $itemRows = '';
        foreach ($items as $item) {
            $itemRows .= '
            <tr>
              <td style="padding:10px 14px;border-bottom:1px solid #f0ede6;font-size:14px;color:#1a1a1a">'
                . htmlspecialchars($item['product_name'] ?? ($item['name'] ?? '')) . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #f0ede6;text-align:center;font-size:14px;color:#555">'
                . (int)$item['quantity'] . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #f0ede6;text-align:right;font-size:14px;font-weight:700;color:#1a1a1a">'
                . number_format((float)($item['line_total'] ?? ((float)$item['price'] * (int)$item['quantity'])), 0) . ' EGP</td>
            </tr>';
        }

        $orderUrl = (defined('APP_URL') ? APP_URL : 'http://localhost:8080')
                  . '/admin/orders.php?search=' . urlencode($order['order_number']);

        $payBadge = strtolower($order['payment_method'] ?? 'cod') === 'card'
            ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700">💳 CARD</span>'
            : '<span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700">💵 CASH ON DELIVERY</span>';

        $discountRow = ((float)($order['discount'] ?? 0)) > 0
            ? '<tr>
                <td style="padding:4px 0;font-size:13px;color:#28a745">Promo Discount</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#28a745">−'
                  . number_format((float)$order['discount'], 0) . ' EGP</td>
               </tr>'
            : '';

        $deliveryText = ((float)($order['delivery_fee'] ?? 0)) > 0
            ? number_format((float)$order['delivery_fee'], 0) . ' EGP'
            : 'FREE';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f2ec;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ec;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">

      <tr><td style="background:#1a1a1a;padding:28px 32px">
        <p style="margin:0;font-size:22px;font-weight:700;color:#F8C417;letter-spacing:0.1em">DUHN FRAGRANCES</p>
        <p style="margin:4px 0 0;font-size:12px;color:#888;letter-spacing:0.08em">ORDER NOTIFICATION</p>
      </td></tr>

      <tr><td style="background:#fffbf0;border-bottom:2px solid #F8C417;padding:20px 32px">
        <p style="margin:0;font-size:13px;color:#888;text-transform:uppercase;letter-spacing:0.08em">New Order Received</p>
        <p style="margin:4px 0 0;font-size:28px;font-weight:700;color:#1a1a1a">#' . htmlspecialchars($order['order_number']) . '</p>
      </td></tr>

      <tr><td style="padding:24px 32px">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding-bottom:12px;width:50%;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Customer</p>
              <p style="margin:0;font-size:15px;color:#1a1a1a;font-weight:600">' . htmlspecialchars($order['customer_name']) . '</p>
            </td>
            <td style="padding-bottom:12px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Phone</p>
              <p style="margin:0;font-size:15px;color:#1a1a1a">' . htmlspecialchars($order['customer_phone'] ?? '—') . '</p>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:12px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Email</p>
              <p style="margin:0;font-size:14px;color:#555">' . htmlspecialchars($order['customer_email'] ?? '—') . '</p>
            </td>
            <td style="padding-bottom:12px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Governorate</p>
              <p style="margin:0;font-size:14px;color:#555">' . htmlspecialchars($order['governorate'] ?? '—') . '</p>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-bottom:8px;vertical-align:top">
              <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Delivery Address</p>
              <p style="margin:0;font-size:14px;color:#555">' . htmlspecialchars($order['delivery_address'] ?? '—') . '</p>
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.08em">Payment</p>
              ' . $payBadge . '
            </td>
          </tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 32px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #f0ede6;border-radius:8px;overflow:hidden">
          <thead>
            <tr style="background:#faf8f4">
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:left">Product</th>
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:center">Qty</th>
              <th style="padding:10px 14px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;text-align:right">Total</th>
            </tr>
          </thead>
          <tbody>' . $itemRows . '</tbody>
        </table>
      </td></tr>

      <tr><td style="padding:0 32px 28px">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="right">
            <table cellpadding="0" cellspacing="0" style="min-width:220px">
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888">Subtotal</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#555">' . number_format((float)($order['subtotal'] ?? 0), 0) . ' EGP</td>
              </tr>
              ' . $discountRow . '
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888">Delivery</td>
                <td style="padding:4px 0;text-align:right;font-size:13px;color:#555">' . $deliveryText . '</td>
              </tr>
              <tr>
                <td style="padding:10px 0 0;font-size:16px;font-weight:700;color:#1a1a1a;border-top:2px solid #f0ede6">ORDER TOTAL</td>
                <td style="padding:10px 0 0;text-align:right;font-size:18px;font-weight:700;color:#1a1a1a;border-top:2px solid #f0ede6">' . number_format((float)($order['total'] ?? 0), 0) . ' EGP</td>
              </tr>
            </table>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 32px 32px;text-align:center">
        <a href="' . $orderUrl . '"
           style="display:inline-block;padding:14px 36px;background:#F8C417;color:#1a1a1a;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;letter-spacing:0.06em">
          VIEW ORDER IN ADMIN →
        </a>
      </td></tr>

      <tr><td style="background:#faf8f4;border-top:1px solid #f0ede6;padding:16px 32px;text-align:center">
        <p style="margin:0;font-size:11px;color:#bbb">DUHN FRAGRANCES · Egypt · This is an automated notification</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }
}
