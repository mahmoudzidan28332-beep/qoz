<?php

declare(strict_types=1);

// ════════════════════════════════════════════════════════════════════════════════
// Suspicious Activity Tracker
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
// Request Logger
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
