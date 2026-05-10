<?php
declare(strict_types=1);

/**
 * frontend/public/index.php
 * ============================================================================
 * QOOQZ - Global Public Homepage [Production v3.0 - Clean]
 */

// -- Bootstrap ----------------------------------------------------------------
require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = (int)$ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' - QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');


// ============================================================================
//  SECTION 1 - CSS SANITISERS
// ============================================================================

if (!function_exists('_pub_safe_color')) {
    function _pub_safe_color(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?$/', $v)) return $v;
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v)) return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]{1,80}\)$/', $v)) return $v;
        return '';
    }
}

if (!function_exists('_pub_safe_padding')) {
    function _pub_safe_padding(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $unit   = '(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?)';
        $single = $unit . '(?:\s+' . $unit . '){0,3}';
        return preg_match('/^' . $single . '$/', $v) ? $v : '';
    }
}

if (!function_exists('_pub_safe_css')) {
    function _pub_safe_css(string $css): string {
        $css = str_replace(['<', '>'], '', $css);
        $css = preg_replace('/\bexpression\s*\(/i', '', $css);
        $css = preg_replace('/\bbehaviour\s*:/i',   '', $css);
        $css = preg_replace('/@import\b/i',         '', $css);
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:data|javascript):/i', 'url(about:', $css);
        return $css;
    }
}


// ============================================================================
//  SECTION 2 - AD LINK BUILDER
// ============================================================================

if (!function_exists('_ad_link')) {
    function _ad_link(string $type, string $value): string {
        if ($value === '') return '#';
        return match ($type) {
            'url' => (static function (string $v): string {
                $scheme = parse_url($v, PHP_URL_SCHEME);
                return ($scheme !== null && in_array(strtolower($scheme), ['http', 'https'], true)) ? $v : '#';
            })($value),
            'product'  => '/frontend/public/product.php?id=' . urlencode($value),
            'category' => '/frontend/public/categories.php?id=' . urlencode($value),
            'entity'   => '/frontend/public/entity.php?id=' . urlencode($value),
            'brand'    => '/frontend/public/brands.php?id=' . urlencode($value),
            'auction'  => '/frontend/public/auction.php?id=' . urlencode($value),
            'job'      => '/frontend/public/job.php?id=' . urlencode($value),
            'page'     => '/frontend/public/page.php?slug=' . urlencode($value),
            default    => '#',
        };
    }
}

function _ad_is_external(string $type, string $href): bool {
    return $type === 'url' && $href !== '#';
}


// ============================================================================
//  SECTION 3 - COMPONENT REGISTRY
// ============================================================================

const PUB_COMPONENT_MAP = [
    'categories' => 'ad_categories',
    'products'   => 'ad_products',
    'deals'      => 'ad_deals',
    'brands'     => 'ad_brands',
    'entities'   => 'ad_entities',
    'tenants'    => 'ad_tenants',
    'auctions'   => 'ad_auctions',
    'jobs'       => 'ad_jobs',
    'offers'     => 'ad_deals',
    'slider'     => 'ad_slider',
    'banners'    => 'ad_slider',
    'banner'     => 'ad_banner',
    'search'     => 'ad_search',
    'html'       => 'ad_html',
    'native'     => 'ad_native',
    'ads'        => 'ad_ads',
    'stats'      => 'ad_stats',
    'custom'     => 'ad_custom',
];

function pub_resolve_component(array $section): ?string {
    $stored = trim($section['component'] ?? '');
    if ($stored !== '') return $stored;
    $type = strtolower(trim($section['section_type'] ?? ''));
    if (isset(PUB_COMPONENT_MAP[$type])) return PUB_COMPONENT_MAP[$type];
    return null;
}


// ============================================================================
//  SECTION 4 - getSectionData()
// ============================================================================

function getSectionData(string $dataSource, string $apiBase, string $lang, int $tenantId): array {
    $dataSource = trim($dataSource);
    if ($dataSource === '') return [];
    [$type, $filter] = array_pad(explode(':', $dataSource, 2), 2, '');
    $type = strtolower($type);
    $filter = trim($filter);
    $base = sprintf('lang=%s&tenant_id=%d&per=12&page=1', urlencode($lang), $tenantId);

    return match ($type) {
        'categories' => pub_fetch($apiBase . 'public/categories?' . $base . '&featured=1')['data']['data'] ?? [],
        'products' => pub_fetch($apiBase . 'public/products?' . $base . '&is_featured=1' . match ($filter) {
            'new' => '&is_new=1', 'sale' => '&on_sale=1', default => '',
        })['data']['data'] ?? [],
        'deals' => pub_fetch($apiBase . 'public/discounts?tenant_id=' . $tenantId . '&lang=' . urlencode($lang) . '&per=20&page=1' . ($filter === 'today' ? '&expires_today=1' : '') . ($filter === 'flash' ? '&type=flash' : ''))['data']['data'] ?? [],
        'brands' => pub_fetch($apiBase . 'public/brands?' . $base . '&is_featured=1')['data']['data'] ?? [],
        'search' => [],
        'banners' => (static function() use ($apiBase, $tenantId, $filter) {
            $pos = ($filter !== '' && $filter !== 'all') ? '&position=' . urlencode($filter) : '';
            $response = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $pos);
            $data = $response['data']['data'] ?? $response['data'] ?? [];
            if (empty($data) && $pos !== '') {
                $fallback = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId);
                $data = $fallback['data']['data'] ?? $fallback['data'] ?? [];
            }
            return is_array($data) ? $data : [];
        })(),
        'entities' => (static function() use ($apiBase, $base, $filter) {
            $extra = '&is_featured=1' . ($filter === 'verified' ? '&is_verified=1' : '');
            return pub_fetch($apiBase . 'public/entities?' . $base . $extra)['data']['data'] ?? [];
        })(),
        'tenants' => pub_fetch($apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=12&page=1&is_featured=1' . ($filter === 'active' ? '&status=active' : ''))['data']['data'] ?? [],
        'auctions' => pub_fetch($apiBase . 'public/auctions?lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=6&page=1&is_featured=1' . match ($filter) {
            'featured' => '&status=active', 'scheduled' => '&status=scheduled', 'ended' => '&status=ended', default => '&status=active',
        })['data']['auctions'] ?? [],
        'jobs' => pub_fetch($apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=8&page=1&is_featured=1' . ($filter === 'urgent' ? '&is_urgent=1' : '') . ($filter === 'remote' ? '&is_remote=1' : ''))['data']['data'] ?? [],
        'ads' => (static function() use ($apiBase, $tenantId, $lang, $filter) {
            $url = $apiBase . 'public/ads?tenant_id=' . $tenantId . '&lang=' . urlencode($lang) . ($filter !== '' ? '&placement_key=' . urlencode($filter) : '');
            $result = pub_fetch($url);
            $data = $result['data']['data'] ?? $result['data'] ?? [];
            return is_array($data) ? $data : [];
        })(),
        'stats', 'html', 'custom' => [],
        default => [],
    };
}


// ============================================================================
//  SECTION 5 - "View all" link map + full-width component list
// ============================================================================

const PUB_VIEW_ALL_MAP = [
    'ad_categories' => '/frontend/public/categories.php',
    'ad_products'   => '/frontend/public/products.php',
    'ad_deals'      => '/frontend/public/discounts.php',
    'ad_brands'     => '/frontend/public/brands.php',
    'ad_entities'   => '/frontend/public/entities.php',
    'ad_tenants'    => '/frontend/public/tenants.php',
    'ad_auctions'   => '/frontend/public/auctions.php',
    'ad_jobs'       => '/frontend/public/jobs.php',
];

const PUB_FULL_WIDTH_COMPONENTS = [
    'ad_slider',
    'ad_search',
    'ad_banner',
    'ad_html',
];


// ============================================================================
//  SECTION 6 - Include header
// ============================================================================

include dirname(__DIR__) . '/partials/header.php';

$_cardStyles = [
    'entities' => ['inline' => pub_card_inline_style('entities'), 'class' => pub_card_css_class('entities')],
    'tenants' => ['inline' => pub_card_inline_style('tenants'), 'class' => pub_card_css_class('tenants')],
    'product' => ['inline' => pub_card_inline_style('product'), 'class' => pub_card_css_class('product'), 'img' => pub_card_img_style('product')],
    'category' => ['inline' => pub_card_inline_style('category'), 'class' => pub_card_css_class('category'), 'img' => pub_card_img_style('category')],
    'auction' => ['inline' => pub_card_inline_style('auction'), 'class' => pub_card_css_class('auction'), 'img' => pub_card_img_style('auction')],
    'job' => ['inline' => pub_card_inline_style('job'), 'class' => pub_card_css_class('job')],
    'promo' => ['inline' => pub_card_inline_style('promo'), 'class' => pub_card_css_class('promo')],
    'feature' => ['inline' => pub_card_inline_style('feature'), 'class' => pub_card_css_class('feature')],
];

$componentsDir = __DIR__ . '/components';


// ============================================================================
//  SECTION 7 - Fetch sections from API
// ============================================================================

$sectionsResp = pub_fetch($apiBase . 'public/homepage_sections?tenant_id=' . $tenantId . '&lang=' . urlencode($lang));
$sections = $sectionsResp['data']['data'] ?? $sectionsResp['data'] ?? [];
if (!is_array($sections)) { $sections = []; }


// ============================================================================
//  SECTION 8 - Render sections
// ============================================================================

$_entitiesRenderedViaSection = false;
?>
<div id="pub-homepage-sections" role="main">
<?php foreach ($sections as $section):
    $component = pub_resolve_component($section);
    if ($component === null || $component === 'ad_search') continue;
    $componentFile = $componentsDir . '/' . basename($component) . '.php';
    if (!is_file($componentFile)) continue;
    $sectionData = getSectionData($section['data_source'] ?? '', $apiBase, $lang, $tenantId);
    if ($component === 'ad_entities' && !empty($sectionData)) { $_entitiesRenderedViaSection = true; }
    $secTitle = trim($section['title'] ?? '');
    $secSub = trim($section['subtitle'] ?? '');
    $viewAllLink = PUB_VIEW_ALL_MAP[$component] ?? '';
    $isFullWidth = in_array($component, PUB_FULL_WIDTH_COMPONENTS, true);
    $sectionAttr = sprintf(' data-section-id="%d" data-component="%s"', (int)($section['id'] ?? 0), e($component));
?>
<section class="pub-section homepage-section homepage-section--<?= e($component) ?>" <?= $sectionAttr ?>>
<?php if ($isFullWidth): ?>
    <?php include $componentFile; ?>
<?php else: ?>
    <div class="pub-container">
        <?php if ($secTitle !== ''): ?>
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e($secTitle) ?></h2>
            <?php if ($viewAllLink !== ''): ?>
            <a href="<?= e($viewAllLink) ?>" class="pub-section-link" aria-label="<?= e(t('sections.view_all')) ?> - <?= e($secTitle) ?>"><?= e(t('sections.view_all')) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($secSub !== ''): ?>
        <p class="pub-section-sub"><?= e($secSub) ?></p>
        <?php endif; ?>
        <?php endif; ?>
        <?php include $componentFile; ?>
    </div>
<?php endif; ?>
</section>
<?php endforeach; ?>
</div>

<?php $_homepageTrackingV = @filemtime(dirname(__DIR__) . '/assets/js/homepage-tracking.js') ?: '1'; ?>
<script src="/frontend/assets/js/homepage-tracking.js?v=<?= $_homepageTrackingV ?>" defer></script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>