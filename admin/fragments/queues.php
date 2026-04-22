<?php
declare(strict_types=1);

/**
 * /admin/fragments/queues.php
 * Queue Management - Production Ready
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
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManageQueues = can('manage_settings') || is_super_admin();
$canDelete       = $canManageQueues;

if (!$canManageQueues) {
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
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
$_qAllowedLangs = ['ar','en','fr','tr','ur','de','es','fa','he','hi','zh','ja','ko','pt','ru','it','nl','sv','pl','th','vi','id','ms','bn','sw','tl'];
$_qSafeLang     = in_array($lang, $_qAllowedLangs, true) ? $lang : 'en';
$_qLangFile     = __DIR__ . '/../../languages/Queues/' . $_qSafeLang . '.json';
$_qStringsRaw   = [];
if (file_exists($_qLangFile)) {
    $decoded = json_decode(file_get_contents($_qLangFile), true);
    $_qStringsRaw = isset($decoded['strings']) ? $decoded['strings'] : ($decoded ?: []);
}

// Flatten nested → dot-notation for JS
function _qFlatten(array $arr, string $prefix = ''): array {
    $result = [];
    foreach ($arr as $k => $v) {
        $key = $prefix ? $prefix . '.' . $k : $k;
        if (is_array($v)) {
            $result += _qFlatten($v, $key);
        } else {
            $result[$key] = $v;
        }
    }
    return $result;
}
$_qStringsFlat = _qFlatten($_qStringsRaw);

if (!function_exists('_qt')) {
    function _qt(string $key, string $fallback = ''): string {
        global $_qStringsRaw;
        $parts = explode('.', $key);
        $val   = $_qStringsRaw;
        foreach ($parts as $p) {
            if (is_array($val) && isset($val[$p])) {
                $val = $val[$p];
            } else {
                return $fallback ?: $key;
            }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}

$apiBase = '/api';
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/queues.css?v=<?= assetVer('/admin/assets/css/pages/queues.css') ?>">

<meta data-page="queues"
      data-i18n-files="/languages/Queues/<?= rawurlencode($_qSafeLang) ?>.json">

<div class="page-container" id="queuesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 data-i18n="title"><?= htmlspecialchars(_qt('title', 'Queue Management')) ?></h1>
            <p class="page-subtitle" data-i18n="subtitle">
                <?= htmlspecialchars(_qt('subtitle', 'Monitor and manage background job queues')) ?>
            </p>
        </div>
        <div class="page-header-actions">
            <button id="btnRefresh" class="btn btn-secondary" data-i18n="actions.refresh">
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <span><?= htmlspecialchars(_qt('actions.refresh', 'Refresh')) ?></span>
            </button>
            <button id="btnArchiveDone" class="btn btn-primary" data-i18n="actions.archive">
                <i class="fas fa-archive" aria-hidden="true"></i>
                <span><?= htmlspecialchars(_qt('actions.archive', 'Archive Done')) ?></span>
            </button>
            <button id="btnOpenPurge" class="btn btn-danger" data-i18n="actions.purge">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                <span><?= htmlspecialchars(_qt('actions.purge', 'Purge Old')) ?></span>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card stat-total">
            <div class="stat-label" data-i18n="stats.total"><?= htmlspecialchars(_qt('stats.total', 'Total Jobs')) ?></div>
            <div class="stat-value" id="statTotal">0</div>
        </div>
        <div class="stat-card stat-pending">
            <div class="stat-label" data-i18n="stats.pending"><?= htmlspecialchars(_qt('stats.pending', 'Pending')) ?></div>
            <div class="stat-value" id="statPending">0</div>
        </div>
        <div class="stat-card stat-working">
            <div class="stat-label" data-i18n="stats.working"><?= htmlspecialchars(_qt('stats.working', 'Working')) ?></div>
            <div class="stat-value" id="statWorking">0</div>
        </div>
        <div class="stat-card stat-done">
            <div class="stat-label" data-i18n="stats.done"><?= htmlspecialchars(_qt('stats.done', 'Done')) ?></div>
            <div class="stat-value" id="statDone">0</div>
        </div>
        <div class="stat-card stat-failed">
            <div class="stat-label" data-i18n="stats.failed"><?= htmlspecialchars(_qt('stats.failed', 'Failed')) ?></div>
            <div class="stat-value" id="statFailed">0</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filters-card">
        <div class="card-body">
            <div class="filters-grid">

                <div class="filter-group">
                    <label class="filter-label" for="filterSearch" data-i18n="filter.search">
                        <?= htmlspecialchars(_qt('filter.search', 'Search')) ?>
                    </label>
                    <input type="text" id="filterSearch" class="form-control"
                           data-i18n-placeholder="filter.search_placeholder"
                           placeholder="<?= htmlspecialchars(_qt('filter.search_placeholder', 'Search...')) ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="filterQueue" data-i18n="filter.queue">
                        <?= htmlspecialchars(_qt('filter.queue', 'Queue')) ?>
                    </label>
                    <select id="filterQueue" class="form-control">
                        <option value="" data-i18n="filter.all_queues">
                            <?= htmlspecialchars(_qt('filter.all_queues', 'All Queues')) ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="filterPriority" data-i18n="filter.priority">
                        <?= htmlspecialchars(_qt('filter.priority', 'Priority')) ?>
                    </label>
                    <select id="filterPriority" class="form-control">
                        <option value=""        data-i18n="filter.all_priorities"><?=     htmlspecialchars(_qt('filter.all_priorities',      'All Priorities')) ?></option>
                        <option value="low"     data-i18n="status.priority.low"><?=       htmlspecialchars(_qt('status.priority.low',         'Low')) ?></option>
                        <option value="normal"  data-i18n="status.priority.normal"><?=    htmlspecialchars(_qt('status.priority.normal',      'Normal')) ?></option>
                        <option value="high"    data-i18n="status.priority.high"><?=      htmlspecialchars(_qt('status.priority.high',        'High')) ?></option>
                        <option value="urgent"  data-i18n="status.priority.urgent"><?=    htmlspecialchars(_qt('status.priority.urgent',      'Urgent')) ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="filterStatus" data-i18n="filter.status">
                        <?= htmlspecialchars(_qt('filter.status', 'Status')) ?>
                    </label>
                    <select id="filterStatus" class="form-control">
                        <option value=""  data-i18n="filter.all_statuses"><?= htmlspecialchars(_qt('filter.all_statuses', 'All Statuses')) ?></option>
                        <option value="0" data-i18n="status.pending"><?=     htmlspecialchars(_qt('status.pending',      'Pending')) ?></option>
                        <option value="1" data-i18n="status.working"><?=     htmlspecialchars(_qt('status.working',      'Working')) ?></option>
                        <option value="2" data-i18n="status.done"><?=        htmlspecialchars(_qt('status.done',         'Done')) ?></option>
                        <option value="3" data-i18n="status.failed"><?=      htmlspecialchars(_qt('status.failed',       'Failed')) ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply">
                            <?= htmlspecialchars(_qt('filter.apply', 'Filter')) ?>
                        </button>
                        <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear">
                            <?= htmlspecialchars(_qt('filter.clear', 'Clear')) ?>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card table-card">
        <div class="card-body" style="padding:0; overflow-x:auto;">

            <!-- Loading -->
            <div id="qmLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="table.loading"><?= htmlspecialchars(_qt('table.loading', 'Loading...')) ?></p>
            </div>

            <!-- Empty -->
            <div id="qmEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
                <h3 data-i18n="table.no_records"><?= htmlspecialchars(_qt('table.no_records', 'No jobs found')) ?></h3>
            </div>

            <!-- Error -->
            <div id="qmError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="messages.load_failed"><?= htmlspecialchars(_qt('messages.load_failed', 'Error loading jobs')) ?></h3>
                <p id="qmErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary"><?= htmlspecialchars(_qt('actions.refresh', 'Retry')) ?></button>
            </div>

            <!-- Table -->
            <div id="qmTableContainer" style="display:none;">
                <table class="data-table" id="queuesTable" aria-label="Queue Jobs">
                    <thead>
                        <tr>
                            <th data-i18n="table.id">         <?= htmlspecialchars(_qt('table.id',         'ID')) ?></th>
                            <th data-i18n="table.queue">      <?= htmlspecialchars(_qt('table.queue',      'Queue')) ?></th>
                            <th data-i18n="table.job_type">   <?= htmlspecialchars(_qt('table.job_type',   'Job Type')) ?></th>
                            <th data-i18n="table.entity">     <?= htmlspecialchars(_qt('table.entity',     'Entity')) ?></th>
                            <th data-i18n="table.priority">   <?= htmlspecialchars(_qt('table.priority',   'Priority')) ?></th>
                            <th data-i18n="table.status">     <?= htmlspecialchars(_qt('table.status',     'Status')) ?></th>
                            <th data-i18n="table.attempts">   <?= htmlspecialchars(_qt('table.attempts',   'Attempts')) ?></th>
                            <th data-i18n="table.error">      <?= htmlspecialchars(_qt('table.error',      'Error')) ?></th>
                            <th data-i18n="table.created_at"> <?= htmlspecialchars(_qt('table.created_at', 'Created')) ?></th>
                            <th data-i18n="table.actions">    <?= htmlspecialchars(_qt('table.actions',    'Actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody id="queuesBody"></tbody>
                </table>
            </div>

        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pagination" role="navigation"></div>
        </div>
    </div>

    <!-- View Job Modal — prefix: qm -->
    <div id="viewJobModal"
         class="qm-modal-backdrop"
         style="display:none;"
         role="dialog" aria-modal="true"
         aria-labelledby="viewJobModalTitle">
        <div class="qm-modal-panel qm-modal-panel--wide">
            <div class="qm-modal-header">
                <h3 id="viewJobModalTitle" data-i18n="modal.view_title">
                    <?= htmlspecialchars(_qt('modal.view_title', 'Job Details')) ?>
                </h3>
                <button type="button" class="btn-close-modal icon-btn"
                        data-modal="viewJobModal"
                        aria-label="<?= htmlspecialchars(_qt('form.close', 'Close')) ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="qm-modal-body">
                <div id="viewJobContent" class="job-details"></div>
            </div>
            <div class="qm-modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal"
                        data-modal="viewJobModal"
                        data-i18n="form.close">
                    <?= htmlspecialchars(_qt('form.close', 'Close')) ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Purge Modal — prefix: qm -->
    <div id="purgeModal"
         class="qm-modal-backdrop"
         style="display:none;"
         role="dialog" aria-modal="true"
         aria-labelledby="purgeModalTitle">
        <div class="qm-modal-panel">
            <div class="qm-modal-header">
                <h3 id="purgeModalTitle" data-i18n="modal.purge_title">
                    <?= htmlspecialchars(_qt('modal.purge_title', 'Purge Old Jobs')) ?>
                </h3>
                <button type="button" class="btn-close-modal icon-btn"
                        data-modal="purgeModal"
                        aria-label="<?= htmlspecialchars(_qt('form.close', 'Close')) ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="qm-modal-body">
                <div class="form-group">
                    <label class="filter-label" for="purgeStatus" data-i18n="modal.purge_status">
                        <?= htmlspecialchars(_qt('modal.purge_status', 'Status to purge')) ?>
                    </label>
                    <select id="purgeStatus" class="form-control">
                        <option value="done"   data-i18n="status.done"><?=   htmlspecialchars(_qt('status.done',   'Done')) ?></option>
                        <option value="failed" data-i18n="status.failed"><?= htmlspecialchars(_qt('status.failed', 'Failed')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="filter-label" for="purgeDays" data-i18n="modal.purge_days">
                        <?= htmlspecialchars(_qt('modal.purge_days', 'Older than (days)')) ?>
                    </label>
                    <input type="number" id="purgeDays" class="form-control" value="30" min="1">
                </div>
                <p class="text-danger" data-i18n="modal.purge_confirm">
                    <?= htmlspecialchars(_qt('modal.purge_confirm', 'Are you sure? This cannot be undone.')) ?>
                </p>
            </div>
            <div class="qm-modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal"
                        data-modal="purgeModal"
                        data-i18n="form.cancel">
                    <?= htmlspecialchars(_qt('form.cancel', 'Cancel')) ?>
                </button>
                <button id="btnConfirmPurge" class="btn btn-danger" data-i18n="form.confirm">
                    <?= htmlspecialchars(_qt('form.confirm', 'Confirm')) ?>
                </button>
            </div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.QUEUES_CONFIG = {
    apiBase:   <?= json_encode($apiBase,        JSON_UNESCAPED_SLASHES) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    lang:      <?= json_encode($_qSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_qStringsFlat,  JSON_UNESCAPED_UNICODE) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/queues.js?v=<?= assetVer('/admin/assets/js/pages/queues.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>