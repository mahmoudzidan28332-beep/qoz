<?php
declare(strict_types=1);

/**
 * /admin/fragments/banners.php
 * Banners Management Fragment
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
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$canManage    = can('banners.manage') || can('banners.create');
$canViewAll   = can_view_all('banners');
$canViewOwn   = can_view_own('banners');
$canViewTenant= can_view_tenant('banners');
$canCreate    = can_create('banners');
$canEditAll   = can_edit_all('banners');
$canEditOwn   = can_edit_own('banners');
$canDeleteAll = can_delete_all('banners');
$canDeleteOwn = can_delete_own('banners');

$canView   = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit   = $canEditAll || $canEditOwn || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied: You do not have permission to view banners');
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
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
if (!function_exists('__t_banners')) {
    function __t_banners(string $key, string $fallback = ''): string {
        return (function_exists('i18n_get') && ($v = i18n_get($key))) ? $v : ($fallback ?: $key);
    }
}
function __t(string $key, string $fallback = ''): string {
    return __t_banners($key, $fallback);
}

// ════════════════════════════════════════════════════════════
// API BASE + IMAGE TYPE
// ════════════════════════════════════════════════════════════
$apiBase     = '/api';
$imageTypeId = 9;

// ════════════════════════════════════════════════════════════
// i18n strings
// ════════════════════════════════════════════════════════════
$_strings = [
    'banners.title'    => __t('banners.title',    'Banners'),
    'banners.add_new'  => __t('banners.add_new',  'Add Banner'),
    'banners.loading'  => __t('banners.loading',  'Loading...'),
    'banners.retry'    => __t('banners.retry',    'Retry'),
    'loading'          => __t('loading',          'Loading...'),
    'retry'            => __t('retry',            'Retry'),
    'error.title'      => __t('messages.error.load_failed', 'Failed to load data'),
    'form.add_title'   => __t('form.add_title',   'Add Banner'),
    'form.edit_title'  => __t('form.edit_title',  'Edit Banner'),
    'form.buttons.save'    => __t('form.buttons.save',    'Save'),
    'form.buttons.saving'  => __t('form.buttons.saving',  'Saving...'),
    'form.buttons.cancel'  => __t('form.buttons.cancel',  'Cancel'),
    'filters.search_placeholder'   => __t('filters.search_placeholder',   'Search banners...'),
    'filters.position_all'         => __t('filters.position_all',         'All Positions'),
    'filters.status_options.all'   => __t('filters.status_options.all',   'All Status'),
    'table.empty.title'            => __t('table.empty.title',            'No Banners Found'),
    'table.empty.message'          => __t('table.empty.message',          'Start by adding a new banner'),
    'table.actions.edit'           => __t('table.actions.edit',           'Edit'),
    'table.actions.delete'         => __t('table.actions.delete',         'Delete'),
    'table.actions.confirm_delete' => __t('table.actions.confirm_delete', 'Are you sure you want to delete this banner?'),
    'messages.success.created'     => __t('messages.success.created',     'Banner created successfully'),
    'messages.success.updated'     => __t('messages.success.updated',     'Banner updated successfully'),
    'messages.success.deleted'     => __t('messages.success.deleted',     'Banner deleted successfully'),
    'messages.error.load_failed'   => __t('messages.error.load_failed',   'Failed to load data'),
    'messages.error.save_failed'   => __t('messages.error.save_failed',   'Failed to save data'),
    'messages.error.delete_failed' => __t('messages.error.delete_failed', 'Failed to delete data'),
    'messages.error.en_required'   => __t('messages.error.en_required',   'English title is required'),
];
?>
<link rel="stylesheet" href="/admin/assets/css/pages/banners.css?v=<?= assetVer('/admin/assets/css/pages/banners.css') ?>">

<meta data-page="banners"
      data-i18n-files="/languages/Banners/<?= rawurlencode($lang) ?>.json">

<div class="page-container full-page-admin" id="bannersPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="banners.title"><?= __t('banners.title', 'Banners') ?></h1>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="btnAddBanner" class="btn btn-sm btn-icon btn-primary"
                    title="<?= __t('banners.add_new', 'Add Banner') ?>"
                    aria-label="<?= __t('banners.add_new', 'Add Banner') ?>">
                <i class="fas fa-plus" aria-hidden="true"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="bannerFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="bannerFormTitle" data-i18n="form.add_title">
                <?= __t('form.add_title', 'Add Banner') ?>
            </h3>
            <button type="button" class="btn btn-sm btn-icon btn-secondary" id="btnCloseForm"
                    title="<?= __t('accessibility.close', 'Close') ?>"
                    aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="bannerForm" novalidate>
                <input type="hidden" id="formId"            name="id">
                <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="bannerImageId"     name="image_id">
                <input type="hidden" id="bannerImageTypeId" value="<?= $imageTypeId ?>">

                <!-- Row 1: Title + Subtitle -->
                <div class="form-row">
                    <div class="form-group form-group-wide">
                        <label for="bannerTitle" class="required filter-label" data-i18n="form.fields.title.label">
                            <?= __t('form.fields.title.label', 'Title') ?>
                        </label>
                        <input type="text" id="bannerTitle" name="title" class="form-control" required
                               data-i18n-placeholder="form.fields.title.placeholder"
                               placeholder="<?= __t('form.fields.title.placeholder', 'Enter banner title') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.title.required">
                            <?= __t('form.fields.title.required', 'Title is required') ?>
                        </div>
                    </div>
                    <div class="form-group form-group-wide">
                        <label for="bannerSubtitle" class="filter-label" data-i18n="form.fields.subtitle.label">
                            <?= __t('form.fields.subtitle.label', 'Subtitle') ?>
                        </label>
                        <input type="text" id="bannerSubtitle" name="subtitle" class="form-control"
                               data-i18n-placeholder="form.fields.subtitle.placeholder"
                               placeholder="<?= __t('form.fields.subtitle.placeholder', 'Enter subtitle (optional)') ?>">
                    </div>
                </div>

                <!-- Row 2: Link URL + Link Text -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerLinkUrl" class="filter-label" data-i18n="form.fields.link_url.label">
                            <?= __t('form.fields.link_url.label', 'Link URL') ?>
                        </label>
                        <input type="url" id="bannerLinkUrl" name="link_url" class="form-control"
                               data-i18n-placeholder="form.fields.link_url.placeholder"
                               placeholder="<?= __t('form.fields.link_url.placeholder', 'https://example.com') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bannerLinkText" class="filter-label" data-i18n="form.fields.link_text.label">
                            <?= __t('form.fields.link_text.label', 'Button Text') ?>
                        </label>
                        <input type="text" id="bannerLinkText" name="link_text" class="form-control"
                               data-i18n-placeholder="form.fields.link_text.placeholder"
                               placeholder="<?= __t('form.fields.link_text.placeholder', 'e.g. Shop Now') ?>">
                    </div>
                </div>

                <!-- Row 3: Position + Status + Sort -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerPosition" class="filter-label" data-i18n="form.fields.position.label">
                            <?= __t('form.fields.position.label', 'Position') ?>
                        </label>
                        <select id="bannerPosition" name="position" class="form-control">
                            <option value="homepage_main"><?=      __t('form.fields.position.options.homepage_main',      'Homepage – Main') ?></option>
                            <option value="homepage_secondary"><?= __t('form.fields.position.options.homepage_secondary', 'Homepage – Secondary') ?></option>
                            <option value="category_top"><?=       __t('form.fields.position.options.category_top',       'Category Top') ?></option>
                            <option value="product_sidebar"><?=    __t('form.fields.position.options.product_sidebar',    'Product Sidebar') ?></option>
                            <option value="footer"><?=             __t('form.fields.position.options.footer',             'Footer') ?></option>
                            <option value="popup"><?=              __t('form.fields.position.options.popup',              'Popup') ?></option>
                            <option value="other"><?=              __t('form.fields.position.options.other',              'Other') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bannerIsActive" class="filter-label" data-i18n="form.fields.status.label">
                            <?= __t('form.fields.status.label', 'Status') ?>
                        </label>
                        <select id="bannerIsActive" name="is_active" class="form-control">
                            <option value="1"><?= __t('form.fields.status.active',   'Active') ?></option>
                            <option value="0"><?= __t('form.fields.status.inactive', 'Inactive') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bannerSortOrder" class="filter-label" data-i18n="form.fields.sort_order.label">
                            <?= __t('form.fields.sort_order.label', 'Sort Order') ?>
                        </label>
                        <input type="number" id="bannerSortOrder" name="sort_order"
                               class="form-control" value="0">
                    </div>
                </div>

                <!-- Row 4: Colors + Button Style -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerBgColor" class="filter-label" data-i18n="form.fields.background_color.label">
                            <?= __t('form.fields.background_color.label', 'Background Color') ?>
                        </label>
                        <input type="color" id="bannerBgColor" name="background_color"
                               class="form-control form-control-color" value="#FFFFFF">
                    </div>
                    <div class="form-group">
                        <label for="bannerTextColor" class="filter-label" data-i18n="form.fields.text_color.label">
                            <?= __t('form.fields.text_color.label', 'Text Color') ?>
                        </label>
                        <input type="color" id="bannerTextColor" name="text_color"
                               class="form-control form-control-color" value="#000000">
                    </div>
                    <div class="form-group">
                        <label for="bannerButtonStyle" class="filter-label" data-i18n="form.fields.button_style.label">
                            <?= __t('form.fields.button_style.label', 'Button Style') ?>
                        </label>
                        <select id="bannerButtonStyle" name="button_style" class="form-control">
                            <option value=""><?= __t('form.fields.button_style.none', '— None —') ?></option>
                            <?php foreach ($GLOBALS['ADMIN_UI']['theme']['button_styles'] ?? [] as $bs): ?>
                            <option value="<?= htmlspecialchars($bs['slug'] ?? '', ENT_QUOTES) ?>">
                                <?= htmlspecialchars($bs['name'] ?? $bs['slug'] ?? '', ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 5: Dates -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerStartDate" class="filter-label" data-i18n="form.fields.start_date.label">
                            <?= __t('form.fields.start_date.label', 'Start Date') ?>
                        </label>
                        <input type="datetime-local" id="bannerStartDate" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="bannerEndDate" class="filter-label" data-i18n="form.fields.end_date.label">
                            <?= __t('form.fields.end_date.label', 'End Date') ?>
                        </label>
                        <input type="datetime-local" id="bannerEndDate" name="end_date" class="form-control">
                    </div>
                </div>

                <!-- Image -->
                <div class="form-group">
                    <label class="filter-label" data-i18n="form.fields.image.label">
                        <?= __t('form.fields.image.label', 'Banner Image') ?>
                    </label>
                    <div class="image-upload-section">
                        <div class="image-upload-row">
                            <img id="bannerImagePreview" src="" alt=""
                                 style="display:none; max-width:200px; max-height:80px; object-fit:cover;
                                        border-radius:6px; border:1px solid var(--border-color,#263044);">
                            <div class="image-upload-btns">
                                <button type="button" id="bannerSelectImageBtn" class="btn btn-sm btn-secondary"
                                        data-i18n="common.select_image">
                                    <i class="fas fa-images" aria-hidden="true"></i>
                                    <?= __t('common.select_image', 'Select Image') ?>
                                </button>
                                <button type="button" id="bannerRemoveImageBtn" class="btn btn-sm btn-danger"
                                        data-i18n="common.remove_image">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                    <?= __t('common.remove_image', 'Remove Image') ?>
                                </button>
                            </div>
                            <div id="bannerImageLinks" class="image-links"></div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h4 class="form-section-title" data-i18n="form.translations.section_title">
                            <?= __t('form.translations.section_title', 'Translations') ?>
                        </h4>
                        <p class="form-section-desc" data-i18n="form.translations.section_desc">
                            <?= __t('form.translations.section_desc', 'English is required. Add other languages as needed.') ?>
                        </p>
                    </div>

                    <div class="translation-panel" data-lang="en">
                        <div class="translation-panel-header">
                            <span class="lang-badge">EN</span>
                            <span data-i18n="form.translations.lang_en"><?= __t('form.translations.lang_en', 'English') ?></span>
                            <span class="badge-required">Required</span>
                        </div>
                        <div class="translation-panel-body">
                            <div class="form-group">
                                <label class="filter-label"><?= __t('form.translations.title_in_lang', 'Title in English') ?></label>
                                <input type="text" id="trans_en_title" name="trans[en][title]"
                                       class="form-control" required
                                       placeholder="<?= __t('form.fields.title.placeholder', 'Enter banner title') ?>">
                                <div class="invalid-feedback"><?= __t('messages.error.en_required', 'English title is required') ?></div>
                            </div>
                            <div class="form-group">
                                <label class="filter-label"><?= __t('form.translations.subtitle_in_lang', 'Subtitle in English') ?></label>
                                <input type="text" id="trans_en_subtitle" name="trans[en][subtitle]"
                                       class="form-control"
                                       placeholder="<?= __t('form.fields.subtitle.placeholder', 'Enter subtitle (optional)') ?>">
                            </div>
                            <div class="form-group">
                                <label class="filter-label"><?= __t('form.translations.link_text_in_lang', 'Button Text in English') ?></label>
                                <input type="text" id="trans_en_link_text" name="trans[en][link_text]"
                                       class="form-control"
                                       placeholder="<?= __t('form.fields.link_text.placeholder', 'e.g. Shop Now') ?>">
                            </div>
                        </div>
                    </div>

                    <div id="bannerTranslations" class="translation-panels"></div>

                    <div class="form-group" style="margin:12px 16px 16px;">
                        <label class="filter-label" for="bannerLangSelect">
                            <?= __t('form.translations.select_lang', 'Select Language') ?>
                        </label>
                        <div class="lang-add-row">
                            <select id="bannerLangSelect" class="form-control">
                                <option value=""><?= __t('form.translations.choose_language', 'Choose language') ?></option>
                            </select>
                            <button type="button" id="bannerAddLangBtn" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <?= __t('form.translations.add_translation', 'Add Translation') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit" id="bannerSaveBtn" class="btn btn-primary">
                        <span id="bannerSaveBtnText" data-i18n="form.buttons.save">
                            <?= __t('form.buttons.save', 'Save') ?>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filters-card">
        <div class="card-body">
            <div class="filters-grid">

                <div class="filter-group">
                    <label class="filter-label" for="bannerSearch" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="search" id="bannerSearch" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search banners...') ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="bannerFilterPosition" data-i18n="filters.position">
                        <?= __t('filters.position', 'Position') ?>
                    </label>
                    <select id="bannerFilterPosition" class="form-control">
                        <option value=""><?=                __t('filters.position_all',                          'All Positions') ?></option>
                        <option value="homepage_main"><?=      __t('form.fields.position.options.homepage_main',      'Homepage – Main') ?></option>
                        <option value="homepage_secondary"><?= __t('form.fields.position.options.homepage_secondary', 'Homepage – Secondary') ?></option>
                        <option value="category_top"><?=       __t('form.fields.position.options.category_top',       'Category Top') ?></option>
                        <option value="product_sidebar"><?=    __t('form.fields.position.options.product_sidebar',    'Product Sidebar') ?></option>
                        <option value="footer"><?=             __t('form.fields.position.options.footer',             'Footer') ?></option>
                        <option value="popup"><?=              __t('form.fields.position.options.popup',              'Popup') ?></option>
                        <option value="other"><?=              __t('form.fields.position.options.other',              'Other') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="bannerFilterStatus" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="bannerFilterStatus" class="form-control">
                        <option value=""><?=  __t('filters.status_options.all',      'All Status') ?></option>
                        <option value="1"><?= __t('filters.status_options.active',   'Active') ?></option>
                        <option value="0"><?= __t('filters.status_options.inactive', 'Inactive') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnRefresh" class="btn btn-secondary"
                                aria-label="<?= __t('banners.retry', 'Refresh') ?>">
                            <i class="fas fa-sync-alt" aria-hidden="true"></i>
                            <span data-i18n="banners.retry"><?= __t('banners.retry', 'Refresh') ?></span>
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
            <div id="bannersLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading"><?= __t('loading', 'Loading...') ?></p>
            </div>

            <!-- Empty -->
            <div id="bannersEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-image" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Banners Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'Start by adding a new banner') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="btnAddBannerEmpty">
                    <?= __t('banners.add_new', 'Add Banner') ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error -->
            <div id="bannersError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title"><?= __t('messages.error.load_failed', 'Failed to load data') ?></h3>
                <p id="bannersErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="retry"><?= __t('retry', 'Retry') ?></button>
            </div>

            <!-- Table -->
            <div id="bannersTableContainer" class="table-responsive" style="display:none;">
                <table id="bannersTable" class="data-table" aria-label="Banners">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">        <?= __t('table.headers.id',         'ID') ?></th>
                            <th data-i18n="table.headers.image">     <?= __t('table.headers.image',      'Image') ?></th>
                            <th data-i18n="table.headers.title">     <?= __t('table.headers.title',      'Title') ?></th>
                            <th data-i18n="table.headers.position">  <?= __t('table.headers.position',   'Position') ?></th>
                            <th data-i18n="table.headers.sort_order"><?= __t('table.headers.sort_order', 'Sort') ?></th>
                            <th data-i18n="table.headers.status">    <?= __t('table.headers.status',     'Status') ?></th>
                            <th data-i18n="table.headers.dates">     <?= __t('table.headers.dates',      'Dates') ?></th>
                            <th data-i18n="table.headers.actions">   <?= __t('table.headers.actions',    'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="bannersTbody"></tbody>
                </table>
            </div>

        </div>
    </div>

</div><!-- /bannersPageContainer -->

<!-- Media Studio Modal — prefix: bnn -->
<div id="bannerMediaStudioOverlay"
     class="bnn-modal-backdrop"
     style="display:none;"
     role="dialog" aria-modal="true"
     aria-labelledby="bannerMediaStudioTitle">
    <div class="bnn-modal-panel bnn-modal-panel--wide">
        <div class="bnn-modal-header">
            <h4 id="bannerMediaStudioTitle">
                <i class="fas fa-images" aria-hidden="true"></i>
                <?= __t('common.select_image', 'Select Image') ?>
            </h4>
            <button type="button" id="bannerMediaStudioClose"
                    class="btn-close-modal icon-btn"
                    aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <iframe id="bannerMediaStudioFrame" class="bnn-modal-frame"
                src="about:blank"
                title="<?= __t('common.select_image', 'Select Image') ?>">
        </iframe>
    </div>
</div>

<!-- Unified BANNERS_CONFIG -->
<script type="text/javascript">
window.BANNERS_CONFIG = {
    apiBase:         <?= json_encode($apiBase,   JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:       <?= json_encode($csrf) ?>,
    lang:            <?= json_encode($lang) ?>,
    dir:             <?= json_encode($dir)  ?>,
    strings:         <?= json_encode($_strings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:       <?= json_encode($canCreate) ?>,
    canEdit:         <?= json_encode($canEdit)   ?>,
    canDelete:       <?= json_encode($canDelete) ?>,
    canViewAll:      <?= json_encode($canViewAll)    ?>,
    canViewOwn:      <?= json_encode($canViewOwn)    ?>,
    canViewTenant:   <?= json_encode($canViewTenant) ?>,
    canEditAll:      <?= json_encode($canEditAll)    ?>,
    canEditOwn:      <?= json_encode($canEditOwn)    ?>,
    canDeleteAll:    <?= json_encode($canDeleteAll)  ?>,
    canDeleteOwn:    <?= json_encode($canDeleteOwn)  ?>,
    isSuperAdmin:    <?= json_encode(is_super_admin()) ?>,
    apiUrl:          <?= json_encode($apiBase . '/banners',       JSON_UNESCAPED_SLASHES) ?>,
    imagesApi:       <?= json_encode($apiBase . '/images',        JSON_UNESCAPED_SLASHES) ?>,
    languagesApi:    <?= json_encode($apiBase . '/languages',     JSON_UNESCAPED_SLASHES) ?>,
    buttonStylesApi: <?= json_encode($apiBase . '/button_styles', JSON_UNESCAPED_SLASHES) ?>,
    imageTypeId:     <?= json_encode($imageTypeId) ?>,
    itemsPerPage:    25,
    tenantId:        <?= json_encode($tenantId) ?>,
    userId:          <?= json_encode(admin_user_id()) ?>
};

/* Legacy globals */
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE   = <?= json_encode($apiBase)   ?>;
window.APP_CONFIG.TENANT_ID  = <?= json_encode($tenantId)  ?>;
window.APP_CONFIG.CSRF_TOKEN = <?= json_encode($csrf)      ?>;
window.APP_CONFIG.USER_ID    = <?= json_encode(admin_user_id()) ?>;
window.USER_LANGUAGE         = <?= json_encode($lang) ?>;
window.USER_DIRECTION        = <?= json_encode($dir)  ?>;
window.CSRF_TOKEN            = <?= json_encode($csrf) ?>;
window.PAGE_PERMISSIONS      = window.BANNERS_CONFIG;
if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/banners.js?v=<?= assetVer('/admin/assets/js/pages/banners.js') ?>"></script>

<script>
(function () {
    var initialized = false;
    var poll;

    function cleanup() {
        clearInterval(poll);
    }

    function tryInit() {
        if (initialized) return;
        if (!window.TRANSLATIONS) return;
        if (!window.Banners || typeof window.Banners.init !== 'function') return;
        initialized = true;
        cleanup();
        var p = window.Banners.init();
        if (p && typeof p.then === 'function') {
            p.catch(function (e) { console.error('[Banners] Init failed:', e); });
        }
    }

    // Flatten nested JSON into dot-notation keys: {table:{headers:{id:"X"}}} → {"table.headers.id":"X"}
    function flattenObj(obj, prefix) {
        var result = {};
        prefix = prefix ? prefix + '.' : '';
        for (var k in obj) {
            if (!obj.hasOwnProperty(k)) continue;
            var val = obj[k];
            if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
                var nested = flattenObj(val, prefix + k);
                for (var nk in nested) result[nk] = nested[nk];
            } else {
                result[prefix + k] = val;
            }
        }
        return result;
    }

    // Load translation file, flatten it, and populate all translation globals
    (function loadI18n() {
        var lang = (window.BANNERS_CONFIG && window.BANNERS_CONFIG.lang) || window.USER_LANGUAGE || 'en';
        var url  = '/languages/Banners/' + encodeURIComponent(lang) + '.json';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) {
                var raw     = data.strings || data;
                var flat    = flattenObj(raw);               // dot-notation keys
                // Store nested version for JS traversal
                window.BANNERS_TRANSLATIONS = raw;
                // Store flat version in TRANSLATIONS and in BANNERS_CONFIG.strings
                window.TRANSLATIONS = window.TRANSLATIONS || {};
                Object.assign(window.TRANSLATIONS, flat);
                if (window.BANNERS_CONFIG) {
                    window.BANNERS_CONFIG.strings = Object.assign({}, window.BANNERS_CONFIG.strings || {}, flat);
                }
                tryInit();
            })
            .catch(function (err) {
                console.warn('[Banners] i18n load failed:', err, '— continuing without translations');
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
            if (!initialized) console.warn('[Banners] init timed out');
        }
    }, 100);
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>