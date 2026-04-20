<?php
declare(strict_types=1);

/**
 * Core Event Validator
 */
final class CoreEventValidator
{
    private const ALLOWED_ENTITY_TYPES = ['product', 'entity', 'brand', 'category', 'job', 'auction'];
    private const ALLOWED_EVENT_TYPES  = ['view', 'click', 'favorite', 'contact', 'add_to_cart', 'purchase'];

    public function validateCreate(array $data): array
    {
        $errors = [];

        $entityType = $data['entity_type'] ?? '';
        if (!in_array($entityType, self::ALLOWED_ENTITY_TYPES, true)) {
            $errors[] = 'entity_type is required. Allowed: ' . implode(', ', self::ALLOWED_ENTITY_TYPES);
        }

        if (empty($data['entity_id']) || !ctype_digit((string) $data['entity_id'])) {
            $errors[] = 'entity_id is required and must be a positive integer';
        }

        $eventType = $data['event_type'] ?? '';
        if (!in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            $errors[] = 'event_type is required. Allowed: ' . implode(', ', self::ALLOWED_EVENT_TYPES);
        }

        return $errors;
    }

    public function validateFilters(array $data): array
    {
        $errors = [];

        if (!empty($data['entity_type']) && !in_array($data['entity_type'], self::ALLOWED_ENTITY_TYPES, true)) {
            $errors[] = 'Invalid entity_type. Allowed: ' . implode(', ', self::ALLOWED_ENTITY_TYPES);
        }

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
