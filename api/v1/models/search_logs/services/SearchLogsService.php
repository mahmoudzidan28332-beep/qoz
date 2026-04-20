<?php
declare(strict_types=1);

final class SearchLogsService
{
    private PdoSearchLogsRepository $repo;

    public function __construct(PdoSearchLogsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function trackQuery(string $query, ?int $tenantId, ?int $userId, ?int $entityId, string $lang): void
    {
        $this->repo->trackQuery($query, $tenantId, $userId, $entityId, $lang);
    }

    public function popular(string $lang, ?int $tenantId, int $limit = 8): array
    {
        return $this->repo->popular($lang, $tenantId, $limit);
    }
}
