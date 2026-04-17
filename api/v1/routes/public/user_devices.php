<?php
declare(strict_types=1);

/**
 * Production API: /api/public/user_devices
 * Fixed: duplicate named params, path resolution, missing DeviceDetector fallback
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__, 2) . '/models/notification/repositories/PdoUserDevicesRepository.php';

// ── Helpers ──────────────────────────────────────────────
function ud_json_body(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function ud_user_id(): ?int {
    if (!empty($_SESSION['user']['id']))  return (int)$_SESSION['user']['id'];
    if (!empty($_SESSION['user_id']))     return (int)$_SESSION['user_id'];
    return null;
}

// ── Database ─────────────────────────────────────────────
if (!isset($pdo) || !$pdo instanceof PDO) {
    $pdo = $GLOBALS['pdo'] ?? ($GLOBALS['ADMIN_DB'] ?? null);
}
if (!$pdo instanceof PDO && function_exists('pub_get_pdo')) {
    $pdo = pub_get_pdo();
}
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ── DeviceDetector — safe require ─────────────────────────
// Try multiple possible paths to avoid fatal error
$detectorPaths = [
    dirname(__DIR__, 4) . '/shared/helpers/device_detector.php',
    dirname(__DIR__, 3) . '/shared/helpers/device_detector.php',
    dirname(__DIR__, 2) . '/shared/helpers/device_detector.php',
    dirname(__DIR__, 1) . '/helpers/device_detector.php',
    __DIR__ . '/helpers/device_detector.php',
];
$detectorLoaded = false;
foreach ($detectorPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $detectorLoaded = true;
        break;
    }
}

// Fallback inline detector if file not found
if (!$detectorLoaded || !class_exists('DeviceDetector')) {
    class DeviceDetector {
        public static function detectType(string $ua): string {
            $ua = strtolower($ua);
            if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) return 'mobile';
            if (preg_match('/ipad|tablet|kindle|playbook|silk/i', $ua))                          return 'tablet';
            return 'desktop';
        }
        public static function detectName(string $ua): string {
            if (preg_match('/iphone/i',  $ua)) return 'iPhone';
            if (preg_match('/ipad/i',    $ua)) return 'iPad';
            if (preg_match('/android/i', $ua)) {
                preg_match('/android[^;]*;\s*([^;)]+)/i', $ua, $m);
                return trim($m[1] ?? 'Android Device');
            }
            if (preg_match('/windows/i', $ua)) return 'Windows PC';
            if (preg_match('/macintosh|mac os x/i', $ua)) return 'Mac';
            if (preg_match('/linux/i',   $ua)) return 'Linux PC';
            return 'Unknown Device';
        }
    }
}

// ── Request info ─────────────────────────────────────────
$userId = ud_user_id();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';

// ── CORS / OPTIONS ────────────────────────────────────────
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── MAIN ─────────────────────────────────────────────────
try {

    // ════════════════════════════════════
    // GET — list user's devices
    // ════════════════════════════════════
    if ($method === 'GET') {

        if (!$userId) {
            ResponseFormatter::error('Auth required', 401);
            exit;
        }

        $devRepo = new PdoUserDevicesRepository($pdo);
        ResponseFormatter::success(['items' => $devRepo->listForUser($userId)]);
        exit;
    }

    // ════════════════════════════════════
    // POST — register / update device
    // ════════════════════════════════════
    if ($method === 'POST') {

        $data      = ud_json_body();
        $anonToken = isset($data['anonymous_token']) ? trim((string)$data['anonymous_token']) : null;
        $fcmToken  = isset($data['fcm_token'])       ? trim((string)$data['fcm_token'])       : null;

        // Normalize empty strings to null
        if ($anonToken === '') $anonToken = null;
        if ($fcmToken  === '') $fcmToken  = null;

        if (!$anonToken && !$fcmToken) {
            ResponseFormatter::error('anonymous_token or fcm_token required', 422);
            exit;
        }

        $deviceType = DeviceDetector::detectType($ua);
        $deviceName = DeviceDetector::detectName($ua);

        // ── Find existing device ──
        // FIX: avoid duplicate named params — use positional params instead
        $device = null;

        $devRepo = new PdoUserDevicesRepository($pdo);
        if ($anonToken) {
            $device = $devRepo->findByAnonymousToken($anonToken);
        }

        if (!$device && $fcmToken) {
            $device = $devRepo->findByToken($fcmToken);
        }

        // ── UPDATE ──
        if ($device) {
            $devRepo->updateRegistration((int)$device['id'], $userId, $anonToken, $fcmToken, $deviceType, $deviceName, $ip);
            ResponseFormatter::success(['id' => (int)$device['id'], 'updated' => true]);
            exit;
        }

        // ── INSERT ──
        $newDevId = $devRepo->insertRegistration($userId, $anonToken, $fcmToken, $deviceType, $deviceName, $ua, $ip);
        ResponseFormatter::success(
            ['id' => $newDevId, 'created' => true],
            'Device registered',
            201
        );
        exit;
    }

    // ════════════════════════════════════
    // DELETE — deactivate device
    // ════════════════════════════════════
    if ($method === 'DELETE') {

        $data     = ud_json_body();
        $fcmToken = isset($data['fcm_token']) ? trim((string)$data['fcm_token']) : null;

        if (!$fcmToken) {
            ResponseFormatter::error('fcm_token required', 422);
            exit;
        }

        $devRepo = new PdoUserDevicesRepository($pdo);
        $devRepo->deactivateByFcmTokenOnly($fcmToken);

        ResponseFormatter::success(['deleted' => true]);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (Throwable $e) {
    error_log(sprintf(
        '[UserDevices API] %s in %s:%d | URI: %s',
        $e->getMessage(), $e->getFile(), $e->getLine(),
        $_SERVER['REQUEST_URI'] ?? 'n/a'
    ));
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}