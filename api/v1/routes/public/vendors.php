<?php
declare(strict_types=1);
/**
 * Public API sub-route: vendors
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'vendors') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    if ($id) {
        // Multi-tenant safety: scope vendor lookup by tenant_id to prevent cross-tenant data leakage
        if ($tenantId) {
            $row = $pdoOne('SELECT id, store_name AS name, is_active FROM entities WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, $tenantId]);
        } else {
            $row = $pdoOne('SELECT id, store_name AS name, is_active FROM entities WHERE id = ? LIMIT 1', [$id]);
        }
        if ($row) ResponseFormatter::success(['ok' => true, 'vendor' => $row]);
        else      ResponseFormatter::notFound('Vendor not found');
    } else {
        // Multi-tenant safety: scope vendor list by tenant_id
        if ($tenantId) {
            $rows = $pdoList('SELECT id, store_name AS name, is_active FROM entities WHERE tenant_id = ? LIMIT ? OFFSET ?', [$tenantId, $per, $offset]);
        } else {
            $rows = $pdoList('SELECT id, store_name AS name, is_active FROM entities LIMIT ? OFFSET ?', [$per, $offset]);
        }
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: Entity Types (public list — used for filter dropdown)
 * GET /api/public/entity_types
 * ----------------------------------------------------- */