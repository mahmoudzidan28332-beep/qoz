<?php
declare(strict_types=1);
/**
 * routes/verify_phone.php
 *
 * Device-bound phone verification endpoint.
 * Called automatically when the user opens the SMS activation link on their device.
 *
 * GET  /api/verify_phone?t=RAW_TOKEN
 *   - Validates the token hash against user_phone_verifications
 *   - Verifies the device cookie (qz_dvt) to confirm same browser/device
 *   - Activates the user account and creates an authenticated session
 *   - Redirects to the frontend verification page with the result
 *
 * POST /api/verify_phone  { token, device_token }
 *   - Same logic but accepts JSON payload (for JS-driven flow)
 */

// ---- Session bootstrap (must match auth.php settings) ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(0, '/', $cookieParams['domain'], $cookieParams['secure'], true);
    }
    @session_start();
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    _vpError('Database unavailable', 503);
    exit;
}

require_once dirname(__DIR__) . '/models/users_account/repositories/PdoUserPhoneVerificationsRepository.php';
require_once dirname(__DIR__) . '/models/users_account/repositories/PdoUsersRepository.php';
$phoneVerifRepo = new PdoUserPhoneVerificationsRepository($pdo);
$usersRepo = new PdoUsersRepository($pdo);

// ---- Read token ----
$rawToken    = '';
$rawDevice   = '';
$isJsonReq   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = @file_get_contents('php://input');
    $payload = $body ? (@json_decode($body, true) ?: []) : [];
    $payload = array_merge($_POST, $payload);
    $rawToken  = trim((string)($payload['token']        ?? ''));
    $rawDevice = trim((string)($payload['device_token'] ?? $_COOKIE['qz_dvt'] ?? ''));
    $isJsonReq = true;

    // ---- CSRF check for POST requests ----
    $csrfHeader = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $csrfPayload = trim((string)($payload['csrf_token'] ?? ''));
    $csrfSubmitted = $csrfHeader !== '' ? $csrfHeader : $csrfPayload;
    $csrfSession = (string)($_SESSION['csrf_token'] ?? '');
    if ($csrfSession === '' || $csrfSubmitted === '' || !hash_equals($csrfSession, $csrfSubmitted)) {
        _vpError('طلب غير صالح. يرجى إعادة تحميل الصفحة.', 403, true);
        exit;
    }
} else {
    // GET — token from URL, device from cookie
    $rawToken  = trim((string)($_GET['t'] ?? ''));
    $rawDevice = trim((string)($_COOKIE['qz_dvt'] ?? ''));
}

// ---- Helper to emit a redirect or JSON error ----
function _vpError(string $msg, int $code = 400, bool $json = false): void {
    if ($json) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $msg]);
    } else {
        // Redirect to frontend page with error
        $appUrl = _vp_app_url();
        $dest = $appUrl . '/frontend/verify_phone.php?status=error&msg=' . urlencode($msg);
        if (!headers_sent()) header('Location: ' . $dest, true, 302);
    }
}

function _vp_app_url(): string {
    if (defined('APP_URL')) return rtrim(APP_URL, '/');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Compare IP prefixes (/24 for IPv4, /64 for IPv6) — full match is too strict
// for mobile/CGNAT users whose IP may change between registration and verification.
function _ip_prefix(string $ip): string {
    if ($ip === '') return '';
    if (strpos($ip, ':') === false) {
        // IPv4 — compare first three octets (e.g. 1.2.3.*)
        $parts = explode('.', $ip, 4);
        return implode('.', array_slice($parts, 0, 3));
    }
    // IPv6 — compare /64 prefix (first 8 bytes)
    $bin = inet_pton($ip);
    if ($bin === false) return $ip;
    return bin2hex(substr($bin, 0, 8));
}

if ($rawToken === '') {
    _vpError('Missing verification token', 400, $isJsonReq);
    exit;
}

$tokenHash  = hash('sha256', $rawToken);
$deviceHash = ($rawDevice !== '') ? hash('sha256', $rawDevice) : '';

try {
    // Look up pending verification record
    $row = $phoneVerifRepo->findPendingByTokenHash($tokenHash);

    if (!$row) {
        // Token not found or already used — check if the user is already active
        $usedRow = $phoneVerifRepo->findUsedTokenUserStatus($tokenHash);
        if ($usedRow && (int)$usedRow['is_active'] === 1) {
            // Account is already active — treat as success
            if ($isJsonReq) {
                if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'تم تفعيل الحساب بنجاح.']);
            } else {
                $dest = _vp_app_url() . '/frontend/verify_phone.php?status=success';
                if (!headers_sent()) header('Location: ' . $dest, true, 302);
            }
            exit;
        }
        _vpError('رابط التفعيل غير صالح أو تم استخدامه مسبقاً.', 404, $isJsonReq);
        exit;
    }

    // Check expiry
    if (strtotime($row['expires_at']) < time()) {
        _vpError('انتهت صلاحية رابط التفعيل. يرجى التسجيل مجدداً.', 410, $isJsonReq);
        exit;
    }

    // ---- Enforce device binding (cookie) ----
    // The qz_dvt cookie is set during registration and binds the token to that browser/device.
    // If the link is opened on a different device (e.g. shared via WhatsApp), the cookie
    // will be absent and activation must be refused.
    if ($row['device_hash'] !== '' && ($deviceHash === '' || $deviceHash !== $row['device_hash'])) {
        _vpError('يجب فتح رابط التفعيل من نفس الجهاز والمتصفح الذي أجريت منه التسجيل.', 403, $isJsonReq);
        exit;
    }

    // ---- Enforce session binding ----
    // Check 1: session_id stored at registration must match the current session.
    $storedSessionId = (string)($row['session_id'] ?? '');
    if ($storedSessionId !== '' && session_id() !== $storedSessionId) {
        _vpError('يجب فتح رابط التفعيل من نفس جلسة المتصفح الذي أجريت منه التسجيل.', 403, $isJsonReq);
        exit;
    }
    // Check 2: pending_user_id in session must match the user in the token.
    $sessionPendingId = isset($_SESSION['pending_user_id']) ? (int)$_SESSION['pending_user_id'] : 0;
    if ($sessionPendingId !== (int)$row['user_id']) {
        _vpError('يجب فتح رابط التفعيل من نفس المتصفح الذي أجريت منه التسجيل.', 403, $isJsonReq);
        exit;
    }

    // ---- Enforce IP binding (prefix only — ISPs and mobile networks change IP) ----
    $currentIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $storedIp  = (string)($row['ip'] ?? '');
    if ($storedIp !== '' && $currentIp !== ''
        && _ip_prefix($currentIp) !== _ip_prefix($storedIp)) {
        _vpError('رابط التفعيل غير صالح من هذا العنوان. يجب التفعيل من نفس الشبكة.', 403, $isJsonReq);
        exit;
    }

    $userId = (int)$row['user_id'];

    // ---- Fetch user record BEFORE making any DB changes ----
    // Doing this first ensures that if the SELECT fails (e.g. unexpected schema
    // difference) the activation UPDATE has not yet run, so the account stays
    // inactive and the token stays unused — no partial state is left behind.
    $userData = $usersRepo->findBasicById($userId);

    if (!$userData) {
        _vpError('لم يُعثر على الحساب المرتبط بهذا الرابط.', 404, $isJsonReq);
        exit;
    }

    // ---- Activate the user ----
    // Omit updated_at from the SET clause — it may be absent on some installs;
    // if the column has ON UPDATE CURRENT_TIMESTAMP MySQL will update it anyway.
    $usersRepo->activateUser($userId);
    // rowCount() == 0 means account was already active; token is still marked used below.

    // Mark token as used (one-time)
    $phoneVerifRepo->markUsed($row['id']);

    // Build user object for session and response
    $user = [
        'id'                 => (int)$userData['id'],
        'name'               => $userData['username'],
        'username'           => $userData['username'],
        'email'              => $userData['email'],
        'phone'              => $userData['phone'],
        'role_id'            => $userData['role_id'] ?? null,
        'preferred_language' => $userData['preferred_language'],
        'is_active'          => true,
        'permissions'        => [],
        'roles'              => [],
        'permissions_count'  => 0,
        'roles_count'        => 0,
    ];

    // Create authenticated session — wrapped separately so that any session/cookie
    // warning (converted to ErrorException by ExceptionHandler) does NOT hide the
    // successful activation that already happened in the DB above.
    try {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user']      = $user;
        $GLOBALS['ADMIN_USER'] = $user;
        unset($_SESSION['pending_user_id']);
    } catch (Throwable $sessionErr) {
        if (class_exists('Logger')) Logger::warning('verify_phone: session setup failed after activation: ' . $sessionErr->getMessage());
    }

    // Expire the device cookie — also non-fatal
    try {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if (!headers_sent()) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie('qz_dvt', '', ['expires' => time() - 3600, 'path' => '/',
                                         'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
            } else {
                setcookie('qz_dvt', '', time() - 3600, '/', '', $secure, true);
            }
        }
    } catch (Throwable $cookieErr) {
        if (class_exists('Logger')) Logger::warning('verify_phone: cookie cleanup failed: ' . $cookieErr->getMessage());
    }

    if ($isJsonReq) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'تم تفعيل الحساب بنجاح.', 'user' => $user]);
    } else {
        // Redirect to frontend success page
        $dest = _vp_app_url() . '/frontend/verify_phone.php?status=success';
        if (!headers_sent()) header('Location: ' . $dest, true, 302);
    }

} catch (Throwable $e) {
    if (class_exists('Logger')) Logger::error('verify_phone error: ' . $e->getMessage());
    _vpError('حدث خطأ أثناء التفعيل. يرجى المحاولة مجدداً.', 500, $isJsonReq);
}
exit;