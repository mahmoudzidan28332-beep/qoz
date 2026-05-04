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

$modelsPath = API_VERSION_PATH . '/models/brands';
require_once $modelsPath . '/repositories/PdoBrandsRepository.php';
require_once $modelsPath . '/validators/BrandsValidator.php';
require_once $modelsPath . '/services/BrandsService.php';
require_once $modelsPath . '/controllers/BrandsController.php';

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
$isPlatformAdmin = is_platform_admin();
$effectiveId     = resolve_tenant_id();

// Platform Admin can override the effective ID via GET
if ($isPlatformAdmin && isset($_GET['tenant_id'])) {
    $effectiveId = (int)$_GET['tenant_id'];
}

// 🔒 SECURITY: Enforce TenantContext
TenantContext::set((int)($effectiveId ?? 0));

$repo       = new PdoBrandsRepository($pdo);
$validator  = new BrandsValidator();
$service    = new BrandsService($repo, $validator);
$controller = new BrandsController($service);

// تحليل المسار للكشف عن slug أو id
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
$slug     = null;
$id       = null;

if (count($segments) > 0) {
    $last = end($segments);
    if ($last !== 'brands' && $last !== 'active' && $last !== 'featured') {
        if (is_numeric($last)) {
            $id = (int)$last;
        } else {
            $slug = $last;
        }
    }
}

try {
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $body    = ($rawBody !== false && $rawBody !== '') ? (json_decode($rawBody, true) ?? []) : [];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if ($slug !== null) {
                ResponseFormatter::success($controller->get($slug));
            } elseif ($id !== null) {
                ResponseFormatter::success($controller->getById($id));
            } else {
                ResponseFormatter::success($controller->list());
            }
            break;

        case 'POST':
            $body['user_id'] = get_user_id();
            ResponseFormatter::success($controller->create($body), 'Created successfully', 201);
            break;

        case 'PUT':
            if ($id !== null && empty($body['id'])) {
                $body['id'] = $id;
            }
            $body['user_id'] = get_user_id();
            ResponseFormatter::success($controller->update($body), 'Updated successfully');
            break;

        case 'DELETE':
            if ($id !== null && empty($body['id'])) {
                $body['id'] = $id;
            }
            if ($slug !== null && empty($body['slug'])) {
                $body['slug'] = $slug;
            }
            $body['user_id'] = get_user_id();
            $controller->delete($body);
            ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'brands.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'brands.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (\Throwable $e) {
    safe_log('critical', 'brands.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
