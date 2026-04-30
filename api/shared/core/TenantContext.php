<?php
declare(strict_types=1);

/**
 * TenantContext
 *
 * Centralized, request-scoped tenant ID holder for the multi-tenant SaaS system.
 *
 * SECURITY CONTRACT:
 *  - tenant_id MUST be set from trusted sources ONLY:
 *      1. $_SESSION['tenant_id']  (regular and tenant users)
 *      2. admin_tenant_id()       (super-admin filter, which itself validates the session role)
 *  - tenant_id MUST NEVER originate from:
 *      - $_GET  (except via admin_tenant_id() which checks is_super_admin() first)
 *      - $_POST
 *      - request body / JSON input
 *
 * USAGE – at the top of every API entry-point (route file):
 *
 *   $resolvedTenantId = resolve_tenant_id();      // already in safe_helpers.php
 *   if ($resolvedTenantId === null) { ... 401 ... }
 *   TenantContext::set($resolvedTenantId);
 *
 * All repository/service layers then simply call TenantContext::getId()
 * instead of receiving tenant_id as a parameter.
 */
final class TenantContext
{
    /** @var int|null Request-scoped tenant identifier. */
    private static ?int $tenantId = null;

    // -------------------------------------------------------------------------
    // Prevent instantiation — this is a pure static registry.
    // -------------------------------------------------------------------------
    private function __construct() {}

    // =========================================================================
    // Core API
    // =========================================================================

    /**
     * Store the current request's tenant ID.
     *
     * @param  int $tenantId  Must be a positive integer from a trusted source.
     * @throws \InvalidArgumentException  If $tenantId is not positive.
     */
    public static function set(int $tenantId): void
    {
        if ($tenantId < 0) {
            throw new \InvalidArgumentException(
                'TenantContext::set() requires a non-negative tenant_id; ' . $tenantId . ' given.'
            );
        }
        self::$tenantId = $tenantId;
    }

    /**
     * Retrieve the active tenant ID.
     *
     * @throws \RuntimeException  If called before TenantContext::set().
     */
    public static function getId(): int
    {
        if (self::$tenantId === null) {
            throw new \SystemException(
                'TenantContext has not been initialized. '
                . 'Call TenantContext::set(resolve_tenant_id()) at the API entry-point.'
            );
        }
        return self::$tenantId;
    }

    /**
     * Returns true when a tenant ID has already been stored this request.
     */
    public static function isSet(): bool
    {
        return self::$tenantId !== null;
    }

    /**
     * Require an active tenant ID — throws a descriptive exception if missing.
     *
     * Use this at the start of any method that MUST run inside a tenant scope
     * (e.g. the top of BaseRepository methods, BaseService mutations, etc.)
     * to produce a fail-fast exception instead of a silent security bypass.
     *
     *   TenantContext::require();  // explodes early if no tenant is bound
     *
     * @throws \RuntimeException  If called before TenantContext::set().
     */
    public static function require(): int
    {
        if (self::$tenantId === null) {
            throw new \SystemException(
                'TenantContext::require() — tenant scope is not initialised. '
                . 'The API entry-point MUST call TenantContext::set(resolve_tenant_id()) '
                . 'before any tenant-scoped operation is attempted.'
            );
        }
        return self::$tenantId;
    }

    /**
     * Clear the stored tenant ID.
     *
     * Intended for:
     *  - CLI scripts that process multiple tenants in sequence.
     *  - Test-suite tear-down.
     *
     * Do NOT call during normal HTTP request handling.
     */
    public static function clear(): void
    {
        self::$tenantId = null;
    }
}