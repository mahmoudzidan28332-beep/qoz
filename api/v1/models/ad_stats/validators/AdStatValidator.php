<?php
declare(strict_types=1);

/**
 * Ad Stat Validator
 */
final class AdStatValidator
{
    private const ALLOWED_EVENT_TYPES = ['view', 'click'];

    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['ad_id']) || !ctype_digit((string) $data['ad_id'])) {
            $errors[] = 'ad_id is required and must be a positive integer';
        }

        if (!empty($data['event_type']) && !in_array($data['event_type'], self::ALLOWED_EVENT_TYPES, true)) {
            $errors[] = 'Invalid event_type. Allowed: ' . implode(', ', self::ALLOWED_EVENT_TYPES);
        }

        return $errors;
    }

    public function validateFilters(array $data): array
    {
        $errors = [];

        if (!empty($data['event_type']) && !in_array($data['event_type'], self::ALLOWED_EVENT_TYPES, true)) {
            $errors[] = 'Invalid event_type. Allowed: ' . implode(', ', self::ALLOWED_EVENT_TYPES);
        }

        if (!empty($data['start_date']) && !$this->isValidDate($data['start_date'])) {
            $errors[] = 'start_date must be YYYY-MM-DD';
        }

        if (!empty($data['end_date']) && !$this->isValidDate($data['end_date'])) {
            $errors[] = 'end_date must be YYYY-MM-DD';
        }

        return $errors;
    }

    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }
}
