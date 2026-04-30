<?php
declare(strict_types=1);

/**
 * Route: /api/tenant_users
 *
 * Handles list/get/create/update/delete for tenant_users.
 * 100% tenant isolation — every operation is scoped to $tenantId from resolve_tenant_id().
 */
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$sharedPath = $baseDir . '/shared/core';
require_once $sharedPath . '/BaseRepository.php';
require_once $sharedPath . '/BaseService.php';
require_once $sharedPath . '/BaseController.php';
require_once $sharedPath . '/TenantContext.php';
require_once $sharedPath . '/QueryGuard.php';
require_once $sharedPath . '/BasePolicy.php';


$modelsPath = API_VERSION_PATH . '/models/tenant_users';
require_once $modelsPath . '/repositories/PdoTenant_usersRepository.php';
require_once $modelsPath . '/validators/Tenant_usersValidator.php';
require_once $modelsPath . '/services/Tenant_usersService.php';
require_once $modelsPath . '/controllers/Tenant_usersController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoTenant_usersRepository($pdo);
$validator  = new Tenant_usersValidator();
$service    = new Tenant_usersService($repo, $validator);
$controller = new Tenant_usersController($service);

// ================================
// Tenant & Auth check
// ================================
$isSuperAdmin    = is_super_admin();
$isPlatformAdmin = is_platform_admin();
$tenantId        = resolve_tenant_id();

if ($tenantId === null && !$isPlatformAdmin) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

// ⚠️ SECURITY LOGIC:
// - True Platform Admin (no tenant context) -> Can see all (0) or filter by ?tenant_id
// - Tenant User/Admin (has tenant context) -> MUST be locked to their $tenantId
$effectiveTenantId = $tenantId;

if ($isPlatformAdmin && $tenantId === null) {
    // True platform admin with no specific tenant attached
    $effectiveTenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) 
        ? (int)$_GET['tenant_id'] 
        : 0;
}

// ALWAYS set TenantContext to satisfy repository fail-fast checks
TenantContext::set((int)($effectiveTenantId ?? 0));

if ($tenantId !== null) {
    TenantContext::set($tenantId);
}

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = $raw ? json_decode($raw, true) : [];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Single record by ?id=
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                // For True Platform Admin, we pass 0 to repository to bypass ownership check
                // For everyone else, we pass their resolved $tenantId to enforce ownership
                $scopedTenantId = ($isPlatformAdmin && $tenantId === null) ? 0 : $tenantId;
                $row = $controller->get($scopedTenantId, (int)$_GET['id']);
                ResponseFormatter::success($row);
            } else {
                // List with filters and pagination
                $query = $_GET ?? [];
                // Force tenant scope (cannot be bypassed by passing tenant_id in GET)
                $query['tenant_id'] = $effectiveTenantId;
                $result = $controller->list($effectiveTenantId, $query);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            // For true platform admins, they can specify target tenant_id
            if ($isPlatformAdmin && $tenantId === null && isset($data['tenant_id'])) {
                $targetTenantId = (int)$data['tenant_id'];
            } else {
                // Everyone else is locked to their own tenant
                $targetTenantId = $tenantId;
            }
            
            $data['tenant_id'] = $targetTenantId;
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            
            $row = $controller->create($targetTenantId, $data, $actingUserId);
            ResponseFormatter::success($row, 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing ID for update', 400);
                break;
            }
            
            // Verify ownership if not a platform admin
            if (!$isPlatformAdmin || $tenantId !== null) {
                // This will throw 404/403 if user doesn't own this record
                $controller->get($tenantId, (int)$data['id']);
                // Prevent tenant hopping via update
                unset($data['tenant_id']);
            }
            
            $targetTenantId = ($isPlatformAdmin && $tenantId === null)
                ? (int)($data['tenant_id'] ?? 0)
                : $tenantId;
                
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $row = $controller->update($targetTenantId, (int)$data['id'], $data, $actingUserId);
            ResponseFormatter::success($row);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
            if ($id <= 0) {
                ResponseFormatter::error('Missing ID for delete', 400);
                break;
            }
            
            // Ownership check
            if (!$isPlatformAdmin || $tenantId !== null) {
                $controller->get($tenantId, $id);
            }
            
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $scopedTenantId = ($isPlatformAdmin && $tenantId === null) ? 0 : $tenantId;
            
            $controller->delete($scopedTenantId, $id, $actingUserId);
            ResponseFormatter::success(['success' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'tenant_users.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'tenant_users.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 404);
} catch (DatabaseException|\PDOException $e) {
    safe_log('error', 'tenant_users.db_error', [
        'message'     => $e->getMessage(),
        'file'        => $e->getFile(),
        'line'        => $e->getLine(),
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    ]);
    ResponseFormatter::error('Database error', 500);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('critical', 'tenant_users.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
