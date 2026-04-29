<?php
declare(strict_types=1);

/**
 * /admin/fragments/homepage_sections.php
 * Homepage Sections Management – Two-Tab UI
 * Tab 1: Homepage Sections (homepage section layout/components)
 * Tab 2: Store Page Sections (store page section configuration)
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// PLATFORM ADMIN STRICT CHECK
// ════════════════════════════════════════════════════════════
$isPlatformStrict = function_exists('is_platform_admin') ? is_platform_admin() : false;
$roleStrict = function_exists('get_platform_role') ? get_platform_role() : '';
if (!$isPlatformStrict || !in_array($roleStrict, ['super_admin', 'admin', 'support'], true)) {
    if (isset($isFragment) && $isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied. Platform Admin strictly required.']);
        exit;
    }
    http_response_code(403);
    exit('Access denied. Platform Admin strictly required.');
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManage = can('manage_settings') || is_super_admin();
$canView   = $canManage || can_view_all('settings') || can_view_own('settings') || can_view_tenant('settings') || is_super_admin();

if (!$canView) {
    http_response_code(403);
    die('Access denied');
}

$apiBase = '/api';

// Translation helpers
if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback ?? $key);
        }
        return $fallback ?? $key;
    }
}

$_hstStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/HomepageSections/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_hstStrings = $_json['strings'];
    }
}

function _hst($key, $fallback = '') {
    global $_hstStrings;
    $keys = explode('.', $key);
    $val = $_hstStrings;
    foreach ($keys as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback ?: $key;
        }
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>

<link rel="stylesheet" href="/admin/assets/css/pages/homepage_sections.css?v=<?= time() ?>">
<meta data-page="homepage_sections"
      data-i18n-files="/languages/HomepageSections/<?= rawurlencode($lang) ?>.json">

<div id="homepage-sections-module" class="hs-module" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="hs-header">
        <h2 data-i18n="title"><?= htmlspecialchars(_hst('title', 'Homepage Sections')) ?></h2>
        <p data-i18n="subtitle"><?= htmlspecialchars(_hst('subtitle', 'Manage homepage and store page sections')) ?></p>
    </div>

    <!-- Tabs Navigation -->
    <div class="hs-tabs">
        <button class="hs-tab active" data-tab="homepage">
            <i class="fas fa-home"></i>
            <span data-i18n="tabs.homepage"><?= htmlspecialchars(_hst('tabs.homepage', 'Homepage Sections')) ?></span>
            <span class="hs-badge" id="homepage-count">0</span>
        </button>
        <button class="hs-tab" data-tab="store_pages">
            <i class="fas fa-store"></i>
            <span data-i18n="tabs.store_pages"><?= htmlspecialchars(_hst('tabs.store_pages', 'Store Page Sections')) ?></span>
            <span class="hs-badge" id="store-pages-count">0</span>
        </button>
    </div>

    <!-- ═══════════════ Tab 1: Homepage Sections ═══════════════ -->
    <div class="hs-tab-content active" data-tab-content="homepage">
        <div class="hs-toolbar">
            <?php if ($canManage): ?>
            <button class="hs-btn hs-btn-primary" id="btn-add-homepage-section" data-i18n="homepage.add_section">
                <i class="fas fa-plus"></i> <span><?= htmlspecialchars(_hst('homepage.add_section', 'Add Section')) ?></span>
            </button>
            <?php endif; ?>
        </div>
        <div class="hs-table-wrap">
            <table class="hs-data-table" id="homepage-sections-table">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_hst('table.id', 'ID')) ?></th>
                        <th data-i18n="table.section_type"><?= htmlspecialchars(_hst('table.section_type', 'Section Type')) ?></th>
                        <th data-i18n="table.component"><?= htmlspecialchars(_hst('table.component', 'Component')) ?></th>
                        <th data-i18n="table.title"><?= htmlspecialchars(_hst('table.title', 'Title')) ?></th>
                        <th data-i18n="table.subtitle"><?= htmlspecialchars(_hst('table.subtitle', 'Subtitle')) ?></th>
                        <th data-i18n="table.layout_type"><?= htmlspecialchars(_hst('table.layout_type', 'Layout')) ?></th>
                        <th data-i18n="table.items_per_row"><?= htmlspecialchars(_hst('table.items_per_row', 'Items/Row')) ?></th>
                        <th data-i18n="table.background_color"><?= htmlspecialchars(_hst('table.background_color', 'BG Color')) ?></th>
                        <th data-i18n="table.text_color"><?= htmlspecialchars(_hst('table.text_color', 'Text Color')) ?></th>
                        <th data-i18n="table.data_source"><?= htmlspecialchars(_hst('table.data_source', 'Data Source')) ?></th>
                        <th data-i18n="table.is_active"><?= htmlspecialchars(_hst('table.is_active', 'Active')) ?></th>
                        <th data-i18n="table.sort_order"><?= htmlspecialchars(_hst('table.sort_order', 'Order')) ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_hst('table.actions', 'Actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="homepage-sections-body">
                    <tr><td colspan="13" class="hs-table-empty" data-i18n="homepage.no_sections"><?= htmlspecialchars(_hst('homepage.no_sections', 'No sections yet')) ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php if ($canManage): ?>
        <div class="hs-actions">
            <button class="hs-btn hs-btn-success" id="btn-save-homepage" data-i18n="homepage.save">
                <i class="fas fa-save"></i> <span><?= htmlspecialchars(_hst('homepage.save', 'Save Changes')) ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════ Tab 2: Store Page Sections ═══════════════ -->
    <div class="hs-tab-content" data-tab-content="store_pages">
        <!-- Store page selector -->
        <div class="hs-filter-card">
            <div class="hs-form-group">
                <label data-i18n="store_pages.select_entity"><?= htmlspecialchars(_hst('store_pages.select_entity', 'Select Store')) ?></label>
                <select id="store-entity-select" class="hs-input">
                    <option value="" data-i18n="store_pages.select_entity_placeholder"><?= htmlspecialchars(_hst('store_pages.select_entity_placeholder', '-- Select Store --')) ?></option>
                </select>
                <small class="hs-entity-info" id="store-entity-info" style="display:none" data-i18n="store_pages.no_entity_selected"><?= htmlspecialchars(_hst('store_pages.no_entity_selected', 'Please select a store first')) ?></small>
            </div>
            <div class="hs-form-group">
                <label data-i18n="store_pages.page_type"><?= htmlspecialchars(_hst('store_pages.page_type', 'Page Type')) ?></label>
                <select id="store-page-type" class="hs-input">
                    <option value="store"><?= htmlspecialchars(_hst('store_pages.type_store', 'Store')) ?></option>
                </select>
            </div>
        </div>
        <div class="hs-toolbar">
            <?php if ($canManage): ?>
            <button class="hs-btn hs-btn-primary" id="btn-add-store-section" data-i18n="store_pages.add_section">
                <i class="fas fa-plus"></i> <span><?= htmlspecialchars(_hst('store_pages.add_section', 'Add Section')) ?></span>
            </button>
            <?php endif; ?>
        </div>
        <div class="hs-table-wrap">
            <table class="hs-data-table" id="store-sections-table">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_hst('table.id', 'ID')) ?></th>
                        <th data-i18n="table.section_type"><?= htmlspecialchars(_hst('table.section_type', 'Section Type')) ?></th>
                        <th data-i18n="table.title"><?= htmlspecialchars(_hst('table.title', 'Title')) ?></th>
                        <th data-i18n="table.position"><?= htmlspecialchars(_hst('table.position', 'Position')) ?></th>
                        <th data-i18n="table.settings"><?= htmlspecialchars(_hst('table.settings', 'Settings')) ?></th>
                        <th data-i18n="table.is_active"><?= htmlspecialchars(_hst('table.is_active', 'Active')) ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_hst('table.actions', 'Actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="store-sections-body">
                    <tr><td colspan="7" class="hs-table-empty" data-i18n="store_pages.no_sections"><?= htmlspecialchars(_hst('store_pages.no_sections', 'No sections yet')) ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php if ($canManage): ?>
        <div class="hs-actions">
            <button class="hs-btn hs-btn-success" id="btn-save-store-sections" data-i18n="store_pages.save">
                <i class="fas fa-save"></i> <span><?= htmlspecialchars(_hst('store_pages.save', 'Save Changes')) ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!-- Modal: Add/Edit Section (shared)   -->
    <!-- ═══════════════════════════════════ -->
    <div class="hs-modal-overlay" id="section-modal" style="display:none">
        <div class="hs-modal">
            <div class="hs-modal-header">
                <h3 id="modal-title" data-i18n="modal.add_section"><?= htmlspecialchars(_hst('modal.add_section', 'Add Section')) ?></h3>
                <button class="hs-modal-close" id="modal-close">&times;</button>
            </div>
            <div class="hs-modal-body">
                <!-- Section Type -->
                <div class="hs-form-group">
                    <label data-i18n="modal.section_type"><?= htmlspecialchars(_hst('modal.section_type', 'Section Type')) ?></label>
                    <select id="modal-section-type" class="hs-input"></select>
                </div>
                <!-- Component (homepage only) -->
                <div class="hs-form-group" id="modal-component-group">
                    <label data-i18n="modal.component"><?= htmlspecialchars(_hst('modal.component', 'Component')) ?></label>
                    <select id="modal-component" class="hs-input"></select>
                </div>
                <!-- Layout Type (homepage only) -->
                <div class="hs-form-group" id="modal-layout-group">
                    <label data-i18n="modal.layout_type"><?= htmlspecialchars(_hst('modal.layout_type', 'Layout Type')) ?></label>
                    <select id="modal-layout-type" class="hs-input">
                        <option value="grid"><?= htmlspecialchars(_hst('modal.layout_grid', 'Grid')) ?></option>
                        <option value="slider"><?= htmlspecialchars(_hst('modal.layout_slider', 'Slider')) ?></option>
                        <option value="list"><?= htmlspecialchars(_hst('modal.layout_list', 'List')) ?></option>
                        <option value="carousel"><?= htmlspecialchars(_hst('modal.layout_carousel', 'Carousel')) ?></option>
                        <option value="masonry"><?= htmlspecialchars(_hst('modal.layout_masonry', 'Masonry')) ?></option>
                        <option value="full"><?= htmlspecialchars(_hst('modal.layout_full', 'Full Width')) ?></option>
                    </select>
                </div>
                <!-- Items Per Row (homepage only) -->
                <div class="hs-form-group" id="modal-items-group">
                    <label data-i18n="modal.items_per_row"><?= htmlspecialchars(_hst('modal.items_per_row', 'Items Per Row')) ?></label>
                    <input type="number" id="modal-items-per-row" class="hs-input" min="1" max="12" value="4">
                </div>
                <!-- Background Color -->
                <div class="hs-form-group">
                    <label data-i18n="modal.background_color"><?= htmlspecialchars(_hst('modal.background_color', 'Background Color')) ?></label>
                    <input type="color" id="modal-bg-color" value="#ffffff">
                </div>
                <!-- Text Color (homepage only) -->
                <div class="hs-form-group" id="modal-text-color-group">
                    <label data-i18n="modal.text_color"><?= htmlspecialchars(_hst('modal.text_color', 'Text Color')) ?></label>
                    <input type="color" id="modal-text-color" value="#000000">
                </div>
                <!-- Is Active -->
                <div class="hs-form-group">
                    <label data-i18n="modal.is_active"><?= htmlspecialchars(_hst('modal.is_active', 'Active')) ?></label>
                    <input type="checkbox" id="modal-is-active" checked>
                </div>
                <!-- Layout Config JSON (homepage only) -->
                <div class="hs-form-group" id="modal-layout-config-group" style="display:none">
                    <label data-i18n="modal.layout_config"><?= htmlspecialchars(_hst('modal.layout_config', 'Layout Config (JSON)')) ?></label>
                    <textarea id="modal-layout-config" class="hs-input" rows="3" placeholder="{}"></textarea>
                </div>
                <!-- Padding (homepage only) -->
                <div class="hs-form-group" id="modal-padding-group" style="display:none">
                    <label data-i18n="modal.padding"><?= htmlspecialchars(_hst('modal.padding', 'Padding')) ?></label>
                    <input type="text" id="modal-padding" class="hs-input" placeholder="e.g. 16px">
                </div>
                <!-- Custom CSS (homepage only) -->
                <div class="hs-form-group" id="modal-custom-css-group" style="display:none">
                    <label data-i18n="modal.custom_css"><?= htmlspecialchars(_hst('modal.custom_css', 'Custom CSS')) ?></label>
                    <textarea id="modal-custom-css" class="hs-input" rows="3" placeholder=""></textarea>
                </div>
                <!-- Custom HTML (homepage only) -->
                <div class="hs-form-group" id="modal-custom-html-group" style="display:none">
                    <label data-i18n="modal.custom_html"><?= htmlspecialchars(_hst('modal.custom_html', 'Custom HTML')) ?></label>
                    <textarea id="modal-custom-html" class="hs-input" rows="3" placeholder=""></textarea>
                </div>
                <!-- Data Source (homepage only) -->
                <div class="hs-form-group" id="modal-data-source-group" style="display:none">
                    <label data-i18n="modal.data_source"><?= htmlspecialchars(_hst('modal.data_source', 'Data Source')) ?></label>
                    <input type="text" id="modal-data-source" class="hs-input" placeholder="">
                </div>
                <!-- Settings JSON (store sections) -->
                <div class="hs-form-group" id="modal-settings-group" style="display:none">
                    <label data-i18n="modal.settings"><?= htmlspecialchars(_hst('modal.settings', 'Settings (JSON)')) ?></label>
                    <textarea id="modal-settings" class="hs-input" rows="4" placeholder="{}"></textarea>
                </div>
                <!-- Translations (dynamic) -->
                <div class="hs-form-group">
                    <h4 data-i18n="modal.translations"><?= htmlspecialchars(_hst('modal.translations', 'Translations')) ?></h4>
                    <div id="modal-translations" class="hs-translations-container">
                        <!-- Dynamic language rows will be injected by JS -->
                        <div class="hs-translations-loading" data-i18n="common.loading"><?= htmlspecialchars(_hst('common.loading', 'Loading languages...')) ?></div>
                    </div>
                </div>
            </div>
            <div class="hs-modal-footer">
                <button class="hs-btn hs-btn-secondary" id="modal-cancel" data-i18n="modal.cancel">
                    <?= htmlspecialchars(_hst('modal.cancel', 'Cancel')) ?>
                </button>
                <button class="hs-btn hs-btn-primary" id="modal-save" data-i18n="modal.save">
                    <?= htmlspecialchars(_hst('modal.save', 'Save')) ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="hs-toast" id="hs-toast" style="display:none"></div>

</div>

<!-- Hidden data -->
<input type="hidden" id="hsCsrfToken" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" id="hsTenantId" value="<?= (int)$tenantId ?>">
<input type="hidden" id="hsUserId" value="<?= (int)$userId ?>">
<input type="hidden" id="hsLang" value="<?= htmlspecialchars($lang) ?>">
<input type="hidden" id="hsCanManage" value="<?= $canManage ? '1' : '0' ?>">
<input type="hidden" id="hsIsSuperAdmin" value="<?= is_super_admin() ? '1' : '0' ?>">

<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/homepage_sections.js?v=<?= time() ?>"></script>
<script>
(function(){
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.HomepageSectionsModule && typeof window.HomepageSectionsModule.init === 'function') {
            clearInterval(interval);
            window.HomepageSectionsModule.init();
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[HomepageSectionsModule] Timeout waiting for module');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/homepage_sections.js?v=<?= time() ?>"></script>
<script>
(function(){
    function tryInit() {
        if (window.HomepageSectionsModule && typeof window.HomepageSectionsModule.init === 'function') {
            window.HomepageSectionsModule.init();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
    window.page = { run: tryInit };
})();
</script>
<?php endif; ?>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>