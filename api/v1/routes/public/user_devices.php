<?php
declare(strict_types=1);

/**
 * Production API: /api/public/user_devices
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ==============================
// Helpers
// ==============================
function json(): array {
    $raw = file_get_contents('php://input');
    return $raw ? json_decode($raw, true) ?? [] : [];
}

function user_id(): ?int {
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    return null;
}

// ==============================
// Init
// ==============================
$userId = user_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

require_once dirname(__DIR__, 3) . '/shared/helpers/device_detector.php';
require_once dirname(__DIR__, 2) . '/models/notification/repositories/PdoUserDevicesRepository.php';

$devicesRepo = new PdoUserDevicesRepository($pdo);

// ==============================
// MAIN
// ==============================
try {

    // ==========================
    // GET: list devices
    // ==========================
    if ($method === 'GET') {

        if (!$userId) {
            ResponseFormatter::error('Auth required', 401);
            exit;
        }

        ResponseFormatter::success([
            'items' => $devicesRepo->listByUserId($userId)
        ]);
        exit;
    }

    // ==========================
    // POST: register/update
    // ==========================
    if ($method === 'POST') {

        $data = json();

        $anonToken = $data['anonymous_token'] ?? null;
        $fcmToken  = $data['fcm_token'] ?? null;

        if (!$anonToken && !$fcmToken) {
            ResponseFormatter::error('anonymous_token or fcm_token required', 422);
            exit;
        }

        // Detect device
        $deviceType = DeviceDetector::detectType($ua);
        $deviceName = DeviceDetector::detectName($ua);

        // ======================
        // Find existing device
        // ======================
        $device = $devicesRepo->findByTokens($anonToken, $fcmToken);

        // ======================
        // UPDATE
        // ======================
        if ($device) {

            $devicesRepo->updatePublicRegistration($userId, $anonToken, $fcmToken, $deviceType, $deviceName, $ip, (int)$device['id']);

            ResponseFormatter::success([
                'id' => (int)$device['id'],
                'updated' => true
            ]);
            exit;
        }

        // ======================
        // INSERT
        // ======================
        $newId = $devicesRepo->insertPublicRegistration($userId, $anonToken, $fcmToken, $deviceType, $deviceName, $ua, $ip);

        ResponseFormatter::success([
            'id' => $newId,
            'created' => true
        ], 'Device registered', 201);

        exit;
    }

    // ==========================
    // DELETE: logout device
    // ==========================
    if ($method === 'DELETE') {

        $data = json();
        $fcmToken = $data['fcm_token'] ?? null;

        if (!$fcmToken) {
            ResponseFormatter::error('fcm_token required', 422);
            exit;
        }

        $devicesRepo->deactivateByFcmToken($fcmToken);

        ResponseFormatter::success(['deleted' => true]);
        exit;
    }

    // OPTIONS
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (Throwable $e) {

    error_log($e->getMessage());

    ResponseFormatter::error('Internal server error', 500);
}