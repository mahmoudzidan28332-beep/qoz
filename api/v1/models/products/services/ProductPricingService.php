<?php
declare(strict_types=1);

final class ProductPricingService
{
    private PdoProductPricingRepository $repo;

    public function __construct(PdoProductPricingRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return [
            'items' => $this->repo->list($limit, $offset, $filters, $orderBy, $orderDir),
            'total' => $this->repo->count($filters)
        ];
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $data): int
    {
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException("ID is required");
        }
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
