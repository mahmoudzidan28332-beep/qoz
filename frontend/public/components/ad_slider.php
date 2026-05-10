<?php
declare(strict_types=1);
/**
 * Component: ad_slider [v2.5.1]
 * Advertisement slider with navigation controls and localized labels.
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
                <span class="ads-badge"><?= e(t('ads.sponsored')) ?></span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>

    <div class="ads-slider-controls" aria-label="<?= e(t('slider.title', 'Promotions & Offers')) ?>">
        <button class="ads-prev" type="button" aria-label="<?= e(t('nav.previous', 'Previous')) ?>">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
        <div class="ads-dots" id="mainAdsDots">
            <?php foreach ($sectionData as $i => $ad): ?>
                <?php if ((int)($ad['id'] ?? 0) === 0) continue; ?>
                <button type="button"
                        class="ads-dot<?= $i === 0 ? ' active' : '' ?>"
                        aria-label="<?= e(t('slider.goto', ['n' => $i + 1])) ?>"
                        data-index="<?= $i ?>"></button>
            <?php endforeach; ?>
        </div>
        <button class="ads-next" type="button" aria-label="<?= e(t('nav.next', 'Next')) ?>">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</div>
