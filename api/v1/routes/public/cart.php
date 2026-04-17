<?php
declare(strict_types=1);
/**
 * Public API sub-route: cart
 * Loaded by api/v1/routes/public.php dispatcher.
 */

if ($first === 'cart') {
    $cartMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $subPath    = strtolower($segments[1] ?? '');

    $cartUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$cartUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    $cartTenantId = $tenantId ?? (int)($_SESSION['pub_tenant_id'] ?? 1);

    // Instantiate repositories
    require_once dirname(__DIR__, 2) . '/models/carts/repositories/PdoCartsRepository.php';
    require_once dirname(__DIR__, 2) . '/models/cart_items/repositories/PdoCartItemsRepository.php';
    $cartsRepo     = new PdoCartsRepository($pdo);
    $cartItemsRepo = new PdoCartItemsRepository($pdo);

    $findFallbackEntityId = function () use ($pdoOne, $cartTenantId): int {
        $row = $pdoOne(
            "SELECT id
               FROM entities
              WHERE tenant_id = ?
                AND status NOT IN ('suspended', 'rejected')
              ORDER BY id ASC
              LIMIT 1",
            [$cartTenantId]
        );
        return (int)($row['id'] ?? 0);
    };

    $resolveCartEntityId = function (array $payload = [], bool $storeInSession = false) use ($pdoOne, $cartTenantId, $findFallbackEntityId): int {
        $requested = (int)($payload['entity_id']
            ?? $_GET['entity_id']
            ?? ($_SESSION['pub_active_entity'][$cartTenantId]['id'] ?? 0));

        if ($requested > 0) {
            $entityRow = $pdoOne(
                "SELECT id
                   FROM entities
                  WHERE id = ?
                    AND tenant_id = ?
                    AND status NOT IN ('suspended', 'rejected')
                  LIMIT 1",
                [$requested, $cartTenantId]
            );
            if ($entityRow) {
                if ($storeInSession) {
                    $_SESSION['pub_active_entity'] ??= [];
                    $_SESSION['pub_active_entity'][$cartTenantId]['id'] = (int)$entityRow['id'];
                }
                return (int)$entityRow['id'];
            }
        }

        $fallback = $findFallbackEntityId();
        if ($fallback > 0 && $storeInSession) {
            $_SESSION['pub_active_entity'] ??= [];
            $_SESSION['pub_active_entity'][$cartTenantId]['id'] = $fallback;
        }
        return $fallback;
    };

    // Helper: get or create the user's active cart for a single entity
    $getOrCreateCart = function (int $entityId) use ($cartsRepo, $cartUserId): int {
        $row = $cartsRepo->findActiveForUser($cartUserId, $entityId);
        if ($row) return (int)$row['id'];
        return $cartsRepo->createActive($entityId, $cartUserId, session_id() ?: null, $_SERVER['REMOTE_ADDR'] ?? null);
    };

    // Helper: refresh cart totals after any item change
    $refreshCartTotals = function (int $cid) use ($cartsRepo): void {
        $cartsRepo->refreshTotals($cid);
    };

    // ── GET /api/public/cart ─────────────────────────────
    if ($cartMethod === 'GET' && $subPath === '') {
        $activeEntityId = $resolveCartEntityId([], true);
        $cartRow = $pdoOne(
            "SELECT id, entity_id, total_items, subtotal, total_amount, currency_code, status
               FROM carts
              WHERE user_id = ?
                AND entity_id = ?
                AND status = 'active'
              ORDER BY id DESC
              LIMIT 1",
            [$cartUserId, $activeEntityId]
        );
        if (!$cartRow) {
            ResponseFormatter::success(['cart' => null, 'items' => [], 'entity_id' => $activeEntityId]);
            exit;
        }
        $cartItems = $pdoList(
            "SELECT ci.id, ci.product_id, ci.entity_id, ci.product_name, ci.sku, ci.quantity,
                    ci.unit_price, ci.sale_price, ci.subtotal, ci.total, ci.currency_code,
                    (SELECT url FROM images WHERE owner_id = ci.product_id ORDER BY id ASC LIMIT 1) AS image_url
               FROM cart_items ci WHERE ci.cart_id = ? ORDER BY ci.added_at ASC",
            [(int)$cartRow['id']]
        );
        ResponseFormatter::success(['cart' => $cartRow, 'items' => $cartItems, 'entity_id' => $activeEntityId]);
        exit;
    }

    // ── POST /api/public/cart/add ────────────────────────
    if ($subPath === 'add' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;

        $pId   = (int)($body['product_id'] ?? 0);
        $pName = trim((string)($body['product_name'] ?? ''));
        $pSku  = trim((string)($body['sku'] ?? ''));
        $price = (float)($body['unit_price'] ?? $body['price'] ?? 0);
        $sale  = isset($body['sale_price']) && (float)$body['sale_price'] > 0 ? (float)$body['sale_price'] : null;
        $qty   = max(1, (int)($body['qty'] ?? 1));
        $eid   = $resolveCartEntityId($body, true);

        if (!$pId || !$pName) {
            ResponseFormatter::error('product_id and product_name are required', 422);
            exit;
        }
        if ($eid <= 0) {
            ResponseFormatter::error('A valid entity_id is required', 422);
            exit;
        }

        $finalPrice = ($sale !== null && $sale < $price) ? $sale : $price;

        try {
            $cid      = $getOrCreateCart($eid);
            $existing = $pdoOne(
                "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1",
                [$cid, $pId]
            );
            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $sub    = round($finalPrice * $newQty, 2);
                $cartItemsRepo->updateQuantity((int)$existing['id'], $newQty, $price, $sale, $sub, $sub);
            } else {
                $sub = round($finalPrice * $qty, 2);
                $cartItemsRepo->insert($cid, $pId, $eid, $pName, $pSku, $qty, $price, $sale, $sub, $sub);
            }
            $refreshCartTotals($cid);
            ResponseFormatter::success(['ok' => true, 'cart_id' => $cid], 'Item added', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to add item: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // ── POST /api/public/cart/update ─────────────────────
    if ($subPath === 'update' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = max(1, (int)($body['qty'] ?? 1));
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.unit_price, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $sub = round((float)$item['unit_price'] * $qty, 2);
            $cartItemsRepo->updateItemQtyTotals($itemId, $qty, $sub, $sub);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to update item', 500);
        }
        exit;
    }

    // ── POST/DELETE /api/public/cart/remove ──────────────
    if ($subPath === 'remove' && in_array($cartMethod, ['POST', 'DELETE'], true)) {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? $_GET['item_id'] ?? 0);
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $cartItemsRepo->deleteItem($itemId);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to remove item', 500);
        }
        exit;
    }

    // ── POST /api/public/cart/clear ──────────────────────
    if ($subPath === 'clear' && $cartMethod === 'POST') {
        try {
            $activeEntityId = $resolveCartEntityId([], true);
            $cRow = $pdoOne(
                "SELECT id
                   FROM carts
                  WHERE user_id = ?
                    AND entity_id = ?
                    AND status = 'active'
                  ORDER BY id DESC
                  LIMIT 1",
                [$cartUserId, $activeEntityId]
            );
            if ($cRow) {
                $cartItemsRepo->deleteAllForCart((int)$cRow['id']);
                $cartsRepo->clearTotals((int)$cRow['id']);
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
