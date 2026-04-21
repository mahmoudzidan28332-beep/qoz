<?php
declare(strict_types=1);

/**
 * TenantScopedInterface
 *
 * Contract that marks a repository as tenant-scoped.
 *
 * Every repository that reads or writes tenant-specific data MUST implement
 * this interface.  It serves two purposes:
 *
 *  1. Documentation — makes it explicit at a glance that this class is
 *     tenant-aware and that ALL its queries must include a tenant_id filter.
 *
 *  2. Runtime guard — SecurityValidator::assertSystemIntegrity() can reflect
 *     over all loaded repository classes and verify that every class that
 *     implements TenantScopedInterface also extends BaseRepository, ensuring
 *     the QueryGuard enforcement layer is always present.
 *
 * USAGE:
 *
 *   final class PdoProductsRepository extends BaseRepository
 *       implements TenantScopedInterface
 *   {
 *       // All queries here MUST be tenant-scoped via $this->execute() or
 *       // $this->guardedQuery() so QueryGuard validates them automatically.
 *   }
 *
 * ENFORCEMENT:
 *   - Any class implementing this interface that does NOT extend BaseRepository
 *     will be flagged by SecurityValidator at runtime.
 *   - In development mode, this triggers a fatal RuntimeException (fail-fast).
 *   - In production, it triggers an error_log warning + audit entry.
 */
interface TenantScopedInterface
{
    // Intentionally empty — this is a marker interface.
    // The presence of the interface on a class is the contract itself.
}
