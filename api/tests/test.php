<?php
declare(strict_types=1);

// تحميل الـ bootstrap الرئيسي
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/DatabaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1️⃣ اختبار DB
    $pdo = DatabaseConnection::getConnection();
    $stmt = $pdo->query("SELECT 1 AS test");
    $dbTest = $stmt->fetch();

    // 2️⃣ اختبار الثوابت
    $constantsTest = [
        'APP_ENV' => APP_ENV,
        'DEBUG' => DEBUG,
        'DEFAULT_CURRENCY' => DEFAULT_CURRENCY,
        'USER_STATUS_ACTIVE' => USER_STATUS_ACTIVE
    ];

    // 3️⃣ اختبار CORS (origin وهمي)
    $originTest = 'https://qooqz.com';
    $allowedOrigins = ALLOWED_ORIGINS;
    $corsTest = in_array($originTest, $allowedOrigins) || in_array('*', $allowedOrigins);

    // 4️⃣ اختبار وظائف المساعدة
    $envCheck = is_production() ? 'production' : 'not production';

    echo json_encode([
        'success' => true,
        'message' => 'All shared config files loaded and working',
        'tests' => [
            'db_connection' => $dbTest,
            'constants' => $constantsTest,
            'cors_allowed_example' => $corsTest,
            'env_check' => $envCheck
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error during shared config test',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
