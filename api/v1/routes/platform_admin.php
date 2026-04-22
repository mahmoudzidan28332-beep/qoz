<?php
declare(strict_types=1);

/**
 * api/v1/routes/platform_admin.php
 *
 * Platform Admin Support Mode API
 *
 * OVERVIEW:
 *  This route handles all Platform Admin / Support Mode operations.
 *  It implements the PLATFORM_ADMIN execution context described in the spec:
 *
 *    - Cross-tenant data access (read, create, update, delete, restore)
 *    - Dynamic entity (model) loader — any table/entity by name
 *    - Impersonation endpoint (read-only debugging aid)
 *    - Audit log viewer for a specific tenant
 *
 * SECURITY GUARANTEES:
 *
 *   ✔  Identity: only users with 'super_admin' role (is_super_admin()) may access.
 *   ✔  Boot:    PlatformContext::bootSuperAdmin() + AuditContext::boot() on every request.
 *   ✔  Reason:  Every mutating operation requires a non-empty 'reason' field.
 *   ✔  Target:  Every operation requires an explicit 'target_tenant_id'.
 *   ✔  Audit:   AuditContext::capturePlatformAdminAction() on every action.
 *   ✔  Guard:   SecurityValidator::assertPlatformAdminIntegrity() before any operation.
 *   ✔  SQL:     All queries go through PdoPlatformAdminRepository (extends BaseRepository)
 *               which uses executeCrossTenant() — NO raw PDO.
 *   ✔  Reversibility: DELETE operations are soft-deletes (deleted_at); restore supported.
 *
 * NON-NEGOTIABLE:
 *   ❌  No silent data modification
 *   ❌  No bypass of audit logs
 *   ❌  No raw PDO usage
 *   ❌  No empty reason
 *   ❌  No unlogged cross-tenant mutation
 *
 * ENDPOINTS:
 *
 *   GET    /api/platform_admin?entity=<table>&target_tenant_id=<id>
 *              [&id=<record_id>] [&limit=N] [&offset=N] [&reason=<text>]
 *          → list records or fetch one record
 *
 *   POST   /api/platform_admin
 *          Body: { entity, target_tenant_id, reason, data: {...} }
 *          → create a new record in the target tenant
 *
 *   PUT    /api/platform_admin
 *          Body: { entity, id, target_tenant_id, reason, data: {...} }
 *          → update a record in the target tenant
 *
 *   DELETE /api/platform_admin
 *          Body: { entity, id, target_tenant_id, reason }
 *          → soft-delete a record (preferred, reversible)
 *
 *   POST   /api/platform_admin?action=restore
 *          Body: { entity, id, target_tenant_id, reason }
 *          → restore a soft-deleted record
 *
 *   POST   /api/platform_admin?action=impersonate
 *          Body: { user_id, target_tenant_id, reason }
 *          → begin a read-only impersonation session for debugging
 *
 *   GET    /api/platform_admin?action=audit&target_tenant_id=<id>
 *          [&entity=<table>] [&limit=N] [&page=N]
 *          → view audit log entries for a specific tenant
 */

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/core/TenantContext.php';
require_once $baseDir . '/shared/core/PlatformContext.php';
require_once $baseDir . '/shared/core/AuditContext.php';
require_once $baseDir . '/shared/core/QueryGuard.php';
require_once $baseDir . '/shared/core/BaseRepository.php';
require_once $baseDir . '/shared/core/SecurityValidator.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/authorize.php';
require_once $baseDir . '/shared/config/db.php';
require_once dirname(__FILE__) . '/../models/platform_admin/repositories/PdoPlatformAdminRepository.php';

// ============================================================================
// 0. Bootstrap: session, CORS, content-type
// ============================================================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key, X-Request-Id');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================================
// 1. Identity check — super_admin ONLY
// ============================================================================
if (!is_super_admin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Platform Admin routes require super_admin privileges.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// 2. Boot security layer
// ============================================================================
AuditContext::boot();

$sessionUserId = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
PlatformContext::bootSuperAdmin(is_numeric($sessionUserId) ? (int)$sessionUserId : null);

// ============================================================================
// 3. Validate integrity (no TenantContext needed for platform admin)
// ============================================================================
SecurityValidator::assertPlatformAdminIntegrity();

// ============================================================================
// 4. Database connection
// ============================================================================
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database connection not initialized', 500);
    exit;
}

$repo   = new PdoPlatformAdminRepository($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================================================
// 5. Helper: parse and validate shared required parameters
// ============================================================================

/**
 * Read JSON body and merge with GET params; return the combined array.
 */
function pa_read_input(): array
{
    $raw  = file_get_contents('php://input');
    $body = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?? []) : [];
    return array_merge($_GET, is_array($body) ? $body : []);
}

/**
 * Assert that the required fields are present and non-empty; respond 422 if not.
 *
 * @param  array    $input
 * @param  string[] $fields
 */
function pa_require_fields(array $input, array $fields): void
{
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($input[$f]) || (is_string($input[$f]) && trim($input[$f]) === '')) {
            $missing[] = $f;
        }
    }
    if (!empty($missing)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missing),
            'fields'  => $missing,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================================
// 6. Routing
// ============================================================================
try {
    switch (true) {

        // ------------------------------------------------------------------
        // GET /api/platform_admin?action=audit&target_tenant_id=X
        // View audit log entries for a specific tenant
        // ------------------------------------------------------------------
        case $method === 'GET' && $action === 'audit':
            $input = pa_read_input();
            pa_require_fields($input, ['target_tenant_id', 'reason']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $limit          = min(200, max(1, (int)($input['limit'] ?? 50)));
            $page           = max(1, (int)($input['page'] ?? 1));
            $offset         = ($page - 1) * $limit;

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            $filters = [];
            if ($entity !== '') {
                $filters['entity_type'] = $entity;
            }

            $rows = $repo->listRecords('audit_logs', $targetTenantId, $reason, $filters, $limit, $offset);

            AuditContext::capturePlatformAdminAction(
                action:       'view_audit_log',
                entityType:   'audit_logs',
                entityId:     null,
                targetTenant: $targetTenantId,
                reason:       $reason
            );

            ResponseFormatter::success([
                'items'  => $rows,
                'meta'   => [
                    'target_tenant_id' => $targetTenantId,
                    'page'             => $page,
                    'per_page'         => $limit,
                    'request_id'       => AuditContext::getRequestId(),
                ],
            ]);
            exit;

        // ------------------------------------------------------------------
        // GET /api/platform_admin?entity=<table>&target_tenant_id=X
        // List records or fetch one record from any tenant
        // ------------------------------------------------------------------
        case $method === 'GET' && $action === '':
            $input = pa_read_input();
            pa_require_fields($input, ['entity', 'target_tenant_id', 'reason']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $recordId       = isset($input['id']) && is_numeric($input['id']) ? (int)$input['id'] : null;

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            if ($recordId !== null) {
                // Fetch single record
                $row = $repo->getRecord($entity, $recordId, $targetTenantId, $reason);
                if ($row === null) {
                    ResponseFormatter::error('Record not found', 404);
                    exit;
                }
                ResponseFormatter::success([
                    'item'       => $row,
                    'meta'       => [
                        'target_tenant_id' => $targetTenantId,
                        'request_id'       => AuditContext::getRequestId(),
                    ],
                ]);
            } else {
                // List records with optional filters
                $limit   = min(200, max(1, (int)($input['limit'] ?? 50)));
                $offset  = max(0, (int)($input['offset'] ?? 0));
                $filters = [];
                if (isset($input['filters']) && is_array($input['filters'])) {
                    foreach ($input['filters'] as $col => $val) {
                        if (is_string($col) && $col !== 'tenant_id') {
                            $filters[$col] = $val;
                        }
                    }
                }

                $rows = $repo->listRecords($entity, $targetTenantId, $reason, $filters, $limit, $offset);
                ResponseFormatter::success([
                    'items' => $rows,
                    'meta'  => [
                        'target_tenant_id' => $targetTenantId,
                        'page'             => max(1, (int)(($offset / $limit) + 1)),
                        'per_page'         => $limit,
                        'request_id'       => AuditContext::getRequestId(),
                    ],
                ]);
            }
            exit;

        // ------------------------------------------------------------------
        // POST /api/platform_admin?action=restore
        // Restore a soft-deleted record
        // ------------------------------------------------------------------
        case $method === 'POST' && $action === 'restore':
            $input = pa_read_input();
            pa_require_fields($input, ['entity', 'id', 'target_tenant_id', 'reason']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $recordId       = (int)$input['id'];

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            $restored = $repo->restoreRecord($entity, $recordId, $targetTenantId, $reason);
            if (!$restored) {
                ResponseFormatter::error('Record not found or is not soft-deleted', 404);
                exit;
            }

            ResponseFormatter::success([
                'restored'   => true,
                'entity'     => $entity,
                'id'         => $recordId,
                'request_id' => AuditContext::getRequestId(),
            ], 'Record restored');
            exit;

        // ------------------------------------------------------------------
        // POST /api/platform_admin?action=impersonate
        // Begin a read-only impersonation session for debugging
        // ------------------------------------------------------------------
        case $method === 'POST' && $action === 'impersonate':
            $input = pa_read_input();
            pa_require_fields($input, ['user_id', 'target_tenant_id', 'reason']);

            $targetUserId   = (int)$input['user_id'];
            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            // Fetch the target user's session/profile for debugging — read-only.
            $userRow = $repo->getRecord('users', $targetUserId, $targetTenantId, $reason);
            if ($userRow === null) {
                ResponseFormatter::error('Target user not found in tenant', 404);
                exit;
            }

            // Strip sensitive fields before returning.
            unset($userRow['password'], $userRow['password_hash'], $userRow['remember_token']);

            AuditContext::capturePlatformAdminAction(
                action:       'impersonate',
                entityType:   'users',
                entityId:     $targetUserId,
                targetTenant: $targetTenantId,
                reason:       $reason
            );

            ResponseFormatter::success([
                'impersonation_context' => [
                    'target_user'      => $userRow,
                    'target_tenant_id' => $targetTenantId,
                    'initiated_by'     => $sessionUserId,
                    'mode'             => 'read_only',
                    'request_id'       => AuditContext::getRequestId(),
                    'warning'          => 'This is a READ-ONLY debug session. Mutations require separate platform_admin calls.',
                ],
            ]);
            exit;

        // ------------------------------------------------------------------
        // POST /api/platform_admin  (no special action)
        // Create a new record in any tenant
        // ------------------------------------------------------------------
        case $method === 'POST' && $action === '':
            $input = pa_read_input();
            pa_require_fields($input, ['entity', 'target_tenant_id', 'reason', 'data']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $data           = is_array($input['data']) ? $input['data'] : [];

            if (empty($data)) {
                ResponseFormatter::error('data field must be a non-empty object', 422);
                exit;
            }

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            $newId = $repo->createRecord($entity, $targetTenantId, $reason, $data);

            ResponseFormatter::success([
                'created'    => true,
                'entity'     => $entity,
                'id'         => $newId,
                'request_id' => AuditContext::getRequestId(),
            ], 'Record created', 201);
            exit;

        // ------------------------------------------------------------------
        // PUT /api/platform_admin
        // Update an existing record in any tenant
        // ------------------------------------------------------------------
        case $method === 'PUT':
            $input = pa_read_input();
            pa_require_fields($input, ['entity', 'id', 'target_tenant_id', 'reason', 'data']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $recordId       = (int)$input['id'];
            $data           = is_array($input['data']) ? $input['data'] : [];
            $oldData        = isset($input['old_data']) && is_array($input['old_data']) ? $input['old_data'] : [];

            if (empty($data)) {
                ResponseFormatter::error('data field must be a non-empty object', 422);
                exit;
            }

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            // If old_data not provided by caller, fetch it first for the audit trail.
            if (empty($oldData)) {
                $oldData = $repo->getRecord($entity, $recordId, $targetTenantId, $reason) ?? [];
            }

            $updated = $repo->updateRecord($entity, $recordId, $targetTenantId, $reason, $data, $oldData);
            if (!$updated) {
                ResponseFormatter::error('Record not found or no changes detected', 404);
                exit;
            }

            ResponseFormatter::success([
                'updated'    => true,
                'entity'     => $entity,
                'id'         => $recordId,
                'request_id' => AuditContext::getRequestId(),
            ], 'Record updated');
            exit;

        // ------------------------------------------------------------------
        // DELETE /api/platform_admin
        // Soft-delete a record (preferred — reversible)
        // ------------------------------------------------------------------
        case $method === 'DELETE':
            $input = pa_read_input();
            pa_require_fields($input, ['entity', 'id', 'target_tenant_id', 'reason']);

            $targetTenantId = (int)$input['target_tenant_id'];
            $reason         = trim((string)($input['reason'] ?? ''));
            $entity         = trim((string)($input['entity'] ?? ''));
            $recordId       = (int)$input['id'];

            PlatformContext::beginSupportSession($targetTenantId, $reason);

            // Fetch old_data before deletion for audit trail.
            $oldData = $repo->getRecord($entity, $recordId, $targetTenantId, $reason) ?? [];

            $deleted = $repo->softDeleteRecord($entity, $recordId, $targetTenantId, $reason, $oldData);
            if (!$deleted) {
                ResponseFormatter::error('Record not found or already deleted', 404);
                exit;
            }

            ResponseFormatter::success([
                'deleted'    => true,
                'entity'     => $entity,
                'id'         => $recordId,
                'soft_delete'=> true,
                'restorable' => true,
                'request_id' => AuditContext::getRequestId(),
            ], 'Record soft-deleted (restorable)');
            exit;

        // ------------------------------------------------------------------
        // Default: unknown route
        // ------------------------------------------------------------------
        default:
            ResponseFormatter::error(
                'Platform Admin: unknown endpoint. '
                . 'Supported: GET (list/get), POST (create/restore/impersonate), PUT (update), DELETE (soft-delete).',
                404
            );
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'platform_admin.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'platform_admin.runtime', ['error' => $e->getMessage()]);
    $code = $e->getCode();
    ResponseFormatter::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (\Throwable $e) {
    error_log('[platform_admin] Fatal: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    safe_log('critical', 'platform_admin.fatal', [
        'error'      => $e->getMessage(),
        'request_id' => AuditContext::getRequestId(),
    ]);
    ResponseFormatter::error('An unexpected error occurred', 500);
}
