<?php
declare(strict_types=1);
// htdocs/api/shared/core/ResponseFormatter.php

final class ResponseFormatter
{
    /* =========================
     * Translation
     * ========================= */

    private static function t(string $message): string
    {
        try {
            if (function_exists('container')) {
                $i18n = container('i18n');
                if ($i18n && method_exists($i18n, 't')) {
                    return (string) $i18n->t($message);
                }
            }

            if (isset($GLOBALS['i18n']) && method_exists($GLOBALS['i18n'], 't')) {
                return (string) $GLOBALS['i18n']->t($message);
            }
        } catch (Throwable $e) {
            error_log('[ResponseFormatter] translation lookup failed: ' . $e->getMessage());
        }

        return $message;
    }

    /* =========================
     * Core responder
     * ========================= */

    private static function respond(array $payload, int $status): void
    {
        // Set headers if possible
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            
            // Prevent all caching of API responses
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        // Encode payload safely
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            // Fallback minimal error JSON if encoding failed
            $json = json_encode([
                'success' => false,
                'message' => 'Response encoding error'
            ]);
            if ($json === false) {
                // Last resort plain text
                echo '{"success":false,"message":"Response encoding error"}';
                // ensure termination
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                exit;
            }
        }

        echo $json;

        // Flush and finish request to ensure no further output is sent to client
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        // Terminate script execution to avoid duplicate responses
        // 🔒 SECURITY: In test mode, we skip exit to allow multiple test cases
        if (defined('TEST_MODE') && TEST_MODE) {
            return;
        }
        exit;
    }


    /* =========================
     * Public API
     * ========================= */

    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200
    ): void {
        self::respond([
            'success' => true,
            'message' => self::t($message),
            'data'    => $data,
            'meta'    => self::meta(),
        ], $status);
    }

    public static function error(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null
    ): void {
        $payload = [
            'success' => false,
            'message' => self::t($message),
            'meta'    => self::meta(),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::respond($payload, $status);
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        self::error($message, 404);
    }

    public static function serverError(mixed $details = null): void
    {
        $message = 'Internal Server Error';
        $errors  = null;

        // Only expose details if DEBUG_MODE is explicitly enabled
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            if (is_string($details)) {
                $message = $details;
            } elseif (is_array($details)) {
                $errors = $details;
            }
        }

        self::error($message, 500, $errors);
    }


    /* =========================
     * Meta
     * ========================= */

    private static function meta(): array
    {
        return [
            'time'       => date('c'),
            'request_id' => defined('REQUEST_ID') ? REQUEST_ID : ($_SERVER['REQUEST_ID'] ?? null),
        ];
    }
}