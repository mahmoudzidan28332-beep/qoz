<?php
declare(strict_types=1);
/**
 * Component: ad_products
 * Renders product cards (featured, new, etc.) with hover overlay and add-to-cart.
 */

if (empty($sectionData)) {
    return;
}

$pdo = pub_get_pdo();
$adProductDiscounts = pub_get_product_discounts($pdo, array_column($sectionData, 'id'));

$_clsProduct = $_cardStyles['product']['class'] ?? '';
?>
<div class="pub-grid pub-home-slider pub-home-slider--products">
    <?php foreach ($sectionData as $p):
        $pId = (int)($p['id'] ?? 0);
        $pName = trim($p['name'] ?? '');
        $pPrice = $p['price'] ?? null;
        $pCur = $p['currency_code'] ?? '';
        $pImg = pub_img($p['image_thumb_url'] ?? $p['image_url'] ?? null, 'product');
    ?>
    <div class="pub-product-card<?= $_clsProduct ? ' ' . $_clsProduct : '' ?>" 
         data-track-type="product"
         data-track-id="<?= $pId ?>">
        
        <a href="/frontend/public/product.php?id=<?= $pId ?>"
           class="pub-product-link"
           aria-label="<?= e($pName) ?>">
            <div class="pub-card-img-wrap">
                <?php if ($pImg): ?>
                    <img src="<?= e($pImg) ?>"
                         alt="<?= e($pName) ?>" 
                         class="pub-cat-img" 
                         loading="lazy"
                         data-fallback-image>
                    <span class="pub-img-placeholder" hidden aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                <?php endif; ?>
                <!-- Hover overlay -->
                <div class="pub-card-overlay">
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.wishlist', 'Wishlist')) ?>"
                            data-product-id="<?= $pId ?>"
                            data-entity-id="<?= $p['entity_id'] ?? 1 ?>"
                            data-pub-action="wishlist"><svg class="pub-icon pub-icon--md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg></button>
                    <a class="pub-card-action" href="/frontend/public/product.php?id=<?= $pId ?>"
                       title="<?= e(t('products.view_product', 'Quick View')) ?>"><svg class="pub-icon pub-icon--md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></a>
                </div>
            </div>
            <div class="pub-product-card-body">
                <?php 
                $hasDiscount = false;
                $discountText = '';
                if (isset($adProductDiscounts[$pId])) {
                    $hasDiscount = true;
                    $discountText = (string)$adProductDiscounts[$pId];
                    // If the text is just a number or contains %, we show it. 
                    // If it's a raw amount, we might want to format it.
                }
                ?>
                <?php if ($hasDiscount): 
                    $finalDiscount = $discountText;
                    if (strpos($discountText, 'discounts.') === 0) {
                        $finalDiscount = t($discountText, '');
                        // If translation failed or returned key, try to extract number
                        if (empty($finalDiscount) || $finalDiscount === $discountText) {
                            $num = preg_replace('/[^\d]/', '', $discountText);
                            $finalDiscount = $num ? '-' . $num . '%' : '';
                        }
                    }
                    if (empty($finalDiscount)) continue; // Don't show empty badges
                ?>
                    <span class="pub-product-badge pub-product-badge--discount" title="<?= e(t('discounts.auto_apply','Auto Apply')) ?>">
                        <?= e($finalDiscount) ?>
                    </span>
                <?php elseif (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                
                <p class="pub-product-name"><?= e($pName) ?></p>
                
                <div class="pub-product-price-block">
                    <?php if ($pPrice !== null): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$pPrice, 2) ?>
                            <small><?= e($pCur) ?></small>
                        </p>
                    <?php endif; ?>

                    <?php 
                    // Try to show old price if discount exists
                    // In some schema versions, $p might have regular_price or compare_at_price
                    $oldPrice = $p['regular_price'] ?? $p['compare_at_price'] ?? null;
                    if ($oldPrice && (float)$oldPrice > (float)$pPrice): ?>
                        <p class="pub-product-price-old">
                            <?= number_format((float)$oldPrice, 2) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </a>

        <div class="pub-card-cart-bar">
            <button class="pub-btn pub-btn--primary pub-btn--sm pub-card-action-cart"
                    type="button"
                    title="<?= e(t('cart.add', 'Add to Cart')) ?>"
                    data-product-id="<?= $pId ?>"
                    data-product-name="<?= e($pName) ?>"
                    data-product-price="<?= e((string)($pPrice ?? '0')) ?>"
                    data-sale-price="<?= e((string)($p['sale_price'] ?? '')) ?>"
                    data-product-image="<?= e($pImg ?: '') ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>"
                    data-currency="<?= e($pCur) ?>"
                    data-entity-id="<?= (int)($p['entity_id'] ?? 1) ?>"
                    data-default-text="<?= e(t('cart.add', 'Add to Cart')) ?>"
                    data-added-text="✅ <?= e(t('cart.added', 'Added')) ?>"
                    data-pub-action="add-cart">
                <svg class="pub-icon pub-icon--sm" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span class="pub-cart-text"><?= e(t('cart.add', 'Add to Cart')) ?></span>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
