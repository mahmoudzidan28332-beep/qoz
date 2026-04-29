<?php
declare(strict_types=1);

final class EntityProductVariantsController
{
    private EntityProductVariantsService $service;

    public function __construct(EntityProductVariantsService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id, int $tenantId, int $entityId): ?array
    {
        return $this->service->get($id, $tenantId, $entityId);
    }

    public function getByEntityAndVariant(int $entityId, int $variantId, int $tenantId): ?array
    {
        return $this->service->getByEntityAndVariant($entityId, $variantId, $tenantId);
    }

    public function getEntityVariants(int $entityId, int $tenantId): array
    {
        return $this->service->getEntityVariants($entityId, $tenantId);
    }

    public function getEntityProductVariants(int $entityId, int $productId, int $tenantId): array
    {
        return $this->service->getEntityProductVariants($entityId, $productId, $tenantId);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): void
    {
        $this->service->update($id, $data);
    }

    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        return $this->service->saveEntityVariants($entityId, $tenantId, $variants);
    }

    public function delete(int $id, int $tenantId, int $entityId): void
    {
        $this->service->delete($id, $tenantId, $entityId);
    }

    public function deleteEntityVariants(int $entityId, int $tenantId): void
    {
        $this->service->deleteEntityVariants($entityId, $tenantId);
    }

    public function deleteEntityProductVariants(int $entityId, int $productId, int $tenantId): void
    {
        $this->service->deleteEntityProductVariants($entityId, $productId, $tenantId);
    }

    public function getStatistics(int $tenantId): array
    {
        return $this->service->getStatistics($tenantId);
    }
}