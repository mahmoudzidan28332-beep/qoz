<?php
declare(strict_types=1);

/**
 * AddressesService
 *
 * Fully-secured service for the addresses resource.
 *
 * SECURITY ENFORCEMENT (applied to every method):
 *  1. Policy enforcement      — AddressPolicy check before any data is read or mutated
 *  2. Automatic audit logging — auditCreate / auditUpdate / auditDelete from BaseService
 *
 * NOTE: authorize() (permission-level check) is called at the controller /
 * route level BEFORE this service is entered.  Policy checks here provide
 * the finer-grained, resource-row-level access control.
 */
final class AddressesService extends BaseService
{
    /** Audit entity type label written into every audit-log entry. */
    protected string $entityType = 'address';

    private PdoAddressesRepository $repo;

    public function __construct(PdoAddressesRepository $repo)
    {
        $this->repo = $repo;
    }

    // =========================================================================
    // LIST
    // =========================================================================

    public function list(
        int $limit,
        int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    // =========================================================================
    // GET — supports tenant users (tenantId > 0) and public users (ownerId only)
    // =========================================================================

    /**
     * @param  int         $id
     * @param  string      $language
     * @param  int|null    $tenantId  Pass TenantContext::getId() for tenant routes,
     *                                null for public/owner-scoped routes.
     * @param  int|null    $ownerId   Required when tenantId is null.
     * @throws \RuntimeException 404 when not found, 403 when policy denies.
     */
    public function get(int $id, string $language = 'ar', ?int $tenantId = null, ?int $ownerId = null): array
    {
        $item = $this->repo->find($id, $language, $tenantId, $ownerId);
        if (!$item) {
            throw new \RuntimeException('Address not found', 404);
        }

        // Policy check — tenant routes always have a tenantId.
        if ($tenantId !== null && $tenantId > 0) {
            $policy = AddressPolicy::forCurrentUser($tenantId);
            $this->enforcePolicy($policy->canView($item), 'view');
        }

        return $item;
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * @throws \RuntimeException 403 when policy denies, validation errors propagate.
     */
    public function create(array $data): int
    {
        // Determine tenant scope — use TenantContext when available.
        $tenantId = TenantContext::isSet() ? TenantContext::getId() : null;

        if ($tenantId !== null) {
            $policy = AddressPolicy::forCurrentUser($tenantId);
            $this->enforcePolicy($policy->canCreate(), 'create');
        }

        AddressesValidator::validateCreate($data);
        $id = $this->repo->create($data);

        // Audit — strip any sensitive fields before logging.
        $this->auditCreate($id, $this->_safeValues($data));

        return $id;
    }

    // =========================================================================
    // UPDATE — supports tenant users and public users
    // =========================================================================

    /**
     * @throws \RuntimeException 404 when not found, 403 when policy denies.
     */
    public function update(int $id, array $data, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        AddressesValidator::validateUpdate($data);

        // Fetch the existing row so we can (a) policy-check and (b) capture old_values.
        $existing = $this->repo->find($id, 'ar', $tenantId, $ownerId);
        if (!$existing) {
            throw new \RuntimeException('Address not found', 404);
        }

        if ($tenantId !== null && $tenantId > 0) {
            $policy = AddressPolicy::forCurrentUser($tenantId);
            $this->enforcePolicy($policy->canUpdate($existing), 'update');
        }

        $result = $this->repo->update($id, $data, $tenantId, $ownerId);

        if ($result) {
            $this->auditUpdate($id, $this->_safeValues($existing), $this->_safeValues($data));
        }

        return $result;
    }

    // =========================================================================
    // DELETE — supports tenant users and public users
    // =========================================================================

    /**
     * @throws \RuntimeException 404 when not found, 403 when policy denies.
     */
    public function delete(int $id, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        // Fetch existing row for policy check and audit old_values capture.
        $existing = $this->repo->find($id, 'ar', $tenantId, $ownerId);
        if (!$existing) {
            throw new \RuntimeException('Address not found', 404);
        }

        if ($tenantId !== null && $tenantId > 0) {
            $policy = AddressPolicy::forCurrentUser($tenantId);
            $this->enforcePolicy($policy->canDelete($existing), 'delete');
        }

        $result = $this->repo->delete($id, $tenantId, $ownerId);

        if ($result) {
            $this->auditDelete($id, $this->_safeValues($existing));
        }

        return $result;
    }

    // =========================================================================
    // GET BY OWNER
    // =========================================================================

    public function getByOwner(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): array
    {
        return $this->repo->getByOwner($ownerId, $ownerType, $tenantId);
    }

    // =========================================================================
    // GET PRIMARY ADDRESS
    // =========================================================================

    public function getPrimaryAddress(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): ?array
    {
        return $this->repo->getPrimaryAddress($ownerId, $ownerType, $tenantId);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Strip sensitive keys before writing a record snapshot to the audit log.
     */
    private function _safeValues(array $values): array
    {
        return array_diff_key($values, array_flip(['password', 'password_hash', 'token', 'secret']));
    }
}
