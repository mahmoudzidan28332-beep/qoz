<?php
declare(strict_types=1);

/**
 * Security Validation & Rate Limiting Trait
 *
 * Extracted from Security class:
 * - Input validation & sanitization
 * - XSS protection
 * - Rate limiting (Session/Redis)
 * - Brute force protection
 *
 * @version 2.0.0
 * @package SecurityCore
 */

trait SecurityValidationTrait
{
    // ===========================================
    // 4️⃣ Input Validation & Sanitization
    // ===========================================

    /**
     * Sanitize input recursively
     *
     * @param mixed $input
     * @param bool $strict Strict mode removes all HTML
     * @return mixed
     */
    public static function sanitizeInput($input, bool $strict = false)
    {
        if (is_array($input)) {
            return array_map(function($item) use ($strict) {
                return self::sanitizeInput($item, $strict);
            }, $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Trim whitespace
        $input = trim($input);

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Convert special characters (protect against XSS and attribute injection)
        if ($strict) {
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        } else {
            // Even in non-strict mode, protect quotes to prevent attribute injection
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return $input;
    }

    /**
     * Validate email address
     *
     * @param string $email
     * @param bool $checkDNS Verify domain has MX records
     * @return bool
     */
    public static function validateEmail(string $email, bool $checkDNS = false): bool
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if ($checkDNS) {
            [$local, $domain] = explode('@', $email);
            return checkdnsrr($domain, 'MX');
        }

        return true;
    }

    /**
     * Validate Saudi phone number
     *
     * @param string $phone
     * @param bool $normalize Return normalized format
     * @return bool|string
     */
    public static function validateSaudiPhone(string $phone, bool $normalize = false)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove country code if present
        $phone = preg_replace('/^\+?966/', '', $phone);

        // Ensure starts with 5 and has 9 digits
        $pattern = defined('REGEX_PHONE_SA') ? REGEX_PHONE_SA : '/^5[0-9]{8}$/';

        if (preg_match($pattern, $phone) !== 1) {
            return false;
        }

        return $normalize ? '+966' . $phone : true;
    }

    /**
     * Validate URL with security checks
     *
     * @param string $url
     * @param array $allowedSchemes
     * @return bool
     */
    public static function validateURL(string $url, array $allowedSchemes = ['http', 'https']): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, $allowedSchemes, true)) {
            return false;
        }

        // Prevent SSRF - block private/local IPs
        $host = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    /**
     * Validate integer with range
     *
     * @param mixed $value
     * @param int|null $min
     * @param int|null $max
     * @return bool
     */
    public static function validateInteger($value, ?int $min = null, ?int $max = null): bool
    {
        $options = [];

        if ($min !== null || $max !== null) {
            $options['options'] = [];
            if ($min !== null) $options['options']['min_range'] = $min;
            if ($max !== null) $options['options']['max_range'] = $max;
        }

        return filter_var($value, FILTER_VALIDATE_INT, $options) !== false;
    }

    /**
     * Validate float with range
     *
     * @param mixed $value
     * @param float|null $min
     * @param float|null $max
     * @return bool
     */
    public static function validateFloat($value, ?float $min = null, ?float $max = null): bool
    {
        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
            return false;
        }

        $floatValue = (float)$value;

        if ($min !== null && $floatValue < $min) {
            return false;
        }

        if ($max !== null && $floatValue > $max) {
            return false;
        }

        return true;
    }

    // ===========================================
    // 5️⃣ XSS Protection
    // ===========================================

    /**
     * Sanitize HTML allowing safe tags
     *
     * @param string $html
     * @param array|null $allowedTags
     * @return string
     */
    public static function sanitizeHTML(string $html, ?array $allowedTags = null): string
    {
        $defaultTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code>';
        $allowed = $allowedTags ? implode('', $allowedTags) : $defaultTags;

        $html = strip_tags($html, $allowed);

        // Remove dangerous attributes
        $html = preg_replace('/<([^>]+)\s+(on\w+|formaction|action|data-)\s*=\s*["\'][^"\']*["\']/i', '<$1', $html);

        return $html;
    }

    /**
     * Comprehensive XSS prevention
     *
     * @param string $data
     * @return string
     */
    public static function preventXSS(string $data): string
    {
        // Convert special characters
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove null bytes
        $data = str_replace(chr(0), '', $data);

        // Remove scripts
        $data = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $data);

        // Remove event handlers
        $data = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $data);

        // Remove javascript: protocol
        $data = preg_replace('/javascript:/i', '', $data);

        return $data;
    }

    // ===========================================
    // 7️⃣ Rate Limiting
    // ===========================================

    /**
     * Check rate limit with Redis support
     *
     * @param string $identifier
     * @param int|null $limit
     * @param int|null $window
     * @return array
     */
    public static function checkRateLimit(
        string $identifier,
        ?int $limit = null,
        ?int $window = null
    ): array {
        $limit = $limit ?? (defined('RATE_LIMIT_REQUESTS') ? RATE_LIMIT_REQUESTS : 60);
        $window = $window ?? (defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 60);

        // Try Redis first, fallback to session
        if (class_exists('Redis') && defined('REDIS_ENABLED') && REDIS_ENABLED) {
            return self::checkRateLimitRedis($identifier, $limit, $window);
        }

        return self::checkRateLimitSession($identifier, $limit, $window);
    }

    /**
     * Rate limiting using session
     */
    private static function checkRateLimitSession(string $identifier, int $limit, int $window): array
    {
        self::ensureSession();

        $key = 'rate_limit_' . hash('sha256', $identifier);
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }

        $data = $_SESSION[$key];

        // Reset if window expired
        if ($now >= $data['reset_time']) {
            $data = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }

        // Increment counter
        $data['count']++;
        $_SESSION[$key] = $data;

        $allowed = $data['count'] <= $limit;
        $remaining = max(0, $limit - $data['count']);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_time' => $data['reset_time'],
            'retry_after' => $allowed ? 0 : ($data['reset_time'] - $now),
            'limit' => $limit
        ];
    }

    /**
     * Rate limiting using Redis (better for production)
     */
    private static function checkRateLimitRedis(string $identifier, int $limit, int $window): array
    {
        // Placeholder for Redis implementation
        // يمكن تطبيقها عند توفر Redis
        return self::checkRateLimitSession($identifier, $limit, $window);
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $identifier
     */
    public static function resetRateLimit(string $identifier): void
    {
        self::ensureSession();

        $key = 'rate_limit_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }

    // ===========================================
    // 8️⃣ Brute Force Protection
    // ===========================================

    /**
     * Record failed login attempt
     *
     * @param string $identifier
     * @return array
     */
    public static function recordFailedLogin(string $identifier): array
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        $now = time();

        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        $lockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }

        $data = $_SESSION[$key];

        // Reset if lockout expired
        if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
            $data = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }

        // Increment counter
        $data['count']++;

        // Apply lockout
        if ($data['count'] >= $maxAttempts) {
            $data['locked_until'] = $now + $lockoutTime;
        }

        $_SESSION[$key] = $data;

        $locked = $data['locked_until'] > $now;

        // Log security event
        if ($locked) {
            self::logSecurityEvent('LOGIN_LOCKED', "Account locked: {$identifier}");
        }

        return [
            'locked' => $locked,
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'lock_time' => $locked ? ($data['locked_until'] - $now) : 0,
            'max_attempts' => $maxAttempts
        ];
    }

    /**
     * Check if account is locked
     *
     * @param string $identifier
     * @return array
     */
    public static function checkLoginLock(string $identifier): array
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;

        if (!isset($_SESSION[$key])) {
            return [
                'locked' => false,
                'attempts' => 0,
                'remaining' => $maxAttempts,
                'lock_time' => 0
            ];
        }

        $data = $_SESSION[$key];
        $now = time();

        $locked = $data['locked_until'] > $now;

        return [
            'locked' => $locked,
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'lock_time' => $locked ? ($data['locked_until'] - $now) : 0
        ];
    }

    /**
     * Reset login attempts
     *
     * @param string $identifier
     */
    public static function resetLoginAttempts(string $identifier): void
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }
}
