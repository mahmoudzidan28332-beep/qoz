<?php
declare(strict_types=1);

final class ProductPricingController
{
    private ProductPricingService $service;

    public function __construct(ProductPricingService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
