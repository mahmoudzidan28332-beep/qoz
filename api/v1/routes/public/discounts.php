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

    $discountsRepo = new PdoDiscountsRepository($pdo);

    // Optional filters
    $entityId   = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0 ? (int)$_GET['entity_id'] : null;
    $dType      = trim($_GET['type'] ?? '');
    $activeOnly = ($_GET['active_only'] ?? '1') !== '0'; // default: only active+currently-valid
    $expiresToday = ($_GET['expires_today'] ?? '0') === '1';

    // Count total
    $total = 0;
    try {
        $total = $discountsRepo->publicCount($tenantId, $entityId, $dType, $activeOnly, $expiresToday);
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
        $rows = $discountsRepo->publicList($tenantId, $entityId, $dType, $activeOnly, $expiresToday, $lang, $perPage, $off);
    } catch (Throwable $e) {
        error_log('[public/discounts] fetch error: ' . $e->getMessage());
    }

    // Enrich rows with primary action (discount value & type from discount_actions)
    if (!empty($rows)) {
        $ids = array_column($rows, 'id');
        
        try {
            // 1. Actions logic
            $actions = [];
            foreach ($discountsRepo->getActionsForIds($ids) as $a) {
                if (!isset($actions[$a['discount_id']])) $actions[$a['discount_id']] = $a;
            }

            // 2. Scopes logic (resolve names for "Applies to" label)
            $scopeMap = [];
            foreach ($discountsRepo->getScopesForIds($ids, $lang) as $s) {
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