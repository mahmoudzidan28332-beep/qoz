<?php
declare(strict_types=1);

/**
 * Export Repository
 * Handles export-related database operations.
 * Delegates to the main PdoPlatformReportRepository for actual queries.
 */
final class ExportRepository
{
    private PdoPlatformReportRepository $repo;

    public function __construct(PdoPlatformReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createExport(array $data): int
    {
        return $this->repo->createExport($data);
    }

    public function listExports(?int $tenantId, int $limit = 20): array
    {
        return $this->repo->listExports($tenantId, $limit);
    }
}
