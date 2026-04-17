<?php
declare(strict_types=1);
/**
 * Public API sub-route: search_suggest
 * GET /api/public/search_suggest?q=...&context=all&lang=ar&tenant_id=1
 *   OR ?popular=1&lang=ar&tenant_id=1  → returns popular/trending queries
 *
 * Returns grouped live-search suggestions:
 * {
 *   "products":   [{id, name, url, icon}, …],
 *   "categories": […],
 *   "entities":   […],
 *   "jobs":       […]
 * }
 *
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $lang, $tenantId
 */

/* -------------------------------------------------------
 * Resolve current user_id from session (best-effort)
 * Follows the same pattern as events.php session fallback.
 * ----------------------------------------------------- */
$currentUserId = null;
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('APP_SESSID');
        session_save_path(sys_get_temp_dir());
        @session_start(['cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    $sessUser = $_SESSION['user'] ?? null;
    if ($sessUser && isset($sessUser['id']) && (int)$sessUser['id'] > 0) {
        $currentUserId = (int)$sessUser['id'];
    }
} catch (Throwable $e) {
    // Session unavailable — treat as guest
}

// entity_id from request (e.g. when user is browsing an entity page)
$searchEntityId = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0
    ? (int)$_GET['entity_id']
    : null;

// Instantiate repositories
$searchLogsRepo    = null;
$searchSuggestRepo = null;
if ($pdo instanceof PDO) {
    require_once dirname(__DIR__, 2) . '/models/search_logs/repositories/PdoSearchLogsRepository.php';
    require_once dirname(__DIR__, 2) . '/models/search_logs/repositories/PdoSearchSuggestRepository.php';
    $searchLogsRepo    = new PdoSearchLogsRepository($pdo);
    $searchSuggestRepo = new PdoSearchSuggestRepository($pdo);
}

/* -------------------------------------------------------
 * Helper: upsert a query into search_logs (best-effort)
 * Logs both the global aggregated row (user_id=NULL) and,
 * when a user is logged in, a per-user row so we can later
 * identify which users searched for what (for targeted offers).
 * ----------------------------------------------------- */
$trackQuery = function (string $query, ?int $entityIdOverride = null) use ($searchLogsRepo, $lang, $tenantId, $currentUserId, $searchEntityId): void {
    if (!$searchLogsRepo || strlen($query) < 2) return;
    // Prefer the entity_id detected from search results; fall back to context entity_id from request.
    $eid = $entityIdOverride ?? $searchEntityId;
    try {
        // Global/guest row (user_id=NULL)
        $searchLogsRepo->trackQuery($query, $tenantId ?: null, null, $eid, $lang);
        // Per-user row so we can target the user with offers/notifications
        if ($currentUserId !== null) {
            $searchLogsRepo->trackQuery($query, $tenantId ?: null, $currentUserId, $eid, $lang);
        }
    } catch (Throwable $e) {
        // Table may not exist yet or columns not migrated — ignore
    }
};

/* -------------------------------------------------------
 * Popular searches: ?popular=1 — return top queries
 * ----------------------------------------------------- */
if (!empty($_GET['popular'])) {
    $popular = [];
    try {
        if ($searchLogsRepo) {
            $popular = $searchLogsRepo->popular($lang, $tenantId);
        }
    } catch (Throwable $e) {
        // search_logs may not exist yet
    }
    ResponseFormatter::success(['popular' => array_map(fn($row) => (string)$row['query'], $popular)]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    ResponseFormatter::success([
        'products'   => [],
        'categories' => [],
        'entities'   => [],
        'jobs'       => [],
        'brands'     => [],
        'auctions'   => [],
    ]);
    exit;
}

// context = all | products | categories | entities | jobs | brands | auctions
$context = strtolower(trim($_GET['context'] ?? 'all'));

// Boost limit for the active context type (show more of it)
$contextLimits = [
    'products'   => 5,
    'categories' => 5,
    'entities'   => 5,
    'jobs'       => 5,
    'brands'     => 5,
    'auctions'   => 5,
];
if (isset($contextLimits[$context])) {
    $contextLimits[$context] = 8;
}

// Escape LIKE wildcards
$safe  = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$like  = '%' . $safe . '%';

/* -------------------------------------------------------
 * Helper: try FULLTEXT MATCH AGAINST, fall back to LIKE
 * ----------------------------------------------------- */
$ftSearch = function (string $sql, array $params, string $likeSql, array $likeParams) use ($searchSuggestRepo): array {
    if (!$searchSuggestRepo) return [];
    return $searchSuggestRepo->fulltextSearch($sql, $params, $likeSql, $likeParams);
};

$results = [
    'products'   => [],
    'categories' => [],
    'entities'   => [],
    'jobs'       => [],
    'brands'     => [],
    'auctions'   => [],
];

/* ═══════════════════════════════════════════════════════
 *  PRODUCTS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['products'];

$tenantCond  = $tenantId ? ' AND p.tenant_id = ?' : '';
$tenantParam = $tenantId ? [$tenantId] : [];

// FULLTEXT on product_translations.name
$ftProductSql = "
    SELECT DISTINCT p.id,
        COALESCE(pt.name, p.slug) AS name,
        p.slug,
        e.id AS entity_id,
        MATCH(pt.name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM products p
    LEFT JOIN product_translations pt
        ON pt.product_id = p.id AND pt.language_code = ?
    LEFT JOIN entities e
        ON e.tenant_id = p.tenant_id AND e.status NOT IN ('suspended','rejected')
    WHERE p.is_active = 1
      AND MATCH(pt.name) AGAINST(? IN BOOLEAN MODE)
      $tenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeProductSql = "
    SELECT DISTINCT p.id,
        COALESCE(pt.name, p.slug) AS name,
        p.slug,
        e.id AS entity_id
    FROM products p
    LEFT JOIN product_translations pt
        ON pt.product_id = p.id AND pt.language_code = ?
    LEFT JOIN entities e
        ON e.tenant_id = p.tenant_id AND e.status NOT IN ('suspended','rejected')
    WHERE p.is_active = 1
      AND (pt.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ?)
      $tenantCond
    ORDER BY p.id DESC
    LIMIT $limit";

// Build boolean-mode query: at least first term required, rest optional for lenient matching
$terms = array_values(array_filter(explode(' ', $safe)));
if (count($terms) > 1) {
    $required = '+' . $terms[0] . '*';
    $optional = implode('* ', array_slice($terms, 1)) . '*';
    $boolQ = $required . ' ' . $optional;
} else {
    $boolQ = $safe . '*';
}
$rows = $ftSearch(
    $ftProductSql,
    array_merge([$boolQ, $lang, $boolQ], $tenantParam),
    $likeProductSql,
    array_merge([$lang, $like, $like, $like], $tenantParam)
);

foreach ($rows as $r) {
    $results['products'][] = [
        'id'        => (int)$r['id'],
        'name'      => (string)($r['name'] ?? ''),
        'url'       => '/frontend/public/product.php?id=' . $r['id'],
        'icon'      => '🛍',
        'type'      => 'product',
        'entity_id' => isset($r['entity_id']) && (int)$r['entity_id'] > 0 ? (int)$r['entity_id'] : null,
    ];
}

/* ═══════════════════════════════════════════════════════
 *  CATEGORIES
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['categories'];

$catTenantCond  = $tenantId ? ' AND c.tenant_id = ?' : '';
$catTenantParam = $tenantId ? [$tenantId] : [];

$ftCatSql = "
    SELECT DISTINCT c.id,
        COALESCE(ct.name, c.slug) AS name,
        c.slug,
        MATCH(ct.name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM categories c
    LEFT JOIN category_translations ct
        ON ct.category_id = c.id AND ct.language_code = ?
    WHERE c.is_active = 1
      AND MATCH(ct.name) AGAINST(? IN BOOLEAN MODE)
      $catTenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeCatSql = "
    SELECT DISTINCT c.id,
        COALESCE(ct.name, c.slug) AS name,
        c.slug
    FROM categories c
    LEFT JOIN category_translations ct
        ON ct.category_id = c.id AND ct.language_code = ?
    WHERE c.is_active = 1
      AND (ct.name LIKE ? OR c.slug LIKE ?)
      $catTenantCond
    ORDER BY c.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftCatSql,
    array_merge([$boolQ, $lang, $boolQ], $catTenantParam),
    $likeCatSql,
    array_merge([$lang, $like, $like], $catTenantParam)
);

foreach ($rows as $r) {
    $results['categories'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/categories.php?category_id=' . $r['id'],
        'icon' => '📂',
        'type' => 'category',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  ENTITIES (stores / vendors)
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['entities'];

$entTenantCond  = $tenantId ? ' AND e.tenant_id = ?' : '';
$entTenantParam = $tenantId ? [$tenantId] : [];

$ftEntSql = "
    SELECT DISTINCT e.id,
        COALESCE(et.store_name, e.store_name) AS name,
        e.slug,
        MATCH(et.store_name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM entities e
    LEFT JOIN entity_translations et
        ON et.entity_id = e.id AND et.language_code = ?
    WHERE e.status NOT IN ('suspended','rejected')
      AND MATCH(et.store_name) AGAINST(? IN BOOLEAN MODE)
      $entTenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeEntSql = "
    SELECT DISTINCT e.id,
        COALESCE(et.store_name, e.store_name) AS name,
        e.slug
    FROM entities e
    LEFT JOIN entity_translations et
        ON et.entity_id = e.id AND et.language_code = ?
    WHERE e.status NOT IN ('suspended','rejected')
      AND (et.store_name LIKE ? OR e.store_name LIKE ? OR e.slug LIKE ?)
      $entTenantCond
    ORDER BY e.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftEntSql,
    array_merge([$boolQ, $lang, $boolQ], $entTenantParam),
    $likeEntSql,
    array_merge([$lang, $like, $like, $like], $entTenantParam)
);

foreach ($rows as $r) {
    $results['entities'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/entity.php?id=' . $r['id'],
        'icon' => '🏢',
        'type' => 'entity',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  JOBS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['jobs'];

$ftJobSql = "
    SELECT DISTINCT j.id,
        COALESCE(jt.job_title, j.slug) AS name,
        j.slug,
        MATCH(jt.job_title) AGAINST(? IN BOOLEAN MODE) AS score
    FROM jobs j
    LEFT JOIN job_translations jt
        ON jt.job_id = j.id AND jt.language_code = ?
    WHERE j.status NOT IN ('cancelled','filled','closed')
      AND MATCH(jt.job_title) AGAINST(? IN BOOLEAN MODE)
    ORDER BY score DESC
    LIMIT $limit";

$likeJobSql = "
    SELECT DISTINCT j.id,
        COALESCE(jt.job_title, j.slug) AS name,
        j.slug
    FROM jobs j
    LEFT JOIN job_translations jt
        ON jt.job_id = j.id AND jt.language_code = ?
    WHERE j.status NOT IN ('cancelled','filled','closed')
      AND (jt.job_title LIKE ? OR j.slug LIKE ?)
    ORDER BY j.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftJobSql,
    [$boolQ, $lang, $boolQ],
    $likeJobSql,
    [$lang, $like, $like]
);

foreach ($rows as $r) {
    $results['jobs'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/jobs.php?job_id=' . $r['id'],
        'icon' => '💼',
        'type' => 'job',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  BRANDS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['brands'];

$brandTenantCond  = $tenantId ? ' AND b.tenant_id = ?' : '';
$brandTenantParam = $tenantId ? [$tenantId] : [];

$ftBrandSql = "
    SELECT DISTINCT b.id,
        COALESCE(bt.name, b.slug) AS name,
        b.slug,
        MATCH(bt.name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM brands b
    LEFT JOIN brand_translations bt
        ON bt.brand_id = b.id AND bt.language_code = ?
    WHERE b.is_active = 1 $brandTenantCond
      AND MATCH(bt.name) AGAINST(? IN BOOLEAN MODE)
    ORDER BY score DESC
    LIMIT $limit";

$likeBrandSql = "
    SELECT DISTINCT b.id,
        COALESCE(bt.name, b.slug) AS name,
        b.slug
    FROM brands b
    LEFT JOIN brand_translations bt
        ON bt.brand_id = b.id AND bt.language_code = ?
    WHERE b.is_active = 1 $brandTenantCond
      AND (bt.name LIKE ? OR b.slug LIKE ?)
    ORDER BY b.sort_order ASC, b.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftBrandSql,
    array_merge([$boolQ, $lang, $boolQ], $brandTenantParam),
    $likeBrandSql,
    array_merge([$lang], $brandTenantParam, [$like, $like])
);

foreach ($rows as $r) {
    $results['brands'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/products.php?brand_id=' . $r['id'],
        'icon' => '🏷',
        'type' => 'brand',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  AUCTIONS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['auctions'];

$auctionTenantCond  = $tenantId ? ' AND a.tenant_id = ?' : '';
$auctionTenantParam = $tenantId ? [$tenantId] : [];

$ftAuctionSql = "
    SELECT DISTINCT a.id,
        COALESCE(at2.title, a.slug) AS name,
        a.slug,
        MATCH(at2.title) AGAINST(? IN BOOLEAN MODE) AS score
    FROM auctions a
    LEFT JOIN auction_translations at2
        ON at2.auction_id = a.id AND at2.language_code = ?
    WHERE a.status IN ('active','scheduled') $auctionTenantCond
      AND MATCH(at2.title) AGAINST(? IN BOOLEAN MODE)
    ORDER BY score DESC
    LIMIT $limit";

$likeAuctionSql = "
    SELECT DISTINCT a.id,
        COALESCE(at2.title, a.slug) AS name,
        a.slug
    FROM auctions a
    LEFT JOIN auction_translations at2
        ON at2.auction_id = a.id AND at2.language_code = ?
    WHERE a.status IN ('active','scheduled') $auctionTenantCond
      AND (at2.title LIKE ? OR a.slug LIKE ?)
    ORDER BY a.end_date ASC, a.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftAuctionSql,
    array_merge([$boolQ, $lang, $boolQ], $auctionTenantParam),
    $likeAuctionSql,
    array_merge([$lang], $auctionTenantParam, [$like, $like])
);

foreach ($rows as $r) {
    $results['auctions'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/auction.php?id=' . $r['id'],
        'icon' => '🔨',
        'type' => 'auction',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  Also build flat "suggestions" array for backward compat
 * ═══════════════════════════════════════════════════════ */
$allSuggestions = [];
foreach ($results as $type => $items) {
    foreach ($items as $item) {
        $allSuggestions[] = $item;
    }
}

/* -------------------------------------------------------
 * Track this query now that we know which entity/tenant
 * the search results belong to.
 * Priority: explicit GET entity_id > first entity result >
 *           first product result's entity_id.
 * ----------------------------------------------------- */
$logEntityId = $searchEntityId;
if ($logEntityId === null && !empty($results['entities'])) {
    $logEntityId = (int)($results['entities'][0]['id'] ?? 0) ?: null;
}
if ($logEntityId === null && !empty($results['products'])) {
    $logEntityId = ($results['products'][0]['entity_id'] ?? null) ?: null;
}
$trackQuery($q, $logEntityId);

ResponseFormatter::success(array_merge($results, ['suggestions' => $allSuggestions]));
exit;