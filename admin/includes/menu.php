<?php
/**
 * Unified Admin Sidebar – Always stays on /admin/dashboard.php
 * - All navigation uses AJAX, never changes browser URL.
 * - Menu items are sorted by numeric 'order' (recursively).
 * - Modern icons via Font Awesome 6.
 * - Colors fetched from DB theme settings.
 * - RTL & i18n ready.
 */

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// -----------------------
// Obtain payload & settings from DB
// -----------------------
$ui_payload = $GLOBALS['ADMIN_UI'] ?? ($ADMIN_UI_PAYLOAD ?? []);
$strings = is_array($ui_payload['strings'] ?? null) ? $ui_payload['strings'] : [];
$theme = $ui_payload['theme'] ?? [];
$settings = $ui_payload['system_settings'] ?? [];
$dir = $ui_payload['direction'] ?? 'ltr';
$isRtl = $dir === 'rtl';
$GLOBALS['ADMIN_UI_LANG_DIR'] = $dir;
$GLOBALS['ADMIN_UI_LANG_CODE'] = $ui_payload['lang'] ?? ($GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en');

// -----------------------
// Helpers
// -----------------------
function getMenuSetting($key, $default = '') {
    global $settings;
    foreach ($settings as $s) {
        if ($s['setting_key'] === $key) return $s['setting_value'];
    }
    return $default;
}

function getMenuThemeValue($arrayKey, $settingKey, $default = '') {
    global $theme;
    foreach ($theme[$arrayKey] ?? [] as $item) {
        if ($item['setting_key'] === $settingKey) return $item['setting_value'];
    }
    return $default;
}

function _can_view($perm) {
    if (!$perm) return true;
    if ($perm === 'platform_admin_only') {
        global $ui_payload;
        $payload = !empty($ui_payload) ? $ui_payload : ($GLOBALS['ADMIN_UI'] ?? []);
        
        $isPlatform = !empty($payload['is_platform_admin']);
        // Some structures have it in user, some in root. Fallback properly.
        if (!$isPlatform && !empty($payload['user']['is_platform_admin'])) {
            $isPlatform = true;
        }
        
        $role = $payload['platform_role'] ?? ($payload['user']['platform_role'] ?? '');
        
        return $isPlatform && in_array($role, ['super_admin', 'admin', 'support'], true);
    }
    if (function_exists('user_can')) return user_can($perm);
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($perm, $_SESSION['permissions'], true);
    }
    return true;
}

function resolve_dot_key(array $arr, string $key) {
    if ($key === '') return null;
    if (array_key_exists($key, $arr)) return $arr[$key];
    $parts = explode('.', $key);
    $cur = $arr;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return null;
        $cur = $cur[$p];
    }
    return $cur;
}

function t(string $key, $fallback = '') {
    global $strings, $ui_payload;
    if (!$key) return $fallback;
    $val = resolve_dot_key($strings, $key);
    if (is_string($val) || is_numeric($val)) return (string)$val;
    $altKey = str_replace('.', '_', $key);
    $val = $strings[$altKey] ?? null;
    if (is_string($val) || is_numeric($val)) return (string)$val;
    $lang = $GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en';
    $jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/languages/admin/' . $lang . '.json';
    static $jsonStrings = null;
    if ($jsonStrings === null && file_exists($jsonPath)) {
        $jsonStrings = json_decode(file_get_contents($jsonPath), true) ?: [];
    }
    if ($jsonStrings && isset($jsonStrings[$key])) return $jsonStrings[$key];
    return $fallback;
}

function getMenuIcon($id, $default = '') {
    $icon = getMenuThemeValue('design_settings', 'icon_' . $id);
    if ($icon) return $icon;
    $icon = getMenuThemeValue('button_styles', 'icon_' . $id);
    if ($icon) return $icon;
    return $default;
}

function getMenuTitle($id, $default = '') {
    return getMenuSetting('menu_title_' . $id, $default);
}

/**
 * Recursively sort menu items by the 'order' key.
 */
function sortMenuByOrder(&$menu) {
    usort($menu, function($a, $b) {
        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });
    foreach ($menu as &$item) {
        if (!empty($item['children'])) {
            sortMenuByOrder($item['children']);
        }
    }
}

/**
 * Renders menu items.
 * - Parent items (with children) have href="#" and js-toggle class.
 * - Leaf items have js-ajax-link class and data-load-url attribute.
 */
function render_menu_items($menu, $level = 0) {
    global $isRtl;
    if (!is_array($menu) || empty($menu)) return '';
    $ulClass = 'sidebar-list sidebar-list--' . (int)$level;
    if ($isRtl) $ulClass .= ' rtl';
    $out = '<ul class="' . h($ulClass) . '" role="' . ($level === 0 ? 'menu' : 'group') . '">';
    foreach ($menu as $item) {
        if (isset($item['permission']) && !_can_view($item['permission'])) {
            continue;
        }
        $hasChildren = !empty($item['children']);
        $href = $hasChildren ? '#' : '/admin/' . ltrim($item['url'] ?? '', '/');
        $i18nKey = $item['i18n'] ?? ('menu.' . $item['id']);
        $titleText = t($i18nKey, str_replace('_', ' ', ucfirst($item['id'])));
        $iconClass = getMenuIcon($item['id'], $item['icon_class'] ?? '');
        $iconHtml = $iconClass ? '<i class="' . htmlspecialchars($iconClass) . ' sidebar-icon" aria-hidden="true"></i>' : '';
        $isHome = ($item['id'] === 'dashboard');
        $linkClass = $hasChildren ? ' js-toggle' : ($isHome ? ' js-home-link' : ' js-ajax-link');
        $out .= '<li class="menu-item' . ($hasChildren ? ' has-children' : '') . '">';
        $out .= '<a href="' . $href . '" class="sidebar-link' . $linkClass . '" data-load-url="' . h($item['url'] ?? '') . '" role="menuitem">';
        $out .= $iconHtml;
        $out .= '<span class="sidebar-title" data-i18n="' . h($i18nKey) . '">' . h($titleText) . '</span>';
        if ($hasChildren) {
            $out .= '<span class="sidebar-arrow">' . ($isRtl ? '❮' : '❯') . '</span>';
        }
        $out .= '</a>';
        if ($hasChildren) {
            $out .= render_menu_items($item['children'], $level + 1);
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
}

// -----------------------
// Menu definition (ordered categories)
// -----------------------
$ADMIN_MENU = [

    // -----------------------
    // Dashboard
    // -----------------------
    [
        'id' => 'dashboard',
        'order' => 1,
        'icon_class' => 'fas fa-home',
        'i18n' => 'nav.dashboard',
        'url' => 'dashboard.php',
    ],

    // -----------------------
    // Users & Access
    // -----------------------
    [
        'id' => 'users_access',
        'order' => 10,
        'icon_class' => 'fas fa-user-shield',
        'i18n' => 'menu.users_access',
        'children' => [
            ['id'=>'tenant_users','order'=>10,'icon_class'=>'fas fa-users','i18n'=>'nav.tenant_users','url'=>'fragments/tenant_users.php'],
            ['id'=>'permissions','order'=>30,'icon_class'=>'fas fa-lock','i18n'=>'nav.permissions','url'=>'fragments/permissions.php'],
        ]
    ],

    // -----------------------
    // Catalog
    // -----------------------
    [
        'id' => 'catalog',
        'order' => 20,
        'icon_class' => 'fas fa-boxes',
        'i18n' => 'menu.catalog',
        'children' => [
            ['id'=>'products','order'=>10,'icon_class'=>'fas fa-box','i18n'=>'nav.products','url'=>'fragments/products.php'],
            ['id'=>'media_studio','order'=>20,'icon_class'=>'fas fa-photo-video','i18n'=>'menu.media_studio','url'=>'fragments/media_studio.php'],
            ['id'=>'tenant_categories','order'=>25,'icon_class'=>'fas fa-sitemap','i18n'=>'menu.tenant_categories','url'=>'fragments/tenant_categories.php'],
            ['id'=>'brands','order'=>30,'icon_class'=>'fas fa-tags','i18n'=>'menu.brands','url'=>'fragments/brands.php'],
        ]
    ],

    // -----------------------
    // Sales
    // -----------------------
    [
        'id' => 'sales',
        'order' => 30,
        'icon_class' => 'fas fa-cash-register',
        'i18n' => 'menu.sales',
        'children' => [
            ['id'=>'pos','order'=>10,'icon_class'=>'fas fa-cash-register','i18n'=>'nav.pos','url'=>'fragments/pos.php'],
            ['id'=>'discounts','order'=>20,'icon_class'=>'fas fa-percent','i18n'=>'menu.discounts','url'=>'fragments/discounts.php'],
            ['id'=>'flash_sales','order'=>30,'icon_class'=>'fas fa-bolt','i18n'=>'menu.flash_sales','url'=>'fragments/flash_sales.php'],
            ['id'=>'carts','order'=>40,'icon_class'=>'fas fa-shopping-cart','i18n'=>'menu.carts','url'=>'fragments/carts.php'],
        ]
    ],

    // -----------------------
    // Orders & Logistics
    // -----------------------
    [
        'id' => 'logistics',
        'order' => 40,
        'icon_class' => 'fas fa-truck',
        'i18n' => 'menu.logistics',
        'children' => [
            ['id'=>'delivery','order'=>10,'icon_class'=>'fas fa-truck','i18n'=>'menu.delivery','url'=>'fragments/delivery.php'],
            ['id'=>'auctions','order'=>20,'icon_class'=>'fas fa-gavel','i18n'=>'menu.auctions','url'=>'fragments/auctions.php'],
            ['id'=>'stock_movements','order'=>30,'icon_class'=>'fas fa-warehouse','i18n'=>'menu.stock_movements','url'=>'fragments/stock_movements.php'],
            ['id'=>'returns','order'=>40,'icon_class'=>'fas fa-undo','i18n'=>'menu.returns','url'=>'fragments/returns.php'],
        ]
    ],

    // -----------------------
    // Finance
    // -----------------------
    [
        'id' => 'finance',
        'order' => 50,
        'icon_class' => 'fas fa-money-bill-wave',
        'i18n' => 'menu.finance',
        'children' => [
            ['id'=>'commissions','order'=>20,'icon_class'=>'fas fa-percentage','i18n'=>'menu.commissions','url'=>'fragments/commissions.php'],
            ['id'=>'subscriptions','order'=>30,'icon_class'=>'fas fa-sync','i18n'=>'menu.subscriptions','url'=>'fragments/subscriptions.php'],
            ['id'=>'plan_selection','order'=>40,'icon_class'=>'fas fa-list-check','i18n'=>'menu.plan_selection','url'=>'fragments/plan_selection.php'],
        ]
    ],

    // -----------------------
    // Content & Marketing
    // -----------------------
    [
        'id' => 'marketing',
        'order' => 60,
        'icon_class' => 'fas fa-bullhorn',
        'i18n' => 'menu.marketing',
        'children' => [
            ['id'=>'ads','order'=>10,'icon_class'=>'fas fa-ad','i18n'=>'menu.ads','url'=>'fragments/ads.php'],
            ['id'=>'banners','order'=>20,'icon_class'=>'fas fa-image','i18n'=>'menu.banners','url'=>'fragments/banners.php'],
            ['id'=>'notifications','order'=>40,'icon_class'=>'fas fa-bell','i18n'=>'menu.notifications','url'=>'fragments/notification.php'],
        ]
    ],

    // -----------------------
    // Support
    // -----------------------
    [
        'id' => 'support',
        'order' => 70,
        'icon_class' => 'fas fa-life-ring',
        'i18n' => 'menu.support',
        'children' => [
            ['id'=>'tickets','order'=>10,'icon_class'=>'fas fa-ticket-alt','i18n'=>'menu.tickets','url'=>'fragments/tickets.php'],
        ]
    ],

    // -----------------------
    // Business / Entities
    // -----------------------
    [
        'id' => 'business',
        'order' => 80,
        'icon_class' => 'fas fa-building',
        'i18n' => 'menu.business',
        'children' => [
            ['id'=>'entities','order'=>10,'icon_class'=>'fas fa-building','i18n'=>'menu.entities','url'=>'fragments/entities.php'],
            ['id'=>'entity_product_variants','order'=>15,'icon_class'=>'fas fa-cubes','i18n'=>'menu.entity_product_variants','url'=>'fragments/entity_product_variants.php'],
            ['id'=>'entities_payment','order'=>20,'icon_class'=>'fas fa-credit-card','i18n'=>'menu.entities_payment','url'=>'fragments/entities_Payment.php'],
            ['id'=>'jobs','order'=>30,'icon_class'=>'fas fa-briefcase','i18n'=>'menu.jobs','url'=>'fragments/jobs.php'],
        ]
    ],

    // -----------------------
    // System
    // -----------------------
    [
        'id' => 'system',
        'order' => 90,
        'icon_class' => 'fas fa-cogs',
        'i18n' => 'menu.system',
        'children' => [
            ['id'=>'themes','order'=>20,'icon_class'=>'fas fa-palette','i18n'=>'nav.settings','url'=>'fragments/themes.php'],
            ['id'=>'platform_report','order'=>25,'icon_class'=>'fas fa-chart-line','i18n'=>'menu.platform_report','url'=>'fragments/platform_report.php'],
            ['id'=>'addresses','order'=>30,'icon_class'=>'fas fa-map-marker-alt','i18n'=>'nav.addresses','url'=>'fragments/addresses.php'],
        ]
    ],

    // -----------------------
    // Platform Admin
    // -----------------------
    [
        'id' => 'platform_admin',
        'order' => 100,
        'icon_class' => 'fas fa-server',
        'i18n' => 'menu.platform_admin',
        'permission' => 'platform_admin_only',
        'children' => [
            ['id'=>'users','order'=>10,'icon_class'=>'fas fa-users-cog','i18n'=>'menu.platform_users','url'=>'fragments/users.php','permission'=>'platform_admin_only'],
            ['id'=>'categories','order'=>20,'icon_class'=>'fas fa-layer-group','i18n'=>'menu.platform_categories','url'=>'fragments/categories.php','permission'=>'platform_admin_only'],
            ['id'=>'escrow','order'=>30,'icon_class'=>'fas fa-shield-alt','i18n'=>'menu.platform_escrow','url'=>'fragments/escrow.php','permission'=>'platform_admin_only'],
            ['id'=>'homepage_sections','order'=>40,'icon_class'=>'fas fa-columns','i18n'=>'menu.homepage_sections','url'=>'fragments/homepage_sections.php','permission'=>'platform_admin_only'],
            ['id'=>'job_categories','order'=>50,'icon_class'=>'fas fa-briefcase','i18n'=>'menu.job_categories','url'=>'fragments/job_categories.php','permission'=>'platform_admin_only'],
            ['id'=>'ticket_categories','order'=>60,'icon_class'=>'fas fa-ticket-alt','i18n'=>'menu.ticket_categories','url'=>'fragments/ticket_categories.php','permission'=>'platform_admin_only'],
            ['id'=>'queues','order'=>70,'icon_class'=>'fas fa-tasks','i18n'=>'menu.system_queues','url'=>'fragments/queues.php','permission'=>'platform_admin_only'],
            ['id'=>'seo_meta','order'=>80,'icon_class'=>'fas fa-search-dollar','i18n'=>'menu.seo_meta','url'=>'fragments/seo_meta.php','permission'=>'platform_admin_only'],
            ['id'=>'tenant','order'=>90,'icon_class'=>'fas fa-store-alt','i18n'=>'menu.tenants','url'=>'fragments/tenant.php','permission'=>'platform_admin_only'],
            ['id'=>'bad_words','order'=>100,'icon_class'=>'fas fa-comment-slash','i18n'=>'menu.bad_words','url'=>'fragments/bad_words.php','permission'=>'platform_admin_only'],
        ]
    ],

];

// Sort menu (including children) by order
sortMenuByOrder($ADMIN_MENU);

// -----------------------
// Dynamic CSS from DB
// -----------------------
// Sidebar CSS is fully DB-driven via AdminUiThemeLoader (header.php) + sidebar.css.
// No inline styles needed — all colors come from :root CSS variables.

// -----------------------
// Render the sidebar
// -----------------------
echo '<nav class="admin-sidebar-nav ' . ($isRtl ? 'rtl' : '') . '">';
echo render_menu_items($ADMIN_MENU, 0);
echo '</nav>';

// -----------------------
// JavaScript: Toggle categories & load fragments without changing URL
// -----------------------
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Toggle expand/collapse for categories (accordion behavior)
    function closeDescendants(item) {
        item.querySelectorAll(".menu-item.has-children.is-open").forEach(function(desc) {
            desc.classList.remove("is-open");
        });
    }

    document.querySelectorAll(".js-toggle").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();     // Prevents any URL change (href="#")
            e.stopPropagation();
            const currentItem = this.closest(".menu-item");
            const parentList  = currentItem.parentElement;
            const isOpening   = !currentItem.classList.contains("is-open");

            // Accordion: close all other open siblings at the same level
            if (isOpening && parentList) {
                parentList.querySelectorAll(":scope > .menu-item.has-children.is-open").forEach(sibling => {
                    if (sibling !== currentItem) {
                        closeDescendants(sibling);
                        sibling.classList.remove("is-open");
                    }
                });
            }

            // Closing: also close all open descendants inside this item
            if (!isOpening) {
                closeDescendants(currentItem);
            }

            currentItem.classList.toggle("is-open");
        });
    });

    // 2. Dashboard / Home link – reloads the page (clears filters)
    document.querySelectorAll(".js-home-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            window.location.reload();
        });
    });

    // 3. AJAX loading for fragment links – NEVER changes the browser URL
    document.querySelectorAll(".js-ajax-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();     // Prevents navigation away from dashboard.php
            const url = this.getAttribute("data-load-url");
            if (!url) return;
            
            // Use the global fragment loader (adjust function name as needed)
            if (typeof window.loadAdminFragment === "function") {
                window.loadAdminFragment(url);
            } else if (typeof window.loadPage === "function") {
                window.loadPage(url);
            } else {
                console.warn("No AJAX loader found. Falling back to full page load.");
                // Fallback: do NOT change URL, but load the fragment manually
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const mainContent = document.getElementById("main-content");
                        if (mainContent) mainContent.innerHTML = html;
                    });
            }
            
            // Highlight the active link
            document.querySelectorAll(".sidebar-link").forEach(el => el.classList.remove("active"));
            this.classList.add("active");
        });
    });
});
</script>';