<?php
declare(strict_types=1);

/**
 * admin/includes/session_boot.php
 * Session Bridge — يضمن استخدام session.php المشترك في كل ملفات الأدمن
 * 
 * يجب أن يُضمَّن بدلاً من session_start() المباشر في كل ملفات admin/routes/
 * يضمن أن اسم الجلسة = APP_SESSID ومسار التخزين موحّد مع API
 */

if (php_sapi_name() === 'cli') {
    return;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// المسار المشترك لملف الجلسة
$sessionConfig = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/session.php';

if (!is_file($sessionConfig)) {
    // Fallback: ابحث بالمسار النسبي
    $sessionConfig = dirname(__DIR__, 2) . '/api/shared/config/session.php';
}

if (is_file($sessionConfig)) {
    require_once $sessionConfig;
} else {
    // آخر fallback: ابدأ الجلسة بنفس الإعدادات يدوياً
    $apiSessionPath = dirname(__DIR__, 2) . '/api/storage/sessions';
    if (is_dir($apiSessionPath)) {
        ini_set('session.save_path', $apiSessionPath);
    }
    session_name('APP_SESSID');
    session_set_cookie_params([
        'lifetime' => 604800,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
