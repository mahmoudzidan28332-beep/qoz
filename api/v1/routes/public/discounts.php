<?php
declare(strict_types=1);
/**
 * Public API sub-route: discounts
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 *
 * GET /api/public/discounts
 *   ?tenant_id=X        — required for tenant scoping
 *   &entity_id=Y        — optional: filter by a single merchant/entity
 *   &type=percentage    — optional: filter by discount type
 *   &active_only=0      — optional: set to 0 to return all statuses
 *   &page=1             — pagination
 *   &per=20             — items per page (max 100, default 20)
 *   &lang=ar            — translation language
 */

if ($first === 'discounts') {

    if (!$pdo instanceof PDO) {
        ResponseFormatter::success(['ok' => true, 'data' => [], 'meta' => ['total' => 0]]);
        exit;
    }

    // Optional filters
    $entityId   = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0 ? (int)$_GET['entity_id'] : null;
    $dType      = trim($_GET['type'] ?? '');
    $activeOnly = ($_GET['active_only'] ?? '1') !== '0'; // default: only active+currently-valid

    // Build WHERE
    $where  = [];
    $params = [];

    // Tenant scoping: discounts apply if they belong to a merchant in this tenant
    // or if they are scoped to a category/product in this tenant.
    if ($tenantId) {
        $where[]  = "(d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?) 
                     OR EXISTS (SELECT 1 FROM discount_scopes ds_t 
                                LEFT JOIN categories c_t ON ds_t.scope_type = 'category' AND c_t.id = ds_t.scope_id
                                LEFT JOIN products p_t ON ds_t.scope_type = 'product' AND p_t.id = ds_t.scope_id
                                WHERE ds_t.discount_id = d.id 
                                  AND (c_t.tenant_id = ? OR p_t.tenant_id = ?)))";
        $params[] = $tenantId;
        $params[] = $tenantId;
        $params[] = $tenantId;
    }

    // Per-merchant filter
    if ($entityId) {
        $where[]  = 'd.entity_id = ?';
        $params[] = $entityId;
    }

    if ($activeOnly) {
        $where[]  = "d.status = 'active'";
        $where[]  = '(d.starts_at IS NULL OR d.starts_at <= NOW())';
        $where[]  = '(d.ends_at IS NULL OR d.ends_at >= NOW())';
    }

    // expires_today=1 filter from homepage
    if (($_GET['expires_today'] ?? '0') === '1') {
        $where[] = 'd.ends_at IS NOT NULL AND d.ends_at >= NOW() AND d.ends_at <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
    }

    if ($dType !== '') {
        $where[]  = 'd.type = ?';
        $params[] = $dType;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $total = 0;
    try {
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM discounts d $whereSQL");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[public/discounts] count error: ' . $e->getMessage());
    }

    // Pagination
    $perPage = min(100, max(1, (int)($_GET['per'] ?? $_GET['limit'] ?? 20)));
    $pg      = max(1, (int)($_GET['page'] ?? 1));
    $off     = ($pg - 1) * $perPage;

    // Main query — ordered by updated_at DESC (newest discount activity first)
    $rows = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.entity_id, d.code, d.type, d.auto_apply, d.priority,
                    d.is_stackable, d.currency_code, d.status,
                    d.max_redemptions, d.max_redemptions_per_user, d.current_redemptions,
                    d.starts_at, d.ends_at, d.created_at, d.updated_at,
                    COALESCE(dt.name, d.code, d.type) AS title,
                    dt.description, dt.terms_conditions, dt.marketing_badge,
                    e.id   AS merchant_id,
                    COALESCE(et.name, e.slug) AS merchant_name
             FROM discounts d
             LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
             LEFT JOIN entities e ON e.id = d.entity_id
             LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
             $whereSQL
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT $perPage OFFSET $off"
        );
        $stmt->execute(array_merge([$lang, $lang], $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[public/discounts] fetch error: ' . $e->getMessage());
    }

    // Enrich rows with primary action (discount value & type from discount_actions)
    if (!empty($rows)) {
        $ids = array_column($rows, 'id');
        $phs = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            // 1. Actions logic
            $aStmt = $pdo->prepare(
                "SELECT discount_id, action_type, action_value
                 FROM discount_actions
                 WHERE discount_id IN ($phs)
                 ORDER BY id ASC"
            );
            $aStmt->execute($ids);
            $actions = [];
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if (!isset($actions[$a['discount_id']])) $actions[$a['discount_id']] = $a;
            }

            // 2. Scopes logic (resolve names for "Applies to" label)
            $sStmt = $pdo->prepare(
                "SELECT ds.discount_id, ds.scope_type, 
                        COALESCE(pt.name, p.slug) as product_name,
                        COALESCE(ct.name, c.slug) as category_name,
                        COALESCE(et.name, e.slug) as entity_name
                 FROM discount_scopes ds
                 LEFT JOIN products p ON ds.scope_type = 'product' AND p.id = ds.scope_id
                 LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                 LEFT JOIN categories c ON ds.scope_type = 'category' AND c.id = ds.scope_id
                 LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
                 LEFT JOIN entities e ON ds.scope_type = 'entity' AND e.id = ds.scope_id
                 LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
                 WHERE ds.discount_id IN ($phs)"
            );
            $sStmt->execute(array_merge([$lang, $lang, $lang], $ids));
            $scopeMap = [];
            foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $did = $s['discount_id'];
                $txt = match($s['scope_type']) {
                    'product'  => $s['product_name'],
                    'category' => $s['category_name'],
                    'entity'   => $s['entity_name'],
                    default    => null
                };
                if ($txt) $scopeMap[$did][] = $txt;
            }

            foreach ($rows as &$row) {
                $act = $actions[$row['id']] ?? null;
                $row['action_type']    = $act['action_type']  ?? null;
                $row['action_value']   = $act['action_value'] ?? null;
                $row['discount_label'] = null;
                if ($act) {
                    $v = $act['action_value'] ?? '';
                    $row['discount_label'] = match (true) {
                        in_array($act['action_type'], ['percentage_discount','percent_discount','percentage'], true)
                            => number_format((float)$v, 0) . '%',
                        in_array($act['action_type'], ['fixed_discount','fixed_amount','fixed'], true)
                            => number_format((float)$v, 2) . ' ' . trim($row['currency_code'] ?? ''),
                        $act['action_type'] === 'free_shipping' => 'free_shipping',
                        default => (string)$v,
                    };
                }
                
                $myScopes = $scopeMap[$row['id']] ?? [];
                $row['scope_summary'] = !empty($myScopes) ? implode(', ', array_unique($myScopes)) : null;
            }
            unset($row);
        } catch (Throwable $e) {
            error_log('[public/discounts] enrichment error: ' . $e->getMessage());
        }
    }

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => [
            'total' => $total,
            'page'  => $pg,
            'per'   => $perPage,
            'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Brands (public listing)
 * GET /api/public/brands?tenant_id=X&lang=Y[&is_featured=1][&per=N][&page=N]
 * ----------------------------------------------------- */
