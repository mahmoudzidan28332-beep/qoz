<?php
declare(strict_types=1);

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$payload  = $GLOBALS['ADMIN_UI'] ?? [];
$user     = $payload['user'] ?? (function_exists('admin_user') ? admin_user() : []);
$permissions = $user['permissions'] ?? [];
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$isSuperAdmin = is_super_admin();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$canManage    = $isSuperAdmin || in_array('manage_stock', $permissions, true);

if (!$canManage && !$isSuperAdmin) {
    if ($isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    die('Access denied');
}

if (!function_exists('assetVer')) {
    function assetVer(string $path): string {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string)filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/stock_movements.css?v=<?= assetVer('/admin/assets/css/pages/stock_movements.css') ?>">

<div class="page-container full-page-admin" id="stockMovementsContainer" dir="<?= htmlspecialchars($dir) ?>">
    <!-- Translation Files -->
    <meta data-i18n-files="/languages/StockMovements/{lang}.json">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h2 data-i18n="title">Stock Command Center</h2>
            <p class="page-subtitle" data-i18n="subtitle">Manage inventory across global products, variants, and entities.</p>
        </div>
        <div class="page-header-actions">
             <!-- Shortcuts removed as requested -->
        </div>
    </div>

    <!-- Step 1 & 2: Searchable Dropdowns -->
    <div class="selection-strip card">
        <div class="card-body filters-grid" style="padding: 15px;">
            <?php if ($isPlatformAdmin): ?>
            <div class="filter-group">
                <label class="filter-label" data-i18n="lookup.search_tenant">Search Tenant 🔍</label>
                <div class="searchable-dropdown" id="tenantSearchContainer">
                    <input type="text" class="form-control" id="tenantSearchInput" data-i18n-placeholder="lookup.tenant_placeholder" placeholder="Type name or ID (1M+ support)...">
                    <div class="search-results" id="tenantSearchResults"></div>
                    <input type="hidden" id="selectedTenantId" value="<?= $tenantId ?>">
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" id="selectedTenantId" value="<?= $tenantId ?>">
            <?php endif; ?>

            <div class="filter-group">
                <label class="filter-label" data-i18n="lookup.select_entity">Select Entity</label>
                <select class="form-control" id="entitySelect">
                    <option value="" data-i18n="lookup.all_entities">-- All Entities --</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label" data-i18n="scan_barcode">Barcode Scanner</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="barcodeInput" data-i18n-placeholder="scan_placeholder" placeholder="Scan here...">
                    <button class="btn btn-secondary" id="btnScan"><i class="fas fa-barcode"></i></button>
                    <button class="btn btn-outline-primary" id="btnToggleCamera"><i class="fas fa-camera"></i></button>
                </div>
            </div>
        </div>

        <!-- Camera Preview Container -->
        <div id="cameraPreviewContainer" style="display:none; position:relative; background:#000; border-top:1px solid var(--border-color);">
            <div id="cameraPreview" style="width:100%; max-height:300px; overflow:hidden;"></div>
            <div style="position:absolute; bottom:10px; left:50%; transform:translateX(-50%); z-index:10;">
                <button class="btn btn-sm btn-danger" id="btnCloseCamera" data-i18n="lookup.stop_camera">Close Camera</button>
            </div>
        </div>
    </div>

    <!-- Step 3: Tabs UI -->
    <div class="tabs-container">
        <ul class="nav nav-tabs" id="stockTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-tab="global-products" type="button" data-i18n="tabs.global_products">Global Products</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-tab="global-variants" type="button" data-i18n="tabs.global_variants">Global Variants</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-tab="entity-products" type="button" data-i18n="tabs.entity_products">Entity Products</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-tab="entity-variants" type="button" data-i18n="tabs.entity_variants">Entity Variants</button>
            </li>
        </ul>
    </div>

    <!-- Step 4: Table Display -->
    <div class="card table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="table-search">
                <input type="text" id="tableSearchInput" class="form-control form-control-sm" data-i18n-placeholder="filter.search" placeholder="Search in table...">
            </div>
            <div class="table-actions">
                <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="fas fa-sync"></i> <span data-i18n="filter.refresh">Refresh</span></button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table" id="stockTable">
                    <thead>
                        <tr id="tableHeaderRow">
                            <!-- Headers injected by JS -->
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        <!-- Rows injected by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div id="paginationInfo">Showing 0 of 0</div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <!-- Step 5: Add Movement Modal -->
    <div class="sm-modal-backdrop" id="movementModal" style="display:none">
        <div class="sm-modal-panel">
            <div class="sm-modal-header">
                <h3 id="modalTitle" data-i18n="add_movement">Add Stock Movement</h3>
                <button class="btn-close-modal" id="btnCloseModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="sm-modal-body">
                <form id="movementForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" id="formTenantId" name="tenant_id" value="">
                    <input type="hidden" id="formEntityId" name="entity_id" value="">
                    <input type="hidden" id="formProductId" name="product_id" value="">
                    <input type="hidden" id="formVariantId" name="variant_id" value="">
                    <input type="hidden" id="formEntityProductId" name="entity_product_id" value="">
                    <input type="hidden" id="formEntityVariantId" name="entity_product_variant_id" value="">

                    <div class="product-summary mb-3 p-3 bg-light rounded border">
                        <strong id="summaryName">Product Name</strong><br>
                        <small class="text-muted" id="summarySku">SKU: ---</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label required" data-i18n="form.quantity">Quantity</label>
                            <input type="number" class="form-control" name="change_quantity" id="changeQuantity" required>
                        </div>
                        <div class="form-group">
                            <label class="filter-label required">Movement Type</label>
                            <select class="form-control" name="type" required>
                                <option value="restock">Restock (+)</option>
                                <option value="sale">Sale (-)</option>
                                <option value="return">Return (+)</option>
                                <option value="adjustment">Adjustment (+/-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label class="filter-label" data-i18n="form.reference_id">Reference ID (e.g. Order #)</label>
                        <input type="number" class="form-control" name="reference_id">
                    </div>

                    <div class="form-group mt-2">
                        <label class="filter-label" data-i18n="form.notes">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" data-i18n-placeholder="form.notes_placeholder" placeholder="Audit trail note..."></textarea>
                    </div>
                </form>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelModal" data-i18n="form.cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveMovement" data-i18n="form.save">Confirm Movement</button>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    window.STOCK_MOVEMENTS_CONFIG = {
        apiBase:         '/api',
        csrfToken:       <?= json_encode((string)($csrf ?? '')) ?>,
        tenantId:        <?= (int)($tenantId ?? 0) ?>,
        isPlatformAdmin: <?= ($isPlatformAdmin ? 'true' : 'false') ?>,
        lang:            <?= json_encode((string)($lang ?? 'ar')) ?>
    };
})();
</script>

<script src="/admin/assets/js/pages/stock_movements.js?v=<?= assetVer('/admin/assets/js/pages/stock_movements.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>