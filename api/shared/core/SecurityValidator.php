<?php
declare(strict_types=1);

/**
 * SecurityValidator
 *
 * Runtime system integrity check for the multi-tenant SaaS security layer.
 *
 * PURPOSE:
 *  Provide a single, callable assertion that verifies all critical security
 *  invariants are in place before the application processes a request.
 *  If any invariant fails, the request is blocked (fail-fast principle).
 *
 * INVARIANTS CHECKED:
 *  1. TenantContext has been initialised (tenant scope is set).
 *  2. QueryGuard class is loaded and functional.
 *  3. AuditContext has been booted for this request.
 *  4. PlatformContext has been booted.
 *  5. All classes implementing TenantScopedInterface also extend BaseRepository,
 *     ensuring the QueryGuard enforcement layer is always active.
 *
 * USAGE:
 *
 *   // At each tenant-scoped API entry-point, after TenantContext::set():
 *   SecurityValidator::assertSystemIntegrity();
 *
 *   // Lightweight check during boot (before tenant is known):
 *   SecurityValidator::assertSecurityLayerLoaded();
 *
 * FAIL-FAST POLICY:
 *  In development (APP_ENV=development or display_errors=1) any failure
 *  throws a RuntimeException so the developer sees the problem immediately.
 *  In production, failures are written to the error log AND the audit trail,
 *  then the request is terminated with a generic 500 response.
 */
final class SecurityValidator
{
    private function __construct() {}

    // =========================================================================
    // Primary assertions
    // =========================================================================

    /**
     * Assert that all security layer components are properly initialised.
     *
     * Call this at every API entry-point that handles tenant-scoped data,
     * AFTER TenantContext::set() and PlatformContext::boot() have been called.
     *
     * For Platform Admin (super-admin) requests, TenantContext is NOT required —
     * PlatformContext::isSuperAdmin() being true satisfies the tenant-scope invariant.
     * In that case, use assertPlatformAdminIntegrity() for a stricter platform check.
     *
     * @throws \RuntimeException  In development mode when any invariant fails.
     */
    public static function assertSystemIntegrity(): void
    {
        $failures = [];

        // Determine whether this request runs in Platform Admin mode.
        $isPlatformAdmin = class_exists('PlatformContext', false)
            && PlatformContext::isBooted()
            && PlatformContext::isSuperAdmin();

        // 1. Tenant scope: either TenantContext is set, OR we are in Platform Admin mode.
        if (!$isPlatformAdmin) {
            if (!class_exists('TenantContext', false) || !TenantContext::isSet()) {
                $failures[] = 'TenantContext is not initialised — call TenantContext::set(resolve_tenant_id()) at the API entry-point, '
                    . 'or call PlatformContext::bootSuperAdmin() for platform-admin routes.';
            }
        }

        // 2. QueryGuard must be loaded.
        if (!class_exists('QueryGuard', false)) {
            $failures[] = 'QueryGuard class is not loaded — require api/shared/core/QueryGuard.php before handling requests.';
        }

        // 3. AuditContext must be booted.
        if (!class_exists('AuditContext', false)) {
            $failures[] = 'AuditContext class is not loaded — require api/shared/core/AuditContext.php at bootstrap.';
        } elseif (!AuditContext::isBooted()) {
            $failures[] = 'AuditContext::boot() has not been called — call AuditContext::boot() at the API entry-point after session_start().';
        }

        // 4. PlatformContext must be loaded and booted.
        if (!class_exists('PlatformContext', false)) {
            $failures[] = 'PlatformContext class is not loaded — require api/shared/core/PlatformContext.php at bootstrap.';
        } elseif (!PlatformContext::isBooted()) {
            $failures[] = 'PlatformContext::boot() has not been called — call PlatformContext::boot() or PlatformContext::bootSuperAdmin() at the API entry-point.';
        }

        // 5. All TenantScopedInterface implementors must extend BaseRepository.
        $interfaceFailures = self::checkTenantScopedRepositories();
        $failures          = array_merge($failures, $interfaceFailures);

        if (!empty($failures)) {
            self::handleFailures($failures);
        }
    }

    /**
     * Assert that the core security classes are available (no tenant required).
     *
     * Use this at bootstrap, before the tenant scope is established, to ensure
     * the security infrastructure files were loaded correctly.
     *
     * @throws \RuntimeException  When any security class is missing.
     */
    public static function assertSecurityLayerLoaded(): void
    {
        $required = [
            'TenantContext'        => 'api/shared/core/TenantContext.php',
            'QueryGuard'           => 'api/shared/core/QueryGuard.php',
            'PlatformContext'      => 'api/shared/core/PlatformContext.php',
            'AuditContext'         => 'api/shared/core/AuditContext.php',
            'BaseRepository'       => 'api/shared/core/BaseRepository.php',
            'BaseService'          => 'api/shared/core/BaseService.php',
        ];

        $missing = [];
        foreach ($required as $class => $file) {
            if (!class_exists($class, false) && !interface_exists($class, false)) {
                $missing[] = "{$class} (expected in {$file})";
            }
        }

        if (!empty($missing)) {
            $message = 'SecurityValidator: required security classes not loaded: '
                . implode(', ', $missing);
            self::fail($message);
        }
    }

    /**
     * Assert that Platform Admin (PLATFORM_ADMIN) context is correctly initialised.
     *
     * Use this at the start of EVERY platform-admin / support-mode route, in place
     * of (or in addition to) assertSystemIntegrity().
     *
     * Invariants checked:
     *  1. PlatformContext is booted AND isSuperAdmin().
     *  2. AuditContext is booted — audit must NEVER be bypassed.
     *  3. QueryGuard is loaded.
     *
     * TenantContext is intentionally NOT required here — Platform Admin operates
     * at platform level and must NOT be bound to a single tenant_id.
     *
     * @throws \RuntimeException  In development mode when any invariant fails.
     */
    public static function assertPlatformAdminIntegrity(): void
    {
        $failures = [];

        // 1. PlatformContext must be booted and super-admin flag must be set.
        if (!class_exists('PlatformContext', false)) {
            $failures[] = 'PlatformContext class is not loaded — require api/shared/core/PlatformContext.php at bootstrap.';
        } elseif (!PlatformContext::isBooted()) {
            $failures[] = 'PlatformContext::boot() has not been called — call PlatformContext::bootSuperAdmin() at the platform-admin entry-point.';
        } elseif (!PlatformContext::isSuperAdmin()) {
            $failures[] = 'Current actor is NOT a super-admin. Platform Admin routes require PlatformContext::isSuperAdmin() === true.';
        }

        // 2. AuditContext must be booted — audit MUST never be bypassed.
        if (!class_exists('AuditContext', false)) {
            $failures[] = 'AuditContext class is not loaded — require api/shared/core/AuditContext.php at bootstrap.';
        } elseif (!AuditContext::isBooted()) {
            $failures[] = 'AuditContext::boot() has not been called — call AuditContext::boot() before any platform-admin operation.';
        }

        // 3. QueryGuard must be loaded (remains active for platform admin).
        if (!class_exists('QueryGuard', false)) {
            $failures[] = 'QueryGuard class is not loaded — require api/shared/core/QueryGuard.php before handling requests.';
        }

        if (!empty($failures)) {
            self::handleFailures($failures);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Verify that every class implementing TenantScopedInterface
     * also extends BaseRepository.
     *
     * @return string[]  List of violation messages (empty = all good).
     */
    private static function checkTenantScopedRepositories(): array
    {
        if (!interface_exists('TenantScopedInterface', false)
            || !class_exists('BaseRepository', false)
        ) {
            return []; // Cannot check if the types aren't loaded yet.
        }

        $violations = [];

        foreach (get_declared_classes() as $className) {
            if (!self::implementsInterface($className, 'TenantScopedInterface')) {
                continue;
            }

            if (!is_a($className, 'BaseRepository', true)) {
                $violations[] = "Class '{$className}' implements TenantScopedInterface but does NOT extend BaseRepository. "
                    . 'All tenant-scoped repositories MUST extend BaseRepository to inherit QueryGuard enforcement.';
            }
        }

        return $violations;
    }

    /**
     * Check whether a class implements a given interface (without autoloading).
     */
    private static function implementsInterface(string $className, string $interfaceName): bool
    {
        $interfaces = class_implements($className, false);
        return is_array($interfaces) && in_array($interfaceName, $interfaces, true);
    }

    /**
     * Handle one or more integrity failures.
     *
     * - In development → throw RuntimeException (fail-fast).
     * - In production  → log + audit + terminate with 500.
     *
     * @param  string[] $failures  Human-readable failure messages.
     */
    private static function handleFailures(array $failures): void
    {
        $message = 'SecurityValidator: system integrity check failed — '
            . implode(' | ', $failures);

        // Record in the audit trail if possible.
        if (function_exists('audit_log')) {
            audit_log([
                'action'      => 'security_integrity_failure',
                'description' => $message,
            ]);
        }

        self::fail($message);
    }

    /**
     * Trigger the fail-fast response.
     *
     * In development (display_errors = 1 or APP_ENV = development),
     * throws so the developer gets a full stack trace.
     * In production, writes to error_log and terminates.
     *
     * @param  string $message
     * @throws \RuntimeException  In development mode.
     */
    private static function fail(string $message): void
    {
        $isDev = ini_get('display_errors') || (getenv('APP_ENV') === 'development');

        error_log($message);

        if ($isDev) {
            throw new \RuntimeException($message);
        }

        // Production: send a minimal, non-leaking error response.
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => false,
            'message' => 'Service temporarily unavailable.',
        ], JSON_UNESCAPED_UNICODE);

        exit(1);
    }
}