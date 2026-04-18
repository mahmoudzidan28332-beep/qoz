<?php
declare(strict_types=1);

/**
 * Security Utilities Trait
 *
 * Extracted from Security class:
 * - Request information (IP, user agent, device detection)
 * - Hashing & comparison utilities
 * - Session management
 * - Logging helpers
 * - Security session cleanup
 *
 * @version 2.0.0
 * @package SecurityCore
 */

trait SecurityUtilsTrait
{
    // ===========================================
    // 9️⃣ Request Information
    // ===========================================

    /**
     * Get real client IP address
     *
     * @return string
     */
    public static function getRealIP(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_X_FORWARDED_FOR',     // Proxy
            'HTTP_CLIENT_IP',           // Proxy
            'REMOTE_ADDR'               // Direct
        ];

        foreach ($ipHeaders as $header) {
            if (!isset($_SERVER[$header])) {
                continue;
            }

            $ip = $_SERVER[$header];

            // Handle comma-separated IPs
            if (strpos($ip, ',') !== false) {
                $ips = array_map('trim', explode(',', $ip));
                $ip = $ips[0];
            }

            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent string
     *
     * @return string
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Detect device type
     *
     * @return string mobile|tablet|desktop
     */
    public static function detectDevice(): string
    {
        $userAgent = strtolower(self::getUserAgent());

        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobi))/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/(up\.browser|up\.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Check if request is from bot
     *
     * @return bool
     */
    public static function isBot(): bool
    {
        $userAgent = strtolower(self::getUserAgent());
        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'googlebot', 'bingbot', 'yandex', 'baiduspider'
        ];

        foreach ($botPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get client fingerprint
     *
     * @return string
     */
    public static function getClientFingerprint(): string
    {
        $components = [
            self::getRealIP(),
            self::getUserAgent(),
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    // ===========================================
    // 🔟 Utility Functions
    // ===========================================

    /**
     * Hash data using specified algorithm
     *
     * @param string $data
     * @param string $algo
     * @return string
     */
    public static function hash(string $data, string $algo = 'sha256'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException("Unsupported hash algorithm: {$algo}");
        }

        return hash($algo, $data);
    }

    /**
     * Timing-safe string comparison
     *
     * @param string $known
     * @param string $user
     * @return bool
     */
    public static function timingSafeEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Ensure session is started
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');

            session_start();
        }
    }

    /**
     * Log security event
     *
     * @param string $event
     * @param string $details
     * @param array $context
     */
    public static function logSecurityEvent(string $event, string $details, array $context = []): void
    {
        if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
            return;
        }

        $logFile = defined('LOG_FILE_AUTH') ? LOG_FILE_AUTH : '/tmp/security.log';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'details' => $details,
            'ip' => self::getRealIP(),
            'user_agent' => self::getUserAgent(),
            'context' => $context
        ];

        $message = json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        error_log($message, 3, $logFile);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    private static function logError(string $message, array $context = []): void
    {
        if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
            return;
        }

        $logFile = defined('LOG_FILE_ERROR') ? LOG_FILE_ERROR : '/tmp/error.log';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];

        error_log(json_encode($logData) . PHP_EOL, 3, $logFile);
    }

    /**
     * Clear all security-related session data
     */
    public static function clearSecuritySession(): void
    {
        self::ensureSession();

        $keysToRemove = [];

        foreach (array_keys($_SESSION) as $key) {
            if (strpos($key, 'csrf_') === 0 ||
                strpos($key, 'rate_limit_') === 0 ||
                strpos($key, 'login_attempts_') === 0) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset($_SESSION[$key]);
        }
    }
}
