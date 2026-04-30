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

$modelsPath = API_VERSION_PATH . '/models/country_taxes';
require_once $modelsPath . '/repositories/PdoCountryTaxesRepository.php';
require_once $modelsPath . '/validators/CountryTaxesValidator.php';
require_once $modelsPath . '/services/CountryTaxesService.php';
require_once $modelsPath . '/controllers/CountryTaxesController.php';

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

$user     = $_SESSION['user'] ?? [];
$tenantId = resolve_tenant_id();

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$repo       = new PdoCountryTaxesRepository($pdo);
$validator  = new CountryTaxesValidator();
$service    = new CountryTaxesService($repo, $validator);
$controller = new CountryTaxesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = $raw ? (json_decode($raw, true) ?? []) : [];

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    $filters = [
        'id'        => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'language'  => $language,
        'tenant_id' => $tenantId,
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (str_contains($uri, '/country_taxes/by_country')) {
                $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
                if ($countryId <= 0) {
                    throw new \InvalidArgumentException('Valid country_id is required');
                }
                ResponseFormatter::success($controller->getByCountry($countryId));
            } elseif (str_contains($uri, '/country_taxes/by_tax_class')) {
                $taxClassId = isset($_GET['tax_class_id']) ? (int)$_GET['tax_class_id'] : 0;
                if ($taxClassId <= 0) {
                    throw new \InvalidArgumentException('Valid tax_class_id is required');
                }
                ResponseFormatter::success($controller->getByTaxClass($taxClassId));
            } else {
                $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
                if ($id) {
                    ResponseFormatter::success($controller->get($id));
                } else {
                    ResponseFormatter::success($controller->list());
                }
            }
            break;

        case 'POST':
            $data = array_intersect_key($data, array_flip(['country_id', 'tax_class_id', 'tax_name', 'tax_name_ar', 'tax_type', 'tax_rate', 'is_inclusive', 'is_active', 'effective_date']));
            ResponseFormatter::success($controller->create($data));
            break;

        case 'PUT':
            $data = array_intersect_key($data, array_flip(['country_id', 'tax_class_id', 'tax_name', 'tax_name_ar', 'tax_type', 'tax_rate', 'is_inclusive', 'is_active', 'effective_date'])) + (isset($data['id']) ? ['id' => $data['id']] : []);
            ResponseFormatter::success($controller->update($data));
            break;

        case 'DELETE':
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'country_taxes.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'country_taxes.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('critical', 'country_taxes.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}