<?php
/**
 * frontend/public/product.php
 * QOOQZ — Product Detail Page
 *
 * Shows full product info: gallery, description, price, brand,
 * categories, variants, reviews, Q&A, related products.
 *
 * Uses direct PDO queries (not HTTP loopback) for reliability.
 * No declare(strict_types=1) — PDO FETCH_ASSOC returns string values
 * and strict typing causes TypeErrors with int/float comparisons.
 */

set_exception_handler(function (Throwable $ex) {
    error_log('[product.php] Uncaught exception: ' . $ex->getMessage()
        . ' at ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(200);
    echo '<div style="padding:40px;text-align:center;font-family:sans-serif;">'
       . '<i class="bi bi-exclamation-triangle" style="font-size:2rem;color:#e74c3c;"></i>'
       . '<p style="margin-top:12px;">Unable to load product. Please try again later.</p>'
       . '</div>';
    exit;
});

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx          = $GLOBALS['PUB_CONTEXT'];
$lang         = $ctx['lang'];
$dir          = $ctx['dir'];
$tenantId     = $ctx['tenant_id'];
$activeEntity = is_array($ctx['active_entity'] ?? null) ? $ctx['active_entity'] : [];

// Accept id or slug
$productId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$productSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$entityId    = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : (int)($activeEntity['id'] ?? 0);

if (!$productId && $productSlug === '') {
    header('Location: /frontend/public/products.php');
    exit;
}

// ============================================================
// Data containers
// ============================================================
$product   = null;
$images    = [];
$categories = [];
$related   = [];
$variants  = [];
$reviews   = [];
$questions = [];
$relations = [];


// ============================================================
// Step 1: PDO — direct DB (primary)
// ============================================================
$pdo = pub_get_pdo();

if (!$product && $pdo) {
    try {
        // Resolve slug -> id
        if (!$productId && $productSlug !== '') {
            $st = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
            $st->execute([$productSlug]);
            $r = $st->fetch();
            if ($r) $productId = (int)$r['id'];
        }

        if ($productId) {
            $st = $pdo->prepare(
                "SELECT p.id, p.sku, p.slug, p.barcode, p.brand_id,
                        p.is_active,
                        COALESCE(ep.is_featured,    p.is_featured)    AS is_featured,
                        p.is_new, p.is_bestseller,
                        COALESCE(ep.stock_quantity, p.stock_quantity) AS stock_quantity,
                        p.stock_status, p.rating_average, p.rating_count,
                        p.views_count, p.tenant_id,
                        COALESCE(pt.name, p.slug)   AS name,
                        pt.short_description, pt.description, pt.specifications,
                        (SELECT pp.price FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                        NULL AS compare_at_price,
                        (SELECT pp.currency_code FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                        NULL AS brand_name,
                        (SELECT i.url FROM images i WHERE i.owner_id = p.id
                           ORDER BY i.id ASC LIMIT 1) AS image_url,
                        NULL AS image_thumb_url
                   FROM products p
              LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              LEFT JOIN entity_products ep      ON ep.product_id = p.id AND ep.entity_id = ?
                  WHERE p.id = ?"
            );
            $st->execute([$lang, $entityId, $productId]);
            $product = $st->fetch() ?: null;

            if ($product) {

                // Gallery images
                try {
                    $st = $pdo->prepare(
                        "SELECT i.id, i.url FROM images i
                          WHERE i.owner_id = ? ORDER BY i.id ASC LIMIT 10"
                    );
                    $st->execute([$productId]);
                    $rawImgs = $st->fetchAll();
                    $images  = [];
                    foreach ($rawImgs as $img) {
                        $images[] = [
                            'id'        => $img['id'],
                            'url'       => $img['url'],
                            'thumb_url' => $img['thumb_url'] ?? $img['url'],
                            'alt_text'  => $img['alt_text']  ?? '',
                        ];
                    }
                    if (!empty($images) && empty($product['image_url'])) {
                        $product['image_url']       = $images[0]['url'];
                        $product['image_thumb_url'] = $images[0]['thumb_url'];
                    } elseif (!empty($product['image_url']) && empty($product['image_thumb_url'])) {
                        $product['image_thumb_url'] = $product['image_url'];
                    }
                } catch (Throwable $_) { $images = []; }

                // Categories
                try {
                    $st = $pdo->prepare(
                        "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug
                           FROM categories c
                     INNER JOIN product_categories pc ON pc.category_id = c.id AND pc.product_id = ?
                      LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
                          LIMIT 5"
                    );
                    $st->execute([$productId, $lang]);
                    $categories = $st->fetchAll();
                } catch (Throwable $_) { $categories = []; }

                // Variants
                try {
                    $st = $pdo->prepare(
                        "SELECT pv.id, pv.sku, pv.stock_quantity, pv.is_default,
                                (SELECT pp.price FROM product_pricing pp
                                   WHERE pp.product_id = ? AND pp.variant_id = pv.id
                                   ORDER BY pp.id ASC LIMIT 1) AS price,
                                (SELECT pp.currency_code FROM product_pricing pp
                                   WHERE pp.product_id = ? AND pp.variant_id = pv.id
                                   ORDER BY pp.id ASC LIMIT 1) AS currency_code
                           FROM product_variants pv
                          WHERE pv.product_id = ? AND pv.is_active = 1
                          ORDER BY pv.is_default DESC, pv.id ASC LIMIT 20"
                    );
                    $st->execute([$productId, $productId, $productId]);
                    $variants = $st->fetchAll();
                } catch (Throwable $_) { $variants = []; }

                // Related products (same first category)
                if (!empty($categories[0]['id'])) {
                    try {
                        $st = $pdo->prepare(
                            "SELECT p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug,
                                    p2.stock_status, p2.stock_quantity,
                                    (SELECT pp2.price FROM product_pricing pp2
                                       WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS price,
                                    (SELECT pp2.currency_code FROM product_pricing pp2
                                       WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS currency_code,
                                    (SELECT i2.url FROM images i2
                                       WHERE i2.owner_id = p2.id ORDER BY i2.id ASC LIMIT 1) AS image_url
                               FROM products p2
                         INNER JOIN product_categories pc2 ON pc2.product_id = p2.id AND pc2.category_id = ?
                          LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                              WHERE p2.is_active = 1 AND p2.id != ?
                              ORDER BY p2.is_featured DESC, p2.id DESC LIMIT 8"
                        );
                        $st->execute([(int)$categories[0]['id'], $lang, $productId]);
                        $related = $st->fetchAll();
                    } catch (Throwable $_) { $related = []; }
                }

                // Recently viewed (fire and forget)
                try {
                    $rvUid = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
                    $pdo->prepare(
                        'INSERT INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                         VALUES (?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE viewed_at = NOW()'
                    )->execute([$rvUid, session_id() ?: null, $productId]);
                } catch (Throwable $_) {
                    try {
                        $pdo->prepare(
                            'INSERT IGNORE INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                             VALUES (?, ?, ?, NOW())'
                        )->execute([$_SESSION['user_id'] ?? null, session_id() ?: null, $productId]);
                    } catch (Throwable $__) {}
                }

                // Reviews (approved only)
                try {
                    $st = $pdo->prepare(
                        "SELECT r.id, r.rating, r.title, r.comment, r.is_verified_purchase,
                                r.helpful_count, r.created_at,
                                COALESCE(u.name, u.username, 'User') AS author
                           FROM product_reviews r
                      LEFT JOIN users u ON u.id = r.user_id
                          WHERE r.product_id = ? AND r.is_approved = 1
                          ORDER BY r.helpful_count DESC, r.created_at DESC LIMIT 30"
                    );
                    $st->execute([$productId]);
                    $reviews = $st->fetchAll();
                } catch (Throwable $_) { $reviews = []; }

                // Q&A (approved questions + answers)
                try {
                    $st = $pdo->prepare(
                        "SELECT q.id, q.question, q.helpful_count, q.created_at,
                                COALESCE(uq.name, uq.username, 'User') AS asker
                           FROM product_questions q
                      LEFT JOIN users uq ON uq.id = q.user_id
                          WHERE q.product_id = ? AND q.is_approved = 1
                          ORDER BY q.helpful_count DESC, q.created_at DESC LIMIT 20"
                    );
                    $st->execute([$productId]);
                    $questions = $st->fetchAll();
                    foreach ($questions as &$qRow) {
                        $sta = $pdo->prepare(
                            "SELECT a.id, a.answer, a.is_staff_answer, a.helpful_count, a.created_at,
                                    COALESCE(ua.name, ua.username, 'User') AS answerer
                               FROM product_answers a
                          LEFT JOIN users ua ON ua.id = a.user_id
                              WHERE a.question_id = ? AND a.is_approved = 1
                              ORDER BY a.is_staff_answer DESC, a.helpful_count DESC LIMIT 5"
                        );
                        $sta->execute([(int)$qRow['id']]);
                        $qRow['answers'] = $sta->fetchAll();
                    }
                    unset($qRow);
                } catch (Throwable $_) { $questions = []; }

                // Product relations (upsell / cross_sell / accessory / alternative)
                try {
                    $st = $pdo->prepare(
                        "SELECT pr.relation_type,
                                p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug, p2.stock_status,
                                (SELECT pp2.price FROM product_pricing pp2
                                   WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS price,
                                (SELECT pp2.currency_code FROM product_pricing pp2
                                   WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS currency_code,
                                (SELECT i2.url FROM images i2
                                   WHERE i2.owner_id = p2.id ORDER BY i2.id ASC LIMIT 1) AS image_url
                           FROM product_relations pr
                      INNER JOIN products p2 ON p2.id = pr.related_product_id AND p2.is_active = 1
                       LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                          WHERE pr.product_id = ?
                          ORDER BY pr.sort_order ASC, p2.id ASC LIMIT 20"
                    );
                    $st->execute([$lang, $productId]);
                    $relations = $st->fetchAll();
                } catch (Throwable $_) { $relations = []; }
            }
        }
    } catch (Throwable $e) {
        error_log('[product.php] PDO load failed: ' . $e->getMessage());
        $product = null;
    }
}


// ============================================================
// Step 2: HTTP API fallback
// ============================================================
if (!$product) {
    $_apiQs   = http_build_query(array_filter([
        'id'   => $productId  ?: null,
        'slug' => $productSlug ?: null,
        'lang' => $lang,
    ]));
    $_apiResp = pub_fetch(pub_api_url('public/products') . '?' . $_apiQs);
    if (!empty($_apiResp['data']['product'])) {
        $product    = $_apiResp['data']['product'];
        $images     = $_apiResp['data']['images']     ?? [];
        $categories = $_apiResp['data']['categories'] ?? [];
        $related    = $_apiResp['data']['related']    ?? [];
        if (!$productId && !empty($product['id'])) {
            $productId = (int)$product['id'];
        }
    }
}


// ============================================================
// Step 3: Secondary data via PDO (if API was used as fallback)
// ============================================================
if ($product && $pdo && $productId) {
    if (empty($variants)) {
        try {
            $st = $pdo->prepare(
                "SELECT pv.id, pv.sku, pv.stock_quantity, pv.is_default,
                        (SELECT pp.price FROM product_pricing pp
                           WHERE pp.product_id = ? AND pp.variant_id = pv.id
                           ORDER BY pp.id ASC LIMIT 1) AS price,
                        (SELECT pp.currency_code FROM product_pricing pp
                           WHERE pp.product_id = ? AND pp.variant_id = pv.id
                           ORDER BY pp.id ASC LIMIT 1) AS currency_code
                   FROM product_variants pv
                  WHERE pv.product_id = ? AND pv.is_active = 1
                  ORDER BY pv.is_default DESC, pv.id ASC LIMIT 20"
            );
            $st->execute([$productId, $productId, $productId]);
            $variants = $st->fetchAll();
        } catch (Throwable $_) { $variants = []; }
    }
    if (empty($reviews)) {
        try {
            $st = $pdo->prepare(
                "SELECT r.id, r.rating, r.title, r.comment, r.is_verified_purchase,
                        r.helpful_count, r.created_at,
                        COALESCE(u.name, u.username, 'User') AS author
                   FROM product_reviews r
              LEFT JOIN users u ON u.id = r.user_id
                  WHERE r.product_id = ? AND r.is_approved = 1
                  ORDER BY r.helpful_count DESC, r.created_at DESC LIMIT 30"
            );
            $st->execute([$productId]);
            $reviews = $st->fetchAll();
        } catch (Throwable $_) { $reviews = []; }
    }
}


// ============================================================
// Derived variables
// ============================================================
$productName  = $product['name']              ?? $product['slug'] ?? '';
$productDesc  = $product['description']       ?? $product['short_description'] ?? '';
$price        = $product['price']             ?? null;
$comparePrice = $product['compare_at_price']  ?? null;
$currency     = $product['currency_code']     ?? '';
$stockStatus  = $product['stock_status']      ?? '';
$stockQty     = (int)($product['stock_quantity'] ?? 0);
$mainImage    = $product['image_url']         ?? '';
$thumbImage   = $product['image_thumb_url']   ?? $mainImage;
$brandName    = $product['brand_name']        ?? '';
$specs        = $product['specifications']    ?? '';
$isFeatured   = !empty($product['is_featured']);
$isNew        = !empty($product['is_new']);
$isBestseller = !empty($product['is_bestseller']);
$inStock      = in_array($stockStatus, ['in_stock', ''], true);

// Build gallery — always include main image first
if (empty($images) && $mainImage) {
    $images = [['url' => $mainImage, 'thumb_url' => $thumbImage, 'alt_text' => $productName]];
}

// Discounts for current product + relation/related products
$allProdIds = [$productId];
foreach ($relations as $r) { $allProdIds[] = $r['id'] ?? 0; }
foreach ($related   as $r) { $allProdIds[] = $r['id'] ?? 0; }
$productDiscounts = pub_get_product_discounts($pdo, $allProdIds);


// ============================================================
// Globals / SEO
// ============================================================
$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';

if (!$product) {
    $GLOBALS['PUB_PAGE_TITLE'] = e(t('products.not_found_title', ['default' => 'Product Not Found'])) . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<main class="pub-container" style="padding:60px 0;text-align:center;">';
    echo '<i class="bi bi-search" style="font-size:3.5rem;color:var(--text-secondary,#999);"></i>';
    echo '<h1 style="margin:16px 0 8px;">' . e(t('products.not_found_title', ['default' => 'Product Not Found'])) . '</h1>';
    echo '<p style="color:var(--text-secondary);">' . e(t('products.not_found_msg', ['default' => 'This product is unavailable or does not exist.'])) . '</p>';
    echo '<a href="/frontend/public/products.php" class="pub-btn pub-btn--primary" style="margin-top:24px;display:inline-block;">'
       . e(t('nav.products')) . '</a>';
    echo '</main>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e($productName) . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = strip_tags($productDesc ?: $productName);

$_seoMeta = pub_get_seo_meta('product', $productId, $lang);
if (!empty($_seoMeta['title']))       $GLOBALS['PUB_PAGE_TITLE'] = e($_seoMeta['title']);
if (!empty($_seoMeta['description'])) $GLOBALS['PUB_PAGE_DESC']  = e($_seoMeta['description']);

$GLOBALS['PUB_SEO'] = [
    'canonical_url'       => $_seoMeta['canonical_url']  ?? '',
    'robots'              => $_seoMeta['robots']          ?? 'index,follow',
    'keywords'            => $_seoMeta['keywords']        ?? implode(',', array_column($categories, 'name')),
    'og_title'            => $_seoMeta['og_title']        ?? $productName,
    'og_description'      => $_seoMeta['og_description']  ?? strip_tags($productDesc ?: ''),
    'og_image'            => $_seoMeta['og_image']        ?? $mainImage,
    'og_type'             => 'product',
    'schema_markup'       => $_seoMeta['schema_markup']   ?? '',
    'schema_type'         => 'Product',
    'schema_name'         => $productName,
    'schema_image'        => $mainImage ? pub_img($mainImage) : '',
    'schema_description'  => strip_tags($productDesc ?: ''),
    'schema_sku'          => $product['sku']   ?? '',
    'schema_price'        => $price,
    'schema_currency'     => $currency,
    'schema_availability' => $inStock
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
    'schema_rating'       => !empty($product['rating_count']) ? [
        'ratingValue' => number_format((float)($product['rating_average'] ?? 0), 1),
        'reviewCount' => (int)($product['rating_count'] ?? 0),
    ] : null,
];

// Card styles from DB
$_productCardStyle = pub_card_inline_style('product');
$_productCardClass = pub_card_css_class('product');
$_productImgStyle  = pub_card_img_style('product');

include dirname(__DIR__) . '/partials/header.php';
?>

<!-- ============================================================
     BREADCRUMB
============================================================ -->
<div class="pub-breadcrumb-bar">
    <div class="pub-container">
        <nav aria-label="breadcrumb" class="pub-breadcrumb">
            <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
            <i class="bi bi-chevron-right pub-breadcrumb__sep" aria-hidden="true"></i>
            <a href="/frontend/public/products.php"><?= e(t('nav.products')) ?></a>
            <?php foreach ($categories as $cat): ?>
            <i class="bi bi-chevron-right pub-breadcrumb__sep" aria-hidden="true"></i>
            <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"><?= e($cat['name'] ?? '') ?></a>
            <?php endforeach; ?>
            <i class="bi bi-chevron-right pub-breadcrumb__sep" aria-hidden="true"></i>
            <span aria-current="page"><?= e($productName) ?></span>
        </nav>
    </div>
</div>


<!-- ============================================================
     PRODUCT DETAIL
============================================================ -->
<main class="pub-container pub-product-page">

    <div class="pub-product-detail">

        <!-- ── Gallery ───────────────────────────────────── -->
        <div class="pub-product-gallery">
            <div class="pub-gallery-main" id="pubGalleryMain">
                <?php if ($mainImage): ?>
                <img src="<?= e(pub_img($mainImage)) ?>"
                     alt="<?= e($productName) ?>"
                     id="pubMainImg"
                     class="pub-gallery-main-img"
                     loading="eager">
                <?php else: ?>
                <div class="pub-gallery-placeholder" aria-hidden="true">
                    <i class="bi bi-image"></i>
                </div>
                <?php endif; ?>

                <!-- Badges -->
                <div class="pub-gallery-badges">
                    <?php if (isset($productDiscounts[$productId])): ?>
                        <span class="pub-product-badge pub-product-badge--discount">
                            <i class="bi bi-tag-fill" aria-hidden="true"></i>
                            <?= e($productDiscounts[$productId]) ?>
                        </span>
                    <?php elseif ($isFeatured): ?>
                        <span class="pub-product-badge pub-product-badge--featured">
                            <i class="bi bi-star-fill" aria-hidden="true"></i>
                            <?= e(t('products.featured')) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($isNew): ?>
                        <span class="pub-product-badge pub-product-badge--new">
                            <?= e(t('products.new')) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($isBestseller): ?>
                        <span class="pub-product-badge pub-product-badge--bestseller">
                            <i class="bi bi-fire" aria-hidden="true"></i>
                            <?= e(t('products.bestseller')) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thumbnails -->
            <?php if (count($images) > 1): ?>
            <div class="pub-gallery-thumbs" id="pubGalleryThumbs">
                <?php foreach ($images as $img): ?>
                <img src="<?= e(pub_img($img['thumb_url'] ?? $img['url'])) ?>"
                     alt="<?= e($img['alt_text'] ?? $productName) ?>"
                     class="pub-gallery-thumb"
                     loading="lazy"
                     data-full="<?= e(pub_img($img['url'])) ?>"
                     onclick="pubSwitchGallery(this)">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>


        <!-- ── Product Info ──────────────────────────────── -->
        <div class="pub-product-info">

            <?php if ($brandName): ?>
            <p class="pub-product-brand"><?= e($brandName) ?></p>
            <?php endif; ?>

            <h1 class="pub-product-detail-title"><?= e($productName) ?></h1>

            <!-- Rating -->
            <?php
            $ratingAvg   = (float)($product['rating_average'] ?? 0);
            $ratingCount = (int)($product['rating_count']     ?? 0);
            if ($ratingCount > 0):
                $starHtml = '';
                for ($s = 1; $s <= 5; $s++) {
                    if ($ratingAvg >= $s)          $starHtml .= '<i class="bi bi-star-fill pub-star pub-star--full"  aria-hidden="true"></i>';
                    elseif ($ratingAvg >= $s - 0.5) $starHtml .= '<i class="bi bi-star-half pub-star pub-star--half"  aria-hidden="true"></i>';
                    else                             $starHtml .= '<i class="bi bi-star      pub-star pub-star--empty" aria-hidden="true"></i>';
                }
            ?>
            <div class="pub-product-rating">
                <span class="pub-stars" aria-label="<?= number_format($ratingAvg, 1) ?> <?= e(t('products.out_of_5', ['default' => 'out of 5'])) ?>">
                    <?= $starHtml ?>
                </span>
                <span class="pub-rating-score"><?= number_format($ratingAvg, 1) ?></span>
                <span class="pub-rating-count">(<?= $ratingCount ?> <?= e(t('products.reviews')) ?>)</span>
            </div>
            <?php endif; ?>

            <!-- Price -->
            <?php if ($price !== null): ?>
            <div class="pub-product-price-block">
                <span class="pub-product-detail-price">
                    <?= number_format((float)$price, 2) ?> <?= e($currency) ?>
                </span>
                <?php if ($comparePrice && (float)$comparePrice > (float)$price): ?>
                <span class="pub-product-compare-price">
                    <?= number_format((float)$comparePrice, 2) ?> <?= e($currency) ?>
                </span>
                <span class="pub-product-discount-pct">
                    <?= round((1 - $price / $comparePrice) * 100) ?>%
                    <?= e(t('products.off')) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stock status -->
            <div class="pub-product-stock <?= $inStock ? 'pub-stock--in' : 'pub-stock--out' ?>">
                <?php if ($inStock): ?>
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <?= e(t('products.in_stock')) ?>
                    <?php if ($stockQty > 0 && $stockQty <= 10): ?>
                    &mdash;
                    <span class="pub-stock-low">
                        <?= e(t('products.low_stock', ['count' => $stockQty])) ?>
                    </span>
                    <?php endif; ?>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                    <?= e(t('products.out_of_stock')) ?>
                <?php endif; ?>
            </div>

            <!-- Short description -->
            <?php if (!empty($product['short_description'])): ?>
            <p class="pub-product-short-desc"><?= e($product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Variants -->
            <?php if (!empty($variants)): ?>
            <div class="pub-variant-wrap">
                <label class="pub-variant-label">
                    <?= e(t('products.variant', ['default' => 'Choose Option'])) ?>:
                </label>
                <div class="pub-variant-btns" id="pubVariantBtns">
                    <?php foreach ($variants as $v):
                        $vActive = !empty($v['is_default']);
                        $vLabel  = $v['sku'] ?? 'Variant ' . $v['id'];
                        $vStock  = (int)($v['stock_quantity'] ?? 0);
                        $vPrice  = $v['price'] ?? null;
                    ?>
                    <button type="button"
                            class="pub-variant-btn<?= $vActive ? ' active' : '' ?><?= $vStock <= 0 ? ' pub-variant-btn--out' : '' ?>"
                            data-variant-id="<?= (int)$v['id'] ?>"
                            data-price="<?= e((string)($vPrice ?? $price ?? '')) ?>"
                            data-currency="<?= e($v['currency_code'] ?? $currency) ?>"
                            data-stock="<?= $vStock ?>"
                            onclick="pubSelectVariant(this)"
                            <?= $vStock <= 0 ? 'aria-disabled="true"' : '' ?>>
                        <?= e($vLabel) ?>
                        <?php if ($vPrice !== null): ?>
                        <small><?= number_format((float)$vPrice, 2) ?> <?= e($v['currency_code'] ?? $currency) ?></small>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Add to cart row -->
            <?php if ($inStock): ?>
            <div class="pub-product-cart-row">

                <!-- Quantity -->
                <div class="pub-qty-wrap">
                    <button class="pub-qty-btn" onclick="pubQtyChange(-1)" type="button"
                            aria-label="<?= e(t('cart.decrease_qty', 'Decrease')) ?>">
                        <i class="bi bi-dash" aria-hidden="true"></i>
                    </button>
                    <input type="number"
                           id="pubQtyInput"
                           class="pub-qty-input"
                           value="1" min="1"
                           max="<?= $stockQty > 0 ? (int)$stockQty : 999 ?>"
                           aria-label="<?= e(t('cart.quantity', 'Quantity')) ?>">
                    <button class="pub-qty-btn" onclick="pubQtyChange(1)" type="button"
                            aria-label="<?= e(t('cart.increase_qty', 'Increase')) ?>">
                        <i class="bi bi-plus" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Add to cart -->
                <button class="pub-btn pub-btn--primary pub-add-to-cart"
                        id="pubAddToCartBtn"
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-product-name="<?= e($productName) ?>"
                        data-product-price="<?= e((string)($price ?? '0')) ?>"
                        data-product-image="<?= e(pub_img($mainImage ?? null)) ?>"
                        data-currency="<?= e($currency) ?>"
                        data-entity-id="<?= (int)($entityId ?: ($product['entity_id'] ?? 1)) ?>"
                        data-added-text="<?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    <i class="bi bi-cart-plus" aria-hidden="true"></i>
                    <?= e(t('cart.add')) ?>
                </button>

                <!-- Wishlist -->
                <button class="pub-btn pub-btn--ghost pub-wishlist-btn"
                        type="button"
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-entity-id="<?= (int)($entityId ?: ($product['entity_id'] ?? 1)) ?>"
                        onclick="pubToggleWishlist(this)"
                        title="<?= e(t('wishlist.add')) ?>"
                        aria-label="<?= e(t('wishlist.add')) ?>">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                </button>

                <!-- Compare -->
                <button type="button"
                        id="pubCompareBtn"
                        class="pub-btn pub-btn--ghost"
                        data-product-id="<?= (int)$product['id'] ?>"
                        onclick="pubToggleCompare(this)"
                        title="<?= e(t('products.compare', ['default' => 'Compare'])) ?>"
                        aria-label="<?= e(t('products.compare', ['default' => 'Compare'])) ?>">
                    <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                    <span class="pub-compare-label"><?= e(t('products.compare', ['default' => 'Compare'])) ?></span>
                </button>

            </div>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (!empty($categories)): ?>
            <div class="pub-product-cats">
                <span class="pub-product-cat-label">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <?= e(t('products.categories')) ?>:
                </span>
                <?php foreach ($categories as $cat): ?>
                <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"
                   class="pub-cat-tag">
                    <?= e($cat['name'] ?? '') ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SKU -->
            <?php if (!empty($product['sku'])): ?>
            <p class="pub-product-sku">
                <i class="bi bi-upc-scan" aria-hidden="true"></i>
                <?= e(t('products.sku')) ?>: <span><?= e($product['sku']) ?></span>
            </p>
            <?php endif; ?>

        </div><!-- /.pub-product-info -->
    </div><!-- /.pub-product-detail -->


    <!-- ============================================================
         TABS: Description / Specs / Reviews / Q&A
    ============================================================ -->
    <div class="pub-section">
        <div class="pub-tabs" id="pubDetailTabs">
            <?php if ($productDesc): ?>
            <button class="pub-tab active" onclick="pubTabSwitch(this,'pubDescPanel')" type="button">
                <?= e(t('products.description')) ?>
            </button>
            <?php endif; ?>
            <?php if ($specs): ?>
            <button class="pub-tab<?= $productDesc ? '' : ' active' ?>"
                    onclick="pubTabSwitch(this,'pubSpecPanel')" type="button">
                <?= e(t('products.specifications')) ?>
            </button>
            <?php endif; ?>
            <button class="pub-tab<?= (!$productDesc && !$specs) ? ' active' : '' ?>"
                    onclick="pubTabSwitch(this,'pubReviewsPanel')" type="button">
                <?= e(t('products.reviews')) ?>
                <?php if (!empty($reviews)): ?>
                <span class="pub-tab-count"><?= count($reviews) ?></span>
                <?php endif; ?>
            </button>
            <button class="pub-tab" onclick="pubTabSwitch(this,'pubQaPanel')" type="button">
                <?= e(t('products.qa', 'Q&amp;A')) ?>
                <?php if (!empty($questions)): ?>
                <span class="pub-tab-count"><?= count($questions) ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Description panel -->
        <?php if ($productDesc): ?>
        <div id="pubDescPanel" class="pub-tab-panel pub-product-desc">
            <?= nl2br(e($productDesc)) ?>
        </div>
        <?php endif; ?>

        <!-- Specifications panel -->
        <?php if ($specs): ?>
        <div id="pubSpecPanel" class="pub-tab-panel pub-product-specs"
             style="<?= $productDesc ? 'display:none;' : '' ?>">
            <?= nl2br(e($specs)) ?>
        </div>
        <?php endif; ?>

        <!-- Reviews panel -->
        <div id="pubReviewsPanel" class="pub-tab-panel"
             style="<?= ($productDesc || $specs) ? 'display:none;' : '' ?>">

            <?php if (!empty($reviews)): ?>
            <div class="pub-reviews-list">
                <?php foreach ($reviews as $rv): ?>
                <div class="pub-review-item">
                    <div class="pub-review-header">
                        <span class="pub-review-stars" aria-label="<?= (int)($rv['rating'] ?? 0) ?> stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="bi <?= $s <= (int)($rv['rating'] ?? 0) ? 'bi-star-fill' : 'bi-star' ?> pub-star" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </span>
                        <strong class="pub-review-author"><?= e($rv['author'] ?? '') ?></strong>
                        <?php if (!empty($rv['is_verified_purchase'])): ?>
                        <span class="pub-review-verified">
                            <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                            <?= e(t('products.verified_purchase')) ?>
                        </span>
                        <?php endif; ?>
                        <time class="pub-review-date" datetime="<?= e(substr($rv['created_at'] ?? '', 0, 10)) ?>">
                            <?= e(substr($rv['created_at'] ?? '', 0, 10)) ?>
                        </time>
                    </div>
                    <?php if (!empty($rv['title'])): ?>
                    <p class="pub-review-title"><?= e($rv['title']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($rv['comment'])): ?>
                    <p class="pub-review-comment"><?= e($rv['comment']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pub-empty-state">
                <i class="bi bi-chat-square-text pub-empty-state__icon" aria-hidden="true"></i>
                <p><?= e(t('products.no_reviews')) ?></p>
            </div>
            <?php endif; ?>

            <!-- Write a review (login-gated) -->
            <?php if ($_isLoggedIn): ?>
            <div class="pub-review-form">
                <h4 class="pub-review-form__title"><?= e(t('products.write_review')) ?></h4>
                <div class="pub-star-picker">
                    <label><?= e(t('products.your_rating')) ?></label>
                    <div id="pubStarPicker" class="pub-star-picker__stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star pub-star-pick" data-val="<?= $i ?>" onclick="pubPickStar(<?= $i ?>)" role="button" tabindex="0" aria-label="<?= $i ?> stars"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="pubReviewRating" value="0">
                </div>
                <input type="text" id="pubReviewTitle"
                       class="pub-input"
                       placeholder="<?= e(t('products.review_title')) ?>">
                <textarea id="pubReviewComment" rows="3"
                          class="pub-input"
                          placeholder="<?= e(t('products.review_comment')) ?>"></textarea>
                <button onclick="pubSubmitReview(<?= (int)$productId ?>)"
                        class="pub-btn pub-btn--primary">
                    <?= e(t('products.submit_review')) ?>
                </button>
                <span id="pubReviewMsg" class="pub-form-msg"></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Q&A panel -->
        <div id="pubQaPanel" class="pub-tab-panel" style="display:none;">

            <?php if (!empty($questions)): ?>
            <div class="pub-qa-list">
                <?php foreach ($questions as $q): ?>
                <div class="pub-qa-item">
                    <div class="pub-qa-question">
                        <i class="bi bi-question-circle pub-qa-icon" aria-hidden="true"></i>
                        <div>
                            <p class="pub-qa-text"><?= e($q['question'] ?? '') ?></p>
                            <span class="pub-qa-meta">
                                <?= e($q['asker'] ?? '') ?>
                                &middot;
                                <?= e(substr($q['created_at'] ?? '', 0, 10)) ?>
                            </span>
                        </div>
                    </div>
                    <?php foreach ((array)($q['answers'] ?? []) as $ans): ?>
                    <div class="pub-qa-answer">
                        <i class="bi bi-reply pub-qa-answer__icon" aria-hidden="true"></i>
                        <div>
                            <p class="pub-qa-answer__text"><?= e($ans['answer'] ?? '') ?></p>
                            <span class="pub-qa-meta">
                                <?= e($ans['answerer'] ?? '') ?>
                                <?php if (!empty($ans['is_staff_answer'])): ?>
                                <span class="pub-qa-staff">
                                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                                    Staff
                                </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pub-empty-state">
                <i class="bi bi-question-circle pub-empty-state__icon" aria-hidden="true"></i>
                <p><?= e(t('products.no_questions')) ?></p>
            </div>
            <?php endif; ?>

            <!-- Ask a question (login-gated) -->
            <?php if ($_isLoggedIn): ?>
            <div class="pub-review-form">
                <h4 class="pub-review-form__title"><?= e(t('products.ask_question')) ?></h4>
                <textarea id="pubQuestionText" rows="2"
                          class="pub-input"
                          placeholder="<?= e(t('products.question_placeholder')) ?>"></textarea>
                <button onclick="pubSubmitQuestion(<?= (int)$productId ?>)"
                        class="pub-btn pub-btn--primary">
                    <i class="bi bi-send" aria-hidden="true"></i>
                    <?= e(t('products.submit_question')) ?>
                </button>
                <span id="pubQaMsg" class="pub-form-msg"></span>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ============================================================
         PRODUCT RELATIONS (upsell / cross-sell / accessory / alternative)
    ============================================================ -->
    <?php
    $relGroups = [];
    foreach ($relations as $r) {
        $relGroups[$r['relation_type']][] = $r;
    }
    $relTitles = [
        'upsell'      => t('products.upsell'),
        'cross_sell'  => t('products.cross_sell'),
        'accessory'   => t('products.accessories'),
        'alternative' => t('products.alternatives'),
    ];
    foreach ($relGroups as $rtype => $rItems): ?>
    <section class="pub-section">
        <div class="pub-section-head">
            <h2 class="pub-section-title">
                <?= e($relTitles[$rtype] ?? ucwords(str_replace('_', ' ', $rtype))) ?>
            </h2>
        </div>
        <div class="pub-grid">
            <?php foreach ($rItems as $p): ?>
            <div class="pub-product-card<?= $_productCardClass ? ' ' . e($_productCardClass) : '' ?>"
                 <?= $_productCardStyle ? 'style="' . e($_productCardStyle) . '"' : '' ?>>
                <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>" class="pub-product-card__link">
                    <div class="pub-cat-img-wrap" style="<?= e($_productImgStyle) ?>">
                        <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= e(pub_img($p['image_url'], 'product')) ?>"
                             alt="<?= e($p['name'] ?? '') ?>"
                             class="pub-cat-img" loading="lazy"
                             onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                        <span class="pub-img-placeholder" hidden aria-hidden="true">
                            <i class="bi bi-image"></i>
                        </span>
                        <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">
                            <i class="bi bi-image"></i>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="pub-product-card-body">
                        <?php if (isset($productDiscounts[$p['id'] ?? 0])): ?>
                        <span class="pub-product-badge pub-product-badge--discount">
                            <i class="bi bi-tag-fill" aria-hidden="true"></i>
                            <?= e($productDiscounts[$p['id'] ?? 0]) ?>
                        </span>
                        <?php endif; ?>
                        <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                        <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? '') ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </a>
                <button class="pub-cart-btn" onclick="pubAddToCart(this)"
                        data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                        data-product-name="<?= e($p['name'] ?? '') ?>"
                        data-product-price="<?= e($p['price'] ?? '0') ?>"
                        data-product-image="<?= e($p['image_url'] ?? '') ?>"
                        data-currency="<?= e($p['currency_code'] ?? '') ?>"
                        data-entity-id="<?= (int)($entityId ?: ($product['entity_id'] ?? 1)) ?>">
                    <i class="bi bi-cart-plus" aria-hidden="true"></i>
                    <?= e(t('cart.add')) ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>


    <!-- ============================================================
         RELATED PRODUCTS
    ============================================================ -->
    <?php if (!empty($related)): ?>
    <section class="pub-section">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('products.related')) ?></h2>
        </div>
        <div class="pub-grid">
            <?php foreach ($related as $p): ?>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card<?= $_productCardClass ? ' ' . e($_productCardClass) : '' ?>"
               style="text-decoration:none;<?= $_productCardStyle ? e($_productCardStyle) : '' ?>">
                <div class="pub-cat-img-wrap" style="<?= e($_productImgStyle) ?>">
                    <?php if (!empty($p['image_url'])): ?>
                    <img src="<?= e(pub_img($p['image_url'], 'product')) ?>"
                         alt="<?= e($p['name'] ?? '') ?>"
                         class="pub-cat-img" loading="lazy"
                         onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <span class="pub-img-placeholder" hidden aria-hidden="true">
                        <i class="bi bi-image"></i>
                    </span>
                    <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true">
                        <i class="bi bi-image"></i>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <?php if (isset($productDiscounts[$p['id'] ?? 0])): ?>
                    <span class="pub-product-badge pub-product-badge--discount">
                        <i class="bi bi-tag-fill" aria-hidden="true"></i>
                        <?= e($productDiscounts[$p['id'] ?? 0]) ?>
                    </span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? '') ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>


<script>
/* ============================================================
   product.php — Inline JS
============================================================ */

// Tab switcher
function pubTabSwitch(btn, panelId) {
    document.querySelectorAll('.pub-tab').forEach(function (t) { t.classList.remove('active'); });
    document.querySelectorAll('.pub-tab-panel').forEach(function (p) { p.style.display = 'none'; });
    btn.classList.add('active');
    var panel = document.getElementById(panelId);
    if (panel) panel.style.display = '';
}

// Gallery thumbnail switch
function pubSwitchGallery(thumb) {
    document.querySelectorAll('.pub-gallery-thumb').forEach(function (t) { t.classList.remove('active'); });
    thumb.classList.add('active');
    var mainImg = document.getElementById('pubMainImg');
    if (mainImg && thumb.dataset.full) mainImg.src = thumb.dataset.full;
}

// Activate first thumbnail on load
document.addEventListener('DOMContentLoaded', function () {
    var first = document.querySelector('.pub-gallery-thumb');
    if (first) first.classList.add('active');
    pubUpdateCompareBadge();
    // Restore compare state
    var compareBtn = document.getElementById('pubCompareBtn');
    if (compareBtn) {
        var pid    = compareBtn.dataset.productId;
        var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
        if (inList.indexOf(pid) >= 0) {
            compareBtn.querySelector('.pub-compare-label').textContent = '<?= e(t('products.in_compare', 'In Compare')) ?>';
            compareBtn.classList.add('active');
        }
    }
});

// Quantity stepper
function pubQtyChange(delta) {
    var input = document.getElementById('pubQtyInput');
    if (!input) return;
    var val = parseInt(input.value || '1', 10) + delta;
    var min = parseInt(input.min || '1', 10);
    var max = parseInt(input.max || '999', 10);
    input.value = Math.min(Math.max(val, min), max);
}

// Variant selector
function pubSelectVariant(btn) {
    document.querySelectorAll('#pubVariantBtns .pub-variant-btn').forEach(function (b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    var price    = btn.dataset.price;
    var currency = btn.dataset.currency;
    var stock    = parseInt(btn.dataset.stock || '0', 10);

    // Update displayed price
    var priceEl = document.querySelector('.pub-product-detail-price');
    if (priceEl && price) priceEl.textContent = parseFloat(price).toFixed(2) + ' ' + currency;

    // Update cart button
    var cartBtn = document.getElementById('pubAddToCartBtn');
    if (cartBtn) {
        cartBtn.dataset.productPrice = price;
        cartBtn.dataset.currency     = currency;
        cartBtn.dataset.variantId    = btn.dataset.variantId;
    }

    // Update stock badge
    var stockEl = document.querySelector('.pub-product-stock');
    if (stockEl) {
        stockEl.className = 'pub-product-stock ' + (stock > 0 ? 'pub-stock--in' : 'pub-stock--out');
    }
}

// Compare toggle
function pubToggleCompare(btn) {
    var pid    = btn.dataset.productId;
    var label  = btn.querySelector('.pub-compare-label');
    var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
    var idx    = inList.indexOf(pid);

    if (idx >= 0) {
        inList.splice(idx, 1);
        if (label) label.textContent = '<?= e(t('products.compare', 'Compare')) ?>';
        btn.classList.remove('active');
        fetch('/api/public/compare/remove', {
            method: 'POST', credentials: 'include',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + pid
        });
    } else {
        if (inList.length >= 4) { alert('<?= e(t('products.compare_max', 'Max 4 products can be compared.')) ?>'); return; }
        inList.push(pid);
        if (label) label.textContent = '<?= e(t('products.in_compare', 'In Compare')) ?>';
        btn.classList.add('active');
        fetch('/api/public/compare/add', {
            method: 'POST', credentials: 'include',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + pid
        });
    }

    localStorage.setItem('pub_compare', inList.join(','));
    pubUpdateCompareBadge();
}

function pubUpdateCompareBadge() {
    var n = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean).length;
    document.querySelectorAll('.pub-compare-badge').forEach(function (el) {
        el.textContent    = n;
        el.style.display  = n > 0 ? 'inline-flex' : 'none';
    });
}

// Star picker (review form)
function pubPickStar(val) {
    document.querySelectorAll('#pubStarPicker .pub-star-pick').forEach(function (el, i) {
        el.className = 'bi ' + (i < val ? 'bi-star-fill' : 'bi-star') + ' pub-star-pick';
    });
    document.getElementById('pubReviewRating').value = val;
}

// Submit review
function pubSubmitReview(productId) {
    var rating  = parseInt(document.getElementById('pubReviewRating').value  || '0', 10);
    var title   = (document.getElementById('pubReviewTitle').value   || '').trim();
    var comment = (document.getElementById('pubReviewComment').value || '').trim();
    var msg     = document.getElementById('pubReviewMsg');

    if (rating < 1 || rating > 5) {
        msg.textContent = '<?= e(t('products.rating_required', 'Please select a rating.')) ?>';
        msg.className   = 'pub-form-msg pub-form-msg--error';
        return;
    }
    msg.textContent = '...';
    msg.className   = 'pub-form-msg';

    fetch('/api/public/products/' + productId + '/reviews', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'rating=' + rating + '&title=' + encodeURIComponent(title) + '&comment=' + encodeURIComponent(comment)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.success) {
            msg.textContent = '<?= e(t('products.review_pending', 'Review submitted — awaiting approval.')) ?>';
            msg.className   = 'pub-form-msg pub-form-msg--success';
            document.getElementById('pubReviewTitle').value   = '';
            document.getElementById('pubReviewComment').value = '';
            pubPickStar(0);
        } else {
            msg.textContent = d.message || '<?= e(t('common.error', 'Error')) ?>';
            msg.className   = 'pub-form-msg pub-form-msg--error';
        }
    })
    .catch(function () {
        msg.textContent = '<?= e(t('common.network_error', 'Network error.')) ?>';
        msg.className   = 'pub-form-msg pub-form-msg--error';
    });
}

// Submit question
function pubSubmitQuestion(productId) {
    var question = (document.getElementById('pubQuestionText').value || '').trim();
    var msg      = document.getElementById('pubQaMsg');

    if (!question) {
        msg.textContent = '<?= e(t('products.question_required', 'Please enter your question.')) ?>';
        msg.className   = 'pub-form-msg pub-form-msg--error';
        return;
    }
    msg.textContent = '...';
    msg.className   = 'pub-form-msg';

    fetch('/api/public/products/' + productId + '/questions', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'question=' + encodeURIComponent(question)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.success) {
            msg.textContent = '<?= e(t('products.question_pending', 'Question submitted — awaiting review.')) ?>';
            msg.className   = 'pub-form-msg pub-form-msg--success';
            document.getElementById('pubQuestionText').value = '';
        } else {
            msg.textContent = d.message || '<?= e(t('common.error', 'Error')) ?>';
            msg.className   = 'pub-form-msg pub-form-msg--error';
        }
    })
    .catch(function () {
        msg.textContent = '<?= e(t('common.network_error', 'Network error.')) ?>';
        msg.className   = 'pub-form-msg pub-form-msg--error';
    });
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>