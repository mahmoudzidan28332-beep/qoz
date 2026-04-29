<?php
declare(strict_types=1);

/**
 * API Route: Countries
 *
 * Supports:
 *  - GET  /api/countries                         -> list (supports filters via query: id, iso2, iso3, name, currency_code, lang, page, per_page)
 *  - GET  /api/countries/{identifier}            -> get single by id|iso2|iso3|name (identifier in path)
 *  - POST /api/countries                         -> create (JSON body)
 *  - PUT  /api/countries                         -> update (JSON body with id)
 *  - DELETE /api/countries                       -> delete (JSON body with id)
 *
 * Note: returns translation name when ?lang=xx is provided (or Accept-Language header fallback).
 */

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

$modelsPath = API_VERSION_PATH . '/models/countries';
require_once $modelsPath . '/repositories/PdoCountriesRepository.php';
require_once $modelsPath . '/validators/CountriesValidator.php';
require_once $modelsPath . '/services/CountriesService.php';
require_once $modelsPath . '/controllers/CountriesController.php';

$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$user = $_SESSION['user'] ?? [];
$tenantId = resolve_tenant_id();

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$repo = new PdoCountriesRepository($pdo);
$validator = new CountriesValidator();
$service = new CountriesService($repo, $validator);
$controller = new CountriesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    $raw = file_get_contents('php://input');
    $data = $raw ? (json_decode($raw, true) ?? []) : [];

    // Determine path suffix after /api/countries
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath = explode('?', $requestUri, 2)[0];
    $routeBase = '/api/countries';
    $after = '';
    $pos = strpos($uriPath, $routeBase);
    if ($pos !== false) {
        $after = substr($uriPath, $pos + strlen($routeBase));
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName && strpos($uriPath, $scriptName) === 0) {
            $after = substr($uriPath, strlen($scriptName));
        }
    }
    $after = trim($after, "/ \t\n\r\0\x0B");
    $segments = $after === '' ? [] : explode('/', $after);

    // Determine requested language: ?lang=xx, ?language=xx, or Accept-Language header
    $lang = $_GET['lang'] ?? $_GET['language'] ?? null;
    if (!$lang) {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($accept) {
            $parts = explode(',', $accept);
            if (!empty($parts[0])) {
                $lang = substr(trim($parts[0]), 0, 2);
            }
        }
    }
    
    // Default to English if no language specified
    if (!$lang) {
        $lang = 'en';
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (!empty($segments[0])) {
                // Get single country by identifier
                $identifier = urldecode($segments[0]);
                $item = $controller->getByIdentifier($identifier, $lang);
                if (!$item) {
                    ResponseFormatter::error('Country not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } else {
                // List countries with filters
                $listFilters = [];
                
                // Add standard filters
                foreach (['id', 'iso2', 'iso3', 'name', 'currency_code', 'page', 'per_page'] as $k) {
                    if (isset($_GET[$k])) {
                        $listFilters[$k] = $_GET[$k];
                    }
                }
                
                // Add language filter - prioritize lang parameter
                if (!empty($_GET['lang'])) {
                    $listFilters['lang'] = $_GET['lang'];
                } elseif (!empty($_GET['language'])) {
                    $listFilters['lang'] = $_GET['language'];
                } else {
                    $listFilters['lang'] = $lang;
                }
                
                // Add pagination parameters
                if (isset($_GET['page'])) {
                    $listFilters['page'] = (int)$_GET['page'];
                }
                if (isset($_GET['per_page'])) {
                    $listFilters['per_page'] = (int)$_GET['per_page'];
                }
                
                $result = $controller->list($listFilters);

                // Handle different response formats
                if (isset($result['data']) && is_array($result['data'])) {
                    $responseData = $result['data'];
                } elseif (isset($result['items']) && is_array($result['items'])) {
                    $responseData = $result['items'];
                } else {
                    $responseData = $result;
                }

                // Ensure response has proper structure
                $finalResponse = [
                    'data' => $responseData,
                    'meta' => $result['meta'] ?? [
                        'total' => is_array($responseData) ? count($responseData) : 0,
                        'page' => (int)($_GET['page'] ?? 1),
                        'per_page' => (int)($_GET['per_page'] ?? 50),
                        'pages' => 1,
                    ],
                ];

                ResponseFormatter::success($finalResponse);
            }
            break;

        case 'POST':
            // Create new country
            $result = $controller->store($data);
            ResponseFormatter::success($result, 'Country created successfully', 201);
            break;

        case 'PUT':
            // Update existing country
            $result = $controller->update($data);
            ResponseFormatter::success($result, 'Country updated successfully');
            break;

        case 'DELETE':
            // Delete country
            $result = $controller->delete($data);
            ResponseFormatter::success($result, 'Country deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'countries.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    $httpCode = in_array((int)$e->getCode(), [400, 403, 404, 422]) ? (int)$e->getCode() : 400;
    safe_log('error', 'countries.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), $httpCode);
} catch (Throwable $e) {
    safe_log('critical', 'countries.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal server error', 500);
}