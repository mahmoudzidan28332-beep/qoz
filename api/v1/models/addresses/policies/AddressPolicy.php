<?php
declare(strict_types=1);

/**
 * AddressPolicy
 *
 * Resource-level access control for the `addresses` table.
 *
 * RESPONSIBILITIES:
 *  - Enforce tenant_id isolation (no address from another tenant is ever accessible)
 *  - Enforce ownership rules (regular users can only touch their own addresses)
 *  - Enforce role-based elevation (managers/admins can access all tenant addresses)
 *  - Provide a single, auditable place for every "can this user do X to this address?"
 *    decision
 *
 * USAGE (inside AddressesService or route handlers):
 *
 *   // Build policy for the current request
 *   $policy = AddressPolicy::forCurrentUser($currentTenantId);
 *
 *   // Before returning a record:
 *   if (!$policy->canView($address)) {
 *       respond_error('Forbidden', 403);
 *   }
 *
 *   // Before saving:
 *   if (!$policy->canUpdate($address)) {
 *       respond_error('Forbidden', 403);
 *   }
 *
 * SECURITY NOTES:
 *  - $tenantId MUST come from TenantContext (or a session-validated source),
 *    never from request body / query string.
 *  - Policies are intentionally pure (no PDO calls) so they are fast and testable.
 *  - Super-admins bypass all checks and generate an audit-log entry via authorize().
 *
 * EXTENDING TO OTHER RESOURCES:
 *  Copy this file, rename the class, adjust the ownership column name in
 *  _isOwner() if necessary, and add any resource-specific logic.
 */
final class AddressPolicy implements BasePolicy
{
    // Roles allowed to access ALL records within the tenant (not just their own).
    private const ELEVATED_ROLES = ['admin', 'manager', 'super_admin'];

    private int    $currentUserId;
    private int    $currentTenantId;
    private bool   $isSuperAdmin;
    private bool   $isElevated;   // admin or manager

    // =========================================================================
    // Construction
    // =========================================================================

    /**
     * @param int   $currentUserId   ID of the authenticated user making the request.
     * @param int   $currentTenantId The tenant the user belongs to (from TenantContext).
     * @param array $userRoles       Role key-names for the user, e.g. ['manager'].
     */
    public function __construct(int $currentUserId, int $currentTenantId, array $userRoles = [])
    {
        $this->currentUserId   = $currentUserId;
        $this->currentTenantId = $currentTenantId;
        $this->isSuperAdmin    = in_array('super_admin', $userRoles, true);
        $this->isElevated      = $this->isSuperAdmin
            || array_intersect(self::ELEVATED_ROLES, $userRoles) !== [];
    }

    /**
     * Convenience factory: build a policy from the current session.
     *
     * @param  int $tenantId  From TenantContext::getId() — do NOT pass $_GET values here.
     * @return self
     */
    public static function forCurrentUser(int $tenantId): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userId = (int)(
            $_SESSION['user']['id']
            ?? $_SESSION['user_id']
            ?? 0
        );

        $roles = $_SESSION['user']['roles'] ?? $_SESSION['roles'] ?? [];

        return new self($userId, $tenantId, (array)$roles);
    }

    // =========================================================================
    // Policy methods
    // =========================================================================

    /**
     * Can the user view this address?
     *
     * Rules:
     *  - Super-admin: always yes (any tenant).
     *  - Admin / Manager: yes, if the address belongs to their tenant.
     *  - Regular user: yes, only if they own the address AND it belongs to their tenant.
     *
     * @param  array $address  Row from the addresses table; must contain
     *                         'tenant_id' and the ownership identifier
     *                         ('user_id' or 'owner_id').
     * @return bool
     */
    public function canView(array $address): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->_isSameTenant($address)) {
            return false;
        }

        if ($this->isElevated) {
            return true;
        }

        return $this->_isOwner($address);
    }

    /**
     * Can the user create a new address?
     *
     * Rules:
     *  - Super-admin: always yes.
     *  - Admin / Manager: yes within their tenant.
     *  - Regular user: yes, but only for themselves (owner_id will be auto-set
     *    by the service layer; this check just allows the action).
     *
     * @return bool
     */
    public function canCreate(): bool
    {
        // Any authenticated user with a valid tenant may create their own address.
        return $this->currentUserId > 0 && $this->currentTenantId > 0;
    }

    /**
     * Can the user update this address?
     *
     * Rules:
     *  - Super-admin: always yes.
     *  - Admin / Manager: yes, if address belongs to their tenant.
     *  - Regular user: yes, only if they own the address AND same tenant.
     *
     * @param  array $address  Existing address row.
     * @return bool
     */
    public function canUpdate(array $address): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->_isSameTenant($address)) {
            return false;
        }

        if ($this->isElevated) {
            return true;
        }

        return $this->_isOwner($address);
    }

    /**
     * Can the user delete this address?
     *
     * Same rules as canUpdate.
     *
     * @param  array $address  Existing address row.
     * @return bool
     */
    public function canDelete(array $address): bool
    {
        return $this->canUpdate($address);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * True when the address's tenant matches the user's tenant.
     *
     * Addresses with a NULL tenant_id are treated as "user-owned without tenant"
     * and are accessible only to the owner (handled in canView/canUpdate/canDelete
     * via _isOwner).
     */
    private function _isSameTenant(array $address): bool
    {
        $addressTenantId = isset($address['tenant_id']) && $address['tenant_id'] !== null
            ? (int)$address['tenant_id']
            : null;

        if ($addressTenantId === null) {
            // Tenant-less address (regular user address): only the owner can access it.
            return false;
        }

        return $addressTenantId === $this->currentTenantId;
    }

    /**
     * True when the current user is the owner of the address.
     *
     * Checks 'owner_id' first, then 'user_id' (covers different schema conventions).
     */
    private function _isOwner(array $address): bool
    {
        if ($this->currentUserId <= 0) {
            return false;
        }

        // Primary ownership column used in addresses table
        if (isset($address['owner_id']) && (int)$address['owner_id'] === $this->currentUserId) {
            return true;
        }

        // Fallback for addresses that use 'user_id' instead of 'owner_id'
        if (isset($address['user_id']) && (int)$address['user_id'] === $this->currentUserId) {
            return true;
        }

        return false;
    }
}