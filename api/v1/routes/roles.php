<?php
declare(strict_types=1);

// api/routes/roles.php

// ===== مسار api =====
$baseDir = dirname(__DIR__, 2);

// ===== تحميل ResponseFormatter =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';

// ===== تحميل safe_helpers (لـ safe_log) =====
require_once $baseDir . '/shared/helpers/safe_helpers.php';

// ===== تحميل قاعدة البيانات =====
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات roles =====
require_once API_VERSION_PATH . '/models/permissions/repositories/PdoRolesRepository.php';
require_once API_VERSION_PATH . '/models/permissions/validators/RolesValidator.php';
require_once API_VERSION_PATH . '/models/permissions/services/RolesService.php';
require_once API_VERSION_PATH . '/models/permissions/controllers/RolesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== احصل على tenantId =====
$tenantId = resolve_tenant_id();

// ===== تحميل بيانات المستخدم الحالي =====
$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? [];
$permissions = $user['permissions'] ?? [];

// ===== تحقق إذا كان super_admin =====
$isSuperAdmin = in_array('super_admin', $roles, true);

// إذا لم يكن super_admin، تحقق من أنه يملك الوصول لهذا الـ tenant
if (!$isSuperAdmin && $tenantId !== ($_SESSION['tenant_id'] ?? 0)) {
    ResponseFormatter::error('Unauthorized for this tenant', 403);
    return;
}

// ===== إنشاء الاعتمادات =====
$repo      = new PdoRolesRepository($pdo);
$validator = new RolesValidator();
$service   = new RolesService($repo, $validator);
$controller = new RolesController($service);

// ===== توجيه الطلب =====
try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            ResponseFormatter::success(
                $controller->list($tenantId)
            );
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            // 🔒 SECURITY: Mass Assignment Filtering
            $allowed = ['key_name', 'display_name'];
            $filtered = array_intersect_key($data, array_flip($allowed));

            ResponseFormatter::success(
                $controller->create($tenantId, $filtered, (int)($user['id'] ?? 0))
            );
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                break;
            }

            // 🔒 SECURITY: Verify ownership before update (IDOR Protection)
            if (class_exists('MultiTenantValidator')) {
                if (!MultiTenantValidator::checkOwnership($pdo, 'roles', (int)$data['id'], $tenantId)) {
                    ResponseFormatter::error('Role not found or unauthorized', 404);
                    break;
                }
            }

            // 🔒 SECURITY: Mass Assignment Filtering
            $allowed = ['id', 'key_name', 'display_name'];
            $filtered = array_intersect_key($data, array_flip($allowed));

            ResponseFormatter::success(
                $controller->update($tenantId, $filtered, (int)($user['id'] ?? 0))
            );
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                break;
            }

            // 🔒 SECURITY: Verify ownership before delete (IDOR Protection)
            if (class_exists('MultiTenantValidator')) {
                if (!MultiTenantValidator::checkOwnership($pdo, 'roles', (int)$data['id'], $tenantId)) {
                    ResponseFormatter::error('Role not found or unauthorized', 404);
                    break;
                }
            }

            $controller->delete($tenantId, $data, (int)($user['id'] ?? 0));
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Roles route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}

