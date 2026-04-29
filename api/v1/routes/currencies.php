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

$modelsPath = API_VERSION_PATH . '/models/currencies';
require_once $modelsPath . '/repositories/PdoCurrenciesRepository.php';
require_once $modelsPath . '/validators/CurrenciesValidator.php';
require_once $modelsPath . '/services/CurrenciesService.php';
require_once $modelsPath . '/controllers/CurrenciesController.php';

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

$repo       = new PdoCurrenciesRepository($pdo);
$validator  = new CurrenciesValidator();
$service    = new CurrenciesService($repo, $validator);
$controller = new CurrenciesController($service);

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
            $code = $_GET['code'] ?? null;
            if ($code) {
                ResponseFormatter::success($controller->get($code));
            } else {
                ResponseFormatter::success($controller->list());
            }
            break;

        case 'POST':
            $data = array_intersect_key($data, array_flip(['code', 'name', 'symbol', 'symbol_position', 'decimal_places', 'is_active']));
            ResponseFormatter::success($controller->create($data));
            break;

        case 'PUT':
            $data = array_intersect_key($data, array_flip(['code', 'name', 'symbol', 'symbol_position', 'decimal_places', 'is_active'])) + (isset($data['id']) ? ['id' => $data['id']] : []);
            ResponseFormatter::success($controller->update($data));
            break;

        case 'DELETE':
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'currencies.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'currencies.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'currencies.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}