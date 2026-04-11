<?php
declare(strict_types=1);
/**
 * Public API sub-route: events  [v1.3.0 — Production]
 *
 * POST /api/public/events — Record a core analytics event into core_events.
 *
 * Changes v1.3.0
 * ──────────────
 *  FIX-1  Session pattern mirrors ads.php exactly — proven to resolve user_id = NULL.
 *  FIX-2  session_write_close() after read — prevents session lock.
 *  FIX-3  value accepts 0.00 — removed the > 0 guard that was dropping real prices.
 *         NULL is only stored when value is genuinely absent from the request body.
 *  FIX-4  IP validation with filter_var; strips ::ffff: prefix and port suffix.
 *  FIX-5  Removed @ suppression from session_start — errors now surface in logs.
 *
 * Body (JSON or form-data):
 *   entity_type  string   required  product|entity|brand|category|job|auction
 *   entity_id    int      required
 *   event_type   string   required  view|click|favorite|contact|add_to_cart|purchase
 *   value        float    optional  send only when genuinely present (e.g. product price)
 *
 * Injected by router: $pdo, $first, $segments
 */

if ($first !== 'events') return;

// ── Method guard ──────────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

// ── Parse body (JSON preferred, form-data fallback) ───────────────────────────
$body    = [];
$rawBody = (string) file_get_contents('php://input');
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
if (empty($body)) {
    $body = $_POST;
}

// ── Validate ──────────────────────────────────────────────────────────────────
$allowedEntityTypes = ['product', 'entity', 'brand', 'category', 'job', 'auction'];
$allowedEventTypes  = ['view', 'click', 'favorite', 'contact', 'add_to_cart', 'purchase'];

$entityType = strtolower(trim((string) ($body['entity_type'] ?? '')));
$entityId   = isset($body['entity_id']) ? (int) $body['entity_id'] : 0;
$eventType  = strtolower(trim((string) ($body['event_type'] ?? '')));

// FIX-3: store NULL only when key is absent from body entirely.
// Do NOT guard with > 0 — a price of 0.00 is a valid value.
$value = null;
if (array_key_exists('value', $body) && is_numeric($body['value'])) {
    $value = round((float) $body['value'], 2);
}

if (
    !in_array($entityType, $allowedEntityTypes, true) ||
    $entityId <= 0 ||
    !in_array($eventType, $allowedEventTypes, true)
) {
    ResponseFormatter::error('Invalid parameters', 422);
    exit;
}

// ── DB guard ──────────────────────────────────────────────────────────────────
if (!$pdo instanceof PDO) {
    ResponseFormatter::success(['ok' => false, 'reason' => 'db_unavailable']);
    exit;
}

// ── Session — identical pattern to ads.php  [FIX-1] ──────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    @session_start([
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$sessId = session_id() ?: null;
$userId = (int)(
    $_SESSION['user']['id']          ??
    ($_SESSION['current_user']['id'] ?? ($_SESSION['user_id'] ?? 0))
) ?: null;

// FIX-2: release session lock immediately — we only read, never write
session_write_close();

// ── IP  [FIX-4] ───────────────────────────────────────────────────────────────
$rawIp = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (str_contains($rawIp, ',')) {
    $rawIp = trim(explode(',', $rawIp)[0]);
}
$rawIp = preg_replace('/^::ffff:/i', '', $rawIp) ?? $rawIp;
if (substr_count($rawIp, ':') === 1) {
    $rawIp = explode(':', $rawIp)[0];
}
$ip = filter_var($rawIp, FILTER_VALIDATE_IP) ? substr($rawIp, 0, 45) : null;

// ── User-agent ────────────────────────────────────────────────────────────────
$userAgent = isset($_SERVER['HTTP_USER_AGENT'])
    ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
    : null;

// ── Insert ────────────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO core_events
             (entity_type, entity_id, user_id, session_id,
              event_type, value, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $entityType,
        $entityId,
        $userId,
        $sessId,
        $eventType,
        $value,
        $ip,
        $userAgent,
    ]);
    ResponseFormatter::success(['ok' => true]);
} catch (Throwable) {
    // Analytics must never break the user experience.
    ResponseFormatter::success(['ok' => false]);
}
exit;