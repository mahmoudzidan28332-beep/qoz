<?php
declare(strict_types=1);

/**
 * Export Controller
 * Handles report export requests.
 */
final class ExportController
{
    private PlatformReportService $service;

    public function __construct(PlatformReportService $service)
    {
        $this->service = $service;
    }

    public function requestExport(array $params): array
    {
        return $this->service->requestExport($params);
    }

    public function listExports(?int $tenantId): array
    {
        return $this->service->listExports($tenantId);
    }
}
