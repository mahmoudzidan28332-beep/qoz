<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/notification';
require_once $modelsPath . '/repositories/PdoNotificationsRepository.php';
require_once $modelsPath . '/validators/NotificationsValidator.php';
require_once $modelsPath . '/services/NotificationsService.php';
require_once $modelsPath . '/controllers/NotificationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoNotificationsRepository($pdo);
$validator  = new NotificationsValidator();
$service    = new NotificationsService($repo, $validator);
$controller = new NotificationsController($service);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------

function parseToArray(mixed $value): array
{
    if (is_array($value))   return $value;
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function parseExpiresAt(mixed $value): ?string
{
    if (empty($value) || !is_string($value)) return null;
    $dt = str_replace('T', ' ', $value);
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $dt) ? $dt : null;
}

function parseChannels(mixed $value): array
{
    $valid = ['database', 'push', 'email', 'sms'];
    $raw   = is_array($value) ? $value : ['database'];
    $result = array_values(array_intersect(
        array_filter($raw, fn($ch) => is_string($ch) && $ch !== ''),
        $valid
    ));
    return empty($result) ? ['database'] : $result;
}

function parseNotificationInput(array $data): array
{
    $title   = trim((string)($data['title']   ?? ''));
    $message = trim((string)($data['message'] ?? ''));

    if ($title === '')   throw new InvalidArgumentException('title مطلوب ولا يمكن أن يكون فارغاً');
    if ($message === '') throw new InvalidArgumentException('message مطلوب ولا يمكن أن يكون فارغاً');

    return [
        'recipientType'  => in_array($data['recipient_type'] ?? '', ['user', 'entity', 'tenant'], true)
                                ? $data['recipient_type'] : 'user',
        'tenantId'       => isset($data['tenant_id']) && is_numeric($data['tenant_id'])
                                ? (int)$data['tenant_id'] : 1,
        'typeCode'       => !empty($data['type_code']) && is_string($data['type_code'])
                                ? trim($data['type_code']) : 'general',
        'title'          => $title,
        'message'        => $message,
        'extraData'      => parseToArray($data['data'] ?? null),
        'channels'       => parseChannels($data['channels'] ?? null),
        'priority'       => in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true)
                                ? $data['priority'] : 'normal',
        'expiresAt'      => parseExpiresAt($data['expires_at'] ?? null),
        'senderEntityId' => isset($data['sender_entity_id']) && is_numeric($data['sender_entity_id'])
                                ? (int)$data['sender_entity_id'] : null,
        'deviceIds'      => !empty($data['device_ids']) && is_array($data['device_ids'])
                                ? array_values(array_map('intval', array_filter($data['device_ids'], 'is_numeric')))
                                : [],
    ];
}

// -------------------------------------------------------
// Router
// -------------------------------------------------------

try {
    $method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri         = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $segments    = explode('/', trim($uri, '/'));
    $lastSegment = end($segments);

    // ===================================================
    // GET
    // ===================================================
    if ($method === 'GET') {

        // GET /notifications/unread-count?user_id=1
        if ($lastSegment === 'unread-count') {
            $userId = isset($_GET['user_id']) && is_numeric($_GET['user_id'])
                ? (int)$_GET['user_id'] : null;
            if (!$userId) {
                ResponseFormatter::error('user_id مطلوب', 400);
                exit;
            }
            ResponseFormatter::success([
                'unread_count' => $controller->unreadCount($userId),
            ]);
            exit;
        }

        // GET /notifications?id=5
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            ResponseFormatter::success($controller->get((int)$_GET['id']));
            exit;
        }

        // GET /notifications?user_id=1&page=1&limit=25
        $filters = [
            'user_id'              => isset($_GET['user_id'])              && is_numeric($_GET['user_id'])              ? (int)$_GET['user_id']              : null,
            'entity_id'            => isset($_GET['entity_id'])            && is_numeric($_GET['entity_id'])            ? (int)$_GET['entity_id']            : null,
            'is_read'              => isset($_GET['is_read'])              && is_numeric($_GET['is_read'])              ? (int)$_GET['is_read']              : null,
            'notification_type_id' => isset($_GET['notification_type_id']) && is_numeric($_GET['notification_type_id']) ? (int)$_GET['notification_type_id'] : null,
        ];

        if (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        $orderBy  = in_array($_GET['order_by']  ?? '', ['sent_at', 'priority', 'id'], true) ? $_GET['order_by']  : 'sent_at';
        $orderDir = in_array(strtoupper($_GET['order_dir'] ?? ''), ['ASC', 'DESC'], true)    ? strtoupper($_GET['order_dir']) : 'DESC';
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
    // Body parsing (POST / PUT / DELETE)
    // ===================================================
    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if (!is_array($data)) {
        ResponseFormatter::error('Request body يجب أن يكون JSON object صحيح', 400);
        exit;
    }

    // ===================================================
    // POST
    // ===================================================
    if ($method === 'POST') {

        require_once dirname(__DIR__, 2) . '/shared/helpers/notification.php';
        \Notification::setPDO($pdo);

        // -------------------------------------------------
        // POST /notifications/send — إرسال لمستخدم واحد
        // -------------------------------------------------
        if ($lastSegment === 'send') {

            $recipientId = isset($data['recipient_id']) && is_numeric($data['recipient_id'])
                ? (int)$data['recipient_id'] : 0;
            if ($recipientId <= 0) {
                ResponseFormatter::error('recipient_id مطلوب ويجب أن يكون رقماً موجباً', 422);
                exit;
            }

            $input  = parseNotificationInput($data);
            $result = \Notification::send(
                $recipientId,
                $input['recipientType'],
                $input['tenantId'],
                $input['typeCode'],
                $input['title'],
                $input['message'],
                $input['extraData'],
                $input['channels'],
                $input['priority'],
                $input['expiresAt'],
                $input['senderEntityId'],
                $input['deviceIds']
            );

            $httpCode = $result['success'] ? 201 : 500;
            $msg      = $result['success'] ? 'تم إرسال الإشعار بنجاح' : 'فشل إرسال الإشعار';
            ResponseFormatter::success($result, $msg, $httpCode);
            exit;
        }

        // -------------------------------------------------
        // POST /notifications/bulk-send — إرسال جماعي
        // -------------------------------------------------
        if ($lastSegment === 'bulk-send') {

            // recipient_ids
            if (empty($data['recipient_ids']) || !is_array($data['recipient_ids'])) {
                ResponseFormatter::error('recipient_ids مطلوب ويجب أن يكون مصفوفة من الأرقام', 422);
                exit;
            }

            $recipientIds = array_values(array_unique(
                array_map('intval', array_filter($data['recipient_ids'], 'is_numeric'))
            ));

            if (empty($recipientIds)) {
                ResponseFormatter::error('recipient_ids لا يحتوي على أرقام صحيحة', 422);
                exit;
            }

            $maxBulk = 1000;
            if (count($recipientIds) > $maxBulk) {
                ResponseFormatter::error("الحد الأقصى للإرسال الجماعي هو {$maxBulk} مستخدم في المرة الواحدة", 422);
                exit;
            }

            $input = parseNotificationInput($data);

            $successCount = 0;
            $failCount    = 0;
            $details      = [];

            foreach ($recipientIds as $recipientId) {
                try {
                    $result = \Notification::send(
                        $recipientId,
                        $input['recipientType'],
                        $input['tenantId'],
                        $input['typeCode'],
                        $input['title'],
                        $input['message'],
                        $input['extraData'],
                        $input['channels'],
                        $input['priority'],
                        $input['expiresAt'],
                        $input['senderEntityId'],
                        []  // device_ids لا تُستخدم في الإرسال الجماعي
                    );

                    if ($result['success']) {
                        $successCount++;
                        $details[] = [
                            'recipient_id'    => $recipientId,
                            'success'         => true,
                            'notification_id' => $result['notification_id'] ?? null,
                        ];
                    } else {
                        $failCount++;
                        $details[] = [
                            'recipient_id' => $recipientId,
                            'success'      => false,
                            'error'        => $result['error'] ?? 'unknown error',
                        ];
                    }
                } catch (Throwable $e) {
                    $failCount++;
                    $details[] = [
                        'recipient_id' => $recipientId,
                        'success'      => false,
                        'error'        => $e->getMessage(),
                    ];
                }
            }

            ResponseFormatter::success([
                'total'         => count($recipientIds),
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'details'       => $details,
            ], "تم الإرسال: {$successCount} نجح، {$failCount} فشل", 201);
            exit;
        }

        // -------------------------------------------------
        // POST /notifications/mark-read
        // -------------------------------------------------
        if ($lastSegment === 'mark-read') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                ResponseFormatter::error('id مطلوب لتحديد الإشعار', 400);
                exit;
            }
            $controller->markAsRead((int)$data['id']);
            ResponseFormatter::success(['marked_read' => true], 'تم تحديد الإشعار كمقروء');
            exit;
        }

        // -------------------------------------------------
        // POST /notifications — إنشاء إشعار عبر الـ controller
        // -------------------------------------------------
        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId], 'تم الإنشاء بنجاح', 201);
        exit;
    }

    // ===================================================
    // PUT
    // ===================================================
    if ($method === 'PUT') {
        $updatedId = $controller->update($data);
        ResponseFormatter::success(['id' => $updatedId], 'تم التحديث بنجاح');
        exit;
    }

    // ===================================================
    // DELETE
    // ===================================================
    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('id مطلوب للحذف', 400);
            exit;
        }
        $controller->delete((int)$id);
        ResponseFormatter::success(['deleted' => true], 'تم الحذف بنجاح');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

// -------------------------------------------------------
// Exception handlers
// -------------------------------------------------------
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'notifications.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error('خطأ في البيانات: ' . $e->getMessage(), 422);

} catch (RuntimeException $e) {
    safe_log('error', 'notifications.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('خطأ في المعالجة: ' . $e->getMessage(), 400);

} catch (Throwable $e) {
    safe_log('critical', 'notifications.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => array_map(
            fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' '
                    . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
            array_slice($e->getTrace(), 0, 5)
        ),
    ]);

    $msg = (defined('IS_DEBUG') && IS_DEBUG)
        ? $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine()
        : 'حدث خطأ داخلي، يرجى المحاولة مجدداً';

    ResponseFormatter::error($msg, 500);
}