<?php
declare(strict_types=1);

/**
 * PlatformContext
 *
 * Centralized super-admin / platform-level context manager.
 *
 * SECURITY MODEL:
 *  - super_admin identity is resolved ONLY from the trusted session (is_super_admin()).
 *  - It is NOT derivable from URL parameters, request body, or JWT claims alone.
 *  - A super-admin CAN bypass tenant restrictions but MUST ALWAYS be audited.
 *  - A regular (non-super-admin) user who lacks a tenant scope is ALWAYS denied.
 *
 * ROLE HIERARCHY:
 *
 *   super_admin    — PLATFORM LEVEL; cross-tenant access; all actions audited.
 *   tenant_admin   — full access INSIDE one tenant only.
 *   manager        — scoped access inside one tenant.
 *   user           — limited, ownership-scoped access inside one tenant.
 *
 * USAGE:
 *
 *   // At every API entry-point, after session validation:
 *   PlatformContext::boot();
 *
 *   // Later, anywhere that cares:
 *   if (PlatformContext::isSuperAdmin()) { ... }
 *
 *   // Guard tenant access (throws for non-admins without a tenant):
 *   PlatformContext::assertTenantAccess(TenantContext::getId());
 */
final class PlatformContext
{
    /** Whether the current actor is a platform super-admin. */
    private static bool $superAdmin = false;

    /** Whether boot() has been called this request. */
    private static bool $booted = false;

    /** Explicitly set user ID for super-admin session (when bootSuperAdmin() is used). */
    private static ?int $superAdminUserId = null;

    /** Target tenant ID for the active support session (null = not in support mode). */
    private static ?int $supportTargetTenantId = null;

    /** Reason captured when a support session was started. */
    private static string $supportReason = '';

    private function __construct() {}

    // =========================================================================
    // Boot
    // =========================================================================

    /**
     * Resolve and cache the super-admin status for this request.
     *
     * Call exactly once, at the API entry-point, AFTER session validation.
     * Idempotent — safe to call multiple times.
     */
    public static function boot(): void
    {
        self::$superAdmin = function_exists('is_super_admin') && is_super_admin();
        self::$booted     = true;
    }

    /**
     * Explicitly boot as super-admin (Platform Owner mode).
     *
     * Use this instead of boot() when programmatic super-admin context is needed
     * (e.g. CLI scripts, impersonation flows, background jobs running as platform).
     *
     * SECURITY: This method must ONLY be called after verifying the identity via
     * a trusted source (session, signed token, etc.).  Never call it based on
     * untrusted user input.
     *
     * Every subsequent cross-tenant action will be audited with $userId as actor.
     *
     * @param  int|null $userId  The super-admin's user ID (null = resolve from session).
     */
    public static function bootSuperAdmin(?int $userId = null): void
    {
        self::$superAdmin       = true;
        self::$booted           = true;
        self::$superAdminUserId = $userId;

        // Audit the explicit super-admin boot for traceability.
        $resolvedUserId = $userId ?? self::resolveSessionUserId();
        if (class_exists('AuditContext', false)) {
            AuditContext::capture('super_admin_boot', 'platform', $resolvedUserId, [
                'user_id' => $resolvedUserId,
                'reason'  => 'Explicit super-admin mode activated via bootSuperAdmin()',
            ]);
        } elseif (function_exists('audit_log')) {
            audit_log([
                'action'  => 'super_admin_boot',
                'user_id' => $resolvedUserId,
                'reason'  => 'Explicit super-admin mode activated via bootSuperAdmin()',
            ]);
        } else {
            error_log('[PlatformContext] super_admin_boot: userId=' . $resolvedUserId);
        }
    }

    /**
     * Return true when boot() or bootSuperAdmin() has been called this request.
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }

    /**
     * Begin a Platform Admin support session targeting a specific tenant.
     *
     * This method combines bootSuperAdmin() with explicit support-session tracking.
     * It MUST be called (instead of or after bootSuperAdmin()) when the Platform Admin
     * is about to perform support operations inside a specific tenant.
     *
     * SECURITY REQUIREMENTS:
     *  - Must be called ONLY after the actor is verified as a super-admin.
     *  - $reason is MANDATORY and must be non-empty — every support action must
     *    have a human-readable justification (spec §3, §5).
     *  - $targetTenantId must be a positive integer identifying the target tenant.
     *
     * @param  int         $targetTenantId  Tenant being accessed (must be > 0).
     * @param  string      $reason          Mandatory justification for this access.
     * @param  int|null    $userId          The support agent's user ID (null = from session).
     *
     * @throws \InvalidArgumentException  When $reason is empty or $targetTenantId <= 0.
     * @throws \RuntimeException          When the actor is not a super-admin.
     */
    public static function beginSupportSession(
        int    $targetTenantId,
        string $reason,
        ?int   $userId = null
    ): void {
        if (!self::$superAdmin) {
            throw new \RuntimeException(
                'PlatformContext::beginSupportSession() requires super-admin privileges. '
                . 'Call PlatformContext::bootSuperAdmin() first.'
            );
        }

        if ($targetTenantId <= 0) {
            throw new \InvalidArgumentException(
                'PlatformContext::beginSupportSession() requires a positive target_tenant_id; '
                . $targetTenantId . ' given.'
            );
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException(
                'PlatformContext::beginSupportSession() requires a non-empty reason. '
                . 'Every Platform Admin support action MUST have a documented justification.'
            );
        }

        self::$supportTargetTenantId = $targetTenantId;
        self::$supportReason         = trim($reason);

        $resolvedUserId = $userId ?? self::resolveSessionUserId();

        // Audit the start of the support session.
        if (class_exists('AuditContext', false)) {
            AuditContext::capture('support_session_started', 'platform', $targetTenantId, [
                'user_id'       => $resolvedUserId,
                'target_tenant' => $targetTenantId,
                'source_tenant' => null,
                'reason'        => self::$supportReason,
            ]);
        } elseif (function_exists('audit_log')) {
            audit_log([
                'action'        => 'support_session_started',
                'user_id'       => $resolvedUserId,
                'target_tenant' => $targetTenantId,
                'reason'        => self::$supportReason,
            ]);
        } else {
            error_log('[PlatformContext] support_session_started: tenant=' . $targetTenantId
                . ' user=' . $resolvedUserId . ' reason=' . self::$supportReason);
        }
    }

    /**
     * Returns true when a support session has been started for a specific tenant.
     *
     * A support session is active when:
     *  - super-admin context is booted, AND
     *  - beginSupportSession() has been called with a valid target tenant + reason.
     */
    public static function isSupportSessionActive(): bool
    {
        return self::$superAdmin && self::$supportTargetTenantId !== null;
    }

    /**
     * Return the target tenant ID for the active support session.
     *
     * @throws \RuntimeException  When no support session is active.
     */
    public static function getTargetTenantId(): int
    {
        if (self::$supportTargetTenantId === null) {
            throw new \RuntimeException(
                'PlatformContext::getTargetTenantId() — no support session is active. '
                . 'Call beginSupportSession($targetTenantId, $reason) first.'
            );
        }
        return self::$supportTargetTenantId;
    }

    /**
     * Return the mandatory reason recorded for the active support session.
     *
     * @throws \RuntimeException  When no support session is active.
     */
    public static function getActiveReason(): string
    {
        if (self::$supportTargetTenantId === null) {
            throw new \RuntimeException(
                'PlatformContext::getActiveReason() — no support session is active. '
                . 'Call beginSupportSession($targetTenantId, $reason) first.'
            );
        }
        return self::$supportReason;
    }

    // =========================================================================
    // Query helpers
    // =========================================================================

    /**
     * Returns true when the current request actor is a platform super-admin.
     */
    public static function isSuperAdmin(): bool
    {
        return self::$superAdmin;
    }

    /**
     * Assert a valid tenant-access pattern for the current actor.
     *
     * Rules:
     *  - A non-super-admin MUST have a valid, positive tenant_id.
     *  - A super-admin may pass any tenant_id (including null for cross-tenant ops)
     *    but every cross-tenant access is force-audited.
     *
     * @param  int|null $tenantId  The tenant being accessed (null = cross-tenant).
     *
     * @throws \RuntimeException  When a non-super-admin has no tenant scope.
     */
    public static function assertTenantAccess(?int $tenantId): void
    {
        if ($tenantId === null && !self::$superAdmin) {
            throw new \RuntimeException(
                'PlatformContext: access denied — a valid tenant scope is required '
                . 'for non-super-admin actors. '
                . 'Call TenantContext::set(resolve_tenant_id()) at the API entry-point.'
            );
        }

        // Super-admin accessing a specific tenant — force audit for traceability.
        if (self::$superAdmin && $tenantId !== null) {
            self::logCrossTenantAction(
                sourceTenant: null,
                targetTenant: $tenantId,
                reason:       'assertTenantAccess — super_admin tenant access'
            );
        }
    }

    /**
     * Log a cross-tenant action performed by a super_admin.
     *
     * MANDATORY for every operation where a super_admin accesses, reads, or
     * mutates data in a tenant that is not their own.  Calling this method is
     * NOT optional — silently omitting it violates the platform audit contract.
     *
     * @param  int|null $sourceTenant  Tenant the actor belongs to (null = platform level).
     * @param  int      $targetTenant  Tenant being accessed.
     * @param  int|null $userId        Actor's user ID (null = resolve from session).
     * @param  string   $reason        Human-readable justification for the access.
     */
    public static function logCrossTenantAction(
        ?int   $sourceTenant = null,
        int    $targetTenant = 0,
        ?int   $userId       = null,
        string $reason       = ''
    ): void {
        // MANDATORY: every cross-tenant action must have a documented reason.
        if (trim($reason) === '') {
            throw new \InvalidArgumentException(
                'PlatformContext::logCrossTenantAction() requires a non-empty reason. '
                . 'Every Platform Admin cross-tenant action MUST have a documented justification.'
            );
        }

        // Resolve user_id from session when not provided.
        if ($userId === null) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $userId = isset($_SESSION['user']['id'])
                ? (int)$_SESSION['user']['id']
                : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        }

        $payload = [
            'action'        => 'cross_tenant_access',
            'source_tenant' => $sourceTenant,
            'target_tenant' => $targetTenant,
            'user_id'       => $userId,
            'reason'        => trim($reason),
            'is_super_admin'=> self::$superAdmin,
            'ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp'     => date('c'),
        ];

        // Use AuditContext when available (preferred — richer payload).
        if (class_exists('AuditContext', false)) {
            AuditContext::captureCrossTenantAccess(
                $sourceTenant,
                $targetTenant,
                $userId,
                $reason
            );
        } elseif (function_exists('audit_log')) {
            audit_log(array_merge(['description' => "Cross-tenant access: {$sourceTenant} → {$targetTenant}"], $payload));
        } else {
            error_log('[PlatformContext] cross_tenant_access: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Require super-admin privileges.
     *
     * Use this to guard platform-level operations (e.g. creating tenants,
     * viewing all audit logs, toggling feature flags) that must never be
     * callable by tenant-level users.
     *
     * @throws \RuntimeException  When the current actor is NOT a super-admin.
     */
    public static function requireSuperAdmin(): void
    {
        if (!self::$superAdmin) {
            if (function_exists('audit_log')) {
                audit_log([
                    'action'      => 'super_admin_access_denied',
                    'description' => 'Non-super-admin attempted a platform-level operation.',
                ]);
            }
            throw new \RuntimeException(
                'PlatformContext: platform-level operation requires super-admin privileges.',
                403
            );
        }

        // Log the elevated operation.
        if (function_exists('audit_log')) {
            audit_log([
                'action'      => 'super_admin_platform_op',
                'description' => 'Super-admin performed a platform-level operation.',
            ]);
        }
    }

    // =========================================================================
    // State management (for tests / CLI scripts)
    // =========================================================================

    /**
     * Reset the context — for test teardown and CLI multi-tenant loops.
     * Do NOT call during normal HTTP request handling.
     */
    public static function reset(): void
    {
        self::$superAdmin            = false;
        self::$booted                = false;
        self::$superAdminUserId      = null;
        self::$supportTargetTenantId = null;
        self::$supportReason         = '';
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the current user ID from the session.
     */
    private static function resolveSessionUserId(): ?int
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
}
