<?php
declare(strict_types=1);

/**
 * Schedule Validator
 * Validates schedule creation requests.
 */
final class ScheduleValidator
{
    private const ALLOWED_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['report_type'])) {
            $errors[] = 'report_type is required';
        }

        $frequency = $data['frequency'] ?? '';
        if (!empty($frequency) && !in_array($frequency, self::ALLOWED_FREQUENCIES, true)) {
            $errors[] = 'Invalid frequency. Allowed: ' . implode(', ', self::ALLOWED_FREQUENCIES);
        }

        if (!empty($data['recipients_email']) && !filter_var($data['recipients_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'recipients_email must be a valid email address';
        }

        return $errors;
    }
}
