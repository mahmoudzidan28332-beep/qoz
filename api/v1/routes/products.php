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
require_once $sharedPath . '/BaseService.php';
require_once $sharedPath . '/BaseController.php';
require_once $sharedPath . '/TenantContext.php';
require_once $sharedPath . '/QueryGuard.php';
require_once $sharedPath . '/BasePolicy.php';

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductsRepository.php';
require_once $modelsPath . '/validators/ProductsValidator.php';
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

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoProductsRepository($pdo);
$service = new ProductsService($repo);
$controller = new ProductsController($service);

// ================================
// Tenant & Auth check
// ================================
$user = $_SESSION['user'] ?? [];
$tenantId = resolve_tenant_id();

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

// ================================
// Handle request
// ================================
try {
    $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw      = file_get_contents('php://input');
    $data     = $raw ? (json_decode($raw, true) ?? []) : [];

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

    // Collect filters
    $filters = [
        'product_type_id' => $_GET['product_type_id'] ?? null,
        'sku'             => $_GET['sku']             ?? null,
        'slug'            => $_GET['slug']            ?? null,
        'barcode'         => $_GET['barcode']         ?? null,
        'brand_id'        => $_GET['brand_id']        ?? null,
        'is_active'       => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
        'language'        => $language,
        'tenant_id'       => $tenantId,
    ];

    // Parse RESTful ID from URL
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $pathInfo   = parse_url($requestUri, PHP_URL_PATH);
    $pathParts  = explode('/', trim($pathInfo, '/'));
    $urlId      = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'products' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            $getId = $urlId ?? (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);
            if ($getId) {
                $item = $controller->get($tenantId, $getId, $language);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $language);
                $total = $result['total'];
                ResponseFormatter::success([
                    'data'  => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            // Check subscription product limit before creating
            try {
                $activePlan = $controller->getSubscriptionProductLimit($tenantId);
                if ($activePlan && (int)$activePlan['max_products'] > 0) {
                    $currentCount = $controller->countByTenant($tenantId);
                    if ($currentCount >= (int)$activePlan['max_products']) {
                        ResponseFormatter::error(
                            'Product limit reached (' . $currentCount . '/' . $activePlan['max_products'] . '). Upgrade your plan to add more products.',
                            403
                        );
                        break;
                    }
                }
            } catch (\Throwable $e) {
                error_log('[products] subscription limit check failed: ' . $e->getMessage());
            }

            // Bad words check on text fields
            try {
                $badWordsRepo    = new PdoBadWordsRepository($pdo);
                $badWordsService = new BadWordsService($badWordsRepo);
                $fieldsToCheck   = ['name', 'description', 'short_description', 'specifications'];
                $badFound        = [];
                foreach ($fieldsToCheck as $field) {
                    $val = $data[$field] ?? '';
                    if ($val === '' || $val === null) continue;
                    $check = $badWordsService->checkText((string)$val);
                    if (!$check['clean']) {
                        foreach ($check['found'] as $hit) {
                            $badFound[] = $hit['word'];
                        }
                    }
                }
                if (!empty($badFound)) {
                    ResponseFormatter::error(
                        'Content contains prohibited words: ' . implode(', ', array_unique($badFound)),
                        422
                    );
                    break;
                }
            } catch (\Throwable $e) {
                // Don't block product creation if bad-words check fails (service unavailable)
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            $newId = $controller->create($tenantId, $data);

            // Auto-populate SEO meta
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$newId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$newId);
            } catch (\Throwable $e) {
                error_log('[products] SEO sync on create failed: ' . $e->getMessage());
            }

            // Audit log: product created (new_values = what was just saved)
            AuditLogsService::log(
                'product.create',
                'product',
                (int)$newId,
                null,
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                null,   // old_values: nothing existed before
                array_merge($data, ['id' => (int)$newId]) // new_values: full payload
            );

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            $updateId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            if (!$updateId) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }
            $data['id'] = $updateId;

            // Bad words check on text fields
            try {
                $badWordsRepo    = new PdoBadWordsRepository($pdo);
                $badWordsService = new BadWordsService($badWordsRepo);
                $fieldsToCheck   = ['name', 'description', 'short_description', 'specifications'];
                $badFound        = [];
                foreach ($fieldsToCheck as $field) {
                    $val = $data[$field] ?? '';
                    if ($val === '' || $val === null) continue;
                    $check = $badWordsService->checkText((string)$val);
                    if (!$check['clean']) {
                        foreach ($check['found'] as $hit) {
                            $badFound[] = $hit['word'];
                        }
                    }
                }
                if (!empty($badFound)) {
                    ResponseFormatter::error(
                        'Content contains prohibited words: ' . implode(', ', array_unique($badFound)),
                        422
                    );
                    break;
                }
            } catch (\Throwable $e) {
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            // Fetch old state for audit diff (best-effort)
            $oldProductState = null;
            try {
                $oldProductState = $controller->get($tenantId, $updateId, $language);
            } catch (\Throwable $e) {}

            $updatedId = $controller->update($tenantId, $data);
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$updatedId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$updatedId);
            } catch (\Throwable $e) {
                error_log('[products] SEO sync on update failed: ' . $e->getMessage());
            }

            // Audit log: product updated (diff auto-computed by repository)
            AuditLogsService::log(
                'product.update',
                'product',
                (int)$updatedId,
                null,
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                $oldProductState,           // old_values: snapshot before update
                array_merge($data, ['id' => (int)$updatedId]) // new_values: submitted payload
            );

            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            $deleteId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            if (!$deleteId) {
                ResponseFormatter::error('Missing product ID for deletion', 400);
                exit;
            }

            // Fetch old state for audit (best-effort)
            $deletedProductState = null;
            try {
                $deletedProductState = $controller->get($tenantId, $deleteId, $language);
            } catch (\Throwable $e) {}

            $deleted = $controller->delete($tenantId, $deleteId);

            // Auto-delete SEO meta
            try {
                SeoAutoManager::delete($pdo, 'product', $deleteId);
            } catch (\Throwable $e) {
                error_log('[products] SEO delete failed: ' . $e->getMessage());
            }

            // Audit log: product deleted (old_values = snapshot before deletion)
            AuditLogsService::log(
                'product.delete',
                'product',
                $deleteId,
                null,
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                $deletedProductState, // old_values
                null                  // new_values: entity no longer exists
            );

            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'products.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'products.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'products.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
