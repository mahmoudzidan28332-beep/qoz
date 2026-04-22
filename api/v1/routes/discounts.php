<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');
date_default_timezone_set('Asia/Riyadh');

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

require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountTranslationsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountScopesRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountConditionsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountActionsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountRedemptionsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountExclusionsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/validators/DiscountsValidator.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/services/DiscountsService.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/controllers/DiscountsController.php';

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

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw      = file_get_contents('php://input');
$data     = $raw ? (json_decode($raw, true) ?? []) : [];

$page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
$limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
$offset   = ($page - 1) * $limit;
$orderBy  = $_GET['order_by']  ?? 'id';
$orderDir = $_GET['order_dir'] ?? 'DESC';
$language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathInfo   = parse_url($requestUri, PHP_URL_PATH);
$pathParts  = explode('/', trim($pathInfo, '/'));
$urlId      = null;
foreach ($pathParts as $i => $part) {
    if ($part === 'discounts' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
        $urlId = (int)$pathParts[$i + 1];
        break;
    }
}

$filters = [];
if (isset($_GET['entity_id'])) $filters['entity_id'] = $_GET['entity_id'];
if (isset($_GET['status']))    $filters['status']    = $_GET['status'];
if (isset($_GET['type']))      $filters['type']      = $_GET['type'];
if (isset($_GET['search']))    $filters['search']    = $_GET['search'];
$filters['language']  = $language;
$filters['tenant_id'] = $tenantId;

try {
    $service    = new DiscountsService($pdo);
    $controller = new DiscountsController($service);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['stats'])) {
                $stats = $controller->discountStats();
                ResponseFormatter::success($stats);
                break;
            }
            $id = $urlId ?? ((isset($_GET['id']) && (int)$_GET['id'] > 0) ? (int)$_GET['id'] : null);
            if ($id !== null) {
                $item = $controller->getDiscount($id);
                if (!$item) { ResponseFormatter::error('Discount not found', 404); exit; }
                ResponseFormatter::success($item);
            } else {
                $result = $controller->listDiscounts($limit, $offset, $filters, 'created_at', 'DESC');
                ResponseFormatter::success(['data' => $result['items'], 'total' => $result['meta']['total'], 'limit' => $limit, 'offset' => $offset]);
            }
            break;

        case 'POST':
            $data = array_intersect_key($data, array_flip(['entity_id', 'type', 'code', 'auto_apply', 'priority', 'is_stackable', 'currency_code', 'max_redemptions', 'max_redemptions_per_user', 'current_redemptions', 'starts_at', 'ends_at', 'status', 'created_by']));
            $validation = DiscountsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                exit;
            }
            $id = $controller->createDiscount($data);
            ResponseFormatter::success(['id' => $id], 'Discount created', 201);
            break;

        case 'PUT':
            $id = (int)($data['id'] ?? $_GET['id'] ?? $urlId ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); exit; }
            $validation = DiscountsValidator::validateUpdate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                exit;
            }
            $controller->updateDiscount($id, $data);
            ResponseFormatter::success(null, 'Discount updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? $urlId ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); exit; }
            $controller->deleteDiscount($id);
            ResponseFormatter::success(null, 'Discount deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'discounts.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'discounts.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'discounts.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
