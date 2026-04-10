<?php
declare(strict_types=1);

/**
 * /api/track_click.php
 * Public ad click tracking endpoint.
 * Records a click event in ad_stats (one row per event, no deduplication).
 *
 * Usage: GET /api/track_click.php?id=AD_ID
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');

$baseDir = __DIR__;
require_once $baseDir . '/shared/config/db.php';

$adId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($adId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ad id']);
    exit;
}

// ── Session ────────────────────────────────────────────────────
// Start session to read user_id and capture the session identifier.
if (session_status() === PHP_SESSION_NONE) {
    @session_start([
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// ── Collect tracking data ──────────────────────────────────────
$sessionId = session_id() ?: '';

// Resolve user_id from session (supports both storage formats used across the app).
$userId = (int)(
    $_SESSION['user']['id'] ??
    ($_SESSION['current_user']['id'] ?? ($_SESSION['user_id'] ?? 0))
);

// Client IP — prefer X-Forwarded-For if behind a trusted proxy.
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains((string)$ipAddress, ',')) {
    // X-Forwarded-For may contain a comma-separated list; take the first (client) IP.
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}
$ipAddress = substr((string)$ipAddress, 0, 45);

$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// ── DB connection ──────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

// ── Verify the ad exists ───────────────────────────────────────
$check = $pdo->prepare("SELECT id FROM ads WHERE id = ? LIMIT 1");
$check->execute([$adId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ad not found']);
    exit;
}

// ── Insert per-event click row ─────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "INSERT INTO ad_stats
             (ad_id, user_id, session_id, ip_address, user_agent,
              date, created_at, views, clicks, event_type)
         VALUES
             (?, ?, ?, ?, ?, CURDATE(), NOW(), 0, 1, 'click')"
    );
    $stmt->execute([
        $adId,
        $userId ?: null,
        $sessionId ?: null,
        $ipAddress ?: null,
        $userAgent ?: null,
    ]);
} catch (\PDOException $e) {
    // Fallback: legacy schema without the new columns.
    $stmt = $pdo->prepare(
        "INSERT INTO ad_stats (ad_id, date, views, clicks)
         VALUES (?, CURDATE(), 0, 1)
         ON DUPLICATE KEY UPDATE clicks = clicks + 1"
    );
    $stmt->execute([$adId]);
}

echo json_encode(['success' => true, 'tracked' => true]);