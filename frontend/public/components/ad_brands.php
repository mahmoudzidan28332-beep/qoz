<?php
declare(strict_types=1);
/**
 * Component: ad_brands
 * Renders brand cards with logos and names.
 */
if (empty($sectionData)) {
    return;
}
?>
<div class="pub-grid-cat pub-home-slider pub-home-slider--brands">
    <?php foreach ($sectionData as $brand):
        $bId   = (int)($brand['id']   ?? 0);
        $bName = trim($brand['name']  ?? $brand['slug'] ?? t('common.unnamed', 'Unnamed'));
        $bLogo = $brand['logo_url']   ?? null;
        $bHref = '/frontend/public/brands.php?id=' . $bId;
    ?>
    <a href="<?= e($bHref) ?>"
       class="pub-cat-card"
       data-track-type="brand"
       data-track-id="<?= $bId ?>">

        <div class="pub-cat-img-wrap">
            <?php if ($bLogo): ?>
                <img src="<?= e(pub_img($bLogo, 'brand')) ?>"
                     alt="<?= e($bName) ?>"
                     class="pub-cat-img"
                     loading="lazy"
                     data-fallback-image>
                <span class="pub-img-placeholder" hidden aria-hidden="true">&#127991;</span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true">&#127991;</span>
            <?php endif; ?>
        </div>

        <div class="pub-cat-body">
            <h3 class="pub-cat-name"><?= e($bName) ?></h3>
            <?php if (!empty($brand['is_featured'])): ?>
                <?php 
                   $featuredTxt = t('brands.featured', '');
                   if (!$featuredTxt || $featuredTxt === 'brands.featured') {
                       $featuredTxt = t('products.featured', 'Featured');
                   }
                ?>
                <span class="pub-tag pub-tag--featured"><?= e($featuredTxt) ?></span>
            <?php endif; ?>
        </div>

    </a>
    <?php endforeach; ?>
</div>
