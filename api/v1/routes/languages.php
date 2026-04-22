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

$modelsPath = API_VERSION_PATH . '/models/languages';
require_once $modelsPath . '/repositories/PdoLanguagesRepository.php';
require_once $modelsPath . '/validators/LanguagesValidator.php';
require_once $modelsPath . '/services/LanguagesService.php';
require_once $modelsPath . '/controllers/LanguagesController.php';

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

$repo       = new PdoLanguagesRepository($pdo);
$validator  = new LanguagesValidator();
$service    = new LanguagesService($repo, $validator);
$controller = new LanguagesController($service);

try {
    $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw      = file_get_contents('php://input');
    $data     = $raw ? (json_decode($raw, true) ?? []) : [];

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

    safe_log('debug', 'Languages request', [
        'method'    => $method,
        'tenant_id' => $tenantId,
        'data'      => $data,
    ]);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            $perPage   = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
            $getOffset = ($page - 1) * $perPage;

            $listFilters = [];
            if (!empty($_GET['search'])) {
                $listFilters['search'] = trim($_GET['search']);
            }
            if (isset($_GET['is_active'])) {
                $listFilters['is_active'] = (bool)$_GET['is_active'];
            }

            $items = $controller->list($perPage, $getOffset, $listFilters);
            $total = $controller->count($listFilters);

            ResponseFormatter::success([
                'data' => $items,
                'meta' => [
                    'total'     => $total,
                    'per_page'  => $perPage,
                    'page'      => $page,
                    'last_page' => ceil($total / $perPage),
                ]
            ]);
            break;

        case 'POST':
            $result = $controller->create($data);
            ResponseFormatter::success($result);
            break;

        case 'PUT':
            $result = $controller->update($data);
            ResponseFormatter::success($result);
            break;

        case 'DELETE':
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed: ' . $method, 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'languages.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'languages.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'languages.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
