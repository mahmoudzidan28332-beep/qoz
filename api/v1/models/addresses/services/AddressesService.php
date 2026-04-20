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
    // GET
    // ================================
    public function get(int $id, string $language = 'ar', ?int $tenantId = null): array
    {
        $item = $this->repo->find($id, $language, $tenantId ?? 0);
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
    // UPDATE (scoped by tenant_id for multi-tenant safety)
    // ================================
    public function update(int $id, array $data, int $tenantId = 0): bool
    {
        AddressesValidator::validateUpdate($data);
        return $this->repo->update($id, $data, $tenantId);
    }

    // ================================
    // DELETE (scoped by tenant_id for multi-tenant safety)
    // ================================
    public function delete(int $id, int $tenantId = 0): bool
    {
        return $this->repo->delete($id, $tenantId);
    }
}