<?php
declare(strict_types=1);

/**
 * authorize.php
 *
 * Two global helpers that together form a lightweight permission-guard layer:
 *
 *  1. authorize(string $permission)
 *     Middleware-style check.  Terminates the request with HTTP 403 when the
 *     current user lacks $permission.  Call it at the very top of every
 *     controller action or route handler before any business logic.
 *
 *     Mapping guidance (follow consistently across all route files):
 *       GET    requests → resource.view    e.g. 'products.view'
 *       POST   requests → resource.create  e.g. 'products.create'
 *       PUT    requests → resource.edit    e.g. 'products.edit'
 *       DELETE requests → resource.delete  e.g. 'products.delete'
 *
 *  2. audit_log(array $data)
 *     Thin wrapper around AuditLogger::log() with a standard, consistent
 *     interface.  Automatically pulls tenant_id and user_id from the session
 *     when not provided in $data.
 *
 * DEPENDENCIES (must be loaded before this file):
 *  - api/shared/helpers/Response.php          (for respond() / HTTP constants)
 *  - api/shared/services/PermissionService.php
 *  - api/shared/helpers/AuditLogger.php
 *
 * This file is idempotent — double-inclusion is safe.
 */

if (!function_exists('authorize')) {
    /**
     * Enforce a named permission for the current user.
     *
     * Uses PermissionService (the canonical RBAC layer) when a PDO connection is
     * available.  Falls back to a session-based check when PDO is absent (e.g. in
     * lightweight public routes) so the application degrades gracefully.
     *
     * Super-admins bypass all permission checks — they are detected via
     * is_super_admin() which inspects $_SESSION['user']['roles'].
     *
     * @param  string $permission  A dot-notated permission key, e.g. 'orders.edit'.
     *
     * @throws never               Always calls exit() on failure.
     */
    function authorize(string $permission): void
    {
        // ── 1. Super-admin fast path ──────────────────────────────────────────
        if (function_exists('is_super_admin') && is_super_admin()) {
            // Log super-admin bypass to audit trail so elevated access is visible.
            audit_log([
                'action'      => 'super_admin_bypass',
                'description' => "Super-admin bypassed permission check for: {$permission}",
            ]);
            return; // permitted
        }

        // ── 2. Session-based shortcut (avoids DB round-trip on cache hit) ─────
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionPerms = $_SESSION['permissions'] ?? [];
        if (is_array($sessionPerms) && in_array($permission, $sessionPerms, true)) {
            return; // permitted — found in session cache
        }

        // ── 3. PermissionService DB check ─────────────────────────────────────
        $pdo = $GLOBALS['ADMIN_DB'] ?? null;

        if ($pdo instanceof \PDO && class_exists('PermissionService', false)) {
            $svc = new PermissionService($pdo);
            if ($svc->hasPermission($permission)) {
                return; // permitted
            }
        } elseif ($pdo === null && !empty($sessionPerms)) {
            // PDO unavailable but session has a permission list — deny since the
            // permission was not found in step 2 above.
            // (Fall through to deny response below.)
        }

        // ── 4. Deny ───────────────────────────────────────────────────────────
        // Log the failed access attempt before responding.
        audit_log([
            'action'      => 'unauthorized_access',
            'description' => "Permission denied: {$permission}",
        ]);

        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success'   => false,
            'message'   => 'Forbidden: you do not have the required permission.',
            'required'  => $permission,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

if (!function_exists('audit_log')) {
    /**
     * Record a security or business event in the audit log.
     *
     * Automatically resolves tenant_id and user_id from the session when not
     * provided in $data so callers only need to supply action-specific fields.
     *
     * @param array{
     *   action?:       string,        Required when entity_type is used.
     *   entity_type?:  string,        The resource type, e.g. 'order', 'product'.
     *   entity_id?:    int|null,      Primary key of the affected record.
     *   old_values?:   array|null,    Snapshot before the change (for updates).
     *   new_values?:   array|null,    Snapshot after the change (for creates/updates).
     *   description?:  string,        Human-readable summary.
     *   tenant_id?:    int|null,      Override — otherwise taken from session.
     *   user_id?:      int|null,      Override — otherwise taken from session.
     * } $data
     */
    function audit_log(array $data): void
    {
        if (!class_exists('AuditLogger', false)) {
            return; // AuditLogger not loaded — fail silently to avoid cascade errors
        }

        // Resolve tenant / user from session when callers omit them.
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $action      = $data['action']      ?? 'event';
        $entityType  = $data['entity_type'] ?? ($data['action'] ?? 'system');
        $entityId    = isset($data['entity_id'])   ? (int)$data['entity_id']  : null;
        $tenantId    = isset($data['tenant_id'])   ? (int)$data['tenant_id']  : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);
        $userId      = isset($data['user_id'])     ? (int)$data['user_id']    : (isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']   : (isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null));

        // Build payload — strip internal keys, keep user-provided extras.
        $payload = array_diff_key($data, array_flip(['action', 'entity_type', 'entity_id', 'tenant_id', 'user_id']));
        $payload['_action']    = $action;
        $payload['_tenant_id'] = $tenantId;
        $payload['_user_id']   = $userId;
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $payload['_ip'] = $_SERVER['REMOTE_ADDR'];
        }

        // AuditLogger::log() signature: (action, entityType, entityId, payload)
        // The class internally reads tenant_id / user_id from session.
        // We temporarily push our resolved values so the logger picks them up
        // even if the session keys differ across contexts.
        $prevTenantId = $_SESSION['tenant_id'] ?? null;
        $prevUserId   = $_SESSION['user_id']   ?? null;

        if ($tenantId !== null) {
            $_SESSION['tenant_id'] = $tenantId;
        }
        if ($userId !== null) {
            $_SESSION['user_id'] = $userId;
        }

        try {
            AuditLogger::log($action, $entityType, $entityId, $payload);
        } catch (\RuntimeException $e) {
            error_log('[audit_log] Failed to write audit record: ' . $e->getMessage());
        } finally {
            // Restore original session values so we don't corrupt ongoing request state.
            if ($prevTenantId !== null) {
                $_SESSION['tenant_id'] = $prevTenantId;
            } elseif ($tenantId !== null) {
                unset($_SESSION['tenant_id']);
            }
            if ($prevUserId !== null) {
                $_SESSION['user_id'] = $prevUserId;
            } elseif ($userId !== null) {
                unset($_SESSION['user_id']);
            }
        }
    }
}