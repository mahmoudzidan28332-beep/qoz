<?php
declare(strict_types=1);

/**
 * Aggregation Service
 * Provides aggregation helpers for report data.
 */
final class AggregationService
{
    private PdoPlatformReportRepository $repo;

    public function __construct(PdoPlatformReportRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Get stored/cached aggregation stats.
     */
    public function getStoredStats(
        string $reportType,
        string $periodType,
        string $startDate,
        string $endDate,
        ?int $tenantId = null,
        ?int $entityId = null
    ): array {
        return $this->repo->getStoredStats($reportType, $periodType, $startDate, $endDate, $tenantId, $entityId);
    }

    /**
     * Save aggregated stats snapshot.
     */
    public function saveStats(array $data): int
    {
        return $this->repo->saveStats($data);
    }
}
