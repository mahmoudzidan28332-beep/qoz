<?php
declare(strict_types=1);

/**
 * /admin/fragments/returns.php
 * Admin fragment – Return Requests Management
 */

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
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();
$entityId = $GLOBALS['ADMIN_UI']['entity_id'] ?? ($_SESSION['entity_id'] ?? 0);

$canCreate = can('returns.manage') || can('returns.create') || is_super_admin();
$canEdit   = can('returns.manage') || can('returns.edit')   || is_super_admin();
$canDelete = can('returns.manage') || can('returns.delete') || is_super_admin();
$canView   = $canCreate || $canEdit || $canDelete || can('returns.view') || is_super_admin();

if (!$canView) {
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
// assetVer() — cache-busting بناءً على filemtime
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
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderReturnsFragmentThemeVars')) {
    function renderReturnsFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// i18n strings للـ PAGE_CONFIG
// ════════════════════════════════════════════════════════════
$_strings = [
    'returns.title'           => 'Return Requests',
    'returns.subtitle'        => 'Manage customer return requests',
    'returns.add_new'         => 'New Return',
    'returns.loading'         => 'Loading returns...',
    'form.add_title'          => 'New Return Request',
    'form.edit_title'         => 'Edit Return',
    'form.tabs.details'       => 'Details',
    'form.tabs.items'         => 'Items',
    'form.tabs.history'       => 'Status History',
    'form.buttons.save'       => 'Save Return',
    'form.buttons.cancel'     => 'Cancel',
    'form.buttons.delete'     => 'Delete Return',
    'filters.search'          => 'Search',
    'filters.search_placeholder' => 'Search by return number...',
    'filters.status'          => 'Status',
    'filters.all_statuses'    => 'All Statuses',
    'filters.apply'           => 'Apply',
    'filters.reset'           => 'Reset',
    'table.headers.id'        => 'ID',
    'table.headers.return_number' => 'Return #',
    'table.headers.order'     => 'Order',
    'table.headers.customer'  => 'Customer',
    'table.headers.status'    => 'Status',
    'table.headers.reason'    => 'Reason',
    'table.headers.requested_at' => 'Requested',
    'table.headers.actions'   => 'Actions',
    'table.empty.title'       => 'No Return Requests Found',
    'table.empty.message'     => 'There are no return requests matching your criteria.',
    'table.empty.add_first'   => 'Create First Return',
    'status.pending'          => 'Pending',
    'status.approved'         => 'Approved',
    'status.rejected'         => 'Rejected',
    'status.processing'       => 'Processing',
    'status.completed'        => 'Completed',
    'status.cancelled'        => 'Cancelled',
    'items.empty'             => 'No items in this return',
    'history.empty'           => 'No status history yet',
    'messages.updated'        => 'Updated successfully',
    'messages.created'        => 'Created successfully',
    'messages.deleted'        => 'Deleted successfully',
    'messages.confirm_delete' => 'Are you sure you want to delete this return?',
    'loading'                 => 'Loading...',
    'retry'                   => 'Retry',
    'error.title'             => 'Something went wrong',
];
?>
<style id="db-theme-vars-returns">
<?php renderReturnsFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/returns.css?v=<?= assetVer('/admin/assets/css/pages/returns.css') ?>">

<meta data-page="returns"
      data-assets-css="/admin/assets/css/pages/returns.css"
      data-i18n-files="/languages/Returns/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="returnsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="returns.title">Return Requests</h1>
            <p class="page-subtitle" data-i18n="returns.subtitle">Manage customer return requests</p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="ret-btnAdd" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="returns.add_new">New Return</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isPlatformAdmin): ?>
    <!-- ═══ PLATFORM ADMIN — TENANT CONTEXT ═══ -->
    <div class="card" id="ret-paPanel" style="border-left:4px solid var(--color-warning,#ff9800);margin-bottom:14px">
        <div class="card-header" style="background:var(--color-warning,#ff9800);color:#fff;padding:8px 16px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-shield-alt"></i>
            <strong>Platform Admin — Tenant Context</strong>
        </div>
        <div class="card-body" style="padding:12px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <div class="form-group" style="margin:0;min-width:260px">
                    <label class="filter-label">Search Tenant (ID or name)</label>
                    <input type="text" id="ret-paTenantSearch" class="form-control"
                           placeholder="Type tenant ID or name...">
                </div>
                <div class="form-group" style="margin:0;min-width:220px">
                    <label class="filter-label">Select Tenant</label>
                    <select id="ret-paTenantSelect" class="form-control">
                        <option value="">— Select tenant —</option>
                    </select>
                </div>
                <div class="form-group" id="ret-paEntityGroup" style="margin:0;min-width:220px;display:none">
                    <label class="filter-label">Select Entity (optional)</label>
                    <select id="ret-paEntitySelect" class="form-control">
                        <option value="">— All entities —</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" id="ret-paApplyBtn" class="btn btn-warning btn-sm" disabled>
                        <i class="fas fa-user-shield"></i> Apply
                    </button>
                    <button type="button" id="ret-paClearBtn" class="btn btn-secondary btn-sm" style="display:none">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
            <div id="ret-paActiveBanner" style="display:none;margin-top:10px;padding:7px 14px;background:rgba(255,152,0,.12);border-radius:6px;font-weight:600;color:#b45309">
                <i class="fas fa-exclamation-triangle"></i>&nbsp;
                <span id="ret-paActiveBannerLabel"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Container -->
    <div id="ret-formContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="ret-formTitle" data-i18n="form.add_title">New Return Request</h3>
            <button type="button" class="btn btn-sm btn-secondary" id="ret-btnCloseForm" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="ret-form" novalidate>
                <input type="hidden" id="ret-formId"   name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="ret-tenantId" name="tenant_id"  value="<?= (int)$tenantId ?>">
                <input type="hidden" id="ret-entityId" name="entity_id"  value="<?= (int)$entityId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span data-i18n="form.tabs.details">Details</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="items">
                        <i class="fas fa-box" aria-hidden="true"></i>
                        <span data-i18n="form.tabs.items">Items</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history" aria-hidden="true"></i>
                        <span data-i18n="form.tabs.history">Status History</span>
                    </button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="ret-tab-details" style="display:block">
                    <!-- Order & User (required for new returns) -->
                    <div class="form-row" id="ret-orderUserRow">
                        <div class="form-group">
                            <label class="filter-label" for="ret-orderId">Order ID <span class="ret-required-star" style="color:var(--color-danger,#e53935)">*</span></label>
                            <input type="number" id="ret-orderId" name="order_id" class="form-control" min="1"
                                   placeholder="Enter order ID">
                            <small id="ret-orderInfo" style="color:var(--text-secondary);font-size:0.8em"></small>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ret-userId">User ID <span class="ret-required-star" style="color:var(--color-danger,#e53935)">*</span></label>
                            <input type="number" id="ret-userId" name="user_id" class="form-control" min="1"
                                   placeholder="Enter user ID">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label" for="ret-returnNumber" data-i18n="form.fields.return_number.label">Return Number</label>
                            <input type="text" id="ret-returnNumber" name="return_number" class="form-control" readonly
                                   placeholder="Auto-generated">
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ret-status" data-i18n="form.fields.status.label">Status</label>
                            <select id="ret-status" name="status" class="form-control">
                                <option value="pending"    data-i18n="status.pending">Pending</option>
                                <option value="approved"   data-i18n="status.approved">Approved</option>
                                <option value="rejected"   data-i18n="status.rejected">Rejected</option>
                                <option value="processing" data-i18n="status.processing">Processing</option>
                                <option value="completed"  data-i18n="status.completed">Completed</option>
                                <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="filter-label" for="ret-reason" data-i18n="form.fields.reason.label">Reason</label>
                        <textarea id="ret-reason" name="reason" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.reason.placeholder"
                                  placeholder="Reason for return request"></textarea>
                    </div>

                    <?php if ($canEdit): ?>
                    <div class="form-group">
                        <label class="filter-label" for="ret-adminNotes" data-i18n="form.fields.admin_notes.label">Admin Notes</label>
                        <textarea id="ret-adminNotes" name="admin_notes" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.admin_notes.placeholder"
                                  placeholder="Internal notes (not visible to customer)"></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Items -->
                <div class="tab-content" id="ret-tab-items" style="display:none">
                    <div id="ret-itemsList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="items.empty">
                            No items in this return
                        </p>
                    </div>
                </div>

                <!-- Tab: History -->
                <div class="tab-content" id="ret-tab-history" style="display:none">
                    <div id="ret-historyList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="history.empty">
                            No status history yet
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-primary" id="ret-btnSave">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save">Save Return</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" id="ret-btnCancelForm" data-i18n="form.buttons.cancel">
                        Cancel
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="ret-btnDelete" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.delete">Delete Return</span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">

                <div class="filter-group">
                    <label class="filter-label" for="ret-searchInput" data-i18n="filters.search">Search</label>
                    <input type="text" id="ret-searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="Search by return number...">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="ret-statusFilter" data-i18n="filters.status">Status</label>
                    <select id="ret-statusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_statuses">All Statuses</option>
                        <option value="pending"    data-i18n="status.pending">Pending</option>
                        <option value="approved"   data-i18n="status.approved">Approved</option>
                        <option value="rejected"   data-i18n="status.rejected">Rejected</option>
                        <option value="processing" data-i18n="status.processing">Processing</option>
                        <option value="completed"  data-i18n="status.completed">Completed</option>
                        <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="ret-btnApplyFilters" class="btn btn-primary"   data-i18n="filters.apply">Apply</button>
                        <button id="ret-btnResetFilters" class="btn btn-secondary" data-i18n="filters.reset">Reset</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">

            <!-- Loading -->
            <div id="ret-tableLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading">Loading...</p>
            </div>

            <!-- Empty -->
            <div id="ret-emptyState" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title">No Return Requests Found</h3>
                <p data-i18n="table.empty.message">There are no return requests matching your criteria.</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="ret-btnAddFirst"
                        data-i18n="table.empty.add_first">
                    Create First Return
                </button>
                <?php endif; ?>
            </div>

            <!-- Error -->
            <div id="ret-errorState" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title">Something went wrong</h3>
                <p id="ret-errorMessage"></p>
                <button id="ret-btnRetry" class="btn btn-primary" data-i18n="retry">Retry</button>
            </div>

            <!-- Table -->
            <div id="ret-tableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="ret-table" aria-label="Return Requests">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">ID</th>
                            <th data-i18n="table.headers.return_number">Return #</th>
                            <th data-i18n="table.headers.order">Order</th>
                            <th data-i18n="table.headers.customer">Customer</th>
                            <th data-i18n="table.headers.status">Status</th>
                            <th data-i18n="table.headers.reason">Reason</th>
                            <th data-i18n="table.headers.requested_at">Requested</th>
                            <th data-i18n="table.headers.actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ret-tableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info" id="ret-paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="ret-pagination" role="navigation"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script type="text/javascript">
window.RETURNS_CONFIG = {
    apiBase:         <?= json_encode($apiBase,   JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:       <?= json_encode($csrf) ?>,
    lang:            <?= json_encode($lang) ?>,
    dir:             <?= json_encode($dir) ?>,
    strings:         <?= json_encode($_strings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:       <?= json_encode($canCreate) ?>,
    canEdit:         <?= json_encode($canEdit) ?>,
    canDelete:       <?= json_encode($canDelete) ?>,
    apiUrl:          <?= json_encode($apiBase . '/returns',               JSON_UNESCAPED_SLASHES) ?>,
    itemsApi:        <?= json_encode($apiBase . '/return_items',          JSON_UNESCAPED_SLASHES) ?>,
    historyApi:      <?= json_encode($apiBase . '/return_status_history', JSON_UNESCAPED_SLASHES) ?>,
    ordersApi:       <?= json_encode($apiBase . '/orders',                JSON_UNESCAPED_SLASHES) ?>,
    usersApi:        <?= json_encode($apiBase . '/users',                 JSON_UNESCAPED_SLASHES) ?>,
    tenantsApi:      <?= json_encode($apiBase . '/tenants',               JSON_UNESCAPED_SLASHES) ?>,
    entitiesApi:     <?= json_encode($apiBase . '/entities',              JSON_UNESCAPED_SLASHES) ?>,
    itemsPerPage:    20,
    tenantId:        <?= json_encode((int)$tenantId) ?>,
    entityId:        <?= json_encode((int)$entityId) ?>,
    currentUserId:   <?= json_encode((int)($user['id'] ?? 0)) ?>,
    isPlatformAdmin: <?= json_encode($isPlatformAdmin) ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/returns.js?v=<?= assetVer('/admin/assets/js/pages/returns.js') ?>"></script>
<script>
(function () {
    var initialized = false;
    var poll;

    function cleanup() {
        clearInterval(poll);
        window.removeEventListener('admin:i18n:applied', tryInit);
    }

    function tryInit() {
        if (initialized) return;
        if (!window.TRANSLATIONS) return;
        if (!window.Returns || typeof window.Returns.init !== 'function') return;
        initialized = true;
        cleanup();
        window.Returns.init();
    }

    window.addEventListener('admin:i18n:applied', tryInit);
    tryInit();

    var pollCount = 0;
    poll = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 60) {
            cleanup();
            if (!initialized) console.warn('[Returns] init timed out');
        }
    }, 100);
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>