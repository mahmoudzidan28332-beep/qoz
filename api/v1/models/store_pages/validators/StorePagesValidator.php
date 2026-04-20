<?php
declare(strict_types=1);

// api/v1/models/store_pages/validators/StorePagesValidator.php

final class StorePagesValidator
{
    private const ALLOWED_PAGE_TYPES = ['store', 'landing'];

    private const ALLOWED_SECTION_TYPES = [
        'header', 'contact', 'tabs', 'products', 'info',
        'hours', 'location', 'offers', 'reviews', 'policies',
    ];

    public static function validatePage(array $data): array
    {
        $errors = [];

        // type (required)
        if (empty($data['type'])) {
            $errors['type'] = 'Page type is required';
        } elseif (!in_array($data['type'], self::ALLOWED_PAGE_TYPES, true)) {
            $errors['type'] = 'Invalid page type';
        }

        // entity_id (optional, positive integer)
        if (isset($data['entity_id']) && $data['entity_id'] !== null && $data['entity_id'] !== '') {
            if (!is_numeric($data['entity_id']) || (int)$data['entity_id'] < 1) {
                $errors['entity_id'] = 'entity_id must be a positive integer';
            }
        }

        // slug (optional)
        if (isset($data['slug']) && strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long (max 255 characters)';
        }

        // is_active
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        // settings (optional, must be valid JSON if string)
        if (isset($data['settings']) && is_string($data['settings'])) {
            $decoded = json_decode($data['settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['settings'] = 'Settings must be valid JSON';
            }
        }

        return $errors;
    }

    public static function validateSection(array $data): array
    {
        $errors = [];

        // type (required)
        if (empty($data['type'])) {
            $errors['type'] = 'Section type is required';
        } elseif (!in_array($data['type'], self::ALLOWED_SECTION_TYPES, true)) {
            $errors['type'] = 'Invalid section type. Allowed: ' . implode(', ', self::ALLOWED_SECTION_TYPES);
        }

        // position (optional, non-negative integer)
        if (isset($data['position']) && (!is_numeric($data['position']) || (int)$data['position'] < 0)) {
            $errors['position'] = 'Position must be a non-negative integer';
        }

        // is_active
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        // settings (optional, must be valid JSON if string)
        if (isset($data['settings']) && is_string($data['settings'])) {
            $decoded = json_decode($data['settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['settings'] = 'Settings must be valid JSON';
            }
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $trans) {
                if (isset($trans['title']) && strlen($trans['title']) > 255) {
                    $errors['translations'][$lang]['title'] = 'Translation title is too long (max 255)';
                }
            }
        }

        return $errors;
    }
}