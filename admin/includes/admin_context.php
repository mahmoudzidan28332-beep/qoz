<?php
declare(strict_types=1);

/**
 * /admin/includes/admin_context.php
 * Global Admin Context - Production Ready v2.0
 * 
 * Supports:
 * - role_permissions (traditional permission system)
 * - resource_permissions (granular resource-level permissions)
 * - Multi-tenant architecture
 * - Session-based authentication
 * - Database-driven roles and permissions
 * - Platform Admin support (cross-tenant super admin)
 * 
 * @version 2.0.0
 */

// ════════════════════════════════════════════════════════════
// PREVENT MULTIPLE INCLUSIONS
// ════════════════════════════════════════════════════════════
if (defined('ADMIN_CONTEXT_LOADED')) {
    return;
}
define('ADMIN_CONTEXT_LOADED', true);

// ════════════════════════════════════════════════════════════
// INITIALIZE ADMIN_UI FROM SESSION (ONCE)
// ════════════════════════════════════════════════════════════
if (!isset($GLOBALS['ADMIN_UI'])) {
    $sharedBootstrap = $_SERVER['DOCUMENT_ROOT'] . '/api/bootstrap_admin_ui.php';
    if (file_exists($sharedBootstrap)) {
        require_once $sharedBootstrap;
    }
}

// If not already initialized, perform standard initialization
if (!isset($GLOBALS['ADMIN_UI'])) {


// ────────────────────────────────────────────────────────────
// LOAD DATABASE CONNECTION
// ────────────────────────────────────────────────────────────
if (!isset($GLOBALS['ADMIN_DB'])) {
    $dbConfigPath = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/db.php';
    if (file_exists($dbConfigPath)) {
        require_once $dbConfigPath;
        
        try {
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                $GLOBALS['ADMIN_DB'] = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            }
        } catch (PDOException $e) {
            error_log('[admin_context] Database connection failed: ' . $e->getMessage());
        }
    }
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;

// ────────────────────────────────────────────────────────────
// GET CURRENT USER FROM SESSION
// ────────────────────────────────────────────────────────────
$currentUser = $_SESSION['user'] ?? null;
$userId = 0;
$tenantId = 0;

// Platform admin detection (set by api/v1/routes/platform_auth.php)
$isPlatformAdminSession = !empty($_SESSION['platform_admin']);
$platformRoleSession    = $isPlatformAdminSession ? (string)($_SESSION['platform_role'] ?? '') : null;

// Extract user ID and tenant ID from session or global identity
if (!empty($currentUser) && is_array($currentUser)) {
    $userId = (int)($currentUser['id'] ?? 0);
} elseif (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
} elseif (isset($GLOBALS['ADMIN_IDENTITY']) && $GLOBALS['ADMIN_IDENTITY']->isAuthenticated()) {
    $userId = $GLOBALS['ADMIN_IDENTITY']->id();
}

// Get tenant ID from session or global identity
if (isset($_SESSION['tenant_id'])) {
    $tenantId = (int)$_SESSION['tenant_id'];
} elseif (!empty($currentUser['tenant_id'])) {
    $tenantId = (int)$currentUser['tenant_id'];
} elseif (isset($GLOBALS['ADMIN_IDENTITY']) && $GLOBALS['ADMIN_IDENTITY']->isAuthenticated()) {
    $tenantId = $GLOBALS['ADMIN_IDENTITY']->tenantId();
}

// For platform admin, ensure tenant_id is set
if ($isPlatformAdminSession && $tenantId === 0) {
    $tenantId = 1;
}

// ════════════════════════════════════════════════════════════
// LOAD USER DATA, ROLES, AND PERMISSIONS FROM DATABASE
// ════════════════════════════════════════════════════════════
$dbUser = null;
$tenantUser = null;
$userRoles = [];
$userPermissions = [];
$userResourcePermissions = [];

// Check if we need to reload from database
$needsReload = !$isPlatformAdminSession && $userId > 0 && $pdo instanceof PDO && (
    empty($_SESSION['roles']) || empty($_SESSION['permissions'])
);

if ($needsReload) {
    error_log('[admin_context] Loading user data from database for user: ' . $userId);
    
    try {
        // 1. GET USER DATA
        $stmt = $pdo->prepare("SELECT id, username, email, preferred_language, phone, timezone, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch();
        
        if (!$dbUser) {
            error_log('[admin_context] User not found: ' . $userId);
            $userId = 0;
        } else {
            // 2. GET TENANT MEMBERSHIP AND ROLE
            $stmt = $pdo->prepare("
                SELECT tu.*, r.key_name as role_key_name
                FROM tenant_users tu
                LEFT JOIN roles r ON r.id = tu.role_id
                WHERE tu.user_id = ? 
                AND tu.tenant_id = ?
                AND tu.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$userId, $tenantId]);
            $tenantUser = $stmt->fetch();
            
            if (!$tenantUser) {
                error_log('[admin_context] User not tenant member: User=' . $userId . ', Tenant=' . $tenantId);
                $tenantUser = ['role_id' => null, 'role_key_name' => null];
            }
            
            $roleId = $tenantUser['role_id'] ?? null;
            $roleKeyName = $tenantUser['role_key_name'] ?? null;
            
            // 3. INITIALIZE PERMISSION ARRAYS
            if ($roleKeyName) {
                $userRoles = [$roleKeyName];
                $isSuperAdmin = ($roleKeyName === 'super_admin');
                
                // 4. LOAD ROLE-BASED PERMISSIONS
                if ($isSuperAdmin) {
                    $stmt = $pdo->prepare("SELECT DISTINCT key_name FROM permissions WHERE tenant_id = ? ORDER BY key_name");
                    $stmt->execute([$tenantId]);
                    $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($roleId) {
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT p.key_name
                        FROM permissions p
                        INNER JOIN role_permissions rp ON rp.permission_id = p.id
                        WHERE rp.role_id = ? AND rp.tenant_id = ?
                        ORDER BY p.key_name
                    ");
                    $stmt->execute([$roleId, $tenantId]);
                    $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                // 5. LOAD RESOURCE PERMISSIONS
                if ($roleId) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            rp.resource_type,
                            rp.can_view_all,
                            rp.can_view_own,
                            rp.can_view_tenant,
                            rp.can_create,
                            rp.can_edit_all,
                            rp.can_edit_own,
                            rp.can_delete_all,
                            rp.can_delete_own,
                            p.key_name as permission_key
                        FROM resource_permissions rp
                        LEFT JOIN permissions p ON p.id = rp.permission_id
                        WHERE rp.role_id = ? AND rp.tenant_id = ?
                        ORDER BY rp.resource_type
                    ");
                    $stmt->execute([$roleId, $tenantId]);
                    $resourcePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($resourcePerms as $perm) {
                        $resourceType = $perm['resource_type'];
                        $userResourcePermissions[$resourceType] = [
                            'can_view_all' => (bool)$perm['can_view_all'],
                            'can_view_own' => (bool)$perm['can_view_own'],
                            'can_view_tenant' => (bool)$perm['can_view_tenant'],
                            'can_create' => (bool)$perm['can_create'],
                            'can_edit_all' => (bool)$perm['can_edit_all'],
                            'can_edit_own' => (bool)$perm['can_edit_own'],
                            'can_delete_all' => (bool)$perm['can_delete_all'],
                            'can_delete_own' => (bool)$perm['can_delete_own'],
                            'permission_key' => $perm['permission_key'],
                        ];
                    }
                    
                    if ($isSuperAdmin && empty($userResourcePermissions)) {
                        $stmt = $pdo->prepare("SELECT DISTINCT resource_type FROM resource_permissions WHERE tenant_id = ?");
                        $stmt->execute([$tenantId]);
                        $resourceTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($resourceTypes as $resourceType) {
                            $userResourcePermissions[$resourceType] = [
                                'can_view_all' => true,
                                'can_view_own' => true,
                                'can_view_tenant' => true,
                                'can_create' => true,
                                'can_edit_all' => true,
                                'can_edit_own' => true,
                                'can_delete_all' => true,
                                'can_delete_own' => true,
                                'permission_key' => 'super_admin',
                            ];
                        }
                    }
                }
            }
            
            // 6. STORE IN SESSION
            $_SESSION['roles'] = $userRoles;
            $_SESSION['permissions'] = $userPermissions;
            $_SESSION['resource_permissions'] = $userResourcePermissions;
            $_SESSION['tenant_id'] = $tenantId;
            $_SESSION['user_id'] = $userId;
            $_SESSION['user'] = [
                'id' => $dbUser['id'],
                'username' => $dbUser['username'],
                'email' => $dbUser['email'],
                'preferred_language' => $dbUser['preferred_language'] ?? 'en',
                'phone' => $dbUser['phone'] ?? null,
                'timezone' => $dbUser['timezone'] ?? 'UTC',
                'is_active' => (bool)($dbUser['is_active'] ?? true),
            ];
            
            $currentUser = $_SESSION['user'];
            
            error_log('[admin_context] Loaded: ' . count($userRoles) . ' roles, ' 
                . count($userPermissions) . ' permissions, ' 
                . count($userResourcePermissions) . ' resource permissions');
        }
    } catch (\RuntimeException $e) {
        error_log('[admin_context] Error loading permissions: ' . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════
// BUILD USER ARRAY
// ════════════════════════════════════════════════════════════
if ($isPlatformAdminSession) {
    // Platform admin - use session data
    $sessionUser = $_SESSION['user'] ?? [];
    
    $finalUser = [
        'id' => (int)($sessionUser['id'] ?? $_SESSION['user_id'] ?? $userId),
        'username' => $sessionUser['username'] ?? 'platform_admin',
        'email' => $sessionUser['email'] ?? '',
        'phone' => $sessionUser['phone'] ?? null,
        'role_id' => null,
        'tenant_id' => $tenantId,
        'preferred_language' => $sessionUser['preferred_language'] ?? 'en',
        'timezone' => $sessionUser['timezone'] ?? 'UTC',
        'is_active' => true,
        'permissions' => [],
        'roles' => [$platformRoleSession, 'super_admin'],
        'resource_permissions' => [],
        'permissions_count' => 0,
        'roles_count' => 2,
        'is_authenticated' => true,
        'identity_source' => 'platform_session',
        'tenant_user_id' => null,
        'entity_id' => null,
    ];
    
    // Update session
    $_SESSION['user_id'] = $finalUser['id'];
    $_SESSION['user'] = $finalUser;
    $_SESSION['roles'] = $finalUser['roles'];
    $_SESSION['permissions'] = [];
    $_SESSION['tenant_id'] = $finalUser['tenant_id'];
    
} elseif ($userId > 0) {
    // Normal authenticated user
    if ($dbUser) {
        $finalUser = [
            'id' => $userId,
            'username' => $dbUser['username'],
            'email' => $dbUser['email'],
            'phone' => $dbUser['phone'] ?? null,
            'role_id' => $tenantUser['role_id'] ?? null,
            'tenant_id' => $tenantId,
            'preferred_language' => $dbUser['preferred_language'] ?? 'en',
            'timezone' => $dbUser['timezone'] ?? 'UTC',
            'is_active' => (bool)($dbUser['is_active'] ?? true),
            'permissions' => $userPermissions,
            'roles' => $userRoles,
            'resource_permissions' => $userResourcePermissions,
            'permissions_count' => count($userPermissions),
            'roles_count' => count($userRoles),
            'is_authenticated' => true,
            'identity_source' => 'database',
            'tenant_user_id' => $tenantUser['id'] ?? null,
            'entity_id' => $tenantUser['entity_id'] ?? null,
        ];
    } else {
        // Use existing session data if available
        $finalUser = $_SESSION['user'] ?? [];
        $finalUser['is_authenticated'] = true;
        $finalUser['id'] = $userId;
    }
    
    // Update session
    $_SESSION['user'] = $finalUser;
    $_SESSION['roles'] = $finalUser['roles'] ?? [];
    $_SESSION['permissions'] = $finalUser['permissions'] ?? [];
    
} else {
    // Guest user
    $finalUser = [
        'id' => 0,
        'username' => 'guest',
        'email' => '',
        'phone' => null,
        'role_id' => null,
        'tenant_id' => 0,
        'preferred_language' => 'en',
        'timezone' => 'UTC',
        'is_active' => false,
        'permissions' => [],
        'roles' => [],
        'resource_permissions' => [],
        'permissions_count' => 0,
        'roles_count' => 0,
        'is_authenticated' => false,
        'identity_source' => 'guest',
        'tenant_user_id' => null,
        'entity_id' => null,
    ];
}

// ════════════════════════════════════════════════════════════
// DETERMINE USER TYPE
// ════════════════════════════════════════════════════════════
$hasUser = $finalUser['id'] > 0 && $finalUser['is_authenticated'];

if (!$hasUser) {
    $_userType = 'guest';
} elseif ($isPlatformAdminSession && $platformRoleSession === 'super_admin') {
    $_userType = 'platform_admin';
} elseif ($isPlatformAdminSession) {
    $_userType = 'platform_staff';
} elseif (in_array('super_admin', $finalUser['roles'] ?? [], true)) {
    $_userType = 'tenant_admin';
} else {
    $_userType = 'tenant_user';
}

$isSuperAdminFlag = in_array('super_admin', $finalUser['roles'] ?? [], true)
    || ($isPlatformAdminSession && $platformRoleSession === 'super_admin');

// ════════════════════════════════════════════════════════════
// GENERATE CSRF TOKEN
// ════════════════════════════════════════════════════════════
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (\RuntimeException $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// ════════════════════════════════════════════════════════════
// BUILD ADMIN_UI CONTEXT
// ════════════════════════════════════════════════════════════
$GLOBALS['ADMIN_USER'] = $finalUser;
$GLOBALS['ADMIN_DB'] = $pdo;
$GLOBALS['ADMIN_UI'] = [
    'user' => $finalUser,
    'lang' => $finalUser['preferred_language'] ?? 'en',
    'direction' => in_array($finalUser['preferred_language'] ?? 'en', ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr',
    'csrf_token' => $_SESSION['csrf_token'],
    'tenant_id' => $finalUser['tenant_id'] ?? 0,
    'is_super_admin' => $isSuperAdminFlag,
    'is_platform_admin' => $isPlatformAdminSession,
    'platform_role' => $platformRoleSession,
    'user_type' => $_userType,
    'theme' => [
        'color_settings' => [],
        'font_settings' => [],
        'design_settings' => [],
        'button_styles' => [],
        'card_styles' => [],
        'generated_css' => '',
    ],
    'strings' => [],
    'settings' => [],
    'translation_path' => '/languages/admin/',
];

// ════════════════════════════════════════════════════════════
// LOAD THEME FROM DATABASE
// ════════════════════════════════════════════════════════════
$themeLoaderPath = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/ui/AdminUiThemeLoader.php';
if (!class_exists('AdminUiThemeLoader') && file_exists($themeLoaderPath)) {
    require_once $themeLoaderPath;
}

if ($pdo instanceof PDO && $finalUser['tenant_id'] > 0) {
    try {
        if (class_exists('AdminUiThemeLoader')) {
            $loader = new AdminUiThemeLoader($pdo);
            $fullThemeData = $loader->getFullThemeData((int)$finalUser['tenant_id']);
            
            if (!empty($fullThemeData['theme'])) {
                $GLOBALS['ADMIN_UI']['theme']['color_settings'] = $fullThemeData['color_settings'] ?? [];
                $GLOBALS['ADMIN_UI']['theme']['font_settings'] = $fullThemeData['font_settings'] ?? [];
                $GLOBALS['ADMIN_UI']['theme']['design_settings'] = $fullThemeData['design_settings'] ?? [];
                $GLOBALS['ADMIN_UI']['theme']['button_styles'] = $fullThemeData['button_styles'] ?? [];
                $GLOBALS['ADMIN_UI']['theme']['card_styles'] = $fullThemeData['card_styles'] ?? [];
                $GLOBALS['ADMIN_UI']['theme']['generated_css'] = $fullThemeData['generated_css'] ?? '';
            }
        } else {
            // Fallback for systems without the theme loader
            $stmt = $pdo->prepare("SELECT id, generated_css FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$finalUser['tenant_id']]);
            $theme = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($theme) {
                $themeId = $theme['id'];
                
                // Load color settings
                $stmt = $pdo->prepare("SELECT id, theme_id, setting_key, setting_name, color_value, category, is_active, sort_order FROM color_settings WHERE tenant_id = ? AND theme_id = ? AND is_active = 1 ORDER BY category, sort_order");
                $stmt->execute([$finalUser['tenant_id'], $themeId]);
                $GLOBALS['ADMIN_UI']['theme']['color_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Load font settings
                $stmt = $pdo->prepare("SELECT id, theme_id, setting_key, setting_name, font_family, font_size, font_weight, line_height, category, is_active, sort_order FROM font_settings WHERE tenant_id = ? AND theme_id = ? AND is_active = 1 ORDER BY category, sort_order");
                $stmt->execute([$finalUser['tenant_id'], $themeId]);
                $GLOBALS['ADMIN_UI']['theme']['font_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Load design settings
                $stmt = $pdo->prepare("SELECT id, theme_id, setting_key, setting_name, setting_value, setting_type, category, is_active, sort_order FROM design_settings WHERE tenant_id = ? AND theme_id = ? AND is_active = 1 ORDER BY category, sort_order");
                $stmt->execute([$finalUser['tenant_id'], $themeId]);
                $GLOBALS['ADMIN_UI']['theme']['design_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Load button styles
                $stmt = $pdo->prepare("SELECT id, tenant_id, theme_id, name, slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color, is_active FROM button_styles WHERE tenant_id = ? AND theme_id = ? AND is_active = 1 ORDER BY button_type, name");
                $stmt->execute([$finalUser['tenant_id'], $themeId]);
                $GLOBALS['ADMIN_UI']['theme']['button_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Load card styles
                $stmt = $pdo->prepare("SELECT id, tenant_id, theme_id, name, slug, card_type, background_color, text_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active FROM card_styles WHERE tenant_id = ? AND (theme_id = ? OR theme_id IS NULL) AND is_active = 1 ORDER BY card_type, name");
                $stmt->execute([$finalUser['tenant_id'], $themeId]);
                $GLOBALS['ADMIN_UI']['theme']['card_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $GLOBALS['ADMIN_UI']['theme']['generated_css'] = $theme['generated_css'] ?? '';
            }
        }
    } catch (\RuntimeException $e) {
        error_log('[admin_context] Theme load error: ' . $e->getMessage());
    }
}

} else {
    // If already initialized, ensure local variables are available for legacy scripts
    $pdo = $GLOBALS['ADMIN_DB'] ?? null;
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - GENERAL
// ════════════════════════════════════════════════════════════

function admin_context(): array {
    return $GLOBALS['ADMIN_UI'] ?? [];
}

if (!function_exists('admin_user')) {
    function admin_user(): array {
        return $GLOBALS['ADMIN_UI']['user'] ?? [];
    }
}

if (!function_exists('admin_user_id')) {
    function admin_user_id(): int {
        return (int)(admin_user()['id'] ?? 0);
    }
}

if (!function_exists('admin_username')) {
    function admin_username(): string {
        return admin_user()['username'] ?? 'guest';
    }
}

if (!function_exists('admin_email')) {
    function admin_email(): string {
        return admin_user()['email'] ?? '';
    }
}

if (!function_exists('is_admin_logged_in')) {
    function is_admin_logged_in(): bool {
        return admin_user_id() > 0;
    }
}

if (!function_exists('admin_lang')) {
    function admin_lang(): string {
        return $GLOBALS['ADMIN_UI']['lang'] ?? 'en';
    }
}

if (!function_exists('admin_dir')) {
    function admin_dir(): string {
        return $GLOBALS['ADMIN_UI']['direction'] ?? 'ltr';
    }
}

if (!function_exists('admin_csrf')) {
    function admin_csrf(): string {
        return $GLOBALS['ADMIN_UI']['csrf_token'] ?? '';
    }
}

if (!function_exists('admin_theme')) {
    function admin_theme(): array {
        return $GLOBALS['ADMIN_UI']['theme'] ?? [];
    }
}

if (!function_exists('admin_strings')) {
    function admin_strings(): array {
        return $GLOBALS['ADMIN_UI']['strings'] ?? [];
    }
}

if (!function_exists('admin_tenant_id')) {
    function admin_tenant_id(): int {
        return (int)($GLOBALS['ADMIN_UI']['tenant_id'] ?? 0);
    }
}

if (!function_exists('admin_db')) {
    function admin_db(): ?PDO {
        return $GLOBALS['ADMIN_DB'] ?? null;
    }
}

function assetVer(string $path = ''): string {
    static $cache = [];
    if ($path === '') {
        static $fallback = null;
        if ($fallback === null) {
            $fallback = (string)time();
        }
        return $fallback;
    }
    if (!isset($cache[$path])) {
        $full = $_SERVER['DOCUMENT_ROOT'] . $path;
        $cache[$path] = file_exists($full) ? (string)filemtime($full) : '0';
    }
    return $cache[$path];
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - ROLE-BASED PERMISSIONS
// ════════════════════════════════════════════════════════════

if (!function_exists('admin_roles')) {
    function admin_roles(): array {
        return admin_user()['roles'] ?? [];
    }
}

if (!function_exists('admin_permissions')) {
    function admin_permissions(): array {
        return admin_user()['permissions'] ?? [];
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool {
        if (is_super_admin()) {
            return true;
        }
        return in_array($permission, admin_permissions(), true);
    }
}

if (!function_exists('has_role')) {
    function has_role(string $role): bool {
        return in_array($role, admin_roles(), true);
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin(): bool {
        return $GLOBALS['ADMIN_UI']['is_super_admin'] ?? false;
    }
}

if (!function_exists('is_platform_admin')) {
    function is_platform_admin(): bool {
        return (bool)($GLOBALS['ADMIN_UI']['is_platform_admin'] ?? false);
    }
}

if (!function_exists('get_platform_role')) {
    function get_platform_role(): ?string {
        return $GLOBALS['ADMIN_UI']['platform_role'] ?? null;
    }
}

if (!function_exists('get_user_type')) {
    function get_user_type(): string {
        return $GLOBALS['ADMIN_UI']['user_type'] ?? 'guest';
    }
}

if (!function_exists('can_any')) {
    function can_any(array $permissions): bool {
        if (is_super_admin()) {
            return true;
        }
        $userPermissions = admin_permissions();
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('can_all')) {
    function can_all(array $permissions): bool {
        if (is_super_admin()) {
            return true;
        }
        $userPermissions = admin_permissions();
        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                return false;
            }
        }
        return true;
    }
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - RESOURCE PERMISSIONS
// ════════════════════════════════════════════════════════════

if (!function_exists('admin_resource_permissions')) {
    function admin_resource_permissions(): array {
        return admin_user()['resource_permissions'] ?? [];
    }
}

if (!function_exists('get_resource_permissions')) {
    function get_resource_permissions(string $resourceType): ?array {
        $resourcePerms = admin_resource_permissions();
        return $resourcePerms[$resourceType] ?? null;
    }
}

if (!function_exists('can_view_all')) {
    function can_view_all(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_view_all'] ?? false;
    }
}

if (!function_exists('can_view_own')) {
    function can_view_own(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_view_own'] ?? false;
    }
}

if (!function_exists('can_view_tenant')) {
    function can_view_tenant(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_view_tenant'] ?? false;
    }
}

if (!function_exists('can_create')) {
    function can_create(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_create'] ?? false;
    }
}

if (!function_exists('can_edit_all')) {
    function can_edit_all(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_edit_all'] ?? false;
    }
}

if (!function_exists('can_edit_own')) {
    function can_edit_own(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_edit_own'] ?? false;
    }
}

if (!function_exists('can_delete_all')) {
    function can_delete_all(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_delete_all'] ?? false;
    }
}

if (!function_exists('can_delete_own')) {
    function can_delete_own(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        $perms = get_resource_permissions($resourceType);
        return $perms['can_delete_own'] ?? false;
    }
}

if (!function_exists('can_view_resource')) {
    function can_view_resource(string $resourceType, int $ownerId, ?int $resourceTenantId = null): bool {
        if (is_super_admin()) {
            return true;
        }
        
        $currentUserId = admin_user_id();
        $currentTenantId = admin_tenant_id();
        
        if (can_view_all($resourceType)) {
            return true;
        }
        
        if (can_view_own($resourceType) && $ownerId === $currentUserId) {
            return true;
        }
        
        if (can_view_tenant($resourceType) && $resourceTenantId === $currentTenantId) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('can_edit_resource')) {
    function can_edit_resource(string $resourceType, int $ownerId): bool {
        if (is_super_admin()) {
            return true;
        }
        
        $currentUserId = admin_user_id();
        
        if (can_edit_all($resourceType)) {
            return true;
        }
        
        if (can_edit_own($resourceType) && $ownerId === $currentUserId) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('can_delete_resource')) {
    function can_delete_resource(string $resourceType, int $ownerId): bool {
        if (is_super_admin()) {
            return true;
        }
        
        $currentUserId = admin_user_id();
        
        if (can_delete_all($resourceType)) {
            return true;
        }
        
        if (can_delete_own($resourceType) && $ownerId === $currentUserId) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('has_any_resource_permission')) {
    function has_any_resource_permission(string $resourceType): bool {
        if (is_super_admin()) {
            return true;
        }
        
        $perms = get_resource_permissions($resourceType);
        if (!$perms) {
            return false;
        }
        
        return $perms['can_view_all'] 
            || $perms['can_view_own'] 
            || $perms['can_view_tenant']
            || $perms['can_create']
            || $perms['can_edit_all']
            || $perms['can_edit_own']
            || $perms['can_delete_all']
            || $perms['can_delete_own'];
    }
}

// ════════════════════════════════════════════════════════════
// AUTHORIZATION HELPERS
// ════════════════════════════════════════════════════════════

if (!function_exists('require_login')) {
    function require_login(string $redirectUrl = '/admin/login.php'): void {
        if (!is_admin_logged_in()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $permission, string $message = 'Access denied'): void {
        if (!can($permission)) {
            http_response_code(403);
            die($message);
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(string $role, string $message = 'Access denied'): void {
        if (!has_role($role)) {
            http_response_code(403);
            die($message);
        }
    }
}

if (!function_exists('require_resource_permission')) {
    function require_resource_permission(string $resourceType, string $action, string $message = 'Access denied'): void {
        $hasPermission = false;
        
        switch ($action) {
            case 'view_all':
                $hasPermission = can_view_all($resourceType);
                break;
            case 'view_own':
                $hasPermission = can_view_own($resourceType);
                break;
            case 'view_tenant':
                $hasPermission = can_view_tenant($resourceType);
                break;
            case 'create':
                $hasPermission = can_create($resourceType);
                break;
            case 'edit_all':
                $hasPermission = can_edit_all($resourceType);
                break;
            case 'edit_own':
                $hasPermission = can_edit_own($resourceType);
                break;
            case 'delete_all':
                $hasPermission = can_delete_all($resourceType);
                break;
            case 'delete_own':
                $hasPermission = can_delete_own($resourceType);
                break;
        }
        
        if (!$hasPermission) {
            http_response_code(403);
            die($message);
        }
    }
}

// ════════════════════════════════════════════════════════════
// LOG INITIALIZATION
// ════════════════════════════════════════════════════════════
error_log('[admin_context] ════════════════════════════════════════════');
error_log('[admin_context] Initialized for user: ' . admin_username() . ' (ID: ' . admin_user_id() . ')');
error_log('[admin_context] Tenant: ' . admin_tenant_id());
error_log('[admin_context] User type: ' . get_user_type());
error_log('[admin_context] Roles: ' . (empty(admin_roles()) ? 'none' : implode(', ', admin_roles())));
error_log('[admin_context] Permissions: ' . count(admin_permissions()));
error_log('[admin_context] Resource Permissions: ' . count(admin_resource_permissions()));
error_log('[admin_context] Super Admin: ' . (is_super_admin() ? 'YES' : 'NO'));
error_log('[admin_context] Platform Admin: ' . (is_platform_admin() ? 'YES (role: ' . (get_platform_role() ?? 'n/a') . ')' : 'NO'));
error_log('[admin_context] ════════════════════════════════════════════');