<?php
declare(strict_types=1);

/**
 * Export Report Service
 * Handles export request creation and listing.
 */
final class ExportReportService
{
    private PdoPlatformReportRepository $repo;
    private PlatformReportValidator $validator;

    public function __construct(PdoPlatformReportRepository $repo, PlatformReportValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function requestExport(array $params): array
    {
        $errors = $this->validator->validateExportRequest($params);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $exportId = $this->repo->createExport($params);
        return [
            'success'   => true,
            'export_id' => $exportId,
            'message'   => 'Export request created. It will be processed shortly.',
        ];
    }

    public function listExports(?int $tenantId): array
    {
        return $this->repo->listExports($tenantId);
    }
}
