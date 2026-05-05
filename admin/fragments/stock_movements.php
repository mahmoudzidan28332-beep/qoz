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
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$payload  = $GLOBALS['ADMIN_UI'] ?? [];
$user     = $payload['user'] ?? (function_exists('admin_user') ? admin_user() : []);
$permissions = $user['permissions'] ?? [];
$roles    = $user['roles'] ?? [];
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$entityId = $payload['entity_id'] ?? ($_SESSION['entity_id'] ?? 0);
$userId   = $user['id'] ?? ($_SESSION['user_id'] ?? 0);

$isSuperAdmin = is_super_admin();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$canManage    = $isSuperAdmin ||
                in_array('manage_stock',    $permissions, true) ||
                in_array('manage_products', $permissions, true);
$canCreate    = $canManage;
$canEdit      = $canManage;
$canDelete    = $canManage;

if (!$canManage && !$isSuperAdmin) {
    if ($isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// assetVer()
// ════════════════════════════════════════════════════════════
if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
        }
        return $cache[$path];
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER — load from file (PHP-side, synchronous)
// ════════════════════════════════════════════════════════════
$_smtAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_smtLangCode     = in_array($lang, $_smtAllowedLangs) ? $lang : 'en';
$_smtStringsFile  = __DIR__ . '/../../languages/StockMovements/' . $_smtLangCode . '.json';
$_smtStringsRaw   = file_exists($_smtStringsFile)
    ? (json_decode(file_get_contents($_smtStringsFile), true) ?: [])
    : [];

// Flatten nested JSON to dot-notation for JS
function _smtFlatten(array $arr, string $prefix = ''): array {
    $result = [];
    foreach ($arr as $k => $v) {
        $key = $prefix ? $prefix . '.' . $k : $k;
        if (is_array($v)) {
            $result += _smtFlatten($v, $key);
        } else {
            $result[$key] = $v;
        }
    }
    return $result;
}
$_smtStringsFlat = _smtFlatten($_smtStringsRaw);

function _smt(string $key, string $fallback = ''): string {
    global $_smtStringsRaw;
    $parts = explode('.', $key);
    $val   = $_smtStringsRaw;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/stock_movements.css?v=<?= assetVer('/admin/assets/css/pages/stock_movements.css') ?>">

<meta data-page="stock_movements"
      data-i18n-files="/languages/StockMovements/<?= rawurlencode($_smtLangCode) ?>.json">

<div class="page-container full-page-admin" id="stockMovementsContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h2 data-i18n="title"><?= _smt('title', 'Stock Movements') ?></h2>
            <p class="page-subtitle" data-i18n="subtitle">
                <?= _smt('subtitle', 'Track product inventory — restock, sales, returns, adjustments') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" id="btnAddMovement"
                    data-i18n="add_movement">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span><?= _smt('add_movement', 'Add Movement') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPlatformAdmin): ?>
    <!-- ═══ PLATFORM ADMIN — TENANT / ENTITY CONTEXT ═══ -->
    <div class="card" id="paPanel" style="border-left:4px solid var(--color-warning,#ff9800);margin-bottom:14px">
        <div class="card-header" style="background:var(--color-warning,#ff9800);color:#fff;padding:8px 16px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-shield-alt"></i>
            <strong><?= _smt('platform_admin.panel_title', 'Platform Admin — Tenant Context') ?></strong>
        </div>
        <div class="card-body" style="padding:12px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <div class="form-group" style="margin:0;min-width:220px">
                    <label class="filter-label"><?= _smt('platform_admin.select_tenant', 'Select Tenant') ?></label>
                    <select id="paTenantSelect" class="form-control">
                        <option value=""><?= _smt('platform_admin.select_tenant_placeholder', '— Select tenant —') ?></option>
                    </select>
                </div>
                <div class="form-group" id="paEntityGroup" style="margin:0;min-width:220px;display:none">
                    <label class="filter-label"><?= _smt('platform_admin.select_entity', 'Select Entity (optional)') ?></label>
                    <select id="paEntitySelect" class="form-control">
                        <option value=""><?= _smt('platform_admin.all_entities', '— All entities —') ?></option>
                    </select>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" id="paApplyBtn" class="btn btn-warning btn-sm" disabled>
                        <i class="fas fa-user-shield"></i>
                        <?= _smt('platform_admin.apply', 'Apply') ?>
                    </button>
                    <button type="button" id="paClearBtn" class="btn btn-secondary btn-sm" style="display:none">
                        <i class="fas fa-times"></i>
                        <?= _smt('platform_admin.clear', 'Clear') ?>
                    </button>
                </div>
            </div>
            <div id="paActiveBanner" style="display:none;margin-top:10px;padding:7px 14px;background:rgba(255,152,0,.12);border-radius:6px;font-weight:600;color:#b45309">
                <i class="fas fa-exclamation-triangle"></i>&nbsp;
                <span id="paActiveBannerLabel"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div id="smTabNav" style="display:flex;flex-wrap:wrap;gap:2px;margin-bottom:14px;border-bottom:2px solid var(--border-color,#e5e7eb)">
        <button class="sm-tab-btn" data-tab="movements"
                style="padding:9px 18px;border:none;background:none;cursor:pointer;font-weight:600;border-bottom:3px solid var(--color-primary,#3b82f6);color:var(--color-primary,#3b82f6);margin-bottom:-2px">
            <i class="fas fa-exchange-alt"></i> <?= _smt('tab.movements', 'Stock Movements') ?>
        </button>
        <button class="sm-tab-btn" data-tab="entity-products"
                style="padding:9px 18px;border:none;background:none;cursor:pointer;color:var(--text-secondary,#6b7280);border-bottom:3px solid transparent;margin-bottom:-2px">
            <i class="fas fa-boxes"></i> <?= _smt('tab.entity_products', 'Entity Products') ?>
        </button>
        <button class="sm-tab-btn" data-tab="product-variants"
                style="padding:9px 18px;border:none;background:none;cursor:pointer;color:var(--text-secondary,#6b7280);border-bottom:3px solid transparent;margin-bottom:-2px">
            <i class="fas fa-layer-group"></i> <?= _smt('tab.product_variants', 'Product Variants') ?>
        </button>
        <button class="sm-tab-btn" data-tab="variant-attributes"
                style="padding:9px 18px;border:none;background:none;cursor:pointer;color:var(--text-secondary,#6b7280);border-bottom:3px solid transparent;margin-bottom:-2px">
            <i class="fas fa-sitemap"></i> <?= _smt('tab.entity_variant_stock', 'Entity Variant Stock') ?>
        </button>
    </div>

    <!-- ═══════════════════ TAB 1: STOCK MOVEMENTS ═══════════════════ -->
    <div id="tabMovements" class="sm-tab-panel">

    <!-- Product Lookup Strip -->
    <div class="sm-lookup-strip">
        <div class="filter-group">
            <label class="filter-label" for="barcodeInput" data-i18n="scan_barcode">
                <?= _smt('scan_barcode', 'Scan Barcode') ?>
            </label>
            <div class="input-group">
                <input type="text" class="form-control" id="barcodeInput"
                       data-i18n-placeholder="scan_placeholder"
                       placeholder="<?= _smt('scan_placeholder', 'Enter barcode...') ?>">
                <button type="button" class="btn btn-sm btn-secondary" id="btnScanBarcode"
                        title="<?= _smt('scan_btn', 'Scan') ?>"
                        aria-label="<?= _smt('scan_btn', 'Scan') ?>">
                    <i class="fas fa-barcode" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="filter-group">
            <label class="filter-label" for="skuInput" data-i18n="lookup.sku">
                <?= _smt('lookup.sku', 'Search by SKU') ?>
            </label>
            <div class="input-group">
                <input type="text" class="form-control" id="skuInput"
                       data-i18n-placeholder="lookup.sku_placeholder"
                       placeholder="<?= _smt('lookup.sku_placeholder', 'Enter SKU...') ?>">
                <button type="button" class="btn btn-sm btn-secondary" id="btnSearchSku"
                        title="<?= _smt('lookup.search', 'Search') ?>"
                        aria-label="<?= _smt('lookup.search', 'Search') ?>">
                    <i class="fas fa-search" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="filter-group">
            <label class="filter-label" aria-hidden="true">&nbsp;</label>
            <div class="filter-buttons">
                <button type="button" class="btn btn-sm btn-secondary" id="btnCameraScanner"
                        title="<?= _smt('lookup.open_camera', 'Open Camera') ?>"
                        aria-label="<?= _smt('lookup.open_camera', 'Open Camera') ?>">
                    <i class="fas fa-camera" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <small id="barcodeResult" class="sm-lookup-result" style="display:none"></small>
    </div>

    <!-- Camera Preview -->
    <div class="sm-camera-container" id="cameraContainer" style="display:none">
        <video id="cameraVideo" autoplay playsinline></video>
        <canvas id="cameraCanvas" style="display:none"></canvas>
        <div class="sm-camera-controls">
            <button type="button" class="btn btn-sm btn-danger" id="btnStopCamera">
                <i class="fas fa-times" aria-hidden="true"></i>
                <span data-i18n="lookup.stop_camera"><?= _smt('lookup.stop_camera', 'Stop Camera') ?></span>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-value" id="statTotal">0</div>
            <div class="stat-label" data-i18n="stats.total"><?= _smt('stats.total', 'Total Movements') ?></div>
        </div>
        <div class="stat-card stat-restocked">
            <div class="stat-value" id="statRestocked">0</div>
            <div class="stat-label" data-i18n="stats.restocked"><?= _smt('stats.restocked', 'Restocked') ?></div>
        </div>
        <div class="stat-card stat-sold">
            <div class="stat-value" id="statSold">0</div>
            <div class="stat-label" data-i18n="stats.sold"><?= _smt('stats.sold', 'Sold') ?></div>
        </div>
        <div class="stat-card stat-returned">
            <div class="stat-value" id="statReturned">0</div>
            <div class="stat-label" data-i18n="stats.returned"><?= _smt('stats.returned', 'Returned') ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-body" style="padding: clamp(8px,1.5vw,12px) clamp(12px,2vw,16px);">
            <div class="filters-grid">
                <div class="filter-group filter-group--search">
                    <label class="filter-label" for="searchInput" data-i18n="filter.search">
                        <?= _smt('filter.search', 'Search') ?>
                    </label>
                    <input type="text" class="form-control" id="searchInput"
                           data-i18n-placeholder="filter.search_placeholder"
                           placeholder="<?= _smt('filter.search_placeholder', 'Search by product name or SKU...') ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="typeFilter" data-i18n="filter.type">
                        <?= _smt('filter.type', 'Type') ?>
                    </label>
                    <select class="form-control" id="typeFilter">
                        <option value=""   data-i18n="filter.all_types"><?=   _smt('filter.all_types',   'All Types') ?></option>
                        <option value="restock"    data-i18n="types.restock"><?=    _smt('types.restock',    'Restock') ?></option>
                        <option value="sale"       data-i18n="types.sale"><?=       _smt('types.sale',       'Sale') ?></option>
                        <option value="return"     data-i18n="types.return"><?=     _smt('types.return',     'Return') ?></option>
                        <option value="adjustment" data-i18n="types.adjustment"><?= _smt('types.adjustment', 'Adjustment') ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="dateFrom" data-i18n="filter.date_from">
                        <?= _smt('filter.date_from', 'From Date') ?>
                    </label>
                    <input type="date" class="form-control" id="dateFrom">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="dateTo" data-i18n="filter.date_to">
                        <?= _smt('filter.date_to', 'To Date') ?>
                    </label>
                    <input type="date" class="form-control" id="dateTo">
                </div>
                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button class="btn btn-sm btn-primary" id="btnFilter"
                                title="<?= _smt('filter.apply', 'Filter') ?>"
                                aria-label="<?= _smt('filter.apply', 'Filter') ?>">
                            <i class="fas fa-search" aria-hidden="true"></i>
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btnClearFilter"
                                title="<?= _smt('filter.clear', 'Clear Filters') ?>"
                                aria-label="<?= _smt('filter.clear', 'Clear Filters') ?>">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading / Empty / Error States -->
    <div class="card" id="smStateCard" style="display:none">
        <div class="card-body">
            <div id="smLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="table.loading"><?= _smt('table.loading', 'Loading...') ?></p>
            </div>
            <div id="smEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-boxes" aria-hidden="true"></i></div>
                <h3 data-i18n="table.no_records"><?= _smt('table.no_records', 'No movements found') ?></h3>
            </div>
            <div id="smError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="messages.error"><?= _smt('messages.error', 'Error loading data') ?></h3>
                <p id="smErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary"><?= _smt('retry', 'Retry') ?></button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card" id="smTableCard">
        <div class="card-body sm-table-overflow">
            <table class="data-table" id="movementsTable" aria-label="Stock Movements">
                <thead>
                    <tr>
                        <th data-i18n="table.id">      <?= _smt('table.id',       'ID') ?></th>
                        <th data-i18n="table.product"> <?= _smt('table.product',  'Product') ?></th>
                        <th data-i18n="table.variant"> <?= _smt('table.variant',  'Variant') ?></th>
                        <th data-i18n="table.type">    <?= _smt('table.type',     'Type') ?></th>
                        <th data-i18n="table.quantity"><?= _smt('table.quantity', 'Quantity') ?></th>
                        <th data-i18n="table.reference"><?= _smt('table.reference','Reference') ?></th>
                        <th data-i18n="table.notes">   <?= _smt('table.notes',    'Notes') ?></th>
                        <th data-i18n="table.date">    <?= _smt('table.date',     'Date') ?></th>
                        <th data-i18n="table.actions"> <?= _smt('table.actions',  'Actions') ?></th>
                    </tr>
                </thead>
                <tbody id="movementsBody">
                    <tr><td colspan="9" class="text-center"><?= _smt('table.loading', 'Loading...') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrapper">
        <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
        <div class="pagination" id="pagination" role="navigation"></div>
    </div>

    </div><!-- /#tabMovements -->

    <!-- ═══════════════════ TAB 2: ENTITY PRODUCTS ═══════════════════ -->
    <div id="tabEntityProducts" class="sm-tab-panel" style="display:none">
        <?php if ($canCreate): ?>
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
            <button class="btn btn-primary btn-sm" id="btnAddEntityProduct">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span><?= _smt('ep.add', 'Add Entity Product') ?></span>
            </button>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body sm-table-overflow">
                <table class="data-table" id="entityProductsTable" aria-label="Entity Products">
                    <thead>
                        <tr>
                            <th><?= _smt('ep.id',              'ID') ?></th>
                            <th><?= _smt('ep.tenant_id',       'Tenant') ?></th>
                            <th><?= _smt('ep.entity_id',       'Entity') ?></th>
                            <th><?= _smt('ep.product_id',      'Product ID') ?></th>
                            <th><?= _smt('ep.stock_quantity',  'Stock Qty') ?></th>
                            <th><?= _smt('ep.low_threshold',   'Low Threshold') ?></th>
                            <th><?= _smt('ep.is_active',       'Active') ?></th>
                            <th><?= _smt('ep.is_featured',     'Featured') ?></th>
                            <th><?= _smt('ep.created_at',      'Created') ?></th>
                            <th><?= _smt('table.actions',      'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="epBody">
                        <tr><td colspan="10" class="text-center"><?= _smt('table.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="epPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="epPagination" role="navigation"></div>
        </div>
    </div><!-- /#tabEntityProducts -->

    <!-- ═══════════════════ TAB 3: PRODUCT VARIANTS ═══════════════════ -->
    <div id="tabProductVariants" class="sm-tab-panel" style="display:none">
        <div class="card">
            <div class="card-body sm-table-overflow">
                <table class="data-table" id="productVariantsTable" aria-label="Product Variants">
                    <thead>
                        <tr>
                            <th><?= _smt('pv.id',             'ID') ?></th>
                            <th><?= _smt('pv.product_id',     'Product ID') ?></th>
                            <th><?= _smt('pv.sku',            'SKU') ?></th>
                            <th><?= _smt('pv.barcode',        'Barcode') ?></th>
                            <th><?= _smt('pv.stock_quantity', 'Stock Qty') ?></th>
                            <th><?= _smt('pv.low_threshold',  'Low Threshold') ?></th>
                            <th><?= _smt('pv.is_active',      'Active') ?></th>
                            <th><?= _smt('pv.is_default',     'Default') ?></th>
                            <th><?= _smt('pv.created_at',     'Created') ?></th>
                            <th><?= _smt('table.actions',     'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="pvBody">
                        <tr><td colspan="10" class="text-center"><?= _smt('table.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="pvPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pvPagination" role="navigation"></div>
        </div>
    </div><!-- /#tabProductVariants -->

    <!-- ═══════════════════ TAB 4: ENTITY VARIANT STOCK ═══════════════════ -->
    <div id="tabVariantAttributes" class="sm-tab-panel" style="display:none">
        <div class="card">
            <div class="card-body sm-table-overflow">
                <table class="data-table" id="variantAttributesTable" aria-label="Entity Variant Stock">
                    <thead>
                        <tr>
                            <th><?= _smt('epv.id',             'ID') ?></th>
                            <th><?= _smt('epv.tenant_id',      'Tenant') ?></th>
                            <th><?= _smt('epv.entity_id',      'Entity') ?></th>
                            <th><?= _smt('epv.product_id',     'Product') ?></th>
                            <th><?= _smt('epv.variant_id',     'Variant ID') ?></th>
                            <th><?= _smt('epv.variant_sku',    'Variant SKU') ?></th>
                            <th><?= _smt('epv.stock_quantity', 'Stock Qty') ?></th>
                            <th><?= _smt('epv.stock_status',   'Status') ?></th>
                            <th><?= _smt('epv.is_active',      'Active') ?></th>
                            <th><?= _smt('epv.created_at',     'Created') ?></th>
                            <th><?= _smt('table.actions',      'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="vaBody">
                        <tr><td colspan="11" class="text-center"><?= _smt('table.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="vaPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="vaPagination" role="navigation"></div>
        </div>
    </div><!-- /#tabVariantAttributes -->

    <!-- Add / Edit Modal — prefix: sm -->
    <div class="sm-modal-backdrop" id="movementModal"
         style="display:none" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="sm-modal-panel">
            <div class="sm-modal-header">
                <h3 id="modalTitle" data-i18n="add_movement"><?= _smt('add_movement', 'Add Movement') ?></h3>
                <button class="sm-modal-close btn-close-modal icon-btn"
                        id="btnCloseModal"
                        aria-label="<?= _smt('accessibility.close', 'Close') ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sm-modal-body">
                <form id="movementForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" id="movementId" name="id" value="">
                    <?php if ($isPlatformAdmin): ?>
                    <input type="hidden" id="movementTenantId"  name="tenant_id"  value="">
                    <input type="hidden" id="movementEntityId"  name="entity_id"  value="">
                    <?php else: ?>
                    <input type="hidden" name="entity_id" value="<?= (int)$entityId ?>">
                    <?php endif; ?>

                    <?php if ($isPlatformAdmin): ?>
                    <div class="form-group" id="formEntityGroup">
                        <label class="filter-label required" for="formEntitySelect">
                            <?= _smt('form.entity', 'Entity') ?> *
                        </label>
                        <select class="form-control" id="formEntitySelect">
                            <option value=""><?= _smt('form.select_entity', '— Select entity —') ?></option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="filter-label required" for="formEntityProductId">
                            <?= _smt('form.entity_product', 'Product') ?> *
                        </label>
                        <select class="form-control" id="formEntityProductId"
                                name="entity_product_id" required disabled>
                            <option value=""><?= _smt('form.select_product', '— Select product —') ?></option>
                        </select>
                        <small id="formProductStockInfo" class="sm-lookup-name"></small>
                    </div>

                    <div class="form-group" id="formVariantGroup" style="display:none">
                        <label class="filter-label" for="formEntityProductVariantId">
                            <?= _smt('form.entity_product_variant', 'Variant (optional)') ?>
                        </label>
                        <select class="form-control" id="formEntityProductVariantId"
                                name="entity_product_variant_id" disabled>
                            <option value=""><?= _smt('form.no_variant', '— No variant —') ?></option>
                        </select>
                        <small id="formVariantStockInfo" class="sm-lookup-name"></small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label required" for="movementType">
                                <?= _smt('form.type', 'Movement Type') ?> *
                            </label>
                            <select class="form-control" id="movementType" name="type" required>
                                <option value="restock"><?=    _smt('types.restock',    'Restock') ?></option>
                                <option value="sale"><?=       _smt('types.sale',       'Sale') ?></option>
                                <option value="return"><?=     _smt('types.return',     'Return') ?></option>
                                <option value="adjustment"><?= _smt('types.adjustment', 'Adjustment') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label required" for="changeQuantity">
                                <?= _smt('form.quantity', 'Quantity') ?> *
                            </label>
                            <input type="number" class="form-control" id="changeQuantity"
                                   name="change_quantity" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="filter-label" for="referenceId">
                            <?= _smt('form.reference_id', 'Reference ID') ?>
                        </label>
                        <input type="number" class="form-control" id="referenceId" name="reference_id" min="1">
                    </div>

                    <div class="form-group">
                        <label class="filter-label" for="movementNotes">
                            <?= _smt('form.notes', 'Notes') ?>
                        </label>
                        <textarea class="form-control" id="movementNotes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelModal">
                    <?= _smt('form.cancel', 'Cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnSaveMovement">
                    <span id="btnSaveMovementText"><?= _smt('form.save', 'Save') ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ Modal: Entity Product ═══ -->
    <div class="sm-modal-backdrop" id="entityProductModal"
         style="display:none" role="dialog" aria-modal="true" aria-labelledby="epModalTitle">
        <div class="sm-modal-panel">
            <div class="sm-modal-header">
                <h3 id="epModalTitle"><?= _smt('ep.add', 'Add Entity Product') ?></h3>
                <button class="sm-modal-close icon-btn" id="btnCloseEpModal" aria-label="<?= _smt('accessibility.close', 'Close') ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sm-modal-body">
                <form id="entityProductForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" id="epId" name="id" value="">
                    <?php if ($isPlatformAdmin): ?>
                    <input type="hidden" id="epTenantId" name="tenant_id" value="">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label required" for="epEntityId"><?= _smt('ep.entity_id', 'Entity ID') ?> *</label>
                            <input type="number" class="form-control" id="epEntityId" name="entity_id" required min="1">
                        </div>
                        <div class="form-group">
                            <label class="filter-label required" for="epProductId"><?= _smt('ep.product_id', 'Product ID') ?> *</label>
                            <input type="number" class="form-control" id="epProductId" name="product_id" required min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label" for="epStockQty"><?= _smt('ep.stock_quantity', 'Stock Qty') ?></label>
                            <input type="number" class="form-control" id="epStockQty" name="stock_quantity" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="epLowThreshold"><?= _smt('ep.low_threshold', 'Low Threshold') ?></label>
                            <input type="number" class="form-control" id="epLowThreshold" name="low_stock_threshold" value="0" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label" for="epIsActive"><?= _smt('ep.is_active', 'Active') ?></label>
                            <select class="form-control" id="epIsActive" name="is_active">
                                <option value="1"><?= _smt('yes', 'Yes') ?></option>
                                <option value="0"><?= _smt('no', 'No') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="epIsFeatured"><?= _smt('ep.is_featured', 'Featured') ?></label>
                            <select class="form-control" id="epIsFeatured" name="is_featured">
                                <option value="0"><?= _smt('no', 'No') ?></option>
                                <option value="1"><?= _smt('yes', 'Yes') ?></option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelEpModal"><?= _smt('form.cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="btnSaveEntityProduct">
                    <span id="btnSaveEpText"><?= _smt('form.save', 'Save') ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Product Variant modal and Variant Attribute modal removed:
         Tabs 3 & 4 now only allow stock adjustments via the movement modal above. -->

</div><!-- /.page-container -->

<script>
window.STOCK_MOVEMENTS_CONFIG = {
    apiBase:     '/api',
    csrfToken:   <?= json_encode($csrf) ?>,
    lang:        <?= json_encode($lang) ?>,
    dir:         <?= json_encode($dir)  ?>,
    strings:     <?= json_encode($_smtStringsFlat, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:   <?= json_encode($canCreate) ?>,
    canEdit:     <?= json_encode($canEdit)   ?>,
    canDelete:   <?= json_encode($canDelete) ?>,
    isSuperAdmin:<?= json_encode($isSuperAdmin) ?>,
    isPlatformAdmin: <?= json_encode($isPlatformAdmin) ?>,
    tenantId:    <?= json_encode($tenantId) ?>,
    entityId:    <?= json_encode($entityId) ?>,
    tenantsApi:          '/api/tenants',
    entitiesApi:         '/api/entities',
    entityProductsApi:   '/api/entity_products',
    entityVariantsApi:   '/api/entity_product_variants'
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/stock_movements.js?v=<?= assetVer('/admin/assets/js/pages/stock_movements.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>