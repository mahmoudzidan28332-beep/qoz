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
$modelsPath = API_VERSION_PATH . '/models/products';
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
$isPlatformAdmin = is_platform_admin();
$effectiveTenantId = resolve_tenant_id();

// Platform Admin defaults to 0 (Global View) if no specific tenant is requested
if ($isPlatformAdmin && ($effectiveTenantId === null || $effectiveTenantId === 0)) {
    $effectiveTenantId = 0;
}

// 🔒 SECURITY: Enforce TenantContext
TenantContext::set($effectiveTenantId);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $lang    = $_GET['lang'] ?? 'ar';
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'product_type_id' => $_GET['product_type_id'] ?? null,
        'sku'             => $_GET['sku'] ?? null,
        'slug'            => $_GET['slug'] ?? null,
        'barcode'         => $_GET['barcode'] ?? null,
        'brand_id'        => $_GET['brand_id'] ?? null,
        'is_active'       => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
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
                $activePlan = $controller->getSubscriptionProductLimit();
                if ($activePlan && (int)$activePlan['max_products'] > 0) {
                    $currentCount = $controller->countByTenant();
                    if ($currentCount >= (int)$activePlan['max_products']) {
                        ResponseFormatter::error(
                            'Product limit reached (' . $currentCount . '/' . $activePlan['max_products'] . '). Upgrade your plan to add more products.',
                            403
                        );
                        break;
                    }
                }
            } catch (\RuntimeException $e) {
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
            } catch (\RuntimeException $e) {
                // Don't block product creation if bad-words check fails (service unavailable)
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            $newId = $controller->create($data);

            // Auto-populate SEO meta
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$newId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $effectiveTenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$newId);
            } catch (\RuntimeException $e) {
                error_log('[products] SEO sync on create failed: ' . $e->getMessage());
            }

            // Audit log: product created (new_values = what was just saved)
            AuditLogsService::log(
                'product.create',
                'product',
                (int)$newId,
                null,
                $effectiveTenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                null,   // old_values: nothing existed before
                array_merge($data, ['id' => (int)$newId]) // new_values: full payload
            );

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
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
            } catch (\RuntimeException $e) {
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            // Fetch old state for audit diff (best-effort)
            $oldProductState = null;
            if (!empty($data['id'])) {
                try {
                    $oldProductState = $controller->get((int)$data['id'], $lang);
                } catch (\RuntimeException $e) {
                    error_log('[products] fetch old product state failed: ' . $e->getMessage());
                }
            }

            $updatedId = $controller->update($data);

            // Auto-update SEO meta
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$updatedId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $effectiveTenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$updatedId);
            } catch (\RuntimeException $e) {
                error_log('[products] SEO sync on update failed: ' . $e->getMessage());
            }

            // Audit log: product updated (diff auto-computed by repository)
            AuditLogsService::log(
                'product.update',
                'product',
                (int)$updatedId,
                null,
                $effectiveTenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                $oldProductState,           // old_values: snapshot before update
                array_merge($data, ['id' => (int)$updatedId]) // new_values: submitted payload
            );

            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing product ID for deletion', 400);
            }

            // Fetch old state for audit (best-effort)
            $deletedProductState = null;
            try {
                $deletedProductState = $controller->get((int)$data['id'], $lang);
            } catch (\RuntimeException $e) {
                error_log('[products] fetch deleted product state failed: ' . $e->getMessage());
            }

            $deleted = $controller->delete((int)$data['id']);

            // Auto-delete SEO meta
            try {
                SeoAutoManager::delete($pdo, 'product', (int)$data['id']);
            } catch (\RuntimeException $e) {
                error_log('[products] SEO delete failed: ' . $e->getMessage());
            }

            // Audit log: product deleted (old_values = snapshot before deletion)
            AuditLogsService::log(
                'product.delete',
                'product',
                (int)$data['id'],
                null,
                $effectiveTenantId,
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
    safe_log('warning','products.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','products.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\RuntimeException $e) {
    safe_log('critical','products.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}