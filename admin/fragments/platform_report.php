<?php
declare(strict_types=1);

/**
 * /admin/fragments/platform_report.php
 * Platform Reports & Analytics Dashboard
 * Comprehensive reporting with charts, tables, filters, and export
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
$dir      = function_exists('admin_dir') ? admin_dir() : 'ltr';
// Fallback: detect RTL from language prefix if admin_dir() returned default
if ($dir === 'ltr') {
    $rtlPrefixes = ['ar', 'he', 'fa', 'ur'];
    foreach ($rtlPrefixes as $prefix) {
        if (strpos($lang, $prefix) === 0) {
            $dir = 'rtl';
            break;
        }
    }
}
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();
$isSuperAdmin    = is_super_admin();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';

// Allow platform admins and tenant admins with a valid tenant context.
// Block users who are neither.
$isTenantAdmin = !$isPlatformAdmin && $tenantId > 0;

if (!$isPlatformAdmin && !$isTenantAdmin) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access restricted to platform or tenant administrators']);
        exit;
    }
    http_response_code(403);
    die('Access restricted to platform or tenant administrators');
}

$apiBase = '/api';

// Translation helpers
if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            if ($v && $v !== $key) return $v;
        }
        global $_PR_LANG;
        return $_PR_LANG[$key] ?? $fallback ?: $key;
    }
}

// Load language file – try exact locale, then language prefix, then English fallback
$_PR_LANG = [];
$langFile = __DIR__ . '/../../languages/PlatformReport/' . $lang . '.json';
if (!file_exists($langFile)) {
    // Try language prefix (e.g., 'ar' from 'ar_SA')
    $langParts = explode('_', $lang);
    $langFile = __DIR__ . '/../../languages/PlatformReport/' . $langParts[0] . '.json';
    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/../../languages/PlatformReport/en.json';
    }
}
if (file_exists($langFile)) {
    $_PR_LANG = json_decode(file_get_contents($langFile), true) ?: [];
}
?>

<link rel="stylesheet" href="/admin/assets/css/pages/platform_report.css">

<div id="platformReportApp" class="pr-container" dir="<?= htmlspecialchars($dir) ?>" data-dir="<?= htmlspecialchars($dir) ?>">

<?php if ($isPlatformAdmin): ?>
<!-- ═══ PLATFORM ADMIN — TENANT SELECTOR ═══ -->
<div class="card platform-admin-panel" id="platformAdminPanel">
    <div class="card-header" style="background:var(--color-warning,#ff9800);color:#fff">
        <i class="fas fa-shield-alt"></i>
        <strong><?= __t('platform_admin.panel_title', 'Platform Admin — Tenant Context') ?></strong>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-5">
                <label><?= __t('platform_admin.select_tenant', 'Select Tenant') ?></label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="number" id="paTenantIdInput" class="form-control" min="1"
                           placeholder="<?= __t('platform_admin.tenant_id_placeholder', 'Enter tenant ID') ?>">
                    <button type="button" id="paLookupTenantBtn" class="btn btn-sm btn-secondary">
                        <i class="fas fa-search"></i>
                        <?= __t('platform_admin.lookup_tenant', 'Lookup') ?>
                    </button>
                </div>
                <select id="paTenantSelect" class="form-control">
                    <option value=""><?= __t('platform_admin.select_tenant_placeholder', 'Select tenant') ?></option>
                </select>
            </div>
            <div class="form-group col-3" style="display:flex;align-items:flex-end">
                <button type="button" id="paApplyTenantBtn" class="btn btn-warning btn-sm">
                    <i class="fas fa-user-shield"></i>
                    <?= __t('platform_admin.act_on_behalf', 'Filter by Tenant') ?>
                </button>
                <button type="button" id="paClearTenantBtn" class="btn btn-sm btn-secondary" style="margin-left:8px;display:none">
                    <i class="fas fa-times"></i> <?= __t('platform_admin.clear_context', 'Clear') ?>
                </button>
            </div>
        </div>
        <div id="paActiveTenantBanner" style="display:none;padding:8px 12px;background:rgba(255,152,0,0.15);border:1px solid #ff9800;border-radius:6px;margin-top:8px;color:#ff9800;font-size:0.9rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="paActiveTenantLabel"></span>
        </div>
    </div>
</div>
<?php endif; ?>

    <!-- Page Header -->
    <div class="pr-page-header">
        <h2 class="pr-page-title"><?= __t('page_title', 'Platform Reports & Analytics') ?></h2>
    </div>

    <!-- Dashboard Summary Cards -->
    <div class="pr-summary-section">
        <h3 class="pr-section-title"><?= __t('dashboard_summary', 'Dashboard Summary') ?></h3>
        <div class="pr-summary-cards" id="prSummaryCards">
            <!-- Today -->
            <div class="pr-card pr-card-today">
                <div class="pr-card-header"><?= __t('today', 'Today') ?></div>
                <div class="pr-card-body">
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('orders', 'Orders') ?></span>
                        <span class="pr-stat-value" id="todayOrders">-</span>
                    </div>
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('revenue', 'Revenue') ?></span>
                        <span class="pr-stat-value" id="todayRevenue">-</span>
                    </div>
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('customers', 'Customers') ?></span>
                        <span class="pr-stat-value" id="todayCustomers">-</span>
                    </div>
                </div>
            </div>
            <!-- This Month -->
            <div class="pr-card pr-card-month">
                <div class="pr-card-header"><?= __t('this_month', 'This Month') ?></div>
                <div class="pr-card-body">
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('orders', 'Orders') ?></span>
                        <span class="pr-stat-value" id="monthOrders">-</span>
                    </div>
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('revenue', 'Revenue') ?></span>
                        <span class="pr-stat-value" id="monthRevenue">-</span>
                    </div>
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('customers', 'Customers') ?></span>
                        <span class="pr-stat-value" id="monthCustomers">-</span>
                    </div>
                    <div class="pr-stat">
                        <span class="pr-stat-label"><?= __t('avg_order_value', 'Avg Order') ?></span>
                        <span class="pr-stat-value" id="monthAvgOrder">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="pr-filters-section">
        <h3 class="pr-section-title"><?= __t('filters', 'Filters') ?></h3>
        <div class="pr-filters-row">
            <div class="pr-filter-group">
                <label for="prReportType"><?= __t('report_type', 'Report Type') ?></label>
                <select id="prReportType" class="pr-input">
                    <option value=""><?= __t('select_report', 'Select Report Type') ?></option>
                    <option value="sales_overview"><?= __t('sales_overview', 'Sales Overview') ?></option>
                    <option value="revenue_profit"><?= __t('revenue_profit', 'Revenue & Profit') ?></option>
                    <option value="orders_performance"><?= __t('orders_performance', 'Orders Performance') ?></option>
                    <option value="products_performance"><?= __t('products_performance', 'Products Performance') ?></option>
                    <option value="ads_performance"><?= __t('ads_performance', 'Ads Performance') ?></option>
                    <option value="returns_complaints"><?= __t('returns_complaints', 'Returns & Complaints') ?></option>
                    <option value="entities_performance"><?= __t('entities_performance', 'Entities Performance') ?></option>
                    <option value="customer_behavior"><?= __t('customer_behavior', 'Customer Behavior') ?></option>
                    <option value="delivery_performance"><?= __t('delivery_performance', 'Delivery Performance') ?></option>
                    <?php if ($isPlatformAdmin): ?>
                    <option value="platform_health"><?= __t('platform_health', 'Platform Health') ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="pr-filter-group">
                <label for="prStartDate"><?= __t('start_date', 'Start Date') ?></label>
                <input type="date" id="prStartDate" class="pr-input">
            </div>

            <div class="pr-filter-group">
                <label for="prEndDate"><?= __t('end_date', 'End Date') ?></label>
                <input type="date" id="prEndDate" class="pr-input">
            </div>

            <div class="pr-filter-group">
                <label for="prGroupBy"><?= __t('group_by', 'Group By') ?></label>
                <select id="prGroupBy" class="pr-input">
                    <option value="day"><?= __t('day', 'Day') ?></option>
                    <option value="week"><?= __t('week', 'Week') ?></option>
                    <option value="month"><?= __t('month', 'Month') ?></option>
                </select>
            </div>

            <?php if ($isPlatformAdmin): ?>
            <input type="hidden" id="prTenantId" value="">
            <?php else: ?>
            <div class="pr-filter-group">
                <label for="prTenantDisplay"><?= __t('tenant', 'Tenant') ?></label>
                <input type="text" id="prTenantDisplay" class="pr-input" value="<?= htmlspecialchars($user['tenant_name'] ?? __t('tenant', 'Tenant') . ' #' . (int)$tenantId) ?>" disabled>
                <input type="hidden" id="prTenantId" value="<?= (int)$tenantId ?>">
            </div>
            <?php endif; ?>

            <div class="pr-filter-group">
                <label for="prEntityId"><?= __t('entity', 'Entity / Store') ?></label>
                <select id="prEntityId" class="pr-input">
                    <option value=""><?= __t('all_entities', 'All Entities') ?></option>
                </select>
            </div>

            <div class="pr-filter-group pr-filter-actions">
                <button type="button" id="prGenerateBtn" class="pr-btn pr-btn-primary">
                    <?= __t('generate_report', 'Generate Report') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Export Buttons -->
    <div class="pr-export-section" id="prExportSection" style="display:none;">
        <button type="button" class="pr-btn pr-btn-export" data-format="excel">
            📊 <?= __t('export_excel', 'Export Excel') ?>
        </button>
        <button type="button" class="pr-btn pr-btn-export" data-format="pdf">
            📄 <?= __t('export_pdf', 'Export PDF') ?>
        </button>
        <button type="button" class="pr-btn pr-btn-export" data-format="csv">
            📋 <?= __t('export_csv', 'Export CSV') ?>
        </button>
    </div>

    <!-- Loading Indicator -->
    <div id="prLoading" class="pr-loading" style="display:none;">
        <div class="pr-spinner"></div>
        <span><?= __t('loading', 'Loading...') ?></span>
    </div>

    <!-- Report Results Area -->
    <div id="prReportResults" class="pr-results-section" style="display:none;">

        <!-- Metrics Cards Grid -->
        <div class="pr-metrics-section">
            <h3 class="pr-section-title"><?= __t('metrics', 'Metrics') ?></h3>
            <div id="prMetricsGrid" class="pr-metrics-grid"></div>
        </div>

        <!-- Chart Area -->
        <div class="pr-chart-section">
            <h3 class="pr-section-title"><?= __t('chart', 'Chart') ?></h3>
            <div class="pr-chart-wrapper">
                <canvas id="prMainChart"></canvas>
            </div>
        </div>

        <!-- Data Table -->
        <div class="pr-table-section">
            <h3 class="pr-section-title"><?= __t('table', 'Table') ?></h3>
            <div class="pr-table-wrapper">
                <table class="pr-data-table" id="prDataTable">
                    <thead id="prDataTableHead"></thead>
                    <tbody id="prDataTableBody"></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- No Data Message -->
    <div id="prNoData" class="pr-no-data" style="display:none;">
        <p><?= __t('no_data', 'No data available for the selected period') ?></p>
    </div>

</div>

<script>
    // Pass PHP variables to JS
    window.__PR_CONFIG = {
        apiBase: <?= json_encode($apiBase) ?>,
        tenantId: <?= json_encode($isPlatformAdmin ? '' : (int)$tenantId) ?>,
        isPlatformAdmin: <?= json_encode($isPlatformAdmin) ?>,
        isTenantAdmin: <?= json_encode($isTenantAdmin) ?>,
        lang: <?= json_encode($lang) ?>,
        dir: <?= json_encode($dir) ?>,
        strings: <?= json_encode($_PR_LANG) ?>
    };
</script>
<script src="/admin/assets/js/pages/platform_report.js"></script>

<?php
// Fragment vs standalone initialization
if (!$isFragment) {
    echo '</main></div>';
    require_once __DIR__ . '/../includes/footer.php';
}
?>