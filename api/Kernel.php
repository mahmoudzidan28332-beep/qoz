<?php

declare(strict_types=1);

/**
 * Kernel – Auto Route Loader (FINAL PRODUCTION v2)
 * (Updated: Removed special handling for resource_permissions, now handled as a regular route file)
 */

final class Kernel
{
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Remove /api prefix
        $uri = preg_replace('#^/api#', '', $uri);
        $uri = '/' . trim($uri, '/');

        // Health check
        if (preg_match('#^/(v\d+/)?(admin|mobile)?/?health$#', $uri)) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Security-Policy: default-src \'none\'');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'time'   => date('c'),
            ]);
            exit;
        }

        // Standard route resolution (resource_permissions now handled like others)
        $routeFile = self::resolveRouteFile($uri);
        if (!$routeFile) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Security-Policy: default-src \'none\'');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            echo json_encode([
                'success' => false,
                'error'   => 'Route not found',
                'path'    => $uri,
                'method'  => $method,
            ]);
            exit;
        }

        require $routeFile;
        exit;
    }

    /**
     * Resolve route file from URI
     */
    private static function resolveRouteFile(string $uri): ?string
    {
        $version = 'v1'; // Default version
        $parts = explode('/', trim($uri, '/'));

        if (isset($parts[0]) && preg_match('/^v\d+$/', $parts[0])) {
            $version = array_shift($parts);
        }

        $base = __DIR__ . '/' . $version . '/routes';

        $prefix = $parts[0] ?? '';
        if ($prefix === 'admin' || $prefix === 'mobile') {
            $name = $parts[1] ?? '';
        } else {
            $name = $prefix;
        }

        if ($name === '') return null;
        $file = $base . '/' . $name . '.php';
        return is_file($file) ? $file : null;
    }
}