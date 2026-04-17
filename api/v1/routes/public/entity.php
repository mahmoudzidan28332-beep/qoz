<?php
declare(strict_types=1);
/**
 * Public API sub-route: entity
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'entity') {
    // Support both numeric ID and slug: /api/public/entity/123 or /api/public/entity/my-store-slug
    $entityId = 0;
    $entitySlug = '';
    if (isset($segments[1])) {
        if (ctype_digit((string)$segments[1])) {
            $entityId = (int)$segments[1];
        } else {
            $entitySlug = trim($segments[1]);
        }
    }
    if (!$entityId) $entityId = (int)($_GET['id'] ?? 0);
    if (!$entitySlug) $entitySlug = trim($_GET['slug'] ?? '');

    // Resolve slug → entity ID
    if (!$entityId && $entitySlug) {
        $slugRow = $pdoOne('SELECT id FROM entities WHERE slug = ? AND status NOT IN (\'suspended\',\'rejected\') LIMIT 1', [$entitySlug]);
        if ($slugRow) $entityId = (int)$slugRow['id'];
    }

    if (!$entityId) {
        ResponseFormatter::notFound('Entity ID or slug required');
        exit;
    }

    $sub      = strtolower($segments[2] ?? '');

    // Sub-route: entity categories — categories with products in this entity's tenant
    if ($sub === 'categories') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $eTenId = (int)$entityRow['tenant_id'];
        $rows   = $pdoList(
            "SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name, c.slug
               FROM product_categories pc
               JOIN categories c ON c.id = pc.category_id AND c.is_active = 1
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
               JOIN products p ON p.id = pc.product_id AND p.tenant_id = ? AND p.is_active = 1
              ORDER BY c.sort_order ASC, c.id ASC LIMIT 50",
            [$lang, $eTenId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity store_sections — page sections with language-aware translations
    // GET /api/public/entity/{id}/store_sections
    if ($sub === 'store_sections') {
        $entityRow = $pdoOne('SELECT id, tenant_id FROM entities WHERE id = ? AND status NOT IN (\'suspended\',\'rejected\') LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }

        // Default section order when no DB config exists
        $defaultSections = [
            ['type' => 'header',   'position' => 10, 'settings' => '{"show_cover":true,"show_rating":true,"show_verified":true,"show_status":true}'],
            ['type' => 'contact',  'position' => 20, 'settings' => '{"show_phone":true,"show_email":true,"show_website":true,"show_share":true,"show_social":true}'],
            ['type' => 'tabs',     'position' => 30, 'settings' => '{"tabs":["products","info","hours","location","offers","reviews","policies"]}'],
            ['type' => 'products', 'position' => 40, 'settings' => '{"per_page":12,"show_categories":true,"show_search":true,"show_cart":true}'],
            ['type' => 'info',     'position' => 50, 'settings' => '{"show_description":true,"show_attributes":true,"show_payment_methods":true,"show_settings":true}'],
            ['type' => 'hours',    'position' => 60, 'settings' => '{}'],
            ['type' => 'location', 'position' => 70, 'settings' => '{"show_osm":true,"show_google":true}'],
            ['type' => 'offers',   'position' => 80, 'settings' => '{}'],
            ['type' => 'reviews',  'position' => 90, 'settings' => '{"show_form":true,"limit":5}'],
            ['type' => 'policies',   'position' => 95, 'settings' => '{"types":["refund","privacy","shipping","terms"]}'],
            ['type' => 'attributes', 'position' => 55, 'settings' => '{}'],
        ];

        $sections = [];
        try {
            $sections = $pdoList(
                "SELECT ss.id, ss.type, ss.position, ss.is_active, ss.settings,
                        sst.title AS translated_title, sst.content AS translated_content
                   FROM store_sections ss
                   JOIN store_pages sp ON sp.id = ss.page_id
              LEFT JOIN store_section_translations sst ON sst.section_id = ss.id AND sst.language_code = ?
                  WHERE sp.tenant_id = ? AND sp.type = 'store' AND sp.is_active = 1 AND ss.is_active = 1
                  ORDER BY ss.position ASC",
                [$lang, $entityRow['tenant_id']]
            );
        } catch (\Throwable $_) {
            // Tables may not exist yet — fall back to defaults
            $sections = [];
        }

        if (empty($sections)) {
            // Return default sections with null translations
            $sections = array_map(function ($s) {
                return [
                    'id'                 => null,
                    'type'               => $s['type'],
                    'position'           => $s['position'],
                    'is_active'          => 1,
                    'settings'           => $s['settings'],
                    'translated_title'   => null,
                    'translated_content' => null,
                ];
            }, $defaultSections);
        }

        // Parse settings JSON for each section
        foreach ($sections as &$sec) {
            $sec['settings'] = is_string($sec['settings'] ?? null)
                ? (json_decode($sec['settings'], true) ?: [])
                : ($sec['settings'] ?? []);
            $sec['translated_content'] = is_string($sec['translated_content'] ?? null)
                ? (json_decode($sec['translated_content'], true) ?: null)
                : ($sec['translated_content'] ?? null);
        }
        unset($sec);

        ResponseFormatter::success(['ok' => true, 'data' => $sections]);
        exit;
    }

    // Sub-route: entity page_categories — hierarchical categories with language support
    // GET /api/public/entity/{id}/page_categories
    if ($sub === 'page_categories') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? AND status NOT IN (\'suspended\',\'rejected\') LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $eTenId = (int)$entityRow['tenant_id'];

        // Check if entity has category assignments — graceful fallback when none exist
        $ecCount = (int)$pdoCount('SELECT COUNT(*) FROM entity_categories WHERE entity_id = ? AND is_active = 1', [$entityId]);
        $entityCategoryJoin = '';
        $catParams = [$lang, $eTenId];
        if ($ecCount > 0) {
            $entityCategoryJoin = 'JOIN entity_categories ec ON ec.category_id = c.id AND ec.entity_id = ? AND ec.is_active = 1';
            $catParams[] = $entityId;
        }

        $rows = $pdoList(
            "SELECT DISTINCT c.id, c.parent_id, COALESCE(ct.name, c.name) AS name, c.slug,
                    c.sort_order
               FROM product_categories pc
               JOIN categories c ON c.id = pc.category_id AND c.is_active = 1
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
               JOIN products p ON p.id = pc.product_id AND p.tenant_id = ? AND p.is_active = 1
               $entityCategoryJoin
              ORDER BY c.sort_order ASC, c.id ASC LIMIT 100",
            $catParams
        );

        // Build hierarchical tree
        $catById = [];
        foreach ($rows as &$cat) {
            $cat['children'] = [];
            $catById[(int)$cat['id']] = &$cat;
        }
        unset($cat);

        foreach ($rows as &$cat) {
            $pid = (int)($cat['parent_id'] ?? 0);
            if ($pid && isset($catById[$pid])) {
                $catById[$pid]['children'][] = &$cat;
            }
        }
        unset($cat);

        // Collect root categories (no parent or parent not in results)
        $tree = [];
        foreach ($rows as $cat) {
            $pid = (int)($cat['parent_id'] ?? 0);
            if (!$pid || !isset($catById[$pid])) {
                $tree[] = $cat;
            }
        }

        // If all are leaf nodes, return flat list
        if (empty($tree)) {
            $tree = $rows;
        }

        ResponseFormatter::success(['ok' => true, 'data' => $tree, 'flat' => $rows]);
        exit;
    }

    // Sub-route: entity policies — refund, privacy, shipping, terms
    // GET /api/public/entity/{id}/policies?types=refund,privacy,shipping,terms
    if ($sub === 'policies') {
        $entityRow = $pdoOne('SELECT id, tenant_id FROM entities WHERE id = ? AND status NOT IN (\'suspended\',\'rejected\') LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }

        // Optional type filter
        $typeFilter = trim($_GET['types'] ?? '');
        $allowedTypes = ['refund', 'privacy', 'shipping', 'terms'];
        $requestedTypes = $typeFilter ? array_intersect(explode(',', $typeFilter), $allowedTypes) : $allowedTypes;

        if (empty($requestedTypes)) {
            ResponseFormatter::success(['ok' => true, 'data' => []]);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($requestedTypes), '?'));
        $params = array_merge([$entityId, $lang], array_values($requestedTypes));

        $rows = [];
        try {
            $rows = $pdoList(
                "SELECT type, title, content, sort_order
                   FROM entity_policies
                  WHERE entity_id = ? AND language_code = ? AND is_active = 1
                    AND type IN ($placeholders)
                  ORDER BY sort_order ASC, type ASC",
                $params
            );
        } catch (\Throwable $_) {
            // Table may not exist yet
            $rows = [];
        }

        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity attributes — merchant attributes/details
    // GET /api/public/entity/{id}/attributes
    if ($sub === 'attributes') {
        $entityRow = $pdoOne('SELECT id, tenant_id FROM entities WHERE id = ? AND status NOT IN (\'suspended\',\'rejected\') LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }

        $rows = [];
        try {
            $rows = $pdoList(
                "SELECT COALESCE(eat.name, ea.slug) AS attribute_name,
                        eat.description AS attribute_description,
                        ea.attribute_type,
                        ea.slug,
                        eav.value
                   FROM entities_attribute_values eav
              LEFT JOIN entities_attributes ea ON ea.id = eav.attribute_id
              LEFT JOIN entities_attribute_translations eat ON eat.attribute_id = ea.id AND eat.language_code = ?
                  WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != ''
                  ORDER BY ea.sort_order ASC, ea.id ASC
                  LIMIT 50",
                [$lang, $entityId]
            );
        } catch (\Throwable $_) {
            $rows = [];
        }

        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity discounts — active discounts for this entity
    if ($sub === 'discounts') {
        $rows = $pdoList(
            "SELECT d.id, d.code, d.type, d.auto_apply, d.currency_code, d.status,
                    d.starts_at, d.ends_at, d.max_redemptions, d.current_redemptions,
                    COALESCE(dt.name, d.code) AS title,
                    dt.description, dt.marketing_badge, dt.terms_conditions
               FROM discounts d
          LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
              WHERE d.entity_id = ?
                AND d.status NOT IN ('cancelled','deleted')
              ORDER BY d.status ASC, d.priority DESC, d.id DESC LIMIT 30",
            [$lang, $entityId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity banners — active banners for this entity with images
    if ($sub === 'banners') {
        $rows = $pdoList(
            "SELECT b.id, b.link_url, b.background_color, b.text_color, b.button_style,
                    b.sort_order, b.position,
                    COALESCE(bt.title,    b.title)    AS title,
                    COALESCE(bt.subtitle, b.subtitle) AS subtitle,
                    COALESCE(bt.link_text, b.link_text) AS link_text,
                    img.url AS image_url, img.thumb_url
               FROM banners b
          LEFT JOIN banner_translations bt ON b.id = bt.banner_id AND bt.language_code = ?
          LEFT JOIN images img ON img.owner_id = b.id AND img.image_type_id = 9 AND img.is_main = 1
             WHERE b.entity_id = ? AND b.is_active = 1
               AND (b.start_date IS NULL OR b.start_date <= NOW())
               AND (b.end_date   IS NULL OR b.end_date   >= NOW())
             ORDER BY b.sort_order ASC, b.id ASC LIMIT 10",
            [$lang, $entityId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity jobs — active job listings for this entity
    if ($sub === 'jobs') {
        $rows = $pdoList(
            "SELECT j.id, j.job_type AS employment_type, j.is_remote, j.is_featured, j.is_urgent,
                    j.application_deadline AS deadline, j.salary_min, j.salary_max, j.salary_currency,
                    j.created_at, COALESCE(jt.job_title, j.slug) AS title,
                    jt.description, jt.requirements, jt.benefits
               FROM jobs j
          LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
             WHERE j.entity_id = ? AND j.status NOT IN ('cancelled','filled','closed')
             ORDER BY j.is_featured DESC, j.is_urgent DESC, j.created_at DESC LIMIT 50",
            [$lang, $entityId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity ratings — GET list or POST submit
    if ($sub === 'ratings') {
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if ($isPost) {
            // Submit a rating
            $rateUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
            if (!$rateUserId) { ResponseFormatter::error('Login required', 401); exit; }
            $ratingVal  = (float)($_POST['rating'] ?? 0);
            $reviewText = trim($_POST['review'] ?? '');
            if ($ratingVal < 1 || $ratingVal > 5) { ResponseFormatter::error('Rating must be between 1 and 5'); exit; }
            try {
                // Upsert: one rating per user per entity (INSERT or UPDATE if exists)
                $entityRatingsRepo = new PdoEntityRatingsRepository($pdo);
                $existing = $pdoOne('SELECT id FROM entity_ratings WHERE entity_id = ? AND user_id = ? LIMIT 1', [$entityId, $rateUserId]);
                if ($existing) {
                    $entityRatingsRepo->updateRating((int)$existing['id'], $ratingVal, $reviewText ?: null);
                    $msg = 'Rating updated';
                } else {
                    $entityRatingsRepo->createRating($entityId, $rateUserId, $ratingVal, $reviewText ?: null);
                    $msg = 'Rating submitted';
                }
                ResponseFormatter::success(['ok' => true, 'message' => $msg]);
            } catch (Throwable $ex) {
                ResponseFormatter::error('Failed to save rating: ' . $ex->getMessage());
            }
            exit;
        }
        // GET: last 5 active ratings
        $rows = $pdoList(
            "SELECT er.id, er.rating, er.review, er.created_at,
                    COALESCE(u.username, 'Anonymous') AS reviewer_name
               FROM entity_ratings er
          LEFT JOIN users u ON u.id = er.user_id
              WHERE er.entity_id = ? AND er.is_active = 1
              ORDER BY er.created_at DESC LIMIT 5",
            [$entityId]
        );
        $avg = $pdoOne('SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM entity_ratings WHERE entity_id = ? AND is_active = 1', [$entityId]);
        ResponseFormatter::success(['ok' => true, 'ratings' => $rows, 'average' => $avg['avg_rating'] ?? null, 'total' => (int)($avg['total'] ?? 0)]);
        exit;
    }

    // Sub-route: entity products
    // Products have no entity_id column; use entity's tenant_id to scope products
    // When entity has category assignments (entity_categories), only show products in those categories
    if ($sub === 'products') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $entityTenantId = (int)$entityRow['tenant_id'];
        $where  = 'WHERE p.is_active = 1 AND p.tenant_id = ?';
        $params = [$entityTenantId];

        // Filter by entity_categories if entity has category assignments
        $ecCount = (int)$pdoCount('SELECT COUNT(*) FROM entity_categories WHERE entity_id = ? AND is_active = 1', [$entityId]);
        if ($ecCount > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM product_categories pc_ec
                JOIN entity_categories ec ON ec.category_id = pc_ec.category_id
                WHERE pc_ec.product_id = p.id AND ec.entity_id = ? AND ec.is_active = 1)';
            $params[] = $entityId;
        }

        if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM product_categories pc2 WHERE pc2.product_id = p.id AND pc2.category_id = ?)';
            $params[] = (int)$_GET['category_id'];
        }
        $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $params);
        $rows  = $pdoList(
            "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug,
                    p.is_featured, p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                    COALESCE(
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id = ? AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id IS NULL AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1)
                    ) AS price,
                    COALESCE(
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id = ? AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id IS NULL AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1)
                    ) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    NULL AS image_thumb_url
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              $where ORDER BY p.is_featured DESC, p.id DESC LIMIT ? OFFSET ?",
            array_merge([$entityId, $entityId, $lang], $params, [$per, $offset])
        );
        ResponseFormatter::success(['ok'=>true,'data'=>$rows,'meta'=>[
            'total'=>$total,'page'=>$page,'per_page'=>$per,
            'total_pages'=>$per > 0 ? (int)ceil($total/$per) : 1
        ]]);
        exit;
    }

    // Full entity profile — images fetched from images table (entity_logo / entity_cover)
    // entities table has vendor_type (varchar code), not entity_type_id — join entity_types by code
    $entity = $pdoOne(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                e.status, e.tenant_id, e.created_at,
                et.name AS type_name,
                (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                NULL AS logo_thumb_url,
                (SELECT i2.url FROM images i2 WHERE i2.owner_id = e.id ORDER BY i2.id ASC LIMIT 1 OFFSET 1) AS cover_url
           FROM entities e
      LEFT JOIN entity_types et ON et.code = e.vendor_type
          WHERE e.id = ? AND e.status NOT IN ('suspended','rejected') LIMIT 1",
        [$entityId]
    );

    if (!$entity) {
        ResponseFormatter::notFound('Entity not found');
        exit;
    }

    // Translation override (store_name / description)
    $translation = $pdoOne(
        "SELECT store_name, description FROM entity_translations
          WHERE entity_id = ? AND language_code = ? LIMIT 1",
        [$entityId, $lang]
    );
    if ($translation) {
        if (!empty($translation['store_name'])) $entity['store_name'] = $translation['store_name'];
        if (!empty($translation['description'])) $entity['description'] = $translation['description'];
    }

    // Working hours — table has is_open (tinyint), not is_closed; day_of_week is tinyint 0-6
    $workingHours = $pdoList(
        "SELECT day_of_week, open_time, close_time, is_open
           FROM entities_working_hours
          WHERE entity_id = ? ORDER BY day_of_week ASC",
        [$entityId]
    );

    // Addresses (with coordinates) — addresses table has no label column
    $addresses = $pdoList(
        "SELECT id, address_line1, address_line2, city_id, country_id,
                postal_code, latitude, longitude, is_primary
           FROM addresses
          WHERE owner_type = 'entity' AND owner_id = ? ORDER BY is_primary DESC, id ASC LIMIT 5",
        [$entityId]
    );

    // Payment methods — payment_methods columns: method_name, method_key, icon_url
    $paymentMethods = $pdoList(
        "SELECT pm.id, pm.method_name AS name, pm.method_key AS code, pm.icon_url AS icon, epm.is_active
           FROM entity_payment_methods epm
      LEFT JOIN payment_methods pm ON pm.id = epm.payment_method_id
          WHERE epm.entity_id = ? AND epm.is_active = 1",
        [$entityId]
    );

    // Attributes — entities_attributes has NO entity_type_id column; start from values table
    $attributes = $pdoList(
        "SELECT COALESCE(eat.name, ea.slug) AS attribute_name, eav.value
           FROM entities_attribute_values eav
      LEFT JOIN entities_attributes ea  ON ea.id = eav.attribute_id
      LEFT JOIN entities_attribute_translations eat
             ON eat.attribute_id = ea.id AND eat.language_code = ?
          WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != ''
          LIMIT 20",
        [$lang, $entityId]
    );

    // Entity settings — expose all business-logic fields for the frontend
    $entitySettings = $pdoOne(
        "SELECT auto_accept_orders, allow_cod, min_order_amount, preparation_time_minutes,
                allow_online_booking, booking_window_days, max_bookings_per_slot,
                booking_cancellation_allowed, allow_preorders, max_daily_orders,
                is_visible, maintenance_mode, show_reviews, show_contact_info,
                featured_in_app, default_payment_method, allow_multiple_payment_methods,
                delivery_radius_km, free_delivery_min_order,
                notification_preferences, additional_settings, card_style_id
           FROM entity_settings
          WHERE entity_id = ? LIMIT 1",
        [$entityId]
    );

    // Card style — fetch full card style when card_style_id is set in entity settings
    $entityCardStyle = null;
    if (!empty($entitySettings['card_style_id'])) {
        $entityCardStyle = $pdoOne(
            "SELECT id, name, slug, card_type, background_color, border_color, border_width,
                    border_radius, shadow_style, padding, hover_effect, text_align,
                    image_aspect_ratio
               FROM card_styles WHERE id = ? AND is_active = 1 LIMIT 1",
            [(int)$entitySettings['card_style_id']]
        );
    }

    // Entity banners
    $entityBanners = $pdoList(
        "SELECT b.id, b.link_url, b.background_color, b.text_color, b.sort_order, b.position,
                COALESCE(bt.title, b.title) AS title,
                COALESCE(bt.subtitle, b.subtitle) AS subtitle,
                COALESCE(bt.link_text, b.link_text) AS link_text,
                img.url AS image_url, img.thumb_url
           FROM banners b
      LEFT JOIN banner_translations bt ON b.id = bt.banner_id AND bt.language_code = ?
      LEFT JOIN images img ON img.owner_id = b.id AND img.image_type_id = 9 AND img.is_main = 1
         WHERE b.entity_id = ? AND b.is_active = 1
           AND (b.start_date IS NULL OR b.start_date <= NOW())
           AND (b.end_date   IS NULL OR b.end_date   >= NOW())
         ORDER BY b.sort_order ASC, b.id ASC LIMIT 10",
        [$lang, $entityId]
    );

    // Entity jobs
    $entityJobs = $pdoList(
        "SELECT j.id, j.job_type AS employment_type, j.is_remote, j.is_featured, j.is_urgent,
                j.application_deadline AS deadline, j.salary_min, j.salary_max, j.salary_currency,
                j.created_at, COALESCE(jt.job_title, j.slug) AS title
           FROM jobs j
      LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
         WHERE j.entity_id = ? AND j.status NOT IN ('cancelled','filled','closed')
         ORDER BY j.is_featured DESC, j.is_urgent DESC, j.created_at DESC LIMIT 20",
        [$lang, $entityId]
    );

    ResponseFormatter::success([
        'ok'      => true,
        'data'    => array_merge($entity, [
            'working_hours'   => $workingHours,
            'addresses'       => $addresses,
            'payment_methods' => $paymentMethods,
            'attributes'      => $attributes,
            'settings'        => $entitySettings ?: [],
            'card_style'      => $entityCardStyle,
            'banners'         => $entityBanners,
            'jobs'            => $entityJobs,
        ]),
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Entities (public listing)
 * GET /api/public/entities[/{id}]
 * ----------------------------------------------------- */