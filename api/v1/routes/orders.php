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

$modelsPath = API_VERSION_PATH . '/models/orders';
require_once $modelsPath . '/repositories/PdoOrdersRepository.php';
require_once $modelsPath . '/services/OrdersService.php';
require_once $modelsPath . '/controllers/OrdersController.php';

// Audit logs
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

$repo = new PdoOrdersRepository($pdo);
$service = new OrdersService($repo);
$controller = new OrdersController($service);

$user     = $_SESSION['user'] ?? [];
$tenantId = resolve_tenant_id();

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

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

    // URL ID parser
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $pathInfo   = parse_url($requestUri, PHP_URL_PATH);
    $pathParts  = explode('/', trim($pathInfo, '/'));
    $urlId      = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'orders' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    $filters = [
        'user_id'            => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
        'status'             => $_GET['status'] ?? null,
        'payment_status'     => $_GET['payment_status'] ?? null,
        'fulfillment_status' => $_GET['fulfillment_status'] ?? null,
        'order_type'         => $_GET['order_type'] ?? null,
        'created_from'       => $_GET['created_from'] ?? null,
        'created_to'         => $_GET['created_to'] ?? null,
        'language'           => $language,
        'tenant_id'          => $tenantId,
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['order_number'])) {
                $order = $controller->getByOrderNumber($tenantId, $_GET['order_number']);
                ResponseFormatter::success($order);
                break;
            }

            $getId = $urlId ?? (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);
            if ($getId) {
                $item = $controller->get($tenantId, $getId, $language);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $language);
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

        case 'POST':
            require_once $modelsPath . '/validators/OrdersValidator.php';
            $validator = new App\Models\Orders\Validators\OrdersValidator();
            $validator->validate($data, false);
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }
            require_once $modelsPath . '/validators/OrdersValidator.php';
            $validator = new App\Models\Orders\Validators\OrdersValidator();
            $validator->validate($data, true);
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing order ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete($tenantId, (int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'orders.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'orders.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'orders.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
