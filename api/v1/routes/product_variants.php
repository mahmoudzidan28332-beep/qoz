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

$modelsPath = API_VERSION_PATH . '/models';
$variantsPath = $modelsPath . '/product_variants';
require_once $variantsPath.'/repositories/PdoProductVariantsRepository.php';
require_once $variantsPath.'/validators/ProductVariantValidator.php';
require_once $variantsPath.'/services/ProductVariantService.php';
require_once $variantsPath.'/controllers/ProductVariantController.php';

if(session_status()===PHP_SESSION_NONE) session_start();

// Multi-tenant isolation hardening
require_once $baseDir . '/shared/helpers/admin_context.php';
require_once $baseDir . '/shared/helpers/TenantContext.php';

$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$effectiveTenantId = resolve_tenant_id($_GET, $_SESSION, $isPlatformAdmin);
TenantContext::set($effectiveTenantId);

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if(!$pdo instanceof PDO) ResponseFormatter::error('Database not initialized',500);

$repo = new PdoProductVariantsRepository($pdo);
$validator = new ProductVariantValidator();
$service = new ProductVariantService($repo,$validator);
$controller = new ProductVariantController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw,true) : [];

    $filters = $_GET;
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : null;
    $offset = isset($filters['offset']) ? (int)$filters['offset'] : null;
    $orderBy = $filters['order_by'] ?? 'id';
    $orderDir = $filters['order_dir'] ?? 'DESC';
    $languageCode = $_GET['language_code'] ?? null;

    switch($method){
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            http_response_code(204);
            exit;

        case 'GET':
            if(!empty($_GET['id'])){
                $items = $controller->listWithTranslations($languageCode, null, null, ['id'=>$_GET['id']]);
                ResponseFormatter::success($items[0] ?? []);
            } elseif(!empty($_GET['variant_id']) && !empty($_GET['translations'])){
                $translations = $controller->getTranslations((int)$_GET['variant_id']);
                ResponseFormatter::success($translations);
            } else {
                $items = $controller->listWithTranslations($languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success(['items'=>$items]);
            }
            break;

        case 'POST':
        case 'PUT':
            if(!empty($data['translation'])){
                $controller->saveTranslation((int)$data['variant_id'], $data['translation']['language_code'], $data['translation']['name']);
                ResponseFormatter::success(['saved'=>true]);
            } else {
                $id = $controller->createOrUpdate($data);
                ResponseFormatter::success(['id'=>$id]);
            }
            break;

        case 'DELETE':
            if(empty($data['id'])) ResponseFormatter::error('Missing id',400);
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted'=>$deleted]);
            break;

        default:
            ResponseFormatter::error('Method not allowed',405);
    }

}catch (\RuntimeException $e){
    ResponseFormatter::error($e->getMessage(),500);
}