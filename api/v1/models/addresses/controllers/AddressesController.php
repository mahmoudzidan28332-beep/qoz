<?php
declare(strict_types=1);

final class AddressesController
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
        int $limit,
        int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list(
            $limit,
            $offset,
            $filters,
            $orderBy,
            $orderDir
        );
    }

    // ================================
    // GET - Supports both tenant users and regular users
    // ================================
    public function get(int $id, string $language = 'ar', ?int $tenantId = null, ?int $ownerId = null): array
    {
        return $this->service->get($id, $language, $tenantId, $ownerId);
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    // ================================
    // UPDATE - Supports both tenant users and regular users
    // ================================
    public function update(int $id, array $data, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        return $this->service->update($id, $data, $tenantId, $ownerId);
    }

    // ================================
    // DELETE - Supports both tenant users and regular users
    // ================================
    public function delete(int $id, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        return $this->service->delete($id, $tenantId, $ownerId);
    }

    // ================================
    // GET BY OWNER
    // ================================
    public function getByOwner(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): array
    {
        return $this->service->getByOwner($ownerId, $ownerType, $tenantId);
    }

    // ================================
    // GET PRIMARY ADDRESS
    // ================================
    public function getPrimaryAddress(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): ?array
    {
        return $this->service->getPrimaryAddress($ownerId, $ownerType, $tenantId);
    }
}
