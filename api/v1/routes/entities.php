<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
if (file_exists(dirname(__DIR__, 3) . '/admin/includes/admin_context.php')) {
    require_once dirname(__DIR__, 3) . '/admin/includes/admin_context.php';
}
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';
require_once $baseDir . '/shared/core/TenantContext.php';
require_once $baseDir . '/shared/core/BaseRepository.php';

$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitiesRepository.php';
require_once $modelsPath . '/repositories/PdoEntityTypesRepository.php';
require_once $modelsPath . '/validators/EntitiesValidator.php';
require_once $modelsPath . '/services/EntitiesService.php';
require_once $modelsPath . '/controllers/EntitiesController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoEntitiesRepository($pdo);
$service    = new EntitiesService($repo);
$controller = new EntitiesController($service);
$validator  = null;

function entities_fail(string $message, int $status): void
{
    ResponseFormatter::error($message, $status);
    exit;
}

function entities_read_json_body(string $method): array
{
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        return [];
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        entities_fail('Invalid JSON request body', 400);
    }

    return $data;
}

function entities_positive_int(mixed $value, string $field): int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) {
        return (int)$value;
    }
    entities_fail("Field '{$field}' must be a positive integer", 422);
}

function entities_optional_positive_int(mixed $value, string $field): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return entities_positive_int($value, $field);
}

function entities_allowed_fields(array $data, bool $isPlatformAdmin, ?array $existing = null): array
{
    $allowed = [
        'id', 'user_id', 'store_name', 'slug', 'parent_id', 'branch_code',
        'vendor_type', 'store_type', 'registration_number', 'tax_number',
        'phone', 'mobile', 'email', 'website_url', 'timezone_id',
        'translations',
    ];

    $clean = array_intersect_key($data, array_flip($allowed));

    if (isset($clean['id'])) {
        $clean['id'] = entities_positive_int($clean['id'], 'id');
    }
    if (isset($clean['user_id'])) {
        $clean['user_id'] = entities_positive_int($clean['user_id'], 'user_id');
    }
    if (array_key_exists('parent_id', $clean)) {
        $clean['parent_id'] = entities_optional_positive_int($clean['parent_id'], 'parent_id');
    }
    if (array_key_exists('timezone_id', $clean)) {
        $clean['timezone_id'] = entities_optional_positive_int($clean['timezone_id'], 'timezone_id');
    }

    if ($isPlatformAdmin) {
        foreach (['status', 'is_verified', 'suspension_reason'] as $field) {
            if (array_key_exists($field, $data)) {
                $clean[$field] = $data[$field];
            }
        }
    } elseif ($existing !== null) {
        $clean['status'] = $existing['status'] ?? 'pending';
        $clean['is_verified'] = (int)($existing['is_verified'] ?? 0);
        $clean['suspension_reason'] = $existing['suspension_reason'] ?? null;
    } else {
        $clean['status'] = 'pending';
        $clean['is_verified'] = 0;
        $clean['suspension_reason'] = null;
    }

    return $clean;
}

function entities_can_view(): bool
{
    return is_platform_admin()
        || can('entities.manage')
        || can_view_all('entities')
        || can_view_tenant('entities')
        || can_view_own('entities')
        || can_create('entities');
}

function entities_can_create(): bool
{
    return is_platform_admin() || can('entities.manage') || can_create('entities');
}

function entities_can_edit(array $entity): bool
{
    $userId = (int)(get_user_id() ?? 0);
    return is_platform_admin()
        || can('entities.manage')
        || can_edit_all('entities')
        || (can_edit_own('entities') && (int)($entity['user_id'] ?? 0) === $userId);
}

function entities_can_delete(array $entity): bool
{
    $userId = (int)(get_user_id() ?? 0);
    return is_platform_admin()
        || can('entities.manage')
        || can_delete_all('entities')
        || (can_delete_own('entities') && (int)($entity['user_id'] ?? 0) === $userId);
}

function entities_user_can_read_row(array $entity): bool
{
    $userId = (int)(get_user_id() ?? 0);
    return is_platform_admin()
        || can('entities.manage')
        || can_view_all('entities')
        || can_view_tenant('entities')
        || (can_view_own('entities') && (int)($entity['user_id'] ?? 0) === $userId);
}

function entities_safe_lang(mixed $lang): string
{
    $lang = is_string($lang) ? trim($lang) : 'en';
    return preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $lang) ? $lang : 'en';
}

$userId = get_user_id();
if ($userId === null || $userId <= 0) {
    entities_fail('Unauthorized', 401);
}

$isPlatformAdmin = is_platform_admin();
if ($isPlatformAdmin && isset($_GET['tenant_id']) && !preg_match('/^[1-9][0-9]*$/', (string)$_GET['tenant_id'])) {
    entities_fail("Field 'tenant_id' must be a positive integer", 422);
}
if ($isPlatformAdmin && !isset($_GET['tenant_id'])) {
    entities_fail("Field 'tenant_id' is required for platform admin entity access", 400);
}
$tenantId = resolve_tenant_id();
if ($tenantId === null || $tenantId <= 0) {
    entities_fail('A valid tenant context is required', 400);
}

if (!$isPlatformAdmin && isset($_GET['tenant_id'])) {
    safe_log('warning', 'entities.tenant_override_ignored', [
        'user_id' => $userId,
    ]);
}

TenantContext::set($tenantId);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $data   = entities_read_json_body($method);

    if (isset($data['tenant_id'])) {
        safe_log('warning', 'entities.body_tenant_id_ignored', [
            'user_id' => $userId,
            'platform_admin' => $isPlatformAdmin,
        ]);
        unset($data['tenant_id']);
    }

    $lang      = entities_safe_lang($_GET['lang'] ?? 'en');
    $page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit     = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset    = ($page - 1) * $limit;
    $orderBy   = is_string($_GET['order_by'] ?? null) ? $_GET['order_by'] : 'id';
    $orderDir  = is_string($_GET['order_dir'] ?? null) ? $_GET['order_dir'] : 'DESC';

    $status = $_GET['status'] ?? null;
    $filters = [
        'user_id'     => isset($_GET['user_id']) ? entities_positive_int($_GET['user_id'], 'user_id') : null,
        'status'      => is_string($status) && $status !== '' ? $status : null,
        'vendor_type' => is_string($_GET['vendor_type'] ?? null) ? trim((string)$_GET['vendor_type']) : null,
        'store_type'  => is_string($_GET['store_type'] ?? null) ? trim((string)$_GET['store_type']) : null,
        'is_verified' => isset($_GET['is_verified']) && $_GET['is_verified'] !== '' ? (int)$_GET['is_verified'] : null,
        'parent_id'   => isset($_GET['parent_id']) ? entities_optional_positive_int($_GET['parent_id'], 'parent_id') : null,
        'search'      => isset($_GET['search']) && strlen(trim((string)$_GET['search'])) >= 2
            ? trim((string)$_GET['search'])
            : null,
    ];

    if ($filters['status'] !== null && !in_array($filters['status'], ['pending', 'approved', 'suspended', 'rejected'], true)) {
        entities_fail("Field 'status' has invalid value", 422);
    }
    if ($filters['store_type'] !== null && !in_array($filters['store_type'], ['individual', 'company', 'brand'], true)) {
        entities_fail("Field 'store_type' has invalid value", 422);
    }
    if ($filters['is_verified'] !== null && !in_array($filters['is_verified'], [0, 1], true)) {
        entities_fail("Field 'is_verified' has invalid value", 422);
    }

    if (!$isPlatformAdmin && !can_view_all('entities') && !can_view_tenant('entities')) {
        $filters['user_id'] = (int)$userId;
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (!entities_can_view()) {
                entities_fail('Forbidden', 403);
            }

            if (isset($_GET['validate_parent'])) {
                $parentId = entities_positive_int($_GET['validate_parent'], 'validate_parent');
                $parent = $controller->get($tenantId, $parentId, $lang);
                ResponseFormatter::success([
                    'valid' => $parent !== null && entities_user_can_read_row($parent),
                    'parent' => $parent ? [
                        'id' => $parent['id'],
                        'store_name' => $parent['store_name'],
                        'branch_code' => $parent['branch_code'] ?? null,
                    ] : null,
                ]);
                break;
            }

            if (isset($_GET['id'])) {
                $id = entities_positive_int($_GET['id'], 'id');
                $item = $controller->get($tenantId, $id, $lang);
                if (!$item) {
                    entities_fail('Entity not found', 404);
                }
                if (!entities_user_can_read_row($item)) {
                    entities_fail('Forbidden', 403);
                }
                ResponseFormatter::success($item);
                break;
            }

            $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
            $total = $result['total'];
            ResponseFormatter::success([
                'items' => $result['items'],
                'meta'  => [
                    'total'       => $total,
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                    'from'        => $total > 0 ? $offset + 1 : 0,
                    'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                ],
            ]);
            break;

        case 'POST':
            if (!entities_can_create()) {
                entities_fail('Forbidden', 403);
            }

            $clean = entities_allowed_fields($data, $isPlatformAdmin);
            if (!$isPlatformAdmin) {
                $clean['user_id'] = (int)$userId;
            }

            if (!$validator instanceof EntitiesValidator) {
                $entityTypesRepo = new PdoEntityTypesRepository($pdo);
                $allEntityTypes  = $entityTypesRepo->all(null, null, [], 'code', 'ASC');
                $validator = new EntitiesValidator(array_column($allEntityTypes, 'code'));
            }
            $validator->validate($clean, false);

            if (!empty($clean['parent_id'])) {
                $parent = $controller->get($tenantId, (int)$clean['parent_id'], $lang);
                if (!$parent || !entities_user_can_read_row($parent)) {
                    entities_fail('Parent entity not found', 422);
                }
            }

            $newId = $controller->save($tenantId, $clean, $lang);

            try {
                SeoAutoManager::sync($pdo, 'entity', (int)$newId, [
                    'name'        => $clean['store_name'] ?? '',
                    'slug'        => $clean['slug'] ?? '',
                    'description' => $clean['description'] ?? '',
                    'tenant_id'   => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'entity', (int)$newId);
            } catch (ApplicationException|\RuntimeException $e) {
                safe_log('warning', 'entities.seo_sync_create_failed', ['entity_id' => $newId]);
            }

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            $id = entities_positive_int($data['id'] ?? null, 'id');
            $existing = $controller->get($tenantId, $id, $lang);
            if (!$existing) {
                entities_fail('Entity not found', 404);
            }
            if (!entities_can_edit($existing)) {
                entities_fail('Forbidden', 403);
            }

            $clean = entities_allowed_fields($data, $isPlatformAdmin, $existing);
            $clean['id'] = $id;
            $clean = array_merge([
                'user_id' => (int)($existing['user_id'] ?? $userId),
                'store_name' => $existing['original_store_name'] ?? $existing['store_name'] ?? null,
                'slug' => $existing['slug'] ?? null,
                'parent_id' => $existing['parent_id'] ?? null,
                'branch_code' => $existing['branch_code'] ?? null,
                'vendor_type' => $existing['vendor_type'] ?? null,
                'store_type' => $existing['store_type'] ?? null,
                'registration_number' => $existing['registration_number'] ?? null,
                'tax_number' => $existing['tax_number'] ?? null,
                'phone' => $existing['phone'] ?? null,
                'mobile' => $existing['mobile'] ?? null,
                'email' => $existing['email'] ?? null,
                'website_url' => $existing['website_url'] ?? null,
                'timezone_id' => $existing['timezone_id'] ?? null,
                'status' => $existing['status'] ?? 'pending',
                'is_verified' => (int)($existing['is_verified'] ?? 0),
                'suspension_reason' => $existing['suspension_reason'] ?? null,
            ], $clean);
            $clean['id'] = $id;
            if (!$isPlatformAdmin) {
                $clean['user_id'] = (int)($existing['user_id'] ?? $userId);
            }

            if (!$validator instanceof EntitiesValidator) {
                $entityTypesRepo = new PdoEntityTypesRepository($pdo);
                $allEntityTypes  = $entityTypesRepo->all(null, null, [], 'code', 'ASC');
                $validator = new EntitiesValidator(array_column($allEntityTypes, 'code'));
            }
            $validator->validate($clean, true);

            if (!empty($clean['parent_id'])) {
                $parent = $controller->get($tenantId, (int)$clean['parent_id'], $lang);
                if (!$parent || !entities_user_can_read_row($parent)) {
                    entities_fail('Parent entity not found', 422);
                }
            }

            $updatedId = $controller->save($tenantId, $clean, $lang);

            try {
                SeoAutoManager::sync($pdo, 'entity', (int)$updatedId, [
                    'name'        => $clean['store_name'] ?? $existing['store_name'] ?? '',
                    'slug'        => $clean['slug'] ?? $existing['slug'] ?? '',
                    'description' => $clean['description'] ?? '',
                    'tenant_id'   => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'entity', (int)$updatedId);
            } catch (ApplicationException|\RuntimeException $e) {
                safe_log('warning', 'entities.seo_sync_update_failed', ['entity_id' => $updatedId]);
            }

            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            $id = entities_positive_int($data['id'] ?? ($_GET['id'] ?? null), 'id');
            $existing = $controller->get($tenantId, $id, $lang);
            if (!$existing) {
                entities_fail('Entity not found', 404);
            }
            if (!entities_can_delete($existing)) {
                entities_fail('Forbidden', 403);
            }

            $deleted = $controller->delete($tenantId, $id);

            try {
                SeoAutoManager::delete($pdo, 'entity', $id);
            } catch (ApplicationException|\RuntimeException $e) {
                safe_log('warning', 'entities.seo_delete_failed', ['entity_id' => $id]);
            }

            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'entities.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (DatabaseException|\PDOException $e) {
    safe_log('error', 'entities.db_error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    ResponseFormatter::error('Database error', 500);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error', 'entities.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Request could not be completed', 400);
} catch (\Throwable $e) {
    safe_log('critical', 'entities.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('Internal Server Error', 500);
}
