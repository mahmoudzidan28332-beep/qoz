<?php
declare(strict_types=1);

// ============================================================
// Bootstrap — full standalone entry (mirrors countries.php)
// ============================================================
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

// ============================================================
// Dependencies
// ============================================================
require_once API_VERSION_PATH . '/models/delivery_zones/Contracts/DeliveryTrackingRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/delivery_zones/repositories/PdoDeliveryTrackingRepository.php';
require_once API_VERSION_PATH . '/models/delivery_zones/validators/DeliveryTrackingValidator.php';
require_once API_VERSION_PATH . '/models/delivery_zones/services/DeliveryTrackingService.php';
require_once API_VERSION_PATH . '/models/delivery_zones/controllers/DeliveryTrackingController.php';

// ============================================================
// Session & Database
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ============================================================
// Tenant resolution (platform-admin-aware)
// ============================================================
$isPlatformAdmin = function_exists('is_platform_admin') && is_platform_admin();
$tenantId        = resolve_tenant_id();

if ($tenantId === null) {
    if (!$isPlatformAdmin) {
        ResponseFormatter::error('Unauthorized', 401);
        exit;
    }
    $tenantId = 0;
}
$tenantId = (int)$tenantId;

if (!$isPlatformAdmin && $tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

// Platform Admin cross-tenant audit when acting on a specific tenant
if ($isPlatformAdmin && $tenantId > 0 && class_exists('PlatformContext', false)) {
    PlatformContext::logCrossTenantAction(
        sourceTenant: null,
        targetTenant: $tenantId,
        reason: 'Platform Admin — delivery_tracking management'
    );
}

// ============================================================
// Wiring
// ============================================================
$controller = new DeliveryTrackingController(
    new DeliveryTrackingService(
        new PdoDeliveryTrackingRepository($pdo),
        new DeliveryTrackingValidator()
    )
);

// ============================================================
// Request parsing
// ============================================================
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

$uriPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = explode('/', trim($uriPath, '/'));
$id       = null;
foreach ($segments as $seg) {
    if (ctype_digit($seg) && (int)$seg > 0) {
        $id = (int)$seg;
        break;
    }
}

$lang = in_array($_GET['lang'] ?? 'ar', ['ar', 'en'], true) ? ($_GET['lang'] ?? 'ar') : 'ar';

// ============================================================
// Route dispatch
// ============================================================
try {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $item = $controller->get($tenantId, $id, $lang);
                if ($item === null) {
                    ResponseFormatter::error('Tracking record not found', 404);
                    break;
                }
                ResponseFormatter::success($item);
                break;
            }

            $filters = [];
            if (isset($_GET['delivery_order_id']) && ctype_digit((string)$_GET['delivery_order_id'])) {
                $filters['delivery_order_id'] = (int)$_GET['delivery_order_id'];
            }
            if (isset($_GET['provider_id']) && ctype_digit((string)$_GET['provider_id'])) {
                $filters['provider_id'] = (int)$_GET['provider_id'];
            }

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'dt.id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);

            ResponseFormatter::success([
                'items' => $result['items'],
                'meta'  => [
                    'total'       => $result['total'],
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                ],
            ]);
            break;

        case 'POST':
            $data  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Tracking record created successfully', 201);
            break;

        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Tracking record deleted successfully');
                break;
            }
            ResponseFormatter::error('ID required for delete', 400);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', '[DeliveryTracking] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (DatabaseException|\PDOException $e) {
    safe_log('error', '[DeliveryTracking] Database error', [
        'tenant_id' => $tenantId,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', '[DeliveryTracking] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}
