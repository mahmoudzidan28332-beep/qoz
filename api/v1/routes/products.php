<?php
declare(strict_types=1);

/**
 * Products Route
 *
 * Thin route layer for products, ensuring zero-finding security posture.
 */

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

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/ProductsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoProductsRepository.php';
require_once $modelsPath . '/services/ProductsService.php';
require_once $modelsPath . '/controllers/ProductsController.php';

// Bad words service
$badWordsPath = API_VERSION_PATH . '/models/bad_words';
require_once $badWordsPath . '/repositories/PdoBadWordsRepository.php';
require_once $badWordsPath . '/services/BadWordsService.php';

// Audit logs service
$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Internal Server Error', 500);
    exit;
}

// Layered Initialization
$repo = new PdoProductsRepository($pdo);
$service = new ProductsService($repo);
$controller = new ProductsController($service);

$badWordsRepo = new PdoBadWordsRepository($pdo);
$badWordsService = new BadWordsService($badWordsRepo);

// Tenant Context Enforcement
$isPlatformAdmin = is_platform_admin();
$effectiveTenantId = resolve_tenant_id();

if ($isPlatformAdmin && ($effectiveTenantId === null || $effectiveTenantId === 0)) {
    $effectiveTenantId = 0;
}

TenantContext::set($effectiveTenantId);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $data = $rawBody ? json_decode($rawBody, true) : [];

    $lang    = (string)($_GET['lang'] ?? 'ar');
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = (string)($_GET['order_by'] ?? 'id');
    $orderDir = (string)($_GET['order_dir'] ?? 'DESC');

    $filters = [
        'product_type_id' => $_GET['product_type_id'] ?? null,
        'sku'             => $_GET['sku'] ?? null,
        'slug'            => $_GET['slug'] ?? null,
        'barcode'         => $_GET['barcode'] ?? null,
        'brand_id'        => $_GET['brand_id'] ?? null,
        'is_active'       => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => (int)ceil($result['total'] / $limit)
                    ]
                ]);
            }
            break;

        case 'POST':
            // 1. Limit Check
            $plan = $controller->getSubscriptionProductLimit();
            if ($plan && (int)$plan['max_products'] > 0) {
                if ($controller->countByTenant() >= (int)$plan['max_products']) {
                    ResponseFormatter::error('Product limit reached', 403);
                    break;
                }
            }

            // 2. Bad Words Check
            foreach (['name', 'description', 'short_description', 'specifications'] as $f) {
                if (!empty($data[$f]) && !$badWordsService->checkText((string)$data[$f])['clean']) {
                    ResponseFormatter::error('Content contains prohibited words', 422);
                    exit;
                }
            }

            // 3. Create
            $newId = $controller->create($data);

            // 4. Post-Create (SEO & Audit)
            SeoAutoManager::sync($pdo, 'product', (int)$newId, [
                'name' => $data['name'] ?? '', 'slug' => $data['slug'] ?? '',
                'description' => $data['description'] ?? '', 'tenant_id' => $effectiveTenantId
            ]);
            AuditLogsService::log('product.create', 'product', (int)$newId, null, $effectiveTenantId, (int)($_SESSION['user_id'] ?? 0), null, $data);

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            // 1. Fetch Old State
            $id = (int)($data['id'] ?? 0);
            $oldState = $controller->get($id, $lang);
            if (!$oldState) {
                ResponseFormatter::error('Product not found', 404);
                break;
            }

            // 2. Bad Words Check
            foreach (['name', 'description', 'short_description', 'specifications'] as $f) {
                if (!empty($data[$f]) && !$badWordsService->checkText((string)$data[$f])['clean']) {
                    ResponseFormatter::error('Content contains prohibited words', 422);
                    exit;
                }
            }

            // 3. Update
            $controller->update($data);

            // 4. Post-Update
            SeoAutoManager::sync($pdo, 'product', $id, [
                'name' => $data['name'] ?? '', 'slug' => $data['slug'] ?? '',
                'description' => $data['description'] ?? '', 'tenant_id' => $effectiveTenantId
            ]);
            AuditLogsService::log('product.update', 'product', $id, null, $effectiveTenantId, (int)($_SESSION['user_id'] ?? 0), $oldState, $data);

            ResponseFormatter::success(['id' => $id], 'Updated successfully');
            break;

        case 'DELETE':
            $id = (int)($data['id'] ?? 0);
            $oldState = $controller->get($id, $lang);
            if (!$oldState) {
                ResponseFormatter::error('Product not found', 404);
                break;
            }

            $controller->delete($id);
            AuditLogsService::log('product.delete', 'product', $id, null, $effectiveTenantId, (int)($_SESSION['user_id'] ?? 0), $oldState, null);

            ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error('Validation failed', 422);
} catch (RuntimeException $e) {
    error_log('[ProductsRoute] Runtime error: ' . $e->getMessage());
    ResponseFormatter::error('Operation failed', 400);
} catch (Throwable $e) {
    error_log('[ProductsRoute] Fatal error: ' . $e->getMessage());
    ResponseFormatter::error('Internal Server Error', 500);
}