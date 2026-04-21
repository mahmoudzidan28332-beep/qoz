<?php
declare(strict_types=1);

final class AddressesService
{
    private PdoAddressesRepository $repo;

    public function __construct(PdoAddressesRepository $repo)
    {
        $this->repo = $repo;
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
        return $this->repo->list(
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
        $item = $this->repo->find($id, $language, $tenantId, $ownerId);
        if (!$item) {
            throw new RuntimeException('Address not found', 404);
        }
        return $item;
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        AddressesValidator::validateCreate($data);
        return $this->repo->create($data);
    }

    // ================================
    // UPDATE - Supports both tenant users and regular users
    // ================================
    public function update(int $id, array $data, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        AddressesValidator::validateUpdate($data);
        return $this->repo->update($id, $data, $tenantId, $ownerId);
    }

    // ================================
    // DELETE - Supports both tenant users and regular users
    // ================================
    public function delete(int $id, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        return $this->repo->delete($id, $tenantId, $ownerId);
    }

    // ================================
    // GET BY OWNER
    // ================================
    public function getByOwner(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): array
    {
        return $this->repo->getByOwner($ownerId, $ownerType, $tenantId);
    }

    // ================================
    // GET PRIMARY ADDRESS
    // ================================
    public function getPrimaryAddress(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): ?array
    {
        return $this->repo->getPrimaryAddress($ownerId, $ownerType, $tenantId);
    }
}
