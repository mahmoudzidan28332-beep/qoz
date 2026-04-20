<?php
declare(strict_types=1);

/**
 * Schedule Repository
 * Handles schedule-related database operations.
 * Delegates to the main PdoPlatformReportRepository for actual queries.
 */
final class ScheduleRepository
{
    private PdoPlatformReportRepository $repo;

    public function __construct(PdoPlatformReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function listSchedules(?int $tenantId): array
    {
        return $this->repo->listSchedules($tenantId);
    }

    public function saveSchedule(array $data): int
    {
        return $this->repo->saveSchedule($data);
    }
}
