<?php
declare(strict_types=1);

/**
 * bootstrap_admin_ui.php - Admin UI Bootstrap with Platform Admin Support
 */

if (!function_exists('_aui_log')) {
    function _aui_log(string $message): void
    {
        $logFile = __DIR__ . '/error_log.txt';
        @file_put_contents($logFile, '[' . date('c') . '] bootstrap_admin_ui: ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// ==============================================
// ⭐ Ensure session is started FIRST
// ==============================================
$sessionConfigPath = __DIR__ . '/shared/config/session.php';
if (file_exists($sessionConfigPath)) {
    require_once $sessionConfigPath;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'APP_SESSID') {
        session_name('APP_SESSID');
    }
    session_start();
    _aui_log('Session started: ' . session_id());
}

// ==============================================
// ⭐ Load required classes
// ==============================================
$requiredFiles = [
    __DIR__ . '/shared/core/DatabaseConnection.php',
    __DIR__ . '/shared/application/Auth/UserIdentity.php',
    __DIR__ . '/shared/application/Auth/UserIdentityResolver.php',
];

foreach ($requiredFiles as $requiredFile) {
    if (is_file($requiredFile)) {
        require_once $requiredFile;
    }
}

// ==============================================
// ⭐ Get database connection
// ==============================================
$db = $GLOBALS['ADMIN_DB'] ?? null;
if (!$db instanceof PDO && class_exists('DatabaseConnection', false)) {
    try {
        $db = DatabaseConnection::getConnection();
        $GLOBALS['ADMIN_DB'] = $db;
    } catch (Throwable $e) {
        _aui_log('DB connection failed: ' . $e->getMessage());
    }
}

// ==============================================
// ⭐ Resolve identity using UserIdentityResolver
// ==============================================
$identity = null;
$requestId = bin2hex(random_bytes(8));

if (class_exists('\Shared\Application\Auth\UserIdentityResolver', false)) {
    try {
        _aui_log('Session data before resolution: platform_admin=' . (!empty($_SESSION['platform_admin']) ? 'true' : 'false') . 
                 ', platform_role=' . ($_SESSION['platform_role'] ?? 'null') . 
                 ', user_id=' . ($_SESSION['user_id'] ?? 'null') .
                 ', session_id=' . session_id());
        
        $identity = \Shared\Application\Auth\UserIdentityResolver::resolve($db, [
            'request_id' => $requestId,
            'force' => true,
        ]);
        
        if ($identity instanceof \Shared\Application\Auth\UserIdentity) {
            _aui_log('Identity resolved: source=' . $identity->source() . 
                     ', is_authenticated=' . ($identity->isAuthenticated() ? 'true' : 'false') .
                     ', user_id=' . ($identity->id() ?? 'null') .
                     ', is_platform_admin=' . ($identity->isPlatformAdmin() ? 'true' : 'false') .
                     ', preferred_language=' . ($identity->preferredLanguage() ?? 'null'));
        } else {
            _aui_log('Identity resolution returned non-object');
        }
    } catch (Throwable $e) {
        _aui_log('Identity resolution failed: ' . $e->getMessage());
    }
}

if (!$identity instanceof \Shared\Application\Auth\UserIdentity) {
    $identity = \Shared\Application\Auth\UserIdentity::guest($requestId);
    _aui_log('Using guest identity');
}

$GLOBALS['ADMIN_IDENTITY'] = $identity;
$currentUser = $identity->isAuthenticated() ? $identity->toArray() : null;
$GLOBALS['ADMIN_USER'] = $currentUser;

// ==============================================
// ⭐ Extract platform admin data from identity
// ==============================================
$isPlatformAdminSession = $identity->isPlatformAdmin();
$platformRoleSession = $identity->platformRole();
$roles = is_array($currentUser['roles'] ?? null) ? $currentUser['roles'] : ($identity->roles() ?? []);
$permissions = is_array($currentUser['permissions'] ?? null) ? $currentUser['permissions'] : ($identity->permissions() ?? []);
$resourcePermissions = is_array($currentUser['resource_permissions'] ?? null)
    ? $currentUser['resource_permissions']
    : ($identity->resourcePermissions() ?? []);

// ⭐⭐ CRITICAL: Get preferred_language from identity (which comes from database)
$preferredLanguage = $identity->preferredLanguage();
if (empty($preferredLanguage) && !empty($currentUser['preferred_language'])) {
    $preferredLanguage = $currentUser['preferred_language'];
}
if (empty($preferredLanguage) && !empty($_SESSION['user']['preferred_language'])) {
    $preferredLanguage = $_SESSION['user']['preferred_language'];
}
if (empty($preferredLanguage)) {
    $preferredLanguage = 'en';
}

_aui_log('Final preferred_language: ' . $preferredLanguage);

$isSuperAdmin = in_array('super_admin', $roles, true)
    || (int) ($currentUser['role_id'] ?? 0) === 1
    || ($isPlatformAdminSession && $platformRoleSession === 'super_admin');

// ==============================================
// ⭐ Build ADMIN_UI array
// ==============================================
$ADMIN_UI = [
    'user' => [
        'id' => null,
        'username' => null,
        'email' => null,
        'role_id' => null,
        'roles' => [],
        'permissions' => [],
        'resource_permissions' => [],
        'is_active' => false,
        'preferred_language' => $preferredLanguage,  // ✅ dynamic from database
        'tenant_id' => null,
    ],
    'settings' => [],
    'lang' => $preferredLanguage,  // ✅ use preferred language
    'direction' => in_array(substr($preferredLanguage, 0, 2), ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'], true) ? 'rtl' : 'ltr',
    'tenant_id' => null,
    'theme' => [],
    'is_platform_admin' => $isPlatformAdminSession,
    'platform_role' => $platformRoleSession,
    'is_super_admin' => $isSuperAdmin,
    'user_type' => $isPlatformAdminSession
        ? ($platformRoleSession === 'super_admin' ? 'platform_admin' : 'platform_staff')
        : ($identity->isAuthenticated() ? ($isSuperAdmin ? 'tenant_admin' : 'tenant_user') : 'guest'),
];

if (is_array($currentUser) && !empty($currentUser['id'])) {
    $ADMIN_UI['user'] = [
        'id' => $currentUser['id'] ?? null,
        'username' => $currentUser['username'] ?? null,
        'email' => $currentUser['email'] ?? null,
        'role_id' => $currentUser['role_id'] ?? null,
        'roles' => $roles,
        'permissions' => $permissions,
        'resource_permissions' => $resourcePermissions,
        'is_active' => (bool) ($currentUser['is_active'] ?? false),
        'preferred_language' => $preferredLanguage,  // ✅ dynamic from database
        'tenant_id' => $currentUser['tenant_id'] ?? null,
    ];
    $ADMIN_UI['tenant_id'] = $ADMIN_UI['user']['tenant_id'];
    $ADMIN_UI['lang'] = $preferredLanguage;
    $ADMIN_UI['direction'] = in_array(substr($preferredLanguage, 0, 2), ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'], true) ? 'rtl' : 'ltr';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$ADMIN_UI['csrf_token'] = $_SESSION['csrf_token'];

// ==============================================
// ⭐ Sync to GLOBALS BEFORE theme loading
// ==============================================
$GLOBALS['ADMIN_UI'] = $ADMIN_UI;

// ==============================================
// ⭐ Load theme if database available
// ==============================================
if ($db instanceof PDO) {
    try {
        $themeLoader = __DIR__ . '/shared/ui/AdminUiThemeLoader.php';
        if (is_file($themeLoader)) {
            require_once $themeLoader;
            $loader = new AdminUiThemeLoader($db);
            $tenantId = $ADMIN_UI['user']['tenant_id'] ?? null;
            
            // For platform admins, if tenant_id is null, default to platform tenant (1)
            if ($tenantId === null && !empty($ADMIN_UI['is_platform_admin'])) {
                $tenantId = 1;
            }
            
            _aui_log('Theme resolution: tenant_id=' . ($tenantId ?? 'null') 
                . ', is_platform_admin=' . ($ADMIN_UI['is_platform_admin'] ? 'true' : 'false')
                . ', user_type=' . ($ADMIN_UI['user_type'] ?? 'unknown'));
            
            if ($tenantId !== null) {
                $themeData = $loader->getFullThemeData((int) $tenantId);
                _aui_log('Theme result: ' . (empty($themeData) ? 'EMPTY' : 'theme_id=' . ($themeData['theme']['id'] ?? 'none')));
                $ADMIN_UI['theme'] = $themeData;
                $GLOBALS['ADMIN_UI']['theme'] = $themeData;
            } else {
                _aui_log('Theme skipped: tenant_id is null');
            }
        }
    } catch (Throwable $e) {
        _aui_log('Theme load failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    }
}

// Final sync
$GLOBALS['ADMIN_UI'] = $ADMIN_UI;

// ==============================================
// ⭐ Debug output
// ==============================================
if (!empty($_GET['__admin_ui_debug']) && $_GET['__admin_ui_debug'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    
    $identityDebug = $_SESSION['identity_debug'] ?? null;
    
    if (!$identityDebug && $identity->isAuthenticated()) {
        $identityDebug = [
            'resolved_user_id' => $identity->id(),
            'resolved_tenant_id' => $identity->tenantId(),
            'identity_source' => $identity->source(),
            'source' => $identity->source(),
            'is_platform_admin' => $identity->isPlatformAdmin(),
            'platform_role' => $identity->platformRole(),
            'preferred_language' => $identity->preferredLanguage(),
            'session_id' => session_id(),
            'request_id' => $identity->requestId(),
        ];
    }
    
    // ── Theme Debug: run direct queries to see what the DB returns ──
    $themeDebug = ['error' => null, 'steps' => []];
    if ($db instanceof PDO) {
        try {
            $tid = (int)($ADMIN_UI['user']['tenant_id'] ?? 0);
            $themeDebug['tenant_id_used'] = $tid;
            $themeDebug['is_platform_admin'] = !empty($ADMIN_UI['is_platform_admin']);
            $themeDebug['globals_is_platform_admin'] = !empty($GLOBALS['ADMIN_UI']['is_platform_admin']);
            
            // Step 1: Check PdoThemesRepository class
            $themeDebug['steps']['class_loaded'] = class_exists('PdoThemesRepository', false);
            
            // Step 2: Direct query - tenant_theme_overrides
            $s = $db->prepare("SELECT id, tenant_id, theme_id, setting_type, setting_key, created_at FROM tenant_theme_overrides WHERE tenant_id = ? AND setting_type = 'theme_selection' ORDER BY id DESC");
            $s->execute([$tid]);
            $themeDebug['steps']['tenant_overrides'] = $s->fetchAll(PDO::FETCH_ASSOC);
            
            // Step 3: Direct query - platform theme
            $s = $db->prepare("SELECT id, name, slug, theme_scope, theme_target, tenant_id, is_active, is_default FROM themes WHERE tenant_id = 1 AND theme_scope = 'platform' AND theme_target = 'platform_admin' AND is_active = 1 LIMIT 1");
            $s->execute();
            $themeDebug['steps']['platform_theme'] = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            
            // Step 4: All themes summary
            $s = $db->query("SELECT id, name, theme_scope, theme_target, tenant_id, is_active, is_default FROM themes");
            $themeDebug['steps']['all_themes'] = $s->fetchAll(PDO::FETCH_ASSOC);
            
            // Step 5: Try AdminUiThemeLoader directly
            if (class_exists('AdminUiThemeLoader', false)) {
                try {
                    $testLoader = new AdminUiThemeLoader($db);
                    $testResult = $testLoader->getFullThemeData($tid);
                    $themeDebug['steps']['loader_result_keys'] = array_keys($testResult);
                    $themeDebug['steps']['loader_theme_id'] = $testResult['theme']['id'] ?? 'NONE';
                } catch (Throwable $le) {
                    $themeDebug['steps']['loader_error'] = $le->getMessage() . ' at ' . $le->getFile() . ':' . $le->getLine();
                }
            } else {
                $themeDebug['steps']['loader_error'] = 'AdminUiThemeLoader class not loaded';
            }
            
        } catch (Throwable $e) {
            $themeDebug['error'] = $e->getMessage();
        }
    } else {
        $themeDebug['error'] = 'No DB connection';
    }
    
    echo json_encode([
        'ok' => true,
        'db_connected' => (bool) $db,
        'db_type' => $db instanceof PDO ? 'pdo' : null,
        'admin_ui' => $ADMIN_UI,
        'identity' => $identityDebug,
        'theme_debug' => $themeDebug,
        'session' => [
            'id' => session_id(),
            'name' => session_name(),
            'cookie_received' => $_COOKIE[session_name()] ?? null,
            'keys' => array_keys($_SESSION),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

return;