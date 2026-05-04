<?php
declare(strict_types=1);
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';
$sharedPath = $baseDir . '/shared/core';
require_once $sharedPath . '/BaseRepository.php';
require_once $sharedPath . '/TenantContext.php';   
require_once $sharedPath . '/QueryGuard.php';


// ===== تحميل ملفات products =====
require_once API_VERSION_PATH . '/models/products/repositories/PdoProductAttributeAssignmentsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductAttributeAssignmentsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductAttributeAssignmentsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductAttributeAssignmentsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

if (session_status() === PHP_SESSION_NONE) session_start();
$rawRequestBody = file_get_contents('php://input');
$requestData = $rawRequestBody ? (json_decode($rawRequestBody, true) ?? []) : [];
$effectiveTenantId = resolve_product_scope_tenant_id($pdo, $requestData);
if (is_platform_admin() && ($effectiveTenantId === null || $effectiveTenantId <= 0)) {
    $effectiveTenantId = 0;
} elseif ($effectiveTenantId === null || $effectiveTenantId <= 0) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}
TenantContext::set($effectiveTenantId);

// إنشاء الاعتمادات
$repo      = new PdoProductAttributeAssignmentsRepository($pdo);
$validator = new ProductAttributeAssignmentsValidator();
$service   = new ProductAttributeAssignmentsService($repo, $validator);
$controller = new ProductAttributeAssignmentsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /product_attribute_assignments/by_product?product_id=123
    if ($method === 'GET' && str_contains($uri, '/product_attribute_assignments/by_product')) {
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($productId <= 0) {
            throw new InvalidArgumentException('Valid product_id is required');
        }
        ResponseFormatter::success(
            $controller->getByProduct($productId)
        );
    } elseif ($method === 'DELETE' && str_contains($uri, '/product_attribute_assignments/by_product')) {
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($productId <= 0) {
            throw new InvalidArgumentException('Valid product_id is required');
        }
        $controller->deleteByProduct($productId);
        ResponseFormatter::success(['deleted' => true]);
    } elseif ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success(
                $controller->get($id)
            );
        } else {
            ResponseFormatter::success(
                $controller->list()
            );
        }
    } elseif ($method === 'POST') {
        $data = $requestData;
        $data = array_intersect_key($data, array_flip(['product_id', 'attribute_id', 'attribute_value_id', 'custom_value']));
        ResponseFormatter::success(
            $controller->create($data)
        );
    } elseif ($method === 'PUT') {
        $data = $requestData;
        $data = array_intersect_key($data, array_flip(['product_id', 'attribute_id', 'attribute_value_id', 'custom_value'])) + (isset($data['id']) ? ['id' => $data['id']] : []);
        ResponseFormatter::success(
            $controller->update($data)
        );
    } elseif ($method === 'DELETE') {
        $data = $requestData;
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    safe_log('error', 'Product attribute assignments route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
