<?php
declare(strict_types=1);

final class ProductsService
{
    private PdoProductsRepository $repo;

    public function __construct(PdoProductsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        if (!is_array($filters)) {
            $filters = [];
        }
        $items = $this->repo->list(
            $limit, $offset, $filters, $orderBy, $orderDir, $lang
        );
        $total = $this->repo->count($filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    public function create(array $data): int
    {
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function getSubscriptionProductLimit(): ?array
    {
        return $this->repo->getSubscriptionProductLimit();
    }

    public function countByTenant(): int
    {
        return $this->repo->countByTenant();
    }
}