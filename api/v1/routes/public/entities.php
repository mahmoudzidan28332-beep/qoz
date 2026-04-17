<?php
declare(strict_types=1);

/**
 * Public API — entities (Production Ready)
 */

if ($first !== 'entities') {
    return;
}

/* ======================================================
 * Helpers
 * ==================================================== */
function _int($v, int $default = 0): int {
    return is_numeric($v) ? (int)$v : $default;
}

$page = max(1, _int($_GET['page'] ?? 1, 1));
$per  = max(1, min(50, _int($_GET['per'] ?? 12, 12)));
$offset = ($page - 1) * $per;

/* ======================================================
 * Single Entity
 * ==================================================== */
$id = isset($segments[1]) && ctype_digit((string)$segments[1])
    ? (int)$segments[1]
    : _int($_GET['id'] ?? 0);

if ($id > 0) {

    $row = $pdoOne(
        "SELECT 
            e.id,
            e.store_name,
            e.slug,
            e.vendor_type,
            e.store_type,
            e.is_verified,
            e.phone,
            e.mobile,
            e.email,
            e.website_url AS website,
            e.status,
            e.tenant_id,
            e.joined_at,
            COALESCE(et.store_name, e.store_name) AS display_name,
            et.description,
            (
                SELECT i.url 
                FROM images i 
                JOIN image_types t ON t.id = i.image_type_id
                WHERE i.owner_id = e.id AND t.name = 'entity_logo'
                ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC 
                LIMIT 1
            ) AS logo_url
         FROM entities e
         LEFT JOIN entity_translations et
           ON et.entity_id = e.id AND et.language_code = ?
         WHERE e.id = ?
           AND e.status NOT IN ('suspended','rejected')
         LIMIT 1",
        [$lang, $id]
    );

    if (!$row) {
        ResponseFormatter::notFound('Entity not found');
    }

    ResponseFormatter::success([
        'ok' => true,
        'entity' => $row
    ]);
    exit;
}

/* ======================================================
 * Listing
 * ==================================================== */

$where  = ["e.status NOT IN ('suspended','rejected')"];
$params = [];

/* ---------- Tenant ---------- */
if (!empty($tenantId) && $tenantId > 0) {
    $where[]  = 'e.tenant_id = ?';
    $params[] = $tenantId;
}

/* ---------- Verified ---------- */
if (!empty($_GET['is_verified']) || !empty($_GET['is_featured'])) {
    $where[] = 'e.is_verified = 1';
}

/* ---------- Vendor Type ---------- */
if (!empty($_GET['vendor_type'])) {
    $allowed = ['product_seller','service_provider','both'];
    if (in_array($_GET['vendor_type'], $allowed, true)) {
        $where[]  = 'e.vendor_type = ?';
        $params[] = $_GET['vendor_type'];
    }
}

/* ---------- Store Type ---------- */
if (!empty($_GET['store_type'])) {
    $allowed = ['individual','company','brand'];
    if (in_array($_GET['store_type'], $allowed, true)) {
        $where[]  = 'e.store_type = ?';
        $params[] = $_GET['store_type'];
    }
}

/* ---------- Search ---------- */
if (!empty($_GET['q'])) {
    $q = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], trim($_GET['q'])) . '%';
    $where[] = "(e.store_name LIKE ? OR e.email LIKE ?)";
    $params[] = $q;
    $params[] = $q;
}

$sqlWhere = 'WHERE ' . implode(' AND ', $where);

/* ======================================================
 * TOTAL (100% مطابق)
 * ==================================================== */
$total = (int)$pdoCount(
    "SELECT COUNT(*) FROM entities e $sqlWhere",
    $params
);

/* ======================================================
 * DATA (بدون JOIN ثقيل)
 * ==================================================== */
$rows = $pdoList(
    "SELECT
        e.id,
        e.store_name,
        e.slug,
        e.vendor_type,
        e.store_type,
        e.is_verified,
        e.tenant_id,
        e.joined_at,

        (
            SELECT i.url
            FROM images i
            JOIN image_types t ON t.id = i.image_type_id
            WHERE i.owner_id = e.id AND t.name = 'entity_logo'
            ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC
            LIMIT 1
        ) AS logo_url

     FROM entities e
     $sqlWhere
     ORDER BY e.is_verified DESC, e.joined_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$per, $offset])
);

/* ======================================================
 * Safety fallback (Production critical)
 * ==================================================== */
if ($total > 0 && empty($rows)) {

    // إعادة محاولة بدون OFFSET (fallback ذكي)
    $rows = $pdoList(
        "SELECT
            e.id,
            e.store_name,
            e.slug,
            e.vendor_type,
            e.store_type,
            e.is_verified,
            e.tenant_id,
            e.joined_at
         FROM entities e
         $sqlWhere
         ORDER BY e.is_verified DESC
         LIMIT 12",
        $params
    );
}

/* ======================================================
 * Response
 * ==================================================== */
ResponseFormatter::success([
    'ok'   => true,
    'data' => $rows,
    'meta' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per,
        'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
    ],
]);
exit;