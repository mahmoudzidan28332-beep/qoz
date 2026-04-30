<?php
/**
 * frontend/public/products.php
 * QOOQZ — Public Products Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];
$activeEntity = is_array($ctx['active_entity'] ?? null) ? $ctx['active_entity'] : [];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('products.page_title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'products';

// Will be confirmed once $onSale is set (a few lines below)
$_onSalePage = !empty($_GET['sale']) || !empty($_GET['on_sale']);

/* Filters */
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$search  = trim($_GET['q'] ?? '');
$brandId = (int)($_GET['brand_id'] ?? 0);
$catId    = (int)($_GET['category_id'] ?? 0);
$entityId = (int)($_GET['entity_id'] ?? ($activeEntity['id'] ?? 0));
$onSale   = !empty($_GET['sale']) || !empty($_GET['on_sale']);   // ?sale=1 → show discounted items
$sort    = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest'], true) ? ($_GET['sort'] ?? 'newest') : 'newest';

/* Fetch — PDO-first (ADMIN_DB is null on LiteSpeed; direct PDO always works) */
$products = [];
$total    = 0;
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $where  = ['1=1'];
        $params = [];

        // Tenant filter
        if ($tenantId) { $where[] = 'p.tenant_id = ?'; $params[] = $tenantId; }

        // Search
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(pt.name LIKE ? OR p.sku LIKE ?)';
            $params[] = $like; $params[] = $like;
        }

        // Brand
        if ($brandId) { $where[] = 'p.brand_id = ?'; $params[] = $brandId; }

        // Category
        if ($catId) {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)';
            $params[] = $catId;
        }

        // Entity-Specific "Soft" Category Filtering
        if ($entityId) {
            $where[] = "(
                NOT EXISTS (SELECT 1 FROM entity_categories WHERE entity_id = ? AND is_active = 1)
                OR EXISTS (
                    SELECT 1 FROM product_categories pc_ec
                    JOIN entity_categories ec ON ec.category_id = pc_ec.category_id AND ec.entity_id = ? AND ec.is_active = 1
                    WHERE pc_ec.product_id = p.id
                )
            )";
            $params[] = $entityId;
            $params[] = $entityId;
        }

        // Active only
        $where[] = 'p.is_active = 1';

        $whereClause = implode(' AND ', $where);

        // Count
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM products p
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
            WHERE $whereClause");
        $cStmt->execute(array_merge([$lang], $params));
        $total = (int)$cStmt->fetchColumn();

        // Sort
        $orderBy = match($sort) {
            'price_asc'  => '(SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) ASC',
            'price_desc' => '(SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) DESC',
            default      => 'p.created_at DESC',
        };

        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT p.id, p.sku, p.slug, 
                    COALESCE(ep.is_featured, p.is_featured) AS is_featured, 
                    COALESCE(ep.stock_quantity, p.stock_quantity) AS stock_quantity, 
                    p.stock_status, p.rating_average, p.rating_count, p.tenant_id,
                    COALESCE(pt.name, p.slug) AS name,
                    (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i
                      JOIN image_types it ON it.id = i.image_type_id
                      WHERE i.owner_id = p.id AND it.name IN ('product','product_thumb')
                      ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS image_url,
                    (SELECT i.thumb_url FROM images i
                      JOIN image_types it ON it.id = i.image_type_id
                      WHERE i.owner_id = p.id AND it.name IN ('product','product_thumb')
                      ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS image_thumb_url,
                    (SELECT oi.entity_id FROM order_items oi WHERE oi.product_id = p.id LIMIT 1) AS entity_id
             FROM products p
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
             LEFT JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = ?
             WHERE $whereClause
             ORDER BY $orderBy
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(array_merge([$lang, $entityId], $params));
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Fetch active discounts for the product IDs in this page ──────
        $productDiscounts = [];  // product_id -> discount_label
        if ($products) {
            $pids = array_column($products, 'id');
            $productDiscounts = pub_get_product_discounts($pdo, $pids);
        }
    } catch (\RuntimeException $e) {
        error_log('[products.php] PDO error: ' . $e->getMessage());
    }
}
// HTTP fallback if PDO unavailable
if (!$products && !$pdo) {
    $qs = http_build_query(array_filter([
        'lang' => $lang, 'page' => $page, 'limit' => $limit,
        'tenant_id' => $tenantId, 'brand_id' => $brandId ?: null,
        'category_id' => $catId ?: null, 'search' => $search ?: null,
    ]));
    $resp     = pub_fetch(pub_api_url('public/products') . '?' . $qs);
    $products = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
    $total    = (int)(($resp['data']['meta']['total'] ?? count($products)));
}
$totalPg = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;

include dirname(__DIR__) . '/partials/header.php';
$_productCardStyle = pub_card_inline_style('product');
$_productCardClass = pub_card_css_class('product');
$_productImgStyle  = pub_card_img_style('product');
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.products')) ?></span>
    </nav>

    <!-- Page title -->
    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">
            <?php if ($_onSalePage): ?>
                <i class="bi bi-tags" style="font-size: 1.3em; margin-inline-end: 6px;"></i>
                <?= e(t('discounts.on_sale_title', 'Offers & Discounts')) ?>
            <?php else: ?>
                <i class="bi bi-box-seam" style="font-size: 1.2em; margin-inline-end: 6px;"></i>
                <?= e(t('nav.products')) ?>
            <?php endif; ?>
        </h1>
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:0.85rem;color:var(--pub-muted);">
                <?= number_format($total) ?> <?= e(t('products.product_count')) ?>
            </span>
            <?php if (!$_onSalePage): ?>
            <a href="/frontend/public/discounts.php<?= $tenantId ? '?tenant_id='.$tenantId : '' ?>"
               style="font-size:.8rem;text-decoration:none;color:var(--pub-primary);font-weight:700;">
                <?= e(t('discounts.view_all_offers', 'View All Offers')) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="get" class="pub-filter-bar">
        <select name="sort" class="pub-filter-select" data-auto-submit>
            <option value="newest" <?= $sort==='newest'?'selected':'' ?>><?= e(t('products.sort_newest')) ?></option>
            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>><?= e(t('products.sort_price_asc')) ?></option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>><?= e(t('products.sort_price_desc')) ?></option>
        </select>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('products.filter')) ?></button>

        <?php if ($search || $sort !== 'newest' || $brandId || $catId || $entityId): ?>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('products.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Grid -->
    <?php if (!empty($products)): ?>
    <div class="pub-grid">
        <?php foreach ($products as $p): ?>
        <?php
            $pId    = (int)($p['id'] ?? 0);
            $pName  = $p['name'] ?? '';
            $pPrice = $p['price'] ?? null;
            $pCur   = $p['currency_code'] ?? 'SAR';
            $imgSrc = pub_img($p['image_thumb_url'] ?? $p['image_url'] ?? null, 'product_thumb');
        ?>
        <div class="pub-product-card<?= $_productCardClass ? ' '.$_productCardClass : '' ?>" style="position:relative;<?= e($_productCardStyle) ?>">
            <a href="/frontend/public/product.php?id=<?= $pId ?><?= $entityId ? '&entity_id=' . $entityId : '' ?>"
               style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
               aria-label="<?= e($pName) ?>">
                <div class="pub-card-img-wrap" style="<?= e($_productImgStyle) ?>">
                    <?php if ($imgSrc): ?>
                        <img src="<?= e($imgSrc) ?>"
                             alt="<?= e($pName) ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                    <?php endif; ?>
                    <!-- Hover overlay with action buttons -->
                    <div class="pub-card-overlay">
                        <button class="pub-card-action" type="button" title="<?= e(t('nav.wishlist', 'Wishlist')) ?>"
                                data-product-id="<?= $pId ?>"
                                data-entity-id="<?= $entityId ?: (int)($p['entity_id'] ?? 1) ?>"
                                onclick="event.preventDefault();event.stopPropagation();pubToggleWishlist(this)"><i class="bi bi-heart"></i></button>
                        <button class="pub-card-action" type="button" title="<?= e(t('nav.compare', 'Compare')) ?>"
                                data-product-id="<?= $pId ?>"
                                onclick="event.preventDefault();event.stopPropagation();if(typeof pubCompare==='function')pubCompare(this)"><i class="bi bi-arrow-left-right"></i></button>
                        <a class="pub-card-action" href="/frontend/public/product.php?id=<?= $pId ?><?= $entityId ? '&entity_id=' . $entityId : '' ?>"
                           title="<?= e(t('products.view_product', 'Quick View')) ?>"
                           onclick="event.stopPropagation()"><i class="bi bi-eye"></i></a>
                    </div>
                </div>
                <div class="pub-product-card-body">
                    <?php
                        $discBadge = $productDiscounts[$pId] ?? null;
                        if ($discBadge): ?>
                        <span class="pub-product-badge" style="background:var(--pub-primary,#03874e);color:#fff;"
                              title="<?= e(t('discounts.auto_apply','Auto Apply')) ?>"><?= e($discBadge) ?></span>
                    <?php elseif (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($pName) ?></p>
                    <?php
                    $pRating = round((float)($p['rating_average'] ?? 0), 1);
                    $pRatingCount = (int)($p['rating_count'] ?? 0);
                    if ($pRating > 0): ?>
                    <div class="pub-stars" style="font-size:0.85rem;margin:3px 0;" title="<?= $pRating ?>/5">
                        <?php for ($s = 1; $s <= 5; $s++):
                            if ($s <= $pRating) echo '<i class="bi bi-star-fill pub-star--full"></i>';
                            elseif ($s - 0.5 <= $pRating) echo '<i class="bi bi-star-half pub-star--half"></i>';
                            else echo '<i class="bi bi-star pub-star--empty"></i>';
                        endfor; ?>
                        <?php if ($pRatingCount > 0): ?>
                            <span class="pub-rating-count">(<?= $pRatingCount ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($p['sku'])): ?>
                        <small style="color:var(--pub-muted);font-size:0.76rem;"><?= e($p['sku']) ?></small>
                    <?php endif; ?>
                    <?php if ($pPrice !== null): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$pPrice, 2) ?>
                            <small><?= e($pCur) ?></small>
                        </p>
                    <?php endif; ?>
                    <?php
                    $pStock = $p['stock_status'] ?? '';
                    $pQty   = (int)($p['stock_quantity'] ?? 0);
                    if ($pStock === 'out_of_stock'):
                    ?>
                        <span class="pub-stock-badge pub-stock-badge--out"><?= e(t('products.out_of_stock')) ?></span>
                    <?php elseif ($pQty > 0 && $pQty <= 10): ?>
                        <span class="pub-stock-badge pub-stock-badge--low"><?= e(t('products.low_stock', ['count' => $pQty, 'default' => 'Only '.$pQty.' left'])) ?></span>
                    <?php elseif ($pStock === 'in_stock' || $pQty > 0): ?>
                        <span class="pub-stock-badge pub-stock-badge--in"><?= e(t('products.in_stock')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <!-- Add to Cart button -->
        <div class="pub-card-cart-bar">
            <button class="pub-btn pub-btn--primary pub-btn--sm"
                    type="button"
                    title="<?= e(t('cart.add', 'Add to Cart')) ?>"
                    data-product-id="<?= $pId ?>"
                    data-product-name="<?= e($pName) ?>"
                    data-product-price="<?= e((string)($pPrice ?? '0')) ?>"
                    data-product-image="<?= e($imgSrc ?: '') ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>"
                    data-currency="<?= e($pCur) ?>"
                    data-entity-id="<?= $entityId ?: (int)($p['entity_id'] ?? 1) ?>"
                    data-added-text="<i class='bi bi-check-circle'></i> <?= e(t('cart.added')) ?>"
                    onclick="event.stopPropagation();pubAddToCart(this)">
                <i class="bi bi-cart-plus" style="font-size: 1.2em;"></i>
            </button>
        </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" aria-label="Pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'sort'=>$sort,'brand_id'=>$brandId,'category_id'=>$catId]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>"
           class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>"
               class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>"
           class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon"><i class="bi bi-bag"></i></div>
        <p class="pub-empty-msg"><?= e(t('products.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
