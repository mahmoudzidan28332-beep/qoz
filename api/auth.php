<?php
declare(strict_types=1);

/**
 * htdocs/api/auth.php
 *
 * Direct-access auth endpoint consumed by admin/assets/js/login.js
 * at fetch('/api/auth', ...).
 *
 * Path note: this file lives at htdocs/api/auth.php.
 *   $baseDir = __DIR__   → points to the api/ directory.
 *   dirname(__DIR__, 2)  would go TWO levels above api/, which is wrong here.
 * All shared includes use $baseDir directly.
 */

// ── Security / output ────────────────────────────────────────────────────────
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Global error handler — prevents bare 500 responses
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    error_log("[api/auth.php] PHP Error #{$errno}: {$errstr} in {$errfile}:{$errline}");
    return false; // let PHP handle it as well
});
set_exception_handler(function (Throwable $e): void {
    error_log('[api/auth.php] Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Internal server error'], JSON_UNESCAPED_UNICODE);
    exit;
});
// Catch fatal errors (out-of-memory, timeout, etc.)
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("[api/auth.php] FATAL: {$err['message']} in {$err['file']}:{$err['line']}");
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Internal server error', 'hint' => 'Check PHP error log'], JSON_UNESCAPED_UNICODE);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Security-Policy: default-src \'none\'');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// ── Early global per-IP rate limiter (auth endpoint) ────────────────────────
// Protects /api/auth from burst attacks (tighter than global: 5 req/min for auth)
(function () {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir = sys_get_temp_dir() . '/security_middleware/rate';
    @mkdir($dir, 0750, true);
    $file = $dir . '/' . hash('sha256', "failsafe:auth:ip:{$ip}") . '.json';
    $now  = time();
    $max  = 5;
    $win  = 60;

    $fh = @fopen($file, 'c+');
    if (!$fh) return;

    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = ($raw !== '' && $raw !== false) ? @json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['window_start'])) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    if ($data['window_start'] + $win <= $now) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    $data['count']++;
    fseek($fh, 0);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($data['count'] > $max) {
        $retryAfter = max(1, ($data['window_start'] + $win) - $now);
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . ($now + $retryAfter));
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
        exit;
    }
})();

// ── Path root ────────────────────────────────────────────────────────────────
// Correct base for this file: the api/ directory itself.
// Do NOT use dirname(__DIR__, 2) — that would escape above the project root.
$baseDir = __DIR__;

// ── Login rate limiter (file-based, no Redis needed) ────────────────────────
$_authRateLimiterDir = sys_get_temp_dir() . '/security_middleware/rate';
@mkdir($_authRateLimiterDir, 0750, true);

/**
 * Check if a key has exceeded the allowed number of requests in a time window.
 * Uses flock() for atomic read-modify-write under concurrent requests.
 * Returns ['allowed' => bool, 'current' => int, 'reset_in' => int].
 */
function _auth_rate_check(string $key, int $max, int $windowSeconds): array
{
    global $_authRateLimiterDir;
    $file = $_authRateLimiterDir . '/' . hash('sha256', 'auth:' . $key) . '.json';
    $now  = time();

    // Atomic read-modify-write with exclusive lock
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return ['allowed' => true, 'current' => 0, 'reset_in' => $windowSeconds];
    }

    flock($fh, LOCK_EX);

    $content = stream_get_contents($fh);
    $data = ($content !== '' && $content !== false) ? @json_decode($content, true) : null;
    if (!is_array($data) || !isset($data['window_start'])) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    if ($data['window_start'] + $windowSeconds <= $now) {
        $data = ['window_start' => $now, 'count' => 0];
    }
    $data['count']++;

    fseek($fh, 0);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $resetIn = max(0, ($data['window_start'] + $windowSeconds) - $now);
    return ['allowed' => $data['count'] <= $max, 'current' => $data['count'], 'reset_in' => $resetIn];
}

// ── Session — match APP_SESSID used by bootstrap_admin_ui.php ───────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    try {
        if (session_name() !== 'APP_SESSID') {
            session_name('APP_SESSID');
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        // Extract hostname without port for cookie domain
        $cookieDomain = '';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $cookieDomain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
        }
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => $cookieDomain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/', $cookieDomain, $secure, true);
        }
        if (!@session_start(['use_strict_mode' => true])) {
            error_log('[api/auth.php] session_start() failed — continuing without session');
        }
    } catch (Throwable $e) {
        error_log('[api/auth.php] Session init error: ' . $e->getMessage());
    }
}

// ── JSON helper ──────────────────────────────────────────────────────────────
function _auth_json(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// ── Database connection ──────────────────────────────────────────────────────
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

if (!$pdo instanceof PDO) {
    // Try shared config — path relative to this file's directory (api/).
    $dbCfgFile = $baseDir . '/shared/config/db.php';
    if (!$pdo instanceof PDO && is_file($dbCfgFile)) {
        $cfg = include $dbCfgFile;
        if (is_array($cfg)) {
            try {
                $host    = $cfg['host']     ?? ($cfg['DB_HOST'] ?? 'localhost');
                $dbname  = $cfg['name']     ?? ($cfg['dbname']  ?? ($cfg['DB_NAME'] ?? ''));
                $user    = $cfg['username'] ?? ($cfg['user']    ?? ($cfg['DB_USER'] ?? ''));
                $pass    = $cfg['password'] ?? ($cfg['pass']    ?? ($cfg['DB_PASS'] ?? ''));
                $charset = $cfg['charset']  ?? 'utf8mb4';
                $dsn     = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]);
                $GLOBALS['ADMIN_DB'] = $pdo;
            } catch (Throwable $e) {
                error_log('[api/auth.php] DB connect failed: ' . $e->getMessage());
            }
        }
    }
    // Fallback: DatabaseConnection class
    if (!$pdo instanceof PDO) {
        $dcFile = $baseDir . '/shared/core/DatabaseConnection.php';
        if (is_file($dcFile) && !class_exists('DatabaseConnection')) {
            @require_once $dcFile;
        }
        if (class_exists('DatabaseConnection')) {
            try {
                $maybe = DatabaseConnection::getConnection();
                if ($maybe instanceof PDO) {
                    $pdo = $maybe;
                    $GLOBALS['ADMIN_DB'] = $pdo;
                }
            } catch (Throwable $e) {
                error_log('[api/auth.php] DatabaseConnection failed: ' . $e->getMessage());
            }
        }
    }
}

if (!$pdo instanceof PDO) {
    _auth_json(false, 'Database unavailable', [], 503);
}

// ── Request method ───────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ── Payload reader ───────────────────────────────────────────────────────────
function _auth_payload(): array
{
    $raw = (string)@file_get_contents('php://input');
    if ($raw !== '') {
        $d = @json_decode($raw, true);
        if (is_array($d)) {
            return $d;
        }
    }
    return !empty($_POST) ? $_POST : [];
}

// ── Load RBAC (best-effort) ──────────────────────────────────────────────────
function _auth_rbac(PDO $pdo, int $userId, ?int $roleId): array
{
    $perms = [];
    $roles = [];
    try {
        // Check user_roles junction table first (many-to-many)
        $st = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        if ($st && $st->rowCount()) {
            $q = $pdo->prepare(
                "SELECT r.key_name FROM roles r
                 JOIN user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = ?"
            );
            $q->execute([$userId]);
            $r = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($r) {
                $roles = array_merge($roles, $r);
            }
        } elseif ($roleId) {
            $q = $pdo->prepare("SELECT key_name FROM roles WHERE id = ? LIMIT 1");
            $q->execute([$roleId]);
            $r = $q->fetchColumn();
            if ($r) {
                $roles[] = $r;
            }
        }
        // Check user_permissions junction table
        $st2 = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
        if ($st2 && $st2->rowCount()) {
            $q2 = $pdo->prepare(
                "SELECT p.key_name FROM permissions p
                 JOIN user_permissions up ON up.permission_id = p.id
                 WHERE up.user_id = ?"
            );
            $q2->execute([$userId]);
            $up = $q2->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($up) {
                $perms = array_merge($perms, $up);
            }
        }
        // Role permissions
        if ($roleId) {
            $q3 = $pdo->prepare(
                "SELECT p.key_name FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = ?"
            );
            $q3->execute([$roleId]);
            $rp = $q3->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp) {
                $perms = array_merge($perms, $rp);
            }
        } elseif (!empty($roles)) {
            $safeRoles = array_values(array_map('strval', $roles));
            $in = implode(',', array_fill(0, count($safeRoles), '?'));
            $q4 = $pdo->prepare(
                "SELECT DISTINCT p.key_name FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN roles r ON r.id = rp.role_id
                 WHERE r.key_name IN ($in)"
            );
            $q4->execute($safeRoles);
            $rp2 = $q4->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp2) {
                $perms = array_merge($perms, $rp2);
            }
        }
    } catch (Throwable $e) {
        error_log('[api/auth.php] RBAC error: ' . $e->getMessage());
    }
    return [
        'roles'       => array_values(array_unique($roles)),
        'permissions' => array_values(array_unique($perms)),
    ];
}

// ════════════════════════════════════════════════════════════════════════════
// GET — check / csrf / logout / me
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {
    $action = strtolower(trim($_GET['action'] ?? ''));

    if ($action === 'logout') {
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['roles']);
        $GLOBALS['ADMIN_USER'] = null;
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_regenerate_id(true);
        _auth_json(true, 'Logged out');
    }

    if ($action === 'me' || $action === 'check') {
        $u = $_SESSION['user'] ?? null;
        _auth_json(true, 'ok', ['authenticated' => (bool)$u, 'user' => $u]);
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }
        _auth_json(true, 'ok', ['csrf' => $_SESSION['csrf_token']]);
    }

    // Default GET: return session status
    $u = $_SESSION['user'] ?? null;
    _auth_json(true, 'ok', ['authenticated' => (bool)$u, 'user' => $u]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST — login / register
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $payload = _auth_payload();
    $action  = strtolower(trim((string)($payload['action'] ?? $_GET['action'] ?? 'login')));

    // ── REGISTER ─────────────────────────────────────────────────────────────
    if ($action === 'register') {
        $username = trim((string)($payload['username'] ?? ''));
        $email    = trim((string)($payload['email']    ?? ''));
        $password = (string)($payload['password']      ?? '');
        $phone    = trim((string)($payload['phone']    ?? ''));
        $lang     = preg_replace('/[^a-z\-]/', '', strtolower((string)($payload['preferred_language'] ?? 'en')));

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

        if ($errors) {
            _auth_json(false, 'Validation failed', ['errors' => $errors], 422);
        }

        try {
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                _auth_json(false, 'Username or email already exists', [], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())'
            );
            $ins->execute([$username, $email, $hash, $phone ?: null, $lang ?: 'en']);
            $newId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $user = [
                'id'                 => $newId,
                'username'           => $username,
                'email'              => $email,
                'role_id'            => null,
                'tenant_id'          => 1,
                'preferred_language' => $lang ?: 'en',
                'is_active'          => true,
                'roles'              => [],
                'permissions'        => [],
            ];
            $_SESSION['user_id']     = $newId;
            $_SESSION['user']        = $user;
            $_SESSION['permissions'] = [];
            $_SESSION['roles']       = [];
            $GLOBALS['ADMIN_USER']   = $user;

            _auth_json(true, 'Registration successful', ['user' => $user]);
        } catch (Throwable $e) {
            error_log('[api/auth.php] Register error: ' . $e->getMessage());
            _auth_json(false, 'Registration failed', [], 500);
        }
    }

    // ── LOGIN ─────────────────────────────────────────────────────────────────
    // Rate-limit login attempts per IP (10 attempts per 60 seconds)
    $loginIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateResult = _auth_rate_check('login:' . $loginIp, 5, 60);
    if (!$rateResult['allowed']) {
        header('Retry-After: ' . $rateResult['reset_in']);
        _auth_json(false, 'Too many login attempts. Please try again later.', [], 429);
    }

    $identifier = trim((string)($payload['username'] ?? $payload['email'] ?? $payload['identifier'] ?? ''));
    $password   = (string)($payload['password'] ?? '');

    if ($identifier === '' || $password === '') {
        _auth_json(false, 'Missing credentials', [], 400);
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            _auth_json(false, 'Invalid credentials', [], 401);
        }

        $hash     = $row['password_hash'] ?? $row['password'] ?? null;
        $verified = ($hash !== null) && password_verify($password, $hash);

        if (!$verified) {
            _auth_json(false, 'Invalid credentials', [], 401);
        }

        if (isset($row['is_active']) && !(bool)$row['is_active']) {
            _auth_json(false, 'Account disabled', [], 403);
        }

        $dbUserId = isset($row['id']) ? (int)$row['id'] : 0;

        // Fetch role_id and tenant_id from tenant_users (not stored on users table)
        $tenantRow = null;
        if ($dbUserId > 0) {
            try {
                $tuStmt = $pdo->prepare(
                    "SELECT tenant_id, role_id FROM tenant_users
                     WHERE user_id = ? AND is_active = 1
                     ORDER BY joined_at DESC LIMIT 1"
                );
                $tuStmt->execute([$dbUserId]);
                $tenantRow = $tuStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                error_log('[api/auth.php] tenant_users lookup error: ' . $e->getMessage());
            }
        }

        $roleId   = isset($tenantRow['role_id'])   ? (int)$tenantRow['role_id']   : null;
        $tenantId = isset($tenantRow['tenant_id']) ? (int)$tenantRow['tenant_id'] : 1;

        $rbac = _auth_rbac($pdo, $dbUserId, $roleId);

        session_regenerate_id(true);

        $user = [
            'id'                 => $dbUserId ?: null,
            'username'           => $row['username'] ?? null,
            'email'              => $row['email']    ?? null,
            'role_id'            => $roleId,
            'tenant_id'          => $tenantId,
            'preferred_language' => $row['preferred_language'] ?? 'en',
            'is_active'          => !empty($row['is_active']),
            'roles'              => $rbac['roles'],
            'permissions'        => $rbac['permissions'],
        ];

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['tenant_id']   = $tenantId;
        $_SESSION['user']        = $user;
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['roles']       = $user['roles'];
        $GLOBALS['ADMIN_USER']   = $user;

        _auth_json(true, 'Login successful', ['user' => $user]);

    } catch (Throwable $e) {
        error_log('[api/auth.php] Login error: ' . $e->getMessage());
        _auth_json(false, 'Authentication failed', [], 500);
    }
}

// ── Unsupported method ────────────────────────────────────────────────────────
_auth_json(false, 'Method not allowed', [], 405);