<?php
declare(strict_types=1);
/**
 * Public API sub-route: cart
 * Loaded by api/v1/routes/public.php dispatcher.
 *
 * GET    /api/public/cart              → fetch active cart + items
 * POST   /api/public/cart/add          → add item
 * POST   /api/public/cart/update       → update qty
 * POST   /api/public/cart/remove       → remove item
 * POST   /api/public/cart/clear        → clear entire cart
 */

if ($first === 'cart') {

    require_once dirname(__DIR__, 2) . '/models/carts/repositories/PdoCartsRepository.php';
    require_once dirname(__DIR__, 2) . '/models/carts/services/CartsService.php';
    require_once dirname(__DIR__, 2) . '/models/cart_items/repositories/PdoCartItemsRepository.php';

    $cartMethod   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $subPath      = strtolower($segments[1] ?? '');
    $cartUserId   = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    $cartTenantId = (int)($tenantId ?? ($_SESSION['pub_tenant_id'] ?? 1));

    /* ─── auth guard ─────────────────────────────────── */
    if (!$cartUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    /* ═══════════════════════════════════════════════════
     *  HELPERS
     * ═══════════════════════════════════════════════════ */

    /**
     * Resolve entity_id from (payload → GET param → session → DB fallback).
     * Validates the entity belongs to the current tenant and is not suspended.
     */
    $resolveEntityId = function (array $payload = []) use ($pdoOne, $cartTenantId): int {
        $requested = (int)(
            $payload['entity_id']
            ?? $_GET['entity_id']
            ?? ($_SESSION['pub_active_entity'][$cartTenantId]['id'] ?? 0)
        );

        if ($requested > 0) {
            $row = $pdoOne(
                "SELECT id FROM entities
                  WHERE id = ? AND tenant_id = ?
                    AND status NOT IN ('suspended','rejected')
                  LIMIT 1",
                [$requested, $cartTenantId]
            );
            if ($row) {
                $_SESSION['pub_active_entity'][$cartTenantId]['id'] = (int)$row['id'];
                return (int)$row['id'];
            }
        }

        /* fallback: first approved entity for this tenant */
        $fb = $pdoOne(
            "SELECT id FROM entities
              WHERE tenant_id = ? AND status NOT IN ('suspended','rejected')
              ORDER BY id ASC LIMIT 1",
            [$cartTenantId]
        );
        if ($fb) {
            $_SESSION['pub_active_entity'][$cartTenantId]['id'] = (int)$fb['id'];
            return (int)$fb['id'];
        }
        return 0;
    };

    /**
     * Get or create an active cart for (user, entity).
     * Returns cart_id or throws on failure.
     */
    $cartRepo = new PdoCartsRepository($pdo);
    $cartItemRepo = new PdoCartItemsRepository($pdo);
    $cartService = new CartsService($cartRepo);
    $getOrCreateCart = function (int $entityId) use ($cartRepo, $cartUserId): int {
        return $cartRepo->getOrCreateActiveCart($cartUserId, $entityId, session_id() ?: null, $_SERVER['REMOTE_ADDR'] ?? null);
    };

    /**
     * Recalculate & persist cart totals after any item mutation.
     */
    $refreshTotals = function (int $cid) use ($cartRepo): void {
        $cartRepo->refreshTotalsWithCurrency($cid);
    };

    /**
     * Fetch the best active price for a product/entity combination.
     * Returns ['unit_price', 'sale_price'|null, 'currency_code'].
     *
     * Priority: entity-specific > tenant-wide (entity_id IS NULL / 0).
     * If compare_at_price > price  →  compare_at_price is the "original"
     *                                  and price is the "sale" price.
     */
    $resolvePrice = function (int $pId, int $eid, array $fallback = []) use ($pdoOne): array {
        /* Try entity-specific price first, then global */
        $pricing = $pdoOne(
            "SELECT price, compare_at_price, currency_code
               FROM product_pricing
              WHERE product_id = ?
                AND is_active = 1
                AND (start_at IS NULL OR start_at <= NOW())
                AND (end_at   IS NULL OR end_at   >= NOW())
                AND (
                      entity_id = ?
                   OR entity_id IS NULL
                   OR entity_id = 0
                    )
              ORDER BY
                CASE WHEN entity_id = ? THEN 0 ELSE 1 END,
                id ASC
              LIMIT 1",
            [$pId, $eid, $eid]
        );

        if ($pricing) {
            $p   = (float)$pricing['price'];
            $cap = (float)$pricing['compare_at_price'];
            $cur = !empty($pricing['currency_code']) ? trim($pricing['currency_code']) : 'SAR';

            /* compare_at_price > price  ⟹  price is the sale price */
            if ($cap > 0 && $cap > $p) {
                return ['unit_price' => $cap, 'sale_price' => $p, 'currency_code' => $cur];
            }
            return ['unit_price' => $p, 'sale_price' => null, 'currency_code' => $cur];
        }

        /* Fall back to values sent by the client */
        $p   = (float)($fallback['unit_price'] ?? $fallback['price'] ?? 0);
        $s   = isset($fallback['sale_price']) && (float)$fallback['sale_price'] > 0
             ? (float)$fallback['sale_price'] : null;
        $cur = !empty($fallback['currency_code']) ? trim($fallback['currency_code']) : 'SAR';
        return ['unit_price' => $p, 'sale_price' => $s, 'currency_code' => $cur];
    };

    /**
     * Parse JSON or form body from php://input.
     */
    $parseBody = function (): array {
        $raw = file_get_contents('php://input');
        $ct  = trim((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($raw && str_starts_with($ct, 'application/json')) {
            return json_decode($raw, true) ?? [];
        }
        return $_POST;
    };

    /* ═══════════════════════════════════════════════════
     *  GET /api/public/cart
     * ═══════════════════════════════════════════════════ */
    if ($cartMethod === 'GET' && $subPath === '') {

        $activeEid = $resolveEntityId();

        /* Find most-recently-active cart for this user+entity */
        $cartRow = $pdoOne(
            "SELECT id, entity_id, total_items, subtotal,
                    total_amount, currency_code, status
               FROM carts
              WHERE user_id   = ?
                AND entity_id = ?
                AND status    = 'active'
              ORDER BY last_activity_at DESC
              LIMIT 1",
            [$cartUserId, $activeEid]
        );

        if (!$cartRow) {
            ResponseFormatter::success([
                'cart'      => null,
                'items'     => [],
                'entity_id' => $activeEid,
            ]);
            exit;
        }

        /*
         * Fetch cart items WITH product image.
         * images table: owner_id = product_id, image_type_id links to image_types.
         * We prefer is_main=1, then lowest sort_order, then lowest id.
         * We pick thumb_url when available, else url.
         */
        $cartItems = $pdoList(
            "SELECT
               ci.id,
               ci.product_id,
               ci.entity_id,
               ci.product_name,
               ci.sku,
               ci.quantity,
               ci.unit_price,
               ci.sale_price,
               ci.subtotal,
               ci.total,
               ci.currency_code,
               ci.selected_attributes,
               ci.special_instructions,
               COALESCE(
                 (SELECT COALESCE(NULLIF(img.thumb_url,''), NULLIF(img.url,''))
                    FROM images img
                   WHERE img.owner_id       = ci.product_id
                     AND img.tenant_id      = ?
                     AND img.visibility     = 'public'
                   ORDER BY img.is_main DESC, img.sort_order ASC, img.id ASC
                   LIMIT 1),
                 ''
               ) AS image_url
             FROM cart_items ci
            WHERE ci.cart_id = ?
            ORDER BY ci.added_at ASC",
            [$cartTenantId, (int)$cartRow['id']]
        );

        ResponseFormatter::success([
            'cart'      => $cartRow,
            'items'     => $cartItems,
            'entity_id' => $activeEid,
        ]);
        exit;
    }

    /* ═══════════════════════════════════════════════════
     *  POST /api/public/cart/add
     * ═══════════════════════════════════════════════════ */
    if ($subPath === 'add' && $cartMethod === 'POST') {

        $body  = $parseBody();
        $pId   = (int)($body['product_id'] ?? 0);
        $pName = trim((string)($body['product_name'] ?? ''));
        $pSku  = trim((string)($body['sku'] ?? ''));
        $qty   = max(1, (int)($body['qty'] ?? 1));
        $eid   = $resolveEntityId($body);

        /* Validate required fields */
        if (!$pId || !$pName) {
            ResponseFormatter::error('product_id and product_name are required', 422);
            exit;
        }
        if ($eid <= 0) {
            ResponseFormatter::error('A valid entity_id is required', 422);
            exit;
        }

        /* Confirm product exists and is active */
        $product = $pdoOne(
            "SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1",
            [$pId]
        );
        if (!$product) {
            ResponseFormatter::error('Product not found or inactive', 404);
            exit;
        }

        /* Resolve pricing */
        $price  = $resolvePrice($pId, $eid, $body);
        $up     = $price['unit_price'];
        $sp     = $price['sale_price'];
        $cur    = $price['currency_code'] ?: 'SAR';

        /* The effective price used for subtotal */
        $effective = ($sp !== null && $sp < $up) ? $sp : $up;

        try {
            $cid = $getOrCreateCart($eid);

            /* Upsert: if same product already in cart, increment qty */
            $existing = $pdoOne(
                "SELECT id, quantity FROM cart_items
                  WHERE cart_id = ? AND product_id = ? LIMIT 1",
                [$cid, $pId]
            );

            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $sub    = round($effective * $newQty, 2);
                $cartItemRepo->updateItemFull((int)$existing['id'], $newQty, $up, $sp, $sub, $sub);
            } else {
                $sub = round($effective * $qty, 2);
                $cartItemRepo->insertPublicItem(
                    $cid, $pId, $eid, $pName, $pSku, $qty,
                    $up, $sp, $sub, $sub, $cur,
                    isset($body['selected_attributes']) ? json_encode($body['selected_attributes']) : null,
                    $body['special_instructions'] ?? null
                );
            }

            $refreshTotals($cid);

            ResponseFormatter::success(['ok' => true, 'cart_id' => $cid], 'Item added', 201);

        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to add item: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    /* ═══════════════════════════════════════════════════
     *  POST /api/public/cart/update
     * ═══════════════════════════════════════════════════ */
    if ($subPath === 'update' && $cartMethod === 'POST') {

        $body   = $parseBody();
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = max(1, (int)($body['qty'] ?? 1));

        if (!$itemId) {
            ResponseFormatter::error('item_id is required', 422);
            exit;
        }

        try {
            /* Security: ensure item belongs to this user's cart */
            $item = $pdoOne(
                "SELECT ci.id, ci.unit_price, ci.sale_price, ci.cart_id
                   FROM cart_items ci
             INNER JOIN carts c ON c.id = ci.cart_id
                  WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );

            if (!$item) {
                ResponseFormatter::notFound('Item not found');
                exit;
            }

            $up  = (float)$item['unit_price'];
            $sp  = $item['sale_price'] !== null ? (float)$item['sale_price'] : null;
            $eff = ($sp !== null && $sp < $up) ? $sp : $up;
            $sub = round($eff * $qty, 2);

            $cartItemRepo->updateItemQuantity($itemId, $qty, $sub, $sub);

            $refreshTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);

        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to update item', 500);
        }
        exit;
    }

    /* ═══════════════════════════════════════════════════
     *  POST /api/public/cart/remove
     * ═══════════════════════════════════════════════════ */
    if ($subPath === 'remove' && in_array($cartMethod, ['POST', 'DELETE'], true)) {

        $body   = $parseBody();
        $itemId = (int)($body['item_id'] ?? $_GET['item_id'] ?? 0);

        if (!$itemId) {
            ResponseFormatter::error('item_id is required', 422);
            exit;
        }

        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.cart_id
                   FROM cart_items ci
             INNER JOIN carts c ON c.id = ci.cart_id
                  WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );

            if (!$item) {
                ResponseFormatter::notFound('Item not found');
                exit;
            }

            $cartItemRepo->removeById($itemId);
            $refreshTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);

        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to remove item', 500);
        }
        exit;
    }

    /* ═══════════════════════════════════════════════════
     *  POST /api/public/cart/clear
     * ═══════════════════════════════════════════════════ */
    if ($subPath === 'clear' && $cartMethod === 'POST') {
        try {
            $body = $parseBody();
            $eid  = $resolveEntityId($body);

            $cRow = $pdoOne(
                "SELECT id FROM carts
                  WHERE user_id = ? AND entity_id = ? AND status = 'active'
                  ORDER BY last_activity_at DESC LIMIT 1",
                [$cartUserId, $eid]
            );

            if ($cRow) {
                $cartItemRepo->removeByCartId((int)$cRow['id']);
                $cartRepo->clearTotals((int)$cRow['id']);
            }

            ResponseFormatter::success(['ok' => true]);

        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to clear cart', 500);
        }
        exit;
    }

    ResponseFormatter::error('Unknown cart action', 404);
    exit;
}