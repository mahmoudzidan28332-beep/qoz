<?php
declare(strict_types=1);
/**
 * Public API sub-route: banners
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'banners') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $banWhere  = 'WHERE b.tenant_id = ? AND b.is_active = 1
                    AND (b.start_date IS NULL OR b.start_date <= NOW())
                    AND (b.end_date   IS NULL OR b.end_date   >= NOW())';
    $banParams = [$tenantId];
    if (!empty($_GET['position'])) { $banWhere .= ' AND b.position = ?'; $banParams[] = $_GET['position']; }
    $rows = $pdoList(
        "SELECT b.id, b.position, b.background_color, b.text_color, b.button_style, b.sort_order,
                b.link_url,
                COALESCE(bt.title,     b.title)     AS title,
                COALESCE(bt.subtitle,  b.subtitle)  AS subtitle,
                COALESCE(bt.link_text, b.link_text) AS link_text,
                img.url        AS image_url,
                img.thumb_url  AS mobile_image_url
           FROM banners b
           LEFT JOIN banner_translations bt
                  ON bt.banner_id = b.id AND bt.language_code = ?
           LEFT JOIN images img
                  ON img.owner_id = b.id AND img.image_type_id = 9 AND img.is_main = 1
         $banWhere ORDER BY b.sort_order ASC, b.id ASC LIMIT 20",
        array_merge([$lang], $banParams)
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Discounts (public active discounts for tenant)
 * GET /api/public/discounts?tenant_id=X&lang=Y
 * ----------------------------------------------------- */