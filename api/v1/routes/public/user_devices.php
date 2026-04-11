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

        $stmt = $pdo->prepare("
            SELECT id, device_type, device_name, ip, last_seen_at, is_active
            FROM user_devices
            WHERE user_id = ?
            ORDER BY last_seen_at DESC
        ");
        $stmt->execute([$userId]);

        ResponseFormatter::success([
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)
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
        $stmt = $pdo->prepare("
            SELECT * FROM user_devices
            WHERE (anonymous_token = :anon AND :anon IS NOT NULL)
               OR (fcm_token = :fcm AND :fcm IS NOT NULL)
            LIMIT 1
        ");
        $stmt->execute([
            ':anon' => $anonToken,
            ':fcm'  => $fcmToken
        ]);

        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        // ======================
        // UPDATE
        // ======================
        if ($device) {

            $stmt = $pdo->prepare("
                UPDATE user_devices SET
                    user_id       = COALESCE(:uid, user_id),
                    anonymous_token = COALESCE(:anon, anonymous_token),
                    fcm_token     = COALESCE(:fcm, fcm_token),
                    device_type   = :type,
                    device_name   = :name,
                    ip            = :ip,
                    last_seen_at  = NOW(),
                    is_active     = 1,
                    updated_at    = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                ':uid'  => $userId,
                ':anon' => $anonToken,
                ':fcm'  => $fcmToken,
                ':type' => $deviceType,
                ':name' => $deviceName,
                ':ip'   => $ip,
                ':id'   => $device['id']
            ]);

            ResponseFormatter::success([
                'id' => (int)$device['id'],
                'updated' => true
            ]);
            exit;
        }

        // ======================
        // INSERT
        // ======================
        $stmt = $pdo->prepare("
            INSERT INTO user_devices (
                user_id, anonymous_token, fcm_token,
                device_type, device_name, user_agent,
                ip, last_seen_at, is_active, created_at
            ) VALUES (
                :uid, :anon, :fcm,
                :type, :name, :ua,
                :ip, NOW(), 1, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':uid'  => $userId,
            ':anon' => $anonToken,
            ':fcm'  => $fcmToken,
            ':type' => $deviceType,
            ':name' => $deviceName,
            ':ua'   => $ua,
            ':ip'   => $ip
        ]);

        ResponseFormatter::success([
            'id' => (int)$pdo->lastInsertId(),
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

        $stmt = $pdo->prepare("
            UPDATE user_devices
            SET is_active = 0
            WHERE fcm_token = ?
        ");
        $stmt->execute([$fcmToken]);

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