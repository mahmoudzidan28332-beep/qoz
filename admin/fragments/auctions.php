<?php
declare(strict_types=1);

/**
 * /admin/fragments/auctions.php — Production v2.0
 * ─ لا inline styles
 * ─ assetVer() بدل time()
 * ─ admin_context helpers
 * ─ AUCTIONS_CONFIG كمصدر وحيد للـ JS
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ── Context ──────────────────────────────────────────────────
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();
$userId   = admin_user_id();
$isSA     = is_super_admin();

// ── Permissions ──────────────────────────────────────────────
$canCreate = $isSA || can_create('auctions')    || can('auctions.manage');
$canEdit   = $isSA || can_edit_all('auctions')  || can_edit_own('auctions')   || can('auctions.manage');
$canDelete = $isSA || can_delete_all('auctions')|| can_delete_own('auctions') || can('auctions.manage');
$canView   = $isSA || can_view_all('auctions')  || can_view_own('auctions')   || can_view_tenant('auctions') || can('auctions.manage');

if (!$canView) {
    http_response_code(403);
    exit($isFragment ? json_encode(['error' => 'Access denied']) : 'Access denied');
}

$apiBase = '/api';

// ── Translations ─────────────────────────────────────────────
$_aucStrings     = [];
$_aucAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
];
$_aucSafeLang = in_array($lang, $_aucAllowedLangs, true) ? $lang : 'en';
$_aucLangFile = __DIR__ . '/../../languages/Auctions/' . $_aucSafeLang . '.json';
if (file_exists($_aucLangFile)) {
    $_aucJson = json_decode(file_get_contents($_aucLangFile), true);
    if (is_array($_aucJson)) {
        $_aucStrings = $_aucJson['strings'] ?? $_aucJson;
    }
}

function _auct(string $key, string $fallback = ''): string
{
    global $_aucStrings;
    $parts = explode('.', $key);
    $val   = $_aucStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) { $val = $val[$k]; } else { return $fallback ?: $key; }
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}

if (!function_exists('assetVer')) {
    function assetVer(string $path): string {
        static $cache = [];
        if (!isset($cache[$path])) {
            $f = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($f) ? (string)filemtime($f) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/auctions.css?v=<?= assetVer('/admin/assets/css/pages/auctions.css') ?>">

<meta data-page="auctions"
      data-i18n-files="/languages/Auctions/<?= rawurlencode($_aucSafeLang) ?>.json">

<div class="page-container" id="auctionsPageContainer"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══ PAGE HEADER ════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="auctions.title">
                <?= htmlspecialchars(_auct('auctions.title', 'Auctions'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="auctions.subtitle">
                <?= htmlspecialchars(_auct('auctions.subtitle', 'Manage your auction listings'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddAuction" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="auctions.add_new">
                    <?= htmlspecialchars(_auct('auctions.add_new', 'Add Auction'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ FORM CARD ══════════════════════════════════════ -->
    <div id="auctionFormContainer" class="card auc-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="auctionFormTitle" data-i18n="form.add_title">
                <?= htmlspecialchars(_auct('form.add_title', 'Add Auction'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseAuctionForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="auctionForm" novalidate>
                <input type="hidden" id="auctionFormId"   name="id">
                <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="auctionTenantId" name="tenant_id" value="<?= (int)$tenantId ?>">

                <!-- TABS NAV -->
                <div class="auc-tabs">
                    <button type="button" class="auc-tab-btn active" data-tab="general">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span data-i18n="tabs.general"><?= htmlspecialchars(_auct('tabs.general','General'), ENT_QUOTES,'UTF-8') ?></span>
                    </button>
                    <button type="button" class="auc-tab-btn" data-tab="pricing">
                        <i class="fas fa-tag" aria-hidden="true"></i>
                        <span data-i18n="tabs.pricing"><?= htmlspecialchars(_auct('tabs.pricing','Pricing'), ENT_QUOTES,'UTF-8') ?></span>
                    </button>
                    <button type="button" class="auc-tab-btn" data-tab="schedule">
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                        <span data-i18n="tabs.schedule"><?= htmlspecialchars(_auct('tabs.schedule','Schedule'), ENT_QUOTES,'UTF-8') ?></span>
                    </button>
                    <button type="button" class="auc-tab-btn" data-tab="bids">
                        <i class="fas fa-gavel" aria-hidden="true"></i>
                        <span data-i18n="tabs.bids"><?= htmlspecialchars(_auct('tabs.bids','Bids'), ENT_QUOTES,'UTF-8') ?></span>
                    </button>
                    <button type="button" class="auc-tab-btn" data-tab="translations">
                        <i class="fas fa-language" aria-hidden="true"></i>
                        <span data-i18n="tabs.translations"><?= htmlspecialchars(_auct('tabs.translations','Translations'), ENT_QUOTES,'UTF-8') ?></span>
                    </button>
                </div>

                <!-- TAB: General ──────────────────────────── -->
                <div class="auc-tab-content active" id="auc-tab-general">
                    <div class="form-row">
                        <div class="form-group auc-full">
                            <label class="required" for="auctionTitle" data-i18n="form.fields.title.label">
                                <?= htmlspecialchars(_auct('form.fields.title.label','Auction Title'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="text" id="auctionTitle" name="title" class="form-control" required
                                   placeholder="<?= htmlspecialchars(_auct('form.fields.title.placeholder','Enter auction title'), ENT_QUOTES,'UTF-8') ?>">
                            <div class="invalid-feedback" data-i18n="form.fields.title.required">
                                <?= htmlspecialchars(_auct('form.fields.title.required','Title is required'), ENT_QUOTES,'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionSlug" data-i18n="form.fields.slug.label">
                                <?= htmlspecialchars(_auct('form.fields.slug.label','Slug'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="text" id="auctionSlug" name="slug" class="form-control"
                                   placeholder="<?= htmlspecialchars(_auct('form.fields.slug.placeholder','auto-generated-if-empty'), ENT_QUOTES,'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label for="auctionProduct" data-i18n="form.fields.product_id.label">
                                <?= htmlspecialchars(_auct('form.fields.product_id.label','Product'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionProduct" name="product_id" class="form-control">
                                <option value="">
                                    <?= htmlspecialchars(_auct('form.fields.product_id.select','Select product (optional)'), ENT_QUOTES,'UTF-8') ?>
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required" for="auctionEntity" data-i18n="form.fields.entity_id.label">
                                <?= htmlspecialchars(_auct('form.fields.entity_id.label','Entity'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionEntity" name="entity_id" class="form-control" required>
                                <option value="">
                                    <?= htmlspecialchars(_auct('form.fields.entity_id.select','Select entity'), ENT_QUOTES,'UTF-8') ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionType" data-i18n="form.fields.auction_type.label">
                                <?= htmlspecialchars(_auct('form.fields.auction_type.label','Auction Type'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionType" name="auction_type" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="reserve">Reserve</option>
                                <option value="buy_now">Buy Now</option>
                                <option value="dutch">Dutch</option>
                                <option value="sealed_bid">Sealed Bid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="auctionStatus" data-i18n="form.fields.status.label">
                                <?= htmlspecialchars(_auct('form.fields.status.label','Status'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionStatus" name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="ended">Ended</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="sold">Sold</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="auctionCondition" data-i18n="form.fields.condition_type.label">
                                <?= htmlspecialchars(_auct('form.fields.condition_type.label','Condition'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionCondition" name="condition_type" class="form-control">
                                <option value="new">New</option>
                                <option value="like_new">Like New</option>
                                <option value="very_good">Very Good</option>
                                <option value="good">Good</option>
                                <option value="acceptable">Acceptable</option>
                                <option value="for_parts">For Parts</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionQuantity" data-i18n="form.fields.quantity.label">
                                <?= htmlspecialchars(_auct('form.fields.quantity.label','Quantity'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionQuantity" name="quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label for="auctionIsFeatured" data-i18n="form.fields.is_featured.label">
                                <?= htmlspecialchars(_auct('form.fields.is_featured.label','Featured'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionIsFeatured" name="is_featured" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="auctionAutoBid" data-i18n="form.fields.auto_bid_enabled.label">
                                <?= htmlspecialchars(_auct('form.fields.auto_bid_enabled.label','Auto Bid'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionAutoBid" name="auto_bid_enabled" class="form-control">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="auctionNotes" data-i18n="form.fields.notes.label">
                            <?= htmlspecialchars(_auct('form.fields.notes.label','Notes'), ENT_QUOTES,'UTF-8') ?>
                        </label>
                        <textarea id="auctionNotes" name="notes" class="form-control" rows="3"
                                  placeholder="<?= htmlspecialchars(_auct('form.fields.notes.placeholder','Internal notes...'), ENT_QUOTES,'UTF-8') ?>"></textarea>
                    </div>
                </div>

                <!-- TAB: Pricing ──────────────────────────── -->
                <div class="auc-tab-content" id="auc-tab-pricing" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required" for="auctionStartingPrice" data-i18n="form.fields.starting_price.label">
                                <?= htmlspecialchars(_auct('form.fields.starting_price.label','Starting Price'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionStartingPrice" name="starting_price"
                                   class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="auctionReservePrice" data-i18n="form.fields.reserve_price.label">
                                <?= htmlspecialchars(_auct('form.fields.reserve_price.label','Reserve Price'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionReservePrice" name="reserve_price"
                                   class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="auctionBuyNowPrice" data-i18n="form.fields.buy_now_price.label">
                                <?= htmlspecialchars(_auct('form.fields.buy_now_price.label','Buy Now Price'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionBuyNowPrice" name="buy_now_price"
                                   class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionBidIncrement" data-i18n="form.fields.bid_increment.label">
                                <?= htmlspecialchars(_auct('form.fields.bid_increment.label','Bid Increment'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionBidIncrement" name="bid_increment"
                                   class="form-control" step="0.01" min="0.01" value="5.00">
                        </div>
                        <div class="form-group">
                            <label class="required" for="auctionCurrency" data-i18n="form.fields.currency_id.label">
                                <?= htmlspecialchars(_auct('form.fields.currency_id.label','Currency'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionCurrency" name="currency_id" class="form-control" required>
                                <option value="">
                                    <?= htmlspecialchars(_auct('form.fields.currency_id.select','Select currency'), ENT_QUOTES,'UTF-8') ?>
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="auctionShipping" data-i18n="form.fields.shipping_cost.label">
                                <?= htmlspecialchars(_auct('form.fields.shipping_cost.label','Shipping Cost'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionShipping" name="shipping_cost"
                                   class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionPaymentDeadline" data-i18n="form.fields.payment_deadline_hours.label">
                                <?= htmlspecialchars(_auct('form.fields.payment_deadline_hours.label','Payment Deadline (hours)'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionPaymentDeadline" name="payment_deadline_hours"
                                   class="form-control" value="48" min="1">
                        </div>
                    </div>
                </div>

                <!-- TAB: Schedule ─────────────────────────── -->
                <div class="auc-tab-content" id="auc-tab-schedule" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required" for="auctionStartDate" data-i18n="form.fields.start_date.label">
                                <?= htmlspecialchars(_auct('form.fields.start_date.label','Start Date & Time'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="datetime-local" id="auctionStartDate" name="start_date"
                                   class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="required" for="auctionEndDate" data-i18n="form.fields.end_date.label">
                                <?= htmlspecialchars(_auct('form.fields.end_date.label','End Date & Time'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="datetime-local" id="auctionEndDate" name="end_date"
                                   class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionAutoExtend" data-i18n="form.fields.auto_extend.label">
                                <?= htmlspecialchars(_auct('form.fields.auto_extend.label','Auto Extend'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <select id="auctionAutoExtend" name="auto_extend" class="form-control">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="auctionExtendMinutes" data-i18n="form.fields.extend_minutes.label">
                                <?= htmlspecialchars(_auct('form.fields.extend_minutes.label','Extend By (minutes)'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionExtendMinutes" name="extend_minutes"
                                   class="form-control" value="5" min="1">
                        </div>
                        <div class="form-group">
                            <label for="auctionMinExtendBidTime" data-i18n="form.fields.min_extend_bid_time.label">
                                <?= htmlspecialchars(_auct('form.fields.min_extend_bid_time.label','Min. Time to Extend (min)'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <input type="number" id="auctionMinExtendBidTime" name="min_extend_bid_time"
                                   class="form-control" value="5" min="1">
                        </div>
                    </div>
                </div>

                <!-- TAB: Bids (view-only) ─────────────────── -->
                <div class="auc-tab-content" id="auc-tab-bids" style="display:none;">
                    <div class="auc-bids-panel">
                        <div class="auc-bids-header">
                            <h5><i class="fas fa-gavel" aria-hidden="true"></i>
                                <?= htmlspecialchars(_auct('bids.title','Bid History'), ENT_QUOTES,'UTF-8') ?>
                            </h5>
                            <button type="button" id="btnRefreshBids" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                                <?= htmlspecialchars(_auct('bids.refresh','Refresh'), ENT_QUOTES,'UTF-8') ?>
                            </button>
                        </div>
                        <div class="auc-bids-stats">
                            <div class="auc-bid-stat"><div class="auc-bid-val" id="statTotalBids">0</div><div class="auc-bid-lbl"><?= htmlspecialchars(_auct('bids.total_bids','Total Bids'), ENT_QUOTES,'UTF-8') ?></div></div>
                            <div class="auc-bid-stat"><div class="auc-bid-val" id="statTotalBidders">0</div><div class="auc-bid-lbl"><?= htmlspecialchars(_auct('bids.total_bidders','Bidders'), ENT_QUOTES,'UTF-8') ?></div></div>
                            <div class="auc-bid-stat"><div class="auc-bid-val" id="statCurrentPrice">—</div><div class="auc-bid-lbl"><?= htmlspecialchars(_auct('bids.current_price','Current Price'), ENT_QUOTES,'UTF-8') ?></div></div>
                            <div class="auc-bid-stat"><div class="auc-bid-val" id="statWinningAmount">—</div><div class="auc-bid-lbl"><?= htmlspecialchars(_auct('bids.winning_amount','Winning Amount'), ENT_QUOTES,'UTF-8') ?></div></div>
                        </div>
                        <div id="bidsLoading" class="loading-state" style="display:none;"><div class="spinner" role="status"></div></div>
                        <div id="bidsEmpty" class="empty-state" style="display:none;">
                            <div class="empty-icon"><i class="fas fa-gavel" aria-hidden="true"></i></div>
                            <p><?= htmlspecialchars(_auct('bids.empty','No bids yet'), ENT_QUOTES,'UTF-8') ?></p>
                        </div>
                        <div id="bidsTableWrapper" class="table-responsive" style="display:none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= htmlspecialchars(_auct('bids.user','User'), ENT_QUOTES,'UTF-8') ?></th>
                                        <th><?= htmlspecialchars(_auct('bids.amount','Amount'), ENT_QUOTES,'UTF-8') ?></th>
                                        <th><?= htmlspecialchars(_auct('bids.type','Type'), ENT_QUOTES,'UTF-8') ?></th>
                                        <th><?= htmlspecialchars(_auct('bids.status','Status'), ENT_QUOTES,'UTF-8') ?></th>
                                        <th><?= htmlspecialchars(_auct('bids.time','Time'), ENT_QUOTES,'UTF-8') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="bidsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB: Translations ────────────────────── -->
                <div class="auc-tab-content" id="auc-tab-translations" style="display:none;">
                    <div class="auc-translations-section">
                        <h4 class="auc-section-title">
                            <i class="fas fa-language" aria-hidden="true"></i>
                            <?= htmlspecialchars(_auct('translations.title','Translations'), ENT_QUOTES,'UTF-8') ?>
                        </h4>
                        <div id="auctionTranslations" class="auc-translation-panels"></div>
                        <div class="form-group">
                            <label for="auctionLangSelect">
                                <?= htmlspecialchars(_auct('translations.select_lang','Add Language'), ENT_QUOTES,'UTF-8') ?>
                            </label>
                            <div class="auc-lang-row">
                                <select id="auctionLangSelect" class="form-control">
                                    <option value="">
                                        <?= htmlspecialchars(_auct('translations.choose','Choose language'), ENT_QUOTES,'UTF-8') ?>
                                    </option>
                                </select>
                                <button type="button" id="auctionAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus" aria-hidden="true"></i>
                                    <?= htmlspecialchars(_auct('translations.add','Add Translation'), ENT_QUOTES,'UTF-8') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM ACTIONS ──────────────────────────── -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitAuctionForm">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save">
                            <?= htmlspecialchars(_auct('form.buttons.save','Save'), ENT_QUOTES,'UTF-8') ?>
                        </span>
                    </button>
                    <button type="button" id="btnCancelAuctionForm" class="btn btn-secondary" data-i18n="form.buttons.cancel">
                        <?= htmlspecialchars(_auct('form.buttons.cancel','Cancel'), ENT_QUOTES,'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAuction"
                            class="btn btn-danger auc-delete-btn" style="display:none;" data-i18n="form.buttons.delete">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <?= htmlspecialchars(_auct('form.buttons.delete','Delete'), ENT_QUOTES,'UTF-8') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ FILTERS ════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="auctionSearch" data-i18n="filters.search">
                        <?= htmlspecialchars(_auct('filters.search','Search'), ENT_QUOTES,'UTF-8') ?>
                    </label>
                    <input type="text" id="auctionSearch" class="form-control"
                           placeholder="<?= htmlspecialchars(_auct('filters.search_placeholder','Search auctions...'), ENT_QUOTES,'UTF-8') ?>">
                </div>
                <?php if ($isSA): ?>
                <div class="filter-group">
                    <label class="filter-label" for="auctionTenantFilter" data-i18n="filters.tenant_id">
                        <?= htmlspecialchars(_auct('filters.tenant_id','Tenant ID'), ENT_QUOTES,'UTF-8') ?>
                    </label>
                    <input type="number" id="auctionTenantFilter" class="form-control" value="<?= (int)$tenantId ?>">
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label class="filter-label" for="auctionStatusFilter" data-i18n="filters.status">
                        <?= htmlspecialchars(_auct('filters.status','Status'), ENT_QUOTES,'UTF-8') ?>
                    </label>
                    <select id="auctionStatusFilter" class="form-control">
                        <option value=""><?= htmlspecialchars(_auct('filters.all_status','All Status'), ENT_QUOTES,'UTF-8') ?></option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="ended">Ended</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="sold">Sold</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auctionTypeFilter" data-i18n="filters.auction_type">
                        <?= htmlspecialchars(_auct('filters.auction_type','Type'), ENT_QUOTES,'UTF-8') ?>
                    </label>
                    <select id="auctionTypeFilter" class="form-control">
                        <option value=""><?= htmlspecialchars(_auct('filters.all_types','All Types'), ENT_QUOTES,'UTF-8') ?></option>
                        <option value="normal">Normal</option>
                        <option value="reserve">Reserve</option>
                        <option value="buy_now">Buy Now</option>
                        <option value="dutch">Dutch</option>
                        <option value="sealed_bid">Sealed Bid</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auctionFeaturedFilter" data-i18n="filters.is_featured">
                        <?= htmlspecialchars(_auct('filters.is_featured','Featured'), ENT_QUOTES,'UTF-8') ?>
                    </label>
                    <select id="auctionFeaturedFilter" class="form-control">
                        <option value=""><?= htmlspecialchars(_auct('filters.all','All'), ENT_QUOTES,'UTF-8') ?></option>
                        <option value="1">Featured</option>
                        <option value="0">Not Featured</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyAuctionFilters" class="btn btn-primary" data-i18n="filters.apply">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <?= htmlspecialchars(_auct('filters.apply','Apply'), ENT_QUOTES,'UTF-8') ?>
                        </button>
                        <button id="btnResetAuctionFilters" class="btn btn-secondary" data-i18n="filters.reset">
                            <?= htmlspecialchars(_auct('filters.reset','Reset'), ENT_QUOTES,'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ DATA TABLE ═════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div id="aucLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="auctions.loading">
                    <?= htmlspecialchars(_auct('auctions.loading','Loading auctions...'), ENT_QUOTES,'UTF-8') ?>
                </p>
            </div>
            <div id="aucEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-gavel" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title">
                    <?= htmlspecialchars(_auct('table.empty.title','No Auctions Found'), ENT_QUOTES,'UTF-8') ?>
                </h3>
                <p data-i18n="table.empty.message">
                    <?= htmlspecialchars(_auct('table.empty.message','Start by adding your first auction'), ENT_QUOTES,'UTF-8') ?>
                </p>
                <?php if ($canCreate): ?>
                <button id="btnAddAuctionEmpty" class="btn btn-primary" data-i18n="table.empty.add_first">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <?= htmlspecialchars(_auct('table.empty.add_first','Add First Auction'), ENT_QUOTES,'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>
            <div id="aucError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="messages.error.load_failed">
                    <?= htmlspecialchars(_auct('messages.error.load_failed','Error Loading Data'), ENT_QUOTES,'UTF-8') ?>
                </h3>
                <p id="aucErrorMessage"></p>
                <button id="btnAuctionRetry" class="btn btn-primary" data-i18n="auctions.retry">
                    <?= htmlspecialchars(_auct('auctions.retry','Retry'), ENT_QUOTES,'UTF-8') ?>
                </button>
            </div>
            <div id="aucTableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="auctionsTable" aria-label="Auctions">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <?php if ($isSA): ?>
                            <th data-i18n="table.headers.tenant">
                                <?= htmlspecialchars(_auct('table.headers.tenant','Tenant'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <?php endif; ?>
                            <th data-i18n="table.headers.entity">
                                <?= htmlspecialchars(_auct('table.headers.entity','Entity'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.title">
                                <?= htmlspecialchars(_auct('table.headers.title','Title'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.type">
                                <?= htmlspecialchars(_auct('table.headers.type','Type'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.status">
                                <?= htmlspecialchars(_auct('table.headers.status','Status'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.current_price">
                                <?= htmlspecialchars(_auct('table.headers.current_price','Price'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.bids">
                                <?= htmlspecialchars(_auct('table.headers.bids','Bids'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.end_date">
                                <?= htmlspecialchars(_auct('table.headers.end_date','End Date'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.actions">
                                <?= htmlspecialchars(_auct('table.headers.actions','Actions'), ENT_QUOTES,'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="auctionTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="aucPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="aucPagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.AUCTIONS_CONFIG = {
    apiBase:     <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>,
    lang:        <?= json_encode($_aucSafeLang) ?>,
    dir:         <?= json_encode($dir) ?>,
    tenantId:    <?= (int)$tenantId ?>,
    csrfToken:   <?= json_encode($csrf) ?>,
    userId:      <?= (int)$userId ?>,
    strings:     <?= json_encode($_aucStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:   <?= json_encode($canCreate) ?>,
    canEdit:     <?= json_encode($canEdit) ?>,
    canDelete:   <?= json_encode($canDelete) ?>,
    isSuperAdmin:<?= json_encode($isSA) ?>,
    urls: {
        auctions:     <?= json_encode($apiBase.'/auctions',            JSON_UNESCAPED_SLASHES) ?>,
        bids:         <?= json_encode($apiBase.'/auction_bids',        JSON_UNESCAPED_SLASHES) ?>,
        translations: <?= json_encode($apiBase.'/auction_translations',JSON_UNESCAPED_SLASHES) ?>,
        products:     <?= json_encode($apiBase.'/products',            JSON_UNESCAPED_SLASHES) ?>,
        currencies:   <?= json_encode($apiBase.'/currencies',          JSON_UNESCAPED_SLASHES) ?>,
        languages:    <?= json_encode($apiBase.'/languages',           JSON_UNESCAPED_SLASHES) ?>,
        entities:     <?= json_encode($apiBase.'/entities',            JSON_UNESCAPED_SLASHES) ?>
    }
};
</script>
<script src="/admin/assets/js/pages/auctions.js?v=<?= assetVer('/admin/assets/js/pages/auctions.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>