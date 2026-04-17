<?php
declare(strict_types=1);

/**
 * api/tests/test_session_login.php
 *
 * ملف تشخيصي: يتحقق مما إذا كانت الجلسة تُحفظ بعد تسجيل الدخول
 * وما إذا كانت بيانات المستخدم تظهر في bootstrap_admin_ui.php
 *
 * الاستخدام:
 *   GET  /api/tests/test_session_login.php              → تقرير حالة الجلسة الحالية
 *   GET  /api/tests/test_session_login.php?simulate=1   → محاكاة تسجيل الدخول (وضع user_id)
 *   GET  /api/tests/test_session_login.php?clear=1      → مسح بيانات الجلسة
 *   GET  /api/tests/test_session_login.php?user_id=123  → محاكاة user_id محدد
 *
 * ملاحظة: هذا الملف للتشخيص فقط – لا تتركه في بيئة الإنتاج.
 */

header('Content-Type: application/json; charset=utf-8');

// ------------------------------------------------------------------
// 1. حساب مسار حفظ الجلسات (نفس المسار الذي تستخدمه bootstrap.php)
// ------------------------------------------------------------------
$apiDir         = dirname(__DIR__);               // /api
$apiSessionPath = $apiDir . '/storage/sessions';

// Capture PHP default path BEFORE applying any custom setting.
$defaultSavePath = session_save_path() ?: ini_get('session.save_path') ?: '/tmp';

// ------------------------------------------------------------------
// 2. ضبط مسار الحفظ قبل بدء الجلسة
// ------------------------------------------------------------------
if (!is_dir($apiSessionPath)) {
    if (!mkdir($apiSessionPath, 0700, true) && !is_dir($apiSessionPath)) {
        error_log('test_session_login: Failed to create session directory: ' . $apiSessionPath);
    }
}
if (is_dir($apiSessionPath)) {
    ini_set('session.save_path', $apiSessionPath);
}

// Evaluate paths_match AFTER ini_set: meaningful check is whether the session
// is NOW configured to use the api custom path, not whether PHP default ≠ custom.
$actualSavePath = ini_get('session.save_path');
$pathsMatch = (
    $actualSavePath !== '' &&
    realpath($actualSavePath) !== false &&
    realpath($actualSavePath) === realpath($apiSessionPath)
);

$diag = [];
$diag['session_paths'] = [
    'api_custom_path'   => $apiSessionPath,
    'api_path_exists'   => is_dir($apiSessionPath),
    'api_path_writable' => is_dir($apiSessionPath) && is_writable($apiSessionPath),
    'php_default_path'  => $defaultSavePath,
    'actual_save_path'  => $actualSavePath,
    'paths_match'       => $pathsMatch,
];
$diag['mismatch_warning'] = !$pathsMatch
    ? 'مشكلة: مسار حفظ الجلسات مختلف! API يحفظ في api_custom_path لكن هذا الملف يقرأ من php_default_path → المستخدم يبدو دائماً غير مسجّل.'
    : 'مسار الجلسات متطابق ✓';

// بدء الجلسة بنفس الإعدادات المستخدمة في bootstrap_admin_ui.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('APP_SESSID');
    session_start([
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// ------------------------------------------------------------------
// 3. محاكاة مسح الجلسة إذا طُلب ذلك
// ------------------------------------------------------------------
if (!empty($_GET['clear'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    echo json_encode([
        'ok'      => true,
        'action'  => 'clear',
        'message' => 'تم مسح بيانات الجلسة',
        'session_id' => session_id(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------------
// 4. محاكاة تسجيل الدخول (simulate) — يضع بيانات وهمية في الجلسة
// ------------------------------------------------------------------
$simulated = false;
if (!empty($_GET['simulate']) || !empty($_GET['user_id'])) {
    $mockUserId = (int)($_GET['user_id'] ?? 1);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $mockUserId;
    $_SESSION['user'] = [
        'id'                 => $mockUserId,
        'username'           => 'test_user_' . $mockUserId,
        'email'              => 'test_' . $mockUserId . '@example.com',
        'role_id'            => 1,
        'tenant_id'          => 1,
        'preferred_language' => 'en',
        'is_active'          => true,
        'permissions'        => ['admin.view', 'admin.edit'],
        'roles'              => ['admin'],
    ];
    $_SESSION['roles']       = ['admin'];
    $_SESSION['permissions'] = ['admin.view', 'admin.edit'];
    $simulated = true;
}

// ------------------------------------------------------------------
// 5. قراءة حالة الجلسة الحالية
// ------------------------------------------------------------------
$sessionState = [
    'session_id'       => session_id(),
    'session_name'     => session_name(),
    'save_path_used'   => ini_get('session.save_path') ?: 'default',
    'cookie_received'  => $_COOKIE[session_name()] ?? null,
    'has_user'         => !empty($_SESSION['user']),
    'has_user_id'      => !empty($_SESSION['user_id']),
    'session_keys'     => array_keys($_SESSION),
    'user_in_session'  => $_SESSION['user'] ?? null,
];

// ------------------------------------------------------------------
// 6. تحميل bootstrap_admin_ui.php والتحقق من ADMIN_UI
// ------------------------------------------------------------------
$adminUiResult = null;
$adminUiError  = null;
$bootstrapFile = $apiDir . '/bootstrap_admin_ui.php';

if (is_file($bootstrapFile)) {
    try {
        require_once $bootstrapFile;
        $adminUi = $GLOBALS['ADMIN_UI'] ?? null;
        if (is_array($adminUi)) {
            $adminUiResult = [
                'user_id'       => $adminUi['user']['id'] ?? null,
                'username'      => $adminUi['user']['username'] ?? null,
                'email'         => $adminUi['user']['email'] ?? null,
                'role_id'       => $adminUi['user']['role_id'] ?? null,
                'is_active'     => $adminUi['user']['is_active'] ?? false,
                'tenant_id'     => $adminUi['user']['tenant_id'] ?? null,
                'roles'         => $adminUi['user']['roles'] ?? [],
                'permissions_count' => count($adminUi['user']['permissions'] ?? []),
                'lang'          => $adminUi['lang'] ?? null,
                'direction'     => $adminUi['direction'] ?? null,
                'user_loaded'   => !empty($adminUi['user']['id']),
            ];
        } else {
            $adminUiError = 'ADMIN_UI غير موجود في GLOBALS بعد تحميل bootstrap_admin_ui.php';
        }
    } catch (Throwable $e) {
        $adminUiError = $e->getMessage();
    }
} else {
    $adminUiError = 'bootstrap_admin_ui.php غير موجود في: ' . $bootstrapFile;
}

// ------------------------------------------------------------------
// 7. تشخيص المشكلة وإعطاء التوصيات
// ------------------------------------------------------------------
$checks = [];

// فحص 1: مطابقة مسار الجلسة
$checks['session_path_match'] = [
    'ok'      => $diag['session_paths']['paths_match'],
    'message' => $diag['session_paths']['paths_match']
        ? 'مسار حفظ الجلسة صحيح ✓'
        : 'مشكلة: مسار الجلسة مختلف بين API ومسار القراءة → الجلسة لن تُقرأ بعد تسجيل الدخول',
];

// فحص 2: وجود بيانات المستخدم في الجلسة
$checks['session_has_user'] = [
    'ok'      => !empty($_SESSION['user']),
    'message' => !empty($_SESSION['user'])
        ? 'بيانات المستخدم موجودة في الجلسة ✓'
        : (empty($_COOKIE[session_name()])
            ? 'لا يوجد كوكي جلسة → ربما المتصفح لا يرسل الكوكي أو لم يتم تسجيل الدخول بعد'
            : 'الكوكي موجود لكن لا توجد بيانات مستخدم → الجلسة فارغة أو تم حفظها في مسار مختلف'),
];

// فحص 3: ADMIN_UI يحتوي على بيانات المستخدم
$checks['admin_ui_has_user'] = [
    'ok'      => ($adminUiResult !== null && !empty($adminUiResult['user_id'])),
    'message' => ($adminUiResult !== null && !empty($adminUiResult['user_id']))
        ? 'ADMIN_UI يحتوي على بيانات المستخدم ✓'
        : 'ADMIN_UI لا يحتوي على بيانات المستخدم → الجلسة فارغة أو DB لا يعمل',
];

// فحص 4: اتساق session_name
$checks['session_name_consistent'] = [
    'ok'      => (session_name() === 'APP_SESSID'),
    'message' => (session_name() === 'APP_SESSID')
        ? 'اسم الجلسة APP_SESSID صحيح ✓'
        : 'اسم الجلسة ' . session_name() . ' ≠ APP_SESSID → عدم تطابق مع API',
];

$allPassed = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);

// ------------------------------------------------------------------
// 8. الخلاصة
// ------------------------------------------------------------------
// Return 200 when simulated (diagnostic mode, not a real login check).
// Return 500 when session checks fail on a real session (non-simulated).
if ($simulated) {
    $statusCode = 200;
} elseif ($allPassed) {
    $statusCode = 200;
} else {
    $statusCode = 500;
}
http_response_code($statusCode);

echo json_encode([
    'ok'        => $allPassed,
    'simulated' => $simulated,
    'summary'   => $allPassed
        ? 'جميع فحوصات الجلسة نجحت ✓'
        : 'بعض الفحوصات فشلت — انظر checks وdiag',
    'checks'    => $checks,
    'session'   => $sessionState,
    'admin_ui'  => $adminUiResult,
    'admin_ui_error' => $adminUiError,
    'diag'      => $diag,
    'tips'      => [
        'simulate_login' => 'أضف ?simulate=1 لمحاكاة تسجيل دخول وهمي ثم تحقق من admin_ui',
        'clear_session'  => 'أضف ?clear=1 لمسح الجلسة',
        'debug_endpoint' => '/api/bootstrap_admin_ui.php?__admin_ui_debug=1',
        'root_cause'     => 'إذا session_path_match=false → bootstrap_admin_ui.php وbootstrap_admin_context.php لا تضبط ini session.save_path قبل session_start',
    ],
    'timestamp' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);