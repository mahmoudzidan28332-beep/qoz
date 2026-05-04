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
require_once API_VERSION_PATH . '/models/delivery_zones/Contracts/ProviderZoneRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/delivery_zones/repositories/PdoProviderZoneRepository.php';
require_once API_VERSION_PATH . '/models/delivery_zones/validators/ProviderZoneValidator.php';
require_once API_VERSION_PATH . '/models/delivery_zones/services/ProviderZoneService.php';
require_once API_VERSION_PATH . '/models/delivery_zones/controllers/ProviderZoneController.php';

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
        reason: 'Platform Admin — provider_zones management'
    );
}

// ============================================================
// Wiring
// ============================================================
$controller = new ProviderZoneController(
    new ProviderZoneService(
        new PdoProviderZoneRepository($pdo),
        new ProviderZoneValidator()
    )
);

// ============================================================
// Request parsing
// ============================================================
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

// Helper to get composite keys from query params for GET/DELETE
$providerIdInput = isset($_GET['provider_id']) && ctype_digit((string)$_GET['provider_id']) ? (int)$_GET['provider_id'] : null;
$zoneIdInput     = isset($_GET['zone_id']) && ctype_digit((string)$_GET['zone_id']) ? (int)$_GET['zone_id'] : null;

$lang = in_array($_GET['lang'] ?? 'ar', ['ar', 'en'], true) ? ($_GET['lang'] ?? 'ar') : 'ar';

// ============================================================
// Route dispatch
// ============================================================
try {
    switch ($method) {
        case 'GET':
            // If both keys present, find specific assignment
            if ($providerIdInput !== null && $zoneIdInput !== null) {
                $item = $controller->get($tenantId, $providerIdInput, $zoneIdInput, $lang);
                if ($item === null) {
                    ResponseFormatter::error('Assignment not found', 404);
                    break;
                }
                ResponseFormatter::success($item);
                break;
            }

            // Otherwise list
            $filters = [];
            if ($providerIdInput !== null) $filters['provider_id'] = $providerIdInput;
            if ($zoneIdInput !== null) $filters['zone_id'] = $zoneIdInput;
            if (isset($_GET['is_active'])) $filters['is_active'] = $_GET['is_active'];

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'pz.provider_id';
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
            $data    = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $created = $controller->create($tenantId, $data);
            ResponseFormatter::success(['created' => $created], 'Zone assigned to provider successfully', 201);
            break;

        case 'PUT':
            $data = json_decode((string)file_get_contents('php://input'), true) ?? [];

            // Require composite key in body
            if (empty($data['provider_id']) || empty($data['zone_id'])) {
                ResponseFormatter::error('provider_id and zone_id are required in body to update assignment', 422);
                break;
            }

            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Assignment updated successfully');
            break;

        case 'DELETE':
            // Accept keys from Query String
            $pId = $providerIdInput;
            $zId = $zoneIdInput;

            // Or from Body
            if ($pId === null || $zId === null) {
                $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
                if (isset($input['provider_id'])) $pId = (int)$input['provider_id'];
                if (isset($input['zone_id'])) $zId = (int)$input['zone_id'];
            }

            if ($pId === null || $zId === null) {
                ResponseFormatter::error('provider_id and zone_id are required to delete assignment', 400);
                break;
            }

            $deleted = $controller->delete($tenantId, $pId, $zId);
            ResponseFormatter::success(['deleted' => $deleted], 'Assignment removed successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', '[ProviderZones] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (DatabaseException|\PDOException $e) {
    // Handle duplicate entry error for create
    if ($e->getCode() == 23000) {
        ResponseFormatter::error('This provider is already assigned to this zone.', 409);
    } else {
        safe_log('error', '[ProviderZones] Database error', [
            'tenant_id' => $tenantId,
            'code'      => $e->getCode(),
            'error'     => $e->getMessage(),
        ]);
        ResponseFormatter::error('A database error occurred.', 500);
    }
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', '[ProviderZones] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}
