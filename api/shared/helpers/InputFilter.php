<?php
declare(strict_types=1);

/**
 * InputFilter — Central mass-assignment protection helper.
 *
 * Provides a simple, reusable method to whitelist allowed fields
 * before any INSERT/UPDATE operation. This prevents attackers from
 * injecting unexpected columns through JSON payloads.
 *
 * Usage in repositories:
 *   $data = InputFilter::filter($data, self::ALLOWED_COLUMNS);
 *
 * Or using the built-in PHP equivalent directly:
 *   $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
 */
final class InputFilter
{
    /**
     * Filter an associative array to only include allowed keys.
     *
     * @param array $data           The raw input data (e.g., from json_decode)
     * @param array $allowedFields  Indexed array of allowed field names
     * @return array                Filtered data containing only allowed keys
     */
    public static function filter(array $data, array $allowedFields): array
    {
        return array_intersect_key($data, array_flip($allowedFields));
    }
}
