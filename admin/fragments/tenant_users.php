<?php
declare(strict_types=1);

/**
 * /admin/fragments/tenant_users.php
 * Production Version - Embedded-friendly with Strict Permission Control
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
$tenantId = admin_tenant_id();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageTenantUsers = can('tenant_users.manage') || can('tenant_users.create');

$canViewAll    = can_view_all('tenant_users')    || $isPlatformAdmin;
$canViewOwn    = can_view_own('tenant_users');
$canViewTenant = can_view_tenant('tenant_users') || $isPlatformAdmin;
$canCreate     = can_create('tenant_users')      || $isPlatformAdmin;
$canEditAll    = can_edit_all('tenant_users')    || $isPlatformAdmin;
$canEditOwn    = can_edit_own('tenant_users');
$canDeleteAll  = can_delete_all('tenant_users')  || ($isPlatformAdmin && get_platform_role() === 'super_admin');
$canDeleteOwn  = can_delete_own('tenant_users');

$canView   = $canViewAll || $canViewOwn || $canViewTenant || $canManageTenantUsers;
$canEdit   = $canEditAll || $canEditOwn || $canManageTenantUsers;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageTenantUsers;

if (!$canView && !is_super_admin() && !$isPlatformAdmin) {
    if ($isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied: You do not have permission to view tenant users');
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
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace('{' . $ph . '}', (string) $val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// i18n strings للـ PAGE_CONFIG
// ════════════════════════════════════════════════════════════
$_strings = [
    'tenant_users.title'    => __t('tenant_users.title',    'Tenant Users Management'),
    'tenant_users.subtitle' => __t('tenant_users.subtitle', 'Manage users assigned to tenants'),
    'tenant_users.add_new'  => __t('tenant_users.add_new',  'Add New User'),
    'tenant_users.loading'  => __t('tenant_users.loading',  'Loading...'),
    'tenant_users.retry'    => __t('tenant_users.retry',    'Retry'),
    'loading'               => __t('loading',               'Loading...'),
    'retry'                 => __t('retry',                 'Retry'),
    'error.title'           => __t('messages.error.load_failed', 'Error Loading Data'),
    'form.add_title'        => __t('form.add_title',        'Add Tenant User'),
    'form.edit_title'       => __t('form.edit_title',       'Edit Tenant User'),
    'form.buttons.save'     => __t('form.buttons.save',     'Save'),
    'form.buttons.cancel'   => __t('form.buttons.cancel',   'Cancel'),
    'table.actions.delete'  => __t('table.actions.delete',  'Delete'),
    'table.actions.export'  => __t('table.actions.export',  'Export Excel'),
    'filters.apply'         => __t('filters.apply',         'Apply'),
    'filters.reset'         => __t('filters.reset',         'Reset'),
    'messages.success.created' => __t('messages.success.created', 'User created successfully'),
    'messages.success.updated' => __t('messages.success.updated', 'User updated successfully'),
    'messages.success.deleted' => __t('messages.success.deleted', 'User deleted successfully'),
    'messages.error.load_failed'  => __t('messages.error.load_failed',  'Failed to load data'),
    'messages.error.save_failed'  => __t('messages.error.save_failed',  'Failed to save data'),
    'messages.error.delete_failed'=> __t('messages.error.delete_failed','Failed to delete data'),
    'table.actions.confirm_delete'=> __t('table.actions.confirm_delete','Are you sure you want to delete this user?'),
];

$apiBase = '/api';
?>

<!-- Page Styles -->
<link rel="stylesheet" href="/admin/assets/css/pages/tenant_users.css?v=<?= assetVer('/admin/assets/css/pages/tenant_users.css') ?>">

<!-- Page Meta -->
<meta data-page="tenant_users"
      data-i18n-files="/languages/TenantUsers/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="tenantUsersPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="tenant_users.title">
                <?= __t('tenant_users.title', 'Tenant Users Management') ?>
            </h1>
            <p class="page-subtitle" data-i18n="tenant_users.subtitle">
                <?= __t('tenant_users.subtitle', 'Manage users assigned to tenants') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <button id="btnExportExcel" class="btn btn-secondary"
                    data-i18n="table.actions.export">
                <i class="fas fa-file-excel" aria-hidden="true"></i>
                <span><?= __t('table.actions.export', 'Export Excel') ?></span>
            </button>
            <?php if ($canCreate): ?>
            <button id="btnAddTenantUser" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="tenant_users.add_new"><?= __t('tenant_users.add_new', 'Add New User') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="tenantUserFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title">
                <?= __t('form.add_title', 'Add Tenant User') ?>
            </h3>
            <button type="button" class="btn btn-sm btn-secondary" id="btnCloseForm"
                    aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantUserForm" novalidate>
                <input type="hidden" id="formId"         name="id">
                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <!-- Tenant ID -->
                    <div class="form-group">
                        <label class="filter-label required" for="formTenantId" data-i18n="form.fields.tenant_id.label">
                            <?= __t('form.fields.tenant_id.label', 'Tenant ID') ?>
                        </label>
                        <input type="number" id="formTenantId" name="tenant_id"
                               class="form-control" required min="1"
                               value="<?= $isPlatformAdmin ? '' : (int)$tenantId ?>"
                               <?= $isPlatformAdmin ? '' : 'readonly' ?>
                               data-i18n-placeholder="form.fields.tenant_id.placeholder"
                               placeholder="<?= __t('form.fields.tenant_id.placeholder', 'Enter tenant ID') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.tenant_id.required">
                            <?= __t('form.fields.tenant_id.required', 'Tenant ID is required') ?>
                        </div>
                    </div>

                    <!-- User ID -->
                    <div class="form-group">
                        <label class="filter-label required" for="formUserId" data-i18n="form.fields.user_id.label">
                            <?= __t('form.fields.user_id.label', 'User ID') ?>
                        </label>
                        <input type="number" id="formUserId" name="user_id"
                               class="form-control" required min="1"
                               data-i18n-placeholder="form.fields.user_id.placeholder"
                               placeholder="<?= __t('form.fields.user_id.placeholder', 'Enter user ID') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.user_id.required">
                            <?= __t('form.fields.user_id.required', 'User ID is required') ?>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label class="filter-label required" for="formRoleId" data-i18n="form.fields.role_id.label">
                            <?= __t('form.fields.role_id.label', 'Role') ?>
                        </label>
                        <select id="formRoleId" name="role_id" class="form-control" required disabled>
                            <option value="" data-i18n="form.fields.role_id.enter_tenant_first">
                                <?= __t('form.fields.role_id.enter_tenant_first', 'Enter tenant ID first') ?>
                            </option>
                        </select>
                        <div class="invalid-feedback" data-i18n="form.fields.role_id.required">
                            <?= __t('form.fields.role_id.required', 'Role is required') ?>
                        </div>
                    </div>

                    <!-- Entity -->
                    <div class="form-group">
                        <label class="filter-label" for="formEntityId" data-i18n="form.fields.entity_id.label">
                            <?= __t('form.fields.entity_id.label', 'Entity (Optional)') ?>
                        </label>
                        <select id="formEntityId" name="entity_id" class="form-control" disabled>
                            <option value="" data-i18n="form.fields.entity_id.enter_tenant_first">
                                <?= __t('form.fields.entity_id.enter_tenant_first', 'Enter tenant ID first') ?>
                            </option>
                        </select>
                        <small class="form-text text-muted" data-i18n="form.fields.entity_id.help">
                            <?= __t('form.fields.entity_id.help', 'Optionally assign user to a specific entity') ?>
                        </small>
                    </div>
                </div>

                <!-- Entity Info Box -->
                <div id="entityInfo" class="user-info-box">
                    <div class="info-content">
                        <i class="fas fa-store entity-icon" aria-hidden="true"></i>
                        <div>
                            <strong data-i18n="form.entity_info.name"><?= __t('form.entity_info.name', 'Store:') ?></strong>
                            <span id="entityName"></span>
                            <small id="entitySlug"></small>
                            <span id="entityStatus" class="badge"></span>
                        </div>
                    </div>
                </div>

                <!-- Tenant Info Box -->
                <div id="tenantInfo" class="user-info-box">
                    <div class="info-content">
                        <i class="fas fa-building tenant-icon" aria-hidden="true"></i>
                        <div>
                            <strong data-i18n="form.tenant_info.name"><?= __t('form.tenant_info.name', 'Name:') ?></strong>
                            <span id="tenantName"></span>
                            <small id="tenantDomain"></small>
                            <span id="tenantStatus" class="badge"></span>
                        </div>
                    </div>
                </div>

                <!-- User Info Box -->
                <div id="userInfo" class="user-info-box">
                    <div class="info-content">
                        <i class="fas fa-user user-icon" aria-hidden="true"></i>
                        <div>
                            <strong data-i18n="form.user_info.name"><?= __t('form.user_info.name', 'Name:') ?></strong>
                            <span id="userName"></span>
                            <small id="userEmail"></small>
                            <span id="userStatus" class="badge"></span>
                        </div>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem">
                    <div class="form-group">
                        <label class="filter-label" for="formIsActive" data-i18n="form.fields.status.label">
                            <?= __t('form.fields.status.label', 'Status') ?>
                        </label>
                        <select id="formIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active">
                                <?= __t('form.fields.status.active', 'Active') ?>
                            </option>
                            <option value="0" data-i18n="form.fields.status.inactive">
                                <?= __t('form.fields.status.inactive', 'Inactive') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTenantUser" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete', 'Delete') ?></span>
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
                    <label class="filter-label" for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search...') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= __t('filters.tenant_id', 'Tenant ID') ?>
                    </label>
                    <input type="number" id="tenantFilter" class="form-control"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="userFilter" data-i18n="filters.user_id">
                        <?= __t('filters.user_id', 'User ID') ?>
                    </label>
                    <input type="number" id="userFilter" class="form-control"
                           data-i18n-placeholder="filters.user_placeholder"
                           placeholder="<?= __t('filters.user_placeholder', 'Filter by user') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="entityFilter" data-i18n="filters.entity_id">
                        <?= __t('filters.entity_id', 'Entity') ?>
                    </label>
                    <input type="number" id="entityFilter" class="form-control"
                           data-i18n-placeholder="filters.entity_placeholder"
                           placeholder="<?= __t('filters.entity_placeholder', 'Filter by entity') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value=""  data-i18n="filters.status_options.all">
                            <?= __t('filters.status_options.all', 'All Status') ?>
                        </option>
                        <option value="1" data-i18n="filters.status_options.active">
                            <?= __t('filters.status_options.active', 'Active') ?>
                        </option>
                        <option value="0" data-i18n="filters.status_options.inactive">
                            <?= __t('filters.status_options.inactive', 'Inactive') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyFilters" class="btn btn-primary"   data-i18n="filters.apply">
                            <?= __t('filters.apply', 'Apply') ?>
                        </button>
                        <button id="btnResetFilters" class="btn btn-secondary" data-i18n="filters.reset">
                            <?= __t('filters.reset', 'Reset') ?>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card table-card">
        <div class="card-body">

            <!-- Loading -->
            <div id="tableLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading"><?= __t('loading', 'Loading...') ?></p>
            </div>

            <!-- Empty -->
            <div id="emptyState" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Tenant Users Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'Start by adding users to tenants') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="btnAddFirst"
                        data-i18n="table.empty.add_first">
                    <?= __t('table.empty.add_first', 'Add First User') ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error -->
            <div id="errorState" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title"><?= __t('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="retry">
                    <?= __t('tenant_users.retry', 'Retry') ?>
                </button>
            </div>

            <!-- Table -->
            <div id="tableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="tenantUsersTable" aria-label="Tenant Users">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">        <?= __t('table.headers.id',        'ID') ?></th>
                            <th data-i18n="table.headers.username">   <?= __t('table.headers.username',   'Username') ?></th>
                            <th data-i18n="table.headers.email">      <?= __t('table.headers.email',      'Email') ?></th>
                            <th data-i18n="table.headers.tenant">     <?= __t('table.headers.tenant',     'Tenant') ?></th>
                            <th data-i18n="table.headers.entity">     <?= __t('table.headers.entity',     'Entity') ?></th>
                            <th data-i18n="table.headers.role">       <?= __t('table.headers.role',       'Role') ?></th>
                            <th data-i18n="table.headers.joined_at">  <?= __t('table.headers.joined_at',  'Joined At') ?></th>
                            <th data-i18n="table.headers.status">     <?= __t('table.headers.status',     'Status') ?></th>
                            <th data-i18n="table.headers.actions">    <?= __t('table.headers.actions',    'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info" aria-live="polite">
                <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                <span id="paginationInfo">0-0 of 0</span>
            </div>
            <div class="pagination" id="pagination" role="navigation"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<!-- Unified PAGE_CONFIG -->
<script type="text/javascript">
window.TENANT_USERS_CONFIG = {
    apiBase:      <?= json_encode($apiBase,    JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:    <?= json_encode($csrf) ?>,
    lang:         <?= json_encode($lang) ?>,
    dir:          <?= json_encode($dir) ?>,
    strings:      <?= json_encode($_strings,   JSON_UNESCAPED_UNICODE) ?>,
    canCreate:    <?= json_encode($canCreate) ?>,
    canEdit:      <?= json_encode($canEdit) ?>,
    canDelete:    <?= json_encode($canDelete) ?>,
    canView:      <?= json_encode($canView) ?>,
    canViewAll:   <?= json_encode($canViewAll) ?>,
    canViewOwn:   <?= json_encode($canViewOwn) ?>,
    canViewTenant:<?= json_encode($canViewTenant) ?>,
    canEditAll:   <?= json_encode($canEditAll) ?>,
    canEditOwn:   <?= json_encode($canEditOwn) ?>,
    canDeleteAll: <?= json_encode($canDeleteAll) ?>,
    canDeleteOwn: <?= json_encode($canDeleteOwn) ?>,
    isSuperAdmin: <?= json_encode($isPlatformAdmin) ?>,
    tenantId:     <?= json_encode($tenantId) ?>
};

/* Legacy globals kept for backward compatibility */
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE     = <?= json_encode($apiBase)    ?>;
window.APP_CONFIG.TENANT_ID    = <?= json_encode($tenantId)   ?>;
window.APP_CONFIG.CSRF_TOKEN   = <?= json_encode($csrf)       ?>;
window.APP_CONFIG.IS_SUPER_ADMIN = <?= is_super_admin() ? 'true' : 'false' ?>;
window.USER_LANGUAGE  = <?= json_encode($lang) ?>;
window.USER_DIRECTION = <?= json_encode($dir)  ?>;
window.CSRF_TOKEN     = <?= json_encode($csrf) ?>;
window.PAGE_PERMISSIONS = window.TENANT_USERS_CONFIG;
</script>

<!-- Permissions JSON for scripts that prefer it in DOM -->
<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate' => $canCreate,
    'canEdit'   => $canEdit,
    'canDelete' => $canDelete
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/tenant_users.js?v=<?= assetVer('/admin/assets/js/pages/tenant_users.js') ?>"></script>

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
        if (!window.TenantUsers || typeof window.TenantUsers.init !== 'function') return;
        initialized = true;
        cleanup();
        var maybePromise = window.TenantUsers.init();
        if (maybePromise && typeof maybePromise.then === 'function') {
            maybePromise
                .then(function ()  { console.log('[TenantUsers] Initialized'); })
                .catch(function (e) { console.error('[TenantUsers] Init failed', e); });
        }
    }

    window.addEventListener('admin:i18n:applied', tryInit);
    tryInit();

    var pollCount = 0;
    poll = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 60) {
            cleanup();
            if (!initialized) console.warn('[TenantUsers] init timed out');
        }
    }, 100);
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
