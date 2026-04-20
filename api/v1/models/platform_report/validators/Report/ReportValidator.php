<?php
declare(strict_types=1);

/**
 * Report Validator
 * Validates report generation requests.
 */
final class ReportValidator
{
    private PlatformReportValidator $validator;

    public function __construct(PlatformReportValidator $validator)
    {
        $this->validator = $validator;
    }

    public function validateReportRequest(array $data): array
    {
        return $this->validator->validateReportRequest($data);
    }
}
