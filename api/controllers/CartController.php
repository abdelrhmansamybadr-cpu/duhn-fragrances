<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';

/**
 * DUHN FRAGRANCES — Cart Controller
 * Cart is identified by user_id (if logged in) or session_token (guest)
 */
class CartController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Resolve or create cart, returns cart_id */
    private function resolveCart(): int
    {
        $userId = null;
        $payload = JwtHelper::fromRequest();
        if ($payload) {
            $userId = (int)$payload['user_id'];
        }

        $sessionToken = $_SESSION['cart_token'] ?? null;
        if (!$sessionToken) {
            $sessionToken = bin2hex(random_bytes(16));
            $_SESSION['cart_token'] = $sessionToken;
        }

        // Try to find existing cart
        if ($userId) {
            $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $userId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM carts WHERE session_token = :tok AND user_id IS NULL LIMIT 1");
            $stmt->execute([':tok' => $sessionToken]);
        }

        $cart = $stmt->fetch();
        if ($cart) return (int)$cart['id'];

        // Create new cart
        $ins = $this->db->prepare("INSERT INTO carts (user_id, session_token) VALUES (:uid, :tok)");
        $ins->execute([':uid' => $userId, ':tok' => $sessionToken]);
        return (int)$this->db->lastInsertId();
    }

    private function calculateTotals(array $items): array
    {
        $subtotal  = 0;
        $itemCount = 0;
        foreach ($items as $item) {
            $subtotal  += (float)$item['price'] * (int)$item['quantity'];
            $itemCount += (int)$item['quantity'];
        }

        $discount    = 0;
        $promoActive = false;
        $promoLabel  = '';
        $promoCode   = '';

        // ── 1. Promo code (takes priority) ──────────────────────────
        $sessionCode = $_SESSION['promo_code'] ?? null;
        if ($sessionCode) {
            try {
                // Re-validate code is still active
                $row = $this->db->prepare("SELECT * FROM promo_codes WHERE code=:c AND is_active=1 LIMIT 1");
                $row->execute([':c' => $sessionCode]);
                $pc = $row->fetch();
                if ($pc && (!$pc['expires_at'] || $pc['expires_at'] >= date('Y-m-d'))
                        && (!$pc['max_uses'] || $pc['used_count'] < $pc['max_uses'])
                        && $subtotal >= (float)$pc['min_order']) {
                    $discount = $pc['type'] === 'percent'
                        ? round($subtotal * ($pc['value'] / 100), 2)
                        : min((float)$pc['value'], $subtotal);
                    $promoActive = true;
                    $promoCode   = $sessionCode;
                    $promoLabel  = $pc['type'] === 'percent'
                        ? number_format($pc['value'],0).'% OFF — code ' . $sessionCode
                        : number_format($pc['value'],0).' EGP OFF — code ' . $sessionCode;
                } else {
                    // Invalid/expired — clear session
                    unset($_SESSION['promo_code'], $_SESSION['promo_discount'],
                          $_SESSION['promo_type'], $_SESSION['promo_value']);
                }
            } catch (Throwable $_promoErr) {
                // Promo table may not exist yet — silently ignore and clear session
                unset($_SESSION['promo_code'], $_SESSION['promo_discount'],
                      $_SESSION['promo_type'], $_SESSION['promo_value']);
            }
        }

        // ── 2. Active Cart Deal from Offers (only if no promo code) ─
        if (!$promoActive) {
            try {
                $now = date('Y-m-d H:i:s');
                $dealQ = $this->db->prepare("
                    SELECT * FROM offers
                    WHERE offer_type = 'cart_deal' AND is_active = 1
                      AND (starts_at IS NULL OR starts_at <= :now)
                      AND (ends_at   IS NULL OR ends_at   >= :now2)
                    ORDER BY id DESC LIMIT 1
                ");
                $dealQ->execute([':now' => $now, ':now2' => $now]);
                $deal = $dealQ->fetch();
            } catch (Throwable $_) { $deal = null; }

            if ($deal && $itemCount >= (int)$deal['trigger_qty']) {
                $freeItems    = (int)$deal['free_qty'];
                $pricePerItem = $itemCount > 0 ? $subtotal / $itemCount : 899;
                $discount     = round($freeItems * $pricePerItem, 2);
                $promoActive  = true;
                $promoLabel   = $deal['badge_text'] ?: 'BUY 2 GET 2 FREE';
            } elseif ($deal) {
                // Deal exists but threshold not met — still show in banner as teaser
                $promoLabel = '🎁 ' . $deal['badge_text'] . ' — Add ' . ((int)$deal['trigger_qty'] - $itemCount) . ' more item(s) to activate!';
            }
        }

        // ── 3. Wallet discount (per-product, for subscriber) ─────────
        if (!$promoActive) {
            $walletEmail = $_SESSION['wallet_email'] ?? null;
            if ($walletEmail) {
                try {
                    $wEnabled  = $this->getSetting('wallet_enabled', '0');
                    $wAmount   = (float)$this->getSetting('wallet_discount_per_product', '50');
                    if ($wEnabled === '1' && $wAmount > 0 && $itemCount > 0) {
                        $usedStmt = $this->db->prepare(
                            "SELECT product_id FROM wallet_product_uses WHERE subscriber_email = :e"
                        );
                        $usedStmt->execute([':e' => $walletEmail]);
                        $usedPids = array_column($usedStmt->fetchAll(\PDO::FETCH_ASSOC), 'product_id');
                        $eligibleCount = 0;
                        foreach ($items as $item) {
                            if (!in_array((int)$item['product_id'], array_map('intval', $usedPids))) {
                                $eligibleCount++;
                            }
                        }
                        if ($eligibleCount > 0) {
                            $walletTotal = min($eligibleCount * $wAmount, $subtotal);
                            $discount    = $walletTotal;
                            $promoActive = true;
                            $promoLabel  = '💰 ' . $eligibleCount . ' product' . ($eligibleCount > 1 ? 's' : '') . ' × ' . number_format($wAmount, 0) . ' EGP wallet discount';
                            $promoCode   = 'WALLET';
                        }
                    }
                } catch (Throwable $_walletErr) {}
            }
        }

        $deliveryFee = (float)$this->getSetting('delivery_fee', '0');
        $total       = max(0, $subtotal - $discount + $deliveryFee);

        // Show deal teaser banner if cart has items but threshold not met yet
        $dealTeaser = (!$promoActive && isset($deal) && $deal && $itemCount > 0) ? $promoLabel : '';

        return [
            'wallet_active' => ($promoCode === 'WALLET'),
            'subtotal'    => number_format($subtotal,    2, '.', ''),
            'discount'    => number_format($discount,    2, '.', ''),
            'delivery_fee'=> number_format($deliveryFee, 2, '.', ''),
            'total'       => number_format($total,       2, '.', ''),
            'item_count'  => $itemCount,
            'promo_active'=> $promoActive,
            'promo_label' => $promoActive ? $promoLabel : '',
            'deal_teaser' => $dealTeaser,
            'promo_code'  => $promoCode,
        ];
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    /** GET /api/cart */
    public function index(): void
    {
        $cartId = $this->resolveCart();
        $stmt   = $this->db->prepare("
            SELECT ci.id, ci.quantity, p.id AS product_id, p.name, p.slug,
                   p.price, p.currency, p.size_ml,
                   (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = :cid
            ORDER BY ci.created_at ASC
        ");
        $stmt->execute([':cid' => $cartId]);
        $items = $stmt->fetchAll();

        $totals = $this->calculateTotals($items);

        ResponseHelper::success([
            'cart_id' => $cartId,
            'items'   => $items,
            ...$totals,
        ]);
    }

    /** POST /api/cart/add  — body: {product_id, quantity} */
    public function add(): void
    {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $productId = (int)($body['product_id'] ?? 0);
        $qty       = max(1, (int)($body['quantity'] ?? 1));

        if (!$productId) {
            ResponseHelper::error('INVALID_PRODUCT', 'product_id is required', 400);
        }

        // Verify product exists and is in stock
        $prod = $this->db->prepare("SELECT id, stock_qty FROM products WHERE id = :id LIMIT 1");
        $prod->execute([':id' => $productId]);
        $product = $prod->fetch();
        if (!$product) ResponseHelper::notFound('Product not found');
        if ($product['stock_qty'] < $qty) {
            ResponseHelper::error('OUT_OF_STOCK', 'Not enough stock available', 400);
        }

        $cartId = $this->resolveCart();

        // Check if already in cart → update qty
        $existing = $this->db->prepare(
            "SELECT id, quantity FROM cart_items WHERE cart_id = :cid AND product_id = :pid LIMIT 1"
        );
        $existing->execute([':cid' => $cartId, ':pid' => $productId]);
        $item = $existing->fetch();

        if ($item) {
            $newQty = $item['quantity'] + $qty;
            $upd = $this->db->prepare("UPDATE cart_items SET quantity = :q WHERE id = :id");
            $upd->execute([':q' => $newQty, ':id' => $item['id']]);
        } else {
            $ins = $this->db->prepare(
                "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cid, :pid, :qty)"
            );
            $ins->execute([':cid' => $cartId, ':pid' => $productId, ':qty' => $qty]);
        }

        ResponseHelper::success(null, 'Item added to cart', 201);
    }

    /** PUT /api/cart/update  — body: {item_id, quantity} */
    public function update(): void
    {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = (int)($body['quantity'] ?? 0);

        if (!$itemId) ResponseHelper::error('INVALID', 'item_id is required', 400);

        $cartId = $this->resolveCart();

        if ($qty <= 0) {
            $del = $this->db->prepare("DELETE FROM cart_items WHERE id = :id AND cart_id = :cid");
            $del->execute([':id' => $itemId, ':cid' => $cartId]);
        } else {
            $upd = $this->db->prepare(
                "UPDATE cart_items SET quantity = :q WHERE id = :id AND cart_id = :cid"
            );
            $upd->execute([':q' => $qty, ':id' => $itemId, ':cid' => $cartId]);
        }

        ResponseHelper::success(null, 'Cart updated');
    }

    /** DELETE /api/cart/remove/{id} */
    public function remove(int $itemId): void
    {
        $cartId = $this->resolveCart();
        $del    = $this->db->prepare("DELETE FROM cart_items WHERE id = :id AND cart_id = :cid");
        $del->execute([':id' => $itemId, ':cid' => $cartId]);
        ResponseHelper::success(null, 'Item removed');
    }

    /** DELETE /api/cart/clear */
    public function clear(): void
    {
        $cartId = $this->resolveCart();
        $del    = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = :cid");
        $del->execute([':cid' => $cartId]);
        ResponseHelper::success(null, 'Cart cleared');
    }
}
