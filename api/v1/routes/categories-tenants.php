<?php
declare(strict_types=1);

/**
 * Route: /api/categories-tenants
 *
 * Full-featured API endpoint for tenant categories.
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


require_once API_VERSION_PATH . '/models/categories/repositories/PdoTenantCategoriesRepository.php';
require_once API_VERSION_PATH . '/models/categories/services/TenantCategoriesService.php';
require_once API_VERSION_PATH . '/models/categories/controllers/TenantCategoriesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure database connection
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// Initialize repository, service, and controller
$repo = new PdoTenantCategoriesRepository($pdo);
$service = new TenantCategoriesService($repo);
$controller = new TenantCategoriesController($service);
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
        : 0; // 0 means "all tenants"
}

// ALWAYS set TenantContext to satisfy repository fail-fast checks
TenantContext::set((int)($effectiveTenantId ?? 0));

// ================================
// Handle request
// ================================

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$data = $raw ? json_decode($raw, true) : [];

// Extract ID from URL path if present
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$id = null;
$action = null;

if (preg_match('/\/categories-tenants\/(\d+)/', $requestUri, $matches)) {
    $id = (int)$matches[1];
}
// Detect sub-action: /categories-tenants/sync
if (preg_match('/\/categories-tenants\/(sync)/', $requestUri, $matches)) {
    $action = $matches[1];
}

try {
    // ─── Sync endpoint ───────────────────────────────────────────────
    if ($action === 'sync' && $method === 'POST') {
        // Force tenant_id from session — never trust payload unless platform admin
        if (!($isPlatformAdmin && $tenantId === null) || !isset($data['tenant_id'])) {
            $data['tenant_id'] = $tenantId;
        }
        $result = $controller->sync($data);
        ResponseFormatter::success($result, 'Categories synced successfully');
        exit;
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            $idGet = isset($_GET['id']) ? (int)$_GET['id'] : $id;

            if ($idGet) {
                $item = $controller->get($idGet);
                if ($item) {
                    // Verify tenant ownership (Bypass for true platform admin)
                    if (!($isPlatformAdmin && $tenantId === null) && (int)($item['tenant_id'] ?? 0) !== $tenantId) {
                        ResponseFormatter::error('Access denied', 403);
                        exit;
                    }
                    ResponseFormatter::success([$item]);
                } else {
                    ResponseFormatter::error('Not found', 404);
                }
            } else {
                // Force effectiveTenantId in $_GET so controller reads it
                $_GET['tenant_id'] = (string)($effectiveTenantId ?? 0);
                
                $result = $controller->list();
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            // Force tenant_id from session — never trust payload unless platform admin
            if (!($isPlatformAdmin && $tenantId === null) || !isset($data['tenant_id'])) {
                $data['tenant_id'] = $tenantId;
            }

            if (empty($data['category_id'])) {
                throw new InvalidArgumentException('category_id is required');
            }
            $created = $controller->create($data);
            ResponseFormatter::success($created, 'Tenant Category created');
            break;

        case 'PUT':
            $idPut = isset($data['id']) ? (int)$data['id'] : $id;
            if (!$idPut) {
                throw new InvalidArgumentException('ID is required for update');
            }

            // Verify ownership (Bypass for true platform admin)
            if (!($isPlatformAdmin && $tenantId === null)) {
                $item = $controller->get($idPut);
                if (!$item || (int)($item['tenant_id'] ?? 0) !== $tenantId) {
                    ResponseFormatter::error('Access denied', 403);
                    break;
                }
                // Strip tenant_id from payload for regular users
                unset($data['tenant_id']);
            }
            
            $data['id'] = $idPut;
            
            // Toggle if only id and is_active are sent (2 fields)
            if (isset($data['is_active']) && count($data) === 2) {
                $toggled = $controller->toggleStatus($data);
                ResponseFormatter::success($toggled, 'Status toggled');
            } else {
                $updated = $controller->update($data);
                ResponseFormatter::success($updated, 'Tenant Category updated');
            }
            break;

        case 'DELETE':
            $idDel = isset($data['id']) ? (int)$data['id'] : $id;
            if (!$idDel) {
                throw new InvalidArgumentException('ID is required for deletion');
            }

            // Verify ownership (Bypass for true platform admin)
            if (!($isPlatformAdmin && $tenantId === null)) {
                $item = $controller->get($idDel);
                if (!$item || (int)($item['tenant_id'] ?? 0) !== $tenantId) {
                    ResponseFormatter::error('Access denied', 403);
                    break;
                }
            }

            $controller->delete(['id' => $idDel]);
            ResponseFormatter::success(['deleted' => true], 'Tenant Category deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'categories_tenants.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'categories_tenants.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 404);
} catch (\Throwable $e) {
    safe_log('critical', 'categories_tenants.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}