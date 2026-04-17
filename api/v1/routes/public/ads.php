<?php
declare(strict_types=1);

/**
 * Public API sub-route: ads  [v2.1.0 — Production]
 *
 * DB tables:
 *   ads, ad_campaigns, ad_translations, ad_placements, ad_placement_items, ad_stats
 *
 * Routes:
 *   GET  /api/public/ads
 *   GET  /api/public/ads/{id}
 *   POST /api/public/ads/{id}/click
 *   POST /api/public/ads/{id}/view
 *
 * Schema migration required before deploying (run once):
 *
 *   -- FIX-3: widen target_type ENUM to match all types handled in PHP
 *   ALTER TABLE ads MODIFY COLUMN target_type
 *     ENUM('url','product','category','entity','brand','auction','job','page')
 *     DEFAULT 'url';
 *
 *   -- FIX-4: unique constraint prevents duplicate rows under concurrent requests;
 *   --        INSERT IGNORE is only effective when this key exists.
 *   ALTER TABLE ad_stats
 *     ADD UNIQUE KEY uq_ad_stats_ad_date (ad_id, date);
 *
 * Variables assumed to exist (injected by the API router):
 *   $pdo, $pdoList, $pdoOne, $first, $segments, $lang,
 *   $page, $per, $offset, $tenantId
 */

if ($first !== 'ads') return;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$adId   = isset($segments[1]) && ctype_digit((string)($segments[1] ?? '')) ? (int)$segments[1] : 0;
$action = strtolower($segments[2] ?? '');

/* ═══════════════════════════════════════════════════════════════════════════
 * POST /ads/{id}/click  |  /ads/{id}/view — impression / click tracking
 *
 * FIX-4: relies on UNIQUE KEY uq_ad_stats_ad_date(ad_id, date) in ad_stats
 *        so INSERT IGNORE truly prevents duplicate rows under race conditions.
 *        Without the key INSERT IGNORE is a silent no-op and duplicates accumulate.
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($adId > 0 && $method === 'POST' && in_array($action, ['click', 'view'], true)) {
    // ── Collect tracking data ────────────────────────────────────────────
    if (session_status() === PHP_SESSION_NONE) {
        @session_start([
            'cookie_secure'   => isset($_SERVER['HTTPS']),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }

    $trackSessionId = session_id() ?: null;
    $trackUserId    = (int)(
        $_SESSION['user']['id'] ??
        ($_SESSION['current_user']['id'] ?? ($_SESSION['user_id'] ?? 0))
    ) ?: null;

    // Release session lock immediately — we only read, never write.
    session_write_close();

    $trackIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains((string)$trackIp, ',')) {
        $trackIp = trim(explode(',', (string)$trackIp)[0]);
    }
    $trackIp        = substr((string)$trackIp, 0, 45) ?: null;
    $trackUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;
    $trackEventType = $action === 'click' ? 'click' : 'view';

    $isView  = $action === 'view'  ? 1 : 0;
    $isClick = $action === 'click' ? 1 : 0;

    $adStatRepo = new PdoAdStatRepository($pdo);
    try {
        $adStatRepo->recordImpression(
            $adId, $trackUserId, $trackSessionId, $trackIp, $trackUserAgent,
            $isView, $isClick, $trackEventType
        );
    } catch (Throwable $e) {
        error_log('[ads.php] ad_stats insert failed for ad_id=' . $adId . ': ' . $e->getMessage());
        try {
            $adStatRepo->incrementStat($adId, $isView, $isClick);
        } catch (Throwable $e2) {
            error_log('[ads.php] ad_stats fallback insert failed for ad_id=' . $adId . ': ' . $e2->getMessage());
        }
    }
    ResponseFormatter::success(['ok' => true]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GET /ads/{id} — single ad
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($adId > 0 && $method === 'GET') {
    if (!$tenantId) {
        ResponseFormatter::notFound('Ad not found');
        exit;
    }

    $row = $pdoOne("
        SELECT
            a.id,
            a.campaign_id,
            a.target_type,
            a.target_value,
            a.status,
            COALESCE(NULLIF(TRIM(atr.title), ''), '')       AS title,
            COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
            img.url       AS image_url,
            img.thumb_url AS thumb_url
        FROM ads a
        JOIN ad_campaigns ac
            ON  ac.id        = a.campaign_id
            AND ac.tenant_id = ?
            AND ac.status    = 'active'
        LEFT JOIN ad_translations atr
            ON  atr.ad_id         = a.id
            AND atr.language_code = ?
        LEFT JOIN images img
            ON  img.owner_id      = a.id
            AND img.image_type_id = 20
            AND img.is_main       = 1
        WHERE a.id     = ?
          AND a.status = 'active'
        LIMIT 1
    ", [$tenantId, $lang, $adId]);

    if (!$row) {
        ResponseFormatter::notFound('Ad not found');
        exit;
    }

    ResponseFormatter::success(['ok' => true, 'data' => $row]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GET /ads — listing
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($method !== 'GET') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

if (!$tenantId) {
    ResponseFormatter::success(['ok' => true, 'data' => []]);
    exit;
}

$placementKey = trim($_GET['placement_key'] ?? '');
$maxAds       = max(1, min(50, (int)($_GET['limit'] ?? $per)));

/* ── Shared SELECT fragment ────────────────────────────────────────────── */
$adSelect = "
    SELECT
        a.id,
        a.target_type,
        a.target_value,
        COALESCE(NULLIF(TRIM(atr.title), ''), '')       AS title,
        COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
        img.url       AS image_url,
        img.thumb_url AS thumb_url
";

/* ── Strategy 1: Placement-aware ──────────────────────────────────────── */
$p1Where  = '';
$p1Params = [$tenantId, $tenantId, $lang];

if ($placementKey !== '') {
    $p1Where    = 'AND ap.placement_key = ?';
    $p1Params[] = $placementKey;
}
$p1Params[] = $maxAds;

$rows = $pdoList("
    {$adSelect},
        api_item.priority,
        api_item.weight
    FROM ad_placement_items api_item
    JOIN ad_placements ap
        ON  ap.id        = api_item.placement_id
        AND ap.tenant_id = ?
        AND ap.status    = 'active'
    JOIN ads a
        ON  a.id     = api_item.ad_id
        AND a.status = 'active'
    JOIN ad_campaigns ac
        ON  ac.id        = a.campaign_id
        AND ac.tenant_id = ?
        AND ac.status    = 'active'
    LEFT JOIN ad_translations atr
        ON  atr.ad_id         = a.id
        AND atr.language_code = ?
    LEFT JOIN images img
        ON  img.owner_id      = a.id
        AND img.image_type_id = 20
        AND img.is_main       = 1
    WHERE (api_item.start_date IS NULL OR api_item.start_date <= NOW())
      AND (api_item.end_date   IS NULL OR api_item.end_date   >= NOW())
      {$p1Where}
    ORDER BY api_item.priority ASC, api_item.weight DESC
    LIMIT ?
", $p1Params);

/* ── Strategy 2: Direct ads (no placement configured) ─────────────────── */
if (empty($rows)) {
    $rows = $pdoList("
        {$adSelect}, 0 AS priority, 0 AS weight
        FROM ads a
        JOIN ad_campaigns ac
            ON  ac.id        = a.campaign_id
            AND ac.tenant_id = ?
            AND ac.status    = 'active'
        LEFT JOIN ad_translations atr
            ON  atr.ad_id         = a.id
            AND atr.language_code = ?
        LEFT JOIN images img
            ON  img.owner_id      = a.id
            AND img.image_type_id = 20
            AND img.is_main       = 1
        WHERE a.status = 'active'
        ORDER BY a.id DESC
        LIMIT ?
    ", [$tenantId, $lang, $maxAds]);
}

ResponseFormatter::success(['ok' => true, 'data' => $rows]);
exit;