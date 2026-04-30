<?php
declare(strict_types=1);
/**
 * api/v1/routes/public/returns.php
 * QOOQZ — Public Returns API
 *
 * Serves /api/public/returns/* requests for the currently logged-in user.
 * Loaded by api/v1/routes/public.php dispatcher when $first === 'returns'.
 *
 * Endpoints:
 *  GET  /api/public/returns                        — list the current user's return requests
 *  POST /api/public/returns                        — create a new return request
 *  GET  /api/public/returns/eligible-orders        — list user's orders eligible for return (for dropdown)
 *  GET  /api/public/returns/order-items            — look up order items by order_number (must belong to user)
 *
 * Variables provided by the parent (public.php):
 *  $pdo, $pdoList, $pdoOne, $pdoCount,
 *  $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

$retMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$retSub    = strtolower($segments[1] ?? '');

if ($retMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database unavailable', 503);
    exit;
}

$retUserId   = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$retTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

/* -------------------------------------------------------
 * GET /api/public/returns/eligible-orders
 * Returns the logged-in user's orders for the return dropdown.
 * Shows all non-cancelled orders so users can select one to return.
 * Requires login.
 * ----------------------------------------------------- */
if ($retMethod === 'GET' && in_array($retSub, ['eligible-orders', 'eligible_orders'], true)) {
    if (!$retUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }

    $orders = $pdoList(
        "SELECT id, order_number, status, grand_total, currency_code, created_at
         FROM orders
         WHERE user_id = ? AND tenant_id = ?
           AND status IN ('delivered','completed')
         ORDER BY created_at DESC
         LIMIT 100",
        [$retUserId, $retTenantId]
    );

    ResponseFormatter::success(['items' => $orders]);
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/returns/order-items?order_number=ORD-xxx
 * Looks up an order that belongs to the logged-in user
 * and returns its items so the user can review before submitting a return.
 * Requires login.
 * ----------------------------------------------------- */
if ($retMethod === 'GET' && in_array($retSub, ['order-items', 'order_items'], true)) {
    if (!$retUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }

    $orderNumber = trim((string)($_GET['order_number'] ?? ''));
    if (!$orderNumber) {
        ResponseFormatter::error('order_number is required', 422);
        exit;
    }

    // Verify the order belongs to this user.
    // All non-cancelled orders are shown in the preview — the actual return creation
    // (POST /api/public/returns) enforces the stricter delivered/completed requirement.
    // Accept a numeric order ID (e.g. "7") as well as the full order_number string.
    $isNumericId = ctype_digit($orderNumber);
    if ($isNumericId) {
        $order = $pdoOne(
            "SELECT id, order_number, status, grand_total, currency_code
             FROM orders
             WHERE (id = ? OR order_number = ?) AND user_id = ? AND tenant_id = ?
               AND status NOT IN ('cancelled')
             LIMIT 1",
            [(int)$orderNumber, $orderNumber, $retUserId, $retTenantId]
        );
    } else {
        $order = $pdoOne(
            "SELECT id, order_number, status, grand_total, currency_code
             FROM orders
             WHERE order_number = ? AND user_id = ? AND tenant_id = ?
               AND status NOT IN ('cancelled')
             LIMIT 1",
            [$orderNumber, $retUserId, $retTenantId]
        );
    }

    if (!$order) {
        ResponseFormatter::error('Order not found or does not belong to your account', 404);
        exit;
    }

    // Check if a return already exists for this order
    $existingReturn = $pdoOne(
        "SELECT id, status FROM returns WHERE order_id = ? AND user_id = ? AND tenant_id = ? LIMIT 1",
        [(int)$order['id'], $retUserId, $retTenantId]
    );

    // Fetch order items
    $items = $pdoList(
        "SELECT oi.id, oi.product_id, oi.product_name, oi.sku,
                oi.quantity, oi.unit_price, oi.subtotal,
                (SELECT i.url FROM images i WHERE i.owner_id = oi.product_id ORDER BY i.id ASC LIMIT 1) AS image_url
         FROM order_items oi
         WHERE oi.order_id = ? AND oi.tenant_id = ?
         ORDER BY oi.id ASC",
        [(int)$order['id'], $retTenantId]
    );

    ResponseFormatter::success([
        'order'           => $order,
        'items'           => $items,
        'existing_return' => $existingReturn,
    ]);
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/returns
 * Returns the logged-in user's return requests (newest first).
 * Requires login.
 * ----------------------------------------------------- */
if ($retMethod === 'GET' && $retSub === '') {
    if (!$retUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }

    $filterStatus = isset($_GET['status']) && in_array($_GET['status'], [
        'pending','approved','rejected','processing','completed','cancelled',
    ]) ? $_GET['status'] : '';

    $where  = 'WHERE r.tenant_id = ? AND r.user_id = ?';
    $params = [$retTenantId, $retUserId];
    if ($filterStatus) {
        $where  .= ' AND r.status = ?';
        $params[] = $filterStatus;
    }

    $total = $pdoCount(
        "SELECT COUNT(*) FROM returns r $where",
        $params
    );

    $rows = $pdoList(
        "SELECT r.id, r.return_number, r.status, r.reason, r.requested_at, r.created_at,
                o.order_number
         FROM returns r
         LEFT JOIN orders o ON o.id = r.order_id
         $where
         ORDER BY r.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
    );

    ResponseFormatter::success([
        'items' => $rows,
        'meta'  => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ],
    ]);
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/returns
 * Create a new return request for the logged-in user.
 * Requires login.
 * Body: { order_number, reason, items? }
 *   - order_number : the order_number string (preferred) OR order_id (numeric)
 *   - reason       : required string
 *   - items        : optional array of { order_item_id, quantity? } to return
 * ----------------------------------------------------- */
if ($retMethod === 'POST' && $retSub === '') {
    if (!$retUserId) {
        ResponseFormatter::error('Login required to submit a return request', 401);
        exit;
    }

    $raw  = (string)(file_get_contents('php://input') ?: '');
    $body = (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'))
          ? (json_decode($raw, true) ?? [])
          : $_POST;

    $reason      = trim((string)($body['reason'] ?? ''));
    $orderNumber = trim((string)($body['order_number'] ?? ''));
    $orderId     = isset($body['order_id']) && is_numeric($body['order_id']) ? (int)$body['order_id'] : 0;
    $returnItems = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];

    if (!$reason) {
        ResponseFormatter::error('reason is required', 422);
        exit;
    }
    if (!$orderNumber && !$orderId) {
        ResponseFormatter::error('order_number or order_id is required', 422);
        exit;
    }

    // Resolve order — must belong to the current user and be eligible for return.
    // When order_number looks like a plain integer, also match by id.
    $baseWhere  = 'user_id = ? AND tenant_id = ? AND status IN (\'delivered\',\'completed\')';
    $baseParams = [$retUserId, $retTenantId];
    if ($orderNumber && ctype_digit($orderNumber)) {
        $order = $pdoOne(
            "SELECT id, order_number FROM orders
             WHERE (id = ? OR order_number = ?) AND $baseWhere LIMIT 1",
            array_merge([(int)$orderNumber, $orderNumber], $baseParams)
        );
    } elseif ($orderNumber) {
        $order = $pdoOne(
            "SELECT id, order_number FROM orders
             WHERE order_number = ? AND $baseWhere LIMIT 1",
            array_merge([$orderNumber], $baseParams)
        );
    } else {
        $order = $pdoOne(
            "SELECT id, order_number FROM orders
             WHERE id = ? AND $baseWhere LIMIT 1",
            array_merge([$orderId], $baseParams)
        );
    }

    if (!$order) {
        ResponseFormatter::error('Order not found or not eligible for return', 404);
        exit;
    }
    $resolvedOrderId = (int)$order['id'];

    // Prevent duplicate return for the same order
    $existing = $pdoOne(
        "SELECT id FROM returns WHERE order_id = ? AND user_id = ? AND tenant_id = ? LIMIT 1",
        [$resolvedOrderId, $retUserId, $retTenantId]
    );
    if ($existing) {
        ResponseFormatter::error('A return request for this order already exists', 409);
        exit;
    }

    // Generate return number
    $returnNumber = 'RET-' . $retTenantId . '-' . time() . '-' . random_int(100, 999);

    try {
        $pdo->beginTransaction();

        $returnsRepo = new PdoReturnsRepository($pdo);
        $returnsService = new ReturnsService($returnsRepo);
        $returnId = $returnsRepo->createPublicReturn($retTenantId, $retUserId, $resolvedOrderId, $returnNumber, $reason);

        // Insert return items if provided
        if ($returnItems && $returnId) {
            $returnItemsRepo = new PdoReturnItemsRepository($pdo);
            foreach ($returnItems as $ri) {
                $oiId = isset($ri['order_item_id']) && is_numeric($ri['order_item_id']) ? (int)$ri['order_item_id'] : 0;
                $qty  = isset($ri['quantity'])      && is_numeric($ri['quantity'])      ? max(1, (int)$ri['quantity']) : 1;
                if (!$oiId) continue;
                try {
                    $returnItemsRepo->createReturnItem($returnId, $oiId, $qty, $retTenantId);
                } catch (ApplicationException|\RuntimeException $riEx) {
                    error_log('[returns] insert return item failed: ' . $riEx->getMessage());
                }
            }
        }

        $pdo->commit();

        ResponseFormatter::success([
            'id'            => $returnId,
            'return_number' => $returnNumber,
            'success'       => true,
        ], 'Return request submitted successfully', 201);
    } catch (ApplicationException|\RuntimeException $ex) {
        try { $pdo->rollBack(); } catch (ApplicationException|\RuntimeException $rb) { error_log('[returns] rollback failed: ' . $rb->getMessage()); }
        ResponseFormatter::error('Failed to create return request', 500);
    }
    exit;
}

ResponseFormatter::error('Method not allowed', 405);