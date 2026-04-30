<?php
declare(strict_types=1);

// Load bootstrap
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/shared/core/ResponseFormatter.php';
if (file_exists(dirname(__DIR__, 3) . '/admin/includes/admin_context.php')) {
    require_once dirname(__DIR__, 3) . '/admin/includes/admin_context.php';
}
require_once dirname(__DIR__, 2) . '/shared/helpers/safe_helpers.php';
require_once dirname(__DIR__, 2) . '/shared/config/db.php';

// Define API_VERSION_PATH if not set
if (!defined('API_VERSION_PATH')) {
    define('API_VERSION_PATH', dirname(__DIR__, 2) . '/v1');
}

// Load users files
require_once API_VERSION_PATH . '/models/users_account/repositories/PdoUsersRepository.php';
require_once API_VERSION_PATH . '/models/users_account/validators/UsersValidator.php';
require_once API_VERSION_PATH . '/models/users_account/services/UsersService.php';
require_once API_VERSION_PATH . '/models/users_account/controllers/UsersController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    ResponseFormatter::error('Not authenticated', 401);
    return;
}

// Helper: try to extract numeric id from (in order):
// 1) ?id= query param
// 2) PATH_INFO (if provided by server)
// 3) trailing numeric segment of REQUEST_URI
if (!function_exists('extractIdFromRequest')) {
    function extractIdFromRequest(): ?int
    {
        // 1) query param
        if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
            return (int)$_GET['id'];
        }

        // 2) PATH_INFO (fast, if PHP provides)
        if (!empty($_SERVER['PATH_INFO'])) {
            $parts = explode('/', trim((string)$_SERVER['PATH_INFO'], '/'));
            $last = end($parts);
            if (is_numeric($last)) {
                return (int)$last;
            }
        }

        // 3) fallback: parse REQUEST_URI and capture trailing number
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = explode('?', $uri, 2)[0]; // remove query string
        if (preg_match('#/(\d+)(?:/)?$#', $uri, $m)) {
            return (int)$m[1];
        }

        return null;
    }
}

if (!function_exists('resolveUsersResourceType')) {
    function resolveUsersResourceType(): string
    {
        $resourcePerms = function_exists('admin_resource_permissions') ? admin_resource_permissions() : [];
        if (!empty($resourcePerms['users_account'])) {
            return 'users_account';
        }
        if (!empty($resourcePerms['users'])) {
            return 'users';
        }
        return 'users_account';
    }
}


// Pagination
$limit = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filters
$filters = [];
if (!empty($_GET['preferred_language'])) $filters['preferred_language'] = trim($_GET['preferred_language']);
if (!empty($_GET['country_id'])) $filters['country_id'] = (int)$_GET['country_id'];
if (!empty($_GET['city_id'])) $filters['city_id'] = (int)$_GET['city_id'];
if (!empty($_GET['timezone'])) $filters['timezone'] = trim($_GET['timezone']);
if (!empty($_GET['role_id'])) $filters['role_id'] = (int)$_GET['role_id'];
if (!empty($_GET['search'])) $filters['search'] = trim($_GET['search']);

// Accept '0' or '1' strings for is_active; keep as int for downstream code
if (isset($_GET['is_active'])) {
    if ($_GET['is_active'] === '' || $_GET['is_active'] === null) {
        // ignore empty
    } elseif (is_numeric($_GET['is_active'])) {
        $filters['is_active'] = (int)$_GET['is_active'];
    } else {
        // accept boolean-like values
        $filters['is_active'] = ($_GET['is_active'] === 'true' || $_GET['is_active'] === '1') ? 1 : 0;
    }
}

// optional: allow filtering by user_id via query
if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}

$tenantId = function_exists('admin_tenant_id') ? (int)admin_tenant_id() : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0);
$currentUser = function_exists('admin_user') ? admin_user() : ($_SESSION['user'] ?? []);
$currentUserId = (int)($currentUser['id'] ?? 0);
$isSuperAdmin = function_exists('is_super_admin') ? is_super_admin() : false;
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$resourceType = resolveUsersResourceType();
$legacyManageUsers = function_exists('can') ? can('manage_users') : in_array('manage_users', $_SESSION['permissions'] ?? [], true);

$canViewAll = (function_exists('can_view_all') ? can_view_all($resourceType) : false) || $isPlatformAdmin;
$canViewOwn = function_exists('can_view_own') ? can_view_own($resourceType) : false;
$canViewTenant = function_exists('can_view_tenant') ? can_view_tenant($resourceType) : false;
$canCreate = (function_exists('can_create') ? can_create($resourceType) : false) || $legacyManageUsers || $isPlatformAdmin;
$canEditAll = (function_exists('can_edit_all') ? can_edit_all($resourceType) : false) || $legacyManageUsers || $isPlatformAdmin;
$canEditOwn = function_exists('can_edit_own') ? can_edit_own($resourceType) : false;
$canDeleteAll = (function_exists('can_delete_all') ? can_delete_all($resourceType) : false) || $legacyManageUsers || $isPlatformAdmin;
$canDeleteOwn = function_exists('can_delete_own') ? can_delete_own($resourceType) : false;

$canView = $isSuperAdmin || $canViewAll || $canViewTenant || $canViewOwn || $legacyManageUsers || $isPlatformAdmin;
$canEdit = $isSuperAdmin || $canEditAll || $canEditOwn || $legacyManageUsers || $isPlatformAdmin;
$canDelete = $isSuperAdmin || $canDeleteAll || $canDeleteOwn || $legacyManageUsers || $isPlatformAdmin;

if (function_exists('safe_log')) {
    safe_log('debug', 'users_account permission check', [
        'isSuperAdmin' => $isSuperAdmin,
        'canViewAll' => $canViewAll,
        'isPlatformAdmin' => $isPlatformAdmin,
        'legacyManageUsers' => $legacyManageUsers,
        'canView' => $canView,
        'admin_ui_user_id' => $currentUser['id'] ?? null,
        'identity_source' => isset($GLOBALS['ADMIN_IDENTITY']) ? $GLOBALS['ADMIN_IDENTITY']->source() : 'n/a'
    ]);
}

// Create dependencies
$repo = new PdoUsersRepository($pdo);
$validator = new UsersValidator();
$service = new UsersService($repo, $validator);
$controller = new UsersController($service);

// Route the request
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (!$canView) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }

        // Try to detect ID from path or query
        $id = extractIdFromRequest();
        if ($id !== null) {
            if (
                !$isSuperAdmin &&
                !$canViewAll &&
                !$legacyManageUsers &&
                !$isPlatformAdmin &&
                !($canViewOwn && $id === $currentUserId) &&
                !($canViewTenant && $tenantId > 0 && $repo->belongsToTenant($id, $tenantId))
            ) {
                ResponseFormatter::error('Access denied', 403);
                return;
            }
            ResponseFormatter::success($controller->get($id));
            return;
        }

        if (!$isSuperAdmin && !$canViewAll && !$legacyManageUsers && !$isPlatformAdmin) {
            if ($canViewTenant && $tenantId > 0) {
                $filters['tenant_id'] = $tenantId;
            } elseif ($canViewOwn && $currentUserId > 0) {
                $filters['user_id'] = $currentUserId;
            } else {
                ResponseFormatter::error('Access denied', 403);
                return;
            }
        }

        // No ID -> list with pagination & filters
        $list = $controller->list($limit, $offset, $filters);
        $total = $controller->count($filters);
        $data = [
            'items' => $list,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'page' => $page,
                'filters' => $filters
            ]
        ];
        ResponseFormatter::success($data);
        return;
    } elseif ($method === 'POST') {
        if (!$canCreate) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = array_intersect_key($data, array_flip(['username', 'email', 'password', 'preferred_language', 'phone', 'is_active']));
        ResponseFormatter::success($controller->create($data));
        return;
    } elseif ($method === 'PUT') {
        if (!$canEdit) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['id'])) {
            $maybeId = extractIdFromRequest();
            if ($maybeId !== null) {
                $data['id'] = $maybeId;
            }
        }
        $targetId = isset($data['id']) ? (int)$data['id'] : 0;
        if (
            !$isSuperAdmin &&
            !$canEditAll &&
            !$legacyManageUsers &&
            !$isPlatformAdmin &&
            !($canEditOwn && $targetId > 0 && $targetId === $currentUserId)
        ) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        if (
            $targetId > 0 &&
            !$isSuperAdmin &&
            !$canEditAll &&
            !$legacyManageUsers &&
            !$isPlatformAdmin &&
            $targetId !== $currentUserId &&
            (!$tenantId || !$repo->belongsToTenant($targetId, $tenantId))
        ) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        $data = array_intersect_key($data, array_flip(['username', 'email', 'password', 'preferred_language', 'phone', 'is_active'])) + (isset($data['id']) ? ['id' => $data['id']] : []);
        ResponseFormatter::success($controller->update($data));
        return;
    } elseif ($method === 'DELETE') {
        if (!$canDelete) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        // Support id in path too
        if (empty($data['id'])) {
            $maybeId = extractIdFromRequest();
            if ($maybeId !== null) $data['id'] = $maybeId;
        }
        $targetId = isset($data['id']) ? (int)$data['id'] : 0;
        if (
            !$isSuperAdmin &&
            !$canDeleteAll &&
            !$legacyManageUsers &&
            !$isPlatformAdmin &&
            !($canDeleteOwn && $targetId > 0 && $targetId === $currentUserId)
        ) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        if (
            $targetId > 0 &&
            !$isSuperAdmin &&
            !$canDeleteAll &&
            !$legacyManageUsers &&
            !$isPlatformAdmin &&
            $targetId !== $currentUserId &&
            (!$tenantId || !$repo->belongsToTenant($targetId, $tenantId))
        ) {
            ResponseFormatter::error('Access denied', 403);
            return;
        }
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
        return;
    } else {
        ResponseFormatter::error('Method not allowed', 405);
        return;
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'Users route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
        'GET' => $_GET ?? null
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
