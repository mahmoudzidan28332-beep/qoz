<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Riyadh');

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

$modelsPath = API_VERSION_PATH . '/models/payment_methods';
require_once $modelsPath . '/repositories/PdoPaymentMethodsRepository.php';
require_once $modelsPath . '/validators/PaymentMethodsValidator.php';
require_once $modelsPath . '/services/PaymentMethodsService.php';
require_once $modelsPath . '/controllers/PaymentMethodsController.php';

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

$service    = new PaymentMethodsService($pdo);
$controller = new PaymentMethodsController($service);

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
            if (isset($_GET['id'])) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) {
                    ResponseFormatter::error('Not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } else {
                $listFilters = [];
                if (!empty($_GET['search'])) $listFilters['search'] = $_GET['search'];

                $result = $controller->list(
                    isset($_GET['limit']) ? (int)$_GET['limit'] : 100,
                    isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
                    $listFilters,
                    $_GET['order_by'] ?? 'method_name',
                    $_GET['order_dir'] ?? 'ASC'
                );
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = array_intersect_key($data, array_flip(['method_key', 'method_name', 'description', 'gateway_name', 'icon_url', 'config']));
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Created');
            break;

        case 'PUT':
            $data = array_intersect_key($data, array_flip(['method_key', 'method_name', 'description', 'gateway_name', 'icon_url', 'config'])) + (isset($data['id']) ? ['id' => $data['id']] : []);
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->update($id, $data);
            ResponseFormatter::success(['id' => $id], 'Updated');
            break;

        case 'DELETE':
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->delete($id);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'payment_methods.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'payment_methods.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'payment_methods.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}


