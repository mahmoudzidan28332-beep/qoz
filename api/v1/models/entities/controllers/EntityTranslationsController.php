<?php
declare(strict_types=1);

final class EntityTranslationsController
{
    private EntityTranslationsService $service;

    public function __construct(EntityTranslationsService $service)
    {
        $this->service = $service;
    }

    public function getByEntity(int $entityId, int $tenantId): array
    {
        return $this->service->getByEntity($entityId, $tenantId);
    }

    public function save(array $data, int $tenantId): int
    {
        return $this->service->save($data, $tenantId);
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->service->delete($id, $tenantId);
    }

    public function getTenantIdByEntityId(int $entityId): ?int
    {
        return $this->service->getTenantIdByEntityId($entityId);
    }
}
