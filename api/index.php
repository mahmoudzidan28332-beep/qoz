<?php
declare(strict_types=1);

/**
 * ==================================================
 * API Front Controller (FINAL)
 * ==================================================
 */

// ── Local file logger — captures errors even when php error_log = /dev/null ──
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

function _kernel_log(string $msg): void
{
    static $logFile = null;
    if ($logFile === null) {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $logFile = is_writable($dir) ? $dir . '/auth_errors.log' : false;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if ($logFile) {
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    error_log($msg); // also try system log (may be /dev/null)
}

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    _kernel_log("[api/index.php] PHP Error #{$errno}: {$errstr} in {$errfile}:{$errline}");
    return false;
});
set_exception_handler(function (Throwable $e): void {
    _kernel_log('[api/index.php] Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Internal server error'], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _kernel_log("[api/index.php] FATAL: {$err['message']} in {$err['file']}:{$err['line']}");
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Internal server error', 'hint' => 'Check logs/auth_errors.log'], JSON_UNESCAPED_UNICODE);
    }
});

// ── Failsafe rate limiting (runs BEFORE bootstrap/middleware) ────────────────
// This standalone limiter guarantees rate-limit enforcement even if the full
// SecurityMiddleware fails to load (e.g. missing file, exception during boot).
(function () {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir  = sys_get_temp_dir() . '/security_middleware/rate';
    @mkdir($dir, 0750, true);

    $globalFile = $dir . '/' . hash('sha256', "failsafe:ip:{$ip}:global") . '.json';
    $now  = time();
    $max  = (int)(getenv('RATE_LIMIT_IP_MAX') ?: 50);    // requests per window
    $win  = 60;                                            // window in seconds

    $fh = @fopen($globalFile, 'c+');
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
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'');
        header('Retry-After: ' . $retryAfter);
        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . ($now + $retryAfter));
        echo json_encode(['error' => 'Too many requests.', 'status' => 429]);
        exit;
    }
})();

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Kernel.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'; frame-ancestors \'none\'');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

Kernel::dispatch();
