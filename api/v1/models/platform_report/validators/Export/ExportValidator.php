<?php
declare(strict_types=1);

/**
 * Export Validator
 * Validates export requests.
 */
final class ExportValidator
{
    private PlatformReportValidator $validator;

    public function __construct(PlatformReportValidator $validator)
    {
        $this->validator = $validator;
    }

    public function validateExportRequest(array $data): array
    {
        return $this->validator->validateExportRequest($data);
    }
}
