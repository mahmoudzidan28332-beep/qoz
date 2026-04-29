<?php
declare(strict_types=1);

/**
 * /api/shared/config/session.php
 * Unified & secure session kernel
 * Shared-hosting safe
 * Fixes empty session / cookie mismatch issues
 */

// منع التنفيذ من CLI
if (php_sapi_name() === 'cli') {
    return;
}

function session_request_host(): string
{
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
    $host = trim(explode(',', $host)[0] ?? '');
    return strtolower((string) preg_replace('/:\d+$/', '', $host));
}

function session_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}

function session_cookie_domain_for_host(string $host): string
{
    $configured = trim((string) (getenv('SESSION_COOKIE_DOMAIN') ?: ''));
    if ($configured !== '') {
        return $configured[0] === '.' ? $configured : '.' . $configured;
    }

    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return '';
    }

    return '';
}

function session_cleanup_legacy_cookie_variants(string $sessionName, string $host, string $cookieDomain, bool $isHttps, string $sameSite): void
{
    if (headers_sent() || $host === '') {
        return;
    }

    $domainsToExpire = [];

    if ($cookieDomain === '') {
        $domainsToExpire[] = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP) && $host !== 'localhost') {
            $domainsToExpire[] = '.' . ltrim($host, '.');
        }
    }

    $domainsToExpire = array_values(array_unique(array_filter($domainsToExpire)));

    foreach ($domainsToExpire as $domain) {
        setcookie($sessionName, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => $domain,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }
}

/**
 * Ensure session directory exists and is writable
 */
$sessionPath = __DIR__ . '/../../storage/sessions';

if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0700, true);
}

if (!is_writable($sessionPath)) {
    error_log('Session path is not writable: ' . $sessionPath);
}

// بدء الجلسة مرة واحدة فقط
if (session_status() === PHP_SESSION_NONE) {

    // ===== SESSION STORAGE =====
    ini_set('session.save_path', $sessionPath);
    error_log('[SESSION] save_path set to: ' . ini_get('session.save_path'));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // ===== COOKIE SETTINGS (HTTPS/PROXY AWARE) =====
    $host = session_request_host();
    $isHttps = session_request_is_https();
    $cookieDomain = session_cookie_domain_for_host($host);
    $sameSite = ($isHttps && $cookieDomain !== '') ? 'None' : 'Lax';
    $sessionName = 'APP_SESSID';

    // session_cleanup_legacy_cookie_variants($sessionName, $host, $cookieDomain, $isHttps, $sameSite);

    session_set_cookie_params([
        'lifetime' => 604800, // 7 days
        'path'     => '/',
        'domain'   => $cookieDomain,
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);

    // ===== SESSION NAME =====
    session_name($sessionName);

    // ===== START SESSION =====
    session_start();

    // ===== BASIC HARDENING =====
    if (empty($_SESSION['__initiated'])) {
        if (empty($_SESSION['user_id'])) {
            session_regenerate_id(true);
        }
        $_SESSION['__initiated'] = time();
    }

    // ===== DEBUG (REMOVE IN PROD IF NEEDED) =====
    error_log('[SESSION] started: ' . session_id() . ' | name=' . session_name() . ' | domain=' . $cookieDomain . ' | samesite=' . $sameSite);
}
/**
 * =========================
 * Helper functions
 * =========================
 */

/**
 * Regenerate session securely (on login)
 */
function regenerateSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['__regenerated_at'] = time();
    }
}

/**
 * Close session safely
 */
function secureSessionClose(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Check if session is valid (basic)
 */
function isSessionValid(): bool
{
    return !empty($_SESSION)
        && !empty($_SESSION['__initiated'])
        && !empty($_SESSION['user_id']);
}

/**
 * Destroy session completely (logout)
 */
function destroySession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
