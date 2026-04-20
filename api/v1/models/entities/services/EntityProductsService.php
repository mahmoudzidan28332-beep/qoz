<?php
declare(strict_types=1);

final class EntityProductsService
{
    private PdoEntityProductsRepository $repo;

    public function __construct(PdoEntityProductsRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List entity products with filtering and pagination
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
     * Get a single entity product
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get by entity and product
     */
    public function getByEntityAndProduct(int $entityId, int $productId): ?array
    {
        return $this->repo->findByEntityAndProduct($entityId, $productId);
    }

    /**
     * Get all products for an entity
     */
    public function getEntityProducts(int $entityId): array
    {
        return $this->repo->getEntityProducts($entityId);
    }

    /**
     * Create a new entity product
     */
    public function create(array $data): int
    {
        EntityProductsValidator::validateCreate($data);
        return $this->repo->save($data);
    }

    /**
     * Update an existing entity product
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Entity product not found");
        }

        EntityProductsValidator::validateUpdate($data);
        $this->repo->save(array_merge(['id' => $id], $data));
    }

    /**
     * Bulk save products for an entity
     */
    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        EntityProductsValidator::validateBulkSave($entityId, $products);
        return $this->repo->saveEntityProducts($entityId, $tenantId, $products);
    }

    /**
     * Delete an entity product
     */
    public function delete(int $id): void
    {
        if (!$this->repo->find($id)) {
            throw new RuntimeException("Entity product not found");
        }

        $this->repo->delete($id);
    }

    /**
     * Delete all products for an entity
     */
    public function deleteEntityProducts(int $entityId): void
    {
        $this->repo->deleteEntityProducts($entityId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return $this->repo->getStatistics();
    }
}
