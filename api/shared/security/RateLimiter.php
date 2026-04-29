<?php

declare(strict_types=1);

/**
 * RateLimiter.php — Redis-backed rate limiter with file fallback
 *
 * Handles:
 *   - Global burst limiting (all endpoints)
 *   - Per-endpoint write limiting (POST/PUT/PATCH/DELETE)
 *   - Brute-force / login protection (exponential backoff + lockout)
 *
 * Usage:
 *   RateLimiter::globalBurst();           // In bootstrap — all requests
 *   RateLimiter::writeEndpoint();         // In write routes
 *   RateLimiter::login($identifier);      // In auth/login route
 */

final class RateLimiter
{
    // ─── Global burst limits ──────────────────────────────────────────────
    private const GLOBAL_MAX        = 60;    // max requests per window
    private const GLOBAL_WINDOW     = 60;    // seconds

    // ─── Per-endpoint write limits ────────────────────────────────────────
    private const WRITE_MAX         = 30;    // max write requests per window
    private const WRITE_WINDOW      = 60;    // seconds

    // ─── Login / brute-force protection ───────────────────────────────────
    private const LOGIN_MAX         = 5;     // max attempts before lockout
    private const LOGIN_WINDOW      = 300;   // 5 minutes window
    private const LOCKOUT_DURATION  = 900;   // 15 minutes lockout
    private const BACKOFF_THRESHOLD = 3;     // attempts before adding delay

    // ─── Storage backend ──────────────────────────────────────────────────
    private const STORAGE_DIR       = '/tmp/rate_limits'; // file fallback dir

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Global burst protection — call once in bootstrap for every request.
     * Limits all IPs to GLOBAL_MAX requests per GLOBAL_WINDOW seconds.
     */
    public static function globalBurst(): void
    {
        $ip  = self::clientIp();
        $key = 'global:' . $ip;

        [$count, $ttl] = self::increment($key, self::GLOBAL_WINDOW);

        self::setRateLimitHeaders($count, self::GLOBAL_MAX, $ttl);

        if ($count > self::GLOBAL_MAX) {
            self::abort(429, 'Too many requests. Slow down.', $ttl);
        }
    }

    /**
     * Write endpoint protection — call at the top of POST/PUT/PATCH/DELETE routes.
     * Tighter limit than global to prevent write-heavy abuse.
     */
    public static function writeEndpoint(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $ip   = self::clientIp();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $key  = 'write:' . $ip . ':' . md5($path);

        [$count, $ttl] = self::increment($key, self::WRITE_WINDOW);

        self::setRateLimitHeaders($count, self::WRITE_MAX, $ttl);

        if ($count > self::WRITE_MAX) {
            self::abort(429, 'Too many write requests. Please wait.', $ttl);
        }
    }

    /**
     * Login brute-force protection.
     *
     * Call with the identifier (email/username) so both IP and
     * account are protected independently.
     *
     *   // In your auth/login route, BEFORE checking the password:
     *   RateLimiter::login($_POST['email'] ?? '');
     *
     * After a successful login, call: RateLimiter::resetLogin($identifier)
     */
    public static function login(string $identifier): void
    {
        $ip      = self::clientIp();
        $keyIp   = 'login_ip:'  . $ip;
        $keyId   = 'login_id:'  . md5(strtolower(trim($identifier)));
        $keyLock = 'lockout:'   . md5($ip . $identifier);

        // Check lockout first
        if (self::isLocked($keyLock)) {
            $ttl = self::ttl($keyLock);
            self::abort(429,
                "Account temporarily locked due to too many failed attempts. Try again in {$ttl} seconds.",
                $ttl,
                ['X-Lockout' => 'true']
            );
        }

        [$countIp] = self::increment($keyIp, self::LOGIN_WINDOW);
        [$countId] = self::increment($keyId, self::LOGIN_WINDOW);

        $attempts = max($countIp, $countId);

        // Exponential backoff: add artificial delay after threshold
        if ($attempts > self::BACKOFF_THRESHOLD && $attempts <= self::LOGIN_MAX) {
            $delay = min(8, 2 ** ($attempts - self::BACKOFF_THRESHOLD));
            sleep($delay);
        }

        // Lockout after max attempts
        if ($attempts > self::LOGIN_MAX) {
            self::lock($keyLock, self::LOCKOUT_DURATION);
            self::abort(429,
                'Too many failed login attempts. Account locked for 15 minutes.',
                self::LOCKOUT_DURATION,
                ['X-Lockout' => 'true', 'X-Attempts' => (string) $attempts]
            );
        }

        // Send remaining attempts header
        $remaining = max(0, self::LOGIN_MAX - $attempts);
        header('X-RateLimit-Login-Remaining: ' . $remaining);
    }

    /**
     * Reset login counter after a successful authentication.
     * Call this after verifying the password is correct.
     */
    public static function resetLogin(string $identifier): void
    {
        $ip      = self::clientIp();
        $keyIp   = 'login_ip:'  . $ip;
        $keyId   = 'login_id:'  . md5(strtolower(trim($identifier)));
        $keyLock = 'lockout:'   . md5($ip . $identifier);

        self::delete($keyIp);
        self::delete($keyId);
        self::delete($keyLock);
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private static function clientIp(): string
    {
        // Respect reverse proxy headers (Nginx/Apache with trusted proxy)
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Increment a counter and return [current_count, ttl_seconds].
     * Uses Redis if available, falls back to file-based storage.
     */
    private static function increment(string $key, int $window): array
    {
        if (self::redisAvailable()) {
            return self::redisIncrement($key, $window);
        }
        return self::fileIncrement($key, $window);
    }

    private static function isLocked(string $key): bool
    {
        if (self::redisAvailable()) {
            return (bool) self::redis()->exists('rl:' . $key);
        }
        $path = self::filePath($key);
        if (!file_exists($path)) return false;
        $data = json_decode(file_get_contents($path) ?: '{}', true);
        return isset($data['locked']) && ($data['expires'] ?? 0) > time();
    }

    private static function lock(string $key, int $duration): void
    {
        if (self::redisAvailable()) {
            self::redis()->setex('rl:' . $key, $duration, '1');
            return;
        }
        self::fileWrite($key, ['locked' => true, 'expires' => time() + $duration]);
    }

    private static function ttl(string $key): int
    {
        if (self::redisAvailable()) {
            return max(0, (int) self::redis()->ttl('rl:' . $key));
        }
        $data = json_decode(file_get_contents(self::filePath($key)) ?: '{}', true);
        return max(0, (int) (($data['expires'] ?? time()) - time()));
    }

    private static function delete(string $key): void
    {
        if (self::redisAvailable()) {
            self::redis()->del('rl:' . $key);
            return;
        }
        $path = self::filePath($key);
        if (file_exists($path)) @unlink($path);
    }

    // ─── Redis backend ────────────────────────────────────────────────────

    private static ?bool $redisAvailable = null;
    private static ?\Redis $redis = null;

    private static function redisAvailable(): bool
    {
        if (self::$redisAvailable === null) {
            try {
                self::$redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: '127.0.0.1';
                $port = (int) (getenv('REDIS_PORT') ?: 6379);
                self::$redisAvailable = self::$redis->connect($host, $port, 1.0);
            } catch (\RedisException $e) {
                self::$redisAvailable = false;
            }
        }
        return self::$redisAvailable;
    }

    private static function redis(): \Redis
    {
        return self::$redis;
    }

    private static function redisIncrement(string $key, int $window): array
    {
        $rKey  = 'rl:' . $key;
        $redis = self::redis();
        $count = (int) $redis->incr($rKey);
        if ($count === 1) {
            $redis->expire($rKey, $window);
        }
        $ttl = max(0, (int) $redis->ttl($rKey));
        return [$count, $ttl];
    }

    // ─── File backend (fallback) ───────────────────────────────────────────

    private static function fileIncrement(string $key, int $window): array
    {
        if (!is_dir(self::STORAGE_DIR)) {
            @mkdir(self::STORAGE_DIR, 0700, true);
        }

        $path = self::filePath($key);
        $now  = time();
        $data = ['count' => 0, 'expires' => $now + $window, 'locked' => false];

        if (file_exists($path)) {
            $stored = json_decode(file_get_contents($path) ?: '{}', true);
            if (is_array($stored) && ($stored['expires'] ?? 0) > $now) {
                $data = $stored;
            }
        }

        $data['count']++;
        self::fileWrite($key, $data);

        $ttl = max(0, (int) ($data['expires'] - $now));
        return [$data['count'], $ttl];
    }

    private static function filePath(string $key): string
    {
        return self::STORAGE_DIR . '/' . md5($key) . '.json';
    }

    private static function fileWrite(string $key, array $data): void
    {
        file_put_contents(self::filePath($key), json_encode($data), LOCK_EX);
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────

    private static function setRateLimitHeaders(int $count, int $max, int $ttl): void
    {
        $remaining = max(0, $max - $count);
        header('X-RateLimit-Limit: '     . $max);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: '     . (time() + $ttl));
    }

    /**
     * @param array<string,string> $extraHeaders
     */
    private static function abort(int $status, string $message, int $retryAfter = 60, array $extraHeaders = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: ' . $retryAfter);

        foreach ($extraHeaders as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode([
            'status'      => 'error',
            'code'        => $status,
            'message'     => $message,
            'retry_after' => $retryAfter,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
