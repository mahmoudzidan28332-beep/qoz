<?php
declare(strict_types=1);

final class EntityTranslationsService
{
    private PdoEntityTranslationsRepository $repo;

    public function __construct(PdoEntityTranslationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getByEntity(int $entityId, int $tenantId): array
    {
        return $this->repo->getByEntity($entityId, $tenantId);
    }

    public function save(array $data, int $tenantId): int
    {
        return $this->repo->save($data, $tenantId);
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->repo->delete($id, $tenantId);
    }

    public function getTenantIdByEntityId(int $entityId): ?int
    {
        return $this->repo->getTenantIdByEntityId($entityId);
    }
}
