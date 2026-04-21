<?php
declare(strict_types=1);

/**
 * AuditContext
 *
 * Enterprise-grade audit capture layer.
 *
 * DESIGN GOALS:
 *  - Every security-relevant event is captured with a rich, consistent payload.
 *  - Payloads include request_id, session_id, actor_role, tenant_id and an
 *    automatic diff when both old and new values are provided.
 *  - All writes are delegated to audit_log() so the physical storage layer
 *    remains interchangeable.
 *
 * EVENTS CAPTURED:
 *   login            — successful and failed login attempts
 *   permission_denied — any authorization check that fails
 *   tenant_access    — a principal accessing a specific tenant (esp. super_admin)
 *   role_change      — any role assignment or revocation
 *   data_create      — INSERT into a sensitive table
 *   data_update      — UPDATE (includes automatic diff)
 *   data_delete      — DELETE from a sensitive table
 *   cross_tenant     — super_admin accessing a tenant different from their own
 *
 * USAGE:
 *
 *   // At bootstrap (once per request):
 *   AuditContext::boot();
 *
 *   // Anywhere an event needs to be recorded:
 *   AuditContext::capture('data_update', 'products', $id, [
 *       'old' => $oldRow,
 *       'new' => $newRow,
 *   ]);
 *
 *   AuditContext::capture('permission_denied', 'orders', null, [
 *       'permission' => 'orders.delete',
 *   ]);
 */
final class AuditContext
{
    /** Unique identifier for this HTTP request (generated once at boot). */
    private static string $requestId = '';

    /** Whether boot() has been called this request. */
    private static bool $booted = false;

    private function __construct() {}

    // =========================================================================
    // Boot
    // =========================================================================

    /**
     * Initialise AuditContext for the current request.
     *
     * Generates a unique request ID that is attached to every audit entry
     * produced during this request, making it trivial to correlate all events
     * from a single HTTP call in the audit log.
     *
     * Call exactly once at the API entry-point, after session_start().
     * Idempotent — safe to call multiple times (subsequent calls are no-ops).
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$requestId = self::generateRequestId();
        self::$booted    = true;
    }

    // =========================================================================
    // Core capture
    // =========================================================================

    /**
     * Capture a security or business event into the audit log.
     *
     * Automatically enriches the payload with:
     *  - request_id   (from this request's boot() call)
     *  - session_id   (current PHP session ID)
     *  - actor_role   (role(s) from $_SESSION['user']['roles'])
     *  - tenant_id    (from TenantContext when set, otherwise from session)
     *  - diff         (automatically calculated when both 'old' and 'new' are present)
     *
     * @param  string   $event       One of the documented event types (see class docblock).
     * @param  string   $resource    Resource type, e.g. 'products', 'addresses'.
     * @param  int|null $resourceId  Primary key of the affected row (null for collection ops).
     * @param  array    $context     Extra fields — 'old', 'new', 'permission', 'reason', etc.
     */
    public static function capture(
        string $event,
        string $resource,
        ?int $resourceId,
        array $context = []
    ): void {
        if (!self::$booted) {
            self::boot();
        }

        $payload = self::buildPayload($event, $resource, $resourceId, $context);

        if (function_exists('audit_log')) {
            audit_log(array_merge([
                'action'      => $event,
                'entity_type' => $resource,
                'entity_id'   => $resourceId,
            ], $payload));
        } else {
            // Fallback: write to error_log so events are never silently dropped.
            error_log('[AuditContext] ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    }

    // =========================================================================
    // Specialised helpers
    // =========================================================================

    /**
     * Capture a login attempt (successful or failed).
     *
     * @param  bool        $success    True when the login succeeded.
     * @param  string|null $email      The email address used in the attempt.
     * @param  string|null $failReason Why the login failed (never log raw passwords).
     */
    public static function captureLogin(
        bool $success,
        ?string $email = null,
        ?string $failReason = null
    ): void {
        self::capture('login', 'auth', null, array_filter([
            'success'    => $success,
            'email'      => $email,
            'fail_reason'=> $failReason,
        ]));
    }

    /**
     * Capture a permission-denied event.
     *
     * @param  string $permission  The permission that was required, e.g. 'orders.delete'.
     * @param  string $resource    The resource the actor tried to access.
     */
    public static function capturePermissionDenied(string $permission, string $resource = 'unknown'): void
    {
        self::capture('permission_denied', $resource, null, [
            'permission' => $permission,
        ]);
    }

    /**
     * Capture a role assignment or revocation.
     *
     * @param  int    $targetUserId  The user whose roles changed.
     * @param  string $role          The role that was granted or revoked.
     * @param  string $action        'grant' or 'revoke'.
     */
    public static function captureRoleChange(int $targetUserId, string $role, string $action): void
    {
        self::capture('role_change', 'user_roles', $targetUserId, [
            'role'   => $role,
            'action' => $action,
        ]);
    }

    /**
     * Capture a super_admin cross-tenant access event.
     *
     * @param  int|null $sourceTenant  Tenant the actor belongs to (null = platform).
     * @param  int      $targetTenant  Tenant being accessed.
     * @param  int|null $userId        Actor's user ID.
     * @param  string   $reason        Human-readable justification.
     */
    public static function captureCrossTenantAccess(
        ?int $sourceTenant,
        int  $targetTenant,
        ?int $userId,
        string $reason = ''
    ): void {
        self::capture('cross_tenant', 'tenants', $targetTenant, [
            'source_tenant' => $sourceTenant,
            'target_tenant' => $targetTenant,
            'user_id'       => $userId,
            'reason'        => $reason,
        ]);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Return the request ID generated for this request.
     */
    public static function getRequestId(): string
    {
        return self::$requestId;
    }

    // =========================================================================
    // Reset (test/CLI use only)
    // =========================================================================

    /**
     * Reset internal state.  Call in test teardown or CLI multi-request loops.
     * Do NOT call during normal HTTP request handling.
     */
    public static function reset(): void
    {
        self::$requestId = '';
        self::$booted    = false;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the enriched audit payload.
     */
    private static function buildPayload(
        string $event,
        string $resource,
        ?int $resourceId,
        array $context
    ): array {
        $sessionId = (session_status() === PHP_SESSION_ACTIVE) ? session_id() : '';
        $actorRole = self::resolveActorRole();
        $tenantId  = self::resolveTenantId();
        $userId    = self::resolveUserId();

        $payload = [
            'request_id'  => self::$requestId,
            'session_id'  => $sessionId,
            'actor_role'  => $actorRole,
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'event'       => $event,
            'resource'    => $resource,
            'resource_id' => $resourceId,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp'   => date('c'),
        ];

        // Auto-calculate diff when both 'old' and 'new' are present.
        if (isset($context['old'], $context['new'])
            && is_array($context['old'])
            && is_array($context['new'])
        ) {
            $payload['diff'] = self::calculateDiff($context['old'], $context['new']);
        }

        // Merge the caller's context (old/new already handled above).
        foreach ($context as $k => $v) {
            $payload[$k] = $v;
        }

        return $payload;
    }

    /**
     * Resolve the actor's role(s) from the session.
     *
     * Returns a comma-separated string (or 'unknown' if unavailable).
     */
    private static function resolveActorRole(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $roles = $_SESSION['user']['roles'] ?? $_SESSION['roles'] ?? null;

        if (is_array($roles)) {
            return implode(',', $roles);
        }
        if (is_string($roles)) {
            return $roles;
        }

        return 'unknown';
    }

    /**
     * Resolve the current tenant ID from TenantContext (preferred) or session.
     */
    private static function resolveTenantId(): ?int
    {
        if (class_exists('TenantContext', false) && TenantContext::isSet()) {
            return TenantContext::getId();
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        return isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
    }

    /**
     * Resolve the current user ID from the session.
     */
    private static function resolveUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (isset($_SESSION['user']['id'])) {
            return (int)$_SESSION['user']['id'];
        }
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        return null;
    }

    /**
     * Calculate the diff between old and new data arrays.
     *
     * Returns only the keys that changed, with ['old' => ..., 'new' => ...] pairs.
     *
     * Sensitive keys (password, token, secret) are automatically redacted.
     */
    private static function calculateDiff(array $old, array $new): array
    {
        $sensitiveKeys = ['password', 'password_hash', 'token', 'secret', 'api_key'];
        $diff          = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal === $newVal) {
                continue;
            }

            // Redact sensitive values.
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $diff[$key] = ['old' => '[REDACTED]', 'new' => '[REDACTED]'];
                continue;
            }

            $diff[$key] = ['old' => $oldVal, 'new' => $newVal];
        }

        return $diff;
    }

    /**
     * Generate a unique request ID.
     *
     * Format: <timestamp_ms>-<random_hex>
     * Example: 1713705662123-a3f9b1c2
     */
    private static function generateRequestId(): string
    {
        $ms  = (int)(microtime(true) * 1000);
        $rnd = bin2hex(random_bytes(4));
        return "{$ms}-{$rnd}";
    }
}
