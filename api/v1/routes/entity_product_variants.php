<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntityProductVariantsRepository.php';
require_once $modelsPath . '/validators/EntityProductVariantsValidator.php';
require_once $modelsPath . '/services/EntityProductVariantsService.php';
require_once $modelsPath . '/controllers/EntityProductVariantsController.php';

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

$repo = new PdoEntityProductVariantsRepository($pdo);
$service = new EntityProductVariantsService($repo);
$controller = new EntityProductVariantsController($service);

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

    if (isset($_GET['variant_id']) && is_numeric($_GET['variant_id'])) {
        $filters['variant_id'] = (int)$_GET['variant_id'];
    }

    if (isset($_GET['is_active']) && in_array($_GET['is_active'], ['0', '1'])) {
        $filters['is_active'] = (int)$_GET['is_active'];
    }

    if (isset($_GET['is_featured']) && in_array($_GET['is_featured'], ['0', '1'])) {
        $filters['is_featured'] = (int)$_GET['is_featured'];
    }

    if (isset($_GET['stock_status']) && in_array($_GET['stock_status'], ['in_stock', 'out_of_stock', 'unlimited'])) {
        $filters['stock_status'] = $_GET['stock_status'];
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
            // GET /api/entity_product_variants?action=statistics
            if (isset($_GET['action']) && $_GET['action'] === 'statistics') {
                $statistics = $controller->getStatistics();
                ResponseFormatter::success($statistics);
                exit;
            }

            // GET /api/entity_product_variants?action=entity&entity_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) {
                $variants = $controller->getEntityVariants((int)$_GET['entity_id'], $tenantId);
                ResponseFormatter::success($variants);
                exit;
            }

            // GET /api/entity_product_variants?action=product&entity_id={id}&product_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'product' && isset($_GET['entity_id']) && isset($_GET['product_id'])) {
                $variants = $controller->getEntityProductVariants((int)$_GET['entity_id'], (int)$_GET['product_id'], $tenantId);
                ResponseFormatter::success($variants);
                exit;
            }

            // GET /api/entity_product_variants?id={id}
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $tenantId);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Entity product variant not found', 404);
                }
            }
            // GET /api/entity_product_variants - paginated list
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
            // POST /api/entity_product_variants?action=bulk&entity_id={id}&tenant_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'bulk' && isset($_GET['entity_id'])) {
                $entityId = (int)$_GET['entity_id'];
                $tenantId = resolve_tenant_id() ?? (int)($data['tenant_id'] ?? 0);
                if ($tenantId <= 0) {
                    ResponseFormatter::error('tenant_id is required for bulk save', 400);
                    exit;
                }
                $savedIds = $controller->saveEntityVariants($entityId, $tenantId, $data);
                ResponseFormatter::success(['saved_ids' => $savedIds], 'Bulk variants saved successfully', 201);
                exit;
            }

            // POST /api/entity_product_variants
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id']) || !is_numeric($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }

            $controller->update((int)$data['id'], $data);
            ResponseFormatter::success(['id' => (int)$data['id']], 'Updated successfully');
            break;

        case 'DELETE':
            // DELETE /api/entity_product_variants?action=entity&entity_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) {
                $controller->deleteEntityVariants((int)$_GET['entity_id'], $tenantId);
                ResponseFormatter::success(null, 'All entity variants deleted successfully');
                exit;
            }

            // DELETE /api/entity_product_variants?action=product&entity_id={id}&product_id={id}
            if (isset($_GET['action']) && $_GET['action'] === 'product' && isset($_GET['entity_id']) && isset($_GET['product_id'])) {
                $controller->deleteEntityProductVariants((int)$_GET['entity_id'], (int)$_GET['product_id'], $tenantId);
                ResponseFormatter::success(null, 'Entity product variants deleted successfully');
                exit;
            }

            // DELETE /api/entity_product_variants?id={id}
            $deleteId = null;
            if (!empty($data['id']) && is_numeric($data['id'])) {
                $deleteId = (int)$data['id'];
            } elseif (!empty($_GET['id']) && is_numeric($_GET['id'])) {
                $deleteId = (int)$_GET['id'];
            }

            if (!$deleteId) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }

            $controller->delete($deleteId, $tenantId);
            ResponseFormatter::success(null, 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'entity_product_variants.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'entity_product_variants.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log("Error in entity_product_variants: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../../error_log.txt');
    safe_log('critical', 'entity_product_variants.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}
