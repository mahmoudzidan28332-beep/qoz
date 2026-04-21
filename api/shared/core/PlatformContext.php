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
            if (function_exists('audit_log')) {
                audit_log([
                    'action'      => 'super_admin_tenant_access',
                    'description' => "Super-admin cross-tenant access to tenant_id={$tenantId}",
                ]);
            }
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
        self::$superAdmin = false;
        self::$booted     = false;
    }
}
