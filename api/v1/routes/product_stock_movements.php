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
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/validators/StockMovementsValidator.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/services/StockMovementsService.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/controllers/StockMovementsController.php';

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
    $pdo    = $GLOBALS['ADMIN_DB'];
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $repo = new PdoStockMovementsRepository($pdo);
                $filters = [];
                if (isset($_GET['product_id'])) $filters['product_id'] = $_GET['product_id'];
                if (isset($_GET['type'])) $filters['type'] = $_GET['type'];
                if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
                $stats = $repo->stats($filters);
                ResponseFormatter::success($stats);
                break;
            }

            if (isset($_GET['barcode']) && $_GET['barcode'] !== '') {
                $repo = new PdoStockMovementsRepository($pdo);
                $row = $repo->lookupByBarcode(trim($_GET['barcode']));
                if (!$row) {
                    ResponseFormatter::error('Barcode not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            if (isset($_GET['sku']) && $_GET['sku'] !== '') {
                $sku = trim($_GET['sku']);
                $lang = $_GET['lang'] ?? ($_SESSION['user']['preferred_language'] ?? 'ar');
                $repo = new PdoStockMovementsRepository($pdo);
                $row = $repo->lookupBySku($sku, $lang);
                if (!$row) {
                    ResponseFormatter::error('SKU not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $repo = new PdoStockMovementsRepository($pdo);
                $item = $repo->findWithProductName((int)$_GET['id']);
                if (!$item) { ResponseFormatter::error('Stock movement not found', 404); break; }
                ResponseFormatter::success($item);
            } elseif (isset($_GET['product_id']) && (int)$_GET['product_id'] > 0) {
                $repo = new PdoStockMovementsRepository($pdo);
                $items = $repo->getByProduct((int)$_GET['product_id']);
                ResponseFormatter::success($items);
            } else {
                $filters = [];
                if (isset($_GET['type']) && $_GET['type'] !== '')        $filters['type'] = $_GET['type'];
                if (isset($_GET['date_from']) && $_GET['date_from'] !== '') $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to']) && $_GET['date_to'] !== '')   $filters['date_to'] = $_GET['date_to'];
                if (isset($_GET['search']) && $_GET['search'] !== '')     $filters['search'] = $_GET['search'];

                $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

                $repo = new PdoStockMovementsRepository($pdo);
                $result = $repo->listPaginated($filters, $limit, $offset);

                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $validation = StockMovementsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            $repo = new PdoStockMovementsRepository($pdo);
            $id = $repo->create($data);
            ResponseFormatter::success(['id' => $id], 'Stock movement created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            $validation = StockMovementsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            // Get old record to reverse stock
            $repo = new PdoStockMovementsRepository($pdo);
            $old = $repo->find($id);
            if (!$old) { ResponseFormatter::error('Movement not found', 404); break; }

            // Reverse old stock change, update record, apply new stock change
            $repo->updateMovement($id, $data, $old);

            ResponseFormatter::success(['id' => $id], 'Stock movement updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $repo = new PdoStockMovementsRepository($pdo);
            $repo->delete($id);
            ResponseFormatter::success(null, 'Stock movement deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

