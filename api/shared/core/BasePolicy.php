<?php
declare(strict_types=1);

/**
 * BasePolicy
 *
 * Contract that every resource-level Policy class MUST implement.
 *
 * DESIGN PRINCIPLES:
 *  - Policies are pure PHP objects (no PDO, no HTTP, no session calls).
 *  - All decisions depend only on the user context supplied at construction
 *    time and the resource row passed into each method.
 *  - Policies are enforced in the SERVICE layer, BEFORE any DB read or write.
 *
 * OWNER / ROLE MATRIX (standardised across all resources):
 *
 *  Role          │ canView     │ canCreate   │ canUpdate   │ canDelete
 *  ──────────────┼─────────────┼─────────────┼─────────────┼────────────
 *  super_admin   │ ANY tenant  │ ANY tenant  │ ANY tenant  │ ANY tenant
 *  admin/manager │ same tenant │ same tenant │ same tenant │ same tenant
 *  user          │ own data    │ own data    │ own data    │ own data
 *
 * USAGE (inside any service):
 *
 *   $policy = MyResourcePolicy::forCurrentUser(TenantContext::getId());
 *
 *   if (!$policy->canView($row)) {
 *       throw new \RuntimeException('Forbidden', 403);
 *   }
 *
 * IMPLEMENTING:
 *
 *   final class ProductPolicy implements BasePolicy
 *   {
 *       public function canView(array $resource): bool  { ... }
 *       public function canCreate(): bool               { ... }
 *       public function canUpdate(array $resource): bool { ... }
 *       public function canDelete(array $resource): bool { ... }
 *   }
 */
interface BasePolicy
{
    /**
     * Can the current user read / list this resource?
     *
     * @param  array $resource  DB row; MUST contain at minimum 'tenant_id' and
     *                          a user-ownership column (e.g. 'user_id', 'owner_id').
     * @return bool
     */
    public function canView(array $resource): bool;

    /**
     * Can the current user create a new instance of this resource?
     *
     * There is no existing row at this point, so no $resource argument.
     * The check is purely role- and tenant-based.
     *
     * @return bool
     */
    public function canCreate(): bool;

    /**
     * Can the current user modify this existing resource?
     *
     * @param  array $resource  Existing DB row (before the mutation).
     * @return bool
     */
    public function canUpdate(array $resource): bool;

    /**
     * Can the current user delete this existing resource?
     *
     * @param  array $resource  Existing DB row (before deletion).
     * @return bool
     */
    public function canDelete(array $resource): bool;
}
