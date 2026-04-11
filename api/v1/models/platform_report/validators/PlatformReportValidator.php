<?php
declare(strict_types=1);

/**
 * Platform Report Validator
 */
final class PlatformReportValidator
{
    private const ALLOWED_REPORT_TYPES = [
        'sales_overview',
        'revenue_profit',
        'orders_performance',
        'products_performance',
        'ads_performance',
        'returns_complaints',
        'entities_performance',
        'customer_behavior',
        'delivery_performance',
        'platform_health',
    ];

    private const ALLOWED_PERIOD_TYPES = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
    private const ALLOWED_GROUP_BY     = ['day', 'week', 'month'];
    private const ALLOWED_EXPORT_FORMATS = ['excel', 'pdf', 'csv'];

    public function validateReportRequest(array $data): array
    {
        $errors = [];

        $reportType = $data['report_type'] ?? '';
        if (!in_array($reportType, self::ALLOWED_REPORT_TYPES, true)) {
            $errors[] = 'Invalid report_type. Allowed: ' . implode(', ', self::ALLOWED_REPORT_TYPES);
        }

        if (empty($data['start_date']) || !$this->isValidDate($data['start_date'])) {
            $errors[] = 'start_date is required and must be a valid date (YYYY-MM-DD)';
        }

        if (empty($data['end_date']) || !$this->isValidDate($data['end_date'])) {
            $errors[] = 'end_date is required and must be a valid date (YYYY-MM-DD)';
        }

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if ($data['start_date'] > $data['end_date']) {
                $errors[] = 'start_date must be before or equal to end_date';
            }
        }

        if (!empty($data['period_type']) && !in_array($data['period_type'], self::ALLOWED_PERIOD_TYPES, true)) {
            $errors[] = 'Invalid period_type. Allowed: ' . implode(', ', self::ALLOWED_PERIOD_TYPES);
        }

        if (!empty($data['group_by']) && !in_array($data['group_by'], self::ALLOWED_GROUP_BY, true)) {
            $errors[] = 'Invalid group_by. Allowed: ' . implode(', ', self::ALLOWED_GROUP_BY);
        }

        if (isset($data['tenant_id']) && $data['tenant_id'] !== '' && !ctype_digit((string)$data['tenant_id'])) {
            $errors[] = 'tenant_id must be a positive integer';
        }

        return $errors;
    }

    public function validateExportRequest(array $data): array
    {
        $errors = $this->validateReportRequest($data);

        $format = $data['export_format'] ?? '';
        if (!in_array($format, self::ALLOWED_EXPORT_FORMATS, true)) {
            $errors[] = 'Invalid export_format. Allowed: ' . implode(', ', self::ALLOWED_EXPORT_FORMATS);
        }

        return $errors;
    }

    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }
}
