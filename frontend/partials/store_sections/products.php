<?php
/**
 * frontend/partials/store_sections/products.php — QOOQZ Public Store Sections
 * Grid of products for the entity, with AJAX "Show More"
 */

require_once __DIR__ . '/icons.php';

$showCategories = ($sectionSettings['show_categories'] ?? true);
$showSearch     = ($sectionSettings['show_search']     ?? true);
$showCart       = ($sectionSettings['show_cart']        ?? true);
?>

<div class="pub-entity-section-content" id="sectionProducts">
    <!-- Hierarchical category menus + search -->
    <?php if ($showCategories && !empty($categoryTree)):
        // Pre-compute which parent category (if any) contains the selected category
        $activePrimaryId = 0;
        foreach ($categoryTree as $mainCat) {
            $mId = (int)($mainCat['id'] ?? 0);
            if ($selectedCat === $mId) { $activePrimaryId = $mId; break; }
            foreach (($mainCat['children'] ?? []) as $ch) {
                if ($selectedCat === (int)($ch['id'] ?? 0)) { $activePrimaryId = $mId; break 2; }
            }
        }
    ?>
    <!-- Main category tabs (parent categories) -->
    <div class="pub-cat-tabs pub-cat-tabs--main" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;" role="tablist">
        <a href="?id=<?= $entityId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= !$selectedCat ? 'active' : '' ?>" role="tab"
           aria-selected="<?= !$selectedCat ? 'true' : 'false' ?>">
            <?= e(t('entity.all_categories')) ?>
        </a>
        <?php foreach ($categoryTree as $mainCat):
            $mainId      = (int)($mainCat['id'] ?? 0);
            $parentActive = ($activePrimaryId === $mainId);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $mainId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= $parentActive ? 'active' : '' ?>" role="tab"
           aria-selected="<?= $parentActive ? 'true' : 'false' ?>">
            <?= e($mainCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($activePrimaryId):
        // Find the active parent and render its sub-category tabs
        foreach ($categoryTree as $mainCat):
            if ((int)($mainCat['id'] ?? 0) !== $activePrimaryId) continue;
            $children = $mainCat['children'] ?? [];
            if (empty($children)) break;
    ?>
    <!-- Sub-category tabs -->
    <div class="pub-cat-tabs pub-cat-tabs--sub" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;padding-inline-start:16px;" role="tablist">
        <a href="?id=<?= $entityId ?>&cat=<?= $activePrimaryId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= ($selectedCat === $activePrimaryId) ? 'active' : '' ?>">
            <?= e(t('entity.all_in_category', 'All items')) ?>
        </a>
        <?php foreach ($children as $subCat):
            $subId = (int)($subCat['id'] ?? 0);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $subId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= $selectedCat === $subId ? 'active' : '' ?>">
            <?= e($subCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
    <div class="pub-grid" style="margin-top:20px;">
        <?php foreach ($products as $p): ?>
        <?php
            $imgSrc = pub_img($p['image_thumb_url'] ?? $p['image_url'] ?? null, 'product_thumb');
        ?>
        <div class="pub-product-card<?= $_entityProductCardClass ? ' ' . $_entityProductCardClass : '' ?>" 
             data-track-type="product"
             data-track-id="<?= (int)($p['id'] ?? 0) ?>"
             style="position:relative;<?= $_entityProductCardStyle ? e($_entityProductCardStyle) : '' ?>">
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               style="text-decoration:none;display:flex;flex-direction:column;flex:1;" aria-label="<?= e($p['name'] ?? '') ?>">
            <div class="pub-card-img-wrap" style="<?= e($_entityProductImgStyle) ?>">
                <?php if ($imgSrc): ?>
                    <img src="<?= e($imgSrc) ?>"
                         alt="<?= e($p['name'] ?? '') ?>" 
                         class="pub-cat-img" 
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><?= icon('image', 24, 'var(--pub-muted)') ?></span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true"><?= icon('image', 24, 'var(--pub-muted)') ?></span>
                <?php endif; ?>
                <!-- Hover overlay with action buttons -->
                <div class="pub-card-overlay">
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.wishlist', 'Wishlist')) ?>"
                            data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                            data-entity-id="<?= $entityId ?>"
                            onclick="event.preventDefault();event.stopPropagation();if(typeof pubToggleWishlist==='function')pubToggleWishlist(this)"><?= icon('heart', 16) ?></button>
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.compare', 'Compare')) ?>"
                            data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                            onclick="event.preventDefault();event.stopPropagation();if(typeof pubCompare==='function')pubCompare(this)"><?= icon('arrow-left-right', 16) ?></button>
                    <a class="pub-card-action" href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
                       title="<?= e(t('products.view_product', 'Quick View')) ?>"
                       onclick="event.stopPropagation()"><?= icon('eye', 16) ?></a>
                </div>
            </div>
            <div class="pub-product-card-body">
                <?php
                    $pId = (int)($p['id'] ?? 0);
                    $discBadge = $productDiscounts[$pId] ?? null;
                    if ($discBadge): ?>
                    <span class="pub-product-badge" style="background:var(--pub-primary,#03874e);color:#fff;"
                          title="<?= e(t('discounts.auto_apply','Auto Apply')) ?>"><?= e($discBadge) ?></span>
                <?php elseif (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                <?php
                $pRating = round((float)($p['rating_average'] ?? 0), 1);
                $pRatingCount = (int)($p['rating_count'] ?? 0);
                if ($pRating > 0): ?>
                <div class="pub-stars" style="font-size:0.85rem;margin:3px 0;display:flex;gap:2px;" title="<?= $pRating ?>/5">
                    <?php for ($s = 1; $s <= 5; $s++):
                        if ($s <= $pRating) echo icon('star', 13, '#f59e0b');
                        elseif ($s - 0.5 <= $pRating) echo icon('star-half', 13, '#f59e0b');
                        else echo icon('star-outline', 13, '#f59e0b');
                    endfor; ?>
                    <?php if ($pRatingCount > 0): ?>
                        <span class="pub-rating-count">(<?= $pRatingCount ?>)</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['sku'])): ?>
                    <small style="color:var(--pub-muted);font-size:0.76rem;"><?= e($p['sku']) ?></small>
                <?php endif; ?>
                <?php if (isset($p['price'])): ?>
                    <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <small><?= e($p['currency_code'] ?? '') ?></small></p>
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
            <?php if ($showCart): ?>
            <!-- Add to Cart button -->
            <div class="pub-card-cart-bar">
                <button class="pub-btn pub-btn--primary pub-btn--sm"
                        type="button"
                        title="<?= e(t('cart.add', 'Add to Cart')) ?>"
                        data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                        data-product-name="<?= e($p['name'] ?? '') ?>"
                        data-product-price="<?= (float)($p['price'] ?? 0) ?>"
                        data-product-image="<?= e($pAllImgs[0] ?? ($p['image_url'] ?? '')) ?>"
                        data-product-sku="<?= e($p['sku'] ?? '') ?>"
                        data-currency="<?= e($p['currency_code'] ?? '') ?>"
                        data-entity-id="<?= $entityId ?>"
                        data-added-text="<?= e(t('cart.added')) ?>"
                        onclick="event.stopPropagation();pubAddToCart(this)">
                    <?= icon('cart-plus', 16) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Product pagination -->
    <?php
    $totalPg = (int)($productMeta['total_pages'] ?? 1);
    if ($totalPg > 1):
        $pg_url = fn(int $pg) => '?id=' . $entityId . ($selectedCat ? '&cat=' . $selectedCat : '') . '&page=' . $pg . '#tabProducts';
    ?>
    <nav class="pub-pagination" style="margin-top:24px;">
        <a href="<?= $pg_url(max(1,$productPage-1)) ?>" class="pub-page-btn <?= $productPage<=1?'disabled':'' ?>">
            <?= e(t('pagination.prev')) ?>
        </a>
        <?php for ($i=max(1,$productPage-2); $i<=min($totalPg,$productPage+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$productPage?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$productPage+1)) ?>" class="pub-page-btn <?= $productPage>=$totalPg?'disabled':'' ?>">
            <?= e(t('pagination.next')) ?>
        </a>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <div class="pub-empty" style="text-align:center; padding: 60px 20px; opacity: 0.5;">
        <div class="pub-empty-icon" style="margin-bottom: 20px;"><?= icon('bag', 52, 'var(--pub-muted)') ?></div>
        <p class="pub-empty-msg"><?= e(t('entity.no_products')) ?></p>
    </div>
    <?php endif; ?>
</div>