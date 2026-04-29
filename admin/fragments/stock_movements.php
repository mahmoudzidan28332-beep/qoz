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

                    <div class="form-group">
                        <label class="filter-label required" for="productIdInput">
                            <?= _smt('form.product_id', 'Product ID') ?> *
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="productIdInput"
                                   name="product_id" required min="1">
                            <button type="button" class="btn btn-sm btn-secondary"
                                    id="btnLookupProduct"
                                    aria-label="<?= _smt('lookup.search', 'Search') ?>">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small id="productName" class="sm-lookup-name"></small>
                    </div>

                    <div class="form-group">
                        <label class="filter-label" for="variantIdInput">
                            <?= _smt('form.variant_id', 'Variant ID (optional)') ?>
                        </label>
                        <input type="number" class="form-control" id="variantIdInput" name="variant_id" min="1">
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
    tenantId:    <?= json_encode($tenantId) ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/stock_movements.js?v=<?= assetVer('/admin/assets/js/pages/stock_movements.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>