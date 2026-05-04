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
 *   &expires_today=0    — optional: set to 1 to show only discounts expiring today
 *   &page=1             — pagination
 *   &per=20             — items per page (max 100, default 20)
 *   &lang=ar            — translation language
 */

if ($first === 'discounts') {

    if (!$pdo instanceof PDO) {
        ResponseFormatter::success(['ok' => true, 'data' => [], 'meta' => ['total' => 0]]);
        exit;
    }

    require_once __DIR__ . '/../../models/discounts/repositories/PdoDiscountsRepository.php';
    require_once __DIR__ . '/../../models/discounts/repositories/PdoDiscountExclusionsRepository.php';
    require_once __DIR__ . '/../../models/discounts/services/DiscountsService.php';

    $discountsRepo = new PdoDiscountsRepository($pdo);
    $discountsService = new DiscountsService($pdo);

    // Optional filters
    $entityId   = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0 ? (int)$_GET['entity_id'] : null;
    $dType      = trim($_GET['type'] ?? '');
    $activeOnly = ($_GET['active_only'] ?? '1') !== '0';
    $expiresToday = ($_GET['expires_today'] ?? '0') === '1';

    // Security: Verify entity belongs to tenant
    if ($entityId && class_exists('MultiTenantValidator')) {
        if (!MultiTenantValidator::checkOwnership($pdo, 'entities', $entityId, $tenantId)) {
            ResponseFormatter::error('Invalid entity_id for this tenant', 403);
            exit;
        }
    }

    // Count total
    $total = 0;
    try {
        $total = $discountsRepo->publicCount($tenantId, $entityId, $dType, $activeOnly, $expiresToday);
    } catch (Exception $e) {
        error_log('[public/discounts] count error: ' . $e->getMessage());
    }

    // Pagination
    $perPage = min(100, max(1, (int)($_GET['per'] ?? $_GET['limit'] ?? 20)));
    $pg      = max(1, (int)($_GET['page'] ?? 1));
    $off     = ($pg - 1) * $perPage;

    // Main query
    $rows = [];
    try {
        $rows = $discountsRepo->publicList($tenantId, $entityId, $dType, $activeOnly, $expiresToday, $lang, $perPage, $off);
    } catch (Exception $e) {
        error_log('[public/discounts] fetch error: ' . $e->getMessage());
    }

    // Enrich rows with actions and scopes
    if (!empty($rows)) {
        $ids = array_column($rows, 'id');
        
        try {
            // Actions
            $actions = [];
            foreach ($discountsRepo->getActionsForIds($ids) as $a) {
                if (!isset($actions[$a['discount_id']])) $actions[$a['discount_id']] = $a;
            }

            // Scopes
            $scopeMap = [];
            foreach ($discountsRepo->getScopesForIds($ids, $lang) as $s) {
                $did = $s['discount_id'];
                $txt = null;
                switch ($s['scope_type']) {
                    case 'product':
                        $txt = $s['product_name'] ?? null;
                        break;
                    case 'category':
                        $txt = $s['category_name'] ?? null;
                        break;
                    case 'entity':
                        $txt = $s['entity_name'] ?? null;
                        break;
                }
                if ($txt) $scopeMap[$did][] = $txt;
            }

            foreach ($rows as &$row) {
                $act = $actions[$row['id']] ?? null;
                $row['action_type']    = $act['action_type']  ?? null;
                $row['action_value']   = $act['action_value'] ?? null;
                $row['discount_label'] = null;
                if ($act) {
                    $v = $act['action_value'] ?? '';
                    $at = $act['action_type'] ?? '';
                    if (in_array($at, ['percentage_discount', 'percent_discount', 'percentage'], true)) {
                        $row['discount_label'] = number_format((float)$v, 0) . '%';
                    } elseif (in_array($at, ['fixed_discount', 'fixed_amount', 'fixed'], true)) {
                        $row['discount_label'] = number_format((float)$v, 2) . ' ' . trim($row['currency_code'] ?? '');
                    } elseif ($at === 'free_shipping') {
                        $row['discount_label'] = 'free_shipping';
                    } else {
                        $row['discount_label'] = (string)$v;
                    }
                }
                
                $myScopes = $scopeMap[$row['id']] ?? [];
                $row['scope_summary'] = !empty($myScopes) ? implode(', ', array_unique($myScopes)) : null;
            }
            unset($row);
        } catch (Exception $e) {
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