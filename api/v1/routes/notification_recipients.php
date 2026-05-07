<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/notification';
require_once $modelsPath . '/repositories/PdoNotificationRecipientsRepository.php';
require_once $modelsPath . '/validators/NotificationRecipientsValidator.php';
require_once $modelsPath . '/services/NotificationRecipientsService.php';
require_once $modelsPath . '/controllers/NotificationRecipientsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoNotificationRecipientsRepository($pdo);
$validator  = new NotificationRecipientsValidator();
$service    = new NotificationRecipientsService($repo, $validator);
$controller = new NotificationRecipientsController($service);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri         = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $segments    = explode('/', trim($uri, '/'));
    $lastSegment = end($segments);

    // 🔒 Resolve tenant: platform admin may pass tenant_id, others are locked to JWT tenant
    $tenantId = resolve_tenant_id();

    // ===================================================
    // GET
    // ===================================================
    if ($method === 'GET') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'tenant_id'      => $tenantId > 0 ? $tenantId : null,
            'notification_id'=> isset($_GET['notification_id']) && is_numeric($_GET['notification_id']) ? (int)$_GET['notification_id'] : null,
            'recipient_type' => isset($_GET['recipient_type']) && in_array($_GET['recipient_type'], ['user','entity','tenant'], true) ? $_GET['recipient_type'] : null,
            'recipient_id'   => isset($_GET['recipient_id'])   && is_numeric($_GET['recipient_id'])   ? (int)$_GET['recipient_id']   : null,
            'is_read'        => isset($_GET['is_read'])        && is_numeric($_GET['is_read'])        ? (int)$_GET['is_read']        : null,
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($v) => $v !== null);

        $orderBy  = in_array($_GET['order_by']  ?? '', ['id','notification_id','tenant_id','recipient_type','recipient_id','is_read','read_at','created_at'], true)
                        ? $_GET['order_by'] : 'id';
        $orderDir = in_array(strtoupper($_GET['order_dir'] ?? ''), ['ASC','DESC'], true) ? strtoupper($_GET['order_dir']) : 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset   = ($page - 1) * $limit;

        $result = $controller->list($filters, $orderBy, $orderDir, $limit, $offset);
        $total  = $result['total'];

        ResponseFormatter::success([
            'items' => $result['items'],
            'meta'  => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                'from'        => $total > 0 ? $offset + 1 : 0,
                'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
            ],
        ]);
        exit;
    }

    // ===================================================
    // Body parsing (POST / DELETE)
    // ===================================================
    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if (!is_array($data)) {
        ResponseFormatter::error('Request body must be a valid JSON object', 400);
        exit;
    }

    // ===================================================
    // POST — mark-read / mark-unread actions
    // ===================================================
    if ($method === 'POST') {
        if (!isset($data['id']) || !is_numeric($data['id'])) {
            ResponseFormatter::error('Field "id" is required', 400);
            exit;
        }
        $id = (int)$data['id'];

        if ($lastSegment === 'mark-read') {
            $done = $controller->markRead($id, $tenantId);
            ResponseFormatter::success(['marked_read' => $done], $done ? 'Marked as read' : 'Already read');
            exit;
        }

        if ($lastSegment === 'mark-unread') {
            $done = $controller->markUnread($id, $tenantId);
            ResponseFormatter::success(['marked_unread' => $done], 'Marked as unread');
            exit;
        }

        ResponseFormatter::error('Unknown action. Supported: mark-read, mark-unread', 400);
        exit;
    }

    // ===================================================
    // DELETE
    // ===================================================
    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('Field "id" is required for deletion', 400);
            exit;
        }
        $controller->delete((int)$id, $tenantId);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'notification_recipients.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'notification_recipients.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
}
