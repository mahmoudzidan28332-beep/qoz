<?php
declare(strict_types=1);

use InvalidArgumentException;

final class UserDevicesValidator
{
    /**
     * Validate data for create/update
     */
    public function validate(array $data, bool $isUpdate = false): void
    {
        // Required fields for create (fcm_token is optional — allows session-based device tracking)
        if (!$isUpdate) {
            if (!isset($data['user_id']) || trim((string)$data['user_id']) === '') {
                throw new InvalidArgumentException("Field 'user_id' is required.");
            }
        }

        // Validate user_id (positive integer)
        if (isset($data['user_id']) && (!is_numeric($data['user_id']) || (int)$data['user_id'] <= 0)) {
            throw new InvalidArgumentException("user_id must be a positive integer.");
        }

        // Validate fcm_token (allow null; reject empty strings)
        if (isset($data['fcm_token']) && $data['fcm_token'] !== null && trim($data['fcm_token']) === '') {
            throw new InvalidArgumentException("fcm_token cannot be an empty string; pass null or omit it.");
        }

        // Validate device_type (allowed values)
        if (isset($data['device_type']) && !in_array($data['device_type'], ['web', 'android', 'ios', 'other'], true)) {
            throw new InvalidArgumentException("device_type must be one of: web, android, ios, other.");
        }

        // Validate device_name (optional, max length)
        if (isset($data['device_name']) && strlen($data['device_name']) > 100) {
            throw new InvalidArgumentException("device_name must be at most 100 characters.");
        }

        // Validate is_active (0 or 1)
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException("is_active must be 0 or 1.");
        }

        // Validate ip (optional, basic format check)
        if (isset($data['ip']) && !filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Invalid IP address format.");
        }
    }
}