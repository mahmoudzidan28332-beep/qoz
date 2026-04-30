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


$modelsPath = API_VERSION_PATH.'/models/products';
require_once $modelsPath.'/repositories/PdoProductPricingRepository.php';
require_once $modelsPath.'/services/ProductPricingService.php';
require_once $modelsPath.'/controllers/ProductPricingController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Multi-tenant isolation hardening
require_once $baseDir . '/shared/helpers/admin_context.php';
require_once $baseDir . '/shared/helpers/TenantContext.php';

$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$effectiveTenantId = resolve_tenant_id($_GET, $_SESSION, $isPlatformAdmin);
TenantContext::set($effectiveTenantId);

$pdo = $GLOBALS['ADMIN_DB'];
$repo = new PdoProductPricingRepository($pdo);
$service = new ProductPricingService($repo);
$controller = new ProductPricingController($service);

/**
 * Accept only explicitly allowed request fields before any write operation.
 */
function whitelistProductPricingInput(mixed $payload, array $allowedFields): array
{
    if (!is_array($payload)) {
        return [];
    }

    $allowedMap = array_fill_keys($allowedFields, true);
    $filtered = array_intersect_key($payload, $allowedMap);

    return is_array($filtered) ? $filtered : [];
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = json_decode(file_get_contents('php://input'), true);

    $filters = [
        'product_id' => $_GET['product_id'] ?? null,
        'variant_id' => $_GET['variant_id'] ?? null,
        'currency_code' => $_GET['currency_code'] ?? null,
        'pricing_type' => $_GET['pricing_type'] ?? null,
        'is_active' => $_GET['is_active'] ?? null
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get((int)$_GET['id'])
                );
            } else {
                ResponseFormatter::success(
                    $controller->list(
                        isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : null,
                        isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int)$_GET['offset'] : null,
                        $filters,
                        $_GET['order_by'] ?? 'id',
                        $_GET['order_dir'] ?? 'DESC'
                    )
                );
            }
            break;

        case 'POST':
            $data = whitelistProductPricingInput($rawInput, [
                'product_id',
                'variant_id',
                'entity_id',
                'price',
                'tax_rate',
                'cost_price',
                'compare_at_price',
                'currency_code',
                'pricing_type',
                'start_at',
                'end_at',
                'country_id',
                'city_id',
                'is_active',
            ]);
            ResponseFormatter::success(
                ['id'=>$controller->create($data)],
                'Created',
                201
            );
            break;

        case 'PUT':
            $data = whitelistProductPricingInput($rawInput, [
                'id',
                'product_id',
                'variant_id',
                'entity_id',
                'price',
                'tax_rate',
                'cost_price',
                'compare_at_price',
                'currency_code',
                'pricing_type',
                'start_at',
                'end_at',
                'country_id',
                'city_id',
                'is_active',
            ]);
            ResponseFormatter::success(
                ['id'=>$controller->update($data)],
                'Updated'
            );
            break;

        case 'DELETE':
            $data = whitelistProductPricingInput($rawInput, ['id']);
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID is required');
            }
            ResponseFormatter::success(
                ['deleted'=>$controller->delete((int)$data['id'])],
                'Deleted'
            );
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error','product_pricing', ['error'=>$e->getMessage()]);
    $msg = ($e instanceof InvalidArgumentException) ? $e->getMessage() : 'Internal server error';
    ResponseFormatter::error($msg, 500);
}
