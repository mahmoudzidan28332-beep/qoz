<?php
declare(strict_types=1);

/**
 * Report Controller
 * Handles report generation and dashboard summary.
 */
final class ReportController
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
}
