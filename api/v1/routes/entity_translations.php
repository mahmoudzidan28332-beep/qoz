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

$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntityTranslationsRepository.php';
require_once $modelsPath . '/services/EntityTranslationsService.php';
require_once $modelsPath . '/controllers/EntityTranslationsController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

function entity_translations_positive_int(mixed $value, string $field): int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) {
        return (int)$value;
    }
    ResponseFormatter::error("Field '{$field}' must be a positive integer", 422);
    exit;
}



try {
    $repo = new PdoEntityTranslationsRepository($pdo);
    $service = new EntityTranslationsService($repo);
    $controller = new EntityTranslationsController($service);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = $rawInput ? json_decode($rawInput, true) : [];
    if ($rawInput && json_last_error() !== JSON_ERROR_NONE) {
        ResponseFormatter::error('Invalid JSON request body', 400);
        exit;
    }
    if (!is_array($data)) {
        $data = [];
    }
    if ($method === 'POST' && !empty($_POST)) {
        $data = array_merge($data, $_POST);
    }

    $tenantId = resolve_tenant_id();
    if (($tenantId === null || $tenantId <= 0) && is_platform_admin()) {
        $entityIdForScope = null;
        if (isset($_GET['entity_id'])) {
            $entityIdForScope = entity_translations_positive_int($_GET['entity_id'], 'entity_id');
        } elseif (isset($data['entity_id'])) {
            $entityIdForScope = entity_translations_positive_int($data['entity_id'], 'entity_id');
        }
        if ($entityIdForScope !== null) {
            $tenantId = $controller->getTenantIdByEntityId($entityIdForScope);
        }
    }

    if ($tenantId === null || $tenantId <= 0) {
        ResponseFormatter::error('A valid tenant context is required', 400);
        exit;
    }

    switch ($method) {
        case 'GET':
            if (!isset($_GET['entity_id'])) {
                ResponseFormatter::error('entity_id is required', 400);
                break;
            }
            $entityId = entity_translations_positive_int($_GET['entity_id'], 'entity_id');
            verify_entity_ownership($pdo, $entityId, $tenantId);
            ResponseFormatter::success($controller->getByEntity($entityId, $tenantId));
            break;

        case 'POST':
        case 'PUT':
            if (empty($data['entity_id']) || empty($data['language_code'])) {
                ResponseFormatter::error('entity_id and language_code are required', 400);
                break;
            }
            $data['entity_id'] = entity_translations_positive_int($data['entity_id'], 'entity_id');
            verify_entity_ownership($pdo, $data['entity_id'], $tenantId);
            $id = $controller->save($data, $tenantId);
            ResponseFormatter::success(['id' => $id], 'Saved successfully', 201);
            break;

        case 'DELETE':
            $id = entity_translations_positive_int($data['id'] ?? ($_GET['id'] ?? null), 'id');
            if (isset($data['entity_id'])) {
                $entityId = entity_translations_positive_int($data['entity_id'], 'entity_id');
                verify_entity_ownership($pdo, $entityId, $tenantId);
            }
            $result = $controller->delete($id, $tenantId);
            ResponseFormatter::success($result, 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'entity_translations.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'entity_translations.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Request could not be completed', 400);
} catch (\Throwable $e) {
    safe_log('critical', 'entity_translations.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('Internal Server Error', 500);
}
