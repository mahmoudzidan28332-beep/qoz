<?php

declare(strict_types=1);

// ════════════════════════════════════════════════════════════════════════════════
// Rate Limiter
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
     * Uses flock() to ensure atomic read-modify-write under concurrent requests.
     *
     * @return array{allowed: bool, current: int, limit: int, reset_in: int}
     */
    public function check(string $key, int $max, int $windowSeconds): array
    {
        $file = $this->storageDir . '/rate/' . hash('sha256', $key) . '.json';
        $now  = time();

        // Atomic read-modify-write with exclusive lock
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return ['allowed' => true, 'current' => 0, 'limit' => $max, 'reset_in' => $windowSeconds];
        }

        flock($fh, LOCK_EX);

        $content = stream_get_contents($fh);
        $data = ($content !== '' && $content !== false) ? @json_decode($content, true) : null;
        if (!is_array($data) || !isset($data['window_start'])) {
            $data = ['window_start' => $now, 'count' => 0];
        }

        // Reset window if expired
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
// IP Blocker
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
