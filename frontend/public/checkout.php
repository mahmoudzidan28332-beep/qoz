<?php
/**
 * frontend/public/checkout.php  â€” QOOQZ
 * 3 delivery modes: merchant zone | shipping company | store pickup
 * Address auto-fills from map pin. Driver nav link stored in order notes.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;
$userId   = (int)($user['id'] ?? 0);
$activeEntity = is_array($ctx['active_entity'] ?? null) ? $ctx['active_entity'] : [];
$activeEntityId = (int)($activeEntity['id'] ?? 0);
$activeEntityName = trim((string)($activeEntity['name'] ?? ''));

if (!$userId) {
    header('Location: /frontend/login.php?redirect=' . urlencode('/frontend/public/checkout.php'));
    exit;
}

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('checkout.title') . ' â€” QOOQZ';

/* -------------------------------------------------------
 * DB + Cart
 * ----------------------------------------------------- */
$pdo       = pub_get_pdo();
$cartItems = [];
$cartTotal = 0.0;
$cartId    = 0;
$entityId  = $activeEntityId > 0 ? $activeEntityId : 1;

if ($pdo) {
    try {
        $cs = $pdo->prepare(
            "SELECT id, entity_id
               FROM carts
              WHERE user_id = ?
                AND entity_id = ?
                AND status = 'active'
              ORDER BY id DESC
              LIMIT 1"
        );
        $cs->execute([$userId, $entityId]);
        $cartRow = $cs->fetch(PDO::FETCH_ASSOC);
        if ($cartRow) {
            $cartId   = (int)$cartRow['id'];
            $entityId = (int)($cartRow['entity_id'] ?: 1);
            $is = $pdo->prepare(
                "SELECT ci.id, ci.product_id, ci.product_name, ci.sku,
                        ci.quantity, ci.unit_price, ci.subtotal, ci.entity_id,
                        (SELECT i.url FROM images i
                          WHERE i.owner_id = ci.product_id AND i.image_type_id = 2
                          ORDER BY i.sort_order ASC, i.id ASC LIMIT 1) AS image_url
                   FROM cart_items ci WHERE ci.cart_id = ? ORDER BY ci.added_at ASC"
            );
            $is->execute([$cartId]);
            $cartItems = $is->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cartItems as $ci) {
                $cartTotal += (float)$ci['unit_price'] * (int)$ci['quantity'];
            }
            $cartTotal = round($cartTotal, 2);
        }
    } catch (Throwable) {}
}

/* -------------------------------------------------------
 * Payment Methods
 * ----------------------------------------------------- */
$entityPMs = [];
if ($pdo) {
    try {
        $ps = $pdo->prepare(
            "SELECT pm.method_key AS code, pm.method_name AS name, pm.icon_url AS icon
               FROM entity_payment_methods epm
               JOIN payment_methods pm ON pm.id = epm.payment_method_id
              WHERE epm.entity_id = ? AND epm.is_active = 1 ORDER BY pm.sort_order ASC"
        );
        $ps->execute([$entityId]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}
if (empty($entityPMs) && $pdo) {
    try {
        $ps = $pdo->prepare(
            "SELECT method_key AS code, method_name AS name, icon_url AS icon
               FROM payment_methods WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 10"
        );
        $ps->execute([$tenantId]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

/* -------------------------------------------------------
 * Shipping Companies (courier tab)
 * ----------------------------------------------------- */
$couriers = [];
if ($pdo) {
    try {
        $cst = $pdo->prepare(
            "SELECT dp.id, tu.name AS provider_name, dp.vehicle_type, dp.rating
               FROM delivery_providers dp
               JOIN tenant_users tu ON dp.tenant_user_id = tu.id
              WHERE dp.tenant_id = ?
                AND dp.provider_type IN ('company','independent_driver')
                AND dp.is_active = 1
              ORDER BY dp.rating DESC, tu.name ASC"
        );
        $cst->execute([$tenantId]);
        $couriers = $cst->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

/* -------------------------------------------------------
 * Pickup Points
 * ----------------------------------------------------- */
$pickupPoints = [];
if ($pdo) {
    try {
        $ppt = $pdo->prepare(
            "SELECT id, name, address, latitude, longitude, working_hours, phone
               FROM entity_pickup_points
              WHERE tenant_id = ? AND entity_id = ? AND is_active = 1
              ORDER BY sort_order ASC, id ASC"
        );
        $ppt->execute([$tenantId, $entityId]);
        $pickupPoints = $ppt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}  // Table may not exist yet
}

/* -------------------------------------------------------
 * POST Handler
 * ----------------------------------------------------- */
$checkoutError   = '';
$checkoutSuccess = false;
$orderNumber     = '';
$orderId         = 0;
$driverNavLink   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $pmCode        = trim($_POST['payment_method_code'] ?? '');
    $custName      = trim($_POST['customer_name']       ?? '');
    $custPhone     = trim($_POST['customer_phone']      ?? '');
    $address       = trim($_POST['delivery_address']    ?? '');
    $notes         = trim($_POST['order_notes']         ?? '');
    $lat           = (isset($_POST['delivery_lat']) && $_POST['delivery_lat'] !== '') ? (float)$_POST['delivery_lat'] : null;
    $lng           = (isset($_POST['delivery_lng']) && $_POST['delivery_lng'] !== '') ? (float)$_POST['delivery_lng'] : null;
    $deliveryMode  = trim($_POST['delivery_mode']        ?? 'merchant');
    $deliveryOptId = (int)($_POST['delivery_option_id']  ?? 0);
    $shippingCost  = 0.0;

    // Server-side fee verification
    if ($deliveryMode === 'merchant' && $deliveryOptId) {
        $fq = $pdo->prepare("SELECT delivery_fee FROM delivery_zones WHERE id = ? AND tenant_id = ? LIMIT 1");
        $fq->execute([$deliveryOptId, $tenantId]);
        $fRow = $fq->fetch(PDO::FETCH_ASSOC);
        if ($fRow) $shippingCost = (float)$fRow['delivery_fee'];
    } elseif ($deliveryMode === 'courier' && $deliveryOptId) {
        $shippingCost = 25.0; // extend with courier_fees table later
    }

    if ($lat !== null && $lng !== null) {
        $driverNavLink = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
    }

    // Fallback localStorage cart
    $jsItems = [];
    if (empty($cartItems)) {
        $jsItems = json_decode($_POST['cart_items_json'] ?? '[]', true);
        if (!is_array($jsItems)) $jsItems = [];
        foreach ($jsItems as &$ji) {
            if (empty($ji['entity_id'])) {
                $ji['entity_id'] = $entityId;
            }
            $cartTotal += (float)($ji['price'] ?? 0) * max(1, (int)($ji['qty'] ?? 1));
        }
        unset($ji);
        $cartTotal = round($cartTotal, 2);
    }
    $allItems = !empty($cartItems) ? $cartItems : $jsItems;
    $itemEntityIds = [];
    foreach ($allItems as $ci) {
        $resolvedItemEntityId = (int)($ci['entity_id'] ?? $entityId ?? 0);
        if ($resolvedItemEntityId > 0) {
            $itemEntityIds[$resolvedItemEntityId] = true;
        }
    }

    if (!$custName || !$custPhone) {
        $checkoutError = t('checkout.error_fields_required');
    } elseif (empty($allItems)) {
        $checkoutError = t('cart.empty');
    } elseif ($entityId <= 0 || $activeEntityId <= 0) {
        $checkoutError = 'Please select a delivery branch before checkout.';
    } elseif (count($itemEntityIds) > 1) {
        $checkoutError = 'Your cart contains items from multiple branches. Please switch branch and try again.';
    } elseif (!empty($itemEntityIds) && !isset($itemEntityIds[$entityId])) {
        $checkoutError = 'Your active branch does not match the current cart.';
    } elseif ($deliveryMode !== 'pickup' && !$deliveryOptId) {
        $checkoutError = 'ظٹط±ط¬ظ‰ ط§ط®طھظٹط§ط± ط·ط±ظٹظ‚ط© ط§ظ„طھظˆطµظٹظ„';
    } else {
        $grandTotal  = round($cartTotal + $shippingCost, 2);
        $orderNumber = 'ORD-' . $tenantId . '-' . time() . '-' . rand(100, 999);

        // Build delivery notes with nav link
        $fullNotes = $notes;
        if ($deliveryMode !== 'pickup' && $lat !== null) {
            $fullNotes .= "\n---\nًں“چ {$address}\nًں—؛ï¸ڈ {$lat},{$lng}\nًں”— {$driverNavLink}";
        } elseif ($deliveryMode === 'pickup') {
            $fullNotes .= "\n--- ط§ط³طھظ„ط§ظ… ظ…ظ† ط§ظ„ظ…طھط¬ط± (ظ†ظ‚ط·ط© ط±ظ‚ظ… {$deliveryOptId}) ---";
        }

        try {
            $pdo->beginTransaction();

            $dZoId = ($deliveryMode === 'merchant') ? $deliveryOptId : null;
            $dCoId = ($deliveryMode === 'courier')  ? $deliveryOptId : null;
            $dPkId = ($deliveryMode === 'pickup')   ? $deliveryOptId : null;
            $dEnId = ($deliveryMode === 'merchant') ? $entityId      : null;

            $oSt = $pdo->prepare(
                "INSERT INTO orders
                   (tenant_id, entity_id, order_number, user_id, cart_id,
                    status, payment_status,
                    subtotal, tax_amount, shipping_cost, discount_amount,
                    total_amount, grand_total, currency_code,
                    customer_notes, ip_address, payment_method,
                    delivery_zone_id, delivery_company_id,
                    delivery_entity_id, delivery_method, pickup_point_id)
                 VALUES
                   (?,?,?,?,?,
                    'pending','pending',
                    ?,0,?,0,?,?,'SAR',
                    ?,?,?,
                    ?,?,?,?,?)"
            );
            $oSt->execute([
                $tenantId, $entityId, $orderNumber, $userId, $cartId ?: null,
                $cartTotal, $shippingCost,
                $cartTotal + $shippingCost, $grandTotal,
                $fullNotes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $pmCode ?: 'cash',
                $dZoId, $dCoId,
                $dEnId, $deliveryMode, $dPkId,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $iSt = $pdo->prepare(
                "INSERT INTO order_items
                   (tenant_id, order_id, entity_id, product_id, product_name, sku,
                    quantity, unit_price, subtotal, total)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            foreach ($allItems as $ci) {
                $pId   = !empty($cartItems) ? (int)$ci['product_id']   : (int)($ci['id']    ?? 0);
                $pName = !empty($cartItems) ? (string)$ci['product_name'] : (string)($ci['name'] ?? '');
                $pSku  = (string)($ci['sku'] ?? '');
                $qty   = max(1, (int)($ci['quantity'] ?? $ci['qty'] ?? 1));
                $price = (float)($ci['unit_price'] ?? $ci['price'] ?? 0);
                if (!$pId || !$pName) continue;
                $iSt->execute([
                    $tenantId, $orderId, $entityId,
                    $pId, $pName, $pSku, $qty, $price,
                    round($price * $qty, 2), round($price * $qty, 2),
                ]);
            }

            if ($cartId) {
                $pdo->prepare(
                    "UPDATE carts SET status='converted', converted_to_order_id=?, updated_at=NOW() WHERE id=?"
                )->execute([$orderId, $cartId]);
            }
            $pdo->commit();
            $checkoutSuccess = true;

            // Payment record
            try {
                $pmNum = 'PAY-' . $tenantId . '-' . $orderId . '-' . time();
                $pdo->prepare(
                    "INSERT INTO payments
                       (entity_id, payment_number, order_id, user_id, payment_method,
                        amount, currency_code, status, payment_type, ip_address, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,'SAR','pending','full',?,NOW(),NOW())"
                )->execute([
                    $entityId, $pmNum, $orderId, $userId,
                    $pmCode ?: 'cod', $grandTotal,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable) {}

        } catch (Throwable $ex) {
            try { $pdo->rollBack(); } catch (Throwable) {}
            $checkoutError = t('common.error') . ': ' . $ex->getMessage();
        }
    }
}

include dirname(__DIR__) . '/partials/header.php';

$hasDbCart    = !empty($cartItems);
$couriersJson = json_encode(array_values($couriers),    JSON_UNESCAPED_UNICODE);
$pickupJson   = json_encode(array_values($pickupPoints), JSON_UNESCAPED_UNICODE);
?>
<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* â”€â”€ Map â”€â”€ */
#deliveryMap{width:100%;height:300px;border-radius:var(--pub-radius);border:2px solid var(--pub-border);margin-top:10px;z-index:0;}

/* â”€â”€ 3-tab bar â”€â”€ */
.dtab-bar{display:flex;border:2px solid var(--pub-border);border-radius:var(--pub-radius);overflow:hidden;margin-bottom:16px;}
.dtab{flex:1;padding:9px 4px;text-align:center;font-size:.78rem;font-weight:700;cursor:pointer;background:var(--pub-bg);color:var(--pub-muted);border:none;border-right:1px solid var(--pub-border);transition:background .15s,color .15s;line-height:1.3;}
.dtab:last-child{border-right:none;}
.dtab.active{background:var(--pub-primary);color:#fff;}
.dtab-icon{display:block;font-size:1.2rem;margin-bottom:2px;}

/* â”€â”€ Zone card â”€â”€ */
.dz-card{display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid var(--pub-border);border-radius:var(--pub-radius);cursor:pointer;transition:border-color .15s,background .15s;margin-bottom:10px;background:var(--pub-bg);}
.dz-card:hover{border-color:var(--pub-primary);}
.dz-card.active{border-color:var(--pub-primary);background:rgba(3,135,78,.07);box-shadow:0 0 0 3px rgba(3,135,78,.1);}
.dz-icon{font-size:1.5rem;flex-shrink:0;}
.dz-body{flex:1;min-width:0;}
.dz-name{font-weight:700;font-size:.9rem;color:var(--pub-text);}
.dz-meta{font-size:.75rem;color:var(--pub-muted);margin-top:2px;}
.dz-fee{font-weight:800;font-size:.95rem;color:var(--pub-primary);white-space:nowrap;}

/* â”€â”€ Zone status bar â”€â”€ */
#zoneBar{display:none;align-items:center;gap:8px;padding:8px 12px;border-radius:var(--pub-radius);font-size:.82rem;font-weight:600;margin-top:8px;}
#zoneBar.in {background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.3);}
#zoneBar.out{background:rgba(239,68,68,.08);color:#dc2626;border:1px solid rgba(239,68,68,.2);}

/* â”€â”€ Courier select â”€â”€ */
.co-box{border:2px solid var(--pub-border);border-radius:var(--pub-radius);padding:14px;margin-bottom:10px;}
.co-box label{font-size:.8rem;font-weight:700;color:var(--pub-muted);display:block;margin-bottom:6px;}
.co-sel{width:100%;padding:9px 12px;border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);background:var(--pub-bg);color:var(--pub-text);font-size:.9rem;font-family:inherit;box-sizing:border-box;}
.co-sel:focus{outline:none;border-color:var(--pub-primary);}
.co-info{display:none;gap:14px;margin-top:10px;font-size:.78rem;color:var(--pub-muted);flex-wrap:wrap;}
.co-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(3,135,78,.1);color:#059669;font-weight:700;border-radius:99px;padding:2px 9px;}

/* â”€â”€ Pickup card â”€â”€ */
.pk-card{border:2px solid var(--pub-border);border-radius:var(--pub-radius);padding:12px 14px;margin-bottom:10px;cursor:pointer;transition:border-color .15s,background .15s;}
.pk-card:hover,.pk-card.active{border-color:var(--pub-primary);background:rgba(3,135,78,.06);}
.pk-title{font-weight:700;font-size:.9rem;color:var(--pub-text);margin-bottom:3px;}
.pk-addr{font-size:.8rem;color:var(--pub-muted);}
.pk-row{display:flex;gap:14px;margin-top:5px;font-size:.77rem;color:var(--pub-muted);flex-wrap:wrap;}

/* â”€â”€ Spinner â”€â”€ */
.spin-wrap{display:flex;align-items:center;gap:10px;padding:10px;color:var(--pub-muted);font-size:.84rem;}
.spin{width:16px;height:16px;border:2px solid var(--pub-border);border-top-color:var(--pub-primary);border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0;}
@keyframes spin{to{transform:rotate(360deg);}}

/* â”€â”€ Driver link box â”€â”€ */
#navBox{display:none;margin-top:10px;padding:10px 13px;background:rgba(26,115,232,.06);border:1px solid rgba(26,115,232,.25);border-radius:var(--pub-radius);font-size:.78rem;}
#navBox a{color:#1558b0;word-break:break-all;}

/* â”€â”€ General â”€â”€ */
.pub-checkout-layout{display:grid;gap:24px;}
@media(min-width:900px){.pub-checkout-layout{grid-template-columns:1fr 360px;align-items:start;}}
.pub-checkout-card{background:var(--pub-bg);border:1px solid var(--pub-border);border-radius:var(--pub-radius);overflow:hidden;}
.pub-checkout-card-title{font-size:1rem;font-weight:700;margin:0;padding:12px 16px;border-bottom:1px solid var(--pub-border);color:var(--pub-text);}
.pub-form-grid{display:grid;gap:14px;padding:16px;}
@media(min-width:600px){.pub-form-grid{grid-template-columns:1fr 1fr;}}
.pub-form-field{display:grid;}
.pub-form-label{font-size:.82rem;font-weight:600;color:var(--pub-muted);margin-bottom:4px;}
.pub-form-input{width:100%;padding:9px 12px;border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);background:var(--pub-bg);color:var(--pub-text);font-size:.9rem;transition:border-color .18s;font-family:inherit;box-sizing:border-box;}
.pub-form-input:focus{outline:none;border-color:var(--pub-primary);box-shadow:0 0 0 2px rgba(3,135,78,.12);}
.pub-pm-grid{display:grid;gap:10px;padding:16px;}
@media(min-width:500px){.pub-pm-grid{grid-template-columns:1fr 1fr;}}
.pub-pm-option{border:2px solid var(--pub-border);border-radius:var(--pub-radius);padding:12px;cursor:pointer;transition:border-color .15s,background .15s;display:block;}
.pub-pm-option:has(input:checked){border-color:var(--pub-primary);background:rgba(3,135,78,.06);}
.pub-pm-option input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.pub-pm-label{display:flex;align-items:center;gap:10px;}
.pub-pm-icon{font-size:1.4rem;}
.pub-pm-name{font-size:.88rem;font-weight:600;color:var(--pub-text);}
.pub-checkout-summary{position:sticky;top:calc(var(--pub-header-h,64px)+16px);}
.nav-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#1a73e8;color:#fff;border-radius:var(--pub-radius);font-weight:700;font-size:.9rem;text-decoration:none;}
.nav-btn:hover{background:#1558b0;}
.chosen-box{display:none;margin-top:10px;padding:9px 12px;background:rgba(3,135,78,.08);border-radius:var(--pub-radius);font-size:.82rem;color:#059669;font-weight:600;}
</style>

<div class="pub-container" style="padding-top:28px;padding-bottom:48px;">

    <!-- Breadcrumb -->
    <nav style="font-size:.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a> â€؛
        <a href="/frontend/public/cart.php">ًں›’ <?= e(t('cart.title')) ?></a> â€؛
        <span>ًں’³ <?= e(t('checkout.title')) ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- âœ… Success -->
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:4rem;margin-bottom:16px;">âœ…</div>
        <h1 style="font-size:1.6rem;margin:0 0 10px;"><?= e(t('checkout.success_title')) ?></h1>
        <p style="color:var(--pub-muted);margin:0 0 6px;"><?= e(t('checkout.success_msg')) ?></p>
        <?php if ($orderNumber): ?>
        <p style="font-size:.9rem;color:var(--pub-muted);margin-bottom:24px;">
            <?= e(t('checkout.order_number')) ?>
            <strong style="color:var(--pub-primary);">#<?= e($orderNumber) ?></strong>
        </p>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary">ًںڈ  <?= e(t('common.home')) ?></a>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost">ًں›چï¸ڈ طھط³ظˆظ‚ ط§ظ„ظ…ط²ظٹط¯</a>
            <?php if ($driverNavLink): ?>
            <a href="<?= e($driverNavLink) ?>" target="_blank" class="nav-btn">
                ًں—؛ï¸ڈ ط±ط§ط¨ط· ط§ظ„طھظ†ظ‚ظ„ ظ„ظ„ط³ط§ط¦ظ‚
            </a>
            <?php endif; ?>
        </div>
    </div>
    <script>
    try {
        if (typeof window.pubClearScopedCart === 'function') {
            window.pubClearScopedCart(<?= (int)$entityId ?>);
        } else {
            localStorage.removeItem('pub_cart');
        }
    } catch (e) {}
    </script>

    <?php else: ?>
    <div style="display:inline-flex;align-items:center;gap:8px;margin:0 0 12px;padding:8px 12px;border-radius:999px;background:rgba(3,135,78,.08);color:var(--pub-primary);font-size:.82rem;font-weight:700;">
        <span aria-hidden="true">&#128205;</span>
        <span><?= e(t('entity.delivering_from', 'Delivering from')) ?>: <?= e($activeEntityName !== '' ? $activeEntityName : ('Entity #' . $entityId)) ?></span>
    </div>
    <h1 style="font-size:1.4rem;margin:0 0 24px;"><?= e(t('checkout.title')) ?></h1>

    <?php if ($checkoutError): ?>
    <div style="background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);color:#e74c3c;
                padding:12px 16px;border-radius:var(--pub-radius);margin-bottom:20px;">
        &#9888; <?= e($checkoutError) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="checkoutForm" class="pub-checkout-layout">

        <!-- â”€â”€ Hidden fields â”€â”€ -->
        <input type="hidden" name="cart_items_json"   id="cartItemsJson"  value="[]">
        <input type="hidden" name="delivery_lat"      id="hLat"           value="">
        <input type="hidden" name="delivery_lng"      id="hLng"           value="">
        <input type="hidden" name="delivery_mode"     id="hMode"          value="merchant">
        <input type="hidden" name="delivery_option_id" id="hOptId"        value="0">

        <!-- â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ LEFT COLUMN â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ -->
        <div>

            <!-- â”€â”€ Customer Info + Map â”€â”€ -->
            <div class="pub-checkout-card">
                <h2 class="pub-checkout-card-title">ًں‘¤ <?= e(t('checkout.customer_info')) ?></h2>
                <div class="pub-form-grid">

                    <div class="pub-form-field">
                        <label class="pub-form-label"><?= e(t('checkout.name')) ?> *</label>
                        <input type="text" name="customer_name" class="pub-form-input" required
                               value="<?= e($user['name'] ?? $user['username'] ?? '') ?>">
                    </div>

                    <div class="pub-form-field">
                        <label class="pub-form-label">ًں“‍ <?= e(t('checkout.phone')) ?> *</label>
                        <input type="tel" name="customer_phone" class="pub-form-input" required
                               placeholder="+966 5xx xxx xxxx">
                    </div>

                    <!-- Address â€” auto-fills from map pin -->
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                            <label class="pub-form-label" style="margin:0;">
                                ًں“چ <?= e(t('checkout.address')) ?>
                            </label>
                            <button type="button" id="btnGPS" class="pub-btn pub-btn--ghost"
                                    style="padding:4px 10px;font-size:.74rem;height:auto;">
                                ًںژ¯ ظ…ظˆظ‚ط¹ظٹ ط§ظ„ط­ط§ظ„ظٹ
                            </button>
                        </div>

                        <!-- Address textarea â€” populated automatically by reverse geocoding -->
                        <textarea name="delivery_address" id="addrField"
                                  class="pub-form-input" rows="2" style="resize:vertical;"
                                  placeholder="ظٹظڈظ…ظ„ط£ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ط¹ظ†ط¯ طھط­ط±ظٹظƒ ط§ظ„ط¯ط¨ظˆط³ ط£ظˆ ط¬ظ„ط¨ ط§ظ„ظ…ظˆظ‚ط¹â€¦"></textarea>

                        <p style="font-size:.7rem;color:var(--pub-muted);margin:4px 0 0;">
                            ًں“Œ ط§ط³ط­ط¨ ط§ظ„ط¯ط¨ظˆط³ ط£ظˆ ط§ظ†ظ‚ط± ط¹ظ„ظ‰ ط§ظ„ط®ط±ظٹط·ط© ظ„طھط­ط¯ظٹط¯ ظ…ظˆظ‚ط¹ظƒ ط¨ط¯ظ‚ط©
                        </p>

                        <!-- Zone status -->
                        <div id="zoneBar"></div>

                        <!-- Leaflet Map -->
                        <div id="deliveryMap"></div>

                        <!-- Driver nav link preview -->
                        <div id="navBox">
                            ًں—؛ï¸ڈ <strong style="color:#1a73e8;">ط±ط§ط¨ط· ط§ظ„طھظ†ظ‚ظ„ ظ„ظ„ط³ط§ط¦ظ‚:</strong>
                            <a id="navLink" href="#" target="_blank"></a>
                        </div>
                    </div>

                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">ًں“‌ <?= e(t('checkout.notes')) ?></label>
                        <textarea name="order_notes" class="pub-form-input" rows="2"
                                  style="resize:vertical;" placeholder="ظ…ظ„ط§ط­ط¸ط§طھ ط¥ط¶ط§ظپظٹط©â€¦"></textarea>
                    </div>
                </div>
            </div>

            <!-- â”€â”€ Delivery Method â”€â”€ -->
            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">ًںڑڑ ط·ط±ظٹظ‚ط© ط§ظ„طھظˆطµظٹظ„</h2>
                <div style="padding:16px;">

                    <!-- 3 tabs -->
                    <div class="dtab-bar">
                        <button type="button" class="dtab active" data-mode="merchant"
                                onclick="switchTab('merchant')">
                            <span class="dtab-icon">ًںڈھ</span>طھظˆطµظٹظ„ ط§ظ„طھط§ط¬ط±
                        </button>
                        <button type="button" class="dtab" data-mode="courier"
                                onclick="switchTab('courier')">
                            <span class="dtab-icon">ًںڑ›</span>ط´ط±ظƒط© ط´ط­ظ†
                        </button>
                        <button type="button" class="dtab" data-mode="pickup"
                                onclick="switchTab('pickup')">
                            <span class="dtab-icon">ًںڈ¬</span>ط§ط³طھظ„ط§ظ… ظ…ظ† ط§ظ„ظ…طھط¬ط±
                        </button>
                    </div>

                    <!-- Tab: Merchant Zones -->
                    <div id="tab-merchant">
                        <p id="merchantMsg" style="font-size:.84rem;color:var(--pub-muted);margin:0 0 8px;">
                            ط­ط¯ط¯ ظ…ظˆظ‚ط¹ظƒ ط¹ظ„ظ‰ ط§ظ„ط®ط±ظٹط·ط© ظ„ط¹ط±ط¶ ظ…ظ†ط§ط·ظ‚ ط§ظ„طھظˆطµظٹظ„ ط§ظ„ظ…طھط§ط­ط©.
                        </p>
                        <div id="merchantList"></div>
                    </div>

                    <!-- Tab: Courier / Shipping Company -->
                    <div id="tab-courier" style="display:none;">
                        <?php if (empty($couriers)): ?>
                        <p style="color:var(--pub-muted);font-size:.85rem;margin:0;">
                            ظ„ط§ طھظˆط¬ط¯ ط´ط±ظƒط§طھ ط´ط­ظ† ظ…ظپط¹ظ‘ظ„ط© ط­ط§ظ„ظٹط§ظ‹.
                        </p>
                        <?php else: ?>
                        <div class="co-box">
                            <label for="courierSel">ط§ط®طھط± ط´ط±ظƒط© ط§ظ„ط´ط­ظ† *</label>
                            <select id="courierSel" class="co-sel" onchange="onCourierPick()">
                                <option value="">â€” ط§ط®طھط± ط´ط±ظƒط© ط§ظ„ط´ط­ظ† â€”</option>
                                <?php foreach ($couriers as $co):
                                    $vIcon = match($co['vehicle_type'] ?? '') {
                                        'car'  => 'ًںڑ—', 'van' => 'ًںڑگ', 'truck' => 'ًںڑڑ', default => 'ًںڈچï¸ڈ'
                                    };
                                ?>
                                <option value="<?= (int)$co['id'] ?>"
                                        data-vehicle="<?= e($co['vehicle_type'] ?? 'bike') ?>"
                                        data-rating="<?= e($co['rating'] ?? '0.00') ?>"
                                        data-fee="25.00">
                                    <?= e($vIcon . ' ' . $co['provider_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- Selected courier details -->
                            <div class="co-info" id="courierInfo">
                                <span id="coRating"></span>
                                <span class="co-badge" id="coFee"></span>
                                <span>âڈ± ~120 ط¯ظ‚ظٹظ‚ط©</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Store Pickup -->
                    <div id="tab-pickup" style="display:none;">
                        <?php if (empty($pickupPoints)): ?>
                        <div style="padding:8px 0;color:var(--pub-muted);font-size:.85rem;">
                            ظ„ط§ طھظˆط¬ط¯ ظ†ظ‚ط§ط· ط§ط³طھظ„ط§ظ… ظ…ظڈط¶ط§ظپط© ط­ط§ظ„ظٹط§ظ‹.<br>
                            <small style="opacity:.6;">ط£ط¶ظپظ‡ط§ ظ…ظ†: ظ„ظˆط­ط© ط§ظ„طھط­ظƒظ… â†’ ط§ظ„ظ…طھط¬ط± â†’ ظ†ظ‚ط§ط· ط§ظ„ط§ط³طھظ„ط§ظ…</small>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pickupPoints as $pk): ?>
                        <div class="pk-card"
                             data-id="<?= (int)$pk['id'] ?>"
                             data-lat="<?= e($pk['latitude']  ?? '') ?>"
                             data-lng="<?= e($pk['longitude'] ?? '') ?>"
                             onclick="selectPickup(this)">
                            <div class="pk-title">ًںڈ¬ <?= e($pk['name']) ?></div>
                            <div class="pk-addr">ًں“چ <?= e($pk['address']) ?></div>
                            <div class="pk-row">
                                <?php if ($pk['working_hours']): ?>
                                <span>ًں•گ <?= e($pk['working_hours']) ?></span>
                                <?php endif; ?>
                                <?php if ($pk['phone']): ?>
                                <span>ًں“‍ <?= e($pk['phone']) ?></span>
                                <?php endif; ?>
                                <span class="co-badge">âœ… ظ…ط¬ط§ظ†ظٹ</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- â”€â”€ Payment â”€â”€ -->
            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">ًں’³ <?= e(t('checkout.payment')) ?></h2>
                <div class="pub-pm-grid">
                    <?php if (empty($entityPMs)): ?>
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code" value="cash" checked>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon">ًں’µ</span>
                            <span class="pub-pm-name">ط§ظ„ط¯ظپط¹ ط¹ظ†ط¯ ط§ظ„ط§ط³طھظ„ط§ظ…</span>
                        </span>
                    </label>
                    <?php else: foreach ($entityPMs as $i => $pm): ?>
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code"
                               value="<?= e($pm['code'] ?? 'pm_'.$i) ?>" <?= $i===0?'checked':'' ?>>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon"><?= e($pm['icon'] ?: 'ًں’³') ?></span>
                            <span class="pub-pm-name"><?= e($pm['name'] ?? $pm['code']) ?></span>
                        </span>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div><!-- /LEFT -->

        <!-- â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ RIGHT: Summary â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ -->
        <div class="pub-checkout-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">ًں“‹ <?= e(t('cart.order_summary')) ?></h2>

                <?php if (!empty($cartItems)): ?>
                <div style="margin-bottom:14px;display:grid;gap:8px;max-height:260px;overflow-y:auto;">
                    <?php foreach ($cartItems as $ci): ?>
                    <div style="display:flex;gap:10px;align-items:center;font-size:.85rem;">
                        <?php if (!empty($ci['image_url'])): ?>
                        <img src="<?= e($ci['image_url']) ?>" alt=""
                             style="width:38px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                        <?php endif; ?>
                        <div style="flex:1;overflow:hidden;">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;color:var(--pub-text);">
                                <?= e($ci['product_name']) ?>
                            </div>
                            <div style="color:var(--pub-muted);font-size:.78rem;">أ—<?= (int)$ci['quantity'] ?></div>
                        </div>
                        <strong style="flex-shrink:0;color:var(--pub-primary);">
                            <?= number_format((float)$ci['unit_price'] * (int)$ci['quantity'], 2) ?>
                        </strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr style="border:none;border-top:1px solid var(--pub-border);margin:10px 0;">
                <?php else: ?>
                <div id="jsItemsList" style="margin-bottom:14px;display:grid;gap:8px;min-height:50px;"></div>
                <?php endif; ?>

                <!-- Totals -->
                <div class="pub-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong id="sumSub"><?= number_format($cartTotal,2) ?> <?= e(t('common.currency')) ?></strong>
                </div>
                <div class="pub-summary-row" style="font-size:.84rem;">
                    <span>ًںڑڑ <?= e(t('cart.shipping')) ?></span>
                    <span id="sumShip" style="color:var(--pub-muted);">â€”</span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong id="sumTotal"><?= number_format($cartTotal,2) ?> <?= e(t('common.currency')) ?></strong>
                </div>

                <!-- Selected delivery label -->
                <div class="chosen-box" id="chosenBox"></div>

                <button type="submit" id="submitBtn"
                        class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:1rem;padding:13px;"
                        disabled>
                    âœ… <?= e(t('checkout.place_order')) ?>
                </button>
                <p id="submitHint" style="font-size:.74rem;text-align:center;color:var(--pub-danger,#e74c3c);margin-top:5px;">
                    ط§ط®طھط± ط·ط±ظٹظ‚ط© ط§ظ„طھظˆطµظٹظ„ ظ„ط¥طھظ…ط§ظ… ط§ظ„ط·ظ„ط¨
                </p>
                <p style="font-size:.73rem;text-align:center;color:var(--pub-muted);margin-top:3px;">
                    ًں”’ <?= e(t('checkout.secure_transaction')) ?>
                </p>
            </div>
        </div>

    </form>
    <?php endif; ?>
</div>

<script>
(function(){
'use strict';

/* â”€â”€ Config â”€â”€ */
var HAS_DB   = <?= $hasDbCart ? 'true' : 'false' ?>;
var CUR      = <?= json_encode(t('common.currency','SAR')) ?>;
var TID      = <?= (int)$tenantId ?>;
var EID      = <?= (int)$entityId ?>;
var LANG     = <?= json_encode($lang) ?>;
var PICKUPS  = <?= $pickupJson ?>;

/* â”€â”€ State â”€â”€ */
var _sub   = <?= (float)$cartTotal ?>;
var _ship  = 0;
var _mode  = 'merchant';
var _optId = 0;
var _map, _marker, _covLyr;
var _addrManual = false;

function $(id){ return document.getElementById(id); }
function fmt(n){ return parseFloat(n||0).toFixed(2); }

/* â”€â”€â”€ Summary â”€â”€â”€ */
function updateSummary(){
    var shipTxt = _ship > 0 ? fmt(_ship)+' '+CUR : (_mode==='pickup' ? 'âœ… ظ…ط¬ط§ظ†ظٹ' : 'â€”');
    $('sumShip').textContent  = shipTxt;
    $('sumTotal').textContent = fmt(_sub + (_mode==='pickup'?0:_ship))+' '+CUR;
}
function setDelivery(mode, optId, fee, label){
    _mode  = mode;  _optId = optId;  _ship = parseFloat(fee)||0;
    $('hMode').value   = mode;
    $('hOptId').value  = optId;
    updateSummary();
    var btn  = $('submitBtn'), hint = $('submitHint'), box = $('chosenBox');
    var ok   = optId > 0 || mode === 'pickup';
    btn.disabled = !ok;
    hint.style.display = ok ? 'none' : '';
    if (box){ box.style.display = ok && label ? '' : 'none'; if(label) box.textContent = label; }
}
function resetDelivery(){
    setDelivery(_mode, 0, 0, '');
}

/* â”€â”€â”€ Local Cart â”€â”€â”€ */
function localCart(){
    return (typeof window.pubLoadScopedCart === 'function')
        ? window.pubLoadScopedCart(EID)
        : [];
}
function renderLocal(){
    if (HAS_DB) return;
    var cart = localCart(), list = $('jsItemsList');
    if (!list) return;
    if (!cart.length){ list.innerHTML='<p style="color:var(--pub-muted);font-size:.85rem;">ط§ظ„ط³ظ„ط© ظپط§ط±ط؛ط©</p>'; $('submitBtn').disabled=true; return; }
    var sub=0, html='';
    cart.forEach(function(it){
        var p=parseFloat(it.price||0), q=parseInt(it.qty||1,10); sub+=p*q;
        html+='<div style="display:flex;justify-content:space-between;gap:8px;font-size:.84rem;">'
            +'<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
            +it.name+' <span style="color:var(--pub-muted);">أ—'+q+'</span></span>'
            +'<strong>'+fmt(p*q)+' '+CUR+'</strong></div>';
    });
    list.innerHTML = html;
    _sub = sub;
    $('sumSub').textContent = fmt(sub)+' '+CUR;
    $('cartItemsJson').value = JSON.stringify(cart);
    updateSummary();
}

/* â”€â”€â”€ Map â”€â”€â”€ */
function initMap(){
    if (!$('deliveryMap')) return;
    _map    = L.map('deliveryMap').setView([24.7136,46.6753],11);
    _covLyr = L.featureGroup().addTo(_map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'آ© OpenStreetMap',maxZoom:19}).addTo(_map);

    _marker = L.marker([24.7136,46.6753],{draggable:true}).addTo(_map);
    _marker.on('dragend', function(){ var p=_marker.getLatLng(); applyCoords(p.lat,p.lng); });
    _map.on('click', function(e){ _marker.setLatLng(e.latlng); applyCoords(e.latlng.lat,e.latlng.lng); });

    // Pickup markers
    PICKUPS.forEach(function(pk){
        if (!pk.latitude||!pk.longitude) return;
        L.marker([+pk.latitude,+pk.longitude],{icon:L.divIcon({html:'ًںڈ¬',iconSize:[28,28],className:'',iconAnchor:[14,14]})})
         .bindPopup('<strong>'+pk.name+'</strong><br>'+pk.address+'<br>ظ…ط¬ط§ظ†ظٹ âœ…')
         .addTo(_map);
    });

    // GPS button
    $('btnGPS').onclick = gpsLocate;

    // Detect manual address input
    var af = $('addrField');
    if (af) af.addEventListener('input', function(){ _addrManual = af.value.trim().length > 0; });

    loadCoverage(function(){ gpsLocate(); });
}

function gpsLocate(){
    var btn = $('btnGPS');
    if (btn){ btn.textContent='âŒ›'; btn.disabled=true; }
    if (!navigator.geolocation){ restoreGPS(btn); return; }
    navigator.geolocation.getCurrentPosition(
        function(pos){
            _map.setView([pos.coords.latitude,pos.coords.longitude],15);
            _marker.setLatLng([pos.coords.latitude,pos.coords.longitude]);
            applyCoords(pos.coords.latitude,pos.coords.longitude);
            restoreGPS(btn);
        },
        function(){ restoreGPS(btn); },
        {enableHighAccuracy:true,timeout:7000}
    );
}
function restoreGPS(btn){ if(btn){btn.textContent='ًںژ¯ ظ…ظˆظ‚ط¹ظٹ ط§ظ„ط­ط§ظ„ظٹ';btn.disabled=false;} }

/* Called every time pin moves or GPS triggered */
function applyCoords(lat,lng){
    $('hLat').value = lat;
    $('hLng').value = lng;

    // Always reverse-geocode (overwrite only if user hasn't manually edited)
    reverseGeocode(lat,lng);

    // Driver nav link
    var url='https://www.google.com/maps/dir/?api=1&destination='+lat+','+lng;
    var box=$('navBox'), lnk=$('navLink');
    if(box&&lnk){ box.style.display=''; lnk.href=url; lnk.textContent=url; }

    // Refresh merchant zones only when on merchant tab
    if (_mode==='merchant') fetchMerchantZones(lat,lng);
}

/* Reverse geocode â€” always updates address field (unless manually edited AND non-empty) */
function reverseGeocode(lat,lng){
    var af=$('addrField');
    if (!af) return;
    // Only skip if user manually typed something
    if (_addrManual && af.value.trim()) return;
    af.placeholder='âŒ› ط¬ط§ط±ظٹ طھط­ط¯ظٹط¯ ط§ظ„ط¹ظ†ظˆط§ظ†...';
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng+'&zoom=18',{
        headers:{'Accept-Language':LANG}
    }).then(function(r){return r.json();})
      .then(function(d){
          if(d&&d.display_name){
              af.value=d.display_name;
              af.placeholder='';
              _addrManual=false; // reset â€” pin now controls address
          }
      }).catch(function(){});
}

/* â”€â”€â”€ Coverage Zones (map overlay) â”€â”€â”€ */
function loadCoverage(cb){
    fetch('/api/public/delivery?tenant_id='+TID+'&entity_id='+EID+'&coverage=1&_t='+Date.now())
    .then(function(r){return r.json();})
    .then(function(resp){
        if(!resp.success||!resp.data||!resp.data.zones){if(cb)cb();return;}
        _covLyr.clearLayers();
        resp.data.zones.forEach(function(z){
            var p=null; try{if(z.value)p=JSON.parse(z.value);}catch(e){}
            var t=(z.type||(p&&p.type)||'').toLowerCase();
            var c=z.is_merchant?'#10b981':'#3b82f6';
            var st={color:c,fillColor:c,fillOpacity:.13,weight:2};
            var lyr=null;
            if(t==='polygon'&&p&&p.coordinates){
                var ll=(p.coordinates[0]||[]).map(function(x){return[+x[1],+x[0]];});
                if(ll.length) lyr=L.polygon(ll,st);
            } else if(['radius','circle','district','city'].indexOf(t)>=0){
                var clat=+((p&&p.center&&p.center[0])||z.center_lat);
                var clng=+((p&&p.center&&p.center[1])||z.center_lng);
                var rad=+((p&&p.radius)||(z.radius_km*1000)||5000);
                if(!isNaN(clat)&&!isNaN(clng)) lyr=L.circle([clat,clng],Object.assign({radius:rad},st));
            } else if(t==='rectangle'&&p&&p.bounds){
                lyr=L.rectangle(p.bounds,st);
            }
            if(lyr){
                lyr.addTo(_covLyr);
                var fee=parseFloat(z.delivery_fee||0).toFixed(2);
                lyr.bindPopup('<strong>'+(z.name||'ظ…ظ†ط·ظ‚ط©')+'</strong><br>'
                    +(z.is_merchant?'ًںڈھ طھظˆطµظٹظ„ ط§ظ„طھط§ط¬ط±':'ًںڑ› ط´ط±ظƒط© ط´ط­ظ†')
                    +'<br>ط±ط³ظˆظ…: <b>'+fee+' '+CUR+'</b>');
            }
        });
        if(_covLyr.getLayers().length>0){
            var b=_covLyr.getBounds();
            if(b.isValid()) _map.fitBounds(b,{padding:[40,40]});
        }
        if(cb)cb();
    }).catch(function(){if(cb)cb();});
}

/* â”€â”€â”€ Merchant Zones fetch â”€â”€â”€ */
function fetchMerchantZones(lat,lng){
    var msg=$('merchantMsg'), list=$('merchantList'), bar=$('zoneBar');
    if(list) list.innerHTML='<div class="spin-wrap"><div class="spin"></div> ط¬ط§ط±ظٹ ط­ط³ط§ط¨ ظ…ظ†ط§ط·ظ‚ ط§ظ„طھظˆطµظٹظ„â€¦</div>';
    if(msg)  msg.style.display='none';
    resetDelivery();

    fetch('/api/public/delivery?tenant_id='+TID+'&entity_id='+EID+'&lat='+lat+'&lng='+lng+'&_t='+Date.now())
    .then(function(r){return r.json();})
    .then(function(resp){
        var opts=(resp.success&&resp.data&&resp.data.options)?resp.data.options.filter(function(o){return o.is_merchant;}):[];
        if(!opts.length){
            if(bar){bar.className='out';bar.style.display='flex';bar.textContent='â‌Œ ط®ط§ط±ط¬ ظ†ط·ط§ظ‚ ط§ظ„طھظˆطµظٹظ„ ط§ظ„ظ…ط¨ط§ط´ط± â€” ط¬ط±ظ‘ط¨ ط´ط±ظƒط© ط´ط­ظ†';}
            if(list) list.innerHTML='';
            return;
        }
        if(bar){bar.className='in';bar.style.display='flex';bar.textContent='âœ… ظ…ظˆظ‚ط¹ظƒ ط¶ظ…ظ† ظ…ظ†ط·ظ‚ط© ط§ظ„طھظˆطµظٹظ„';}
        var html='';
        opts.forEach(function(o){
            html+='<div class="dz-card" data-id="'+o.id+'" data-fee="'+o.fee+'" onclick="pickMerchant(this)">'
                +'<div class="dz-icon">ًںڈھ</div>'
                +'<div class="dz-body"><div class="dz-name">'+o.name+'</div>'
                +'<div class="dz-meta">âڈ± ~'+(o.estimated||45)+' ط¯ظ‚ظٹظ‚ط©</div></div>'
                +'<div class="dz-fee">'+parseFloat(o.fee).toFixed(2)+' '+CUR+'</div>'
                +'</div>';
        });
        if(list){ list.innerHTML=html; var first=list.querySelector('.dz-card'); if(first) pickMerchant(first); }
    }).catch(function(){
        if(list) list.innerHTML='<p style="color:var(--pub-muted);font-size:.84rem;">âڑ ï¸ڈ طھط¹ط°ظ‘ط± طھط­ظ…ظٹظ„ ط®ظٹط§ط±ط§طھ ط§ظ„طھظˆطµظٹظ„</p>';
    });
}

/* â”€â”€â”€ Selection handlers â”€â”€â”€ */
window.pickMerchant = function(card){
    document.querySelectorAll('#merchantList .dz-card').forEach(function(c){c.classList.remove('active');});
    card.classList.add('active');
    var fee=parseFloat(card.dataset.fee||0);
    setDelivery('merchant', card.dataset.id, fee, 'ًںڈھ '+card.querySelector('.dz-name').textContent+' آ· '+fmt(fee)+' '+CUR);
};

window.onCourierPick = function(){
    var sel=$('courierSel'), opt=sel?sel.options[sel.selectedIndex]:null;
    var info=$('courierInfo');
    if(!opt||!opt.value){ resetDelivery(); if(info)info.style.display='none'; return; }
    var fee=parseFloat(opt.dataset.fee||25);
    var rating=parseFloat(opt.dataset.rating||0);
    setDelivery('courier', opt.value, fee, 'ًںڑ› '+opt.text+' آ· '+fmt(fee)+' '+CUR);
    if(info){
        info.style.display='flex';
        $('coRating').textContent='â­گ '+rating.toFixed(1);
        $('coFee').textContent='ًں’° '+fmt(fee)+' '+CUR;
    }
};

window.selectPickup = function(card){
    document.querySelectorAll('.pk-card').forEach(function(c){c.classList.remove('active');});
    card.classList.add('active');
    setDelivery('pickup', card.dataset.id, 0, 'ًںڈ¬ '+card.querySelector('.pk-title').textContent+' آ· ظ…ط¬ط§ظ†ظٹ');
    if(card.dataset.lat&&card.dataset.lng&&_map){
        _map.setView([+card.dataset.lat,+card.dataset.lng],15);
    }
};

/* â”€â”€â”€ Tab switching â”€â”€â”€ */
window.switchTab = function(mode){
    _mode=mode; $('hMode').value=mode;
    document.querySelectorAll('.dtab').forEach(function(b){b.classList.toggle('active',b.dataset.mode===mode);});
    ['merchant','courier','pickup'].forEach(function(m){ $('tab-'+m).style.display=m===mode?'':'none'; });
    resetDelivery();
    var bar=$('zoneBar'); if(bar&&mode!=='merchant'){bar.style.display='none';}

    if(mode==='merchant'){
        var lat=$('hLat').value, lng=$('hLng').value;
        if(lat&&lng) fetchMerchantZones(+lat,+lng);
    }
    if(mode==='pickup'){
        var cards=document.querySelectorAll('.pk-card');
        if(cards.length===1) selectPickup(cards[0]);
        // Pickup = no location required
        if(!$('hLat').value) setDelivery('pickup',0,0,'');
    }
    if(mode==='courier'){
        var cs=$('courierSel'); if(cs){cs.selectedIndex=0; onCourierPick();}
    }
};

/* â”€â”€â”€ Init â”€â”€â”€ */
document.addEventListener('DOMContentLoaded',function(){
    renderLocal();
    initMap();
    var form=document.getElementById('checkoutForm');
    if(form) form.addEventListener('submit',function(ev){
        var lat=$('hLat').value, lng=$('hLng').value;
        // Pickup doesn't require map location
        if(_mode!=='pickup'&&(!lat||!lng)){
            ev.preventDefault();
            alert('ظٹط±ط¬ظ‰ طھط­ط¯ظٹط¯ ظ…ظˆظ‚ط¹ظƒ ط¹ظ„ظ‰ ط§ظ„ط®ط±ظٹط·ط© ط£ظˆظ„ط§ظ‹');
            return;
        }
        if(!HAS_DB) $('cartItemsJson').value=JSON.stringify(localCart());
    });
});

}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>

