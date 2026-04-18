<?php
declare(strict_types=1);

/**
 * api/diagnose.php — Diagnostic endpoint for debugging 500 errors
 * 
 * Access: GET /api/diagnose or GET /api/diagnose.php
 * Returns JSON report of system health checks.
 * 
 * ⚠️  Remove or restrict this file in production after debugging.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('Content-Security-Policy: default-src \'none\'');

$checks = [];
$errors = [];

// ── 1. PHP version ──────────────────────────────────────────────────────────
$checks['php_version'] = [
    'value'  => PHP_VERSION,
    'ok'     => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'OK' : 'PHP 8.0+ required',
];

// ── 2. Required extensions ──────────────────────────────────────────────────
$requiredExts = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'session'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    $checks["ext_{$ext}"] = ['ok' => $loaded, 'detail' => $loaded ? 'loaded' : 'MISSING'];
    if (!$loaded) {
        $errors[] = "Extension '{$ext}' not loaded";
    }
}

// ── 3. Database connection ──────────────────────────────────────────────────
$checks['db_config_file'] = ['ok' => false, 'detail' => ''];
$dbCfgFile = __DIR__ . '/shared/config/db.php';
if (is_file($dbCfgFile)) {
    $checks['db_config_file'] = ['ok' => true, 'detail' => 'exists'];
    try {
        $cfg = include $dbCfgFile;
        $checks['db_config_parse'] = ['ok' => is_array($cfg), 'detail' => is_array($cfg) ? 'valid array' : 'not array'];
        
        if (is_array($cfg)) {
            $host    = $cfg['host'] ?? ($cfg['DB_HOST'] ?? 'localhost');
            $dbname  = $cfg['name'] ?? ($cfg['dbname'] ?? ($cfg['DB_NAME'] ?? ''));
            $user    = $cfg['username'] ?? ($cfg['user'] ?? ($cfg['DB_USER'] ?? ''));
            $pass    = $cfg['password'] ?? ($cfg['pass'] ?? ($cfg['DB_PASS'] ?? ''));
            $charset = $cfg['charset'] ?? 'utf8mb4';
            
            $checks['db_credentials'] = [
                'ok'     => ($host !== '' && $dbname !== '' && $user !== ''),
                'detail' => "host={$host}, db={$dbname}, user={$user}",
            ];
            
            try {
                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]);
                $checks['db_connection'] = ['ok' => true, 'detail' => 'connected'];
                
                // Check users table
                try {
                    $st = $pdo->query("SELECT COUNT(*) as cnt FROM users LIMIT 1");
                    $row = $st->fetch();
                    $checks['db_users_table'] = ['ok' => true, 'detail' => "users table has {$row['cnt']} rows"];
                } catch (Throwable $e) {
                    $checks['db_users_table'] = ['ok' => false, 'detail' => $e->getMessage()];
                    $errors[] = "users table: " . $e->getMessage();
                }
                
                // Check tenant_users table  
                try {
                    $pdo->query("SELECT 1 FROM tenant_users LIMIT 1");
                    $checks['db_tenant_users_table'] = ['ok' => true, 'detail' => 'exists'];
                } catch (Throwable $e) {
                    $checks['db_tenant_users_table'] = ['ok' => false, 'detail' => $e->getMessage()];
                    $errors[] = "tenant_users table: " . $e->getMessage();
                }
                
                // Check roles table
                try {
                    $pdo->query("SELECT 1 FROM roles LIMIT 1");
                    $checks['db_roles_table'] = ['ok' => true, 'detail' => 'exists'];
                } catch (Throwable $e) {
                    $checks['db_roles_table'] = ['ok' => false, 'detail' => $e->getMessage()];
                }
                
            } catch (Throwable $e) {
                $checks['db_connection'] = ['ok' => false, 'detail' => $e->getMessage()];
                $errors[] = "DB connection: " . $e->getMessage();
            }
        }
    } catch (Throwable $e) {
        $checks['db_config_parse'] = ['ok' => false, 'detail' => $e->getMessage()];
        $errors[] = "DB config: " . $e->getMessage();
    }
} else {
    $checks['db_config_file'] = ['ok' => false, 'detail' => 'NOT FOUND at ' . $dbCfgFile];
    $errors[] = "db.php not found";
}

// ── 4. Session ──────────────────────────────────────────────────────────────
$sessionDir = __DIR__ . '/storage/sessions';
$checks['session_dir'] = [
    'ok'     => is_dir($sessionDir) && is_writable($sessionDir),
    'detail' => is_dir($sessionDir)
        ? (is_writable($sessionDir) ? 'exists & writable' : 'exists but NOT writable')
        : 'does NOT exist',
];

$tempDir = sys_get_temp_dir();
$checks['tmp_dir'] = [
    'ok'     => is_writable($tempDir),
    'detail' => $tempDir . (is_writable($tempDir) ? ' (writable)' : ' (NOT writable)'),
];

// Test session_start
try {
    if (session_status() === PHP_SESSION_NONE) {
        $savePath = ini_get('session.save_path');
        $checks['session_save_path'] = [
            'ok'     => true,
            'detail' => $savePath ?: '(default)',
        ];
        
        // Don't actually start session in diagnostics — just report
        $checks['session_status'] = ['ok' => true, 'detail' => 'ready (not started)'];
    } else {
        $checks['session_status'] = ['ok' => true, 'detail' => 'already active'];
    }
} catch (Throwable $e) {
    $checks['session_status'] = ['ok' => false, 'detail' => $e->getMessage()];
    $errors[] = "Session: " . $e->getMessage();
}

// ── 5. File system checks ───────────────────────────────────────────────────
$criticalFiles = [
    'auth.php'                          => __DIR__ . '/auth.php',
    'bootstrap.php'                     => __DIR__ . '/bootstrap.php',
    'index.php'                         => __DIR__ . '/index.php',
    'Kernel.php'                        => __DIR__ . '/Kernel.php',
    'bootstrap_helpers.php'             => __DIR__ . '/bootstrap_helpers.php',
    'shared/config/db.php'              => __DIR__ . '/shared/config/db.php',
    'shared/config/config.php'          => __DIR__ . '/shared/config/config.php',
    'shared/config/session.php'         => __DIR__ . '/shared/config/session.php',
    'shared/config/DatabaseConnection.php' => __DIR__ . '/shared/config/DatabaseConnection.php',
    'shared/security/SecurityMiddleware.php' => __DIR__ . '/shared/security/SecurityMiddleware.php',
    'shared/security/SecurityRateLimiter.php' => __DIR__ . '/shared/security/SecurityRateLimiter.php',
];

foreach ($criticalFiles as $label => $path) {
    $exists = is_file($path);
    $checks["file_{$label}"] = [
        'ok'     => $exists,
        'detail' => $exists ? 'exists' : 'MISSING',
    ];
    if (!$exists) {
        $errors[] = "Missing file: {$label}";
    }
}

// ── 6. Rate limiter directory ───────────────────────────────────────────────
$rlDir = sys_get_temp_dir() . '/security_middleware/rate';
$checks['rate_limit_dir'] = [
    'ok'     => is_dir($rlDir) && is_writable($rlDir),
    'detail' => is_dir($rlDir)
        ? (is_writable($rlDir) ? 'exists & writable' : 'exists but NOT writable')
        : 'does not exist (will be auto-created)',
];

// ── 7. .htaccess checks ────────────────────────────────────────────────────
$htaccess = __DIR__ . '/.htaccess';
if (is_file($htaccess)) {
    $content = file_get_contents($htaccess);
    $hasPhpFlag = (bool)preg_match('/^\s*php_flag\b/mi', $content);
    $hasPhpValue = (bool)preg_match('/^\s*php_value\b/mi', $content);
    $hasRewrite = str_contains($content, 'RewriteEngine');
    
    $checks['htaccess'] = [
        'ok'     => !$hasPhpFlag && !$hasPhpValue,
        'detail' => implode(', ', array_filter([
            $hasRewrite ? 'has RewriteEngine' : 'no RewriteEngine',
            $hasPhpFlag ? '⚠️ HAS php_flag (causes 500 on shared hosting!)' : 'no php_flag',
            $hasPhpValue ? '⚠️ HAS php_value (causes 500 on shared hosting!)' : 'no php_value',
        ])),
    ];
    
    if ($hasPhpFlag || $hasPhpValue) {
        $errors[] = ".htaccess contains php_flag/php_value — this causes 500 on shared hosting with CGI/FPM";
    }
} else {
    $checks['htaccess'] = ['ok' => false, 'detail' => 'no .htaccess found'];
}

// Root .htaccess
$rootHtaccess = dirname(__DIR__) . '/.htaccess';
if (is_file($rootHtaccess)) {
    $content = file_get_contents($rootHtaccess);
    $hasPhpFlag = (bool)preg_match('/^\s*php_flag\b/mi', $content);
    $hasPhpValue = (bool)preg_match('/^\s*php_value\b/mi', $content);
    $checks['root_htaccess'] = [
        'ok'     => !$hasPhpFlag && !$hasPhpValue,
        'detail' => implode(', ', array_filter([
            $hasPhpFlag ? '⚠️ HAS php_flag' : 'no php_flag',
            $hasPhpValue ? '⚠️ HAS php_value' : 'no php_value',
        ])),
    ];
} else {
    $checks['root_htaccess'] = ['ok' => false, 'detail' => 'no root .htaccess found'];
}

// ── 8. Auth.php simulation (dry run) ────────────────────────────────────────
try {
    $authFile = __DIR__ . '/auth.php';
    $syntaxCheck = trim(shell_exec("php -l " . escapeshellarg($authFile) . " 2>&1") ?? '');
    $checks['auth_syntax'] = [
        'ok'     => str_contains($syntaxCheck, 'No syntax errors'),
        'detail' => $syntaxCheck,
    ];
} catch (Throwable $e) {
    $checks['auth_syntax'] = ['ok' => false, 'detail' => $e->getMessage()];
}

// ── 9. PHP error log path ───────────────────────────────────────────────────
$errorLog = ini_get('error_log');
$checks['error_log'] = [
    'ok'     => true,
    'detail' => $errorLog ?: '(syslog/default)',
];

// ── 10. Server info ─────────────────────────────────────────────────────────
$checks['server'] = [
    'ok'     => true,
    'detail' => ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
];
$checks['sapi'] = [
    'ok'     => true,
    'detail' => php_sapi_name(),
];
$checks['memory_limit'] = [
    'ok'     => true,
    'detail' => ini_get('memory_limit'),
];

// ── Summary ─────────────────────────────────────────────────────────────────
$failedCount = count(array_filter($checks, fn($c) => !$c['ok']));
$totalCount  = count($checks);

$result = [
    'status'       => $failedCount === 0 ? 'all_pass' : 'issues_found',
    'passed'       => $totalCount - $failedCount,
    'failed'       => $failedCount,
    'total'        => $totalCount,
    'errors'       => $errors,
    'checks'       => $checks,
    'generated_at' => date('c'),
    'tip'          => $failedCount > 0
        ? 'Fix the failed checks above. Common causes of 500: (1) php_flag in .htaccess on CGI/FPM, (2) missing DB tables, (3) session directory not writable, (4) missing PHP extensions.'
        : 'All checks passed. If 500 persists, check the PHP error log at: ' . ($errorLog ?: 'server default location'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
