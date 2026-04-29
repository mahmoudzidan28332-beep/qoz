<?php
declare(strict_types=1);

/**
 * /admin/fragments/entity_product_variants.php
 * Standalone Entity Products & Variants Management – Two-Tab UI
 * Tab 1: Entity Products (entity_products table + product_pricing)
 * Tab 2: Entity Product Variants (entity_product_variants table)
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
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManage = can('manage_entities') || can('manage_entity_products') || is_super_admin();
$canView   = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities') || is_super_admin();

if (!$canView) {
    http_response_code(403);
    die('Access denied');
}

$entityId = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0
    ? (int)$_GET['entity_id']
    : (isset($_SESSION['entity_id']) && (int)$_SESSION['entity_id'] > 0
        ? (int)$_SESSION['entity_id']
        : 0);

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

$_epvStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/EntityProductVariants/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_epvStrings = $_json['strings'];
    }
}

function _epvt($key, $fallback = '') {
    global $_epvStrings;
    $keys = explode('.', $key);
    $val = $_epvStrings;
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

<link rel="stylesheet" href="/admin/assets/css/pages/entity_product_variants.css?v=<?= time() ?>">
<meta data-page="entity_product_variants"
      data-i18n-files="/languages/EntityProductVariants/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="epvPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1 data-i18n="title"><?= htmlspecialchars(_epvt('title', 'Entity Products & Variants')) ?></h1>
        <p data-i18n="subtitle"><?= htmlspecialchars(_epvt('subtitle', 'Manage entity products and their variants')) ?></p>
    </div>

    <!-- Super Admin: Tenant → Entity Cascade -->
    <?php if (is_super_admin()): ?>
    <div class="epv-card" id="epvEntityFilterCard">
        <div class="epv-card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="epv-form-group" style="min-width:150px;">
                <label data-i18n="filter.tenant_id"><?= htmlspecialchars(_epvt('filter.tenant_id', 'Tenant ID')) ?></label>
                <div style="display:flex;gap:5px;">
                    <input type="number" id="epvTenantIdInput" class="epv-input"
                           placeholder="<?= htmlspecialchars(_epvt('filter.enter_tenant_id', 'Enter Tenant ID')) ?>"
                           min="1" style="width:120px;"
                           value="<?= $tenantId ? (int)$tenantId : '' ?>">
                    <button id="epvBtnVerifyTenant" class="epv-btn epv-btn-secondary" style="white-space:nowrap;"
                            data-i18n="filter.verify"><?= htmlspecialchars(_epvt('filter.verify', 'Verify')) ?></button>
                </div>
                <small id="epvTenantNameDisplay" style="display:none;margin-top:4px;"></small>
            </div>
            <div class="epv-form-group" style="flex:1;min-width:250px;">
                <label data-i18n="filter.entity"><?= htmlspecialchars(_epvt('filter.entity', 'Entity')) ?></label>
                <select id="epvEntityFilter" class="epv-input">
                    <option value=""><?= htmlspecialchars(_epvt('filter.select_entity', 'Select Entity...')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Tenant Admin: Entity Selector -->
    <div class="epv-card">
        <div class="epv-card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="epv-form-group" style="flex:1;min-width:250px;">
                <label data-i18n="filter.entity"><?= htmlspecialchars(_epvt('filter.entity', 'Entity')) ?></label>
                <select id="epvEntityFilter" class="epv-input">
                    <option value=""><?= htmlspecialchars(_epvt('filter.select_entity', 'Select Entity...')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════ -->
    <!-- Tab Navigation                      -->
    <!-- ═══════════════════════════════════ -->
    <div id="epvTabsContainer" style="display:none;">
        <div class="epv-tabs">
            <button class="epv-tab active" id="epvTabProducts" data-tab="products">
                <?= htmlspecialchars(_epvt('products.title', 'Entity Products')) ?>
                <span class="epv-tab-badge" id="epvProductsCount">0</span>
            </button>
            <button class="epv-tab" id="epvTabVariants" data-tab="variants">
                <?= htmlspecialchars(_epvt('variants.title', 'Product Variants')) ?>
                <span class="epv-tab-badge" id="epvVariantsCount">0</span>
            </button>
        </div>

        <!-- ═══════════════ Tab 1: Products ═══════════════ -->
        <div class="epv-tab-content active" id="epvProductsContent" data-tab="products">
            <div class="epv-section-header">
                <div class="epv-search-box">
                    <input type="text" id="epvProductSearch" class="epv-input"
                           placeholder="<?= htmlspecialchars(_epvt('products.search_products', 'Search products...')) ?>">
                </div>
                <?php if ($canManage): ?>
                <button id="epvBtnAddProduct" class="epv-btn epv-btn-primary" data-i18n="products.add_product">
                    <?= htmlspecialchars(_epvt('products.add_product', 'Add Products')) ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="epvProductsList" class="epv-items-list"></div>
            <div id="epvProductsEmpty" class="epv-empty-state" style="display:none;">
                <p data-i18n="products.no_products"><?= htmlspecialchars(_epvt('products.no_products', 'No entity products yet. Add products to get started.')) ?></p>
            </div>

            <?php if ($canManage): ?>
            <div class="epv-section-footer" id="epvProductsFooter" style="display:none;">
                <button id="epvBtnSaveProducts" class="epv-btn epv-btn-success" data-i18n="products.save_products">
                    <?= htmlspecialchars(_epvt('products.save_products', 'Save Products')) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ Tab 2: Variants ═══════════════ -->
        <div class="epv-tab-content" id="epvVariantsContent" data-tab="variants">
            <div class="epv-section-header">
                <div class="epv-search-box">
                    <input type="text" id="epvVariantSearch" class="epv-input"
                           placeholder="<?= htmlspecialchars(_epvt('variants.search_variants', 'Search variants...')) ?>">
                </div>
                <?php if ($canManage): ?>
                <button id="epvBtnAddVariant" class="epv-btn epv-btn-primary" data-i18n="variants.add_variant">
                    <?= htmlspecialchars(_epvt('variants.add_variant', 'Add Variants')) ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="epvVariantsList" class="epv-items-list"></div>
            <div id="epvVariantsEmpty" class="epv-empty-state" style="display:none;">
                <p data-i18n="variants.no_variants"><?= htmlspecialchars(_epvt('variants.no_variants', 'No variants assigned yet. Add variants from entity products.')) ?></p>
            </div>

            <?php if ($canManage): ?>
            <div class="epv-section-footer" id="epvVariantsFooter" style="display:none;">
                <button id="epvBtnSaveVariants" class="epv-btn epv-btn-success" data-i18n="variants.save_variants">
                    <?= htmlspecialchars(_epvt('variants.save_variants', 'Save Variants')) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!-- Modal: Product Selection            -->
    <!-- ═══════════════════════════════════ -->
    <div class="epv-modal-overlay" id="epvProductsModal" style="display:none;">
        <div class="epv-modal">
            <div class="epv-modal-header">
                <h3 data-i18n="products.select_products"><?= htmlspecialchars(_epvt('products.select_products', 'Select Products')) ?></h3>
                <button class="epv-modal-close" id="epvCloseProductsModal">&times;</button>
            </div>
            <div class="epv-modal-body">
                <input type="text" id="epvModalProductSearch" class="epv-input"
                       placeholder="<?= htmlspecialchars(_epvt('products.search_products', 'Search products...')) ?>">
                <div class="epv-modal-actions">
                    <button class="epv-btn epv-btn-sm epv-btn-secondary" id="epvSelectAllProducts"
                            data-i18n="products.select_all"><?= htmlspecialchars(_epvt('products.select_all', 'Select All')) ?></button>
                    <button class="epv-btn epv-btn-sm epv-btn-secondary" id="epvDeselectAllProducts"
                            data-i18n="products.deselect_all"><?= htmlspecialchars(_epvt('products.deselect_all', 'Deselect All')) ?></button>
                    <span id="epvProductSelectedCount" class="epv-selected-count">0 <?= htmlspecialchars(_epvt('products.selected_count', 'selected')) ?></span>
                </div>
                <div id="epvModalProductsList" class="epv-modal-list">
                    <div class="epv-loading" data-i18n="products.loading_products">
                        <?= htmlspecialchars(_epvt('products.loading_products', 'Loading products...')) ?>
                    </div>
                </div>
            </div>
            <div class="epv-modal-footer">
                <button class="epv-btn epv-btn-primary" id="epvConfirmProductSelection"
                        data-i18n="products.add_selected"><?= htmlspecialchars(_epvt('products.add_selected', 'Add Selected')) ?></button>
                <button class="epv-btn epv-btn-secondary" id="epvCancelProductSelection"
                        data-i18n="cancel"><?= htmlspecialchars(_epvt('cancel', 'Cancel')) ?></button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!-- Modal: Variant Selection            -->
    <!-- ═══════════════════════════════════ -->
    <div class="epv-modal-overlay" id="epvVariantsModal" style="display:none;">
        <div class="epv-modal">
            <div class="epv-modal-header">
                <h3 data-i18n="variants.select_variants"><?= htmlspecialchars(_epvt('variants.select_variants', 'Select Variants')) ?></h3>
                <button class="epv-modal-close" id="epvCloseVariantsModal">&times;</button>
            </div>
            <div class="epv-modal-body">
                <div class="epv-form-group" style="margin-bottom:10px;">
                    <label data-i18n="filter.product"><?= htmlspecialchars(_epvt('filter.product', 'Product')) ?></label>
                    <select id="epvModalVariantProductFilter" class="epv-input">
                        <option value=""><?= htmlspecialchars(_epvt('variants.select_product_first', 'Select a product first')) ?></option>
                    </select>
                </div>
                <div class="epv-modal-actions">
                    <button class="epv-btn epv-btn-sm epv-btn-secondary" id="epvSelectAllVariants"
                            data-i18n="products.select_all"><?= htmlspecialchars(_epvt('products.select_all', 'Select All')) ?></button>
                    <button class="epv-btn epv-btn-sm epv-btn-secondary" id="epvDeselectAllVariants"
                            data-i18n="products.deselect_all"><?= htmlspecialchars(_epvt('products.deselect_all', 'Deselect All')) ?></button>
                    <span id="epvVariantSelectedCount" class="epv-selected-count">0 <?= htmlspecialchars(_epvt('variants.selected_count', 'selected')) ?></span>
                </div>
                <div id="epvModalVariantsList" class="epv-modal-list">
                    <div class="epv-loading" data-i18n="variants.select_product_to_see_variants">
                        <?= htmlspecialchars(_epvt('variants.select_product_to_see_variants', 'Select a product to see its variants')) ?>
                    </div>
                </div>
            </div>
            <div class="epv-modal-footer">
                <button class="epv-btn epv-btn-primary" id="epvConfirmVariantSelection"
                        data-i18n="variants.add_selected"><?= htmlspecialchars(_epvt('variants.add_selected', 'Add Selected')) ?></button>
                <button class="epv-btn epv-btn-secondary" id="epvCancelVariantSelection"
                        data-i18n="cancel"><?= htmlspecialchars(_epvt('cancel', 'Cancel')) ?></button>
            </div>
        </div>
    </div>

</div>

<!-- Hidden data -->
<input type="hidden" id="epvCsrfToken" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" id="epvTenantId" value="<?= (int)$tenantId ?>">
<input type="hidden" id="epvEntityId" value="<?= (int)$entityId ?>">
<input type="hidden" id="epvLang" value="<?= htmlspecialchars($lang) ?>">
<input type="hidden" id="epvCanManage" value="<?= $canManage ? '1' : '0' ?>">
<input type="hidden" id="epvIsSuperAdmin" value="<?= is_super_admin() ? '1' : '0' ?>">

<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/entity_product_variants.js?v=<?= time() ?>"></script>
<script>
(function(){
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.EntityProductVariants && typeof window.EntityProductVariants.init === 'function') {
            clearInterval(interval);
            window.EntityProductVariants.init();
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[EntityProductVariants] Timeout waiting for module');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/entity_product_variants.js?v=<?= time() ?>"></script>
<script>
(function(){
    function tryInit() {
        if (window.EntityProductVariants && typeof window.EntityProductVariants.init === 'function') {
            window.EntityProductVariants.init();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>