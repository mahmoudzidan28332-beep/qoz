<?php
declare(strict_types=1);

/**
 * Schedule Service
 * Handles report schedule creation and listing.
 */
final class ScheduleService
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

    public function createSchedule(array $data): array
    {
        $id = $this->repo->saveSchedule($data);
        return ['success' => true, 'id' => $id];
    }
}
