<?php

declare(strict_types=1);

/**
 * SecurityHeaders.php — HTTP Security Headers
 *
 * Fixes:
 *   ✔ Missing Content-Type charset
 *   ✔ Missing Content-Security-Policy
 *   ✔ Missing X-Content-Type-Options
 *   ✔ Missing X-Frame-Options
 *   ✔ Missing Referrer-Policy
 *   ✔ Missing Permissions-Policy
 *   ✔ Missing Strict-Transport-Security (HTTPS only)
 *
 * Usage:
 *   SecurityHeaders::apply();   // Call once in bootstrap
 */

final class SecurityHeaders
{
    public static function apply(): void
    {
        // ── Prevent MIME-type sniffing (fixes X-Content-Type-Options warning)
        header('X-Content-Type-Options: nosniff');

        // ── Prevent clickjacking
        header('X-Frame-Options: DENY');

        // ── XSS protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // ── Referrer policy — don't leak internal URLs
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // ── Content Security Policy — strict for a pure API
        // If your API never serves HTML, this blocks all inline scripts/styles
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

        // ── Permissions Policy — disable unneeded browser features
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');

        // ── HSTS — only send over HTTPS (enable in production)
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // ── Remove fingerprinting headers
        header_remove('X-Powered-By');
        header_remove('Server');

        // ── Correct Content-Type with charset for all JSON responses
        // This is overridden per-response but ensures a safe default
        header('Content-Type: application/json; charset=UTF-8');
    }

    /**
     * Call this for file upload responses to enforce size limits early.
     * Returns false if the request body exceeds $maxMb MB.
     */
    public static function enforceUploadLimit(int $maxMb = 10): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $maxBytes      = $maxMb * 1024 * 1024;

        if ($contentLength > $maxBytes) {
            http_response_code(413);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'status'  => 'error',
                'code'    => 413,
                'message' => "Request body exceeds the maximum allowed size of {$maxMb}MB.",
            ]);
            exit;
        }

        // Also handle PHP upload errors
        if (isset($_FILES)) {
            foreach ($_FILES as $file) {
                if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                    || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
                    http_response_code(413);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'status'  => 'error',
                        'code'    => 413,
                        'message' => "Uploaded file exceeds the maximum allowed size of {$maxMb}MB.",
                    ]);
                    exit;
                }
            }
        }

        return true;
    }

    /**
     * Add CORS headers for your API.
     * Call after apply() if your API is consumed by a frontend.
     *
     * @param string[] $allowedOrigins
     */
    public static function cors(
        array $allowedOrigins = [],
        string $allowedMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        string $allowedHeaders = 'Content-Type, Authorization, X-Requested-With',
    ): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (empty($allowedOrigins)) {
            // Development: allow all (restrict in production)
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: '     . $allowedMethods);
        header('Access-Control-Allow-Headers: '     . $allowedHeaders);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Preflight request — return immediately
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
