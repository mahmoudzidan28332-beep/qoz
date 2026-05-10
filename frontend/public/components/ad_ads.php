<?php
declare(strict_types=1);
/**
 * Component: ad_ads [v2.5.1]
 * Advertisement hero slider with auto-play, controls, and dots navigation.
 */

if (empty($sectionData) || !is_array($sectionData)) {
    return;
}

if (!function_exists('_ad_link')) {
    function _ad_link(string $type, string $value): string {
        if (empty($value)) return '#';
        switch ($type) {
            case 'url':
                $scheme = parse_url($value, PHP_URL_SCHEME);
                return ($scheme !== null && in_array(strtolower($scheme), ['http', 'https'], true)) ? $value : '#';
            default:
                return '/frontend/public/' . $type . '.php?id=' . urlencode($value);
        }
    }
}

if (!function_exists('_ad_is_external')) {
    function _ad_is_external(string $type, string $href): bool {
        return $type === 'url' && $href !== '#';
    }
}
?>

<div class="ads-hero-slider" id="mainAdsSlider">
    <?php foreach ($sectionData as $i => $ad):
        $adId   = (int)($ad['id'] ?? 0);
        $title  = trim($ad['title'] ?? t('ads.sponsored', 'Sponsored Ad'));
        $desc   = trim($ad['description'] ?? '');
        $image  = $ad['image_url'] ?? $ad['thumb_url'] ?? '';
        $type   = $ad['target_type'] ?? '';
        $value  = $ad['target_value'] ?? '';

        $href    = _ad_link($type, $value);
        $external = _ad_is_external($type, $href);

        if ($adId === 0) continue;
    ?>
    <div class="ads-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <a href="<?= e($href) ?>" 
           class="ads-slide-link"
           <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
           data-ad-id="<?= $adId ?>">

            <?php if ($image): ?>
            <img src="<?= e(pub_img($image)) ?>" 
                 alt="<?= e($title) ?>" 
                 class="ads-slide-img"
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
            <?php endif; ?>

            <div class="ads-slide-content">
                <h2 class="ads-slide-title"><?= e($title) ?></h2>
                <?php if ($desc): ?>
                <p class="ads-slide-desc"><?= e($desc) ?></p>
                <?php endif; ?>
                <span class="ads-badge"><?= e(t('ads.sponsored', 'Sponsored')) ?></span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Controls -->
<div class="ads-slider-controls">
    <button class="ads-prev" aria-label="<?= e(t('nav.previous', 'Previous')) ?>">&lsaquo;</button>
    <div class="ads-dots" id="mainAdsDots">
        <?php foreach ($sectionData as $i => $ad): ?>
            <button class="ads-dot <?= $i === 0 ? 'active' : '' ?>" 
                    data-index="<?= $i ?>"
                    aria-label="<?= e(t('nav.go_to_slide', 'Go to slide')) ?> <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
    </div>
    <button class="ads-next" aria-label="<?= e(t('nav.next', 'Next')) ?>">&rsaquo;</button>
</div>
