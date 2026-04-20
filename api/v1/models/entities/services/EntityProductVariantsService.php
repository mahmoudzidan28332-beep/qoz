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
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get by entity and variant
     */
    public function getByEntityAndVariant(int $entityId, int $variantId): ?array
    {
        return $this->repo->findByEntityAndVariant($entityId, $variantId);
    }

    /**
     * Get all variants for an entity
     */
    public function getEntityVariants(int $entityId): array
    {
        return $this->repo->getEntityVariants($entityId);
    }

    /**
     * Get variants for a specific entity product
     */
    public function getEntityProductVariants(int $entityId, int $productId): array
    {
        return $this->repo->getEntityProductVariants($entityId, $productId);
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
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Entity product variant not found");
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
    public function delete(int $id): void
    {
        if (!$this->repo->find($id)) {
            throw new RuntimeException("Entity product variant not found");
        }

        $this->repo->delete($id);
    }

    /**
     * Delete all variants for an entity
     */
    public function deleteEntityVariants(int $entityId): void
    {
        $this->repo->deleteEntityVariants($entityId);
    }

    /**
     * Delete all variants for a specific entity product
     */
    public function deleteEntityProductVariants(int $entityId, int $productId): void
    {
        $this->repo->deleteEntityProductVariants($entityId, $productId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return $this->repo->getStatistics();
    }
}
