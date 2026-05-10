<?php
declare(strict_types=1);
/**
 * Component: ad_categories
 * Renders a category grid with images and product counts.
 */

if (empty($sectionData)) {
    return;
}

$_clsCategory = $_cardStyles['category']['class'] ?? '';
?>
<div class="pub-grid-cat pub-home-slider pub-home-slider--categories">
    <?php foreach ($sectionData as $cat): 
        if (empty($cat['name']) && empty($cat['id'])) continue;
        $catId = (int)($cat['id'] ?? 0);
    ?>
    <a href="/frontend/public/products.php?category_id=<?= $catId ?>"
       class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?><?= $_clsCategory ? ' ' . $_clsCategory : '' ?>"
       data-track-type="category"
       data-track-id="<?= $catId ?>">
        <div class="pub-cat-img-wrap">
            <?php if (!empty($cat['image_url'])): ?>
                <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                     alt="<?= e($cat['name'] ?? '') ?>" 
                     class="pub-cat-img" 
                     loading="lazy"
                     data-fallback-image>
                <span class="pub-img-placeholder" hidden aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
            <?php endif; ?>
        </div>
        <div class="pub-cat-body">
            <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
            <?php 
               $catProductsCount = (int)($cat['product_count'] ?? $cat['products_count'] ?? 0);
               if ($catProductsCount > 0): 
                   $countTxt = t('categories.products_count', '');
                   if (!$countTxt || $countTxt === 'categories.products_count') {
                       $countTxt = 'products';
                   }
            ?>
                <span class="pub-cat-count">
                    <?= number_format($catProductsCount) ?> <?= e($countTxt) ?>
                </span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
