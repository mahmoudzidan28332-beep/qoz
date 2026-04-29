<?php
declare(strict_types=1);

final class ProductsController
{
    private ProductsService $service;

    public function __construct(ProductsService $service)
    {
        $this->service = $service;
    }

    /**
     * List products with filters, ordering, and pagination
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
     * Get a single product by ID
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
     * Create a new product
     *
     * @param array $data
     * @return int New product ID
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing product
     *
     * @param array $data Must include 'id'
     * @return int Updated product ID
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a product by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Get subscription product limit for a tenant
     *
     * @return array|null
     */
    public function getSubscriptionProductLimit(): ?array
    {
        return $this->service->getSubscriptionProductLimit();
    }

    /**
     * Count products by tenant
     *
     * @return int
     */
    public function countByTenant(): int
    {
        return $this->service->countByTenant();
    }
}