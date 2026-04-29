<?php
declare(strict_types=1);

/**
 * ProductsRepositoryInterface
 *
 * Defines the contract for product data access with mandatory tenant isolation.
 */
interface ProductsRepositoryInterface
{
    /**
     * List products with filters and pagination.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @param string $lang
     * @return array
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count products matching filters.
     *
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int;

    /**
     * Find a single product by ID.
     *
     * @param int $id
     * @param string $lang
     * @return array|null
     */
    public function find(int $id, string $lang = 'ar'): ?array;

    /**
     * Save (create or update) a product.
     *
     * @param array $data
     * @return int The product ID
     */
    public function save(array $data): int;

    /**
     * Delete a product by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get subscription product limit for the current tenant.
     *
     * @return array|null
     */
    public function getSubscriptionProductLimit(): ?array;

    /**
     * Count products for the current tenant.
     *
     * @return int
     */
    public function countByTenant(): int;
}
