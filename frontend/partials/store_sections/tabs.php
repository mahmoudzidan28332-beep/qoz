<?php
/**
 * frontend/partials/store_sections/tabs.php — QOOQZ Public Store Sections
 * Tabbed navigation for entity sections (optional)
 */

require_once __DIR__ . '/icons.php';

$configuredTabs = $sectionSettings['tabs'] ?? ['products', 'info', 'hours', 'location', 'offers', 'reviews'];
$firstTab = $configuredTabs[0] ?? 'products';
$tabPanelMap = [
    'products' => 'tabProducts', 'info' => 'tabInfo', 'hours' => 'tabHours',
    'location' => 'tabMap', 'offers' => 'tabDiscounts', 'reviews' => 'tabRatings',
];


$isFirstRendered = true;
?>

<style>
/* ── Tabs nav ──────────────────────────────────────── */
.pub-tabs-nav {
    display: flex;
    gap: 2px;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 0 0 1px;
    border-bottom: 1.5px solid var(--pub-border);
    margin-top: 24px;
}
.pub-tabs-nav::-webkit-scrollbar { display: none; }

.pub-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--pub-muted);
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    margin-bottom: -1.5px;
    cursor: pointer;
    white-space: nowrap;
    font-family: inherit;
    border-radius: var(--pub-radius-sm) var(--pub-radius-sm) 0 0;
    transition: color .15s, border-color .15s, background .15s;
}
.pub-tab-btn:hover { color: var(--pub-text); background: rgba(0,0,0,.03); }
.pub-tab-btn.active { color: var(--pub-primary); border-bottom-color: var(--pub-primary); }
.pub-tab-btn svg { opacity: .7; transition: opacity .15s; flex-shrink: 0; }
.pub-tab-btn:hover svg,
.pub-tab-btn.active svg { opacity: 1; }

.pub-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 999px;
    background: rgba(3,135,78,.12);
    color: var(--pub-primary);
    line-height: 1;
}
.pub-tab-btn.active .pub-tab-count {
    background: var(--pub-primary);
    color: #fff;
}
</style>

<div class="pub-container">
    <nav class="pub-tabs-nav" role="tablist" aria-label="Store sections">
        <?php
        $tabDefs = [
            'products' => ['data' => 'products', 'panel' => 'tabProducts', 'label' => t('entity.products_tab'), 'show' => true,                    'count' => null],
            'info'     => ['data' => 'info',     'panel' => 'tabInfo',     'label' => t('entity.info_tab'),     'show' => true,                    'count' => null],
            'hours'    => ['data' => 'hours',    'panel' => 'tabHours',    'label' => t('entity.hours_tab'),    'show' => true,                    'count' => null],
            'location' => ['data' => 'map',      'panel' => 'tabMap',      'label' => t('entity.location_tab'), 'show' => true,                   'count' => null],
            'offers'   => ['data' => 'discounts','panel' => 'tabDiscounts','label' => t('entity.discounts_tab'),'show' => !empty($discounts),      'count' => !empty($discounts) ? count($discounts) : null],
            'reviews'  => ['data' => 'ratings',  'panel' => 'tabRatings',  'label' => t('entity.ratings_tab'),  'show' => $entityShowReviews,      'count' => $entityRatingTotal > 0 ? $entityRatingTotal : null],
        ];
        $isFirstRendered = true;
        foreach ($configuredTabs as $tabKey):
            if (!isset($tabDefs[$tabKey]) || !$tabDefs[$tabKey]['show']) continue;
            $td     = $tabDefs[$tabKey];
            $active = $isFirstRendered;
            $isFirstRendered = false;
        ?>
        <button class="pub-tab-btn<?= $active ? ' active' : '' ?>"
                data-tab="<?= e($td['data']) ?>"
                role="tab"
                aria-selected="<?= $active ? 'true' : 'false' ?>"
                aria-controls="<?= e($td['panel']) ?>">
            <?php
                $tabIconMap = [
                    'products' => 'bag',
                    'info'     => 'info',
                    'hours'    => 'clock',
                    'location' => 'pin',
                    'offers'   => 'tag',
                    'reviews'  => 'star',
                ];
                echo icon($tabIconMap[$tabKey] ?? 'list', 15);
            ?>
            <?= e($td['label']) ?>
            <?php if ($td['count'] !== null): ?>
                <span class="pub-tab-count"><?= $td['count'] ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </nav>
</div>