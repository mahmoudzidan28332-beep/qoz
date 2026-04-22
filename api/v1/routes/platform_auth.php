<?php
declare(strict_types=1);

/**
 * api/v1/routes/platform_auth.php
 *
 * Dedicated authentication endpoint for Platform Admin users.
 * Only users present in `platform_users` (with is_active = 1) may authenticate here.
 *
 * POST /api/platform_auth
 *   Body (form or JSON): { identifier, password, csrf_token }
 *   → Sets session and returns success with redirect hint.
 *
 * GET /api/platform_auth?action=logout
 *   → Destroys platform admin session.
 *
 * GET /api/platform_auth?action=me
 *   → Returns current platform admin session info.
 */

// ── Session ──────────────────────────────────────────────────────────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (session_name() !== 'APP_SESSID') {
        session_name('APP_SESSID');
    }
    $cp = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    PHP_VERSION_ID >= 70300
        ? session_set_cookie_params($cp)
        : session_set_cookie_params($cp['lifetime'], $cp['path'], $cp['domain'], $cp['secure'], $cp['httponly']);
    @session_start();
}

// ── Bootstrap / DB ───────────────────────────────────────────────────────────
$_baseDir = dirname(__DIR__, 2);
require_once $_baseDir . '/shared/core/ResponseFormatter.php';

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::serverError('Database unavailable');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function _pa_no_cache(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

function _pa_read_payload(): array
{
    $raw = @file_get_contents('php://input');
    if ($raw) {
        $d = @json_decode($raw, true);
        if (is_array($d)) {
            return $d;
        }
    }
    return $_POST ?: [];
}

function _pa_rate_check(string $key, int $max = 10, int $window = 600): bool
{
    $dir  = sys_get_temp_dir() . '/security_middleware/pa_rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $file = $dir . '/' . hash('sha256', 'pa_login:' . $key) . '.json';
    $now  = time();

    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return true;
    }
    flock($fh, LOCK_EX);
    $content = stream_get_contents($fh);
    $data = ($content !== '' && $content !== false) ? @json_decode($content, true) : null;
    if (!is_array($data) || !isset($data['window_start'])) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    if ($data['window_start'] + $window <= $now) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    $data['count']++;
    fseek($fh, 0);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $data['count'] <= $max;
}

// ── Routing ───────────────────────────────────────────────────────────────────
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action  = strtolower(trim((string)($_GET['action'] ?? '')));

_pa_no_cache();

// ── GET: logout / me ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($action === 'logout') {
        unset(
            $_SESSION['platform_admin'],
            $_SESSION['platform_role'],
            $_SESSION['platform_user_id'],
            $_SESSION['user'],
            $_SESSION['user_id'],
            $_SESSION['permissions'],
            $_SESSION['roles']
        );
        session_regenerate_id(true);
        ResponseFormatter::success(['ok' => true, 'message' => 'Logged out']);
        exit;
    }

    if ($action === 'me') {
        if (empty($_SESSION['platform_admin'])) {
            ResponseFormatter::error('Not authenticated', 401);
            exit;
        }
        ResponseFormatter::success([
            'ok'           => true,
            'user_id'      => $_SESSION['platform_user_id'] ?? null,
            'role'         => $_SESSION['platform_role'] ?? null,
            'username'     => $_SESSION['user']['username'] ?? null,
        ]);
        exit;
    }

    ResponseFormatter::error('Invalid action', 400);
    exit;
}

// ── POST: login ───────────────────────────────────────────────────────────────
if ($method !== 'POST') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

$payload    = _pa_read_payload();
$identifier = trim((string)($payload['identifier'] ?? $payload['username'] ?? ''));
$password   = (string)($payload['password'] ?? '');
$csrfToken  = (string)($payload['csrf_token'] ?? '');

// ── CSRF ──────────────────────────────────────────────────────────────────────
$sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $csrfToken)) {
    ResponseFormatter::error('Invalid request token. Please reload the page.', 403);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────
if ($identifier === '') {
    ResponseFormatter::error('Username or email is required', 422, ['identifier' => 'This field is required']);
    exit;
}
if ($password === '') {
    ResponseFormatter::error('Password is required', 422, ['password' => 'This field is required']);
    exit;
}

// ── Rate limiting ─────────────────────────────────────────────────────────────
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!_pa_rate_check($ip . ':' . $identifier)) {
    ResponseFormatter::error('Too many login attempts. Please try again later.', 429);
    exit;
}

// ── Fetch user from `users` table ─────────────────────────────────────────────
try {
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
    if ($isEmail) {
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, is_active FROM users WHERE email = :val LIMIT 1');
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, is_active FROM users WHERE username = :val LIMIT 1');
    }
    $stmt->execute([':val' => $identifier]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[platform_auth] DB error fetching user: ' . $e->getMessage());
    ResponseFormatter::serverError('Authentication failed. Please try again.');
    exit;
}

if (!$userRow) {
    // Generic message — do not reveal whether user exists
    ResponseFormatter::error('Invalid credentials', 401);
    exit;
}

// ── Verify password ───────────────────────────────────────────────────────────
$hash = (string)($userRow['password_hash'] ?? '');
if ($hash === '' || !password_verify($password, $hash)) {
    ResponseFormatter::error('Invalid credentials', 401);
    exit;
}

$userId = (int)$userRow['id'];

// ── Check platform_users table ────────────────────────────────────────────────
try {
    $stmtPu = $pdo->prepare(
        'SELECT id, role_key, is_active FROM platform_users WHERE user_id = :uid LIMIT 1'
    );
    $stmtPu->execute([':uid' => $userId]);
    $puRow = $stmtPu->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[platform_auth] DB error fetching platform_users: ' . $e->getMessage());
    ResponseFormatter::serverError('Authentication failed. Please try again.');
    exit;
}

if (!$puRow) {
    ResponseFormatter::error('Access denied. This account is not a platform admin.', 403);
    exit;
}

if (!(bool)$puRow['is_active']) {
    ResponseFormatter::error('Your platform admin account has been deactivated.', 403);
    exit;
}

if (!(bool)$userRow['is_active']) {
    ResponseFormatter::error('Your account has been deactivated.', 403);
    exit;
}

$roleKey = (string)$puRow['role_key'];

// ── Build session ─────────────────────────────────────────────────────────────
session_regenerate_id(true);

$user = [
    'id'                 => $userId,
    'name'               => $userRow['username'],
    'username'           => $userRow['username'],
    'email'              => $userRow['email'],
    'role_id'            => null,
    'tenant_id'          => null,
    'preferred_language' => 'en',
    'is_active'          => true,
    'permissions'        => [],
    'roles'              => [$roleKey, 'super_admin'],   // always include super_admin for is_super_admin() check
];

$_SESSION['user_id']          = $userId;
$_SESSION['user']             = $user;
$_SESSION['permissions']      = $user['permissions'];
$_SESSION['roles']            = $user['roles'];
$_SESSION['platform_admin']   = true;
$_SESSION['platform_role']    = $roleKey;
$_SESSION['platform_user_id'] = (int)$puRow['id'];
// Keep logged_in flag for auth_guard.php compatibility
$_SESSION['logged_in']        = true;
$_SESSION['last_activity']    = time();

$GLOBALS['ADMIN_USER'] = $user;

ResponseFormatter::success([
    'ok'       => true,
    'message'  => 'Authenticated',
    'redirect' => '/admin/dashboard.php',
    'role'     => $roleKey,
    'username' => $userRow['username'],
]);
exit;
