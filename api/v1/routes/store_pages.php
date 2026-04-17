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
require_once API_VERSION_PATH . '/models/store_pages/repositories/PdoStorePagesRepository.php';
require_once API_VERSION_PATH . '/models/store_pages/validators/StorePagesValidator.php';
require_once API_VERSION_PATH . '/models/store_pages/services/StorePagesService.php';
require_once API_VERSION_PATH . '/models/store_pages/controllers/StorePagesController.php';

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

    // Tenant + User
    $tenantId = (int)($_SESSION['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
    $userId   = isset($_SESSION['user_id'])
        ? (int)$_SESSION['user_id']
        : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : null);

    // Wiring
    $repo = new PdoStorePagesRepository($pdo);
    $controller = new StorePagesController(
        new StorePagesService(
            $repo,
            new StorePagesValidator()
        )
    );

    switch ($method) {

        // =====================================================
        // GET
        // =====================================================
        case 'GET':

            // GET ?section_id=Y&translations → getSection with all translations
            if (isset($_GET['section_id']) && isset($_GET['translations'])) {
                $sectionId = (int)$_GET['section_id'];
                // Look up page_id from the section itself
                $sectionRow = $controller->findSectionByIdOnly($sectionId);
                if ($sectionRow) {
                    $fullSection = $controller->findSection((int)$sectionRow['page_id'], $sectionId, 'en', true);
                    if ($fullSection) {
                        // Parse settings JSON if needed
                        if (isset($fullSection['settings']) && is_string($fullSection['settings'])) {
                            $decoded = json_decode($fullSection['settings'], true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $fullSection['settings'] = $decoded;
                            }
                        }
                        ResponseFormatter::success($fullSection);
                        break;
                    }
                }
                // Fallback: return just translations
                ResponseFormatter::success($controller->getSectionTranslations($sectionId));
                break;
            }

            // GET ?page_id=X&section_id=Y → getSection
            if (isset($_GET['page_id']) && isset($_GET['section_id'])) {
                $pageId    = (int)$_GET['page_id'];
                $sectionId = (int)$_GET['section_id'];
                ResponseFormatter::success($controller->getSection($pageId, $sectionId));
                break;
            }

            // GET ?page_id=X&sections → listSections
            if (isset($_GET['page_id']) && isset($_GET['sections'])) {
                $pageId = (int)$_GET['page_id'];
                ResponseFormatter::success($controller->listSections($pageId));
                break;
            }

            // GET ?type=X&tenant_id=Y → getPageByType
            if (isset($_GET['type']) && $tenantId > 0) {
                $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
                ResponseFormatter::success($controller->getPageByType($tenantId, $_GET['type'], $entityId));
                break;
            }

            // GET ?id=X → getPage
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                ResponseFormatter::success($controller->getPage($tenantId, (int)$_GET['id']));
                break;
            }

            // GET ?tenant_id=Y → listPages
            if ($tenantId > 0) {
                $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
                ResponseFormatter::success($controller->listPages($tenantId, $entityId));
                break;
            }

            ResponseFormatter::error('tenant_id is required', 400);
            break;

        // =====================================================
        // POST
        // =====================================================
        case 'POST':
            $rawBody = (string)file_get_contents('php://input');
            $data    = json_decode($rawBody, true);
            if ($rawBody !== '' && $data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseFormatter::error('Invalid JSON in request body', 400);
                break;
            }
            $data   = $data ?? [];
            $target = $_GET['target'] ?? '';

            // POST ?target=translations → saveSectionTranslations
            if ($target === 'translations') {
                $sectionId    = (int)($data['section_id'] ?? $_GET['section_id'] ?? 0);
                $translations = $data['translations'] ?? $data;

                if ($sectionId <= 0) {
                    ResponseFormatter::error('section_id is required', 400);
                    break;
                }

                $controller->saveSectionTranslations($sectionId, $translations);
                ResponseFormatter::success(
                    $controller->getSectionTranslations($sectionId),
                    'Translations saved successfully'
                );
                break;
            }

            // POST ?target=reorder → reorderSections
            if ($target === 'reorder') {
                $pageId    = (int)($data['page_id'] ?? $_GET['page_id'] ?? 0);
                $positions = $data['positions'] ?? [];

                if ($pageId <= 0) {
                    ResponseFormatter::error('page_id is required', 400);
                    break;
                }

                $controller->reorderSections($pageId, $positions);
                ResponseFormatter::success(null, 'Sections reordered successfully');
                break;
            }

            // POST ?target=section → createSection
            if ($target === 'section') {
                $pageId = (int)($data['page_id'] ?? $_GET['page_id'] ?? 0);
                if ($pageId <= 0) {
                    ResponseFormatter::error('page_id is required', 400);
                    break;
                }

                ResponseFormatter::success(
                    $controller->createSection($pageId, $data, $userId),
                    'Section created successfully',
                    201
                );
                break;
            }

            // POST ?target=page → createPage (default)
            if ($target === 'page' || $target === '') {
                if ($tenantId <= 0) {
                    ResponseFormatter::error('tenant_id is required', 400);
                    break;
                }

                ResponseFormatter::success(
                    $controller->createPage($tenantId, $data, $userId),
                    'Page created successfully',
                    201
                );
                break;
            }

            ResponseFormatter::error('Invalid target: ' . $target, 400);
            break;

        // =====================================================
        // PUT
        // =====================================================
        case 'PUT':
            $rawBody = (string)file_get_contents('php://input');
            $data    = json_decode($rawBody, true);
            if ($rawBody !== '' && $data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseFormatter::error('Invalid JSON in request body', 400);
                break;
            }
            $data   = $data ?? [];
            $target = $_GET['target'] ?? '';

            // PUT ?target=section → updateSection
            if ($target === 'section') {
                $pageId = (int)($data['page_id'] ?? $_GET['page_id'] ?? 0);
                if ($pageId <= 0) {
                    ResponseFormatter::error('page_id is required', 400);
                    break;
                }

                ResponseFormatter::success(
                    $controller->updateSection($pageId, $data, $userId),
                    'Section updated successfully'
                );
                break;
            }

            // PUT ?target=page → updatePage (default)
            if ($target === 'page' || $target === '') {
                if ($tenantId <= 0) {
                    ResponseFormatter::error('tenant_id is required', 400);
                    break;
                }

                ResponseFormatter::success(
                    $controller->updatePage($tenantId, $data, $userId),
                    'Page updated successfully'
                );
                break;
            }

            ResponseFormatter::error('Invalid target: ' . $target, 400);
            break;

        // =====================================================
        // DELETE
        // =====================================================
        case 'DELETE':
            $target = $_GET['target'] ?? '';

            // DELETE ?target=section&page_id=X&section_id=Y → deleteSection
            if ($target === 'section') {
                $pageId    = (int)($_GET['page_id'] ?? 0);
                $sectionId = (int)($_GET['section_id'] ?? 0);

                if ($pageId <= 0 || $sectionId <= 0) {
                    ResponseFormatter::error('page_id and section_id are required', 400);
                    break;
                }

                $controller->deleteSection($pageId, $sectionId, $userId);
                ResponseFormatter::success(['deleted' => true], 'Section deleted successfully');
                break;
            }

            // DELETE ?target=page&id=X → deletePage
            if ($target === 'page' || $target === '') {
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) {
                    ResponseFormatter::error('Page ID is required', 400);
                    break;
                }

                if ($tenantId <= 0) {
                    ResponseFormatter::error('tenant_id is required', 400);
                    break;
                }

                $controller->deletePage($tenantId, $id, $userId);
                ResponseFormatter::success(['deleted' => true], 'Page deleted successfully');
                break;
            }

            ResponseFormatter::error('Invalid target: ' . $target, 400);
            break;

        // =====================================================
        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[StorePages] Validation failed', [
        'tenant_id' => $tenantId ?? 0,
        'method'    => $method ?? '',
        'error'     => $e->getMessage(),
    ]);
    $decoded = json_decode($e->getMessage(), true);
    ResponseFormatter::error($decoded ?? $e->getMessage(), 422);

} catch (RuntimeException $e) {
    $code = in_array((int)$e->getCode(), [400, 404, 409, 422], true)
        ? (int)$e->getCode() : 400;
    ResponseFormatter::error($e->getMessage(), $code);

} catch (PDOException $e) {
    safe_log('error', '[StorePages] Database error', [
        'tenant_id' => $tenantId ?? 0,
        'method'    => $method ?? '',
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ]);
    ResponseFormatter::error('A database error occurred. Please try again later.', 500);

} catch (Throwable $e) {
    safe_log('error', '[StorePages] Unexpected error', [
        'tenant_id' => $tenantId ?? 0,
        'method'    => $method ?? '',
        'error'     => $e->getMessage(),
        'trace'     => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('An unexpected error occurred. Please try again later.', 500);
}