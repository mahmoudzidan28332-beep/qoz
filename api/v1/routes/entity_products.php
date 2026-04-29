<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntityProductsRepository.php';
require_once $modelsPath . '/validators/EntityProductsValidator.php';
require_once $modelsPath . '/services/EntityProductsService.php';
require_once $modelsPath . '/controllers/EntityProductsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ================================
// Tenant & Auth check
// ================================
$tenantId = resolve_tenant_id();

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$repo = new PdoEntityProductsRepository($pdo);
$service = new EntityProductsService($repo);
$controller = new EntityProductsController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters — tenant_id is always forced from session
    $filters = ['tenant_id' => $tenantId];

    if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
        $filters['entity_id'] = (int)$_GET['entity_id'];
    }

    if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
        $filters['product_id'] = (int)$_GET['product_id'];
    }

    if (isset($_GET['is_active']) && in_array($_GET['is_active'], ['0', '1'])) {
        $filters['is_active'] = (int)$_GET['is_active'];
    }

    if (isset($_GET['is_featured']) && in_array($_GET['is_featured'], ['0', '1'])) {
        $filters['is_featured'] = (int)$_GET['is_featured'];
    }

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $filters['search'] = trim($_GET['search']);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // GET /api/entity_products?action=statistics
            if (isset($_GET['action']) && $_GET['action'] === 'statistics') {
                $statistics = $controller->getStatistics($tenantId);
                ResponseFormatter::success($statistics);
                exit;
            }

            // GET /api/entity_products?action=entity&entity_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) {
                $products = $controller->getEntityProducts((int)$_GET['entity_id'], $tenantId);
                ResponseFormatter::success($products);
                exit;
            }

            // GET /api/entity_products?id={id}&entity_id={id}
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $entityId = (int)($_GET['entity_id'] ?? 0);
                if ($entityId <= 0) {
                    ResponseFormatter::error('entity_id is required', 400);
                    exit;
                }
                $item = $controller->get((int)$_GET['id'], $tenantId, $entityId);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Entity product not found', 404);
                }
            }
            // GET /api/entity_products - paginated list
            else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
                $total = $result['meta']['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                        'filters'     => $filters
                    ]
                ]);
            }
            break;

        case 'POST':
            // POST /api/entity_products?action=bulk&entity_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'bulk' && isset($_GET['entity_id'])) {
                $entityId = (int)$_GET['entity_id'];
                $savedIds = $controller->saveEntityProducts($entityId, $tenantId, $data);
                ResponseFormatter::success(['saved_ids' => $savedIds], 'Bulk products saved successfully', 201);
                exit;
            }

            // POST /api/entity_products
            $data['tenant_id'] = $tenantId;
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id']) || !is_numeric($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('entity_id is required', 400);
                exit;
            }

            $data['tenant_id'] = $tenantId;
            $controller->update((int)$data['id'], $data);
            ResponseFormatter::success(['id' => (int)$data['id']], 'Updated successfully');
            break;

        case 'DELETE':
            // DELETE /api/entity_products?action=entity&entity_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) {
                $controller->deleteEntityProducts((int)$_GET['entity_id'], $tenantId);
                ResponseFormatter::success(null, 'All entity products deleted successfully');
                exit;
            }

            // DELETE /api/entity_products?id={id}&entity_id={id}
            $deleteId = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $entityId = (int)($_GET['entity_id'] ?? $data['entity_id'] ?? 0);

            if (!$deleteId || !$entityId) {
                ResponseFormatter::error('ID and entity_id are required', 400);
                exit;
            }

            $controller->delete($deleteId, $tenantId, $entityId);
            ResponseFormatter::success(null, 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'entity_products.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'entity_products.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log("Error in entity_products: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../../error_log.txt');
    safe_log('critical', 'entity_products.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}