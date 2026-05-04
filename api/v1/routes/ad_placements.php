<?php
declare(strict_types=1);

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

$modelsPath = API_VERSION_PATH . '/models/ads';
require_once $modelsPath . '/Contracts/AdPlacementsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAdPlacementsRepository.php';
require_once $modelsPath . '/validators/AdPlacementsValidator.php';
require_once $modelsPath . '/services/AdPlacementsService.php';
require_once $modelsPath . '/controllers/AdPlacementsController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

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

if ($isPlatformAdmin && $tenantId > 0 && class_exists('PlatformContext', false)) {
    PlatformContext::logCrossTenantAction(
        sourceTenant: null,
        targetTenant: $tenantId,
        reason: 'Platform Admin — ad_placements management'
    );
}

try {
    $repo       = new PdoAdPlacementsRepository($pdo);
    $service    = new AdPlacementsService($repo);
    $controller = new AdPlacementsController($service);

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])            : 1;
    $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'status' => $_GET['status'] ?? null,
        'search' => $_GET['search'] ?? null,
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id']);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Ad placement created successfully', 201);
            break;

        case 'PUT':
            $data      = json_decode(file_get_contents('php://input'), true) ?: [];
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Ad placement updated successfully');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, $id);
            ResponseFormatter::success(['deleted' => $deleted], 'Ad placement deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'ad_placements.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (DatabaseException|\PDOException $e) {
    safe_log('error', 'ad_placements.database', [
        'code'  => $e->getCode(),
        'error' => $e->getMessage(),
        'file'  => $e->getFile() . ':' . $e->getLine(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'ad_placements.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}