<?php
declare(strict_types=1);

/**
 * /admin/fragments/tickets.php
 * Production Version — Support Tickets Management
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
$tenantId = admin_tenant_id();

$canManageTickets = can('tickets.manage') || can('tickets.create');
$canViewAll       = can_view_all('tickets');
$canViewOwn       = can_view_own('tickets');
$canCreate        = can_create('tickets');
$canEdit          = can_edit_all('tickets') || $canManageTickets;
$canDelete        = can_delete_all('tickets') || $canManageTickets;
$canView          = $canViewAll || $canViewOwn;

if (!$canView && !is_super_admin()) {
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
// TRANSLATION HELPER (server-side)
// ════════════════════════════════════════════════════════════
function __t(string $key, string $fallback = ''): string
{
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback !== '' ? $fallback : $key);
    }
    return $fallback !== '' ? $fallback : $key;
}

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void
    {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'],  ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// i18n strings — flat for JS (from i18n_get or file)
// ════════════════════════════════════════════════════════════
$_ticketsStringsFlat = [];
$_ticketsLangFile = __DIR__ . '/../../languages/tickets/' . rawurlencode($lang) . '.json';
if (file_exists($_ticketsLangFile)) {
    $decoded = json_decode(file_get_contents($_ticketsLangFile), true);
    if (is_array($decoded)) {
        // Flatten nested JSON to dot-notation
        $flatten = function (array $arr, string $prefix = '') use (&$flatten): array {
            $result = [];
            foreach ($arr as $k => $v) {
                $key = $prefix ? $prefix . '.' . $k : $k;
                if (is_array($v)) {
                    $result += $flatten($v, $key);
                } else {
                    $result[$key] = $v;
                }
            }
            return $result;
        };
        $_ticketsStringsFlat = $flatten($decoded);
    }
}
?>
<style id="db-theme-vars-tickets">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet"
      href="/admin/assets/css/pages/tickets.css?v=<?= assetVer('/admin/assets/css/pages/tickets.css') ?>">

<meta data-page="tickets"
      data-assets-css="/admin/assets/css/pages/tickets.css"
      data-i18n-files="/languages/tickets/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="ticketsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="tickets.title">
                <?= __t('tickets.title', 'Support Tickets') ?>
            </h1>
            <p class="page-subtitle" data-i18n="tickets.subtitle">
                <?= __t('tickets.subtitle', 'Manage customer support requests') ?>
            </p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="btnAddTicket" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="tickets.add_new"><?= __t('tickets.add_new', 'New Ticket') ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="ticketFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title">
                <?= __t('form.add_title', 'New Support Ticket') ?>
            </h3>
            <button type="button" class="btn btn-sm btn-secondary" id="btnCloseForm"
                    aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="ticketForm" novalidate>
                <input type="hidden" id="formId"          name="id">
                <input type="hidden"                       name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="ticketTenantId"  name="tenant_id"  value="<?= $tenantId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span data-i18n="tabs.details"><?= __t('tabs.details', 'Details') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="messages">
                        <i class="fas fa-comments" aria-hidden="true"></i>
                        <span data-i18n="tabs.messages"><?= __t('tabs.messages', 'Messages') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history" aria-hidden="true"></i>
                        <span data-i18n="tabs.history"><?= __t('tabs.history', 'Status History') ?></span>
                    </button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="tab-details">
                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label class="filter-label" for="ticketSubject" data-i18n="form.fields.subject.label">
                                <?= __t('form.fields.subject.label', 'Subject') ?>
                            </label>
                            <input type="text" id="ticketSubject" name="subject"
                                   class="form-control" required
                                   data-i18n-placeholder="form.fields.subject.placeholder"
                                   placeholder="<?= __t('form.fields.subject.placeholder', 'Enter ticket subject') ?>">
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ticketCategory" data-i18n="form.fields.category.label">
                                <?= __t('form.fields.category.label', 'Category') ?>
                            </label>
                            <select id="ticketCategory" name="category_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label" for="ticketUser" data-i18n="form.fields.user.label">
                                <?= __t('form.fields.user.label', 'Customer') ?>
                            </label>
                            <select id="ticketUser" name="user_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ticketOrder" data-i18n="form.fields.order.label">
                                <?= __t('form.fields.order.label', 'Related Order') ?>
                            </label>
                            <select id="ticketOrder" name="order_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ticketEntity" data-i18n="form.fields.entity.label">
                                <?= __t('form.fields.entity.label', 'Entity') ?>
                            </label>
                            <select id="ticketEntity" name="entity_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="filter-label" for="ticketDescription" data-i18n="form.fields.description.label">
                            <?= __t('form.fields.description.label', 'Description') ?>
                        </label>
                        <textarea id="ticketDescription" name="description"
                                  class="form-control" rows="5"
                                  data-i18n-placeholder="form.fields.description.placeholder"
                                  placeholder="<?= __t('form.fields.description.placeholder', 'Describe the issue in detail') ?>"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="filter-label" for="ticketStatus" data-i18n="form.fields.status.label">
                                <?= __t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="ticketStatus" name="status" class="form-control">
                                <option value="open"              data-i18n="status.open">              <?= __t('status.open',              'Open') ?></option>
                                <option value="pending"           data-i18n="status.pending">           <?= __t('status.pending',           'Pending') ?></option>
                                <option value="awaiting_customer" data-i18n="status.awaiting_customer"><?= __t('status.awaiting_customer', 'Awaiting Customer') ?></option>
                                <option value="awaiting_vendor"   data-i18n="status.awaiting_vendor">  <?= __t('status.awaiting_vendor',   'Awaiting Vendor') ?></option>
                                <option value="in_progress"       data-i18n="status.in_progress">      <?= __t('status.in_progress',       'In Progress') ?></option>
                                <option value="resolved"          data-i18n="status.resolved">         <?= __t('status.resolved',          'Resolved') ?></option>
                                <option value="closed"            data-i18n="status.closed">           <?= __t('status.closed',            'Closed') ?></option>
                                <option value="cancelled"         data-i18n="status.cancelled">        <?= __t('status.cancelled',         'Cancelled') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ticketPriority" data-i18n="form.fields.priority.label">
                                <?= __t('form.fields.priority.label', 'Priority') ?>
                            </label>
                            <select id="ticketPriority" name="priority" class="form-control">
                                <option value="low"    data-i18n="priority.low">   <?= __t('priority.low',    'Low') ?></option>
                                <option value="normal" data-i18n="priority.normal"><?= __t('priority.normal', 'Normal') ?></option>
                                <option value="high"   data-i18n="priority.high">  <?= __t('priority.high',   'High') ?></option>
                                <option value="urgent" data-i18n="priority.urgent"><?= __t('priority.urgent', 'Urgent') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="filter-label" for="ticketAssigned" data-i18n="form.fields.assigned_to.label">
                                <?= __t('form.fields.assigned_to.label', 'Assigned To') ?>
                            </label>
                            <select id="ticketAssigned" name="assigned_to" class="form-control"></select>
                        </div>
                    </div>
                </div><!-- /tab-details -->

                <!-- Tab: Messages -->
                <div class="tab-content" id="tab-messages" style="display:none">
                    <div id="ticketMessagesList" class="messages-list"></div>
                    <div class="reply-section">
                        <div class="form-group">
                            <label class="filter-label" for="ticketReply" data-i18n="form.fields.reply.label">
                                <?= __t('form.fields.reply.label', 'Add Reply') ?>
                            </label>
                            <textarea id="ticketReply" class="form-control" rows="3"
                                      data-i18n-placeholder="form.fields.reply.placeholder"
                                      placeholder="<?= __t('form.fields.reply.placeholder', 'Type your response here...') ?>"></textarea>
                        </div>
                        <div class="reply-actions">
                            <button type="button" id="btnSendReply" class="btn btn-primary">
                                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                <span data-i18n="form.buttons.send_reply"><?= __t('form.buttons.send_reply', 'Send Reply') ?></span>
                            </button>
                            <label class="internal-note-label">
                                <input type="checkbox" id="replyInternal">
                                <span data-i18n="form.fields.internal_note"><?= __t('form.fields.internal_note', 'Internal Note?') ?></span>
                            </label>
                        </div>
                    </div>
                </div><!-- /tab-messages -->

                <!-- Tab: History -->
                <div class="tab-content" id="tab-history" style="display:none">
                    <div id="ticketHistoryList"></div>
                </div><!-- /tab-history -->

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save Ticket') ?></span>
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnCancelForm"
                            data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTicket" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.delete"><?= __t('form.buttons.delete', 'Delete Ticket') ?></span>
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
                           placeholder="<?= __t('filters.search_placeholder', 'Search by subject or #ID...') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value=""                  data-i18n="filters.all_statuses">       <?= __t('filters.all_statuses',       'All Statuses') ?></option>
                        <option value="open"              data-i18n="status.open">                <?= __t('status.open',                'Open') ?></option>
                        <option value="pending"           data-i18n="status.pending">             <?= __t('status.pending',             'Pending') ?></option>
                        <option value="awaiting_customer" data-i18n="status.awaiting_customer">  <?= __t('status.awaiting_customer',   'Awaiting Customer') ?></option>
                        <option value="awaiting_vendor"   data-i18n="status.awaiting_vendor">    <?= __t('status.awaiting_vendor',     'Awaiting Vendor') ?></option>
                        <option value="in_progress"       data-i18n="status.in_progress">        <?= __t('status.in_progress',         'In Progress') ?></option>
                        <option value="resolved"          data-i18n="status.resolved">           <?= __t('status.resolved',            'Resolved') ?></option>
                        <option value="closed"            data-i18n="status.closed">             <?= __t('status.closed',              'Closed') ?></option>
                        <option value="cancelled"         data-i18n="status.cancelled">          <?= __t('status.cancelled',           'Cancelled') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="priorityFilter" data-i18n="filters.priority">
                        <?= __t('filters.priority', 'Priority') ?>
                    </label>
                    <select id="priorityFilter" class="form-control">
                        <option value=""       data-i18n="filters.all_priorities"><?= __t('filters.all_priorities', 'All Priorities') ?></option>
                        <option value="low"    data-i18n="priority.low">   <?= __t('priority.low',    'Low') ?></option>
                        <option value="normal" data-i18n="priority.normal"><?= __t('priority.normal', 'Normal') ?></option>
                        <option value="high"   data-i18n="priority.high">  <?= __t('priority.high',   'High') ?></option>
                        <option value="urgent" data-i18n="priority.urgent"><?= __t('priority.urgent', 'Urgent') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyFilters" class="btn btn-primary" data-i18n="filters.apply">
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
                <p data-i18n="tickets.loading"><?= __t('tickets.loading', 'Loading tickets...') ?></p>
            </div>

            <!-- Empty -->
            <div id="emptyState" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-ticket-alt" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Tickets Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'There are no support tickets matching your criteria.') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="btnAddTicketEmpty" data-i18n="table.empty.add_first">
                    <?= __t('table.empty.add_first', 'Create First Ticket') ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error -->
            <div id="errorState" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="errors.load_failed"><?= __t('errors.load_failed', 'Failed to load tickets') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="tickets.retry">
                    <?= __t('tickets.retry', 'Retry') ?>
                </button>
            </div>

            <!-- Table -->
            <div id="tableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="ticketsTable" aria-label="Support Tickets">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">       <?= __t('table.headers.id',       'ID') ?></th>
                            <th data-i18n="table.headers.subject">   <?= __t('table.headers.subject',  'Subject') ?></th>
                            <th data-i18n="table.headers.customer">  <?= __t('table.headers.customer', 'Customer') ?></th>
                            <th data-i18n="table.headers.category">  <?= __t('table.headers.category', 'Category') ?></th>
                            <th data-i18n="table.headers.priority">  <?= __t('table.headers.priority', 'Priority') ?></th>
                            <th data-i18n="table.headers.status">    <?= __t('table.headers.status',   'Status') ?></th>
                            <th data-i18n="table.headers.updated">   <?= __t('table.headers.updated',  'Last Updated') ?></th>
                            <th data-i18n="table.headers.actions">   <?= __t('table.headers.actions',  'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pagination" role="navigation"></div>
        </div>
    </div>

</div><!-- /ticketsPageContainer -->

<!-- Unified TICKETS_CONFIG -->
<script>
window.TICKETS_CONFIG = {
    apiBase:      <?= json_encode($apiBase,   JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:    <?= json_encode($csrf) ?>,
    lang:         <?= json_encode($lang) ?>,
    dir:          <?= json_encode($dir)  ?>,
    strings:      <?= json_encode($_ticketsStringsFlat, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:    <?= json_encode($canCreate) ?>,
    canEdit:      <?= json_encode($canEdit)   ?>,
    canDelete:    <?= json_encode($canDelete) ?>,
    apiUrl:       <?= json_encode($apiBase . '/support_tickets',      JSON_UNESCAPED_SLASHES) ?>,
    categoriesApi:<?= json_encode($apiBase . '/ticket_categories',    JSON_UNESCAPED_SLASHES) ?>,
    messagesApi:  <?= json_encode($apiBase . '/ticket_messages',      JSON_UNESCAPED_SLASHES) ?>,
    historyApi:   <?= json_encode($apiBase . '/ticket_status_history',JSON_UNESCAPED_SLASHES) ?>,
    usersApi:     <?= json_encode($apiBase . '/users',                JSON_UNESCAPED_SLASHES) ?>,
    ordersApi:    <?= json_encode($apiBase . '/orders',               JSON_UNESCAPED_SLASHES) ?>,
    entitiesApi:  <?= json_encode($apiBase . '/entities',             JSON_UNESCAPED_SLASHES) ?>,
    itemsPerPage: 20,
    tenantId:     <?= json_encode($tenantId) ?>,
    userId:       <?= json_encode(admin_user_id()) ?>
};

/* Legacy globals */
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE    = <?= json_encode($apiBase)   ?>;
window.APP_CONFIG.TENANT_ID   = <?= json_encode($tenantId)  ?>;
window.APP_CONFIG.CSRF_TOKEN  = <?= json_encode($csrf)      ?>;
window.APP_CONFIG.USER_ID     = <?= json_encode(admin_user_id()) ?>;
window.USER_LANGUAGE          = <?= json_encode($lang) ?>;
window.PAGE_PERMISSIONS       = window.TICKETS_CONFIG;
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/tickets.js?v=<?= assetVer('/admin/assets/js/pages/tickets.js') ?>"></script>

<script>
(function () {
    var initialized = false;
    var poll;

    function flattenObj(obj, prefix) {
        var result = {};
        prefix = prefix ? prefix + '.' : '';
        for (var k in obj) {
            if (!obj.hasOwnProperty(k)) continue;
            var val = obj[k];
            if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
                var nested = flattenObj(val, prefix + k);
                for (var nk in nested) result[nk] = nested[nk];
            } else { result[prefix + k] = val; }
        }
        return result;
    }

    function cleanup() { clearInterval(poll); }

    function tryInit() {
        if (initialized) return;
        if (!window.TRANSLATIONS) return;
        if (!window.Tickets || typeof window.Tickets.init !== 'function') return;
        initialized = true;
        cleanup();
        window.Tickets.init();
    }

    (function loadI18n() {
        var lang = (window.TICKETS_CONFIG && window.TICKETS_CONFIG.lang) || window.USER_LANGUAGE || 'en';
        var url  = '/languages/tickets/' + encodeURIComponent(lang) + '.json';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) {
                var flat = flattenObj(data);
                window.TICKETS_TRANSLATIONS = data;
                window.TRANSLATIONS = window.TRANSLATIONS || {};
                Object.assign(window.TRANSLATIONS, flat);
                if (window.TICKETS_CONFIG) {
                    window.TICKETS_CONFIG.strings = Object.assign({}, window.TICKETS_CONFIG.strings || {}, flat);
                }
                tryInit();
            })
            .catch(function (err) {
                console.warn('[Tickets] i18n load failed:', err);
                window.TRANSLATIONS = window.TRANSLATIONS || {};
                tryInit();
            });
    })();

    tryInit();

    var pollCount = 0;
    poll = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 80) {
            cleanup();
            if (!initialized) {
                console.warn('[Tickets] init timed out — forcing start');
                initialized = true;
                if (window.Tickets && typeof window.Tickets.init === 'function') window.Tickets.init();
            }
        }
    }, 100);
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
