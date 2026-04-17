<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

try {
    $db = connectDB();

    // اختبار بسيط
    $stmt = $db->query("SELECT 1 AS test");
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'PDO connection OK',
        'result'  => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PDO connection failed',
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
