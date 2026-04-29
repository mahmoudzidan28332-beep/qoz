<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/authorize.php';
require_once $baseDir . '/shared/config/db.php';

// ================================
// Load shared base classes FIRST
// ================================
$sharedPath = $baseDir . '/shared/core';
require_once $sharedPath . '/BaseRepository.php';
require_once $sharedPath . '/BaseService.php';
require_once $sharedPath . '/BaseController.php';
require_once $sharedPath . '/TenantContext.php';
require_once $sharedPath . '/QueryGuard.php';
require_once $sharedPath . '/BasePolicy.php';

// ================================
// Load model files
// ================================
$modelsPath = API_VERSION_PATH . '/models/addresses';
require_once $modelsPath . '/repositories/AddressesRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAddressesRepository.php';
require_once $modelsPath . '/validators/AddressesValidator.php';
require_once $modelsPath . '/services/AddressesService.php';
require_once $modelsPath . '/controllers/AddressesController.php';

// ================================
// Load AddressPolicy
// ================================
$policiesPath = API_VERSION_PATH . '/models/addresses/policies';
require_once $policiesPath . '/AddressPolicy.php';

// Audit logs
$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

// ================================
// Session
// ================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================
// DB
// ================================
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ================================
// Init layers
// ================================
$repo       = new PdoAddressesRepository($pdo);
$service    = new AddressesService($repo);
$controller = new AddressesController($service);

// ================================
// Tenant & Auth check
// ================================
$user            = $_SESSION['user'] ?? [];
$isPlatformAdmin = is_platform_admin();
$effectiveTenantId = resolve_tenant_id();

// Platform Admin defaults to 0 (Global View) if no specific tenant is requested
if ($isPlatformAdmin && ($effectiveTenantId === null || $effectiveTenantId === 0)) {
    $effectiveTenantId = 0;
}

// 🔒 SECURITY: Enforce TenantContext
TenantContext::set($effectiveTenantId);

// ================================
// Whitelisted ORDER BY options
// ================================
$allowedOrderBy = ['a.id', 'a.owner_id', 'a.owner_type', 'a.city_id',
                   'a.country_id', 'a.is_primary', 'a.created_at', 'a.updated_at'];

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = $raw ? (json_decode($raw, true) ?? []) : [];

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;

    // Whitelist ORDER BY to prevent SQL injection.
    $rawOrderBy  = $_GET['order_by']  ?? 'a.id';
    $orderBy     = in_array($rawOrderBy, $allowedOrderBy, true) ? $rawOrderBy : 'a.id';
    $rawOrderDir = strtoupper($_GET['order_dir'] ?? 'DESC');
    $orderDir    = $rawOrderDir === 'ASC' ? 'ASC' : 'DESC';

    $language = $_GET['language'] ?? $_GET['lang'] ?? 'ar';

    // ================================
    // Filters
    // ================================
    $filters = [
        'id'         => isset($_GET['id'])         ? (int)$_GET['id']         : null,
        'owner_type' => $_GET['owner_type']        ?? null,
        'owner_id'   => isset($_GET['owner_id'])   ? (int)$_GET['owner_id']   : null,
        'city_id'    => isset($_GET['city_id'])    ? (int)$_GET['city_id']    : null,
        'country_id' => isset($_GET['country_id']) ? (int)$_GET['country_id'] : null,
        'is_primary' => isset($_GET['is_primary']) ? (int)$_GET['is_primary'] : null,
        'language'   => $language,
    ];

    // ================================
    // Parse RESTful ID from URL
    // ================================
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $pathInfo   = parse_url($requestUri, PHP_URL_PATH);
    $pathParts  = explode('/', trim($pathInfo ?? '', '/'));
    $urlId      = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'addresses' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    // ================================
    // Allowed fields for mass-assignment guard
    // ================================
    $allowedFields = [
        'owner_type', 'owner_id',
        'address_line1', 'address_line2',
        'city_id', 'country_id', 'postal_code',
        'latitude', 'longitude', 'is_primary',
    ];

    switch ($method) {

        // ================================
        case 'OPTIONS':
        // ================================
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        // ================================
        case 'GET':
        // ================================
            $getId = $urlId ?? (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);

            if ($getId) {
                $item = $controller->get($getId, $language);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
                $total  = $result['total'];
                ResponseFormatter::success([
                    'data' => $result['items'],
                    'meta' => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                    ],
                ]);
            }
            break;

        // ================================
        case 'POST':
        // ================================
            // Mass-assignment guard: only allowed fields pass through.
            $data = array_intersect_key($data, array_flip($allowedFields));

            $data['owner_type'] = $data['owner_type'] ?? 'user';
            $data['owner_id']   = $data['owner_id']   ?? ($user['id'] ?? null);

            $newId = $controller->create($data);

            AuditLogsService::log(
                'address.create',
                'address',
                (int)$newId,
                null,
                $effectiveTenantId,
                get_user_id(),
                null,
                array_merge($data, ['id' => (int)$newId])
            );

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        // ================================
        case 'PUT':
        // ================================
            $updateId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            if (!$updateId) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }

            // Mass-assignment guard: only allowed fields pass through.
            $data = array_intersect_key($data, array_flip($allowedFields));

            // Fetch old state for audit diff
            $oldState = null;
            try {
                $oldState = $controller->get($updateId, $language);
            } catch (\Throwable $e) {
                safe_log('warning', 'addresses.fetch_old_state', ['error' => $e->getMessage()]);
            }

            $controller->update($updateId, $data);

            AuditLogsService::log(
                'address.update',
                'address',
                $updateId,
                null,
                $effectiveTenantId,
                get_user_id(),
                $oldState,
                array_merge($data, ['id' => $updateId])
            );

            ResponseFormatter::success(['id' => $updateId], 'Updated successfully');
            break;

        // ================================
        case 'DELETE':
        // ================================
            $deleteId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            if (!$deleteId) {
                ResponseFormatter::error('Missing address ID for deletion', 400);
                exit;
            }

            // Fetch old state for audit
            $deletedState = null;
            try {
                $deletedState = $controller->get($deleteId, $language);
            } catch (\Throwable $e) {
                safe_log('warning', 'addresses.fetch_deleted_state', ['error' => $e->getMessage()]);
            }

            $deleted = $controller->delete($deleteId);

            AuditLogsService::log(
                'address.delete',
                'address',
                $deleteId,
                null,
                $effectiveTenantId,
                get_user_id(),
                $deletedState,
                null
            );

            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        // ================================
        default:
        // ================================
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'addresses.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $code     = $e->getCode();
    $httpCode = in_array($code, [400, 403, 404, 422]) ? $code : 400;
    safe_log('error', 'addresses.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Operation failed', $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'addresses.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}