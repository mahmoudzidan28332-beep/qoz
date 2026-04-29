<?php
declare(strict_types=1);

final class AddressesController extends BaseController
{
    private AddressesService $service;

    public function __construct(AddressesService $service)
    {
        $this->service = $service;
    }

    // ================================
    // LIST
    // ================================
    public function list(
        int    $limit,
        int    $offset,
        array  $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $this->requirePermission('addresses.view');
        $this->requireTenantScope();

        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    // ================================
    // GET
    // ================================
    public function get(int $id, string $language = 'ar'): array
    {
        $this->requirePermission('addresses.view');
        $this->requireTenantScope();

        return $this->service->get($id, $language);
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        $this->requirePermission('addresses.create');
        $this->requireTenantScope();

        return $this->service->create($data);
    }

    // ================================
    // UPDATE
    // ================================
    public function update(int $id, array $data): bool
    {
        $this->requirePermission('addresses.edit');
        $this->requireTenantScope();

        return $this->service->update($id, $data);
    }

    // ================================
    // DELETE
    // ================================
    public function delete(int $id): bool
    {
        $this->requirePermission('addresses.delete');
        $this->requireTenantScope();

        return $this->service->delete($id);
    }

    // ================================
    // GET BY OWNER
    // ================================
    public function getByOwner(int $ownerId, string $ownerType = 'user', string $language = 'ar'): array
    {
        $this->requirePermission('addresses.view');
        $this->requireTenantScope();

        return $this->service->getByOwner($ownerId, $ownerType, $language);
    }

    // ================================
    // GET PRIMARY ADDRESS
    // ================================
    public function getPrimaryAddress(int $ownerId, string $ownerType = 'user'): ?array
    {
        $this->requirePermission('addresses.view');
        $this->requireTenantScope();

        return $this->service->getPrimaryAddress($ownerId, $ownerType);
    }
}