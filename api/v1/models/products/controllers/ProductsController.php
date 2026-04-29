<?php
declare(strict_types=1);

/**
 * ProductsController
 *
 * Entry point for product-related requests. Coordinates between
 * routes and the ProductsService.
 */
final class ProductsController
{
    private ProductsService $service;

    public function __construct(ProductsService $service)
    {
        $this->service = $service;
    }

    /**
     * List products.
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
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    /**
     * Get a product.
     *
     * @param int $id
     * @param string $lang
     * @return array|null
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($id, $lang);
    }

    /**
     * Create a product.
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update a product.
     *
     * @param array $data
     * @return int
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a product.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Check product limits.
     *
     * @return array|null
     */
    public function getSubscriptionProductLimit(): ?array
    {
        return $this->service->getSubscriptionProductLimit();
    }

    /**
     * Count products by tenant.
     *
     * @return int
     */
    public function countByTenant(): int
    {
        return $this->service->countByTenant();
    }
}