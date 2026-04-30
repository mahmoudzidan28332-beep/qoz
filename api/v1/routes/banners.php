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

$modelsPath = API_VERSION_PATH . '/models/banners';
require_once $modelsPath . '/repositories/PdoBannersRepository.php';
require_once $modelsPath . '/validators/BannersValidator.php';
require_once $modelsPath . '/services/BannersService.php';
require_once $modelsPath . '/controllers/BannersController.php';

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

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$repo       = new PdoBannersRepository($pdo);
$validator  = new BannersValidator();
$service    = new BannersService($repo, $validator);
$controller = new BannersController($service);

// Parse URI to extract id or sub-path
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
$id       = null;
$subPath  = null;

if (count($segments) > 0) {
    $last = end($segments);
    if (is_numeric($last)) {
        $id = (int)$last;
    } elseif ($last !== 'banners') {
        $subPath = $last;
    }
}

try {
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $body    = ($rawBody !== '' && $rawBody !== false) ? (json_decode($rawBody, true) ?? []) : [];

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

    $filters = [
        'id'        => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'language'  => $language,
        'tenant_id' => $tenantId,
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
            http_response_code(204);
            exit;

        case 'GET':
            if ($subPath === 'active') {
                $position = $_GET['position'] ?? 'homepage_main';
                ResponseFormatter::success($controller->getActive($tenantId, $position));
            } elseif ($subPath === 'positions') {
                ResponseFormatter::success($controller->positions($tenantId));
            } elseif ($id !== null) {
                ResponseFormatter::success($controller->get($tenantId, $id));
            } else {
                ResponseFormatter::success($controller->list($tenantId));
            }
            break;

        case 'POST':
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            ResponseFormatter::success($controller->create($tenantId, $body, $actingUserId), 201);
            break;

        case 'PUT':
            if ($id === null && !empty($body['id'])) {
                $id = (int)$body['id'];
            }
            if ($id === null) {
                ResponseFormatter::error('Banner ID required', 400);
                exit;
            }
            $body['id']   = $id;
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            ResponseFormatter::success($controller->update($tenantId, $body, $actingUserId));
            break;

        case 'DELETE':
            if ($id === null && !empty($body['id'])) {
                $id = (int)$body['id'];
            }
            if ($id === null) {
                ResponseFormatter::error('Banner ID required', 400);
                exit;
            }
            $body['id']   = $id;
            $actingUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $controller->delete($tenantId, $body, $actingUserId);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'banners.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'banners.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('critical', 'banners.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}