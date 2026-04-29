<?php
declare(strict_types=1);

/**
 * test_request_source.php - تحليل الطلبات التي تأتي من admin
 */

// تسجيل معلومات الطلب الحالي
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'script' => __FILE__,
    'request_uri' => $_SERVER['REQUEST_URI'],
    'is_admin_request' => strpos($_SERVER['REQUEST_URI'], '/admin') === 0,
    'is_api_request' => strpos($_SERVER['REQUEST_URI'], '/api') === 0,
    'cookies' => $_COOKIE,
    'app_sessid_value' => $_COOKIE['APP_SESSID'] ?? 'NOT_SET',
    'session_started' => session_status(),
];

// بدء الجلسة إذا لزم الأمر
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__, 2) . '/api/shared/config/session.php';
}

$logEntry['session_id_after_start'] = session_id();
$logEntry['session_user_id'] = $_SESSION['user_id'] ?? null;
$logEntry['session_keys'] = array_keys($_SESSION);

// حفظ السجل
$logFile = dirname(__DIR__, 2) . '/api/logs/source_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n" . str_repeat('-', 80) . "\n", FILE_APPEND);

// عرض النتيجة
echo "<pre>";
echo "=== تحليل مصدر الطلب ===\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n";
echo "APP_SESSID in cookie: " . ($_COOKIE['APP_SESSID'] ?? 'NOT SET') . "\n";
echo "Session ID after start: " . session_id() . "\n";
echo "User ID in session: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "\n✅ تم تسجيل المعلومات في: /api/logs/source_debug.log";
echo "</pre>";