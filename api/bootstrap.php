<?php
declare(strict_types=1);

/**
 * htdocs/api/bootstrap.php
 * PRODUCTION READY - Stable Version with Enhanced Logging
 * Version: 4.0 - Platform Admin Support
 * 
 * Features:
 * - Unified session management
 * - Platform Admin support (platform_users table)
 * - Enhanced identity logging with user details
 * - Multi-tenant support
 * - Production optimized
 */

define('BASE_DIR', __DIR__);
define('API_BASE_PATH', realpath(__DIR__));
define('API_SHARED_PATH', API_BASE_PATH . '/shared');

require_once __DIR__ . '/bootstrap_helpers.php';

// ==============================================
// 0. Environment & Error Handling
// ==============================================
define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
define('IS_DEBUG', ENVIRONMENT === 'development' || filter_var(getenv('DEBUG'), FILTER_VALIDATE_BOOLEAN));
define('START_TIME', microtime(true));
define('REQUEST_ID', bin2hex(random_bytes(8)));

ini_set('display_errors', IS_DEBUG ? '1' : '0');
ini_set('display_startup_errors', IS_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('memory_limit', getenv('MEMORY_LIMIT') ?: '256M');
error_reporting(IS_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);

$timeout = IS_DEBUG ? 300 : (getenv('SCRIPT_TIMEOUT') ?: 30);
set_time_limit($timeout);

// ==============================================
// 0.5 Filter Non-Essential Requests (Prevent Log Pollution)
// ==============================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$shouldSkipLogging = false;

// Skip health check endpoints
$skipPaths = ['/health', '/ping', '/metrics', '/heartbeat', '/cron', '/status'];
foreach ($skipPaths as $path) {
    if (strpos($requestUri, $path) !== false) {
        $shouldSkipLogging = true;
        break;
    }
}

// Skip known bots
$bots = ['Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'AhrefsBot', 'SemrushBot', 'YandexBot', 'Baiduspider'];
foreach ($bots as $bot) {
    if (stripos($userAgent, $bot) !== false) {
        $shouldSkipLogging = true;
        break;
    }
}

// Skip CLI requests
if (php_sapi_name() === 'cli') {
    $shouldSkipLogging = true;
}

// For health checks - respond quickly
if (strpos($requestUri, '/health') !== false || strpos($requestUri, '/ping') !== false) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

// ==============================================
// 1. Advanced Logging System
// ==============================================
function safe_log(string $level, string $message, array $context = []): void
{
    static $bufferSize = 10;

    if (defined('SKIP_LOGGING') && SKIP_LOGGING === true) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $context = array_merge($context, [
        'request_id' => REQUEST_ID,
        'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
        'timestamp' => $timestamp
    ]);
    $contextStr = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    $line = sprintf('[%s] [%s] [RID:%s] %s%s' . "\n", $timestamp, $level, REQUEST_ID, $message, $contextStr);

    if (!isset($GLOBALS['log_buffer'])) {
        $GLOBALS['log_buffer'] = [];
    }

    $GLOBALS['log_buffer'][] = $line;
    
    if (count($GLOBALS['log_buffer']) >= $bufferSize) {
        safe_flush_logs($GLOBALS['log_buffer']);
        $GLOBALS['log_buffer'] = [];
    }

    if ($level === 'critical' || $level === 'emergency') {
        safe_flush_logs($GLOBALS['log_buffer']);
        $GLOBALS['log_buffer'] = [];
    }
}

function safe_flush_logs(array $buffer): void
{
    static $logFile = BASE_DIR . '/logs/app.log';
    static $isDirChecked = false;

    if (!$isDirChecked) {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $isDirChecked = true;
    }

    try {
        if (is_writable(dirname($logFile))) {
            file_put_contents($logFile, implode('', $buffer), FILE_APPEND | LOCK_EX);
        } else {
            error_log(implode('', $buffer));
        }
    } catch (Throwable $e) {
        error_log('Logging failed: ' . $e->getMessage());
    }
}

define('SKIP_LOGGING', $shouldSkipLogging);

register_shutdown_function(function() {
    if (function_exists('safe_flush_logs') && isset($GLOBALS['log_buffer'])) {
        safe_flush_logs($GLOBALS['log_buffer']);
    }
});

if (!$shouldSkipLogging) {
    safe_log('info', 'Bootstrap initializing', ['env' => ENVIRONMENT]);
}

// ==============================================
// 2. API Version Detection
// ==============================================
function detect_api_version(): array {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    $version = 'v1';
    $route = '';
    
    if (preg_match('#^/api/(v\d+)/?(.*)$#', $path, $matches)) {
        $version = $matches[1];
        $route = '/' . trim($matches[2], '/');
    } elseif (preg_match('#^/api/?(.*)$#', $path, $matches)) {
        $route = '/' . trim($matches[1], '/');
    }
    
    if (!preg_match('/^v\d+$/', $version)) {
        $version = 'v1';
    }
    
    $versionPath = API_BASE_PATH . '/' . $version;
    $versionShared = API_SHARED_PATH . '/' . $version;
    
    return [
        'version' => $version,
        'route' => $route,
        'path' => $path,
        'version_path' => $versionPath,
        'version_shared' => $versionShared,
        'is_versioned' => is_dir($versionPath),
    ];
}

$routing = detect_api_version();
define('API_VERSION', $routing['version']);
define('API_ROUTE', $routing['route']);
define('API_VERSION_PATH', $routing['version_path']);
define('API_VERSION_SHARED', $routing['version_shared']);
define('IS_VERSIONED_API', $routing['is_versioned']);

if (!$shouldSkipLogging) {
    safe_log('info', 'API routing detected', [
        'version' => API_VERSION,
        'route' => API_ROUTE,
    ]);
}

// ==============================================
// 3. Load .env
// ==============================================
$envPath = BASE_DIR . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $envLoaded = 0;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2) + [1 => ''];
        putenv(trim($key) . '=' . trim($value));
        $envLoaded++;
    }
    if (!$shouldSkipLogging) {
        safe_log('info', '.env loaded', ['variables' => $envLoaded]);
    }
}

// ==============================================
// 4. Load Advanced Logger
// ==============================================
$loggerPath = BASE_DIR . '/shared/core/Logger.php';
if (file_exists($loggerPath)) {
    try {
        require_once $loggerPath;
        if (class_exists('Logger', false)) {
            Logger::setLogFile(BASE_DIR . '/logs/app.log');
            Logger::setRequestId(REQUEST_ID);
            if (!$shouldSkipLogging) {
                Logger::info('Advanced Logger initialized');
            }
        }
    } catch (Throwable $e) {
        if (!$shouldSkipLogging) {
            safe_log('warning', 'Advanced Logger failed to load', ['error' => $e->getMessage()]);
        }
    }
}

// ==============================================
// 5. Load Configuration Files
// ==============================================
$configCache = [];
function load_config_file(string $path): ?array {
    global $configCache;
    
    if (isset($configCache[$path])) {
        return $configCache[$path];
    }
    
    if (file_exists($path)) {
        $config = require $path;
        $configCache[$path] = $config;
        return $config;
    }
    
    return null;
}

// Version-specific configs
if (IS_VERSIONED_API) {
    $versionConfigs = [
        'constants' => '/config/constants.php',
        'config'    => '/config/config.php',
        'db'        => '/config/db.php',
        'cors'      => '/config/cors.php',
    ];

    foreach ($versionConfigs as $name => $relPath) {
        $path = API_VERSION_PATH . $relPath;
        load_config_file($path);
    }
}

// Global configs
$configs = [
    'constants' => '/shared/config/constants.php',
    'config'    => '/shared/config/config.php',
    'db'        => '/shared/config/db.php',
    'cors'      => '/shared/config/cors.php',
];

foreach ($configs as $name => $relPath) {
    $path = BASE_DIR . $relPath;
    load_config_file($path);
}

// ==============================================
// 6. Load Session Config (CRITICAL - MUST BE FIRST)
// ==============================================
$sessionConfigPath = BASE_DIR . '/shared/config/session.php';
if (file_exists($sessionConfigPath)) {
    require_once $sessionConfigPath;
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!$shouldSkipLogging) {
            safe_log('info', 'Session config loaded', [
                'session_id' => session_id(),
                'session_name' => session_name(),
                'session_cookie_received' => $_COOKIE[session_name()] ?? null,
                'session_status' => session_status(),
            ]);
        }
    }
} else {
    if (!$shouldSkipLogging) {
        safe_log('warning', 'Session config not found', ['path' => $sessionConfigPath]);
    }
}

// ==============================================
// 6.5 Ensure Session Active (CRITICAL FOR PLATFORM ADMIN)
// ==============================================
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'APP_SESSID') {
        session_name('APP_SESSID');
    }
    session_start();
    if (!$shouldSkipLogging) {
        safe_log('info', 'Session started explicitly', [
            'session_id' => session_id(),
            'session_name' => session_name(),
        ]);
    }
}

// ==============================================
// 7. Load Core Classes
// ==============================================
$coreFiles = [
    'ConfigLoader.php',
    'DomainException.php',
    'DatabaseException.php',
    'ApplicationException.php',
    'AuthException.php',
    'AuthorizationException.php',
    'SystemException.php',
    'ExceptionHandler.php',
    'DatabaseConnection.php',
    'ResponseFormatter.php',
    'BaseModel.php',
    'CacheManager.php',
    'QueueManager.php',
];

foreach ($coreFiles as $file) {
    $path = BASE_DIR . "/shared/core/{$file}";
    if (file_exists($path)) {
        require_once $path;
    }
}

if (class_exists('ExceptionHandler', false)) {
    ExceptionHandler::register();
}

// ==============================================
// 8. Security Middleware
// ==============================================
$secMiddlewarePath = BASE_DIR . '/shared/security/SecurityMiddleware.php';
if (file_exists($secMiddlewarePath)) {
    require_once $secMiddlewarePath;
    SecurityMiddleware::boot([
        'storageDir'            => sys_get_temp_dir() . '/security_middleware',
        'rateLimitIpMax'        => (int)(getenv('RATE_LIMIT_IP_MAX') ?: 300),
        'rateLimitIpWindow'     => 60,
        'rateLimitAuthMax'      => (int)(getenv('RATE_LIMIT_AUTH_MAX') ?: 10),
        'rateLimitAuthWindow'   => 60,
        'rateLimitWriteMax'     => (int)(getenv('RATE_LIMIT_WRITE_MAX') ?: 60),
        'rateLimitWriteWindow'  => 60,
        'blockAfterViolations'  => 5,
        'blockDuration'         => 300,
        'rateLimitWhitelist'    => ['/ping', '/status', '/health'],
    ]);
    if (!$shouldSkipLogging) {
        safe_log('info', 'SecurityMiddleware booted');
    }
}

// Upload size protection
$maxUploadBytes = (int)(getenv('MAX_UPLOAD_SIZE') ?: 20 * 1024 * 1024);
$contentLength  = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxUploadBytes && $contentLength > 0) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Request entity too large']);
    exit;
}

// ==============================================
// 9. Load Helpers
// ==============================================
$redisHelperPath = BASE_DIR . '/shared/helpers/RedisHelper.php';
if (file_exists($redisHelperPath)) {
    require_once $redisHelperPath;
    if (!$shouldSkipLogging) {
        safe_log('info', 'RedisHelper loaded');
    }
}

// ==============================================
// 10. Load Application Context
// ==============================================
$requestContextPath = BASE_DIR . '/shared/application/Context/RequestContext.php';
if (file_exists($requestContextPath)) {
    require_once $requestContextPath;
}

$identityFiles = [
    BASE_DIR . '/shared/application/Auth/UserIdentity.php',
    BASE_DIR . '/shared/application/Auth/UserIdentityResolver.php',
];
foreach ($identityFiles as $identityFile) {
    if (file_exists($identityFile)) {
        require_once $identityFile;
    }
}

// Essential helpers
$essentialHelpers = [
    'RBAC.php',
    'auth_helper.php',
    'jwt.php',
    'security.php',
    'CSRF.php',
    'utils.php',
];

foreach ($essentialHelpers as $helper) {
    $path = BASE_DIR . "/shared/helpers/{$helper}";
    if (file_exists($path)) {
        require_once $path;
    }
}

// Version-specific helpers
if (IS_VERSIONED_API) {
    $versionHelpersPath = API_VERSION_SHARED . '/helpers';
    if (is_dir($versionHelpersPath)) {
        $versionHelpers = glob($versionHelpersPath . '/*.php');
        foreach ($versionHelpers as $helperPath) {
            require_once $helperPath;
        }
    }
}

// ==============================================
// 11. Database Connection
// ==============================================
$container = [
    'pdo'              => null,
    'current_user'     => null,
    'cache_manager'    => null,
    'metrics'          => [],
];

try {
    if (class_exists('DatabaseConnection', false)) {
        $pdo = DatabaseConnection::getConnection();
        $container['pdo'] = $pdo;

        if (method_exists('BaseModel', 'setPDO')) {
            BaseModel::setPDO($pdo);
        }
        
        $pdo->query('SELECT 1');
        if (!$shouldSkipLogging) {
            safe_log('info', 'Database connection established');
        }
    }
} catch (Throwable $e) {
    if (!$shouldSkipLogging) {
        safe_log('critical', 'Database connection failed', [
            'message' => $e->getMessage(),
        ]);
    }
    $container['pdo'] = null;
}

// ==============================================
// 12. Cache Manager
// ==============================================
if (class_exists('CacheManager', false)) {
    try {
        $container['cache_manager'] = CacheManager::getInstance();
        if (!$shouldSkipLogging) {
            safe_log('info', 'CacheManager initialized');
        }
    } catch (Throwable $e) {
        if (!$shouldSkipLogging) {
            safe_log('warning', 'CacheManager failed', ['error' => $e->getMessage()]);
        }
    }
}

// ==============================================
// 13. Unified Identity Resolution - WITH PLATFORM ADMIN SUPPORT
// ==============================================
$authMethodsUsed = [];
$identity = null;

// ⭐ CRITICAL: Log platform admin session status before resolution
if (!$shouldSkipLogging) {
    safe_log('debug', 'Session status before identity resolution', [
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'session_id' => session_id(),
        'has_platform_admin' => !empty($_SESSION['platform_admin']),
        'platform_role' => $_SESSION['platform_role'] ?? null,
        'session_user_id' => $_SESSION['user_id'] ?? null,
        'session_keys' => array_keys($_SESSION ?? []),
    ]);
}

if (class_exists('\Shared\Application\Auth\UserIdentityResolver', false)) {
    try {
        // ⭐ Force fresh resolution with platform admin data
        $identity = \Shared\Application\Auth\UserIdentityResolver::resolve($container['pdo'], [
            'request_id' => REQUEST_ID,
            'force' => true,
        ]);

        $container['identity'] = $identity;
        $container['current_user'] = $identity->isAuthenticated() ? $identity->toArray() : null;
        $authMethodsUsed[] = $identity->source();

        // Enhanced logging
        if ($identity->isAuthenticated()) {
            $userData = [
                'user_id' => $identity->id(),
                'tenant_id' => $identity->tenantId(),
                'username' => $identity->username(),
                'email' => $identity->email(),
                'roles' => $identity->roles(),
                'permissions_count' => count($identity->permissions()),
                'identity_source' => $identity->source(),
                'session_id' => session_id(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'is_platform_admin' => $identity->isPlatformAdmin(),
                'platform_role' => $identity->platformRole(),
            ];
            
            safe_log('info', '🔐 USER AUTHENTICATED', $userData);
        } else {
            if (IS_DEBUG && !$shouldSkipLogging) {
                safe_log('debug', '👤 GUEST ACCESS', [
                    'session_id' => session_id(),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            }
        }
    } catch (Throwable $e) {
        if (!$shouldSkipLogging) {
            safe_log('error', 'Identity resolution failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!$identity instanceof \Shared\Application\Auth\UserIdentity) {
    $identity = \Shared\Application\Auth\UserIdentity::guest(REQUEST_ID);
    $container['identity'] = $identity;
    $container['current_user'] = null;
}

// ⭐ Ensure GLOBALS have platform admin flags
if ($identity->isPlatformAdmin()) {
    $GLOBALS['IS_PLATFORM_ADMIN'] = true;
    $GLOBALS['PLATFORM_ROLE'] = $identity->platformRole();
}

// ==============================================
// 14. RequestContext Creation
// ==============================================
if (class_exists('\Shared\Application\Context\RequestContext', false)) {
    try {
        $contextAttributes = [
            'start_time' => START_TIME,
            'environment' => ENVIRONMENT,
            'api_version' => API_VERSION,
            'api_route' => API_ROUTE,
            'is_platform_admin' => $identity->isPlatformAdmin(),
            'platform_role' => $identity->platformRole(),
        ];

        $requestContextClass = \Shared\Application\Context\RequestContext::class;

        if (method_exists($requestContextClass, 'boot')) {
            $context = $requestContextClass::boot($identity, REQUEST_ID, $contextAttributes);
            $contextFactory = 'boot';
        } elseif (method_exists($requestContextClass, 'fromIdentity')) {
            $context = $requestContextClass::fromIdentity($identity, REQUEST_ID, $contextAttributes);
            $contextFactory = 'fromIdentity';
        } else {
            $context = new $requestContextClass(
                REQUEST_ID,
                $identity->tenantId(),
                $identity->id(),
                $identity->roles(),
                $identity->permissions(),
                $identity->preferredLanguage(),
                $identity->timezone(),
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                array_merge($identity->attributes(), [
                    'identity_source' => $identity->source(),
                    'identity_request_id' => $identity->requestId(),
                    'resource_permissions' => $identity->resourcePermissions(),
                    'is_platform_admin' => $identity->isPlatformAdmin(),
                    'platform_role' => $identity->platformRole(),
                ], $contextAttributes)
            );
            $contextFactory = 'constructor';
        }

        $container['request_context'] = $context;
        $GLOBALS['request_context'] = $context;

        if (!$shouldSkipLogging && $identity->isAuthenticated()) {
            safe_log('info', 'RequestContext created', [
                'user_id' => $identity->id(),
                'tenant_id' => $identity->tenantId(),
                'roles_count' => count($identity->roles()),
                'permissions_count' => count($identity->permissions()),
                'source' => $identity->source(),
                'factory' => $contextFactory,
                'is_platform_admin' => $identity->isPlatformAdmin(),
            ]);
        }
    } catch (Throwable $e) {
        if (!$shouldSkipLogging) {
            safe_log('error', 'Failed to create RequestContext', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

// ==============================================
// 15. Global Container Setup
// ==============================================
$containerPath = BASE_DIR . '/shared/application/Container.php';
if (file_exists($containerPath)) {
    require_once $containerPath;
}
$GLOBALS['CONTAINER']      = $container;
$GLOBALS['ADMIN_DB']       = $container['pdo'];
$GLOBALS['ADMIN_USER']     = $container['current_user'];
$GLOBALS['ADMIN_IDENTITY'] = $identity;
$GLOBALS['app_container']  = new \Shared\Application\Container($GLOBALS['ADMIN_DB']);

// ==============================================
// 16. Admin UI Bootstrap (Conditional)
// ==============================================
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') === 0) {
    $adminBootstrap = BASE_DIR . '/bootstrap_admin_ui.php';
    if (file_exists($adminBootstrap)) {
        require_once $adminBootstrap;
    }
}

// ==============================================
// 17. Rate Limiting (Production only)
// ==============================================
if (!IS_DEBUG && !$shouldSkipLogging) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $route = API_ROUTE;
    $userId = $identity->id() ?? 0;
    $tenantId = $identity->tenantId() ?? 0;

    $rateLimitKey = "ratelimit:{$tenantId}:{$ip}:{$route}:{$userId}";

    $incrementRateLimit = static function (string $key, int $window): array {
        if (class_exists('RedisHelper', false)) {
            try {
                $redis = RedisHelper::getInstance();
                if ($redis !== null) {
                    $count = (int)$redis->incr($key);
                    if ($count === 1) {
                        $redis->expire($key, $window);
                    }
                    return ['count' => $count, 'ttl' => max(0, (int)$redis->ttl($key)), 'backend' => 'redis'];
                }
            } catch (Throwable $e) {
                if (function_exists('safe_log')) {
                    safe_log('warning', 'Redis rate limit failed, falling back to file', ['error' => $e->getMessage()]);
                }
            }
        }

        $storageDir = BASE_DIR . '/storage/rate_limits';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $path = $storageDir . '/' . md5($key) . '.json';
        $now = time();
        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            return ['count' => 1, 'ttl' => $window, 'backend' => 'memory'];
        }

        flock($handle, LOCK_EX);
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($data) || ($data['expires_at'] ?? 0) <= $now) {
            $data = ['count' => 0, 'expires_at' => $now + $window];
        }

        $data['count']++;

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['count' => $data['count'], 'ttl' => max(0, $data['expires_at'] - $now), 'backend' => 'file'];
    };

    $limitState = $incrementRateLimit($rateLimitKey, 60);
    $maxRequests = (int)(getenv('RATE_LIMIT_MAX') ?: 1000);

    header('X-RateLimit-Limit: ' . $maxRequests);
    header('X-RateLimit-Remaining: ' . max(0, $maxRequests - $limitState['count']));
    header('X-RateLimit-Reset: ' . (time() + $limitState['ttl']));

    if ($limitState['count'] > $maxRequests) {
        safe_log('warning', 'Rate limit exceeded', ['ip' => $ip, 'route' => $route]);
        http_response_code(429);
        header('Retry-After: ' . $limitState['ttl']);
        echo json_encode(['success' => false, 'error' => 'Too many requests']);
        exit;
    }
}

// ==============================================
// 18. Health Check Handler
// ==============================================
if (strpos($requestUri, '/health') !== false || strpos($requestUri, '/ping') !== false) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => REQUEST_ID,
    ]);
    exit;
}

// ==============================================
// 19. Performance Metrics
// ==============================================
register_metrics_shutdown($container);

// ==============================================
// 20. Bootstrap Completion Log
// ==============================================
if (!$shouldSkipLogging) {
    $completionData = [
        'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'auth_method' => $authMethodsUsed[0] ?? 'guest',
        'api_version' => API_VERSION,
        'execution_time_ms' => round((microtime(true) - START_TIME) * 1000, 1),
    ];
    
    if ($identity->isAuthenticated()) {
        $completionData['user_id'] = $identity->id();
        $completionData['tenant_id'] = $identity->tenantId();
        $completionData['username'] = $identity->username();
        $completionData['is_platform_admin'] = $identity->isPlatformAdmin();
        $completionData['platform_role'] = $identity->platformRole();
    }
    
    safe_log('info', 'Bootstrap completed successfully', $completionData);
}