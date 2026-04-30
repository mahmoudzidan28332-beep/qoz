<?php
declare(strict_types=1);
/**
 * frontend/public/checkout.php — QOOQZ
 *
 * Delivery modes (from delivery API):
 *   merchant  → matched_zone from delivery API (entity_driver zones)
 *   courier   → external company / independent providers
 *   pickup    → entity_pickup_points
 *
 * Address auto-fills from map reverse-geocoding.
 * All delivery data fetched from /api/public/delivery (the fixed API).
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

try {
    $tables = ['order_status_history', 'payments', 'product_stock_movements', 'delivery_providers'];
    foreach($tables as $t) {
        $stmt = $pdo->query("SELECT id FROM `$t` WHERE id = 0");
        if ($stmt && $stmt->fetch()) {
            $maxId = $pdo->query("SELECT MAX(id) FROM `$t`")->fetchColumn();
            $newId = $maxId > 0 ? $maxId + 1 : 1;
            $pdo->exec("UPDATE `$t` SET id = $newId WHERE id = 0");
        }
        $pdo->exec("ALTER TABLE `$t` MODIFY `id` BIGINT AUTO_INCREMENT");
    }
} catch (\RuntimeException $dbEx) {
    // Ignore silentyly
}

$ctx            = $GLOBALS['PUB_CONTEXT'];
$lang           = $ctx['lang'];
$dir            = $ctx['dir'] ?? 'ltr';
$tenantId       = (int)($ctx['tenant_id'] ?? 1);
$user           = $ctx['user'] ?? null;
$userId         = (int)($user['id'] ?? 0);
$activeEntity   = is_array($ctx['active_entity'] ?? null) ? $ctx['active_entity'] : [];
$entityId       = (int)($activeEntity['id'] ?? 0);
$entityName     = trim((string)($activeEntity['name'] ?? ''));

if (!$userId) {
    header('Location: /frontend/login.php?redirect=' . urlencode('/frontend/public/checkout.php'));
    exit;
}
if ($entityId <= 0) {
    die('Error: No active fulfillment branch found. Please select a branch first.');
}

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('checkout.title') . ' — QOOQZ';

/* ─── DB + Cart ─────────────────────────────────────────────── */
$pdo            = pub_get_pdo();
$cartItems      = [];
$cartTotal      = 0.0;
$cartId         = 0;
$displayCurrency = 'SAR';

if ($pdo) {
    try {
        $cs = $pdo->prepare(
            "SELECT id, entity_id FROM carts
              WHERE user_id = ? AND entity_id = ? AND status = 'active'
              ORDER BY last_activity_at DESC LIMIT 1"
        );
        $cs->execute([$userId, $entityId]);
        $cartRow = $cs->fetch(PDO::FETCH_ASSOC);

        if ($cartRow) {
            $cartId = (int)$cartRow['id'];

            $is = $pdo->prepare(
                "SELECT
                    ci.id, ci.product_id, ci.product_name, ci.sku,
                    ci.quantity, ci.unit_price, ci.sale_price, ci.subtotal,
                    ci.entity_id, ci.currency_code, ci.selected_attributes,
                    COALESCE(
                        (SELECT COALESCE(NULLIF(img.thumb_url,''), NULLIF(img.url,''))
                           FROM images img
                          WHERE img.owner_id   = ci.product_id
                            AND img.tenant_id  = ?
                            AND img.visibility = 'public'
                          ORDER BY img.is_main DESC, img.sort_order ASC, img.id ASC
                          LIMIT 1),
                        ''
                    ) AS image_url
                   FROM cart_items ci
                  WHERE ci.cart_id = ?
                  ORDER BY ci.added_at ASC"
            );
            $is->execute([$tenantId, $cartId]);
            $cartItems = $is->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cartItems as $ci) {
                $up = (float)$ci['unit_price'];
                $sp = isset($ci['sale_price']) && is_numeric($ci['sale_price']) ? (float)$ci['sale_price'] : null;
                $effective = ($sp !== null && $sp > 0 && $sp < $up) ? $sp : $up;
                $cartTotal += $effective * (int)$ci['quantity'];
                if (!empty($ci['currency_code'])) $displayCurrency = $ci['currency_code'];
            }
            $cartTotal = round($cartTotal, 2);
        }
    } catch (\RuntimeException) {}
}

/* ─── Payment Methods ───────────────────────────────────────── */
$entityPMs = [];
if ($pdo) {
    try {
        $ps = $pdo->prepare(
            "SELECT pm.method_key AS code, pm.method_name AS name, pm.icon_url AS icon
               FROM entity_payment_methods epm
               JOIN payment_methods pm ON pm.id = epm.payment_method_id
              WHERE epm.entity_id = ? AND epm.is_active = 1
              ORDER BY pm.sort_order ASC"
        );
        $ps->execute([$entityId]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (\RuntimeException) {}

    if (empty($entityPMs)) {
        try {
            $ps = $pdo->prepare(
                "SELECT method_key AS code, method_name AS name, icon_url AS icon
                   FROM payment_methods
                  WHERE tenant_id = ? AND is_active = 1
                  ORDER BY sort_order ASC LIMIT 10"
            );
            $ps->execute([$tenantId]);
            $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
        } catch (\RuntimeException) {}
    }
}

/* ─── POST Handler ──────────────────────────────────────────── */
$checkoutError   = '';
$checkoutSuccess = false;
$orderNumber     = '';
$orderId         = 0;
$driverNavLink   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {

    $pmCode        = trim($_POST['payment_method_code'] ?? 'cash');
    $custName      = trim($_POST['customer_name']       ?? '');
    $custPhone     = trim($_POST['customer_phone']      ?? '');
    $address       = trim($_POST['delivery_address']    ?? '');
    $notes         = trim($_POST['order_notes']         ?? '');
    $lat           = ($_POST['delivery_lat'] ?? '') !== '' ? (float)$_POST['delivery_lat'] : null;
    $lng           = ($_POST['delivery_lng'] ?? '') !== '' ? (float)$_POST['delivery_lng'] : null;
    $deliveryMode  = in_array($_POST['delivery_mode'] ?? '', ['merchant','courier','pickup'], true)
                   ? $_POST['delivery_mode'] : 'merchant';
    $deliveryOptId = (int)($_POST['delivery_option_id'] ?? 0);
    $shippingCost  = 0.0;

    /* Server-side fee verification */
    if ($deliveryMode === 'merchant' && $deliveryOptId) {
        $fq = $pdo->prepare(
            "SELECT delivery_fee FROM delivery_zones
              WHERE id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1"
        );
        $fq->execute([$deliveryOptId, $tenantId]);
        $fRow = $fq->fetch(PDO::FETCH_ASSOC);
        if ($fRow) $shippingCost = (float)$fRow['delivery_fee'];
    } elseif ($deliveryMode === 'courier' && $deliveryOptId) {
        /* Look up courier fee from posted amount, verified against providers table */
        $postedFee = (float)($_POST['courier_fee'] ?? 25.00);
        $pq = $pdo->prepare(
            "SELECT id FROM delivery_providers
              WHERE id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1"
        );
        $pq->execute([$deliveryOptId, $tenantId]);
        if ($pq->fetch()) $shippingCost = max(0, $postedFee);
    }
    /* pickup: shippingCost stays 0 */

    if ($lat !== null && $lng !== null) {
        $driverNavLink = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
    }

    /* Fallback: JS cart posted as JSON */
    $jsItems = [];
    if (empty($cartItems)) {
        $jsItems = json_decode($_POST['cart_items_json'] ?? '[]', true);
        if (!is_array($jsItems)) $jsItems = [];
        foreach ($jsItems as &$ji) {
            if (empty($ji['entity_id'])) $ji['entity_id'] = $entityId;
            $up = (float)($ji['price'] ?? 0);
            $sp = isset($ji['sale_price']) && is_numeric($ji['sale_price']) ? (float)$ji['sale_price'] : null;
            $p = ($sp !== null && $sp > 0 && $sp < $up) ? $sp : $up;
            $q = max(1, (int)($ji['qty'] ?? 1));
            $cartTotal += $p * $q;
            if (!empty($ji['currency']) && empty($displayCurrency)) $displayCurrency = $ji['currency'];
        }
        unset($ji);
        $cartTotal = round($cartTotal, 2);
    }

    $allItems = !empty($cartItems) ? $cartItems : $jsItems;

    /* Entity consistency check */
    $itemEntityIds = [];
    foreach ($allItems as $ci) {
        $eid = (int)($ci['entity_id'] ?? $entityId);
        if ($eid > 0) $itemEntityIds[$eid] = true;
    }

    /* Validation */
    if (!$custName || !$custPhone) {
        $checkoutError = t('checkout.error_fields_required');
    } elseif (empty($allItems)) {
        $checkoutError = t('cart.empty');
    } elseif ($deliveryMode !== 'pickup' && !$deliveryOptId && $deliveryMode !== 'merchant') {
        $checkoutError = t('checkout.error_delivery_method_required');
    } elseif (count($itemEntityIds) > 1) {
        $checkoutError = t('cart.conflict_error');
    } elseif ($deliveryMode !== 'pickup' && $lat === null) {
        $checkoutError = t('checkout.error_location_required');
    } else {

        $grandTotal  = round($cartTotal + $shippingCost, 2);
        $orderNumber = 'ORD-' . $tenantId . '-' . time() . '-' . rand(100, 999);

        /* Build full notes */
        $fullNotes = $notes;
        if ($address) $fullNotes .= "\n📍 " . $address;
        if ($deliveryMode !== 'pickup' && $lat !== null) {
            $fullNotes .= "\n🗺️ {$lat},{$lng}\n🔗 {$driverNavLink}";
        }
        if ($deliveryMode === 'pickup') {
            $fullNotes .= "\n🏬 " . t('checkout.delivery_pickup') . " #" . $deliveryOptId;
        }

        try {
            $pdo->beginTransaction();

            $dZoneId  = $deliveryMode === 'merchant' ? $deliveryOptId  : null;
            $dCoId    = $deliveryMode === 'courier'  ? $deliveryOptId  : null;
            $dPickId  = $deliveryMode === 'pickup'   ? $deliveryOptId  : null;
            $dEnId    = $deliveryMode === 'merchant' ? $entityId       : null;
            $dMethod  = $deliveryMode;

            $oSt = $pdo->prepare(
                "INSERT INTO orders
                   (tenant_id, entity_id, order_number, user_id, cart_id,
                    status, payment_status, fulfillment_status,
                    subtotal, tax_amount, shipping_cost, discount_amount,
                    total_amount, grand_total, currency_code,
                    customer_notes, ip_address, user_agent,
                    payment_method, delivery_method,
                    delivery_zone_id, delivery_company_id,
                    delivery_entity_id, pickup_point_id,
                    created_at, updated_at)
                 VALUES
                   (?,?,?,?,?,
                    'pending','pending','unfulfilled',
                    ?,0,?,0,?,?,?,
                    ?,?,?,
                    ?,?,
                    ?,?,?,?,
                    NOW(),NOW())"
            );
            $oSt->execute([
                $tenantId, $entityId, $orderNumber, $userId,
                $cartId ?: null,
                $cartTotal, $shippingCost,
                $cartTotal + $shippingCost, $grandTotal,
                $displayCurrency,
                $fullNotes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $pmCode,
                $dMethod,
                $dZoneId, $dCoId,
                $dEnId, $dPickId,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            /* Order status history */
            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, status, notes, created_at)
                 VALUES (?, 'pending', 'Order placed', NOW())"
            )->execute([$orderId]);

            /* Order items */
            $iSt = $pdo->prepare(
                "INSERT INTO order_items
                   (tenant_id, order_id, entity_id,
                    product_id, product_name, sku,
                    quantity, unit_price, sale_price,
                    subtotal, total, currency_code,
                    selected_attributes, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            foreach ($allItems as $ci) {
                $isDb  = !empty($cartItems);
                $pId   = (int)($isDb ? $ci['product_id'] : ($ci['id'] ?? 0));
                $pName = (string)($isDb ? $ci['product_name'] : ($ci['name'] ?? ''));
                $pSku  = (string)($ci['sku'] ?? '');
                $qty   = max(1, (int)($ci['quantity'] ?? $ci['qty'] ?? 1));
                $up    = (float)($ci['unit_price'] ?? $ci['price'] ?? 0);
                $sp    = isset($ci['sale_price']) && (float)$ci['sale_price'] > 0 && (float)$ci['sale_price'] < $up
                       ? (float)$ci['sale_price'] : null;
                $eff   = ($sp !== null) ? $sp : $up;
                $attrs = $ci['selected_attributes'] ?? null;
                if (!$pId || !$pName) continue;

                $iSt->execute([
                    $tenantId, $orderId, $entityId,
                    $pId, $pName, $pSku,
                    $qty, $up, $sp,
                    round($eff * $qty, 2), round($eff * $qty, 2),
                    $displayCurrency,
                    is_string($attrs) ? $attrs : ($attrs ? json_encode($attrs) : null),
                ]);
            }

            /* Stock movement */
            foreach ($allItems as $ci) {
                $isDb  = !empty($cartItems);
                $pId   = (int)($isDb ? $ci['product_id'] : ($ci['id'] ?? 0));
                $qty   = max(1, (int)($ci['quantity'] ?? $ci['qty'] ?? 1));
                if (!$pId) continue;
                try {
                    $pdo->prepare(
                        "INSERT INTO product_stock_movements
                           (product_id, variant_id, change_quantity, type, reference_id, notes, created_at)
                         VALUES (?, NULL, ?, 'sale', ?, 'Order placed', NOW())"
                    )->execute([$pId, -$qty, $orderId]);
                } catch (\RuntimeException) {}
            }

            /* Convert cart */
            if ($cartId) {
                $pdo->prepare(
                    "UPDATE carts
                        SET status = 'converted',
                            converted_to_order_id = ?,
                            updated_at = NOW()
                      WHERE id = ?"
                )->execute([$orderId, $cartId]);
            }

            $pdo->commit();
            $checkoutSuccess = true;

            /* Payment record */
            try {
                $pmNum = 'PAY-' . $tenantId . '-' . $orderId . '-' . time();
                $pdo->prepare(
                    "INSERT INTO payments
                       (entity_id, payment_number, order_id, user_id,
                        payment_method, amount, currency_code,
                        status, payment_type, ip_address,
                        created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,'pending','full',?,NOW(),NOW())"
                )->execute([
                    $entityId, $pmNum, $orderId, $userId,
                    $pmCode ?: 'cod', $grandTotal, $displayCurrency,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (\RuntimeException) {}

        } catch (\RuntimeException $ex) {
            try { $pdo->rollBack(); } catch (\RuntimeException) {}
            @file_put_contents(__DIR__ . '/checkout_error_debug.log', date('Y-m-d H:i:s') . "\n" . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n\n");
            $checkoutError = t('common.error') . ': ' . $ex->getMessage();
        }
    }
}

$hasDbCart    = !empty($cartItems);
$cartItemsJs  = json_encode(array_values($cartItems), JSON_UNESCAPED_UNICODE);

include dirname(__DIR__) . '/partials/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>

<style>
/* ── Layout ─────────────────────────────────── */
.qz-co-page { padding-top:28px; padding-bottom:56px; }
.qz-co-layout { display:grid; gap:24px; }
@media(min-width:1024px){ .qz-co-layout{ grid-template-columns:1fr 360px; align-items:start; } }

/* ── Card ───────────────────────────────────── */
.qz-co-card {
    background: var(--pub-bg);
    border: 1px solid var(--pub-glass-border);
    border-radius: var(--pub-radius);
    box-shadow: var(--pub-shadow);
    overflow: hidden;
    margin-bottom: 24px;
    transition: all var(--pub-transition);
}
.qz-co-card:hover {
    box-shadow: var(--pub-shadow-hover);
}
.qz-co-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    padding: 16px 20px;
    background: color-mix(in srgb, var(--pub-text) 3%, var(--pub-surface));
    border-bottom: 1px solid var(--pub-glass-border);
    color: var(--pub-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ── Form ───────────────────────────────────── */
.qz-form-grid {
    display: grid;
    gap: 14px;
    padding: 16px;
}
@media(min-width:600px){ .qz-form-grid{ grid-template-columns:1fr 1fr; } }
.qz-form-field { display: grid; }
.qz-form-label {
    font-size: .78rem;
    font-weight: 600;
    color: var(--pub-muted);
    margin-bottom: 5px;
    letter-spacing: .02em;
    text-transform: uppercase;
}
.qz-form-input {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    background: var(--pub-bg);
    color: var(--pub-text);
    font-size: .9rem;
    transition: border-color .18s, box-shadow .18s;
    font-family: inherit;
    box-sizing: border-box;
}
.qz-form-input:focus {
    outline: none;
    border-color: var(--pub-primary);
    box-shadow: 0 0 0 3px rgba(3,135,78,.1);
}

/* ── Map ────────────────────────────────────── */
#deliveryMap {
    width: 100%;
    height: 290px;
    border-radius: var(--pub-radius-sm);
    border: 1.5px solid var(--pub-border);
    margin-top: 10px;
    z-index: 0;
}

/* ── Zone status bar ────────────────────────── */
#zoneBar {
    display: none;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: var(--pub-radius-sm);
    font-size: .8rem;
    font-weight: 600;
    margin-top: 8px;
}
#zoneBar.in  { background:color-mix(in srgb, var(--pub-success) 12%, transparent); color:var(--pub-success); border:1px solid color-mix(in srgb, var(--pub-success) 20%, transparent); }
#zoneBar.out { background:color-mix(in srgb, var(--pub-danger) 8%, transparent); color:var(--pub-danger); border:1px solid color-mix(in srgb, var(--pub-danger) 15%, transparent); }

/* ── Delivery tabs ──────────────────────────── */
.qz-tab-bar {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius);
    overflow: hidden;
    margin-bottom: 16px;
}
.qz-tab {
    padding: 10px 4px;
    text-align: center;
    font-size: .76rem;
    font-weight: 700;
    cursor: pointer;
    background: var(--pub-bg);
    color: var(--pub-muted);
    border: none;
    border-right: 1px solid var(--pub-border);
    transition: background .15s, color .15s;
    line-height: 1.4;
    letter-spacing: .01em;
}
.qz-tab:last-child { border-right: none; }
.qz-tab.active { background: var(--pub-primary); color: #fff; border-color: var(--pub-primary); }
.qz-tab-icon { display: block; font-size: 1.3rem; margin-bottom: 4px; }

/* ── Zone / option card ─────────────────────── */
.qz-opt-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 13px;
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    cursor: pointer;
    transition: border-color .15s, background .15s, box-shadow .15s;
    margin-bottom: 8px;
    background: var(--pub-bg);
}
.qz-opt-card:hover { border-color: var(--pub-primary); }
.qz-opt-card.active {
    border-color: var(--pub-primary);
    background: rgba(3,135,78,.06);
    box-shadow: 0 0 0 3px rgba(3,135,78,.08);
}
.qz-opt-icon { font-size: 1.4rem; flex-shrink: 0; }
.qz-opt-body { flex: 1; min-width: 0; }
.qz-opt-name { font-weight: 700; font-size: .88rem; color: var(--pub-text); }
.qz-opt-meta { font-size: .74rem; color: var(--pub-muted); margin-top: 2px; }
.qz-opt-fee  { font-weight: 800; font-size: .92rem; color: var(--pub-primary); white-space: nowrap; }
.qz-opt-free { font-weight: 800; font-size: .88rem; color: #059669; white-space: nowrap; }

/* ── Courier select ─────────────────────────── */
.qz-co-sel-wrap { padding: 2px 0 8px; }
.qz-co-sel-wrap label { font-size: .78rem; font-weight: 600; color: var(--pub-muted); display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .02em; }
.qz-co-sel {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    background: var(--pub-bg);
    color: var(--pub-text);
    font-size: .9rem;
    font-family: inherit;
    box-sizing: border-box;
    transition: border-color .15s;
}
.qz-co-sel:focus { outline: none; border-color: var(--pub-primary); }
.qz-co-detail {
    display: none;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: wrap;
    font-size: .78rem;
    color: var(--pub-muted);
    align-items: center;
}
.qz-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(3,135,78,.1);
    color: #059669;
    font-weight: 700;
    border-radius: 99px;
    padding: 2px 10px;
    font-size: .77rem;
}

/* ── Driver nav box ─────────────────────────── */
#navBox {
    display: none;
    margin-top: 8px;
    padding: 9px 12px;
    background: rgba(26,115,232,.06);
    border: 1px solid rgba(26,115,232,.2);
    border-radius: var(--pub-radius-sm);
    font-size: .76rem;
}
#navBox a { color: #1558b0; word-break: break-all; }

/* ── Spinner ────────────────────────────────── */
.qz-spin-wrap { display:flex; align-items:center; gap:10px; padding:10px 0; color:var(--pub-muted); font-size:.83rem; }
.qz-spin { width:16px; height:16px; border:2px solid var(--pub-border); border-top-color:var(--pub-primary); border-radius:50%; animation:qzSpin .7s linear infinite; flex-shrink:0; }
@keyframes qzSpin{ to{ transform:rotate(360deg); } }

/* ── Payment methods ────────────────────────── */
.qz-pm-grid { display:grid; gap:10px; padding:16px; }
@media(min-width:500px){ .qz-pm-grid{ grid-template-columns:1fr 1fr; } }
.qz-pm-opt {
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    padding: 11px 14px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    display: block;
}
.qz-pm-opt:has(input:checked) { border-color: var(--pub-primary); background: rgba(3,135,78,.05); }
.qz-pm-opt input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
.qz-pm-label { display:flex; align-items:center; gap:10px; }
.qz-pm-icon { font-size:1.35rem; }
.qz-pm-name { font-size:.87rem; font-weight:600; color:var(--pub-text); }

/* ── Summary sidebar ────────────────────────── */
.qz-summary-wrap { position: sticky; top: calc(var(--pub-header-h, 64px) + 24px); }
.qz-summary-card {
    background: var(--pub-bg);
    border: 1px solid var(--pub-glass-border);
    border-radius: var(--pub-radius);
    padding: 24px;
    box-shadow: 
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.qz-summary-title { font-size:.95rem; font-weight:700; margin:0 0 14px; color:var(--pub-text); }
.qz-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid var(--pub-border);
    font-size: .88rem;
}
.qz-summary-row:last-of-type { border-bottom: none; }
.qz-summary-total { font-size: .98rem; font-weight: 800; }

/* ── Entity pill ────────────────────────────── */
.qz-entity-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 13px;
    border-radius: 999px;
    background: rgba(3,135,78,.08);
    color: var(--pub-primary);
    font-size: .8rem;
    font-weight: 700;
    margin-bottom: 14px;
}

/* ── Notice ─────────────────────────────────── */
.qz-notice {
    padding: 11px 15px;
    border-radius: var(--pub-radius-sm);
    margin-bottom: 18px;
    font-size: .87rem;
    border: 1px solid transparent;
}
.qz-notice--error   { background:#fef2f2; color:#b91c1c; border-color:#fca5a5; }
.qz-notice--success { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }

/* ── Chosen delivery pill ───────────────────── */
#chosenBox {
    display: none;
    margin-top: 10px;
    padding: 8px 12px;
    background: rgba(3,135,78,.07);
    border-radius: var(--pub-radius-sm);
    font-size: .8rem;
    color: #059669;
    font-weight: 600;
}

/* ── Submit btn hint ────────────────────────── */
#submitHint { font-size:.73rem; text-align:center; color:var(--pub-danger, #dc2626); margin-top:5px; }

/* ── GPS button ─────────────────────────────── */
#btnGPS {
    font-size: .73rem;
    padding: 4px 10px;
    height: auto;
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    background: var(--pub-bg);
    cursor: pointer;
    color: var(--pub-muted);
    transition: border-color .15s, color .15s;
    font-family: inherit;
}
#btnGPS:hover { border-color: var(--pub-primary); color: var(--pub-primary); }

/* ── Items list in summary ──────────────────── */
.qz-item-thumb {
    width: 36px; height: 36px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
}
</style>

<div class="pub-container qz-co-page">

    <!-- Breadcrumb -->
    <nav class="pub-breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span class="pub-breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
        <a href="/frontend/public/cart.php"><?= e(t('cart.title')) ?></a>
        <span class="pub-breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
        <span><?= e(t('checkout.title')) ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- ── SUCCESS ──────────────────────────────── -->
    <div style="text-align:center;padding:80px 20px; background: var(--pub-surface); border-radius: 30px; box-shadow: var(--pub-shadow); margin: 40px 0;">
        <div style="font-size:4.5rem;margin-bottom:24px; color: var(--pub-success);"><i class="bi bi-check-circle-fill"></i></div>
        <h1 style="font-size:1.8rem;margin:0 0 12px; font-weight: 800;"><?= e(t('checkout.success_title')) ?></h1>
        <p style="color:var(--pub-muted);margin:0 0 8px; font-size: 1.05rem;"><?= e(t('checkout.success_msg')) ?></p>
        <?php if ($orderNumber): ?>
        <p style="font-size:1rem;color:var(--pub-muted);margin-bottom:32px;">
            <?= e(t('checkout.order_number')) ?>
            <strong style="color:var(--pub-primary); background: rgba(0,0,0,0.05); padding: 4px 12px; border-radius: 8px;">#<?= e($orderNumber) ?></strong>
        </p>
        <?php endif; ?>
        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary" style="padding: 14px 28px;"><?= e(t('common.home')) ?></a>
            <a href="/frontend/public/products.php" class="pub-btn" style="padding: 14px 28px; background: rgba(0,0,0,0.05);"><?= e(t('common.shop_more')) ?></a>
            <?php if ($driverNavLink): ?>
            <a href="<?= e($driverNavLink) ?>" target="_blank" class="pub-btn"
               style="display:inline-flex;align-items:center;gap:10px;padding:14px 28px;background:var(--pub-primary);color:#fff;border-radius:var(--pub-radius);font-weight:700;">
                <i class="bi bi-map"></i> <?= e(t('checkout.driver_nav_link')) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <script>
    try {
        ['pub_cart_t<?= $tenantId ?>_e<?= $entityId ?>','pub_cart'].forEach(function(k){
            localStorage.removeItem(k);
        });
        if (typeof window.pubClearScopedCart === 'function') {
            window.pubClearScopedCart(<?= $entityId ?>);
        }
    } catch(e){}
    </script>

    <?php else: ?>


    <div class="pub-section-head" style="margin-bottom:24px;">
        <h1 style="font-size:1.6rem; margin:0;"><i class="bi bi-bag-check"></i> <?= e(t('checkout.title')) ?></h1>
    </div>

    <?php if ($checkoutError): ?>
    <div class="qz-notice qz-notice--error">⚠️ <?= e($checkoutError) ?></div>
    <?php endif; ?>

    <form method="post" id="checkoutForm" class="qz-co-layout">

        <!-- Hidden state -->
        <input type="hidden" name="cart_items_json"    id="cartItemsJson"  value="[]">
        <input type="hidden" name="delivery_lat"       id="hLat"           value="">
        <input type="hidden" name="delivery_lng"       id="hLng"           value="">
        <input type="hidden" name="delivery_mode"      id="hMode"          value="merchant">
        <input type="hidden" name="delivery_option_id" id="hOptId"         value="0">
        <input type="hidden" name="courier_fee"        id="hCourierFee"    value="0">

        <!-- ══════════════════ LEFT COLUMN ══════════════════ -->
        <div>

            <!-- Customer Info -->
            <div class="qz-co-card">
                <h2 class="qz-co-card-title"><i class="bi bi-person"></i> <?= e(t('checkout.customer_info')) ?></h2>
                <div class="qz-form-grid">

                    <div class="qz-form-field">
                        <label class="qz-form-label"><?= e(t('checkout.name')) ?> *</label>
                        <input type="text" name="customer_name" class="qz-form-input" required
                               value="<?= e($user['name'] ?? $user['username'] ?? '') ?>">
                    </div>

                    <div class="qz-form-field">
                        <label class="qz-form-label"><i class="bi bi-telephone"></i> <?= e(t('checkout.phone')) ?> *</label>
                        <input type="tel" name="customer_phone" class="qz-form-input" required
                               placeholder="+971 5x xxx xxxx" dir="ltr">
                    </div>

                    <!-- Address + Map -->
                    <div class="qz-form-field" style="grid-column:1/-1;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                            <label class="qz-form-label" style="margin:0;">
                                <i class="bi bi-geo-alt"></i> <?= e(t('checkout.address')) ?>
                            </label>
                            <button type="button" id="btnGPS" style="display: flex; align-items: center; gap: 4px;">
                                <i class="bi bi-crosshair"></i> <?= e(t('checkout.my_location')) ?>
                            </button>
                        </div>

                        <textarea name="delivery_address" id="addrField"
                                  class="qz-form-input" rows="2" style="resize:vertical;"
                                  placeholder="<?= e(t('checkout.address_placeholder')) ?>"></textarea>

                        <p style="font-size:.7rem;color:var(--pub-muted);margin:5px 0 0;">
                            <i class="bi bi-info-circle"></i> <?= e(t('checkout.map_hint')) ?>
                        </p>

                        <div id="zoneBar"></div>
                        <div id="deliveryMap"></div>

                        <!-- Driver nav preview -->
                        <div id="navBox" style="display: none; margin-top: 10px; padding: 12px; border-radius: 12px; background: rgba(26,115,232,0.05); border: 1px solid rgba(26,115,232,0.15);">
                            <i class="bi bi-map"></i> <strong style="color:#1a73e8;">Navigation link:</strong>
                            <a id="navLink" href="#" target="_blank" style="word-break: break-all; opacity: 0.8; font-size: 0.8rem; display: block; margin-top: 4px;"></a>
                        </div>
                    </div>

                    <div class="qz-form-field" style="grid-column:1/-1;">
                        <label class="qz-form-label"><i class="bi bi-chat-left-text"></i> <?= e(t('checkout.notes')) ?></label>
                        <textarea name="order_notes" class="qz-form-input" rows="2"
                                  style="resize:vertical;"
                                  placeholder="<?= e(t('checkout.additional_notes')) ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- Delivery Method -->
            <div class="qz-co-card">
                <h2 class="qz-co-card-title"><i class="bi bi-truck"></i> <?= e(t('checkout.delivery_info')) ?></h2>
                <div style="padding:16px;">

                    <!-- 3 tabs: rendered by JS based on API response -->
                    <div class="qz-tab-bar" id="tabBar">
                        <button type="button" class="qz-tab active" data-mode="merchant" onclick="switchTab('merchant')">
                            <span class="qz-tab-icon"><i class="bi bi-shop"></i></span><?= e(t('checkout.delivery_merchant')) ?>
                        </button>
                        <button type="button" class="qz-tab" data-mode="courier" onclick="switchTab('courier')">
                            <span class="qz-tab-icon"><i class="bi bi-truck"></i></span><?= e(t('checkout.delivery_courier')) ?>
                        </button>
                        <button type="button" class="qz-tab" data-mode="pickup" onclick="switchTab('pickup')">
                            <span class="qz-tab-icon"><i class="bi bi-geo-alt"></i></span><?= e(t('checkout.delivery_pickup')) ?>
                        </button>
                    </div>

                    <!-- Merchant tab -->
                    <div id="tab-merchant">
                        <p id="merchantHint" style="font-size:.83rem;color:var(--pub-muted);margin:0 0 8px;">
                            <?= e(t('checkout.merchant_delivery_map_hint')) ?>
                        </p>
                        <div id="merchantList"></div>
                    </div>

                    <!-- Courier tab -->
                    <div id="tab-courier" style="display:none;">
                        <div class="qz-co-sel-wrap">
                            <label for="courierSel"><?= e(t('checkout.select_courier')) ?></label>
                            <select id="courierSel" class="qz-co-sel" onchange="onCourierPick()">
                                <option value="">— <?= e(t('checkout.select_courier')) ?> —</option>
                            </select>
                            <div class="qz-co-detail" id="courierDetail">
                                <span id="coRating"></span>
                                <span class="qz-badge" id="coFee"></span>
                                <span id="coEta"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Pickup tab -->
                    <div id="tab-pickup" style="display:none;">
                        <div id="pickupList">
                            <div class="qz-spin-wrap">
                                <div class="qz-spin"></div>
                                <?= e(t('common.loading')) ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Payment -->
            <div class="qz-co-card">
                <h2 class="qz-co-card-title"><i class="bi bi-credit-card"></i> <?= e(t('checkout.payment')) ?></h2>
                <div class="qz-pm-grid">
                    <?php if (empty($entityPMs)): ?>
                    <label class="qz-pm-opt">
                        <input type="radio" name="payment_method_code" value="cash" checked>
                        <span class="qz-pm-label">
                            <span class="qz-pm-icon"><i class="bi bi-cash-coin"></i></span>
                            <span class="qz-pm-name"><?= e(t('checkout.cash_on_delivery')) ?></span>
                        </span>
                    </label>
                    <?php else: foreach ($entityPMs as $i => $pm): ?>
                    <label class="qz-pm-opt">
                        <input type="radio" name="payment_method_code"
                               value="<?= e($pm['code'] ?? 'pm_'.$i) ?>" <?= $i===0?'checked':'' ?>>
                        <span class="qz-pm-label">
                            <span class="qz-pm-icon"><i class="bi bi-credit-card"></i></span>
                            <span class="qz-pm-name"><?= e($pm['name'] ?? $pm['code']) ?></span>
                        </span>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div><!-- /LEFT -->

        <div class="qz-summary-wrap">
            <div class="qz-summary-card">
                <h2 class="qz-summary-title"><i class="bi bi-receipt"></i> <?= e(t('cart.order_summary')) ?></h2>

                <!-- Items list -->
                <div id="summaryItems" style="margin-bottom:14px;display:grid;gap:8px;max-height:240px;overflow-y:auto;">
                    <?php if (!empty($cartItems)):
                        foreach ($cartItems as $ci):
                            $up = (float)$ci['unit_price'];
                            $sp = isset($ci['sale_price']) && is_numeric($ci['sale_price']) ? (float)$ci['sale_price'] : null;
                            $eff = ($sp !== null && $sp > 0 && $sp < $up) ? $sp : $up;
                    ?>
                    <div style="display:flex;gap:10px;align-items:center;font-size:.84rem;">
                        <?php if (!empty($ci['image_url'])): ?>
                        <img src="<?= e($ci['image_url']) ?>" alt=""
                             class="qz-item-thumb"
                             onerror="this.style.display='none'">
                        <?php endif; ?>
                        <div style="flex:1;overflow:hidden;">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;color:var(--pub-text);">
                                <?= e($ci['product_name']) ?>
                            </div>
                            <div style="color:var(--pub-muted);font-size:.76rem;">×<?= (int)$ci['quantity'] ?></div>
                        </div>
                        <strong style="flex-shrink:0;color:var(--pub-primary);">
                            <?= number_format($eff * (int)$ci['quantity'], 2) ?> <?= e($displayCurrency) ?>
                        </strong>
                    </div>
                    <?php endforeach; else: ?>
                    <!-- JS fills this for guest/localStorage cart -->
                    <?php endif; ?>
                </div>

                <div style="border-top:1px solid var(--pub-border);margin-bottom:10px;"></div>

                <!-- Totals -->
                <div class="qz-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong id="sumSub"><?= number_format($cartTotal,2) ?> <?= e($displayCurrency) ?></strong>
                </div>
                <div class="qz-summary-row" style="font-size:.84rem;">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span id="sumShip" style="color:var(--pub-muted);">—</span>
                </div>
                <div class="qz-summary-row qz-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong id="sumTotal"><?= number_format($cartTotal,2) ?> <?= e($displayCurrency) ?></strong>
                </div>

                <!-- Selected option pill -->
                <div id="chosenBox"></div>

                <button type="submit" id="submitBtn"
                        class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:.95rem;padding:13px;"
                        disabled>
                    <i class="bi bi-check-circle"></i> <?= e(t('checkout.place_order')) ?>
                </button>
                <p id="submitHint"><?= e(t('checkout.delivery_select_hint')) ?></p>
                <p style="font-size:.72rem;text-align:center;color:var(--pub-muted);margin-top:4px;">
                    <i class="bi bi-shield-lock"></i> <?= e(t('checkout.secure_transaction')) ?>
                </p>
            </div>
        </div>

    </form>
    <?php endif; ?>

</div><!-- /pub-container -->

<script>
(function(){
'use strict';

/* ── PHP injected config ──────────────────────────── */
var HAS_DB = <?= $hasDbCart ? 'true' : 'false' ?>;
var CUR    = <?= json_encode($displayCurrency) ?>;
var TID    = <?= $tenantId ?>;
var EID    = <?= $entityId ?>;
var LANG   = <?= json_encode($lang) ?>;
var DB_CART = <?= $cartItemsJs ?>;

var S = {
    free        : <?= json_encode(t('common.free') ?: 'Free') ?>,
    loading     : <?= json_encode(t('common.loading') ?: 'Loading…') ?>,
    empty       : <?= json_encode(t('cart.empty') ?: 'Cart is empty') ?>,
    myLoc       : <?= json_encode(t('checkout.my_location') ?: 'My location') ?>,
    calculating : <?= json_encode(t('checkout_js.calculating') ?: 'Checking coverage…') ?>,
    outCov      : <?= json_encode(t('checkout_js.out_of_coverage') ?: 'Outside delivery area') ?>,
    inCov       : <?= json_encode(t('checkout_js.in_coverage') ?: 'Delivery available') ?>,
    mins        : <?= json_encode(t('checkout_js.mins') ?: 'min') ?>,
    errLoad     : <?= json_encode(t('checkout_js.err_load') ?: 'Could not load delivery options') ?>,
    locReq      : <?= json_encode(t('checkout.error_location_required') ?: 'Please pin your location on the map') ?>,
    noZones     : <?= json_encode(t('checkout.no_delivery_zones') ?: 'No delivery zones for your location') ?>,
    noCouriers  : <?= json_encode(t('checkout.no_couriers') ?: 'No couriers available') ?>,
    noPickup    : <?= json_encode(t('checkout.no_pickup_points') ?: 'No pickup points available') ?>,
    fallbackZone: <?= json_encode(t('checkout.standard_delivery') ?: 'Standard Delivery') ?>
};

/* ── State ──────────────────────────────────────── */
var _sub      = <?= (float)$cartTotal ?>;
var _ship     = 0;
var _mode     = 'merchant';
var _optId    = 0;
var _map, _marker, _covLyr;
var _addrManual = false;
var _deliveryData = null;  /* last response from delivery API */

/* ── DOM helpers ────────────────────────────────── */
function $(id){ return document.getElementById(id); }
function fmt(n){ return parseFloat(n||0).toFixed(2); }
function esc(s){
    return String(s==null?'':s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Summary ─────────────────────────────────────── */
function updateSummary(){
    var ship = (_mode === 'pickup') ? 0 : _ship;
    $('sumShip').textContent = ship > 0 ? fmt(ship)+' '+CUR : (_mode==='pickup' ? '✅ '+S.free : '—');
    $('sumTotal').textContent = fmt(_sub + ship)+' '+CUR;
}

function setDelivery(mode, optId, fee, label){
    _mode  = mode;
    _optId = optId;
    _ship  = parseFloat(fee) || 0;
    $('hMode').value  = mode;
    $('hOptId').value = optId;
    $('hCourierFee').value = _ship;
    updateSummary();

    var ok  = optId > 0 || mode === 'pickup';
    var btn = $('submitBtn'), hint = $('submitHint'), box = $('chosenBox');
    btn.disabled = !ok;
    hint.style.display = ok ? 'none' : '';
    if (box) { box.style.display = ok && label ? '' : 'none'; if(label) box.textContent = label; }
}

function resetDelivery(){ setDelivery(_mode, 0, 0, ''); }

/* ── Local cart (guest) ──────────────────────────── */
function localCart(){
    try {
        var k = 'pub_cart_t'+TID+'_e'+EID;
        var items = JSON.parse(localStorage.getItem(k)||'[]');
        if (!items.length) {
            /* legacy key fallback */
            items = JSON.parse(localStorage.getItem('pub_cart')||'[]');
        }
        return items;
    } catch(e){ return []; }
}

function renderLocalSummary(){
    if (HAS_DB) return;
    var cart = localCart();
    var wrap = $('summaryItems');
    if (!wrap) return;
    if (!cart.length){
        wrap.innerHTML = '<p style="color:var(--pub-muted);font-size:.84rem;">'+S.empty+'</p>';
        $('submitBtn').disabled = true;
        return;
    }
    var sub = 0, html = '';
    CUR = cart[0].currency || cart[0].currency_code || CUR;
    cart.forEach(function(it){
        var _sp = parseFloat(it.sale_price);
        var _up = parseFloat(it.price || 0);
        var p = (!isNaN(_sp) && _sp > 0 && _sp < _up) ? _sp : _up;
        var q = parseInt(it.qty||1,10);
        sub += p*q;
        html += '<div style="display:flex;gap:10px;align-items:center;font-size:.84rem;">'
            + (it.image ? '<img src="'+esc(it.image)+'" class="qz-item-thumb" onerror="this.style.display=\'none\'">' : '')
            + '<div style="flex:1;overflow:hidden;">'
            +   '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;color:var(--pub-text);">'+esc(it.name)+'</div>'
            +   '<div style="color:var(--pub-muted);font-size:.76rem;">×'+q+'</div>'
            + '</div>'
            + '<strong style="flex-shrink:0;color:var(--pub-primary);">'+fmt(p*q)+' '+CUR+'</strong>'
            + '</div>';
    });
    wrap.innerHTML = html;
    _sub = sub;
    $('sumSub').textContent = fmt(sub)+' '+CUR;
    $('cartItemsJson').value = JSON.stringify(cart);
    updateSummary();
}

/* ── Map init ───────────────────────────────────── */
function waitForLeaflet(cb){
    if (typeof L !== 'undefined') { cb(); return; }
    setTimeout(function(){ waitForLeaflet(cb); }, 100);
}

function initMap(){
    waitForLeaflet(function(){
        var mapEl = $('deliveryMap');
        if (!mapEl) return;

        _map    = L.map('deliveryMap').setView([23.4241,53.8478], 8); /* UAE center */
        _covLyr = L.featureGroup().addTo(_map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
            attribution:'© OpenStreetMap', maxZoom:19
        }).addTo(_map);

        _marker = L.marker([23.4241,53.8478], {draggable:true}).addTo(_map);
        _marker.on('dragend', function(){
            var p = _marker.getLatLng();
            applyCoords(p.lat, p.lng);
        });
        _map.on('click', function(e){
            _marker.setLatLng(e.latlng);
            applyCoords(e.latlng.lat, e.latlng.lng);
        });

        $('btnGPS').onclick = gpsLocate;

        var af = $('addrField');
        if (af) af.addEventListener('input', function(){ _addrManual = af.value.trim().length > 0; });

        /* Load coverage overlay + GPS on ready */
        loadCoverage(function(){ gpsLocate(); });
    });
}

function gpsLocate(){
    var btn = $('btnGPS');
    if (btn){ btn.textContent = '⌛'; btn.disabled = true; }
    if (!navigator.geolocation){
        var p = _marker.getLatLng(); applyCoords(p.lat, p.lng);
        restoreGPS(btn); return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos){
            _map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            _marker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
            applyCoords(pos.coords.latitude, pos.coords.longitude);
            restoreGPS(btn);
        },
        function(){
            var p = _marker.getLatLng(); applyCoords(p.lat, p.lng);
            restoreGPS(btn);
        },
        {enableHighAccuracy:true, timeout:8000}
    );
}

function restoreGPS(btn){
    if (btn){ btn.innerHTML = '<i class="bi bi-crosshair"></i> '+S.myLoc; btn.disabled = false; }
}

/* Called every time pin moves */
function applyCoords(lat, lng){
    $('hLat').value = lat;
    $('hLng').value = lng;
    reverseGeocode(lat, lng);
    updateNavLink(lat, lng);
    if (_mode === 'merchant') fetchDelivery(lat, lng);
}

function reverseGeocode(lat, lng){
    var af = $('addrField');
    if (!af || (_addrManual && af.value.trim())) return;
    af.placeholder = '⌛ …';
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng+'&zoom=18',{
        headers: {'Accept-Language': LANG}
    }).then(function(r){ return r.json(); })
      .then(function(d){
          if (d && d.display_name) { af.value = d.display_name; _addrManual = false; }
          af.placeholder = '';
      }).catch(function(){ af.placeholder = ''; });
}

function updateNavLink(lat, lng){
    var url = 'https://www.google.com/maps/dir/?api=1&destination='+lat+','+lng;
    var box = $('navBox'), lnk = $('navLink');
    if (box && lnk){ box.style.display=''; lnk.href=url; lnk.textContent=url; }
}

/* ── Coverage overlay ────────────────────────────── */
function loadCoverage(cb){
    fetch('/api/public/delivery?coverage=1&tenant_id='+TID+'&entity_id='+EID+'&_t='+Date.now())
    .then(function(r){ return r.json(); })
    .then(function(resp){
        var zones = (resp.success && resp.data && resp.data.zones) ? resp.data.zones : [];
        _covLyr.clearLayers();
        zones.forEach(function(z){
            var data = null;
            try { if(z.zone_value) data = JSON.parse(z.zone_value); } catch(e){}
            var type = ((data&&data.type)||z.zone_type||'').toLowerCase();
            var c    = z.is_merchant_zone ? '#10b981' : '#3b82f6';
            var st   = {color:c, fillColor:c, fillOpacity:.12, weight:1.5};
            var lyr  = null;

            if (type === 'polygon' && data && data.coordinates) {
                var ll = (data.coordinates[0]||[]).map(function(x){ return[+x[1],+x[0]]; });
                if (ll.length) lyr = L.polygon(ll, st);
            } else if (type === 'multipolygon' && data && data.coordinates) {
                var polys = data.coordinates.map(function(p){
                    return (p[0]||[]).map(function(x){ return[+x[1],+x[0]]; });
                });
                if (polys.length) lyr = L.polygon(polys, st);
            } else if (['radius','circle'].indexOf(type) >= 0) {
                var clat = +((data&&data.center&&data.center[0])||z.center_lat||0);
                var clng = +((data&&data.center&&data.center[1])||z.center_lng||0);
                var rad  = +((data&&data.radius)||(z.radius_km*1000)||5000);
                if (!isNaN(clat) && clat) lyr = L.circle([clat,clng], Object.assign({radius:rad},st));
            } else if (type === 'rectangle' && data && data.bounds) {
                lyr = L.rectangle(data.bounds, st);
            }

            if (lyr) {
                var fee = parseFloat(z.delivery_fee||0).toFixed(2);
                lyr.bindPopup(
                    '<strong>'+esc(z.zone_name||'Zone')+'</strong><br>'
                    + (z.is_merchant_zone?'<i class="bi bi-shop"></i> Merchant':'<i class="bi bi-truck"></i> Courier')
                    + '<br>Fee: <b>'+fee+' '+CUR+'</b>'
                );
                lyr.addTo(_covLyr);
            }
        });
        if (_covLyr.getLayers().length > 0) {
            var b = _covLyr.getBounds();
            if (b.isValid()) _map.fitBounds(b, {padding:[40,40]});
        }
        if (cb) cb();
    }).catch(function(){ if(cb) cb(); });
}

/* ── Main delivery fetch ─────────────────────────── */
function fetchDelivery(lat, lng){
    var list = $('merchantList'), bar = $('zoneBar'), hint = $('merchantHint');
    if (list) list.innerHTML = '<div class="qz-spin-wrap"><div class="qz-spin"></div>'+S.calculating+'</div>';
    if (hint) hint.style.display = 'none';
    resetDelivery();

    fetch('/api/public/delivery?tenant_id='+TID+'&entity_id='+EID+'&lat='+lat+'&lng='+lng+'&_t='+Date.now())
    .then(function(r){ return r.json(); })
    .then(function(resp){
        if (!resp.success || !resp.data) {
            showMerchantErr(); return;
        }
        _deliveryData = resp.data;
        renderMerchantTab(resp.data);
        renderCourierTab(resp.data);
        renderPickupTab(resp.data);
    })
    .catch(function(){ showMerchantErr(); });
}

/* ── Merchant tab ────────────────────────────────── */
function renderMerchantTab(data){
    var list = $('merchantList'), bar = $('zoneBar');
    /* options with method === 'merchant' */
    var opts = (data.options||[]).filter(function(o){ return o.method === 'merchant'; });

    if (!opts.length) {
        if (bar){ bar.className='in'; bar.style.display='flex'; bar.textContent='✅ '+S.inCov; }
        var html = '<div class="qz-opt-card" data-id="0" data-fee="0" data-name="'+esc(S.fallbackZone)+'" onclick="pickMerchant(this)">'
             + '<div class="qz-opt-icon"><i class="bi bi-shop"></i></div>'
             + '<div class="qz-opt-body">'
             +   '<div class="qz-opt-name">'+esc(S.fallbackZone)+'</div>'
             +   '<div class="qz-opt-meta">'+S.free+'</div>'
             + '</div>'
             + '<div class="qz-opt-free"><i class="bi bi-check-circle-fill"></i> '+S.free+'</div>'
             + '</div>';
        if (list) {
            list.innerHTML = html;
            var first = list.querySelector('.qz-opt-card');
            if (first) pickMerchant(first);
        }
        return;
    }
    if (bar){ bar.className='in'; bar.style.display='flex'; bar.textContent='✅ '+S.inCov; }

    var html = '';
    opts.forEach(function(o){
        var fee = parseFloat(o.delivery_fee||0);
        var freeOver = o.free_delivery_over ? parseFloat(o.free_delivery_over) : 0;
        var meta = '⏱ ~'+((o.estimated_minutes||45))+' '+S.mins;
        if (freeOver > 0) meta += ' · Free over '+fmt(freeOver)+' '+CUR;
        html += '<div class="qz-opt-card" data-id="'+o.zone_id+'" data-fee="'+fee+'" data-name="'+esc(o.label)+'" onclick="pickMerchant(this)">'
             + '<div class="qz-opt-icon"><i class="bi bi-shop"></i></div>'
             + '<div class="qz-opt-body">'
             +   '<div class="qz-opt-name">'+esc(o.label)+'</div>'
             +   '<div class="qz-opt-meta">'+meta+'</div>'
             + '</div>'
             + (fee > 0 ? '<div class="qz-opt-fee">'+fmt(fee)+' '+CUR+'</div>'
                        : '<div class="qz-opt-free"><i class="bi bi-check-circle-fill"></i> '+S.free+'</div>')
             + '</div>';
    });
    if (list) {
        list.innerHTML = html;
        /* Auto-select first */
        var first = list.querySelector('.qz-opt-card');
        if (first) pickMerchant(first);
    }
}

/* ── Courier tab ─────────────────────────────────── */
function renderCourierTab(data){
    var sel = $('courierSel');
    if (!sel) return;

    var couriers = (data.providers||[]);

    /* Also check options for courier entries */
    var courierOpts = (data.options||[]).filter(function(o){ return o.method === 'courier'; });

    sel.innerHTML = '<option value="">— '+S.loading.replace('⌛ ','')+'… —</option>';

    if (!couriers.length && !courierOpts.length) {
        sel.innerHTML = '<option value="" disabled>'+S.noCouriers+'</option>';
        return;
    }

    sel.innerHTML = '<option value="">— <?= e(t('checkout.select_courier')) ?> —</option>';

    courierOpts.forEach(function(o){
        if (!o.provider) return;
        var icon = o.provider.vehicle_type === 'car' ? '<i class="bi bi-car-front"></i>'
                 : o.provider.vehicle_type === 'van' ? '<i class="bi bi-truck-front"></i>'
                 : o.provider.vehicle_type === 'truck' ? '<i class="bi bi-truck"></i>' : '<i class="bi bi-bicycle"></i>';
        var opt = document.createElement('option');
        opt.value          = o.provider.id;
        opt.dataset.fee    = (o.delivery_fee !== null ? o.delivery_fee : 25);
        opt.dataset.rating = o.provider.rating || '0';
        opt.dataset.eta    = o.estimated_minutes || '';
        opt.dataset.online = o.provider.is_online ? '1' : '0';
        opt.textContent    = icon + ' ' + (o.provider.name || 'Provider #'+o.provider.id);
        sel.appendChild(opt);
    });

    couriers.forEach(function(p){
        var alreadyAdded = courierOpts.some(function(o){ return o.provider && o.provider.id == p.id; });
        if (alreadyAdded) return;
        var icon = p.vehicle_type === 'car' ? '🚗' : p.vehicle_type === 'van' ? '🚐' : p.vehicle_type === 'truck' ? '🚚' : '🏍️';
        var opt = document.createElement('option');
        opt.value          = p.id;
        opt.dataset.fee    = 25;
        opt.dataset.rating = p.rating || '0';
        opt.dataset.eta    = '';
        opt.dataset.online = p.is_online ? '1' : '0';
        opt.textContent    = icon + ' ' + (p.name || 'Provider #'+p.id);
        sel.appendChild(opt);
    });
}

/* ── Pickup tab ──────────────────────────────────── */
function renderPickupTab(data){
    var list    = $('pickupList');
    var points  = data.pickup_points || [];

    if (!list) return;

    if (!points.length) {
        list.innerHTML = '<p style="color:var(--pub-muted);font-size:.84rem;">'+S.noPickup+'</p>';
        return;
    }

    var html = '';
    points.forEach(function(pk){
        var dist = pk.distance_km !== null && pk.distance_km !== undefined
                 ? ' · 📏 '+pk.distance_km+' km' : '';
        html += '<div class="qz-opt-card" data-id="'+pk.id+'"'
             +  ' data-lat="'+esc(pk.latitude||'')+'" data-lng="'+esc(pk.longitude||'')+'"'
             +  ' onclick="selectPickup(this)">'
             + '<div class="qz-opt-icon"><i class="bi bi-geo-alt"></i></div>'
             + '<div class="qz-opt-body">'
             +   '<div class="qz-opt-name">'+esc(pk.name)+'</div>'
             +   '<div class="qz-opt-meta"><i class="bi bi-geo"></i> '+esc(pk.address)+dist+'</div>'
             +   (pk.working_hours ? '<div class="qz-opt-meta"><i class="bi bi-clock"></i> '+esc(pk.working_hours)+'</div>' : '')
             +   (pk.phone ? '<div class="qz-opt-meta"><i class="bi bi-telephone"></i> '+esc(pk.phone)+'</div>' : '')
             + '</div>'
             + '<div class="qz-opt-free"><i class="bi bi-check-circle-fill"></i> '+S.free+'</div>'
             + '</div>';
    });
    list.innerHTML = html;
}

function showMerchantErr(){
    var list = $('merchantList'), bar = $('zoneBar');
    if (bar){ bar.className='out'; bar.style.display='flex'; bar.textContent='⚠️ '+S.errLoad; }
    if (list) list.innerHTML = '';
}

/* ── Selection handlers ──────────────────────────── */
window.pickMerchant = function(card){
    document.querySelectorAll('#merchantList .qz-opt-card').forEach(function(c){ c.classList.remove('active'); });
    card.classList.add('active');
    var fee  = parseFloat(card.dataset.fee || 0);
    var name = card.dataset.name || card.querySelector('.qz-opt-name').textContent;
    setDelivery('merchant', card.dataset.id, fee, '<i class="bi bi-shop"></i> '+name+' · '+(fee>0?fmt(fee)+' '+CUR:'<i class="bi bi-check-circle-fill"></i> '+S.free));
};

window.onCourierPick = function(){
    var sel  = $('courierSel');
    var opt  = sel ? sel.options[sel.selectedIndex] : null;
    var info = $('courierDetail');
    if (!opt || !opt.value){ resetDelivery(); if(info) info.style.display='none'; return; }

    var fee    = parseFloat(opt.dataset.fee || 25);
    var rating = parseFloat(opt.dataset.rating || 0);
    var eta    = opt.dataset.eta ? '⏱ ~'+opt.dataset.eta+' '+S.mins : '';
    var online = opt.dataset.online === '1';

    setDelivery('courier', opt.value, fee, '🚛 '+opt.text+' · '+fmt(fee)+' '+CUR);
    $('hCourierFee').value = fee;

    if (info) {
        info.style.display = 'flex';
        $('coRating').textContent = rating > 0 ? '⭐ '+rating.toFixed(1) : (online ? '🟢 Online' : '⚫ Offline');
        $('coFee').textContent    = '💰 '+fmt(fee)+' '+CUR;
        $('coEta').textContent    = eta;
    }
};

window.selectPickup = function(card){
    document.querySelectorAll('#pickupList .qz-opt-card').forEach(function(c){ c.classList.remove('active'); });
    card.classList.add('active');
    setDelivery('pickup', card.dataset.id, 0, '🏬 '+card.querySelector('.qz-opt-name').textContent);
    if (card.dataset.lat && card.dataset.lng && _map) {
        _map.setView([+card.dataset.lat, +card.dataset.lng], 15);
        _marker.setLatLng([+card.dataset.lat, +card.dataset.lng]);
        $('hLat').value = card.dataset.lat;
        $('hLng').value = card.dataset.lng;
    }
};

/* ── Tab switching ───────────────────────────────── */
window.switchTab = function(mode){
    _mode = mode;
    $('hMode').value = mode;

    document.querySelectorAll('.qz-tab').forEach(function(b){
        b.classList.toggle('active', b.dataset.mode === mode);
    });
    ['merchant','courier','pickup'].forEach(function(m){
        $('tab-'+m).style.display = m === mode ? '' : 'none';
    });

    resetDelivery();
    var bar = $('zoneBar');
    if (bar && mode !== 'merchant') bar.style.display = 'none';

    if (mode === 'merchant') {
        var lat = $('hLat').value, lng = $('hLng').value;
        if (lat && lng) fetchDelivery(+lat, +lng);
    }
    if (mode === 'courier') {
        var cs = $('courierSel');
        if (cs) { cs.selectedIndex = 0; onCourierPick(); }
        /* Fetch if no data yet */
        if (!_deliveryData) {
            var lat2 = $('hLat').value, lng2 = $('hLng').value;
            if (lat2 && lng2) fetchDelivery(+lat2, +lng2);
        }
    }
    if (mode === 'pickup') {
        var cards = document.querySelectorAll('#pickupList .qz-opt-card');
        if (cards.length === 1) selectPickup(cards[0]);
        /* Pickup doesn't need location */
        if (!$('hLat').value) setDelivery('pickup', 0, 0, '');
        /* Fetch pickup points if not loaded */
        if (!_deliveryData) {
            var lat3 = $('hLat').value, lng3 = $('hLng').value;
            if (lat3 && lng3) fetchDelivery(+lat3, +lng3);
            else {
                /* Fetch without coordinates using pickup endpoint */
                fetch('/api/public/delivery/pickup?tenant_id='+TID+'&entity_id='+EID+'&_t='+Date.now())
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp.success && resp.data) {
                        renderPickupTab({pickup_points: resp.data.pickup_points||[]});
                    }
                }).catch(function(){});
            }
        }
    }
};

/* ── Form submit ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function(){
    renderLocalSummary();
    initMap();

    var form = $('checkoutForm');
    if (!form) return;
    form.addEventListener('submit', function(ev){
        if (_mode !== 'pickup' && !$('hLat').value) {
            ev.preventDefault();
            alert(S.locReq);
            return;
        }
        if (!HAS_DB) {
            $('cartItemsJson').value = JSON.stringify(localCart());
        }
    });
});

}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
