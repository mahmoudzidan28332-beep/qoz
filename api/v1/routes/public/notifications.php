<?php
declare(strict_types=1);
/**
 * api/v1/routes/public/notifications.php
 * QOOQZ — Public Notifications API
 *
 * Serves /api/public/notifications/* requests.
 * Loaded by api/v1/routes/public.php dispatcher when $first === 'notifications'.
 *
 * All list/count endpoints require an authenticated user session because
 * notifications are addressed to specific recipients via notification_recipients.
 *
 * Endpoints:
 *  GET  /api/public/notifications               — list notifications addressed to the logged-in user
 *  GET  /api/public/notifications/unread-count  — unread count for the logged-in user
 *  GET  /api/public/notifications/types         — active notification types (for icons/labels)
 *  POST /api/public/notifications/mark-read     — mark notification IDs as read in notification_recipients
 *  POST /api/public/notifications/mark-all-read — mark all unread notifications as read for the user
 *
 * Variables provided by the parent (public.php):
 *  $pdo        PDO|null
 *  $first      string   (always 'notifications' when this file is loaded)
 *  $segments   array
 *  $lang       string
 *  $page       int
 *  $per        int
 *  $offset     int
 *  $tenantId   int|null
 */

$notifMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$notifSub    = strtolower($segments[1] ?? '');

// Handle CORS preflight so POST mark-read / mark-all-read calls succeed
if ($notifMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

// Resolve authenticated user from session (same pattern as rest of public.php)
$notifUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));

// $tenantId from public.php is null when no ?tenant_id= param is present.
// Fall back to the session-cached value (set by public_context.php) or default to 1.
$notifTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

/* ------------------------------------------------------------------
 * GET /api/public/notifications/types
 * Returns all active notification types (id, code, name, description).
 * Public — no login required.
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === 'types') {
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }
    try {
        $notifRepo = new PdoNotificationsRepository($pdo);
        $notifService = new NotificationsService($notifRepo);
        $types = $notifRepo->getActiveTypes();
        ResponseFormatter::success(['types' => $types, 'total' => count($types)]);
    } catch (Throwable $e) {
        error_log('[public/notifications] types error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notification types', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * GET /api/public/notifications/unread-count
 * Returns the unread count for the logged-in user.
 * Reads from notification_counters when available, falls back to counting
 * notification_recipients directly.
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === 'unread-count') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }
    try {
        $counterRepo = new PdoNotificationCountersRepository($pdo);
        $cachedCount = $counterRepo->getUnreadCountForUser($notifTenantId, $notifUserId);

        if ($cachedCount !== null) {
            $count = $cachedCount;
        } else {
            $notifRepo = new PdoNotificationsRepository($pdo);
            $count = $notifRepo->countUnreadForUser($notifUserId, $notifTenantId);
        }
        ResponseFormatter::success(['unread_count' => $count]);
    } catch (Throwable $e) {
        error_log('[public/notifications] unread-count error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load unread count', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * GET /api/public/notifications
 * Returns notifications addressed to the logged-in user via
 * notification_recipients (recipient_type='user', recipient_id=user_id).
 *
 * Query params:
 *   limit     int    default 20, max 100
 *   page      int    default 1
 *   type_code string filter by notification_type code
 *   priority  string filter by priority (low|normal|high|urgent)
 *   unread    1      show only unread notifications
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === '') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    $nLimit  = min(100, max(1, (int)($_GET['limit'] ?? $per)));
    $nPage   = max(1, (int)($_GET['page'] ?? $page));
    $nOffset = ($nPage - 1) * $nLimit;

    $allowedPriorities = ['low', 'normal', 'high', 'urgent'];

    try {
        $notifRepo = new PdoNotificationsRepository($pdo);
        $typeCode = !empty($_GET['type_code']) ? (string)$_GET['type_code'] : null;
        $priFilter = (!empty($_GET['priority']) && in_array($_GET['priority'], $allowedPriorities, true)) ? $_GET['priority'] : null;
        $unreadOnly = !empty($_GET['unread']);
        $result = $notifRepo->listForUser($notifUserId, $notifTenantId, $nLimit, $nOffset, $typeCode, $priFilter, $unreadOnly);
        $total = $result['total'];
        $items = $result['items'];

        // Cast is_read to bool for clean JSON
        foreach ($items as &$item) {
            $item['is_read'] = (bool)$item['is_read'];
        }
        unset($item);

        ResponseFormatter::success([
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'page'        => $nPage,
                'per_page'    => $nLimit,
                'total_pages' => $total > 0 ? (int)ceil($total / $nLimit) : 0,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[public/notifications] list error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notifications', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * POST /api/public/notifications/mark-read
 * Marks specific notification IDs as read for the logged-in user.
 * Updates notification_recipients.is_read = 1 and notification_counters.
 *
 * Body: { "ids": [1, 2, 3] }
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'POST' && $notifSub === 'mark-read') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    $raw  = file_get_contents('php://input');
    $body = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?? []) : [];
    $ids  = array_values(array_filter(array_map('intval', (array)($body['ids'] ?? []))));

    if (empty($ids)) { ResponseFormatter::error('ids array is required', 422); exit; }

    try {
        $notifRepo = new PdoNotificationsRepository($pdo);
        $affected = $notifRepo->markReadByIds($ids, $notifUserId);

        if ($affected > 0) {
            try {
                $counterRepo = new PdoNotificationCountersRepository($pdo);
                $counterRepo->recalculateForUser($notifTenantId, $notifUserId);
            } catch (Throwable $e) {
                error_log('[notifications] recalculate counter failed: ' . $e->getMessage());
            }
    } catch (Throwable $e) {
        error_log('[public/notifications] mark-read error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to mark notifications as read', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * POST /api/public/notifications/mark-all-read
 * Marks ALL unread notifications as read for the logged-in user and
 * resets notification_counters.unread_count to 0.
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'POST' && $notifSub === 'mark-all-read') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    try {
        $notifRepo = new PdoNotificationsRepository($pdo);
        $affected = $notifRepo->markAllReadForUser($notifUserId, $notifTenantId);

        try {
            $counterRepo = new PdoNotificationCountersRepository($pdo);
            $counterRepo->resetForUser($notifTenantId, $notifUserId);
        } catch (Throwable $e) {
            error_log('[notifications] reset counter failed: ' . $e->getMessage());
        }

        ResponseFormatter::success(['affected' => $affected]);
    } catch (Throwable $e) {
        error_log('[public/notifications] mark-all-read error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to mark all notifications as read', 500);
    }
    exit;
}

ResponseFormatter::notFound('Notifications route not found: /' . implode('/', $segments));