<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

$modelsDir = $baseDir . '/v1/models/discounts';
require_once $modelsDir . '/repositories/PdoDiscountsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountTranslationsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountScopesRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountConditionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountActionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountRedemptionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountExclusionsRepository.php';
require_once $modelsDir . '/validators/DiscountsValidator.php';
require_once $modelsDir . '/services/DiscountsService.php';
require_once $modelsDir . '/services/DiscountSubResourceService.php';
require_once $modelsDir . '/controllers/DiscountSubResourceController.php';

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $subResourceService = new DiscountSubResourceService($pdo);
    $controller = new DiscountSubResourceController($subResourceService);
    $method     = $_SERVER['REQUEST_METHOD'];

    $pathParts = explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));
    $urlId = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'discount_redemptions' && isset($pathParts[$i + 1]) && ctype_digit($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = min(200, max(1, (int)($_GET['limit'] ?? 25)));
    $offset  = (int)($_GET['offset'] ?? (($page - 1) * $limit));

    switch ($method) {
        case 'GET':
            $discountId = (int)($_GET['discount_id'] ?? 0);
            if ($discountId <= 0) {
                ResponseFormatter::error('discount_id is required', 400);
                break;
            }
            ResponseFormatter::success($controller->listRedemptions($discountId, $limit, $offset));
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $data = array_intersect_key($data, array_flip([
                'discount_id', 'user_id', 'order_id', 'amount_discounted', 'currency_code'
            ]));

            $validation = DiscountsValidator::validateRedemption($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }
            $id = $controller->createRedemption($data);
            ResponseFormatter::success(['id' => $id], 'Redemption created', 201);
            break;

        case 'DELETE':
            $id = (int)($urlId ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required', 400);
                break;
            }
            $controller->deleteRedemption($id);
            ResponseFormatter::success(null, 'Redemption deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'discount_redemptions.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\PDOException $e) {
    safe_log('critical', 'discount_redemptions.db_error', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error', 500);
} catch (\RuntimeException $e) {
    safe_log('error', 'discount_redemptions.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
}
