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

$modelsPath = API_VERSION_PATH . '/models/auctions';
require_once $modelsPath . '/Contracts/AuctionsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAuctionsRepository.php';
require_once $modelsPath . '/validators/AuctionsValidator.php';
require_once $modelsPath . '/services/AuctionsService.php';
require_once $modelsPath . '/controllers/AuctionsController.php';

$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

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

$repo       = new PdoAuctionsRepository($pdo);
$service    = new AuctionsService($repo);
$controller = new AuctionsController($service);

try {
    $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $language = $_GET['language']  ?? $_GET['lang'] ?? 'ar';

    // URL ID parser
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $pathInfo   = parse_url($requestUri, PHP_URL_PATH);
    $pathParts  = explode('/', trim($pathInfo, '/'));
    $urlId      = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'auctions' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    $filters = [
        'status'         => $_GET['status']         ?? null,
        'auction_type'   => $_GET['auction_type']   ?? null,
        'product_id'     => $_GET['product_id']     ?? null,
        'is_featured'    => isset($_GET['is_featured']) ? (int)$_GET['is_featured'] : null,
        'condition_type' => $_GET['condition_type'] ?? null,
        'currency_id'    => isset($_GET['currency_id']) && is_numeric($_GET['currency_id'])
                                ? (int)$_GET['currency_id'] : null,
        'search'         => $_GET['search']         ?? null,
        'language'       => $language,
        'tenant_id'      => $tenantId,
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
                $item = $controller->get($tenantId, (int)$_GET['id'], $language);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $language);
                ResponseFormatter::success([
                    'data'  => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                        'from'        => $result['total'] > 0 ? $offset + 1 : 0,
                        'to'          => $result['total'] > 0 ? min($offset + $limit, $result['total']) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Auction created successfully', 201);
            break;

        case 'PUT':
            $data      = json_decode(file_get_contents('php://input'), true) ?: [];
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Auction updated successfully');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, $id);
            ResponseFormatter::success(['deleted' => $deleted], 'Auction deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'auctions.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'auctions.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'auctions.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}
