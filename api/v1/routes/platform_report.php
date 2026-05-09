<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';
$sharedPath = $baseDir . '/shared/core';
require_once $sharedPath . '/BaseRepository.php';
require_once $sharedPath . '/TenantContext.php';
require_once $sharedPath . '/QueryGuard.php';

$modelsPath = API_VERSION_PATH . '/models/platform_report';
require_once $modelsPath . '/repositories/PdoPlatformReportRepository (1).php';
require_once $modelsPath . '/validators/PlatformReportValidator.php';
require_once $modelsPath . '/services/PlatformReportService.php';
require_once $modelsPath . '/controllers/PlatformReportController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ================================
// RBAC & Tenant resolution
// ================================
$isPlatformAdmin = function_exists('is_platform_admin') && is_platform_admin();
$resolvedTenantId = resolve_tenant_id();

// Block if not platform admin and no tenant context
if (!$isPlatformAdmin && ($resolvedTenantId === null || $resolvedTenantId === 0)) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

$repo       = new PdoPlatformReportRepository($pdo);
$validator  = new PlatformReportValidator();
$service    = new PlatformReportService($repo, $validator);
$controller = new PlatformReportController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // GET /api/platform_report?action=types
            if ($action === 'types') {
                $types = $controller->getReportTypes();
                ResponseFormatter::success($types);
                exit;
            }

            // GET /api/platform_report?action=dashboard
            if ($action === 'dashboard') {
                $tenantId = $resolvedTenantId;
                $summary  = $controller->getDashboardSummary($tenantId);
                ResponseFormatter::success($summary);
                exit;
            }

            // GET /api/platform_report?action=report&report_type=X&start_date=Y&end_date=Z
            if ($action === 'report') {
                // Always derive tenant from the resolved (session-enforced) value
                $tenantId = $resolvedTenantId;

                // Entity filter: only allowed when a tenant scope is established
                $entityIdRaw = $_GET['entity_id'] ?? '';
                $entityId    = ($entityIdRaw !== '' && is_numeric($entityIdRaw)) ? (int)$entityIdRaw : '';

                $params = [
                    'report_type' => $_GET['report_type'] ?? '',
                    'start_date'  => $_GET['start_date']  ?? '',
                    'end_date'    => $_GET['end_date']    ?? '',
                    'tenant_id'   => $tenantId ?? '',
                    'entity_id'   => $entityId,
                    'period_type' => $_GET['period_type'] ?? 'daily',
                    'group_by'    => $_GET['group_by']    ?? 'day',
                ];
                $result = $controller->generateReport($params);
                if (!($result['success'] ?? false)) {
                    ResponseFormatter::error(implode('; ', $result['errors'] ?? ['Unknown error']), 422);
                } else {
                    ResponseFormatter::success($result);
                }
                exit;
            }

            // GET /api/platform_report?action=exports
            if ($action === 'exports') {
                $tenantId = $resolvedTenantId;
                $exports  = $controller->listExports($tenantId);
                ResponseFormatter::success($exports);
                exit;
            }

            // GET /api/platform_report?action=schedules
            if ($action === 'schedules') {
                $tenantId  = $resolvedTenantId;
                $schedules = $controller->listSchedules($tenantId);
                ResponseFormatter::success($schedules);
                exit;
            }

            ResponseFormatter::error('Invalid action. Use: types, dashboard, report, exports, schedules', 400);
            break;

        case 'POST':
            // POST /api/platform_report?action=export
            if ($action === 'export') {
                $allowedExportKeys = ['report_type', 'start_date', 'end_date', 'tenant_id', 'export_format'];
                $params = array_intersect_key($data, array_flip($allowedExportKeys));
                $params['tenant_id']    = $resolvedTenantId;
                $params['requested_by'] = $_SESSION['user_id'] ?? null;

                $result = $controller->requestExport($params);
                if (!($result['success'] ?? false)) {
                    ResponseFormatter::error(implode('; ', $result['errors'] ?? ['Unknown error']), 422);
                } else {
                    ResponseFormatter::success($result, 'Export requested', 201);
                }
                exit;
            }

            // POST /api/platform_report?action=schedule
            if ($action === 'schedule') {
                $allowedScheduleKeys = ['report_type', 'frequency', 'start_date', 'end_date', 'format'];
                $params = array_intersect_key($data, array_flip($allowedScheduleKeys));
                $params['tenant_id']  = $resolvedTenantId;
                $params['created_by'] = $_SESSION['user_id'] ?? null;

                $result = $controller->createSchedule($params);
                ResponseFormatter::success($result, 'Schedule created', 201);
                exit;
            }

            ResponseFormatter::error('Invalid POST action. Use: export, schedule', 400);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'platform_report.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'platform_report.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('An error occurred. Please try again.', 400);
} catch (\Throwable $e) {
    safe_log('critical', 'platform_report.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}