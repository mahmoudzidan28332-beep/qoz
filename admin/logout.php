<?php
declare(strict_types=1);

/**
 * admin/logout.php
 * Unified logout handler for both tenant admin and platform admin sessions.
 */

require_once __DIR__ . '/includes/session_boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPlatformSession = !empty($_SESSION['platform_admin']);
$loginUrl = $isPlatformSession ? '/admin/platform_login.php' : '/admin/login.php';

// Best-effort CSRF validation for POST without blocking logout on stale sessions.
if ($method === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($sessionToken !== '' && $postedToken !== '' && !hash_equals($sessionToken, $postedToken)) {
        // Continue logout anyway to avoid trapping the user in a broken session.
    }
}

$sessionName = session_name() ?: 'APP_SESSID';
$cookieParams = session_get_cookie_params();
$secure = !empty($cookieParams['secure']);
$domain = (string)($cookieParams['domain'] ?? '');
$path = (string)($cookieParams['path'] ?? '/');
$host = $_SERVER['HTTP_HOST'] ?? '';

$_SESSION = [];

$extraSessionKeys = [
    'platform_admin',
    'platform_role',
    'platform_user_id',
    'user',
    'user_id',
    'logged_in',
    'last_activity',
    'roles',
    'permissions',
    'resource_permissions',
    'tenant_id',
    'csrf_token',
    '__initiated',
    '__regenerated_at',
];

foreach ($extraSessionKeys as $key) {
    unset($_SESSION[$key]);
}

if (ini_get('session.use_cookies')) {
    setcookie($sessionName, '', time() - 42000, $path, $domain, $secure, true);
    setcookie($sessionName, '', time() - 42000, '/', $domain, $secure, true);
    if ($host !== '') {
        setcookie($sessionName, '', time() - 42000, '/', $host, $secure, true);
        setcookie($sessionName, '', time() - 42000, '/', '.' . ltrim($host, '.'), $secure, true);
    }
}

$extraCookies = ['APP_SESSID', 'PHPSESSID', 'session_token', 'remember_me', '__test'];
foreach ($extraCookies as $cookieName) {
    setcookie($cookieName, '', time() - 42000, '/', '', $secure, false);
    if ($host !== '') {
        setcookie($cookieName, '', time() - 42000, '/', $host, $secure, false);
        setcookie($cookieName, '', time() - 42000, '/', '.' . ltrim($host, '.'), $secure, false);
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>جارٍ تسجيل الخروج...</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: system-ui, sans-serif; background:#f8fafc; color:#1e293b; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { text-align:center; padding:30px; background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:420px; }
    h2 { margin:0 0 16px; color:#dc2626; }
    p { margin:8px 0; color:#64748b; }
    .spinner { width:40px; height:40px; border:4px solid #e2e8f0; border-top:4px solid #3b82f6; border-radius:50%; animation:spin 1s linear infinite; margin:20px auto; }
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>
  <div class="box">
    <div class="spinner"></div>
    <h2>تم تسجيل الخروج بنجاح</h2>
    <p>يتم الآن تحويلك إلى صفحة تسجيل الدخول المناسبة...</p>
  </div>
  <script>
    (function () {
      const names = ['APP_SESSID', 'PHPSESSID', 'session_token', 'remember_me', '__test'];
      const domains = [location.hostname, '.' + location.hostname].filter(Boolean);

      names.forEach(function (name) {
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/admin';
        domains.forEach(function (domain) {
          document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=' + domain;
          document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/admin;domain=' + domain;
        });
      });

      setTimeout(function () {
        window.location.replace(<?php echo json_encode($loginUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);
      }, 500);
    })();
  </script>
</body>
</html>
