<?php
declare(strict_types=1);

/**
 * BaseService
 *
 * Abstract foundation for all service classes.
 *
 * PROVIDES:
 *  1. Automatic audit-log helpers (auditCreate / auditUpdate / auditDelete).
 *     Every mutation MUST call the corresponding helper so that no data change
 *     is ever untracked.
 *
 *  2. Policy enforcement helper (enforcePolicy) for DRY 403 handling.
 *
 *  3. Tenant-scope assertion (assertTenantScope) as a fail-fast guard.
 *
 * USAGE:
 *
 *   final class ProductsService extends BaseService
 *   {
 *       protected string $entityType = 'product';
 *
 *       public function create(array $data): int
 *       {
 *           $this->assertTenantScope();
 *           $policy = ProductPolicy::forCurrentUser(TenantContext::getId());
 *           $this->enforcePolicy($policy->canCreate(), 'create');
 *
 *           $id = $this->repo->create($data);
 *           $this->auditCreate($id, $data);
 *           return $id;
 *       }
 *   }
 *
 * AUDIT TRAIL:
 *  All helpers delegate to the global audit_log() function (authorize.php) which
 *  in turn writes through AuditLogger.  No direct DB calls are made here.
 *
 * BACKWARD COMPATIBILITY:
 *  Existing services that do NOT yet extend BaseService continue to work
 *  unchanged.  Extend incrementally — there is no forced migration.
 */
abstract class BaseService
{
    /**
     * Resource type written into every audit-log entry produced by this service.
     *
     * Override in each concrete service, e.g.:
     *   protected string $entityType = 'address';
     */
    protected string $entityType = 'unknown';

    // =========================================================================
    // Audit hooks
    // =========================================================================

    /**
     * Record a CREATE event in the audit log.
     *
     * Call this AFTER the repository insert succeeds and you have the new ID.
     *
     * @param  int   $entityId   Primary key of the newly created record.
     * @param  array $newValues  Data that was saved (strip sensitive fields first).
     */
    protected function auditCreate(int $entityId, array $newValues): void
    {
        $this->callAuditLog([
            'action'      => 'create',
            'entity_type' => $this->entityType,
            'entity_id'   => $entityId,
            'new_values'  => $newValues,
        ]);
    }

    /**
     * Record an UPDATE event in the audit log.
     *
     * Call this AFTER the repository update succeeds.
     *
     * @param  int        $entityId   Primary key of the updated record.
     * @param  array      $oldValues  Snapshot of the row BEFORE the update.
     * @param  array      $newValues  Incoming data that was applied.
     */
    protected function auditUpdate(int $entityId, array $oldValues, array $newValues): void
    {
        $this->callAuditLog([
            'action'      => 'update',
            'entity_type' => $this->entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
        ]);
    }

    /**
     * Record a DELETE event in the audit log.
     *
     * Call this AFTER the repository delete succeeds (or before, if you need to
     * capture old_values while the row still exists).
     *
     * @param  int        $entityId   Primary key of the deleted record.
     * @param  array|null $oldValues  Optional snapshot of the row before deletion.
     */
    protected function auditDelete(int $entityId, ?array $oldValues = null): void
    {
        $this->callAuditLog([
            'action'      => 'delete',
            'entity_type' => $this->entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues,
        ]);
    }

    /**
     * Safe wrapper around the global audit_log() helper.
     *
     * Guards against the case where authorize.php has not been loaded yet,
     * preventing a fatal "Call to undefined function audit_log()" error.
     */
    private function callAuditLog(array $data): void
    {
        if (function_exists('audit_log')) {
            audit_log($data);
        }
    }

    // =========================================================================
    // Policy enforcement
    // =========================================================================

    /**
     * Throw a 403-coded RuntimeException when the policy check fails.
     *
     * This is a thin, DRY wrapper so service methods stay readable:
     *
     *   $this->enforcePolicy($policy->canDelete($row), 'delete');
     *
     * @param  bool   $allowed     Result of a Policy method.
     * @param  string $action      Human-readable action name for the error message.
     *
     * @throws \RuntimeException  HTTP 403 when $allowed is false.
     */
    protected function enforcePolicy(bool $allowed, string $action = 'access'): void
    {
        if (!$allowed) {
            // Log the denied action before throwing.
            $this->callAuditLog([
                'action'      => 'policy_denied',
                'entity_type' => $this->entityType,
                'description' => "Policy check failed for action: {$action}",
            ]);
            throw new \AuthorizationException(
                "Forbidden: you do not have permission to {$action} this {$this->entityType}."
            );
        }
    }

    // =========================================================================
    // Tenant scope guard
    // =========================================================================

    /**
     * Assert that a valid tenant scope is active.
     *
     * Call this at the very start of any service method that touches tenant data.
     * It triggers a fail-fast exception before any business logic runs, surfacing
     * the misconfiguration loudly instead of producing a silent data leak.
     *
     * @throws \RuntimeException  If TenantContext is not initialised.
     */
    protected function assertTenantScope(): void
    {
        TenantContext::require();
    }

    // =========================================================================
    // Service-layer SQL ban
    // =========================================================================

    /**
     * Assert that no direct PDO or raw SQL access is occurring in this call stack.
     *
     * Service classes MUST NOT access the database directly.  All persistence
     * MUST go through repository classes that extend BaseRepository.
     *
     * Call this at the top of any service method as a defence-in-depth guard.
     * It scans the backtrace for PDO class frames and throws immediately if found.
     *
     * @throws \RuntimeException  When a PDO call is detected in the call stack.
     */
    protected function assertNoPdoAccess(): void
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = $frame['class'] ?? '';
            if ($class === 'PDO' || $class === 'PDOStatement') {
                throw new \SystemException(
                    'SecurityException: Direct database access (PDO) detected inside a Service class. '
                    . 'Services MUST only call repositories. Move all SQL into a BaseRepository subclass.'
                );
            }
        }
    }

    /**
     * Prevent service subclasses from directly using a PDO instance.
     *
     * This method is intentionally declared to throw, providing a clear error
     * if a service attempts to accept or store a PDO object.
     *
     * @throws \RuntimeException  Always.
     *
     * @internal
     */
    final protected function forbidDirectDb(): void
    {
        throw new \SystemException(
            'SecurityException: Services MUST NOT access PDO directly. '
            . 'Inject a repository (extends BaseRepository) instead.'
        );
    }
}