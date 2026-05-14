<?php
declare(strict_types=1);

final class NotificationRecipientsValidator
{
    private const VALID_RECIPIENT_TYPES = ['user', 'entity', 'tenant'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if ($isUpdate) {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
            return;
        }

        if (empty($data['notification_id']) || !is_numeric($data['notification_id'])) {
            throw new InvalidArgumentException('Field "notification_id" is required and must be numeric.');
        }
        if (empty($data['recipient_id']) || !is_numeric($data['recipient_id'])) {
            throw new InvalidArgumentException('Field "recipient_id" is required and must be numeric.');
        }
        if (empty($data['recipient_type'])) {
            throw new InvalidArgumentException('Field "recipient_type" is required.');
        }
        if (!in_array($data['recipient_type'], self::VALID_RECIPIENT_TYPES, true)) {
            throw new InvalidArgumentException(
                'Field "recipient_type" must be one of: ' . implode(', ', self::VALID_RECIPIENT_TYPES) . '.'
            );
        }
    }
}
