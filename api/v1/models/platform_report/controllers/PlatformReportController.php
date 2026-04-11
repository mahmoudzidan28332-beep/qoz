<?php
declare(strict_types=1);

/**
 * Platform Report Controller
 * Thin controller that delegates to the service layer.
 */
final class PlatformReportController
{
    private PlatformReportService $service;

    public function __construct(PlatformReportService $service)
    {
        $this->service = $service;
    }

    public function getReportTypes(): array
    {
        return $this->service->getReportTypes();
    }

    public function generateReport(array $params): array
    {
        return $this->service->generateReport($params);
    }

    public function getDashboardSummary(?int $tenantId = null): array
    {
        return $this->service->getDashboardSummary($tenantId);
    }

    public function requestExport(array $params): array
    {
        return $this->service->requestExport($params);
    }

    public function listExports(?int $tenantId): array
    {
        return $this->service->listExports($tenantId);
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
