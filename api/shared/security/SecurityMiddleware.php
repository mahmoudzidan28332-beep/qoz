<?php

declare(strict_types=1);

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
    /** Paths that bypass rate limiting (health-check, etc.). */
    public static array $rateLimitWhitelist = ['/health', '/ping', '/status'];

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
    ];
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 2 — SQL Injection Detector
// ════════════════════════════════════════════════════════════════════════════════

final class SqlInjectionDetector
{
    /** Patterns ordered from most specific (lowest FP) to more general. */
    private static array $patterns = [
        // Tautologies
        '/\b(?:OR|AND)\s+[\'"0-9][^\s]*\s*=\s*[\'"0-9]/i',
        '/\b1\s*=\s*1\b|\btrue\s*=\s*true\b/i',

        // UNION SELECT attacks
        '/\bUNION\b[\s\S]{0,100}\bSELECT\b/i',

        // Comment terminators
        '/--[\s\S]|#[\s\S]/i',
        '/\/\*[\s\S]*?\*\//i',

        // DML statements in input (not config)
        '/\b(?:DROP|TRUNCATE|ALTER)\s+(?:TABLE|DATABASE|SCHEMA)\b/i',
        '/\bINSERT\s+INTO\b[\s\S]{0,100}\bVALUES\b/i',
        '/\bDELETE\s+FROM\b/i',
        '/\bUPDATE\b[\s\S]{0,100}\bSET\b/i',

        // Dangerous functions
        '/\b(?:SLEEP|BENCHMARK|WAITFOR\s+DELAY|PG_SLEEP)\s*\(/i',
        '/\b(?:LOAD_FILE|INTO\s+OUTFILE|INTO\s+DUMPFILE)\b/i',
        '/\b(?:INFORMATION_SCHEMA|SYSOBJECTS|SYSCOLUMNS|SYS\.TABLES)\b/i',
        '/\b(?:CHAR|NCHAR|VARCHAR)\s*\(\s*\d+\s*\)/i',
        '/\bCONCAT\s*\([^)]*(?:CHAR|0x[0-9a-f]+)/i',
        '/\bEXTRACTVALUE\s*\(/i',
        '/\bXMLTYPE\s*\(/i',
        '/\bHEX\s*\(|0x[0-9a-f]{4,}/i',

        // Stacked queries
        '/;\s*(?:SELECT|INSERT|UPDATE|DELETE|DROP|EXEC)\b/i',

        // Boolean-based blind
        '/\bIF\s*\(\s*\d+\s*=\s*\d+\s*,\s*[\'"]/i',
        '/\bCASE\s+WHEN\b[\s\S]{0,100}\bTHEN\b/i',

        // NoSQL injection style
        '/\$(?:ne|eq|gt|lt|gte|lte|in|nin|or|and|not|nor|exists|type|mod|regex)\b/i',
        '/\{\s*["\']\s*\$(?:where|regex|in|ne)\s*["\']/i',
    ];

    /** Inputs considered safe even if matched (e.g. encoded URL in a whitelist). */
    private static array $whitelist = [];

    /**
     * Analyse a value (string or array) for SQL injection patterns.
     *
     * @return array{detected: bool, pattern: string, value: string}
     */
    public static function analyse(mixed $value, string $fieldName = ''): array
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $result = self::analyse($v, $fieldName . '[' . $k . ']');
                if ($result['detected']) {
                    return $result;
                }
            }
            return ['detected' => false, 'pattern' => '', 'value' => ''];
        }

        if (!is_string($value)) {
            return ['detected' => false, 'pattern' => '', 'value' => ''];
        }

        // Decode URL encoding before analysis
        $decoded = urldecode($value);

        foreach (self::$patterns as $pattern) {
            if (preg_match($pattern, $decoded)) {
                return [
                    'detected' => true,
                    'pattern'  => $pattern,
                    'value'    => substr($decoded, 0, 200),
                ];
            }
        }

        return ['detected' => false, 'pattern' => '', 'value' => ''];
    }

    /**
     * Scan all inputs ($GET, $POST, JSON body) and return first detected hit.
     *
     * @return array{detected: bool, field: string, pattern: string, value: string}
     */
    public static function scanRequest(array $get, array $post, mixed $json): array
    {
        foreach (['GET' => $get, 'POST' => $post] as $source => $data) {
            foreach ($data as $field => $value) {
                $result = self::analyse($value, "{$source}[{$field}]");
                if ($result['detected']) {
                    return array_merge($result, ['field' => "{$source}[{$field}]"]);
                }
            }
        }

        if (is_array($json)) {
            foreach ($json as $field => $value) {
                $result = self::analyse($value, "JSON[{$field}]");
                if ($result['detected']) {
                    return array_merge($result, ['field' => "JSON[{$field}]"]);
                }
            }
        }

        return ['detected' => false, 'field' => '', 'pattern' => '', 'value' => ''];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 3 — XSS Detector / Sanitizer
// ════════════════════════════════════════════════════════════════════════════════

final class XssDetector
{
    private static array $patterns = [
        // Script tags
        '/<\s*script[^>]*>/i',
        '/<\s*\/\s*script\s*>/i',

        // Event handlers
        '/\bon(?:load|error|click|mouse\w+|key\w+|focus|blur|change|submit|reset|select|copy|cut|paste|drag\w*|drop|scroll|resize|unload|beforeunload|hash\w*|message|online|offline|storage|popstate|pagehide|pageshow)\s*=/i',

        // javascript: protocol
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:\s*(?:text\/html|application\/x-javascript)/i',

        // XSS via HTML attributes
        '/<\s*(?:img|iframe|embed|object|form|input|button|select|textarea|meta|link|base|applet)\b[^>]*(?:src|href|action|formaction|data|srcdoc)\s*=/i',

        // SVG attack vectors
        '/<\s*svg\b[^>]*>/i',
        '/<\s*(?:animate|set|feImage|use)\b/i',

        // CSS expressions
        '/expression\s*\(/i',
        '/-moz-binding\s*:/i',

        // Unicode / encoding tricks
        '/&#(?:x[0-9a-f]+|[0-9]+)\s*;/i',
        '/%[0-9a-f]{2}/i',      // URL encoded
    ];

    /**
     * True if the value contains XSS patterns.
     */
    public static function isXss(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Double-decode before checking
        $decoded = html_entity_decode(urldecode($value), ENT_QUOTES, 'UTF-8');

        foreach (self::$patterns as $pattern) {
            if (preg_match($pattern, $decoded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a value by HTML-encoding all special characters.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }

        return $value;
    }

    /**
     * Recursively scan input array for XSS.
     *
     * @return array{detected: bool, field: string, value: string}
     */
    public static function scanInputs(array $inputs, string $source = ''): array
    {
        foreach ($inputs as $field => $value) {
            if (is_array($value)) {
                $result = self::scanInputs($value, "{$source}[{$field}]");
                if ($result['detected']) {
                    return $result;
                }
            } elseif (self::isXss((string) $value)) {
                return [
                    'detected' => true,
                    'field'    => "{$source}[{$field}]",
                    'value'    => substr((string) $value, 0, 200),
                ];
            }
        }

        return ['detected' => false, 'field' => '', 'value' => ''];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Rate Limiter
// ════════════════════════════════════════════════════════════════════════════════

final class RateLimiter
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = $storageDir;
        @mkdir($this->storageDir . '/rate', 0750, true);
    }

    /**
     * Check the rate limit for a given key.
     *
     * @return array{allowed: bool, current: int, limit: int, reset_in: int}
     */
    public function check(string $key, int $max, int $windowSeconds): array
    {
        $file  = $this->storageDir . '/rate/' . hash('sha256', $key) . '.json';
        $now   = time();
        $data  = $this->readFile($file);

        // Reset window if expired
        if ($data['window_start'] + $windowSeconds <= $now) {
            $data = ['window_start' => $now, 'count' => 0];
        }

        $data['count']++;
        $this->writeFile($file, $data);

        $resetIn = ($data['window_start'] + $windowSeconds) - $now;

        return [
            'allowed'  => $data['count'] <= $max,
            'current'  => $data['count'],
            'limit'    => $max,
            'reset_in' => max(0, $resetIn),
        ];
    }

    /**
     * Check rate limit and return HTTP headers (X-RateLimit-*).
     *
     * @return array{allowed: bool, headers: array<string, string>}
     */
    public function checkWithHeaders(string $key, int $max, int $windowSeconds): array
    {
        $result = $this->check($key, $max, $windowSeconds);

        $headers = [
            'X-RateLimit-Limit'     => (string) $result['limit'],
            'X-RateLimit-Remaining' => (string) max(0, $result['limit'] - $result['current']),
            'X-RateLimit-Reset'     => (string) (time() + $result['reset_in']),
        ];

        if (!$result['allowed']) {
            $headers['Retry-After'] = (string) $result['reset_in'];
        }

        return ['allowed' => $result['allowed'], 'headers' => $headers];
    }

    /** Reset a key's counter (e.g. after successful auth). */
    public function reset(string $key): void
    {
        $file = $this->storageDir . '/rate/' . hash('sha256', $key) . '.json';
        @unlink($file);
    }

    // ── File helpers ─────────────────────────────────────────────────────────────

    private function readFile(string $file): array
    {
        if (!file_exists($file)) {
            return ['window_start' => time(), 'count' => 0];
        }

        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : ['window_start' => time(), 'count' => 0];
    }

    private function writeFile(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 5 — IP Blocker
// ════════════════════════════════════════════════════════════════════════════════

final class IpBlocker
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = $storageDir;
        @mkdir($this->storageDir . '/blocks', 0750, true);
    }

    /**
     * True if the IP is currently blocked.
     */
    public function isBlocked(string $ip): bool
    {
        if (in_array($ip, SecurityConfig::$ipWhitelist, true)) {
            return false;
        }

        $file = $this->blockFile($ip);
        if (!file_exists($file)) {
            return false;
        }

        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data)) {
            return false;
        }

        // Permanent block
        if ($data['permanent'] ?? false) {
            return true;
        }

        // Temporary block
        $expiresAt = $data['blocked_at'] + $data['duration'];
        if (time() < $expiresAt) {
            return true;
        }

        // Block expired — clean up
        @unlink($file);
        return false;
    }

    /**
     * Record a violation for an IP.
     *
     * Returns true if the IP was blocked as a result of this violation.
     */
    public function recordViolation(string $ip, string $reason = ''): bool
    {
        if (in_array($ip, SecurityConfig::$ipWhitelist, true)) {
            return false;
        }

        $file  = $this->violationFile($ip);
        $data  = $this->readFile($file);
        $now   = time();

        // Clean old violations (outside window)
        $data['violations'] = array_values(array_filter(
            $data['violations'] ?? [],
            fn($v) => $v['time'] > $now - 3600 // 1-hour violation window
        ));

        $data['violations'][] = ['time' => $now, 'reason' => $reason];
        $data['total']        = ($data['total'] ?? 0) + 1;

        @file_put_contents($file, json_encode($data), LOCK_EX);

        // Check if we should block
        $recentCount = count($data['violations']);
        if ($recentCount >= SecurityConfig::$blockAfterViolations) {
            $permanent = $data['total'] >= SecurityConfig::$permanentBlockAfter;
            $this->block($ip, $reason, $permanent);
            return true;
        }

        return false;
    }

    /**
     * Explicitly block an IP.
     */
    public function block(string $ip, string $reason = '', bool $permanent = false): void
    {
        $file = $this->blockFile($ip);
        @file_put_contents($file, json_encode([
            'ip'         => $ip,
            'blocked_at' => time(),
            'duration'   => SecurityConfig::$blockDuration,
            'permanent'  => $permanent,
            'reason'     => $reason,
        ]), LOCK_EX);
    }

    /**
     * Unblock an IP.
     */
    public function unblock(string $ip): void
    {
        @unlink($this->blockFile($ip));
        @unlink($this->violationFile($ip));
    }

    /**
     * Return seconds until block expires (0 if not blocked or permanent).
     */
    public function blockedUntil(string $ip): int
    {
        $file = $this->blockFile($ip);
        if (!file_exists($file)) {
            return 0;
        }

        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data)) {
            return 0;
        }

        if ($data['permanent'] ?? false) {
            return PHP_INT_MAX;
        }

        return max(0, ($data['blocked_at'] + $data['duration']) - time());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function blockFile(string $ip): string
    {
        return $this->storageDir . '/blocks/block_' . hash('sha256', $ip) . '.json';
    }

    private function violationFile(string $ip): string
    {
        return $this->storageDir . '/blocks/violations_' . hash('sha256', $ip) . '.json';
    }

    private function readFile(string $file): array
    {
        if (!file_exists($file)) {
            return ['violations' => [], 'total' => 0];
        }
        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : ['violations' => [], 'total' => 0];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 6 — Suspicious Activity Tracker
// ════════════════════════════════════════════════════════════════════════════════

final class SuspiciousActivityTracker
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = $storageDir;
        @mkdir($this->storageDir . '/suspicious', 0750, true);
    }

    /** Record an auth failure (401/403). Returns true if threshold reached. */
    public function recordAuthFailure(string $ip): bool
    {
        return $this->record($ip, 'auth_failure', SecurityConfig::$suspiciousAuthFailures);
    }

    /** Record a 404 response (possible endpoint probing). Returns true if threshold reached. */
    public function record404(string $ip): bool
    {
        return $this->record($ip, '404', SecurityConfig::$suspicious404Count);
    }

    /** Record an injection attempt. */
    public function recordInjectionAttempt(string $ip, string $type): void
    {
        $this->record($ip, "injection_{$type}", 1);
    }

    /** @return array<string, mixed> Activity summary for an IP. */
    public function getSummary(string $ip): array
    {
        $file = $this->file($ip);
        if (!file_exists($file)) {
            return ['events' => [], 'total' => 0];
        }

        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : ['events' => [], 'total' => 0];
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function record(string $ip, string $type, int $threshold): bool
    {
        $file = $this->file($ip);
        $data = $this->read($file);
        $now  = time();
        $win  = SecurityConfig::$suspiciousWindow;

        // Prune stale events
        $data['events'] = array_values(array_filter(
            $data['events'],
            fn($e) => $e['time'] > $now - $win
        ));

        $data['events'][] = ['type' => $type, 'time' => $now];
        $data['total']    = ($data['total'] ?? 0) + 1;

        @file_put_contents($file, json_encode($data), LOCK_EX);

        // Count events of this specific type in the window
        $typeCount = count(array_filter($data['events'], fn($e) => $e['type'] === $type));

        return $typeCount >= $threshold;
    }

    private function file(string $ip): string
    {
        return $this->storageDir . '/suspicious/' . hash('sha256', $ip) . '.json';
    }

    private function read(string $file): array
    {
        if (!file_exists($file)) {
            return ['events' => [], 'total' => 0];
        }
        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : ['events' => [], 'total' => 0];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 7 — Request Logger
// ════════════════════════════════════════════════════════════════════════════════

final class RequestLogger
{
    private string $logFile;
    private bool   $enabled;

    public function __construct()
    {
        $this->enabled = SecurityConfig::$enableLogging;
        $this->logFile = SecurityConfig::$logFile ?: SecurityConfig::$storageDir . '/security.log';
        @mkdir(dirname($this->logFile), 0750, true);
    }

    /**
     * Write a structured log entry.
     *
     * @param array<string, mixed> $extra
     */
    public function log(string $level, string $event, string $ip, string $endpoint, array $extra = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->rotateIfNeeded();

        $entry = array_merge([
            'ts'       => date('Y-m-d\TH:i:s\Z'),
            'level'    => strtoupper($level),
            'event'    => $event,
            'ip'       => $ip,
            'endpoint' => $endpoint,
            'method'   => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        ], $extra);

        @file_put_contents($this->logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function logRequest(string $ip, string $userId, string $endpoint, int $statusCode, float $durationMs, ?string $payload = null): void
    {
        $extra = [
            'user_id'     => $userId,
            'status'      => $statusCode,
            'duration_ms' => round($durationMs, 2),
        ];

        if (SecurityConfig::$logPayloads && $payload !== null) {
            $extra['payload'] = substr($payload, 0, SecurityConfig::$logPayloadMaxBytes);
        }

        $level = match (true) {
            $statusCode >= 500 => 'ERROR',
            $statusCode >= 400 => 'WARN',
            default            => 'INFO',
        };

        $this->log($level, 'REQUEST', $ip, $endpoint, $extra);
    }

    public function logSecurityEvent(string $event, string $ip, string $endpoint, array $details = []): void
    {
        $this->log('SECURITY', $event, $ip, $endpoint, $details);
    }

    public function logBlock(string $ip, string $reason): void
    {
        $this->log('SECURITY', 'IP_BLOCKED', $ip, '-', ['reason' => $reason]);
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $sizeBytes = filesize($this->logFile);
        $maxBytes  = SecurityConfig::$logMaxSizeMb * 1024 * 1024;

        if ($sizeBytes >= $maxBytes) {
            $rotated = $this->logFile . '.' . date('Ymd_His');
            @rename($this->logFile, $rotated);
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 8 — Input Validator & Sanitizer
// ════════════════════════════════════════════════════════════════════════════════

final class InputValidator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    /**
     * Validate a field value against a set of rules.
     *
     * Rule format:  'required|string|min:3|max:100|email|numeric|in:a,b,c|regex:/^[a-z]+$/'
     *
     * @param mixed $value
     * @return bool  True if valid
     */
    public function validate(string $field, mixed $value, string $rules): bool
    {
        $ruleList = explode('|', $rules);
        $valid    = true;

        foreach ($ruleList as $rule) {
            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, '');

            $passed = match ($ruleName) {
                'required' => $value !== null && $value !== '' && $value !== [],
                'string'   => is_string($value),
                'numeric'  => is_numeric($value),
                'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false,
                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'url'      => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'boolean'  => is_bool($value) || in_array($value, ['true', 'false', '0', '1', 0, 1], true),
                'array'    => is_array($value),
                'nullable' => true,  // always passes — just marks field as nullable
                'min'      => is_string($value) ? mb_strlen($value) >= (int) $ruleParam
                                                 : (is_numeric($value) && $value >= (float) $ruleParam),
                'max'      => is_string($value) ? mb_strlen($value) <= (int) $ruleParam
                                                 : (is_numeric($value) && $value <= (float) $ruleParam),
                'in'       => in_array($value, explode(',', $ruleParam), false),
                'not_in'   => !in_array($value, explode(',', $ruleParam), false),
                'regex'    => is_string($value) && preg_match($ruleParam, $value) === 1,
                'uuid'     => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value),
                'alpha'    => is_string($value) && ctype_alpha($value),
                'alphanum' => is_string($value) && ctype_alnum($value),
                'date'     => is_string($value) && strtotime($value) !== false,
                'ip'       => filter_var($value, FILTER_VALIDATE_IP) !== false,
                default    => true,
            };

            if (!$passed) {
                $this->errors[$field][] = $this->message($ruleName, $field, $ruleParam);
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Validate multiple fields at once.
     *
     * @param array<string, mixed>  $data   Input data
     * @param array<string, string> $rules  [field => 'rule|rule|rule']
     */
    public function validateAll(array $data, array $rules): bool
    {
        $this->errors = [];
        $valid = true;

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            if (!$this->validate($field, $value, $fieldRules)) {
                $valid = false;
            }
        }

        return $valid;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        foreach ($this->errors as $field => $messages) {
            return "{$field}: " . $messages[0];
        }
        return '';
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function message(string $rule, string $field, string $param): string
    {
        return match ($rule) {
            'required' => "{$field} is required",
            'string'   => "{$field} must be a string",
            'numeric'  => "{$field} must be numeric",
            'integer'  => "{$field} must be an integer",
            'email'    => "{$field} must be a valid email address",
            'url'      => "{$field} must be a valid URL",
            'min'      => "{$field} must be at least {$param}",
            'max'      => "{$field} must not exceed {$param}",
            'in'       => "{$field} must be one of: {$param}",
            'not_in'   => "{$field} must not be one of: {$param}",
            'regex'    => "{$field} format is invalid",
            'uuid'     => "{$field} must be a valid UUID",
            'alpha'    => "{$field} must contain only letters",
            'alphanum' => "{$field} must contain only letters and numbers",
            'date'     => "{$field} must be a valid date",
            'ip'       => "{$field} must be a valid IP address",
            default    => "{$field} is invalid",
        };
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 9 — JWT Validator
// ════════════════════════════════════════════════════════════════════════════════

final class JwtValidator
{
    /**
     * Validate a JWT token and return its claims.
     *
     * @return array{valid: bool, claims: array<string, mixed>, error: string}
     */
    public static function validate(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'claims' => [], 'error' => 'Malformed token'];
        }

        [$headerB64, $payloadB64, $signature] = $parts;

        // Decode header
        $header = json_decode(
            (string) base64_decode(strtr($headerB64, '-_', '+/') . str_repeat('=', (4 - strlen($headerB64) % 4) % 4)),
            true
        );

        if (!is_array($header)) {
            return ['valid' => false, 'claims' => [], 'error' => 'Cannot decode JWT header'];
        }

        // Algorithm check
        $alg = $header['alg'] ?? 'none';
        if (!in_array($alg, SecurityConfig::$allowedJwtAlgs, true)) {
            return ['valid' => false, 'claims' => [], 'error' => "Algorithm '{$alg}' not allowed"];
        }

        // Decode payload
        $claims = json_decode(
            (string) base64_decode(strtr($payloadB64, '-_', '+/') . str_repeat('=', (4 - strlen($payloadB64) % 4) % 4)),
            true
        );

        if (!is_array($claims)) {
            return ['valid' => false, 'claims' => [], 'error' => 'Cannot decode JWT payload'];
        }

        // Expiry check
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return ['valid' => false, 'claims' => $claims, 'error' => 'Token expired'];
        }

        // Not-before check
        if (isset($claims['nbf']) && $claims['nbf'] > time()) {
            return ['valid' => false, 'claims' => $claims, 'error' => 'Token not yet valid'];
        }

        // Signature verification (HMAC only — RS256 requires openssl key)
        if (!empty($secret) && in_array($alg, ['HS256', 'HS384', 'HS512'], true)) {
            $hashAlg = str_replace('HS', 'sha', $alg);
            $expected = rtrim(strtr(base64_encode(hash_hmac($hashAlg, "{$headerB64}.{$payloadB64}", $secret, true)), '+/', '-_'), '=');
            if (!hash_equals($expected, $signature)) {
                return ['valid' => false, 'claims' => $claims, 'error' => 'Invalid signature'];
            }
        }

        return ['valid' => true, 'claims' => $claims, 'error' => ''];
    }

    /**
     * Extract token from Authorization header.
     */
    public static function fromHeader(): string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// SECTION 10 — The Main Middleware Class
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
        $isAuth  = str_contains($path, '/auth/') || str_contains($path, '/login') || str_contains($path, '/register');
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
