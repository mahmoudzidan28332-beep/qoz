<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/core_events';
require_once $modelsPath . '/repositories/PdoCoreEventRepository.php';
require_once $modelsPath . '/validators/CoreEventValidator.php';
require_once $modelsPath . '/services/CoreEventService.php';
require_once $modelsPath . '/controllers/CoreEventController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoCoreEventRepository($pdo);
$validator  = new CoreEventValidator();
$service    = new CoreEventService($repo, $validator);
$controller = new CoreEventController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = $raw ? json_decode($raw, true) : [];
    $action = $_GET['action'] ?? '';
    
    // 🔒 SECURITY: Resolve tenant ID for security checks
    $tenantId = resolve_tenant_id();

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // GET /api/core_events?action=aggregate&entity_type=product&start_date=X&end_date=Y
            if ($action === 'aggregate') {
                $entityId = $_GET['entity_id'] ?? '';
                if ($entityId !== '') {
                    // 🔒 SECURITY: Verify entity ownership
                    verify_entity_ownership($pdo, $entityId, $tenantId);
                }
                
                $params = [
                    'entity_type' => $_GET['entity_type'] ?? 'product',
                    'entity_id'   => $entityId,
                    'start_date'  => $_GET['start_date'] ?? date('Y-m-01'),
                    'end_date'    => $_GET['end_date'] ?? date('Y-m-d'),
                    'group_by'    => $_GET['group_by'] ?? 'day',
                ];
                $result = $controller->aggregate($params);
                ResponseFormatter::success($result);
                exit;
            }

            // GET /api/core_events?id=X
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id > 0) {
                $result = $controller->findById($id);
                if ($result['success']) {
                    ResponseFormatter::success($result['data']);
                } else {
                    ResponseFormatter::notFound($result['errors'][0] ?? 'Not found');
                }
                exit;
            }

            // GET /api/core_events (list)
            $page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit   = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 25;
            $offset  = ($page - 1) * $limit;
            $orderBy = $_GET['order_by'] ?? 'id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $entityId = $_GET['entity_id'] ?? '';
            if ($entityId !== '') {
                // 🔒 SECURITY: Verify entity ownership
                verify_entity_ownership($pdo, $entityId, $tenantId);
            }

            $filters = [
                'entity_type' => $_GET['entity_type'] ?? '',
                'entity_id'   => $entityId,
                'event_type'  => $_GET['event_type'] ?? '',
                'user_id'     => $_GET['user_id'] ?? '',
                'session_id'  => $_GET['session_id'] ?? '',
                'start_date'  => $_GET['start_date'] ?? '',
                'end_date'    => $_GET['end_date'] ?? '',
            ];

            $result = $controller->list($filters, $limit, $offset, $orderBy, $orderDir);
            if ($result['success']) {
                ResponseFormatter::success([
                    'items' => $result['data']['items'],
                    'total' => $result['data']['total'],
                    'page'  => $page,
                    'limit' => $limit,
                ]);
            } else {
                ResponseFormatter::error(implode('; ', $result['errors'] ?? ['Error']), 422);
            }
            break;

        case 'POST':
            // 🔒 SECURITY: Verify entity ownership if provided in data
            if (isset($data['entity_id']) && $data['entity_id'] !== '') {
                verify_entity_ownership($pdo, $data['entity_id'], $tenantId);
            }

            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
            $data['user_id']    = $data['user_id'] ?? ($_SESSION['user_id'] ?? null);

            $result = $controller->create($data);
            if ($result['success']) {
                ResponseFormatter::success(['id' => $result['id']], 'Event created', 201);
            } else {
                ResponseFormatter::error(implode('; ', $result['errors'] ?? ['Error']), 422);
            }
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                ResponseFormatter::error('id is required', 400);
                exit;
            }
            $result = $controller->delete($id);
            if ($result['success']) {
                ResponseFormatter::success(null, 'Event deleted');
            } else {
                ResponseFormatter::notFound($result['errors'][0] ?? 'Not found');
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    safe_log('error', 'core_events.fatal', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal Server Error', 500);
}
