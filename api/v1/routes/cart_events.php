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

$modelsPath = API_VERSION_PATH . '/models/cart_events';
require_once $modelsPath . '/repositories/PdoCartEventsRepository.php';
require_once $modelsPath . '/services/CartEventsService.php';
require_once $modelsPath . '/controllers/CartEventsController.php';

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

$cartEventsRepo    = new PdoCartEventsRepository($pdo);
$cartEventsService = new CartEventsService($cartEventsRepo);
$controller        = new CartEventsController($cartEventsService);

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
        if ($part === 'cart_events' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    // Allowed order columns
    $allowedOrder = ['id', 'cart_id', 'event_type', 'actor_type', 'created_at'];
    if (!in_array($orderBy, $allowedOrder, true)) $orderBy = 'id';
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            $getId = $urlId ?? (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);
            if ($getId) {
                $item = $controller->find($getId);
                ResponseFormatter::success($item ?: null);
                break;
            }

            $where  = ['1=1'];
            $params = [];

            if (isset($_GET['cart_id']) && is_numeric($_GET['cart_id'])) {
                $where[] = 'ce.cart_id = :cart_id';
                $params[':cart_id'] = (int)$_GET['cart_id'];
            }
            if (!empty($_GET['event_type'])) {
                $where[] = 'ce.event_type = :event_type';
                $params[':event_type'] = $_GET['event_type'];
            }
            if (!empty($_GET['actor_type'])) {
                $where[] = 'ce.actor_type = :actor_type';
                $params[':actor_type'] = $_GET['actor_type'];
            }
            if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
                $where[] = 'ce.entity_id = :entity_id';
                $params[':entity_id'] = (int)$_GET['entity_id'];
            }

            $total = $controller->count($where, $params);
            $items = $controller->list($where, $params, $orderBy, $orderDir, $limit, $offset);

            ResponseFormatter::success([
                'data' => $items,
                'meta' => [
                    'total'       => $total,
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                    'from'        => $total > 0 ? $offset + 1 : 0,
                    'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                ],
            ]);
            break;

        case 'POST':
            if (empty($data['cart_id']) || !is_numeric($data['cart_id'])) {
                ResponseFormatter::error('cart_id is required', 422);
                exit;
            }
            if (empty($data['event_type'])) {
                ResponseFormatter::error('event_type is required', 422);
                exit;
            }
            $validActors = ['user', 'admin', 'system'];
            $actorType   = $data['actor_type'] ?? 'system';
            if (!in_array($actorType, $validActors, true)) {
                ResponseFormatter::error('actor_type must be user, admin, or system', 422);
                exit;
            }

            $entityId = isset($data['entity_id']) && is_numeric($data['entity_id'])
                ? (int)$data['entity_id']
                : (isset($_SESSION['entity_id']) ? (int)$_SESSION['entity_id'] : (int)($tenantId ?? 1));

            $newId = $controller->create([
                'entity_id'       => $entityId,
                'cart_id'         => (int)$data['cart_id'],
                'event_type'      => $data['event_type'],
                'actor_type'      => $actorType,
                'actor_id'        => isset($data['actor_id']) && is_numeric($data['actor_id']) ? (int)$data['actor_id'] : null,
                'related_item_id' => isset($data['related_item_id']) && is_numeric($data['related_item_id']) ? (int)$data['related_item_id'] : null,
                'old_value'       => $data['old_value'] ?? null,
                'new_value'       => $data['new_value'] ?? null,
                'note'            => isset($data['note']) ? substr($data['note'], 0, 255) : null,
            ]);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing event ID for deletion', 400);
                exit;
            }
            $deleted = $controller->deleteById((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'cart_events.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'cart_events.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'cart_events.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
