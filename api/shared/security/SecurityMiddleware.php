<?php

declare(strict_types=1);

require_once __DIR__ . '/SecurityDetectors.php';
require_once __DIR__ . '/SecurityRateLimiter.php';
require_once __DIR__ . '/SecurityTracking.php';
require_once __DIR__ . '/SecurityValidators.php';

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║   RUNTIME SECURITY MIDDLEWARE  v1.0                                        ║
 * ║   Drop-in security layer for PHP REST APIs                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                 ║
 * ║   • SQL injection detection & blocking                                     ║
 * ║   • XSS detection & sanitization                                          ║
 * ║   • Rate limiting per IP and per user (file-based, no Redis required)      ║
 * ║   • Suspicious activity detection (repeated failures, probing)             ║
 * ║   • Automatic temporary IP blocking                                        ║
 * ║   • Full request logging (IP, user, endpoint, payload, timing)             ║
 * ║   • Configurable rules (thresholds, limits, whitelists)                    ║
 * ║                                                                            ║
 * ║  Integration (bootstrap.php or index.php — runs BEFORE routing):          ║
 * ║   require 'SecurityMiddleware.php';                                        ║
 * ║   SecurityMiddleware::boot();                                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 *
 * @version 1.0.0
 * @license MIT
 */

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Configuration
// ════════════════════════════════════════════════════════════════════════════════

final class SecurityConfig
{
    // ── Storage ──────────────────────────────────────────────────────────────────
    /** Directory where rate-limit counters, blocks, and logs are stored. */
    public static string $storageDir = '/tmp/security_middleware';

    // ── Rate Limiting ────────────────────────────────────────────────────────────
    /** Max requests per window per IP (global). */
    public static int $rateLimitIpMax     = 120;
    public static int $rateLimitIpWindow  = 60;   // seconds

    /** Max requests per window per authenticated user. */
    public static int $rateLimitUserMax    = 200;
    public static int $rateLimitUserWindow = 60;  // seconds

    /** Tighter limit for auth/login endpoints. */
    public static int $rateLimitAuthMax    = 10;
    public static int $rateLimitAuthWindow = 60;

    /** Tighter limit for write operations (POST/PUT/PATCH/DELETE). */
    public static int $rateLimitWriteMax    = 60;
    public static int $rateLimitWriteWindow = 60;

    // ── Blocking ─────────────────────────────────────────────────────────────────
    /** Number of violations before an IP is temporarily blocked. */
    public static int $blockAfterViolations = 5;

    /** How long an IP stays blocked (seconds). */
    public static int $blockDuration        = 300; // 5 minutes

    /** Permanent block threshold (violations across multiple block cycles). */
    public static int $permanentBlockAfter  = 50;

    // ── Suspicious Activity ──────────────────────────────────────────────────────
    /** Consecutive 401/403 responses before flagging as suspicious. */
    public static int $suspiciousAuthFailures = 5;

    /** Consecutive 404 responses before flagging as probing. */
    public static int $suspicious404Count    = 10;

    /** Time window for suspicious activity tracking (seconds). */
    public static int $suspiciousWindow      = 60;

    // ── Logging ──────────────────────────────────────────────────────────────────
    public static bool   $enableLogging      = true;
    public static string $logFile            = '';    // auto-set in init()
    public static int    $logMaxSizeMb       = 50;   // rotate when > 50 MB
    public static bool   $logPayloads        = true;  // log request bodies
    public static int    $logPayloadMaxBytes = 2048;  // truncate long bodies

    // ── SQL Injection Detection ──────────────────────────────────────────────────
    /** True = block (return 400) on detection. False = log only. */
    public static bool $sqliBlock           = true;

    // ── XSS Detection ────────────────────────────────────────────────────────────
    /** True = sanitize inputs automatically. False = block request. */
    public static bool $xssSanitize         = true;

    // ── Public Paths ─────────────────────────────────────────────────────────────
    /** Paths that bypass rate limiting. Health is rate-limited to prevent burst abuse. */
    public static array $rateLimitWhitelist = ['/ping', '/status'];

    /** IPs that are never blocked (e.g. your office, monitoring server). */
    public static array $ipWhitelist        = ['127.0.0.1', '::1'];

    // ── JWT ──────────────────────────────────────────────────────────────────────
    /** If non-empty, the middleware also validates the JWT algorithm. */
    public static string $jwtSecret         = '';
    public static array  $allowedJwtAlgs    = ['HS256', 'HS384', 'HS512', 'RS256'];

    // ── Response ─────────────────────────────────────────────────────────────────
    /** Add these security headers to every response. */
    public static array $securityHeaders = [
        'X-Content-Type-Options'    => 'nosniff',
        'X-Frame-Options'           => 'SAMEORIGIN',
        'X-XSS-Protection'          => '1; mode=block',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy'   => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'",
    ];
}

// ════════════════════════════════════════════════════════════════════════════════
// The Main Middleware Class
// ════════════════════════════════════════════════════════════════════════════════

final class SecurityMiddleware
{
    private static RateLimiter              $rateLimiter;
    private static IpBlocker                $ipBlocker;
    private static SuspiciousActivityTracker $tracker;
    private static RequestLogger             $logger;
    private static float                     $requestStartTime;

    /**
     * Boot the middleware.
     *
     * Call this as the VERY FIRST thing in bootstrap.php / index.php,
     * before any routing or controller logic.
     *
     * @param array<string, mixed> $options  Override SecurityConfig properties
     */
    public static function boot(array $options = []): void
    {
        // Apply overrides
        foreach ($options as $key => $value) {
            if (property_exists(SecurityConfig::class, $key)) {
                SecurityConfig::$$key = $value;
            }
        }

        // Ensure storage directory exists
        @mkdir(SecurityConfig::$storageDir, 0750, true);

        self::$rateLimiter = new RateLimiter(SecurityConfig::$storageDir);
        self::$ipBlocker   = new IpBlocker(SecurityConfig::$storageDir);
        self::$tracker     = new SuspiciousActivityTracker(SecurityConfig::$storageDir);
        self::$logger      = new RequestLogger();
        self::$requestStartTime = microtime(true);

        // Add security response headers immediately
        self::sendSecurityHeaders();

        // Run all checks
        self::checkIpBlock();
        self::checkRateLimit();
        self::checkSqlInjection();
        self::checkXss();
        self::checkJwt();

        // Register a shutdown function to log the completed request
        register_shutdown_function([self::class, 'onRequestComplete']);
    }

    // ── Checks ────────────────────────────────────────────────────────────────────

    /**
     * Block immediately if IP is on the block list.
     */
    private static function checkIpBlock(): void
    {
        $ip = self::clientIp();

        if (self::$ipBlocker->isBlocked($ip)) {
            $retryAfter = self::$ipBlocker->blockedUntil($ip);
            self::$logger->logBlock($ip, 'IP is blocked');
            self::abort(403, 'Access denied.', ['Retry-After' => $retryAfter === PHP_INT_MAX ? '0' : (string) $retryAfter]);
        }
    }

    /**
     * Apply rate limiting based on endpoint type and method.
     */
    private static function checkRateLimit(): void
    {
        $ip   = self::clientIp();
        $path = self::requestPath();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Whitelisted paths bypass rate limiting
        foreach (SecurityConfig::$rateLimitWhitelist as $whitelisted) {
            if (str_starts_with($path, $whitelisted)) {
                return;
            }
        }

        // Select the appropriate limit
        $isAuth  = str_contains($path, '/auth/') || str_ends_with($path, '/auth')
                   || str_contains($path, '/login') || str_contains($path, '/register');
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($isAuth) {
            $max    = SecurityConfig::$rateLimitAuthMax;
            $window = SecurityConfig::$rateLimitAuthWindow;
        } elseif ($isWrite) {
            $max    = SecurityConfig::$rateLimitWriteMax;
            $window = SecurityConfig::$rateLimitWriteWindow;
        } else {
            $max    = SecurityConfig::$rateLimitIpMax;
            $window = SecurityConfig::$rateLimitIpWindow;
        }

        $key    = "ip:{$ip}:path:{$path}:method:{$method}";
        $result = self::$rateLimiter->checkWithHeaders($key, $max, $window);

        // Add rate-limit headers to response
        foreach ($result['headers'] as $name => $value) {
            header("{$name}: {$value}");
        }

        if (!$result['allowed']) {
            self::$logger->logSecurityEvent('RATE_LIMIT_EXCEEDED', $ip, $path, [
                'limit'  => $max,
                'window' => $window,
            ]);
            self::$ipBlocker->recordViolation($ip, 'Rate limit exceeded');
            self::abort(429, 'Too many requests.', ['Retry-After' => $result['headers']['Retry-After'] ?? '60']);
        }
    }

    /**
     * Scan all request inputs for SQL injection patterns.
     */
    private static function checkSqlInjection(): void
    {
        $ip     = self::clientIp();
        $path   = self::requestPath();
        $json   = self::jsonBody();
        $result = SqlInjectionDetector::scanRequest($_GET, $_POST, $json);

        if (!$result['detected']) {
            return;
        }

        self::$logger->logSecurityEvent('SQL_INJECTION_DETECTED', $ip, $path, [
            'field'   => $result['field'],
            'pattern' => $result['pattern'],
            'value'   => $result['value'],
        ]);
        self::$tracker->recordInjectionAttempt($ip, 'sqli');
        $blocked = self::$ipBlocker->recordViolation($ip, 'SQL injection attempt');

        if ($blocked) {
            self::$logger->logBlock($ip, 'Blocked after SQL injection attempts');
        }

        if (SecurityConfig::$sqliBlock) {
            self::abort(400, 'Invalid input detected.');
        }
    }

    /**
     * Scan all request inputs for XSS patterns; sanitize or block.
     */
    private static function checkXss(): void
    {
        $ip   = self::clientIp();
        $path = self::requestPath();
        $all  = array_merge($_GET, $_POST);

        $result = XssDetector::scanInputs($all);

        if (!$result['detected']) {
            // Also check JSON
            $json = self::jsonBody();
            if (is_array($json)) {
                $result = XssDetector::scanInputs($json, 'JSON');
            }
        }

        if (!$result['detected']) {
            return;
        }

        self::$logger->logSecurityEvent('XSS_DETECTED', $ip, $path, [
            'field' => $result['field'],
            'value' => $result['value'],
        ]);
        self::$tracker->recordInjectionAttempt($ip, 'xss');

        if (SecurityConfig::$xssSanitize) {
            // Sanitize in place
            $_GET   = XssDetector::sanitize($_GET);
            $_POST  = XssDetector::sanitize($_POST);
            $_REQUEST = XssDetector::sanitize($_REQUEST);
            // Note: JSON body sanitization must be done by the consumer via getCleanJson()
        } else {
            self::$ipBlocker->recordViolation($ip, 'XSS attempt');
            self::abort(400, 'Invalid input detected.');
        }
    }

    /**
     * Validate JWT if the request is authenticated.
     *
     * Only runs if a JWT secret is configured AND the request carries a token.
     * Routes that do NOT require auth are handled by skipping (no token = not checked here).
     */
    private static function checkJwt(): void
    {
        if (empty(SecurityConfig::$jwtSecret)) {
            return; // JWT validation not configured
        }

        $token = JwtValidator::fromHeader();
        if (empty($token)) {
            return; // Unauthenticated request — routing layer handles 401 for protected routes
        }

        $result = JwtValidator::validate($token, SecurityConfig::$jwtSecret);

        if (!$result['valid']) {
            $ip   = self::clientIp();
            $path = self::requestPath();

            self::$logger->logSecurityEvent('JWT_INVALID', $ip, $path, ['error' => $result['error']]);
            self::$tracker->recordAuthFailure($ip);
            self::$ipBlocker->recordViolation($ip, "JWT invalid: {$result['error']}");
            self::abort(401, 'Authentication failed: ' . $result['error']);
        }

        // Expose claims globally for downstream controllers
        $GLOBALS['__jwt_claims'] = $result['claims'];
    }

    // ── Shutdown (request completed) ──────────────────────────────────────────────

    /**
     * Called after the response is sent (register_shutdown_function).
     *
     * Logs the completed request and tracks suspicious response codes.
     */
    public static function onRequestComplete(): void
    {
        $status    = http_response_code();
        $ip        = self::clientIp();
        $path      = self::requestPath();
        $userId    = (string) ($GLOBALS['__jwt_claims']['sub'] ?? $GLOBALS['__jwt_claims']['user_id'] ?? 'anonymous');
        $duration  = (microtime(true) - self::$requestStartTime) * 1000;
        $payload   = null;

        if (SecurityConfig::$logPayloads) {
            $raw = @file_get_contents('php://input');
            $payload = $raw !== false ? substr($raw, 0, SecurityConfig::$logPayloadMaxBytes) : null;
        }

        self::$logger->logRequest($ip, $userId, $path, (int) $status, $duration, $payload);

        // Track suspicious response patterns
        if ($status === 401 || $status === 403) {
            $threshold = self::$tracker->recordAuthFailure($ip);
            if ($threshold) {
                self::$logger->logSecurityEvent('SUSPICIOUS_AUTH_FAILURES', $ip, $path, ['status' => $status]);
                self::$ipBlocker->recordViolation($ip, "Repeated auth failures ({$status})");
            }
        }

        if ($status === 404) {
            $threshold = self::$tracker->record404($ip);
            if ($threshold) {
                self::$logger->logSecurityEvent('ENDPOINT_PROBING_SUSPECTED', $ip, $path);
            }
        }
    }

    // ── Public Helpers ────────────────────────────────────────────────────────────

    /**
     * Return the JWT claims for the currently authenticated request.
     *
     * @return array<string, mixed>
     */
    public static function jwtClaims(): array
    {
        return $GLOBALS['__jwt_claims'] ?? [];
    }

    /**
     * Return the authenticated user ID (from JWT sub/user_id claim).
     */
    public static function currentUserId(): int|string|null
    {
        $claims = self::jwtClaims();
        return $claims['sub'] ?? $claims['user_id'] ?? null;
    }

    /**
     * Return the authenticated tenant ID (from JWT tenant_id claim).
     * ALWAYS use this — never trust client-supplied tenant_id values.
     */
    public static function currentTenantId(): int|string|null
    {
        $claims = self::jwtClaims();
        return $claims['tenant_id'] ?? null;
    }

    /**
     * Return XSS-sanitized JSON body as array.
     *
     * @return array<string, mixed>
     */
    public static function getCleanJson(): array
    {
        $raw = self::jsonBody();
        if (!is_array($raw)) {
            return [];
        }
        return XssDetector::sanitize($raw);
    }

    /**
     * Get a clean, safe input validator instance.
     */
    public static function validator(): InputValidator
    {
        return new InputValidator();
    }

    /**
     * Manually record a violation for an IP.
     */
    public static function violation(string $reason, ?string $ip = null): void
    {
        $ip ??= self::clientIp();
        self::$ipBlocker->recordViolation($ip, $reason);
        self::$logger->logSecurityEvent('MANUAL_VIOLATION', $ip, self::requestPath(), ['reason' => $reason]);
    }

    /**
     * Admin: unblock an IP.
     */
    public static function unblock(string $ip): void
    {
        self::$ipBlocker->unblock($ip);
        self::$logger->logSecurityEvent('IP_UNBLOCKED', $ip, '-');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────────

    private static function sendSecurityHeaders(): void
    {
        foreach (SecurityConfig::$securityHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    private static function abort(int $status, string $message, array $extraHeaders = []): never
    {
        http_response_code($status);
        foreach ($extraHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $message, 'status' => $status]);
        exit;
    }

    private static function clientIp(): string
    {
        // Trust X-Forwarded-For only if it's from a trusted proxy
        // For simplicity, we use REMOTE_ADDR (most secure default)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function requestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?? '/';
    }

    private static ?array $jsonBodyCache = null;

    private static function jsonBody(): mixed
    {
        if (self::$jsonBodyCache !== null) {
            return self::$jsonBodyCache;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            self::$jsonBodyCache = [];
            return self::$jsonBodyCache;
        }

        $raw = @file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            self::$jsonBodyCache = [];
            return self::$jsonBodyCache;
        }

        $decoded = json_decode($raw, true);
        self::$jsonBodyCache = is_array($decoded) ? $decoded : [];
        return self::$jsonBodyCache;
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 11 — Integration Examples
// ════════════════════════════════════════════════════════════════════════════════

/*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INTEGRATION EXAMPLE 1 — bootstrap.php (minimal)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

<?php
require_once 'SecurityMiddleware.php';

SecurityMiddleware::boot([
    'storageDir'            => __DIR__ . '/../storage/security',
    'jwtSecret'             => $_ENV['JWT_SECRET'] ?? '',
    'rateLimitIpMax'        => 100,
    'rateLimitIpWindow'     => 60,
    'rateLimitAuthMax'      => 5,
    'blockAfterViolations'  => 3,
    'blockDuration'         => 600,    // 10 minutes
    'ipWhitelist'           => ['127.0.0.1', '10.0.0.1'],
]);

// ... rest of bootstrap (routing, DI container, etc.)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INTEGRATION EXAMPLE 2 — Controller usage
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

class OrderController
{
    public function create(): void
    {
        // Get clean, sanitized JSON input
        $data = SecurityMiddleware::getCleanJson();

        // Validate using built-in validator
        $validator = SecurityMiddleware::validator();
        if (!$validator->validateAll($data, [
            'product_id' => 'required|integer|min:1',
            'quantity'   => 'required|integer|min:1|max:999',
            'note'       => 'nullable|string|max:500',
        ])) {
            http_response_code(422);
            echo json_encode(['errors' => $validator->errors()]);
            return;
        }

        // Always use middleware-provided tenant_id — never trust client
        $tenantId = SecurityMiddleware::currentTenantId();
        $userId   = SecurityMiddleware::currentUserId();

        // ... create order using $tenantId and $userId ...
    }
}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INTEGRATION EXAMPLE 3 — Record custom violation
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// In AuthService::login():
if ($loginAttemptFailed) {
    SecurityMiddleware::violation('Failed login attempt');
    // After $blockAfterViolations failures, IP is auto-blocked
}

// Unblock an IP (admin panel):
SecurityMiddleware::unblock('192.168.1.100');

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INTEGRATION EXAMPLE 4 — Standalone InputValidator
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$v = new InputValidator();
$ok = $v->validateAll($_POST, [
    'email'    => 'required|email|max:254',
    'password' => 'required|string|min:8|max:128',
    'role'     => 'required|in:user,editor,manager', // admin NOT allowed
]);

if (!$ok) {
    // Return 422 with structured errors
    echo json_encode(['errors' => $v->errors()]);
    exit;
}

*/
