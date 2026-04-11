<?php
declare(strict_types=1);

/**
 * Schedule Controller
 * Handles report scheduling operations.
 */
final class ScheduleController
{
    private PlatformReportService $service;

    public function __construct(PlatformReportService $service)
    {
        $this->service = $service;
    }

    public function listSchedules(?int $tenantId): array
    {
        return $this->service->listSchedules($tenantId);
    }

    public function createSchedule(array $data): array
    {
        return $this->service->createSchedule($data);
    }
}
