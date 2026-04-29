<?php
declare(strict_types=1);

/**
 * /admin/fragments/addresses.php
 * Production-ready Addresses Management
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded  = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment  = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// AUTH
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment ?? false) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT (SECURE - IDENTITY IS SYSTEM CONTROLLED)
// ════════════════════════════════════════════════════════════
$user = admin_user();
if (!$user) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$isSuperAdmin = function_exists('is_super_admin') && is_super_admin();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';

// Language (safe fallback only)
$lang = $_GET['lang'] ?? (function_exists('admin_lang') ? admin_lang() : 'ar');
$dir  = in_array($lang, ['ar','he','fa','ur'], true) ? 'rtl' : 'ltr';

// CSRF
$csrf = function_exists('admin_csrf') ? admin_csrf() : bin2hex(random_bytes(16));

// 🔒 SECURITY: Identity is system-controlled
$tenantId = (int) admin_tenant_id();

// Permissions
$canView   = $isPlatformAdmin || (function_exists('can') && can('manage_addresses'));
$canCreate = $canView;
$canEdit   = $canView;
$canDelete = $canView;

// Elevated fields (ownership etc)
$canEditAllFields = $isPlatformAdmin || $isSuperAdmin;

// ════════════════════════════════════════════════════════════
// FILTERS (Platform Admin only)
// ════════════════════════════════════════════════════════════
$selectedTenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : ($isPlatformAdmin ? 0 : $tenantId);

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
$_addrStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/Addresses/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_addrStrings = $_json['strings'];
    }
}

if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        global $_addrStrings;
        $keys = explode('.', $key);
        $val = $_addrStrings;
        foreach ($keys as $k) {
            if (is_array($val) && isset($val[$k])) {
                $val = $val[$k];
            } else {
                return $fallback ?: $key;
            }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}

// API BASE
$apiBase = '/api';
?>

<?php if ($isFragment): ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="/admin/assets/css/admin_framework.css?v=<?= assetVer() ?>">
<link rel="stylesheet" href="/admin/assets/css/pages/addresses.css?v=<?= assetVer() ?>">
</head>
<body dir="<?= htmlspecialchars($dir) ?>" style="margin:0;padding:0;">
<?php endif; ?>

<meta data-page="addresses">

<div class="page-container" id="addressesPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title"><?= __t('title', 'Addresses') ?></h1>
            <p class="page-subtitle"><?= __t('subtitle', 'Manage addresses') ?></p>
        </div>

        <div class="page-header-actions">
            <?php if ($isPlatformAdmin): ?>
            <div class="filter-group">
                <label><?= __t('filter_by_tenant', 'Filter by Tenant') ?>:</label>
                <input type="number" id="globalTenantFilter" class="form-control" style="width:100px; display:inline-block;" placeholder="0 = All" value="<?= $selectedTenantId ?>">
            </div>
            <?php endif; ?>

            <?php if ($canCreate): ?>
            <button id="btnAddAddress" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <?= __t('add_address', 'Add Address') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form -->
    <div class="card addr-form-card" id="addressFormCard" style="display:none; margin-bottom: 24px;">
        <div class="card-header">
            <h3 id="addressFormTitle"><?= __t('add_address', 'Add Address') ?></h3>
            <button type="button" id="btnCloseForm" class="btn-close-form">&times;</button>
        </div>

        <div class="card-body">
            <form id="addressForm" novalidate>

                <input type="hidden" name="id" id="addressId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                
                <?php if ($isPlatformAdmin): ?>
                <div class="form-group">
                    <label><?= __t('tenant_id', 'Tenant ID') ?> <span class="required-star">*</span></label>
                    <input type="number" name="tenant_id" id="formTenantId" class="form-control" value="<?= $tenantId ?>" required>
                </div>
                <?php else: ?>
                <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
                <?php endif; ?>

                <?php if (!$canEditAllFields): ?>
                <input type="hidden" name="owner_type" id="ownerTypeHidden" value="user">
                <input type="hidden" name="owner_id" id="ownerIdHidden" value="<?= (int)($user['id'] ?? 0) ?>">
                <?php else: ?>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('owner_type', 'Owner Type') ?> <span class="required-star">*</span></label>
                        <select name="owner_type" id="ownerTypeSelect" class="form-control" required>
                            <option value="user"><?= __t('user', 'User') ?></option>
                            <option value="entity"><?= __t('entity', 'Entity') ?></option>
                        </select>
                    </div>
                    <div class="form-group" id="ownerIdGroup">
                        <label><?= __t('owner_id', 'Owner ID') ?> <span class="required-star">*</span></label>
                        <!-- Shown when owner_type = user -->
                        <input type="number" name="owner_id" id="ownerIdInput" class="form-control" required min="1">
                        <!-- Shown when owner_type = entity — populated by JS with tenant-scoped entities -->
                        <select name="owner_id" id="ownerEntitySelect" class="form-control" style="display:none" disabled>
                            <option value=""><?= __t('select_entity', 'Select entity...') ?></option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group" style="position: relative;">
                        <label><?= __t('country', 'Country') ?> <span class="required-star">*</span></label>
                        <select id="countrySelect" name="country_id" class="form-control" required style="width: 100%;">
                            <option value=""><?= __t('select', 'Select...') ?></option>
                        </select>
                    </div>

                    <div class="form-group" style="position: relative;">
                        <label><?= __t('city', 'City') ?> <span class="required-star">*</span></label>
                        <select id="citySelect" name="city_id" class="form-control" required disabled style="width: 100%;">
                            <option value=""><?= __t('select', 'Select...') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><?= __t('address_line1', 'Address Line 1') ?> <span class="required-star">*</span></label>
                    <input type="text" name="address_line1" class="form-control" required>
                </div>

                <div class="form-group">
                    <label><?= __t('address_line2', 'Address Line 2') ?></label>
                    <input type="text" name="address_line2" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('postal_code', 'Postal Code') ?></label>
                        <input type="text" name="postal_code" class="form-control">
                    </div>

                    <div class="form-group">
                        <label><?= __t('is_primary', 'Primary Address') ?></label>
                        <select name="is_primary" class="form-control">
                            <option value="0"><?= __t('no', 'No') ?></option>
                            <option value="1"><?= __t('yes', 'Yes') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= __t('save', 'Save') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAddress" class="btn btn-danger addr-delete-btn" style="display:none">
                        <i class="fas fa-trash"></i> <?= __t('delete', 'Delete') ?>
                    </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <table class="data-table" id="addressesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($isPlatformAdmin): ?>
                        <th><?= __t('tenant', 'Tenant') ?></th>
                        <?php endif; ?>
                        <th><?= __t('owner', 'Owner') ?></th>
                        <th><?= __t('country', 'Country') ?></th>
                        <th><?= __t('city', 'City') ?></th>
                        <th><?= __t('address', 'Address') ?></th>
                        <th><?= __t('primary', 'Primary') ?></th>
                        <th><?= __t('actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="<?= $isPlatformAdmin ? '8' : '7' ?>" style="text-align:center"><?= __t('loading', 'Loading...') ?></td></tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>

</div>

<script>
window.ADDRESSES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/addresses',
    countriesApi: '<?= $apiBase ?>/countries',
    citiesApi: '<?= $apiBase ?>/cities',
    entitiesApi: '<?= $apiBase ?>/entities',
    tenantId: <?= $tenantId ?>,
    lang: '<?= addslashes($lang) ?>',
    csrf: '<?= addslashes($csrf) ?>',
    isPlatformAdmin: <?= json_encode($isPlatformAdmin) ?>,
    canEditAllFields: <?= json_encode($canEditAllFields) ?>,
    permissions: {
        canCreate: <?= json_encode($canCreate) ?>,
        canEdit: <?= json_encode($canEdit) ?>,
        canDelete: <?= json_encode($canDelete) ?>
    },
    strings: <?= json_encode($_addrStrings, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer() ?>"></script>
<script src="/admin/assets/js/pages/addresses.js?v=<?= assetVer() ?>"></script>
</body>
</html>
<?php else: ?>
<script src="/admin/assets/js/pages/addresses.js?v=<?= assetVer() ?>"></script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
