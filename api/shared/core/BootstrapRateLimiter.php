<?php
declare(strict_types=1);

/**
 * High-performance Global API Rate Limiter
 * Handles Redis with a file-system fallback.
 * Automatically tightens limits for auth endpoints to prevent brute-force (CWE-307)
 */

if (!IS_DEBUG) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $route = API_ROUTE;
    $userId = $container['current_user']['id'] ?? 0;
    $tenantId = $_SESSION['tenant_id'] ?? 1;
    
    $rateLimitKey = "ratelimit:{$tenantId}:{$ip}:{$route}:{$userId}";
    
    if (class_exists('RedisHelper', false)) {
        try {
            $redis = RedisHelper::getInstance();
            if ($redis === null) {
                throw new SystemException('Redis unavailable');
            }
            $requests = $redis->incr($rateLimitKey);
            $redis->expire($rateLimitKey, 60); // 1 minute window
            
            $maxRequests = getenv('RATE_LIMIT_MAX') ?: 1000;
            // Strict limit for AUTH endpoints
            if (strpos($route, 'register') !== false || strpos($route, 'verify_phone') !== false) {
                $maxRequests = 5;
            }

            if ($requests > $maxRequests) {
                safe_log('warning', 'Rate limit exceeded (Redis)', [
                    'ip' => $ip,
                    'route' => $route,
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'requests' => $requests
                ]);
                
                http_response_code(429);
                ResponseFormatter::error('Too many requests', 429);
                exit;
            }
        } catch (\RuntimeException $e) {
            // FALLBACK: File-based rate limiting if Redis is unavailable
            $maxRequests = getenv('RATE_LIMIT_MAX') ?: 1000;
            if (strpos($route, 'register') !== false || strpos($route, 'verify_phone') !== false) {
                $maxRequests = 5;
            }

            $cacheDir = sys_get_temp_dir() . '/api_rate_limits';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
            $cacheFile = $cacheDir . '/' . md5($rateLimitKey) . '.txt';
            $requests = 1;
            $now = time();
            
            if (file_exists($cacheFile)) {
                $data = explode('|', @file_get_contents($cacheFile));
                if (count($data) === 2 && ($now - (int)$data[0]) < 60) {
                    $requests = (int)$data[1] + 1;
                }
            }
            
            @file_put_contents($cacheFile, $now . '|' . $requests);
            
            if ($requests > $maxRequests) {
                safe_log('warning', 'Rate limit exceeded (File Fallback)', [
                    'ip' => $ip, 'route' => $route, 'requests' => $requests 
                ]);
                http_response_code(429);
                ResponseFormatter::error('Too many requests', 429);
                exit;
            }
        }
    }
}
