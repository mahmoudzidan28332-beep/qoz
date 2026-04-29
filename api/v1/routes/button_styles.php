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

$modelsPath = API_VERSION_PATH . '/models/button_styles';
require_once $modelsPath . '/repositories/PdoButtonStylesRepository.php';
require_once $modelsPath . '/validators/ButtonStylesValidator.php';
require_once $modelsPath . '/services/ButtonStylesService.php';
require_once $modelsPath . '/controllers/ButtonStylesController.php';

$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$user     = $_SESSION['user'] ?? [];
$tenantId = resolve_tenant_id();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : !empty($_SESSION['platform_admin']);

if ($tenantId === null && $isPlatformAdmin) {
    $tenantId = 1; // Fallback to platform tenant
}

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$repo       = new PdoButtonStylesRepository($pdo);
$validator  = new ButtonStylesValidator();
$service    = new ButtonStylesService($repo, $validator);
$controller = new ButtonStylesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = $raw ? (json_decode($raw, true) ?? []) : [];

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    $filters = [
        'id'        => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'language'  => $language,
        'tenant_id' => $tenantId,
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (str_contains($uri, '/button_styles/active')) {
                ResponseFormatter::success($controller->getActive($tenantId));
            } elseif (str_contains($uri, '/button_styles/types')) {
                ResponseFormatter::success($controller->buttonTypes($tenantId));
            } else {
                $id = $_GET['id'] ?? null;
                $slug = $_GET['slug'] ?? null;
                if ($id) {
                    ResponseFormatter::success($controller->getById($tenantId, (int)$id));
                } elseif ($slug) {
                    ResponseFormatter::success($controller->get($tenantId, $slug));
                } else {
                    ResponseFormatter::success($controller->list($tenantId));
                }
            }
            break;

        case 'POST':
            if (str_contains($uri, '/button_styles/bulk')) {
                ResponseFormatter::success($controller->bulkUpdate($tenantId, $data));
            } else {
                ResponseFormatter::success($controller->create($tenantId, $data));
            }
            break;

        case 'PUT':
            ResponseFormatter::success($controller->update($tenantId, $data));
            break;

        case 'DELETE':
            $controller->delete($tenantId, $data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'button_styles.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'button_styles.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'button_styles.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}