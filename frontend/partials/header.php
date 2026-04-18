<?php
declare(strict_types=1);
/**
 * frontend/partials/header.php — Production v3.1
 * QOOQZ — Public Interface Header
 */

if (defined('PUB_HEADER_INCLUDED')) {
    return;
}
define('PUB_HEADER_INCLUDED', true);

if (php_sapi_name() === 'cli') {
    return;
}
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    http_response_code(403);
    exit('Direct access denied');
}

$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
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

    <!-- SEO -->
    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?>
    <meta name="description" content="<?= e($_pageDesc) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title"       content="<?= e($_pageTitle) ?>">
    <meta property="og:description" content="<?= e($_pageDesc) ?>">
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e($_appName) ?>">

    <!-- PWA / Mobile -->
    <meta name="mobile-web-app-capable"                content="yes">
    <meta name="apple-mobile-web-app-capable"          content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title"            content="<?= e($_appName) ?>">
    <meta name="theme-color" content="<?= e($_themeColor) ?>">
    <link rel="manifest" href="/frontend/manifest.json">
    <link rel="apple-touch-icon" href="/images/default-image.png">

    <!-- DNS / Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fonts (default + DB fonts) -->
    <link rel="preload" href="<?= e($_fontUrl) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_fontUrl) ?>"></noscript>
    <?php foreach ($_dbFontLinks as $_dbFont): ?>
    <link rel="preload" href="<?= e($_dbFont) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_dbFont) ?>"></noscript>
    <?php endforeach; ?>

    <!-- Icon Library (Bootstrap Icons) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Design Tokens (variables.css) — loaded FIRST -->
    <link rel="stylesheet"
          href="/frontend/assets/css/variables.css?v=<?= _pub_asset_ver('/frontend/assets/css/variables.css') ?>">

    <!-- CSS VARIABLES (single source of truth from DB) -->
    <style id="pub-theme-vars">
:root {
<?php if ($_themeVars): ?>
<?= $_themeVars . "\n" ?>
<?php endif; ?>
}

body {
    background:  var(--pub-bg, var(--background-main, var(--background_main, #ffffff)));
    color:       var(--pub-text, var(--text-primary, var(--text_primary, #222831)));
    font-family: var(--body-font-family, var(--body_font-family, "Cairo", "Inter", system-ui, sans-serif));
    margin: 0;
    padding: 0;
}

.pub-header {
    background: var(--pub-header-bg, var(--header-background, var(--header_background, var(--pub-primary, #2d8cf0))));
    color:      var(--pub-header-text, var(--header-text, var(--header_text, #ffffff)));
    border-bottom: var(--header-border-width, 1px) solid var(--header-border-color, var(--pub-border, rgba(0,0,0,0.12)));
    position: sticky;
    top: 0;
    z-index: 10000;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.pub-sidebar {
    background: var(--pub-sidebar-bg, var(--sidebar-background, var(--sidebar_background, var(--pub-header-bg, var(--pub-primary, #2d8cf0)))));
    color:      var(--pub-sidebar-text, var(--sidebar-text, var(--sidebar_text, #ffffff)));
}

.pub-footer {
    background: var(--pub-footer-bg, var(--footer-background, var(--footer_background, #1e2a38)));
    color:      var(--pub-footer-text, var(--footer-text, var(--footer_text, rgba(255,255,255,0.8))));
}

@media (max-width: 640px) {
    .pub-layout {
        min-height: calc(100vh - 116px);
    }
}

.pub-header-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-shrink: 0;
    margin-inline-start: .5rem;
}
.pub-header-action-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .4rem .5rem;
    border-radius: 6px;
    color: var(--pub-header-text, var(--header-text, var(--header_text, #fff)));
    text-decoration: none;
    font-size: .82rem;
    font-weight: 600;
    transition: background .15s;
    white-space: nowrap;
    border: none;
    background: transparent;
    cursor: pointer;
}
.pub-header-action-btn:hover {
    background: var(--header-action-hover-bg, rgba(255,255,255,.18));
}
.pub-header-action-btn--auth {
    background: var(--header-auth-bg, rgba(255,255,255,.12));
    border: 1px solid var(--header-auth-border, rgba(255,255,255,.25));
}
.pub-header-action-btn span[aria-hidden] {
    font-size: 1.2rem;
    line-height: 1;
}
.pub-header-action-btn--auth:hover {
    background: var(--header-auth-hover-bg, rgba(255,255,255,.25));
}
@media (max-width: 640px) {
    .pub-header-action-label { display: none; }
}

.pub-lang-switcher {
    position: relative;
}
.pub-lang-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    inset-inline-end: 0;
    min-width: 130px;
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #ddd);
    border-radius: 8px;
    box-shadow: 0 6px 24px rgba(0,0,0,.15);
    z-index: 9999;
    list-style: none;
    margin: 0; padding: 4px 0;
    font-size: .88rem;
    color: var(--pub-text, #222);
}
.pub-lang-dropdown[hidden] { display: none; }
.pub-lang-dropdown__item a {
    display: block;
    padding: .45rem .85rem;
    text-decoration: none;
    color: inherit;
    white-space: nowrap;
    transition: background .12s;
    border-radius: 4px;
}
.pub-lang-dropdown__item a:hover {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
}
.pub-lang-dropdown__item--active a {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
    font-weight: 700;
}

.pub-cat-bar {
    position: sticky;
    top: var(--pub-header-h, 60px);
    background: var(--pub-cat-bar-bg, var(--pub-primary, #2d8cf0));
    border-top: 1px solid rgba(255,255,255,.12);
    z-index: 9997;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
@media (max-width: 640px) {
    .pub-cat-bar {
        top: var(--pub-header-h, 60px);
    }
}

/* Smart Sticky Behavior Classes */
body.pub-smart-hidden .pub-header {
    transform: translateY(-100%);
}
body.pub-smart-hidden .pub-cat-bar {
    transform: translateY(calc(-100% - var(--pub-header-h, 60px)));
}
@media (max-width: 640px) {
    body.pub-smart-hidden .pub-cat-bar {
        transform: translateY(calc(-100% - var(--pub-header-h, 60px)));
    }
}

.pub-cat-bar__inner {
    display: flex;
    align-items: center;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -ms-overflow-style: none;
    gap: 0;
    position: relative;
    min-height: 48px;
}
.pub-cat-bar__inner::-webkit-scrollbar { display: none; }

.pub-cat-bar__arrow {
    position: absolute;
    top: 0; bottom: 0;
    width: 36px;
    background: linear-gradient(to var(--arrow-dir,right), var(--pub-primary,#2d8cf0) 60%, transparent);
    border: none;
    color: #fff;
    cursor: pointer;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    padding: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s;
}
.pub-cat-bar__arrow--start { inset-inline-start: 0; --arrow-dir: left; }
.pub-cat-bar__arrow--end   { inset-inline-end:   0; --arrow-dir: right; }
.pub-cat-bar--can-start .pub-cat-bar__arrow--start,
.pub-cat-bar--can-end   .pub-cat-bar__arrow--end   { opacity: 1; pointer-events: auto; }

.pub-cat-item {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1rem;
    color: rgba(255,255,255,0.95);
    font-size: .85rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    text-decoration: none;
    transition: all .2s ease;
    user-select: none;
    flex-shrink: 0;
    line-height: 1.2;
}
.pub-cat-item:hover,
.pub-cat-item.active {
    background: var(--cat-item-active-bg, rgba(255,255,255,.15));
    border-bottom-color: var(--cat-item-active-border, #fff);
    color: var(--cat-item-active-color, #fff);
}
.pub-cat-item img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    border-radius: 3px;
    flex-shrink: 0;
}

.pub-mega-menu {
    position: absolute;
    inset-inline-start: 0;
    inset-inline-end: 0;
    top: 100%;
    background: var(--mega-menu-bg, var(--pub-surface, #fff));
    border: 1px solid var(--mega-menu-border, var(--pub-border, #e0e0e0));
    border-top: none;
    border-radius: var(--mega-menu-radius, 0 0 10px 10px);
    box-shadow: var(--mega-menu-shadow, 0 12px 40px rgba(0,0,0,.14));
    z-index: 1000;
    display: none;
    min-height: 280px;
    overflow: hidden;
}
.pub-mega-menu.open { display: flex; }

.pub-mega-menu__col {
    flex: 1 1 0;
    padding: 1rem 1.1rem;
    border-inline-end: 1px solid var(--pub-border, #e8e8e8);
    overflow: hidden;
    min-width: 0;
}
.pub-mega-menu__col:last-child { border-inline-end: none; }

.pub-mega-menu__heading {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--pub-muted, #888);
    margin: 0 0 .6rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid var(--pub-border, #eee);
}

.pub-mega-sub__list {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    flex-direction: column;
    gap: .2rem;
}
.pub-mega-sub__list a {
    display: block;
    padding: .3rem .4rem;
    font-size: .85rem;
    color: var(--pub-text, #222);
    text-decoration: none;
    border-radius: 5px;
    transition: background .12s, color .12s;
}
.pub-mega-sub__list a:hover {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
}

.pub-mega-prods {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: .55rem;
}
.pub-mega-prod {
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #eee);
    border-radius: 8px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition: box-shadow .15s, transform .12s;
}
.pub-mega-prod:hover {
    box-shadow: var(--mega-prod-hover-shadow, 0 4px 16px rgba(0,0,0,.12));
    transform: translateY(-2px);
}
.pub-mega-prod img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
}
.pub-mega-prod__info {
    padding: .35rem .4rem;
    font-size: .76rem;
}
.pub-mega-prod__name {
    font-weight: 600;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
    margin-bottom: .2rem;
}
.pub-mega-prod__price {
    color: var(--pub-primary, #2d8cf0);
    font-weight: 700;
}

.pub-mega-brands {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.pub-mega-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 50px;
    border: 1px solid var(--pub-border, #eee);
    border-radius: 7px;
    padding: .3rem;
    text-decoration: none;
    background: var(--pub-surface, #fff);
    transition: box-shadow .15s, border-color .15s;
}
.pub-mega-brand:hover {
    box-shadow: var(--mega-brand-hover-shadow, 0 2px 10px rgba(0,0,0,.1));
    border-color: var(--mega-brand-hover-border, var(--pub-primary, #2d8cf0));
}
.pub-mega-brand img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.pub-mega-brand--text {
    font-size: .72rem;
    font-weight: 600;
    color: var(--pub-text, #222);
    text-align: center;
    word-break: break-word;
    line-height: 1.2;
}

.pub-mega-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: pubSkeleton .9s infinite;
    border-radius: 5px;
    min-height: 18px;
    margin-bottom: .4rem;
}
@keyframes pubSkeleton {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@media (max-width: 768px) {
    .pub-cat-bar__inner {
        flex-wrap: nowrap;
        padding-bottom: 2px;
        padding-inline: 40px; /* Space for absolute arrows */
    }
    .pub-mega-menu.open {
        flex-direction: column;
        position: fixed;
        inset: 0;
        top: calc(var(--pub-header-h, 60px) + 48px);
        z-index: 1200;
        overflow-y: auto;
        border-radius: 0;
    }
    .pub-mega-menu__col {
        border-inline-end: none;
        border-bottom: 1px solid var(--pub-border, #eee);
    }
}
    </style>

    <!-- Public Stylesheet -->
    <link rel="stylesheet"
          href="/frontend/assets/css/public.css?v=<?= _pub_asset_ver('/frontend/assets/css/public.css') ?>">

    <!-- Generated CSS -->
    <?php if (!empty($theme['generated_css'])): ?>
    <style id="pub-theme-generated"><?= $theme['generated_css'] ?></style>
    <?php endif; ?>

    <script defer src="/frontend/assets/js/homepage-engine.js?v=<?= _pub_asset_ver('/frontend/assets/js/homepage-engine.js') ?>"></script>
    <script defer src="/frontend/assets/js/slider.js?v=<?= _pub_asset_ver('/frontend/assets/js/slider.js') ?>"></script>
    <script defer src="/frontend/assets/js/public.js?v=<?= _pub_asset_ver('/frontend/assets/js/public.js') ?>"></script>

    <?php if ($_isLoggedIn): ?>
    <script>window.pubSessionUser = <?= json_encode([
        'id'       => (int)($_user['id']       ?? 0),
        'name'     => (string)($_user['name']     ?? ''),
        'username' => (string)($_user['username'] ?? ''),
        'email'    => (string)($_user['email']    ?? ''),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
    <?php endif; ?>
    <script>
        window.__qzTenantId = <?= $_tenantId ?>;
        window.__qzEntityId = <?= (int)($_activeEntity['id'] ?? 0) ?>;
        window.pubActiveEntity = <?= json_encode($_activeEntity, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}' ?>;
        window.pubEntityStrings = <?= json_encode($_entityStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}' ?>;
    </script>
</head>

<body class="pub-body <?= e($dir) ?>">
<script>
    (function(){
        try {
            var s = localStorage.getItem('pub_sidebar_state');
            if (s === '1') document.body.classList.add('pub-sidebar-collapsed');
            else if (s !== '0') document.body.classList.add('pub-sidebar-hidden');
        } catch(e) {}
    })();
</script>

<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">
        <?php
            if (empty($_logoUrl)) {
                $_logoUrl = '/frontend/assets/images/logo.png';
            } else {
                if (!str_starts_with($_logoUrl, 'http') && !str_starts_with($_logoUrl, '/')) {
                    $_logoUrl = '/' . ltrim($_logoUrl, '/');
                }
            }
        ?>
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>" style="display:flex; align-items:center; text-decoration:none; color:inherit;">
            <img src="<?= e($_logoUrl) ?>" alt="<?= e($_appName) ?>" class="pub-logo-img" loading="eager" decoding="async" style="max-height:55px; width:auto; object-fit:contain;">
        </a>
        
        <!-- Branch context indicator removed per user request -->

        <form class="pub-header-search" method="get" action="<?= e($_basePath . '/search.php') ?>" role="search" autocomplete="off" style="position:relative;">
            <input type="hidden" name="context" value="<?= e($GLOBALS['PUB_PAGE_TYPE'] ?? 'all') ?>">
            <input type="search" name="q" id="pubGlobalSearchInput" class="pub-header-search-input" placeholder="<?= e(t('search.placeholder', 'Search products, stores...')) ?>" value="<?= e($_GET['q'] ?? '') ?>" aria-label="<?= e(t('search.placeholder', 'Search products, stores...')) ?>" aria-autocomplete="list" aria-controls="pubSearchSuggest" style="padding-inline-end:2.4rem;">
            <button type="button" id="pubSearchClear" aria-label="<?= e(t('search.clear', 'Clear search')) ?>" style="position:absolute;inset-block-start:50%;inset-inline-end:calc(100% - 2.2rem); transform:translateY(-50%);background:none;border:none;cursor:pointer; color:var(--pub-muted,#888);font-size:1.1rem;padding:0 .35rem;line-height:1; display:<?= !empty($_GET['q']) ? 'block' : 'none' ?>;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            <button type="submit" class="pub-btn pub-btn--primary" aria-label="<?= e(t('search.button', 'Search')) ?>" style="padding: 7px 18px; border-radius: var(--pub-radius, 8px);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </button>
            <ul id="pubSearchSuggest" role="listbox" hidden style="position:absolute;top:100%;inset-inline-start:0;min-width:300px;max-width:520px;width:100%; background:var(--pub-surface,#fff);border:1px solid var(--pub-border,#ddd); border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.12); list-style:none;margin:4px 0 0;padding:4px 0;z-index:9999;font-size:.9rem;"></ul>
        </form>

        <nav class="pub-header-actions" aria-label="<?= e(t('nav.actions', 'Actions')) ?>">
            <button type="button" class="pub-header-action-btn" id="pubInstallBtn" style="display:inline-flex; color:var(--pub-primary, #2d8cf0); background:rgba(255,255,255,0.95); box-shadow:0 2px 6px rgba(0,0,0,0.15);" title="<?= e(t('nav.install_app', 'Install App')) ?>" aria-label="<?= e(t('nav.install_app', 'Install App')) ?>">
                <span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg></span>
                <span class="pub-header-action-label" style="font-weight:800;"><?= e(t('nav.install_app', 'Install')) ?></span>
            </button>

            <?php if ($_isLoggedIn): ?>
            <a href="<?= e($_authPath . '/logout.php') ?>" class="pub-header-action-btn pub-header-action-btn--auth" title="<?= e(t('nav.logout', 'Logout')) ?>" aria-label="<?= e(t('nav.logout', 'Logout')) ?>">
                <span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg></span>
                <span class="pub-header-action-label"><?= e(t('nav.logout', 'Logout')) ?></span>
            </a>
            <?php else: ?>
            <a href="<?= e($_authPath . '/login.php') ?>" class="pub-header-action-btn pub-header-action-btn--auth" title="<?= e(t('nav.login', 'Login')) ?>" aria-label="<?= e(t('nav.login', 'Login')) ?>">
                <span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7" /></svg></span>
                <span class="pub-header-action-label"><?= e(t('nav.login', 'Login')) ?></span>
            </a>
            <?php endif; ?>

            <div class="pub-lang-switcher" id="pubLangSwitcher">
                <button type="button" class="pub-header-action-btn" id="pubLangBtn" aria-haspopup="listbox" aria-expanded="false" title="<?= e(t('nav.language', 'Language')) ?>" aria-label="<?= e(t('nav.language', 'Language')) ?>">
                    <span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 15h2.498m-3.953-2.903a15.938 15.938 0 01-5.176-5.176M6.412 9a15.938 15.938 0 005.176 5.176" /></svg></span>
                    <span class="pub-header-action-label" id="pubLangLabel"><?= e($lang) ?></span>
                </button>
                <ul class="pub-lang-dropdown" id="pubLangDropdown" role="listbox" hidden></ul>
            </div>
        </nav>
        
        <script>
            (function() {
                var deferredPrompt;
                var installBtn = document.getElementById('pubInstallBtn');
                window.addEventListener('beforeinstallprompt', function(e) { e.preventDefault(); deferredPrompt = e; if(installBtn) installBtn.style.display = 'inline-flex'; });
                if(installBtn) {
                    installBtn.addEventListener('click', function() {
                        if (deferredPrompt) {
                            deferredPrompt.prompt();
                            deferredPrompt.userChoice.then(function(choiceResult) { if (choiceResult.outcome === 'accepted') installBtn.style.display = 'none'; deferredPrompt = null; });
                        } else { alert("<?= e(t('nav.install_instructions', 'To install the app, tap Share then Add to Home Screen.')) ?>"); }
                    });
                }
                window.addEventListener('appinstalled', function() { if(installBtn) installBtn.style.display = 'none'; });
            })();
        </script>
    </div>
</header>

<!-- Branch selection UI removed per user request -->

<nav class="pub-cat-bar" id="pubCatBar" aria-label="<?= e(t('nav.categories', 'Categories')) ?>" style="display:flex; align-items:center; flex-wrap:nowrap; padding-inline-start:4px; z-index:9997;">
    <button class="pub-hamburger" id="pubHamburger" aria-label="<?= e(t('nav.menu_open', 'Open menu')) ?>" aria-expanded="false" aria-controls="pubSidebar" data-bound="1" style="margin-inline-end:4px; min-width:44px; flex-shrink:0;">
        <span></span><span></span><span></span>
    </button>
    <div style="flex:1; position:relative; display:flex; min-width:0;">
        <button class="pub-cat-bar__arrow pub-cat-bar__arrow--start" id="pubCatArrowStart" aria-label="<?= e(t('nav.previous', 'Previous')) ?>" tabindex="-1">&#8249;</button>
        <div class="pub-cat-bar__inner" id="pubCatBarInner" style="flex:1;">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <span class="pub-cat-item" style="width:90px;"><span class="pub-mega-skeleton" style="width:70px;height:14px;border-radius:6px;"></span></span>
            <?php endfor; ?>
        </div>
        <button class="pub-cat-bar__arrow pub-cat-bar__arrow--end" id="pubCatArrowEnd" aria-label="<?= e(t('nav.next', 'Next')) ?>" tabindex="-1">&#8250;</button>
    </div>
    <button type="button" class="pub-cat-bar__all-btn" id="pubCatSliderBtn" title="<?= e(t('nav.categories_label', 'التصنيفات')) ?>">
        <i class="bi bi-grid-fill"></i>
        <span><?= e(t('nav.categories_label', 'التصنيفات')) ?></span>
    </button>
    <div class="pub-mega-menu" id="pubMegaMenu" role="region" aria-live="polite" style="z-index:9999;"></div>
</nav>

<!-- Categories Slider Drawer -->
<div class="pub-cat-slider-backdrop" id="pubCatSliderBackdrop"></div>
<aside class="pub-cat-slider" id="pubCatSlider" aria-label="<?= e(t('nav.categories_label', 'التصنيفات')) ?>">
    <div class="pub-cat-slider__header">
        <h3><i class="bi bi-grid-fill"></i> <?= e(t('nav.categories_label', 'التصنيفات')) ?></h3>
        <button type="button" class="pub-cat-slider__close" id="pubCatSliderClose" aria-label="<?= e(t('common.close', 'Close')) ?>">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="pub-cat-slider__body" id="pubCatSliderBody">
        <div class="pub-cat-slider__loading">
            <?php for ($i = 0; $i < 8; $i++): ?>
            <div class="pub-mega-skeleton" style="height:48px; border-radius:10px; margin-bottom:8px;"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pub-cat-slider__footer">
        <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-cat-slider__view-all">
            <?= e(t('nav.view_all_categories', 'عرض جميع التصنيفات')) ?>
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</aside>

<script>
(function () {
    'use strict';
    var TENANT_ID = (window.__qzTenantId || 0);
    var LANG = <?= json_encode($lang) ?>;
    var BASE_PATH = <?= json_encode($_basePath) ?>;
    var IS_RTL = <?= json_encode($dir === 'rtl') ?>;
    var DEBOUNCE = 150;

    // 🌍 Global translations (Full Export from PHP with Fallback)
    <?php 
        $strings = $GLOBALS['PUB_STRINGS'] ?? [];
        if (empty($strings) && function_exists('pub_load_translations')) {
            $strings = pub_load_translations($lang ?? 'en');
        }
    ?>
    window.pubTranslations = <?= json_encode($strings ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    var bar = document.getElementById('pubCatBar');
    var inner = document.getElementById('pubCatBarInner');
    var megaMenu = document.getElementById('pubMegaMenu');
    var arrowStart = document.getElementById('pubCatArrowStart');
    var arrowEnd = document.getElementById('pubCatArrowEnd');

    if (!bar || !inner || !megaMenu) return;

    var _catCache = {};
    var _topCats = null;
    var _activeId = null;
    var _menuTimer = null;
    var _isMobile = window.matchMedia('(max-width:768px)').matches;

    window.matchMedia('(max-width:768px)').addEventListener('change', function (e) { _isMobile = e.matches; });

    function apiGet(url) { return fetch(url, { credentials: 'include' }).then(function (r) { return r.ok ? r.json() : null; }); }
    function apiBase() { return '/api/public/'; }
    function tenantQ() { return TENANT_ID ? ('&tenant_id=' + TENANT_ID) : ''; }

    function loadTopCats() {
        var url = apiBase() + 'categories?tree=1&has_products=1&lang=' + encodeURIComponent(LANG) + tenantQ();
        apiGet(url).then(function (j) {
            if (!j) return;
            var cats = (j.data && Array.isArray(j.data.data)) ? j.data.data : (Array.isArray(j.data) ? j.data : []);
            _topCats = cats;
            cats.forEach(function (cat) { if (cat.children && cat.children.length) _catCache[cat.id] = { subs: cat.children, prods: null, brands: null }; });
            renderCatBar(cats);
        }).catch(function () {});
    }

    function renderCatBar(cats) {
        inner.innerHTML = '';
        if (!cats || !cats.length) { bar.style.display = 'none'; return; }
        bar.style.display = '';
        cats.forEach(function (cat) {
            var el = document.createElement('a');
            el.className = 'pub-cat-item';
            el.href = BASE_PATH + '/categories.php?id=' + encodeURIComponent(cat.id);
            el.dataset.catId = cat.id;
            el.setAttribute('aria-haspopup', 'true');
            if (cat.image_url) { var img = document.createElement('img'); img.src = cat.image_url; img.alt = ''; el.appendChild(img); }
            var span = document.createElement('span'); span.textContent = cat.name || ''; el.appendChild(span);
            inner.appendChild(el);
            if (_isMobile) { el.addEventListener('click', function (e) { e.preventDefault(); toggleMobileMega(cat.id, el); }); }
            else { el.addEventListener('mouseenter', debounce(function () { openMega(cat.id, el); }, DEBOUNCE)); }
        });
        updateArrows();
    }

    function fetchMegaData(catId) {
        if (_catCache[catId] && _catCache[catId].prods !== null) return Promise.resolve(_catCache[catId]);
        var langQ = '&lang=' + encodeURIComponent(LANG);
        var tQ = tenantQ();
        var pSubs = (_catCache[catId] && _catCache[catId].subs) ? Promise.resolve({ data: _catCache[catId].subs }) : apiGet(apiBase() + 'categories?parent_id=' + catId + '&per=15' + langQ + tQ);
        var pProds = apiGet(apiBase() + 'products?category_id=' + catId + '&per=8' + langQ + tQ);
        var pBrands = apiGet(apiBase() + 'brands?per=10' + langQ + tQ);
        return Promise.all([pSubs, pProds, pBrands]).then(function (res) {
            var d = { subs: extractList(res[0]), prods: extractList(res[1]), brands: extractList(res[2]) };
            _catCache[catId] = d; return d;
        });
    }

    function extractList(j) { if (!j) return []; var d = j.data || j; return Array.isArray(d) ? d : (d.data || []); }

    function renderMega(data) {
        var html = '<div class="pub-mega-menu__col"><p class="pub-mega-menu__heading">' + escapeHtml(<?= json_encode(t('mega.subcategories','Subcategories')) ?>) + '</p>';
        if (data.subs.length) { html += '<ul class="pub-mega-sub__list">'; data.subs.forEach(function(s){ html += '<li><a href="'+escapeAttr(BASE_PATH+'/categories.php?id='+s.id)+'">'+escapeHtml(s.name)+'</a></li>'; }); html += '</ul>'; }
        else { html += '<p style="font-size:.82rem;color:var(--pub-muted,#aaa)">No subcategories</p>'; }
        html += '</div>';

        html += '<div class="pub-mega-menu__col"><p class="pub-mega-menu__heading">' + escapeHtml(<?= json_encode(t('mega.featured_products','Featured Products')) ?>) + '</p>';
        if (data.prods.length) { html += '<div class="pub-mega-prods">'; data.prods.forEach(function(p){ 
            html += '<a class="pub-mega-prod" href="'+escapeAttr(BASE_PATH+'/product.php?id='+p.id)+'">';
            html += p.image_url ? '<img src="'+escapeAttr(p.image_url)+'" alt="" loading="lazy">' : '<div style="aspect-ratio:1;background:var(--pub-hover,#f0f0f0)"></div>';
            html += '<div class="pub-mega-prod__info"><div class="pub-mega-prod__name">'+escapeHtml(p.name)+'</div>';
            if(p.price) html += '<div class="pub-mega-prod__price">'+escapeHtml(formatPrice(p.price, p.currency))+'</div>';
            html += '</div></a>';
        }); html += '</div>'; }
        html += '</div><div class="pub-mega-menu__col"><p class="pub-mega-menu__heading">Brands</p>';
        if (data.brands.length) { html += '<div class="pub-mega-brands">'; data.brands.forEach(function(b){ html += '<a class="pub-mega-brand" href="'+escapeAttr(BASE_PATH+'/products.php?brand_id='+b.id)+'">'+ (b.logo_url ? '<img src="'+escapeAttr(b.logo_url)+'" alt="">' : '<span>'+escapeHtml(b.name)+'</span>') +'</a>'; }); html += '</div>'; }
        html += '</div>';
        megaMenu.innerHTML = html;
    }

    function renderSkeleton() { megaMenu.innerHTML = '<div class="pub-mega-menu__col"><div class="pub-mega-skeleton"></div><div class="pub-mega-skeleton"></div></div>'; }
    function openMega(catId, el) { clearTimeout(_menuTimer); _activeId = catId; inner.querySelectorAll('.pub-cat-item').forEach(function(x){ x.classList.toggle('active', x.dataset.catId == catId); }); megaMenu.classList.add('open'); renderSkeleton(); fetchMegaData(catId).then(function(d){ if(_activeId==catId) renderMega(d); }); }
    function closeMega() { _activeId = null; megaMenu.classList.remove('open'); inner.querySelectorAll('.pub-cat-item').forEach(function(x){ x.classList.remove('active'); }); }
    function toggleMobileMega(catId, el) { if(_activeId == catId) closeMega(); else openMega(catId, el); }

    bar.addEventListener('mouseleave', function(){ if(!_isMobile) _menuTimer = setTimeout(closeMega, 250); });
    bar.addEventListener('mouseenter', function(){ clearTimeout(_menuTimer); });
    document.addEventListener('click', function(e){ if(!bar.contains(e.target)) closeMega(); });
    function updateArrows() { var sl = inner.scrollLeft; var max = inner.scrollWidth - inner.clientWidth; bar.classList.toggle('pub-cat-bar--can-start', IS_RTL ? sl < -2 : sl > 2); bar.classList.toggle('pub-cat-bar--can-end', IS_RTL ? sl > -(max-2) : sl < max-2); }
    inner.addEventListener('scroll', updateArrows, {passive:true});
    arrowStart.addEventListener('click', function(){ inner.scrollBy({left:IS_RTL?160:-160, behavior:'smooth'}); });
    arrowEnd.addEventListener('click', function(){ inner.scrollBy({left:IS_RTL?-160:160, behavior:'smooth'}); });
    function debounce(fn, ms) { var t; return function(){ var c=this, a=arguments; clearTimeout(t); t=setTimeout(function(){fn.apply(c,a);}, ms); }; }
    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function escapeAttr(s) { return String(s).replace(/"/g,'&quot;'); }
    function formatPrice(p, c){ return parseFloat(p).toLocaleString(LANG==='ar'?'ar-SA':'en-US', {style:'currency', currency:c||'SAR'}); }

    // ── Categories Slider Drawer ──
    var sliderBtn = document.getElementById('pubCatSliderBtn');
    var slider = document.getElementById('pubCatSlider');
    var sliderBackdrop = document.getElementById('pubCatSliderBackdrop');
    var sliderClose = document.getElementById('pubCatSliderClose');
    var sliderBody = document.getElementById('pubCatSliderBody');
    var _sliderRendered = false;

    function openSlider() {
        if (!_sliderRendered && _topCats && _topCats.length) {
            renderSliderCats(_topCats);
            _sliderRendered = true;
        }
        slider.classList.add('open');
        sliderBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSlider() {
        slider.classList.remove('open');
        sliderBackdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    function renderSliderCats(cats) {
        var html = '';
        cats.forEach(function(cat) {
            var hasSubs = cat.children && cat.children.length > 0;
            html += '<div class="pub-cat-slider__item">';
            html += '<a href="' + escapeAttr(BASE_PATH + '/categories.php?id=' + cat.id) + '" class="pub-cat-slider__link">';
            if (cat.image_url) {
                html += '<img src="' + escapeAttr(cat.image_url) + '" alt="" class="pub-cat-slider__img">';
            } else {
                html += '<span class="pub-cat-slider__icon"><i class="bi bi-folder2-open"></i></span>';
            }
            html += '<span class="pub-cat-slider__name">' + escapeHtml(cat.name) + '</span>';
            if (hasSubs) {
                html += '<span class="pub-cat-slider__count">' + cat.children.length + '</span>';
            }
            html += '</a>';
            if (hasSubs) {
                html += '<button type="button" class="pub-cat-slider__expand" data-cat-id="' + cat.id + '" aria-label="Expand"><i class="bi bi-chevron-down"></i></button>';
                html += '<div class="pub-cat-slider__subs" id="pubCatSubs_' + cat.id + '">';
                cat.children.forEach(function(sub) {
                    html += '<a href="' + escapeAttr(BASE_PATH + '/categories.php?id=' + sub.id) + '" class="pub-cat-slider__sub-link">';
                    html += '<i class="bi bi-dash"></i> ' + escapeHtml(sub.name);
                    html += '</a>';
                });
                html += '</div>';
            }
            html += '</div>';
        });
        sliderBody.innerHTML = html;

        // Attach expand toggles
        sliderBody.querySelectorAll('.pub-cat-slider__expand').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var catId = btn.dataset.catId;
                var subsEl = document.getElementById('pubCatSubs_' + catId);
                if (subsEl) {
                    var isOpen = subsEl.classList.toggle('open');
                    btn.classList.toggle('open', isOpen);
                }
            });
        });
    }

    if (sliderBtn) sliderBtn.addEventListener('click', function(e) { e.stopPropagation(); openSlider(); });
    if (sliderClose) sliderClose.addEventListener('click', closeSlider);
    if (sliderBackdrop) sliderBackdrop.addEventListener('click', closeSlider);

    loadTopCats();
})();
</script>

<script>
(function () {
    var btn = document.getElementById('pubLangBtn'), dropdown = document.getElementById('pubLangDropdown');
    if (!btn || !dropdown) return;
    function open() { fetch('/api/public/languages', {credentials:'include'}).then(function(r){return r.json();}).then(function(j){
        var langs = j.data.data || j.data || []; dropdown.innerHTML = '';
        langs.forEach(function(l){ var li = document.createElement('li'); li.innerHTML = '<a href="?lang='+l.code+'">'+l.name+'</a>'; dropdown.appendChild(li); });
        dropdown.hidden = false;
    }); }
    btn.addEventListener('click', function(e){ e.stopPropagation(); if(dropdown.hidden) open(); else dropdown.hidden=true; });
    document.addEventListener('click', function(){ dropdown.hidden=true; });
})();
</script>

<div class="pub-layout">
    <?php $menuFile = __DIR__ . '/menu.php'; if (is_readable($menuFile)) include $menuFile; ?>
    <div class="pub-sidebar-backdrop" id="pubSidebarOverlay" aria-hidden="true"></div>
    <script>
    (function () {
        var h = document.getElementById('pubHamburger'), s = document.getElementById('pubSidebar'), b = document.getElementById('pubSidebarOverlay');
        if (!h || !s) return;
        h.addEventListener('click', function () { s.classList.toggle('open'); if(b) b.classList.toggle('open'); });
        if (b) b.addEventListener('click', function(){ s.classList.remove('open'); b.classList.remove('open'); });
    })();
    </script>

    <main class="pub-main-content">

    <script>
    (function() {
        var lastScrollY = window.scrollY;
        var header = document.querySelector('.pub-header');
        var catBar = document.querySelector('.pub-cat-bar');
        if (!header) return;

        // Ensure variables for height are set so CSS calc() works accurately!
        function updateHeights() {
            var h = header.getBoundingClientRect().height;
            document.documentElement.style.setProperty('--pub-header-h', h + 'px');
            // Also set the combined header+catbar height for content offset
            if (catBar) {
                var ch = catBar.getBoundingClientRect().height;
                document.documentElement.style.setProperty('--pub-header-total-h', (h + ch) + 'px');
            }
        }

        // Run on multiple lifecycle events to handle font/image loading
        updateHeights();
        window.addEventListener('resize', updateHeights);
        document.addEventListener('DOMContentLoaded', updateHeights);
        window.addEventListener('load', updateHeights);

        window.addEventListener('scroll', function() {
            var currentScroll = window.scrollY;
            if (currentScroll <= 10) {
                document.body.classList.remove('pub-smart-hidden');
            } else if (currentScroll > lastScrollY + 10) {
                // Scrolling down
                document.body.classList.add('pub-smart-hidden');
            } else if (currentScroll < lastScrollY - 5) {
                // Scrolling up
                document.body.classList.remove('pub-smart-hidden');
            }
            lastScrollY = currentScroll;
        }, { passive: true });
    })();
    </script>
