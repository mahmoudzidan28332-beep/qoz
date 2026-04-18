<?php
declare(strict_types=1);

/**
 * /admin/fragments/categories.php
 * Production Version - Uses admin_context for authentication
 * 
 * This fragment manages categories with full translation support,
 * proper RTL/LTR handling, and server-side initial data loading.
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT (CRITICAL - Uses shared admin_context)
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK (MUST be after admin_context)
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated', 'redirect' => '/admin/login.php']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// GET USER & TENANT DATA
// ════════════════════════════════════════════════════════════
$user = admin_user();
$lang = admin_lang();
$isSuperAdmin = is_super_admin();
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$userId = admin_user_id();

// ════════════════════════════════════════════════════════════
// SET RTL/LTR DIRECTION (Server-side - no flash)
// ════════════════════════════════════════════════════════════
$rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
$isRtl = in_array(substr($lang, 0, 2), $rtlLangs);
$dir = $isRtl ? 'rtl' : 'ltr';

// ════════════════════════════════════════════════════════════
// PERMISSIONS (Using can() helpers from admin_context)
// ════════════════════════════════════════════════════════════
$canManage = can('manage_categories');
$canCreate = $canManage || can('categories.create');
$canEdit = $canManage || can('categories.edit');
$canDelete = $canManage || can('categories.delete');
$canViewAll = can_view_all('categories');
$canViewOwn = can_view_own('categories');
$canViewTenant = can_view_tenant('categories');

$canView = $canViewAll || $canViewOwn || $canViewTenant || $isSuperAdmin;

// Access denied for unauthorized users
if (!$canView && !$isSuperAdmin) {
    if ($isFragment) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    exit('Access denied');
}

// ════════════════════════════════════════════════════════════
// LOAD INITIAL DATA (Server-side for fast rendering)
// ════════════════════════════════════════════════════════════
$initialPayload = [
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 0]
];

try {
    $pdo = $GLOBALS['ADMIN_DB'] ?? $GLOBALS['DB'] ?? null;
    if ($pdo instanceof PDO) {
        require_once API_VERSION_PATH . '/models/categories/Contracts/CategoriesRepositoryInterface.php';
        require_once API_VERSION_PATH . '/models/categories/repositories/PdoCategoriesRepository.php';
        require_once API_VERSION_PATH . '/models/categories/validators/CategoriesValidator.php';
        require_once API_VERSION_PATH . '/models/categories/services/CategoriesService.php';
        
        $repo = new PdoCategoriesRepository($pdo);
        $validator = new CategoriesValidator();
        $service = new CategoriesService($repo, $validator);
        
        // Fetch first page of categories with correct method signature:
        // list(?int $tenantId, array $filters = [], string $lang = 'ar'): array
        $filters = [
            'parent_id' => -1,   // show all categories (bypass parent filter)
            'page'      => 1,
            'limit'     => 25,
        ];
        $result = $service->list($tenantId ? (int)$tenantId : null, $filters, $lang);
        if (is_array($result)) {
            $initialPayload['items'] = $result['items'] ?? [];
            $initialPayload['meta'] = $result['meta'] ?? [
                'total' => count($result['items'] ?? []),
                'page' => 1,
                'per_page' => 25,
                'total_pages' => ceil(count($result['items'] ?? []) / 25)
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[Categories] Failed to load initial data: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════
// LOAD TRANSLATIONS
// ════════════════════════════════════════════════════════════
$allowedLangs = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es', 'fa', 'he', 'hi'];
$safeLang = in_array($lang, $allowedLangs, true) ? $lang : 'en';
$translations = [];

$langFile = __DIR__ . '/../../languages/Categories/' . $safeLang . '.json';
if (file_exists($langFile)) {
    $jsonContent = file_get_contents($langFile);
    $translations = json_decode($jsonContent, true);
    if (!is_array($translations)) $translations = [];
}

// Helper function for translations
function _cat(string $key, string $fallback = ''): string
{
    global $translations;
    $parts = explode('.', $key);
    $value = $translations;
    foreach ($parts as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } else {
            return $fallback ?: $key;
        }
    }
    return is_string($value) ? $value : ($fallback ?: $key);
}

// ════════════════════════════════════════════════════════════
// ASSET VERSIONING
// ════════════════════════════════════════════════════════════
if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string)filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
?>

<!-- CSS -->
<link rel="stylesheet" href="/admin/assets/css/pages/categories.css?v=<?= assetVer('/admin/assets/css/pages/categories.css') ?>">

<!-- Page Meta -->
<meta data-page="categories"
      data-i18n-files="/languages/Categories/<?= rawurlencode($safeLang) ?>.json"
      data-assets-css="/admin/assets/css/pages/categories.css"
      data-assets-js="/admin/assets/js/pages/categories.js">

<!-- Page Container with correct direction -->
<div class="page-container" id="categoriesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- ============================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="categories.title"><?= _cat('categories.title', 'Categories') ?></h1>
            <p class="page-subtitle" data-i18n="categories.subtitle"><?= _cat('categories.subtitle', 'Manage product and content categories') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddCategory" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="categories.add_new"><?= _cat('categories.add_new', 'Add Category') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORM CONTAINER (Hidden by default) -->
    <!-- ============================================ -->
    <div id="categoryFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 id="formTitle" class="card-title" data-i18n="form.add_title"><?= _cat('form.add_title', 'Add Category') ?></h3>
            <button type="button" id="btnCloseForm" class="btn btn-secondary" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="categoryForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="catTenantId" name="tenant_id" value="<?= $tenantId ?>">
                
                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="general">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.general"><?= _cat('tabs.general', 'General') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= _cat('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: General -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="catName" class="required" data-i18n="form.fields.name.label">
                                <?= _cat('form.fields.name.label', 'Name') ?>
                            </label>
                            <input type="text" id="catName" name="name" class="form-control" required
                                   data-i18n-placeholder="form.fields.name.placeholder"
                                   placeholder="<?= _cat('form.fields.name.placeholder', 'Enter category name') ?>">
                            <div class="invalid-feedback"><?= _cat('validation.required', 'Required') ?></div>
                        </div>
                        <div class="form-group">
                            <label for="catSlug" data-i18n="form.fields.slug.label">
                                <?= _cat('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="catSlug" name="slug" class="form-control"
                                   data-i18n-placeholder="form.fields.slug.placeholder"
                                   placeholder="<?= _cat('form.fields.slug.placeholder', 'category-slug') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="catParentId" data-i18n="form.fields.parent.label">
                                <?= _cat('form.fields.parent.label', 'Parent Category') ?>
                            </label>
                            <select id="catParentId" name="parent_id" class="form-control">
                                <option value=""><?= _cat('form.fields.parent.none', 'None (Root)') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="catSortOrder" data-i18n="form.fields.sort_order.label">
                                <?= _cat('form.fields.sort_order.label', 'Sort Order') ?>
                            </label>
                            <input type="number" id="catSortOrder" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="catIsActive" data-i18n="form.fields.status.label">
                                <?= _cat('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="catIsActive" name="is_active" class="form-control">
                                <option value="1" data-i18n="form.fields.status.active"><?= _cat('form.fields.status.active', 'Active') ?></option>
                                <option value="0" data-i18n="form.fields.status.inactive"><?= _cat('form.fields.status.inactive', 'Inactive') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="catIsFeatured" data-i18n="form.fields.featured.label">
                                <?= _cat('form.fields.featured.label', 'Featured') ?>
                            </label>
                            <select id="catIsFeatured" name="is_featured" class="form-control">
                                <option value="0" data-i18n="form.fields.featured.no"><?= _cat('form.fields.featured.no', 'No') ?></option>
                                <option value="1" data-i18n="form.fields.featured.yes"><?= _cat('form.fields.featured.yes', 'Yes') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="catDescription" data-i18n="form.fields.description.label">
                                <?= _cat('form.fields.description.label', 'Description') ?>
                            </label>
                            <textarea id="catDescription" name="description" class="form-control" rows="3"
                                      data-i18n-placeholder="form.fields.description.placeholder"
                                      placeholder="<?= _cat('form.fields.description.placeholder', 'Enter description') ?>"></textarea>
                        </div>
                    </div>

                    <!-- Image Selection -->
                    <div class="form-row">
                        <div class="form-group">
                            <label data-i18n="form.fields.image.label"><?= _cat('form.fields.image.label', 'Category Image') ?></label>
                            <div class="image-selector">
                                <img id="catImagePreview" src="/assets/images/no-image.png" alt="Preview" class="image-preview">
                                <input type="hidden" id="catImageId" name="image_id">
                                <button type="button" id="catSelectImageBtn" class="btn btn-secondary">
                                    <i class="fas fa-image"></i>
                                    <span data-i18n="common.select_image"><?= _cat('common.select_image', 'Select Image') ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-section">
                        <h4><i class="fas fa-language"></i> <?= _cat('tabs.translations', 'Translations') ?></h4>
                        <div class="info-hint-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>English</strong> translation fields are in the <strong>General tab</strong>.
                            Use this tab to add translations for other languages (Arabic, French, etc.).
                        </div>
                        <div id="catTranslations" class="translation-panels"></div>
                        <div class="form-group">
                            <label for="catLangSelect" data-i18n="form.translations.select_lang"><?= _cat('form.translations.select_lang', 'Select Language') ?></label>
                            <div class="lang-add-row">
                                <select id="catLangSelect" class="form-control">
                                    <option value=""><?= _cat('form.translations.choose_lang', 'Choose language') ?></option>
                                </select>
                                <button type="button" id="catAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    <span data-i18n="form.translations.add_translation"><?= _cat('form.translations.add_translation', 'Add Translation') ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" id="btnSubmitForm" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= _cat('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" id="btnCancelForm" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <span data-i18n="form.buttons.cancel"><?= _cat('form.buttons.cancel', 'Cancel') ?></span>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteCategory" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="form.buttons.delete"><?= _cat('form.buttons.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FILTERS CARD -->
    <!-- ============================================ -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search"><?= _cat('filters.search', 'Search') ?></label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= _cat('filters.search_placeholder', 'Search...') ?>">
                </div>

                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id"><?= _cat('filters.tenant_id', 'Tenant ID') ?></label>
                    <input type="number" id="tenantFilter" class="form-control" value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= _cat('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="parentFilter" data-i18n="filters.parent_id"><?= _cat('filters.parent_id', 'Parent Category') ?></label>
                    <select id="parentFilter" class="form-control">
                        <option value=""><?= _cat('filters.parent_options.all', 'All Parents') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status"><?= _cat('filters.status', 'Status') ?></label>
                    <select id="statusFilter" class="form-control">
                        <option value=""><?= _cat('filters.status_options.all', 'All Status') ?></option>
                        <option value="1"><?= _cat('filters.status_options.active', 'Active') ?></option>
                        <option value="0"><?= _cat('filters.status_options.inactive', 'Inactive') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="featuredFilter" data-i18n="filters.featured"><?= _cat('filters.featured', 'Featured') ?></label>
                    <select id="featuredFilter" class="form-control">
                        <option value=""><?= _cat('filters.featured_options.all', 'All') ?></option>
                        <option value="1"><?= _cat('filters.featured_options.yes', 'Featured') ?></option>
                        <option value="0"><?= _cat('filters.featured_options.no', 'Not Featured') ?></option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-primary" data-i18n="filters.apply"><?= _cat('filters.apply', 'Apply') ?></button>
                    <button id="btnResetFilters" class="btn btn-secondary" data-i18n="filters.reset"><?= _cat('filters.reset', 'Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RESULTS COUNT -->
    <!-- ============================================ -->
    <div id="resultsCount" class="results-count" style="display:none;">
        <span><i class="fas fa-folder-tree"></i> <span id="resultsCountText"></span></span>
    </div>

    <!-- ============================================ -->
    <!-- TABLE CARD -->
    <!-- ============================================ -->
    <div class="card table-card">
        <div class="card-body">

            <!-- Loading State -->
            <div id="tableLoading" class="loading-state" <?= empty($initialPayload['items']) ? '' : 'style="display:none"' ?>>
                <div class="spinner"></div>
                <p data-i18n="categories.loading"><?= _cat('categories.loading', 'Loading...') ?></p>
            </div>

            <!-- Table Container -->
            <div id="tableContainer" class="table-responsive" style="<?= empty($initialPayload['items']) ? 'display:none' : '' ?>">
                <div class="table-responsive">
                    <table class="data-table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= _cat('table.headers.id', 'ID') ?></th>
                                <?php if ($isSuperAdmin): ?>
                                <th data-i18n="table.headers.tenant"><?= _cat('table.headers.tenant', 'Tenant') ?></th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.image"><?= _cat('table.headers.image', 'Image') ?></th>
                                <th data-i18n="table.headers.name"><?= _cat('table.headers.name', 'Name') ?></th>
                                <th data-i18n="table.headers.slug"><?= _cat('table.headers.slug', 'Slug') ?></th>
                                <th data-i18n="table.headers.parent"><?= _cat('table.headers.parent', 'Parent') ?></th>
                                <th data-i18n="table.headers.sort_order"><?= _cat('table.headers.sort_order', 'Sort Order') ?></th>
                                <th data-i18n="table.headers.status"><?= _cat('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.featured"><?= _cat('table.headers.featured', 'Featured') ?></th>
                                <th data-i18n="table.headers.actions"><?= _cat('table.headers.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        <?php if (!empty($initialPayload['items']) && is_array($initialPayload['items'])): ?>
                            <?php foreach ($initialPayload['items'] as $cat): 
                                $id = htmlspecialchars((string)($cat['id'] ?? ''), ENT_QUOTES);
                                $image = htmlspecialchars((string)($cat['image_url'] ?? ($cat['image'] ?? '')), ENT_QUOTES);
                                $name = htmlspecialchars((string)($cat['name'] ?? ''), ENT_QUOTES);
                                $slug = htmlspecialchars((string)($cat['slug'] ?? ''), ENT_QUOTES);
                                $parent = htmlspecialchars((string)($cat['parent_name'] ?? ($cat['parent_id'] ?: 'Root')), ENT_QUOTES);
                                $sortOrder = (int)($cat['sort_order'] ?? 0);
                                $isActive = !empty($cat['is_active']);
                                $isFeatured = !empty($cat['is_featured']);
                                $tenant = (int)($cat['tenant_id'] ?? $tenantId);
                            ?>
                            <tr>
                                <td><?= $id ?></td>
                                <?php if ($isSuperAdmin): ?>
                                <td><?= $tenant ?></td>
                                <?php endif; ?>
                                <td><?= $image ? "<img src=\"{$image}\" width=\"40\" height=\"40\" class=\"table-image\">" : '<span class="no-image">—</span>' ?></td>
                                <td><strong><?= $name ?></strong></td>
                                <td><?= $slug ?></td>
                                <td><?= $parent ?></td>
                                <td><?= $sortOrder ?></td>
                                <td>
                                    <span class="status-badge status-<?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? _cat('table.status.active', 'Active') : _cat('table.status.inactive', 'Inactive') ?>
                                    </span>
                                </td>
                                <td><?= $isFeatured ? _cat('form.fields.featured.yes', 'Yes') : _cat('form.fields.featured.no', 'No') ?></td>
                                <td class="table-actions">
                                    <?php if ($canEdit): ?>
                                    <button class="btn btn-icon btn-edit" data-action="edit" data-id="<?= $id ?>" title="<?= _cat('table.actions.edit', 'Edit') ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <button class="btn btn-icon btn-delete" data-action="delete" data-id="<?= $id ?>" title="<?= _cat('table.actions.delete', 'Delete') ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing"><?= _cat('pagination.showing', 'Showing') ?></span>
                        <span id="paginationInfo">
                            <?php 
                            $total = $initialPayload['meta']['total'] ?? 0;
                            $perPage = $initialPayload['meta']['per_page'] ?? 25;
                            $start = $total > 0 ? 1 : 0;
                            $end = min($perPage, $total);
                            echo "{$start}–{$end} of {$total}";
                            ?>
                        </span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="<?= empty($initialPayload['items']) ? '' : 'display:none' ?>">
                <div class="empty-icon">📁</div>
                <h3 data-i18n="table.empty.title"><?= _cat('table.empty.title', 'No Categories Found') ?></h3>
                <p data-i18n="table.empty.message"><?= _cat('table.empty.message', 'Start by adding categories') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="emptyAddBtn">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first"><?= _cat('table.empty.add_first', 'Add First Category') ?></span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= _cat('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="categories.retry"><?= _cat('categories.retry', 'Retry') ?></button>
            </div>

        </div>
    </div>

    <!-- ============================================ -->
    <!-- IMAGE STUDIO MODAL -->
    <!-- ============================================ -->
    <div id="mediaStudioModal" class="modal-backdrop" style="display:none">
        <div class="modal-panel modal-panel--wide">
            <div class="modal-header">
                <h3 data-i18n="common.select_image"><?= _cat('common.select_image', 'Select Image') ?></h3>
                <button type="button" id="mediaStudioClose" class="btn-close-modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <iframe id="mediaStudioFrame" src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= rawurlencode($safeLang) ?>&image_type_id=1"></iframe>
            </div>
        </div>
    </div>

</div>

<!-- ============================================ -->
<!-- SCRIPTS & CONFIGURATION -->
<!-- ============================================ -->

<!-- Page Permissions Data -->
<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canView' => $canView,
    'isSuperAdmin' => $isSuperAdmin,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Initial Categories Payload -->
<script id="CATEGORIES_INITIAL_PAYLOAD" type="application/json">
<?= json_encode($initialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>

<!-- Configuration for JavaScript -->
<script>
window.CATEGORIES_CONFIG = {
    apiBase: '/api',
    apiUrl: '/api/categories',
    languagesApi: '/api/languages',
    tenantsApi: '/api/tenants',
    imagesApi: '/api/images',
    csrfToken: <?= json_encode($csrf) ?>,
    lang: <?= json_encode($safeLang) ?>,
    dir: <?= json_encode($dir) ?>,
    tenantId: <?= (int)$tenantId ?>,
    userId: <?= (int)$userId ?>,
    itemsPerPage: 25,
    strings: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
    permissions: {
        canCreate: <?= $canCreate ? 'true' : 'false' ?>,
        canEdit: <?= $canEdit ? 'true' : 'false' ?>,
        canDelete: <?= $canDelete ? 'true' : 'false' ?>,
        isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>
    }
};

window.CATEGORIES_USER_LANG = <?= json_encode($safeLang) ?>;
window.USER_LANGUAGE = <?= json_encode($safeLang) ?>;
</script>

<!-- Load JavaScript -->
<script src="/admin/assets/js/pages/categories.js?v=<?= assetVer('/admin/assets/js/pages/categories.js') ?>"></script>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
