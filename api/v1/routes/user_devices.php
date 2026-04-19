<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/notification';
require_once $modelsPath . '/repositories/PdoUserDevicesRepository.php';
require_once $modelsPath . '/services/UserDevicesService.php';
require_once $modelsPath . '/controllers/UserDevicesController.php';
require_once $modelsPath . '/validators/UserDevicesValidator.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoUserDevicesRepository($pdo);
$service = new UserDevicesService($repo);
$controller = new UserDevicesController($service);

// ================================
// Auth check
// ================================
$user = $_SESSION['user'] ?? [];

// Session-based user_id (safe for write operations — POST, deregister)
$sessionUserId = isset($user['id']) ? (int)$user['id'] : null;
if ($sessionUserId === null && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $sessionUserId = (int)$_SESSION['user_id'];
}

// Filtered user_id for GET/read operations (accepts query param for admin/backend calls)
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id'])
    ? (int)$_GET['user_id']
    : $sessionUserId;

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    // Detect sub-action from URI (e.g. /user_devices/deregister)
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uriParts = explode('/', trim($uri, '/'));
    $subAction = $uriParts[count($uriParts) - 1] ?? '';

    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Filters (from query string)
    $filters = [
        'user_id'      => $_GET['user_id'] ?? null,
        'device_type'  => $_GET['device_type'] ?? null,
        'device_name'  => $_GET['device_name'] ?? null,
        'is_active'    => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            } elseif (isset($_GET['user_id'])) {
                $items = $controller->getByUser((int)$_GET['user_id']);
                ResponseFormatter::success(['items' => $items, 'total' => count($items)]);
            } else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            // Handle deregister sub-action (POST /user_devices/deregister)
            if ($subAction === 'deregister') {
                $fcmToken = $data['fcm_token'] ?? '';
                if (empty($fcmToken)) {
                    ResponseFormatter::error('fcm_token is required', 422);
                }
                // Deactivate the device token (set is_active = 0) — scoped to current user
                if ($sessionUserId === null || $sessionUserId <= 0) {
                    ResponseFormatter::error('Authentication required', 401);
                    break;
                }
                $stmt = $repo->deactivateByFcmTokenAndUser($fcmToken, $sessionUserId);
                ResponseFormatter::success(['deregistered' => true], 'Device deregistered successfully');
                break;
            }

            // Require session-based auth for device registration
            if ($sessionUserId === null || $sessionUserId <= 0) {
                ResponseFormatter::error('Authentication required', 401);
                break;
            }
            $data['user_id'] = $sessionUserId;

            // Add request metadata (truncate UA to 512 chars to match login device registration)
            $data['user_agent'] = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
            $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $data['last_seen_at'] = date('Y-m-d H:i:s');

            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Device registered successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing device ID for update', 400);
            }
            // Add metadata if needed (user_agent, ip, last_seen)
            if (isset($data['touch']) && $data['touch'] === true) {
                $controller->touch((int)$data['id']);
            }
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Device updated successfully');
            break;

        case 'DELETE':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $deleted = $controller->delete((int)$_GET['id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Device deleted successfully');
            } elseif (isset($_GET['user_id'])) {
                $deleted = $controller->deleteByUser((int)$_GET['user_id']);
                ResponseFormatter::success(['deleted' => $deleted], 'All devices for user deleted');
            } else {
                ResponseFormatter::error('Missing device ID or user_id for deletion', 400);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','user_devices.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','user_devices.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','user_devices.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}