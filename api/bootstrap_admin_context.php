<?php
declare(strict_types=1);

$requiredFiles = [
    __DIR__ . '/shared/config/session.php',
    __DIR__ . '/shared/core/DatabaseConnection.php',
    __DIR__ . '/shared/core/CacheManager.php',
    __DIR__ . '/shared/helpers/RedisHelper.php',
    __DIR__ . '/shared/helpers/RBAC.php',
    __DIR__ . '/shared/helpers/jwt.php',
    __DIR__ . '/shared/application/Auth/UserIdentity.php',
    __DIR__ . '/shared/application/Auth/UserIdentityResolver.php',
];

foreach ($requiredFiles as $requiredFile) {
    if (is_file($requiredFile)) {
        require_once $requiredFile;
    }
}

$pdo = $GLOBALS['ADMIN_DB'] ?? ($GLOBALS['CONTAINER']['pdo'] ?? null);
if (!$pdo instanceof PDO && class_exists('DatabaseConnection', false)) {
    try {
        $pdo = DatabaseConnection::getConnection();
    } catch (Throwable $e) {
        error_log('bootstrap_admin_context: DB connection failed: ' . $e->getMessage());
        $pdo = null;
    }
}

$requestId = defined('REQUEST_ID') ? REQUEST_ID : bin2hex(random_bytes(8));
$identity = null;

// ==============================================
// ⭐ CHECK FOR PLATFORM ADMIN FIRST
// ==============================================
$isPlatformAdmin = !empty($_SESSION['platform_admin']);
$platformRole = $isPlatformAdmin ? ($_SESSION['platform_role'] ?? null) : null;

error_log('[bootstrap_admin_context] Platform admin check: ' . ($isPlatformAdmin ? 'YES (role: ' . $platformRole . ')' : 'NO'));

// If not platform admin, try normal resolution
if (!$isPlatformAdmin && class_exists('\Shared\Application\Auth\UserIdentityResolver', false)) {
    try {
        $identity = \Shared\Application\Auth\UserIdentityResolver::resolve($pdo, [
            'request_id' => $requestId,
            'force' => true,
        ]);
        error_log('[bootstrap_admin_context] Normal identity resolution: ' . ($identity->isAuthenticated() ? 'authenticated' : 'guest'));
    } catch (Throwable $e) {
        error_log('bootstrap_admin_context: identity resolution failed: ' . $e->getMessage());
    }
}

// If still no identity, create guest
if (!$identity instanceof \Shared\Application\Auth\UserIdentity) {
    $identity = \Shared\Application\Auth\UserIdentity::guest($requestId);
    error_log('[bootstrap_admin_context] Using guest identity');
}

$currentUser = $identity->isAuthenticated() ? $identity->toArray() : null;

// ==============================================
// ⭐ OVERRIDE WITH PLATFORM ADMIN DATA IF APPLICABLE
// ==============================================
if ($isPlatformAdmin) {
    // Override with platform admin data from session
    $sessionUser = $_SESSION['user'] ?? [];
    $currentUser = [
        'id' => $_SESSION['user_id'] ?? ($sessionUser['id'] ?? 0),
        'username' => $sessionUser['username'] ?? 'platform_admin',
        'email' => $sessionUser['email'] ?? '',
        'tenant_id' => $_SESSION['tenant_id'] ?? 1,
        'roles' => [$platformRole, 'super_admin'],
        'permissions' => [],
        'resource_permissions' => [],
        'is_active' => true,
        'is_authenticated' => true,
        'identity_source' => 'platform_session',
        'is_platform_admin' => true,
        'platform_role' => $platformRole,
        'user_type' => $platformRole === 'super_admin' ? 'platform_admin' : 'platform_staff',
    ];
    
    error_log('[bootstrap_admin_context] Platform admin override applied for user: ' . $currentUser['username']);
}

$GLOBALS['ADMIN_DB'] = $pdo;
$GLOBALS['ADMIN_USER'] = $currentUser;
$GLOBALS['ADMIN_IDENTITY'] = $identity;

// ==============================================
// ⭐ GLOBAL FUNCTIONS WITH PLATFORM ADMIN SUPPORT
// ==============================================

function is_platform_admin(): bool
{
    global $currentUser;
    return ($currentUser['is_platform_admin'] ?? false) || !empty($_SESSION['platform_admin']);
}

function get_platform_role(): ?string
{
    global $currentUser;
    return $currentUser['platform_role'] ?? $_SESSION['platform_role'] ?? null;
}

function is_logged_in(): bool
{
    global $currentUser;
    // Check both normal auth and platform admin
    return !empty($currentUser['id']) || !empty($_SESSION['platform_admin']);
}

function is_user_active(): bool
{
    global $currentUser;
    return !empty($currentUser['is_active']);
}

function current_user_id(): ?int
{
    global $currentUser;
    return isset($currentUser['id']) ? (int) $currentUser['id'] : null;
}

function current_username(): ?string
{
    global $currentUser;
    return isset($currentUser['username']) ? (string) $currentUser['username'] : null;
}

function current_user_role(): ?int
{
    global $currentUser;
    return isset($currentUser['role_id']) ? (int) $currentUser['role_id'] : null;
}

function has_permission(string $permission): bool
{
    global $currentUser;
    
    // Platform admin (super_admin) has all permissions
    if (is_platform_admin() && get_platform_role() === 'super_admin') {
        return true;
    }
    
    // Check if user is super admin via roles
    $roles = $currentUser['roles'] ?? [];
    if (in_array('super_admin', is_array($roles) ? $roles : [], true)) {
        return true;
    }

    $permissions = $currentUser['permissions'] ?? [];
    return in_array($permission, is_array($permissions) ? $permissions : [], true);
}

function require_login(string $redirectTo = '/admin/login.php'): void
{
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . $redirectTo);
        exit;
    }
}

function require_permission(string $permission, string $redirectTo = '/admin/403.php'): void
{
    require_login();
    if (!has_permission($permission)) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

function get_pdo(): ?PDO
{
    return $GLOBALS['ADMIN_DB'] ?? null;
}

function get_user_permissions(): array
{
    global $currentUser;
    $permissions = $currentUser['permissions'] ?? [];
    return is_array($permissions) ? $permissions : [];
}

function get_user_roles(): array
{
    global $currentUser;
    $roles = $currentUser['roles'] ?? [];
    return is_array($roles) ? $roles : [];
}

// ==============================================
// ⭐ ADMIN UI COMPATIBILITY
// ==============================================
if (!isset($GLOBALS['ADMIN_UI'])) {
    $GLOBALS['ADMIN_UI'] = [
        'user' => $currentUser ?? [],
        'is_platform_admin' => is_platform_admin(),
        'platform_role' => get_platform_role(),
        'is_super_admin' => is_platform_admin() || in_array('super_admin', get_user_roles(), true),
        'user_type' => is_platform_admin() ? 'platform_admin' : (in_array('super_admin', get_user_roles(), true) ? 'tenant_admin' : 'tenant_user'),
        'tenant_id' => $currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0,
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ];
}

// ==============================================
// ⭐ DEBUG OUTPUT (only in development)
// ==============================================
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && !empty($_GET['debug'])) {
    echo '<pre style="background:#f0f0f0; padding:10px; margin:10px;">';
    echo '=== ADMIN CONTEXT DEBUG ===' . PHP_EOL;
    echo 'PDO Connected: ' . ($pdo ? 'Yes' : 'No') . PHP_EOL;
    echo 'User Logged In: ' . (is_logged_in() ? 'Yes' : 'No') . PHP_EOL;
    echo 'Platform Admin: ' . (is_platform_admin() ? 'Yes (' . get_platform_role() . ')' : 'No') . PHP_EOL;
    echo 'User ID: ' . (current_user_id() ?? 'null') . PHP_EOL;
    echo 'Tenant ID: ' . (($currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? null) ?? 'null') . PHP_EOL;
    echo 'Identity Source: ' . ($identity ? $identity->source() : 'null') . PHP_EOL;
    echo 'Permissions: ' . json_encode(get_user_permissions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo 'Roles: ' . json_encode(get_user_roles(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo 'Session ID: ' . session_id() . PHP_EOL;
    echo 'Session keys: ' . implode(', ', array_keys($_SESSION)) . PHP_EOL;
    echo '</pre>';
}