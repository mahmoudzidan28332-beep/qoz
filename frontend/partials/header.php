<?php
declare(strict_types=1);
/**
 * frontend/partials/header.php — Production v3.2 (Fixed - no duplicate functions)
 */

if (defined('PUB_HEADER_INCLUDED')) {
    return;
}
define('PUB_HEADER_INCLUDED', true);

if (php_sapi_name() === 'cli') {
    return;
}
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
    http_response_code(403);
    exit('Direct access denied');
}

$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
require_once dirname(__DIR__) . '/partials/store_sections/icons.php';

$lang      = $_ctx['lang'] ?? 'ar';
$dir       = $_ctx['dir']  ?? 'rtl';
$theme     = $_ctx['theme'] ?? [];
$_seo      = $_ctx['seo']  ?? [];
$_user     = $_ctx['user'] ?? [];
$_tenantId = (int)($_ctx['tenant_id'] ?? 1);
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath  = '/frontend';
$_activeEntity = is_array($_ctx['active_entity'] ?? null) ? $_ctx['active_entity'] : [];
$_activeEntityMode = $_activeEntity['mode'] ?? 'discovery';
$_activeEntityName = trim((string)($_activeEntity['name'] ?? ''));
$_activeEntityCity = trim((string)($_activeEntity['city'] ?? ''));

$_entityStrings = [
    'deliver_to'        => t('entity.deliver_to', 'Deliver to'),
    'deliver_from'      => t('entity.delivering_from', 'Delivering from'),
    'change'            => t('common.change', 'Change'),
    'select_location'   => t('entity.select_location', 'Select location'),
    'select_branch'     => t('entity.select_branch', 'Select branch'),
    'choose_branch'     => t('entity.choose_branch', 'Choose a branch'),
    'nearest_first'     => t('entity.nearest_first', 'Nearest branches first'),
    'detecting'         => t('entity.detecting', 'Detecting your nearest branch...'),
    'detect_location'   => t('entity.detect_location', 'Detect location'),
    'location_required' => t('entity.location_required', 'We could not detect a nearby branch. Choose one manually.'),
    'branch_closed'     => t('entity.branch_closed', 'Closed now'),
    'branch_open'       => t('entity.branch_open', 'Open now'),
    'pickup_only'       => t('entity.pickup_only', 'Pickup available'),
    'delivery_hint'     => t('entity.delivery_hint', 'Delivery available'),
    'selected'          => t('entity.selected', 'Selected'),
    'switching_notice'  => t('entity.switching_notice', 'Switching branch shows that branch cart and delivery options.'),
    'cart_conflict_msg' => t('cart.conflict_msg', 'Your cart contains items from another branch. Do you want to clear it and add this item?'),
];

if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('t')) {
    function t(string $key, $r = []): string {
        $strings = $GLOBALS['PUB_STRINGS'] ?? [];
        $parts   = explode('.', $key, 2);
        $group   = $parts[0] ?? '';
        $sub     = $parts[1] ?? '';
        
        $val = $sub !== ''
            ? ($strings[$group][$sub] ?? null)
            : ($strings[$group] ?? null);

        if ($val === null) return is_string($r) ? $r : $key;
        if (!is_string($val)) return $key;

        if (is_array($r)) {
            foreach ($r as $k => $v) {
                $val = str_replace('{' . $k . '}', (string)$v, $val);
            }
        }
        return $val;
    }
}

if (!function_exists('_pub_safe_css_val')) {
    function _pub_safe_css_val(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $v = preg_replace('/[{};`]/', '', $v);
        if ($v === '') return '';
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) return $v;
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/]+\)$/i', $v)) return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) return $v;
        if (preg_match('/^[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex)(\s+[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex))*$/', $v)) return $v;
        if (preg_match('/^[\d.]{1,6}$/', $v)) return $v;
        if (preg_match('/^[\d\s.]+(px|em|rem)?\s+(rgba?\([\d\s%,.\/]+\))$/i', $v)) return $v;
        if (preg_match('/^[\d.]+(px)?\s+[\d.]+(px)?\s+[\d.]+(px)?(\s+[\d.]+(px)?)?\s+(rgba?\([\d\s%,.\/]+\)|#[0-9a-fA-F]{3,8})$/i', $v)) return $v;
        if (preg_match('/^"[a-zA-Z0-9\s\-]{1,60}"$/', $v)) return $v;
        if (preg_match('/^[a-zA-Z0-9\s,"\'\-]+$/', $v) && strlen($v) <= 200) return $v;
        return '';
    }
}

$_cssVars = [];

$_setVar = function (string $key, string $value) use (&$_cssVars): void {
    if ($value === '') return;
    $safe = _pub_safe_css_val($value);
    if ($safe === '') return;
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    $keyU = '--' . str_replace('-', '_', $sanitized);
    $keyH = '--' . str_replace('_', '-', $sanitized);
    $_cssVars[$keyU] = $safe;
    if ($keyH !== $keyU) {
        $_cssVars[$keyH] = $safe;
    }
};

foreach ($theme['color_settings'] ?? [] as $cs) {
    $k = trim($cs['setting_key'] ?? '');
    $v = trim($cs['color_value']  ?? ($cs['setting_value'] ?? ''));
    if ($k !== '' && $v !== '') {
        $_setVar($k, $v);
    }
}

foreach ($theme['font_settings'] ?? [] as $f) {
    $k = trim($f['setting_key'] ?? '');
    if ($k === '') continue;
    if (!empty($f['font_family'])) $_setVar("{$k}_family", $f['font_family']);
    if (!empty($f['font_size']))   $_setVar("{$k}_size",   $f['font_size']);
    if (!empty($f['font_weight'])) $_setVar("{$k}_weight", (string)$f['font_weight']);
}

foreach ($theme['design_settings'] ?? [] as $d) {
    $k = trim($d['setting_key']   ?? '');
    $v = trim($d['setting_value'] ?? '');
    if ($k !== '' && $v !== '' && $k !== 'logo_url') {
        $_setVar($k, $v);
    }
}

foreach ($theme['buttons'] ?? [] as $b) {
    $slug = trim($b['slug'] ?? '');
    if ($slug === '') continue;
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($slug));
    if (!empty($b['background_color']))       $_setVar("btn-{$slug}-bg",           (string)$b['background_color']);
    if (!empty($b['text_color']))             $_setVar("btn-{$slug}-color",        (string)$b['text_color']);
    if (!empty($b['border_color']))           $_setVar("btn-{$slug}-border",       (string)$b['border_color']);
    if (isset($b['border_width']))            $_setVar("btn-{$slug}-border-width", (int)$b['border_width'] . 'px');
    if (isset($b['border_radius']))           $_setVar("btn-{$slug}-radius",       (int)$b['border_radius'] . 'px');
    if (!empty($b['padding']))                $_setVar("btn-{$slug}-padding",      (string)$b['padding']);
    if (!empty($b['font_size']))              $_setVar("btn-{$slug}-font-size",    (is_numeric($b['font_size']) ? $b['font_size'] . 'px' : (string)$b['font_size']));
    if (!empty($b['font_weight']))            $_setVar("btn-{$slug}-font-weight",  (string)$b['font_weight']);
    if (!empty($b['hover_background_color'])) $_setVar("btn-{$slug}-hover-bg",     (string)$b['hover_background_color']);
    if (!empty($b['hover_text_color']))       $_setVar("btn-{$slug}-hover-color",  (string)$b['hover_text_color']);
    if (!empty($b['hover_border_color']))     $_setVar("btn-{$slug}-hover-border", (string)$b['hover_border_color']);
}

foreach ($theme['cards'] ?? [] as $c) {
    $slug = trim($c['slug'] ?? '');
    if ($slug === '') continue;
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($slug));
    if (!empty($c['background_color'])) $_setVar("card-{$slug}-bg",           (string)$c['background_color']);
    if (!empty($c['border_color']))     $_setVar("card-{$slug}-border",       (string)$c['border_color']);
    if (isset($c['border_width']))      $_setVar("card-{$slug}-border-width", (int)$c['border_width'] . 'px');
    if (isset($c['border_radius']))     $_setVar("card-{$slug}-radius",       (int)$c['border_radius'] . 'px');
    if (!empty($c['shadow_style']))     $_setVar("card-{$slug}-shadow",       (string)$c['shadow_style']);
    if (!empty($c['padding']))          $_setVar("card-{$slug}-padding",      (string)$c['padding']);
    if (!empty($c['text_color']))       $_setVar("card-{$slug}-text",         (string)$c['text_color']);
}

$_aliasVars = [];
$_getVar = function (string ...$names) use (&$_cssVars): string {
    foreach ($names as $n) {
        if (isset($_cssVars[$n]) && $_cssVars[$n] !== '') return $_cssVars[$n];
    }
    return '';
};
$_alias = function (string $target, string ...$sources) use (&$_cssVars, $_getVar, &$_aliasVars): void {
    if (isset($_cssVars[$target]) && $_cssVars[$target] !== '') return;
    $v = $_getVar(...$sources);
    if ($v !== '') $_aliasVars[$target] = $v;
};

$_alias('--surface-color',       '--surface_color', '--background-secondary', '--background_secondary');
$_alias('--card-bg',             '--card_bg',       '--surface-color', '--background-secondary');
$_alias('--input-bg',            '--input_bg',      '--surface-color', '--background-secondary');
$_alias('--input-background',    '--input_background', '--input-bg', '--input_bg');
$_alias('--background-tertiary', '--background_tertiary', '--background-secondary');
$_alias('--danger-color',        '--danger_color',  '--error-color',   '--error_color');
$_alias('--error-color',         '--error_color',   '--danger-color',  '--danger_color');
$_alias('--info-color',          '--info_color',    '--primary-color', '--primary_color');
$_alias('--text-secondary',      '--text_secondary', '--text-muted', '--text-light');
$_alias('--text-tertiary',       '--text_tertiary',  '--text-secondary', '--text_secondary');
$_alias('--border-color',        '--border_color',  '--border', '--divider-color');
$_alias('--input-placeholder',   '--input_placeholder', '--text-secondary', '--text_secondary');
$_alias('--sidebar-hover',       '--sidebar_hover',  '--primary-color', '--primary_color');
$_alias('--sidebar-active',      '--sidebar_active', '--primary-color', '--primary_color');

$_themeVars = '';
$_allVars   = array_merge($_cssVars, $_aliasVars);
if (!empty($_allVars)) {
    $parts = [];
    foreach ($_allVars as $name => $value) {
        $parts[] = '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                 . ': '   . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ';';
    }
    $_themeVars = implode("\n", $parts);
}

$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap';

$_dbFontLinks  = [];
$_systemFonts  = [
    'system-ui','ui-sans-serif','ui-serif','ui-monospace',
    'sans-serif','serif','monospace','cursive','fantasy',
    'inherit','initial','unset',
    'arial','verdana','helvetica','helvetica neue','georgia',
    'times','times new roman','courier','courier new',
    'impact','trebuchet ms','comic sans ms','tahoma',
    'lucida','palatino','garamond',
];
$_trustedFontHosts = ['fonts.googleapis.com', 'fonts.gstatic.com'];
foreach ($theme['font_settings'] ?? [] as $_f) {
    if (empty($_f['font_family'])) continue;
    if (!empty($_f['font_url'])) {
        $_url     = $_f['font_url'];
        $_urlHost = parse_url($_url, PHP_URL_HOST);
        if (!$_urlHost || !in_array($_urlHost, $_trustedFontHosts, true)) continue;
    } else {
        $_primary = trim(explode(',', $_f['font_family'])[0], " \"'");
        if ($_primary === '' || in_array(strtolower($_primary), $_systemFonts, true)) continue;
        $_url = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '%20', $_primary) . ':wght@400;500;600;700&display=swap';
    }
    if (!in_array($_url, $_dbFontLinks, true)) {
        $_dbFontLinks[] = $_url;
    }
}

$_logoUrl = '';
if (!empty($theme['design_settings']) && is_array($theme['design_settings'])) {
    foreach ($theme['design_settings'] as $d) {
        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
            $_logoUrl = $d['setting_value'];
            break;
        }
    }
}
if (empty($_logoUrl)) {
    foreach (['logo.png', 'logo.svg', 'logo.webp'] as $_lf) {
        if (@file_exists(FRONTEND_BASE . '/assets/images/' . $_lf)) {
            $_logoUrl = '/frontend/assets/images/' . $_lf;
            break;
        }
    }
}

if (!function_exists('_pub_asset_ver')) {
    function _pub_asset_ver(string $path): string {
        $full = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $path;
        return file_exists($full) ? (string)filemtime($full) : '1';
    }
}

$_themeColor = $_allVars['--primary-color'] ?? $_allVars['--primary_color']
            ?? ($theme['primary'] ?? '#2d8cf0');
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $_themeColor) && !preg_match('/^[a-zA-Z]{2,20}$/', $_themeColor)) {
    $_themeColor = '#2d8cf0';
}

?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="index, follow">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?>
    <meta name="description" content="<?= e($_pageDesc) ?>">
    <?php endif; ?>

    <meta property="og:title"       content="<?= e($_pageTitle) ?>">
    <meta property="og:description" content="<?= e($_pageDesc) ?>">
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e($_appName) ?>">

    <meta name="mobile-web-app-capable"                content="yes">
    <meta name="apple-mobile-web-app-capable"          content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title"            content="<?= e($_appName) ?>">
    <meta name="theme-color" content="<?= e($_themeColor) ?>">
    <link rel="manifest" href="/frontend/manifest.json">
    <link rel="apple-touch-icon" href="/images/default-image.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet" href="<?= e($_fontUrl) ?>">
    <?php foreach ($_dbFontLinks as $_dbFont): ?>
    <link rel="stylesheet" href="<?= e($_dbFont) ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet"
          href="/frontend/assets/css/variables.css?v=<?= _pub_asset_ver('/frontend/assets/css/variables.css') ?>">

    <link rel="stylesheet"
          href="<?= e($_basePath . '/theme.css.php?tenant_id=' . $_tenantId . '&lang=' . rawurlencode($lang) . '&v=' . (int)($theme['_debug']['theme_id'] ?? $_tenantId)) ?>">

    <link rel="stylesheet"
          href="/frontend/assets/css/public.css?v=<?= _pub_asset_ver('/frontend/assets/css/public.css') ?>">
    <link rel="stylesheet"
          href="/frontend/assets/css/header_fix.css?v=<?= time() ?>">

    <script defer src="/frontend/assets/js/public-header.js?v=<?= time() ?>"></script>
    <script defer src="/frontend/assets/js/public.js?v=<?= _pub_asset_ver('/frontend/assets/js/public.js') ?>"></script>

</head>

<body class="pub-body <?= e($dir) ?>"
      data-tenant-id="<?= $_tenantId ?>"
      data-entity-id="<?= (int)($_activeEntity['id'] ?? 0) ?>"
      data-lang="<?= e($lang) ?>"
      data-dir="<?= e($dir) ?>"
      data-base-path="<?= e($_basePath) ?>"
      data-install-instructions="<?= e(t('nav.install_instructions', 'To install the app, tap Share then Add to Home Screen.')) ?>"
      data-active-entity="<?= e(json_encode($_activeEntity, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}') ?>"
      data-entity-strings="<?= e(json_encode($_entityStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}') ?>"
      data-session-user="<?= $_isLoggedIn ? e(json_encode([
          'id'       => (int)($_user['id'] ?? 0),
          'name'     => (string)($_user['name'] ?? ''),
          'username' => (string)($_user['username'] ?? ''),
          'email'    => (string)($_user['email'] ?? ''),
      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) : '' ?>">

<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">
        <?php
            if (empty($_logoUrl)) {
                $_logoUrl = '/frontend/assets/images/logo.png';
            } else {
                if (strpos($_logoUrl, 'http') !== 0 && strpos($_logoUrl, '/') !== 0) {
                    $_logoUrl = '/' . ltrim($_logoUrl, '/');
                }
            }
        ?>
        <div class="pub-header-row1">
            <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
                <img src="<?= e($_logoUrl) ?>" alt="<?= e($_appName) ?>" class="pub-logo-img" loading="eager" decoding="async">
            </a>

            <form class="pub-header-search pub-header-search--desktop" id="pubSearchFormDesktop" method="get" action="<?= e($_basePath . '/search.php') ?>" role="search" autocomplete="off">
                <input type="hidden" name="context" value="<?= e($GLOBALS['PUB_PAGE_TYPE'] ?? 'all') ?>">
                <div class="pub-header-search-cat-wrap">
                    <select name="category_id" class="pub-header-search-cat-select pub-search-cat-mirror" aria-label="<?= e(t('search.category', 'Category')) ?>">
                        <option value=""><?= e(t('search.all_categories', 'All Categories')) ?></option>
                    </select>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <input type="search" name="q" class="pub-header-search-input pub-search-input-mirror" placeholder="<?= e(t('search.placeholder', 'Search products, stores...')) ?>" value="<?= e($_GET['q'] ?? '') ?>" aria-label="<?= e(t('search.placeholder', 'Search products, stores...')) ?>">
                <button type="submit" class="pub-btn pub-btn--primary pub-header-search-submit" aria-label="<?= e(t('search.button', 'Search')) ?>">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>
            </form>

            <nav class="pub-header-actions" aria-label="<?= e(t('nav.actions', 'Actions')) ?>">
                <button type="button" class="pub-header-action-btn pub-header-action-btn--install" id="pubInstallBtn" title="<?= e(t('nav.install_app', 'Install App')) ?>" aria-label="<?= e(t('nav.install_app', 'Install App')) ?>">
                    <span aria-hidden="true"><i class="bi bi-download"></i></span>
                </button>

                <?php if ($_isLoggedIn): ?>
                <a href="<?= e($_authPath . '/logout.php') ?>" class="pub-header-action-btn pub-header-action-btn--auth" title="<?= e(t('nav.logout', 'Logout')) ?>" aria-label="<?= e(t('nav.logout', 'Logout')) ?>">
                    <span aria-hidden="true"><i class="bi bi-box-arrow-left"></i></span>
                </a>
                <?php else: ?>
                <a href="<?= e($_authPath . '/login.php') ?>" class="pub-header-action-btn pub-header-action-btn--auth" title="<?= e(t('nav.login', 'Login')) ?>" aria-label="<?= e(t('nav.login', 'Login')) ?>">
                    <span aria-hidden="true"><i class="bi bi-person-circle"></i></span>
                </a>
                <?php endif; ?>

                <div class="pub-lang-switcher" id="pubLangSwitcher">
                    <button type="button" class="pub-header-action-btn" id="pubLangBtn" aria-haspopup="listbox" aria-expanded="false" title="<?= e(t('nav.language', 'Language')) ?>" aria-label="<?= e(t('nav.language', 'Language')) ?>">
                        <span aria-hidden="true"><i class="bi bi-translate"></i></span>
                    </button>
                    <ul class="pub-lang-dropdown" id="pubLangDropdown" role="listbox" hidden></ul>
                </div>
            </nav>
        </div>

        <form class="pub-header-search pub-header-search--mobile" id="pubSearchForm" method="get" action="<?= e($_basePath . '/search.php') ?>" role="search" autocomplete="off">
            <input type="hidden" name="context" value="<?= e($GLOBALS['PUB_PAGE_TYPE'] ?? 'all') ?>">
            <div class="pub-header-search-cat-wrap">
                <select name="category_id" id="pubSearchCatSelect" class="pub-header-search-cat-select" aria-label="<?= e(t('search.category', 'Category')) ?>">
                    <option value=""><?= e(t('search.all_categories', 'All Categories')) ?></option>
                </select>
                <i class="bi bi-chevron-down"></i>
            </div>
            <input type="search" name="q" id="pubGlobalSearchInput" class="pub-header-search-input" placeholder="<?= e(t('search.placeholder', 'Search products, stores...')) ?>" value="<?= e($_GET['q'] ?? '') ?>" aria-label="<?= e(t('search.placeholder', 'Search products, stores...')) ?>" aria-autocomplete="list" aria-controls="pubSearchSuggest">
            <button type="button" id="pubSearchClear" class="pub-search-clear" aria-label="<?= e(t('search.clear', 'Clear search')) ?>" <?= empty($_GET['q']) ? 'hidden' : '' ?>>×</button>
            <button type="submit" class="pub-btn pub-btn--primary pub-header-search-submit" aria-label="<?= e(t('search.button', 'Search')) ?>">
                <i class="bi bi-search" aria-hidden="true"></i>
            </button>
            <ul id="pubSearchSuggest" class="pub-search-suggest" role="listbox" hidden></ul>
        </form>
        
    </div>
</header>
<div class="pub-header-spacer" id="pubHeaderSpacer"></div>

<nav class="pub-cat-bar" id="pubCatBar" aria-label="<?= e(t('nav.categories', 'Categories')) ?>">
    <button class="pub-hamburger" id="pubHamburger" aria-label="<?= e(t('nav.menu_open', 'Open menu')) ?>" aria-expanded="false" aria-controls="pubSidebar" data-bound="1">
        <span></span><span></span><span></span>
    </button>
    <div class="pub-cat-bar__viewport">
        <button class="pub-cat-bar__arrow pub-cat-bar__arrow--start" id="pubCatArrowStart" aria-label="<?= e(t('nav.previous', 'Previous')) ?>" tabindex="-1">&#8249;</button>
        <div class="pub-cat-bar__inner" id="pubCatBarInner">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <span class="pub-cat-item pub-cat-item--skeleton"><span class="pub-mega-skeleton"></span></span>
            <?php endfor; ?>
        </div>
        <button class="pub-cat-bar__arrow pub-cat-bar__arrow--end" id="pubCatArrowEnd" aria-label="<?= e(t('nav.next', 'Next')) ?>" tabindex="-1">&#8250;</button>
    </div>
    <button type="button" class="pub-cat-bar__all-btn" id="pubCatSliderBtn" title="<?= e(t('nav.categories_label', 'Categories')) ?>">
        <i class="bi bi-grid-fill"></i>
        <span><?= e(t('nav.categories_label', 'Categories')) ?></span>
    </button>
    <div class="pub-mega-menu" id="pubMegaMenu" role="region" aria-live="polite"></div>
</nav>

<div class="pub-cat-slider-backdrop" id="pubCatSliderBackdrop" hidden></div>
<aside class="pub-cat-slider" id="pubCatSlider" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1" aria-label="<?= e(t('nav.categories_label', 'Categories')) ?>">
    <div class="pub-cat-slider__header">
        <h3><i class="bi bi-grid-fill"></i> <?= e(t('nav.categories_label', 'Categories')) ?></h3>
        <button type="button" class="pub-cat-slider__close" id="pubCatSliderClose" aria-label="<?= e(t('common.close', 'Close')) ?>">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="pub-cat-slider__body" id="pubCatSliderBody">
        <div class="pub-cat-slider__loading">
            <?php for ($i = 0; $i < 8; $i++): ?>
            <div class="pub-mega-skeleton pub-mega-skeleton--row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pub-cat-slider__footer">
        <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-cat-slider__view-all">
            <?= e(t('nav.view_all_categories', 'View All Categories')) ?>
            <span class="pub-cat-slider__view-all-icon"><?= icon('chevron-right', 16) ?></span>
        </a>
    </div>
</aside>

<div class="pub-layout">
    <?php $menuFile = __DIR__ . '/menu.php'; if (is_readable($menuFile)) include $menuFile; ?>
    <div class="pub-sidebar-backdrop" id="pubSidebarOverlay" aria-hidden="true"></div>

    <main class="pub-main-content">