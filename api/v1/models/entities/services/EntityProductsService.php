<?php
declare(strict_types=1);

final class EntityProductsService
{
    private PdoEntityProductsRepository $repo;

    public function __construct(PdoEntityProductsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
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

    public function get(int $id, int $tenantId, int $entityId): ?array
    {
        return $this->repo->find($id, $tenantId, $entityId);
    }

    public function getEntityProducts(int $entityId, int $tenantId, string $lang = 'ar'): array
    {
        return $this->repo->getEntityProducts($entityId, $lang, $tenantId);
    }

    public function create(array $data): int
    {
        EntityProductsValidator::validateCreate($data);
        return $this->repo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $tenantId = (int)($data['tenant_id'] ?? 0);
        $entityId = (int)($data['entity_id'] ?? 0);
        
        if ($tenantId <= 0 || $entityId <= 0) {
            throw new ApplicationException("tenant_id and entity_id are required for update");
        }

        $existing = $this->repo->find($id, $tenantId, $entityId);
        if (!$existing) {
            throw new ApplicationException("Entity product not found or access denied");
        }

        EntityProductsValidator::validateUpdate($data);
        $this->repo->save(array_merge(['id' => $id], $data));
    }

    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        EntityProductsValidator::validateBulkSave($entityId, $products);
        return $this->repo->saveEntityProducts($entityId, $tenantId, $products);
    }

    public function delete(int $id, int $tenantId, int $entityId): void
    {
        if (!$this->repo->find($id, $tenantId, $entityId)) {
            throw new ApplicationException("Entity product not found or access denied");
        }
        $this->repo->delete($id, $tenantId, $entityId);
    }

    public function deleteEntityProducts(int $entityId, int $tenantId): void
    {
        $this->repo->deleteEntityProducts($entityId, $tenantId);
    }

    public function getStatistics(int $tenantId): array
    {
        return $this->repo->getStatistics($tenantId);
    }
}