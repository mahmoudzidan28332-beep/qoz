<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$sharedPath = $baseDir . '/shared/core';
require_once $sharedPath . '/BaseRepository.php';
require_once $sharedPath . '/BaseService.php';
require_once $sharedPath . '/BaseController.php';
require_once $sharedPath . '/TenantContext.php';
require_once $sharedPath . '/QueryGuard.php';
require_once $sharedPath . '/BasePolicy.php';


$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductTranslationsRepository.php';
require_once $modelsPath . '/services/ProductTranslationsService.php';
require_once $modelsPath . '/controllers/ProductTranslationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Multi-tenant isolation hardening
require_once $baseDir . '/shared/helpers/admin_context.php';
require_once $baseDir . '/shared/helpers/TenantContext.php';

$user = $_SESSION['user'] ?? [];
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$effectiveTenantId = resolve_tenant_id($_GET, $_SESSION, $isPlatformAdmin);
TenantContext::set($effectiveTenantId);

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) ResponseFormatter::error('Database not initialized', 500);

$repo = new PdoProductTranslationsRepository($pdo);
$service = new ProductTranslationsService($repo);
$controller = new ProductTranslationsController($service);

$languageCode = $user['preferred_language'] ?? 'en';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $filters = $_GET ?? [];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
        $orderBy = $_GET['order_by'] ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';

        if ($id !== null) {
            $res = $controller->get($id, $languageCode);
            ResponseFormatter::success($res);
        } else {
            // When listing translations for a specific product, show ALL languages
            $langFilter = !empty($filters['product_id']) ? null : $languageCode;
            $res = $controller->list($langFilter, $limit, $offset, $filters, $orderBy, $orderDir);
            ResponseFormatter::success($res);
        }
        exit;
    }

    if ($method === 'POST') {
        $res = $controller->create($data);

        // Auto-sync SEO translation
        try {
            $prodId = $data['product_id'] ?? null;
            $lc = $data['language_code'] ?? null;
            if ($prodId && $lc) {
                SeoAutoManager::syncTranslation($pdo, 'product', (int)$prodId, $lc, [
                    'name'        => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[product_translations] SEO sync on create failed: ' . $e->getMessage());
        }

        ResponseFormatter::success($res, 'Created', 201);
        exit;
    }

    if ($method === 'PUT') {
        $res = $controller->update($data);

        // Auto-sync SEO translation
        try {
            $prodId = $data['product_id'] ?? null;
            $lc = $data['language_code'] ?? null;
            if ($prodId && $lc) {
                SeoAutoManager::syncTranslation($pdo, 'product', (int)$prodId, $lc, [
                    'name'        => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[product_translations] SEO sync on update failed: ' . $e->getMessage());
        }

        ResponseFormatter::success($res, 'Updated');
        exit;
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if ($id === null) ResponseFormatter::error('ID required', 400);
        $res = $controller->delete((int)$id);
        ResponseFormatter::success($res, 'Deleted');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);
} catch(Throwable $e){
    safe_log('critical','product_translations.fatal',['error'=>$e->getMessage()]);
    $msg = ($e instanceof InvalidArgumentException) ? $e->getMessage() : 'Internal server error';
    ResponseFormatter::error($msg,500);
}
