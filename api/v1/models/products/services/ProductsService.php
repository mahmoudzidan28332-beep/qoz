<?php
declare(strict_types=1);

/**
 * ProductsService
 *
 * Orchestrates product operations, ensuring business logic, security,
 * and data integrity are maintained.
 */
final class ProductsService
{
    private ProductsRepositoryInterface $repo;

    public function __construct(ProductsRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List products with metadata.
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        try {
            $items = $this->repo->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
            $total = $this->repo->count($filters);

            return [
                'items' => $items,
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log('[ProductsService] List failed: ' . $e->getMessage());
            throw new RuntimeException('Operation failed', 0, $e);
        }
    }

    /**
     * Get a single product by ID.
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        try {
            return $this->repo->find($id, $lang);
        } catch (Exception $e) {
            error_log('[ProductsService] Get failed: ' . $e->getMessage());
            throw new RuntimeException('Operation failed', 0, $e);
        }
    }

    /**
     * Create a product.
     */
    public function create(array $data): int
    {
        try {
            return $this->repo->save($data);
        } catch (Exception $e) {
            error_log('[ProductsService] Create failed: ' . $e->getMessage());
            throw new RuntimeException('Operation failed', 0, $e);
        }
    }

    /**
     * Update a product.
     */
    public function update(array $data): int
    {
        try {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID is required');
            }
            return $this->repo->save($data);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log('[ProductsService] Update failed: ' . $e->getMessage());
            throw new RuntimeException('Operation failed', 0, $e);
        }
    }

    /**
     * Delete a product.
     */
    public function delete(int $id): bool
    {
        try {
            return $this->repo->delete($id);
        } catch (Exception $e) {
            error_log('[ProductsService] Delete failed: ' . $e->getMessage());
            throw new RuntimeException('Operation failed', 0, $e);
        }
    }

    /**
     * Check product limits.
     */
    public function getSubscriptionProductLimit(): ?array
    {
        return $this->repo->getSubscriptionProductLimit();
    }

    /**
     * Count products by tenant.
     */
    public function countByTenant(): int
    {
        return $this->repo->countByTenant();
    }
}