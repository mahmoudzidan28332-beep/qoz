<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

function _auth_log(string $message): void
{
    static $logFile = null;

    if ($logFile === null) {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $logFile = is_writable($dir) ? $dir . '/auth_errors.log' : false;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if ($logFile) {
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    error_log($message);
}

set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    _auth_log("[api/auth.php] PHP Error #{$errno}: {$errstr} in {$errfile}:{$errline}");
    return false;
});

set_exception_handler(static function (Throwable $e): void {
    _auth_log('[api/auth.php] Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode(['success' => false, 'message' => 'Internal server error'], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    _auth_log("[api/auth.php] FATAL: {$error['message']} in {$error['file']}:{$error['line']}");
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'hint' => 'Check logs/auth_errors.log',
    ], JSON_UNESCAPED_UNICODE);
});

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$baseDir = __DIR__;

$sharedRequires = [
    $baseDir . '/shared/config/session.php',
    $baseDir . '/shared/core/DatabaseConnection.php',
    $baseDir . '/shared/core/CacheManager.php',
    $baseDir . '/shared/helpers/RedisHelper.php',
    $baseDir . '/shared/helpers/RBAC.php',
    $baseDir . '/shared/helpers/jwt.php',
    $baseDir . '/shared/application/Auth/UserIdentity.php',
    $baseDir . '/shared/application/Auth/UserIdentityResolver.php',
];

foreach ($sharedRequires as $sharedRequire) {
    if (is_file($sharedRequire)) {
        require_once $sharedRequire;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    _auth_log('[api/auth.php] Unified session kernel did not start an active session');
}

(static function (): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir = sys_get_temp_dir() . '/security_middleware/rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $file = $dir . '/' . hash('sha256', 'failsafe:auth:ip:' . $ip) . '.json';
    $now = time();
    $max = 5;
    $window = 60;
    $handle = @fopen($file, 'c+');

    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = ($raw !== '' && $raw !== false) ? @json_decode($raw, true) : null;

    if (!is_array($state) || !isset($state['window_start'])) {
        $state = ['window_start' => $now, 'count' => 0];
    }

    if (($state['window_start'] + $window) <= $now) {
        $state = ['window_start' => $now, 'count' => 0];
    }

    $state['count']++;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($state['count'] <= $max) {
        return;
    }

    $retryAfter = max(1, ($state['window_start'] + $window) - $now);
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    header('X-RateLimit-Limit: ' . $max);
    header('X-RateLimit-Remaining: 0');
    header('X-RateLimit-Reset: ' . ($now + $retryAfter));
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
})();

$_authRateLimiterDir = sys_get_temp_dir() . '/security_middleware/rate';
@mkdir($_authRateLimiterDir, 0750, true);

function _auth_rate_check(string $key, int $max, int $windowSeconds): array
{
    global $_authRateLimiterDir;

    $file = $_authRateLimiterDir . '/' . hash('sha256', 'auth:' . $key) . '.json';
    $now = time();
    $handle = @fopen($file, 'c+');

    if ($handle === false) {
        return ['allowed' => true, 'current' => 0, 'reset_in' => $windowSeconds];
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = ($raw !== '' && $raw !== false) ? @json_decode($raw, true) : null;

    if (!is_array($state) || !isset($state['window_start'])) {
        $state = ['window_start' => $now, 'count' => 0];
    }

    if (($state['window_start'] + $windowSeconds) <= $now) {
        $state = ['window_start' => $now, 'count' => 0];
    }

    $state['count']++;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return [
        'allowed' => $state['count'] <= $max,
        'current' => $state['count'],
        'reset_in' => max(0, ($state['window_start'] + $windowSeconds) - $now),
    ];
}

function _auth_json(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function _auth_reject_disallowed_bearer_token(): void
{
    if (!class_exists('JWT', false)) {
        return;
    }

    $token = \JWT::getBearerToken();
    if ($token === null) {
        return;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        _auth_log('[api/auth.php] Rejected malformed bearer token');
        _auth_json(false, 'Invalid token', [], 401);
    }

    $headerJson = base64_decode(strtr($parts[0], '-_', '+/') . str_repeat('=', (4 - strlen($parts[0]) % 4) % 4), true);
    $header = is_string($headerJson) ? json_decode($headerJson, true) : null;
    $alg = is_array($header) ? (string) ($header['alg'] ?? '') : '';

    if (strcasecmp($alg, 'none') === 0) {
        _auth_log('[api/auth.php] Rejected bearer token with alg=none');
        _auth_json(false, 'Invalid token algorithm', [], 401);
    }

    $allowedAlgorithms = \JWT::getAllowedAlgorithms();
    if (in_array($alg, $allowedAlgorithms, true) && \JWT::hasAllowedHeaderAlgorithm($token)) {
        return;
    }

    _auth_log('[api/auth.php] Rejected bearer token due to disallowed JWT alg header');
    _auth_json(false, 'Invalid token algorithm', [], 401);
}

function _auth_payload(): array
{
    $raw = (string) @file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = @json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return !empty($_POST) ? $_POST : [];
}

function _auth_resolve_identity(PDO $pdo): \Shared\Application\Auth\UserIdentity
{
    return \Shared\Application\Auth\UserIdentityResolver::resolve($pdo, [
        'request_id' => bin2hex(random_bytes(8)),
        'force' => true,
    ]);
}

function _auth_identity_debug(\Shared\Application\Auth\UserIdentity $identity): array
{
    return [
        'resolved_user_id' => $identity->id(),
        'resolved_tenant_id' => $identity->tenantId(),
        'source' => $identity->source(),
        'request_id' => $identity->requestId(),
    ];
}

function _auth_get_pdo(string $baseDir): ?PDO
{
    $pdo = $GLOBALS['ADMIN_DB'] ?? null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (class_exists('DatabaseConnection', false)) {
        try {
            $pdo = DatabaseConnection::getConnection();
            if ($pdo instanceof PDO) {
                $GLOBALS['ADMIN_DB'] = $pdo;
                return $pdo;
            }
        } catch (Throwable $e) {
            _auth_log('[api/auth.php] DatabaseConnection failed: ' . $e->getMessage());
        }
    }

    $dbCfgFile = $baseDir . '/shared/config/db.php';
    if (!is_file($dbCfgFile)) {
        return null;
    }

    $cfg = include $dbCfgFile;
    if (!is_array($cfg)) {
        return null;
    }

    try {
        $host = $cfg['host'] ?? ($cfg['DB_HOST'] ?? 'localhost');
        $dbname = $cfg['name'] ?? ($cfg['dbname'] ?? ($cfg['DB_NAME'] ?? ''));
        $user = $cfg['username'] ?? ($cfg['user'] ?? ($cfg['DB_USER'] ?? ''));
        $pass = $cfg['password'] ?? ($cfg['pass'] ?? ($cfg['DB_PASS'] ?? ''));
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $GLOBALS['ADMIN_DB'] = $pdo;

        return $pdo;
    } catch (Throwable $e) {
        _auth_log('[api/auth.php] DB connect failed: ' . $e->getMessage());
        return null;
    }
}

_auth_reject_disallowed_bearer_token();

$pdo = _auth_get_pdo($baseDir);
if (!$pdo instanceof PDO) {
    _auth_json(false, 'Database unavailable', [], 503);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $action = strtolower(trim((string) ($_GET['action'] ?? '')));

    if ($action === 'logout') {
        \Shared\Application\Auth\UserIdentityResolver::clearSessionIdentity();
        if (function_exists('destroySession')) {
            destroySession();
        }

        _auth_json(true, 'Logged out');
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }

        _auth_json(true, 'ok', ['csrf' => $_SESSION['csrf_token']]);
    }

    $identity = _auth_resolve_identity($pdo);
    $user = $identity->isAuthenticated() ? $identity->toArray() : null;

    _auth_json(true, 'ok', [
        'authenticated' => $identity->isAuthenticated(),
        'user' => $user,
        'debug' => _auth_identity_debug($identity),
    ]);
}

if ($method !== 'POST') {
    _auth_json(false, 'Method not allowed', [], 405);
}

$payload = _auth_payload();
$action = strtolower(trim((string) ($payload['action'] ?? ($_GET['action'] ?? 'login'))));

if ($action === 'register') {
    $username = trim((string) ($payload['username'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $phone = trim((string) ($payload['phone'] ?? ''));
    $language = preg_replace('/[^a-z\-]/', '', strtolower((string) ($payload['preferred_language'] ?? 'en')));

    $errors = [];
    if ($username === '') {
        $errors['username'] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3-50 alphanumeric characters or underscores';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email address';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }

    if ($errors !== []) {
        _auth_json(false, 'Validation failed', ['errors' => $errors], 422);
    }

    try {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            _auth_json(false, 'Username or email already exists', [], 409);
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );
        $insert->execute([
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone !== '' ? $phone : null,
            $language !== '' ? $language : 'en',
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        if (function_exists('regenerateSession')) {
            regenerateSession();
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['tenant_id'] = 1;
        $_SESSION['user'] = [
            'id' => $newUserId,
            'tenant_id' => 1,
            'preferred_language' => $language !== '' ? $language : 'en',
        ];

        \Shared\Application\Auth\UserIdentityResolver::forgetResolvedIdentity();
        $identity = _auth_resolve_identity($pdo);
        $GLOBALS['ADMIN_USER'] = $identity->isAuthenticated() ? $identity->toArray() : null;
        $GLOBALS['ADMIN_IDENTITY'] = $identity;

        _auth_json(true, 'Registration successful', [
            'user' => $identity->toArray(),
            'debug' => _auth_identity_debug($identity),
        ]);
    } catch (Throwable $e) {
        _auth_log('[api/auth.php] Register error: ' . $e->getMessage());
        _auth_json(false, 'Registration failed', [], 500);
    }
}

$loginIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateResult = _auth_rate_check('login:' . $loginIp, 5, 60);
if (!$rateResult['allowed']) {
    header('Retry-After: ' . $rateResult['reset_in']);
    _auth_json(false, 'Too many login attempts. Please try again later.', [], 429);
}

$identifier = trim((string) ($payload['username'] ?? $payload['email'] ?? $payload['identifier'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($identifier === '' || $password === '') {
    _auth_json(false, 'Missing credentials', [], 400);
}

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        _auth_json(false, 'Invalid credentials', [], 401);
    }

    $hash = $row['password_hash'] ?? ($row['password'] ?? null);
    $verified = $hash !== null && password_verify($password, $hash);
    if (!$verified) {
        _auth_json(false, 'Invalid credentials', [], 401);
    }

    if (isset($row['is_active']) && !(bool) $row['is_active']) {
        _auth_json(false, 'Account disabled', [], 403);
    }

    $userId = isset($row['id']) ? (int) $row['id'] : 0;
    $tenantStmt = $pdo->prepare(
        'SELECT tenant_id, role_id
         FROM tenant_users
         WHERE user_id = ? AND is_active = 1
         ORDER BY joined_at DESC
         LIMIT 1'
    );
    $tenantStmt->execute([$userId]);
    $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $tenantId = isset($tenantRow['tenant_id']) ? (int) $tenantRow['tenant_id'] : null;
    $roleId = isset($tenantRow['role_id']) ? (int) $tenantRow['role_id'] : null;

    if (function_exists('regenerateSession')) {
        regenerateSession();
    } elseif (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $userId;
    if ($tenantId !== null) {
        $_SESSION['tenant_id'] = $tenantId;
    } else {
        unset($_SESSION['tenant_id']);
    }
    $_SESSION['user'] = [
        'id' => $userId,
        'tenant_id' => $tenantId,
        'role_id' => $roleId,
        'username' => $row['username'] ?? null,
        'email' => $row['email'] ?? null,
    ];

    \Shared\Application\Auth\UserIdentityResolver::forgetResolvedIdentity();
    $identity = _auth_resolve_identity($pdo);
    $GLOBALS['ADMIN_USER'] = $identity->isAuthenticated() ? $identity->toArray() : null;
    $GLOBALS['ADMIN_IDENTITY'] = $identity;

    _auth_json(true, 'Login successful', [
        'user' => $identity->toArray(),
        'debug' => _auth_identity_debug($identity),
    ]);
} catch (Throwable $e) {
    _auth_log('[api/auth.php] Login error: ' . $e->getMessage());
    _auth_json(false, 'Authentication failed', [], 500);
}
