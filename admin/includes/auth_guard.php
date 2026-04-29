<?php
declare(strict_types=1);
/**
 * admin/includes/auth_guard.php
 * Authentication Guard — Production Fixed
 *
 * الإصلاح: isLoggedIn() كانت تتحقق من $_SESSION['logged_in']
 * وهو مفتاح لا يكتبه نظام الجلسة الحديث أبدًا.
 * الآن تتحقق من $_SESSION['user_id'] و $_SESSION['user']['id']
 * بما يتوافق مع UserIdentityResolver::syncSession().
 */

require_once __DIR__ . '/session_boot.php';

// ════════════════════════════════════════════════════════════
// CORE AUTH CHECKS
// ════════════════════════════════════════════════════════════

/**
 * فحص إذا كان المستخدم مسجل دخول.
 *
 * يتحقق من user_id في الجلسة (يكتبه UserIdentityResolver::syncSession)
 * وليس من 'logged_in' الذي لم يعد يُكتب في النظام الحديث.
 */
function isLoggedIn(): bool
{
    $userId = $_SESSION['user_id']
           ?? $_SESSION['user']['id']
           ?? null;

    return $userId !== null && (int)$userId > 0;
}

/**
 * فحص صلاحية الجلسة (منع الخمول الطويل).
 * Timeout: ساعتان من آخر نشاط.
 */
function isSessionValid(): bool
{
    $sessionTimeout = 7200;
    $lastActivity   = (int)($_SESSION['last_activity'] ?? 0);

    if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeout) {
        return false;
    }

    return true;
}

/**
 * تحديث وقت آخر نشاط.
 */
function updateLastActivity(): void
{
    $_SESSION['last_activity'] = time();
}

// ════════════════════════════════════════════════════════════
// PERMISSION HELPERS
// ════════════════════════════════════════════════════════════

/**
 * فحص صلاحية معينة.
 * super_admin يملك كل الصلاحيات تلقائيًا.
 */
function hasPermission(string $permission): bool
{
    // super_admin bypass
    $roles = $_SESSION['roles'] ?? $_SESSION['user']['roles'] ?? [];
    if (in_array('super_admin', (array)$roles, true)) {
        return true;
    }

    // role_id = 1 → super_admin أيضًا
    $roleId = (int)(
        $_SESSION['user']['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );
    if ($roleId === 1) {
        return true;
    }

    $permissions = $_SESSION['permissions']
                ?? $_SESSION['user']['permissions']
                ?? [];

    return in_array($permission, (array)$permissions, true);
}

/**
 * فحص دور بواسطة role_id.
 */
function hasRole(int $roleId): bool
{
    $current = (int)(
        $_SESSION['user']['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );
    return $current === $roleId;
}

/**
 * هل المستخدم مدير (role_id = 1 أو super_admin)؟
 */
function isAdmin(): bool
{
    if (hasRole(1)) {
        return true;
    }
    $roles = $_SESSION['roles'] ?? $_SESSION['user']['roles'] ?? [];
    return in_array('super_admin', (array)$roles, true);
}

// ════════════════════════════════════════════════════════════
// USER INFO HELPERS
// ════════════════════════════════════════════════════════════

/**
 * بيانات المستخدم الحالي من الجلسة.
 * يدمج بيانات $_SESSION['user'] مع المفاتيح المسطحة للتوافق الرجعي.
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $u = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];

    return [
        'id'          => (int)(  $u['id']          ?? $_SESSION['user_id']  ?? 0),
        'username'    => (string)($u['username']    ?? $u['name']            ?? ''),
        'email'       => (string)($u['email']       ?? ''),
        'role_id'     => (int)(  $u['role_id']      ?? $_SESSION['role_id'] ?? 0),
        'tenant_id'   => isset($u['tenant_id']) ? (int)$u['tenant_id'] : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null),
        'roles'       => (array)($u['roles']        ?? $_SESSION['roles']        ?? []),
        'permissions' => (array)($u['permissions']  ?? $_SESSION['permissions']  ?? []),
        'is_active'   => (bool)( $u['is_active']    ?? true),
        'preferred_language' => (string)($u['preferred_language'] ?? 'en'),
    ];
}

// ════════════════════════════════════════════════════════════
// REDIRECT HELPERS
// ════════════════════════════════════════════════════════════

/**
 * إعادة التوجيه إلى صفحة تسجيل الدخول.
 * لا تدمر الجلسة — فقط تسجل الـ redirect target.
 */
function redirectToLogin(string $message = ''): void
{
    if ($message && !headers_sent()) {
        setcookie('login_message', $message, time() + 60, '/');
    }

    // احفظ هدف الإعادة قبل التوجيه
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';

    if (!headers_sent()) {
        header('Location: /admin/login.php');
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// AUTOMATIC PROTECTION (runs on include)
// ════════════════════════════════════════════════════════════

if (!isLoggedIn()) {
    redirectToLogin('يجب تسجيل الدخول للوصول إلى هذه الصفحة');
}

if (!isSessionValid()) {
    // الجلسة منتهية الصلاحية — امسح بيانات المصادقة فقط
    unset(
        $_SESSION['user_id'],
        $_SESSION['user'],
        $_SESSION['roles'],
        $_SESSION['permissions'],
        $_SESSION['resource_permissions'],
        $_SESSION['tenant_id'],
        $_SESSION['identity_debug'],
        $_SESSION['last_activity'],
    );
    redirectToLogin('انتهت صلاحية الجلسة. سجل دخولك مرة أخرى');
}

updateLastActivity();

/** @var array $currentUser بيانات المستخدم الحالي */
$currentUser = getCurrentUser();

// منع الوصول المباشر لهذا الملف
if (basename($_SERVER['PHP_SELF']) === 'auth_guard.php') {
    http_response_code(403);
    exit('Access denied');
}