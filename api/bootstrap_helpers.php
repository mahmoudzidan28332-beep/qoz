<?php
declare(strict_types=1);

/**
 * bootstrap_helpers.php
 * Extracted helper functions from bootstrap.php
 * - Health check endpoint handler
 * - Performance metrics shutdown registration
 */

function handle_health_check(array $container): void
{
    if (API_ROUTE !== '/health') {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src \'none\'');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $health = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'request_id' => REQUEST_ID,
        'checks' => [
            'database' => $container['pdo'] !== null,
            'redis' => class_exists('RedisHelper', false) && RedisHelper::isAvailable(),
            'cache' => $container['cache_manager'] !== null,
            'rbac' => class_exists('RBAC', false),
            'request_context' => isset($container['request_context']),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'execution_time_ms' => round((microtime(true) - START_TIME) * 1000, 1),
        ],
        'version' => API_VERSION,
        'environment' => ENVIRONMENT,
    ];

    $unhealthy = array_filter($health['checks'], fn($check) => $check === false);
    if (!empty($unhealthy)) {
        $health['status'] = 'unhealthy';
        http_response_code(503);
    }

    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}

function register_metrics_shutdown(array &$container): void
{
    $container['metrics'] = [
        'start_time' => START_TIME,
        'memory_start' => memory_get_usage(true),
        'request_id' => REQUEST_ID,
        'user_id' => $container['current_user']['id'] ?? null,
        'tenant_id' => $_SESSION['tenant_id'] ?? null,
        'route' => API_ROUTE,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    ];

    register_shutdown_function(function() use ($container) {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'request_id' => REQUEST_ID,
            'duration_ms' => round(($endTime - START_TIME) * 1000, 1),
            'memory_used_mb' => round(($endMemory - $container['metrics']['memory_start']) / 1048576, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'user_id' => $container['metrics']['user_id'],
            'tenant_id' => $container['metrics']['tenant_id'],
            'route' => $container['metrics']['route'],
            'method' => $container['metrics']['method'],
            'api_version' => API_VERSION,
            'status' => http_response_code(),
        ];

        // ✅ فلتر: لا تسجل الطلبات التالية
        $shouldSkipMetricLogging = false;
        
        // 1. تجاهل طلبات health check
        if (strpos($metrics['route'], 'health') !== false || strpos($metrics['route'], 'ping') !== false) {
            $shouldSkipMetricLogging = true;
        }
        
        // 2. تجاهل طلبات check/me لـ guest users
        if ($metrics['user_id'] === null && (strpos($metrics['route'], 'check') !== false || strpos($metrics['route'], 'me') !== false)) {
            $shouldSkipMetricLogging = true;
        }
        
        // 3. تجاهل أخطاء 404 و 401 العادية
        if (in_array($metrics['status'], [401, 404]) && $metrics['user_id'] === null) {
            $shouldSkipMetricLogging = true;
        }
        
        // 4. تجاهل طلبات OPTIONS (preflight)
        if ($metrics['method'] === 'OPTIONS') {
            $shouldSkipMetricLogging = true;
        }

        // تسجيل فقط إذا لم يتم التخطي
        if (!$shouldSkipMetricLogging) {
            safe_log('metric', 'Request completed', $metrics);
        }

        // ✅ تسجيل الأخطاء الحرجة فقط (بغض النظر عن الفلتر)
        if ($metrics['status'] >= 500) {
            safe_log('error', 'Critical error in request', $metrics);
        }

        if (class_exists('RedisHelper', false)) {
            try {
                RedisHelper::logMetric('request_duration', $metrics['duration_ms'], [
                    'route' => $metrics['route'],
                    'method' => $metrics['method'],
                    'tenant_id' => $metrics['tenant_id']
                ]);
            } catch (Throwable $e) {
                error_log('[Bootstrap] monitoring metrics failed: ' . $e->getMessage());
            }
        }
    });
}