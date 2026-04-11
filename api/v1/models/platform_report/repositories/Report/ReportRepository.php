<?php
declare(strict_types=1);

/**
 * Report Repository
 * Handles report-related database operations (report types, stored stats, live aggregations).
 * Delegates to the main PdoPlatformReportRepository for actual queries.
 */
final class ReportRepository
{
    private PdoPlatformReportRepository $repo;

    public function __construct(PdoPlatformReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function allReportTypes(): array
    {
        return $this->repo->allReportTypes();
    }

    public function getStoredStats(string $reportType, string $periodType, string $startDate, string $endDate, ?int $tenantId = null, ?int $entityId = null): array
    {
        return $this->repo->getStoredStats($reportType, $periodType, $startDate, $endDate, $tenantId, $entityId);
    }

    public function saveStats(array $data): int
    {
        return $this->repo->saveStats($data);
    }
}
