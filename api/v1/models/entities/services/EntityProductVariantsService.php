<?php
declare(strict_types=1);

final class EntityProductVariantsService
{
    private PdoEntityProductVariantsRepository $repo;

    public function __construct(PdoEntityProductVariantsRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List entity product variants with filtering and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
            ]
        ];
    }

    /**
     * Get a single entity product variant
     */
    public function get(int $id, int $tenantId, int $entityId): ?array
    {
        return $this->repo->find($id, $tenantId, $entityId);
    }

    /**
     * Get by entity and variant
     */
    public function getByEntityAndVariant(int $entityId, int $variantId, int $tenantId): ?array
    {
        return $this->repo->findByEntityAndVariant($entityId, $variantId, $tenantId);
    }

    /**
     * Get all variants for an entity
     */
    public function getEntityVariants(int $entityId, int $tenantId): array
    {
        return $this->repo->getEntityVariants($entityId, $tenantId);
    }

    /**
     * Get variants for a specific entity product
     */
    public function getEntityProductVariants(int $entityId, int $productId, int $tenantId): array
    {
        return $this->repo->getEntityProductVariants($entityId, $productId, $tenantId);
    }

    /**
     * Create a new entity product variant
     */
    public function create(array $data): int
    {
        EntityProductVariantsValidator::validateCreate($data);
        return $this->repo->save($data);
    }

    /**
     * Update an existing entity product variant
     */
    public function update(int $id, array $data): void
    {
        $tenantId = (int)($data['tenant_id'] ?? 0);
        $entityId = (int)($data['entity_id'] ?? 0);
        
        if ($tenantId <= 0 || $entityId <= 0) {
            throw new RuntimeException("tenant_id and entity_id are required for update");
        }

        $existing = $this->repo->find($id, $tenantId, $entityId);
        if (!$existing) {
            throw new RuntimeException("Entity product variant not found or access denied");
        }

        EntityProductVariantsValidator::validateUpdate($data);
        $this->repo->save(array_merge(['id' => $id], $data));
    }

    /**
     * Bulk save variants for an entity
     */
    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        EntityProductVariantsValidator::validateBulkSave($entityId, $variants);
        return $this->repo->saveEntityVariants($entityId, $tenantId, $variants);
    }

    /**
     * Delete an entity product variant
     */
    public function delete(int $id, int $tenantId, int $entityId): void
    {
        if (!$this->repo->find($id, $tenantId, $entityId)) {
            throw new RuntimeException("Entity product variant not found or access denied");
        }

        $this->repo->delete($id, $tenantId, $entityId);
    }

    /**
     * Delete all variants for an entity
     */
    public function deleteEntityVariants(int $entityId, int $tenantId): void
    {
        $this->repo->deleteEntityVariants($entityId, $tenantId);
    }

    /**
     * Delete all variants for a specific entity product
     */
    public function deleteEntityProductVariants(int $entityId, int $productId, int $tenantId): void
    {
        $this->repo->deleteEntityProductVariants($entityId, $productId, $tenantId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(int $tenantId): array
    {
        return $this->repo->getStatistics($tenantId);
    }
}