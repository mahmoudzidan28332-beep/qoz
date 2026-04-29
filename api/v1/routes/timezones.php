<?php
declare(strict_types=1);

/**
 * API Route: Timezones
 *
 * Supports:
 *  - GET  /api/timezones                      -> list all
 *  - GET  /api/timezones/{id}                 -> get single by id (numeric id)
 *  - GET  /api/timezones/{timezone}           -> get single by timezone string (e.g. UTC)
 *  - GET  /api/timezones?timezone=UTC         -> get by timezone (query)
 *  - POST /api/timezones                      -> create
 *  - PUT  /api/timezones                      -> update (accept JSON body with id)
 *  - DELETE /api/timezones                    -> delete (accept JSON body with id)
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

$modelsPath = API_VERSION_PATH . '/models/timezones';
require_once $modelsPath . '/repositories/PdoTimezonesRepository.php';
require_once $modelsPath . '/validators/TimezonesValidator.php';
require_once $modelsPath . '/services/TimezonesService.php';
require_once $modelsPath . '/controllers/TimezonesController.php';

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

$repo       = new PdoTimezonesRepository($pdo);
$validator  = new TimezonesValidator();
$service    = new TimezonesService($repo, $validator);
$controller = new TimezonesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    $raw  = file_get_contents('php://input');
    $data = $raw ? (json_decode($raw, true) ?? []) : [];

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

    // Determine path segment after /api/timezones
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath    = explode('?', $requestUri, 2)[0];
    $routeBase  = '/api/timezones';
    $after      = '';

    $pos = strpos($uriPath, $routeBase);
    if ($pos !== false) {
        $after = substr($uriPath, $pos + strlen($routeBase));
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName && strpos($uriPath, $scriptName) === 0) {
            $after = substr($uriPath, strlen($scriptName));
        }
    }

    $after    = trim($after, "/ \t\n\r\0\x0B");
    $segments = $after === '' ? [] : explode('/', $after);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (!empty($segments[0])) {
                $seg = urldecode($segments[0]);
                if (ctype_digit($seg)) {
                    $item = $controller->show((int)$seg);
                    if (!$item) {
                        ResponseFormatter::error('Timezone not found', 404);
                        exit;
                    }
                    ResponseFormatter::success($item);
                } else {
                    $item = $controller->getByTimezone($seg);
                    if (!$item) {
                        ResponseFormatter::error('Timezone not found', 404);
                        exit;
                    }
                    ResponseFormatter::success($item);
                }
            } elseif (!empty($_GET['id'])) {
                $item = $controller->show((int)$_GET['id']);
                if (!$item) {
                    ResponseFormatter::error('Timezone not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } elseif (!empty($_GET['timezone'])) {
                $item = $controller->getByTimezone($_GET['timezone']);
                if (!$item) {
                    ResponseFormatter::error('Timezone not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } else {
                ResponseFormatter::success($controller->list());
            }
            break;

        case 'POST':
            ResponseFormatter::success($controller->store($data));
            break;

        case 'PUT':
            ResponseFormatter::success($controller->update($data));
            break;

        case 'DELETE':
            ResponseFormatter::success($controller->delete($data));
            break;

        default:
            ResponseFormatter::error('Method not allowed: ' . $method, 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'timezones.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'timezones.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'timezones.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}