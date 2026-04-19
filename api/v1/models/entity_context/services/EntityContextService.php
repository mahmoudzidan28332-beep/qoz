<?php
declare(strict_types=1);

final class EntityContextService
{
    private PdoEntityContextRepository $repo;

    public function __construct(PdoEntityContextRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getEntitiesWithContext(string $lang, int $tenantId, array $entityIds = []): array
    {
        return $this->repo->getEntitiesWithContext($lang, $tenantId, $entityIds);
    }

    public function getWorkingHours(array $entityIds): array
    {
        return $this->repo->getWorkingHours($entityIds);
    }
}
