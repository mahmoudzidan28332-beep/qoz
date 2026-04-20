<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';
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

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Session
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database connection
if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $service    = new DiscountsService($pdo);
    $controller = new DiscountsController($service);
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $stats = $controller->discountStats();
                ResponseFormatter::success($stats);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $item = $controller->getDiscount((int)$_GET['id']);
                if (!$item) { ResponseFormatter::error('Discount not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['entity_id'])) $filters['entity_id'] = $_GET['entity_id'];
                if (isset($_GET['status']))    $filters['status'] = $_GET['status'];
                if (isset($_GET['type']))      $filters['type'] = $_GET['type'];
                if (isset($_GET['search']))    $filters['search'] = $_GET['search'];

                $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

                $result = $controller->listDiscounts($limit, $offset, $filters, 'created_at', 'DESC');

                ResponseFormatter::success(['items' => $result['items'], 'total' => $result['meta']['total'], 'limit' => $limit, 'offset' => $offset]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $validation = DiscountsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            $id = $controller->createDiscount($data);
            ResponseFormatter::success(['id' => $id], 'Discount created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            $validation = DiscountsValidator::validateUpdate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            $controller->updateDiscount($id, $data);
            ResponseFormatter::success(null, 'Discount updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->deleteDiscount($id);
            ResponseFormatter::success(null, 'Discount deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

