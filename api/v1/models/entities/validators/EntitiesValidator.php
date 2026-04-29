<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitiesValidator
{
    /** @var string[] $validVendorTypeCodes empty = skip DB check */
    private array $validVendorTypeCodes;

    public function __construct(array $validVendorTypeCodes = [])
    {
        $this->validVendorTypeCodes = $validVendorTypeCodes;
    }

    public function validate(array $data, bool $update = false): void
    {
        // الحقول الأساسية المطلوبة عند الإنشاء
        $requiredFields = [
            'user_id',
            'store_name',
            'slug',
            'vendor_type',
            'store_type',
            'phone',
            'email'
        ];

        if (!$update) {
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Field '{$field}' is required");
                }
            }
        }

        // تحقق ديناميكي من vendor_type مقابل جدول entity_types
        if (isset($data['vendor_type'])) {
            if (empty($data['vendor_type'])) {
                throw new InvalidArgumentException("Field 'vendor_type' is required");
            }
            if (!empty($this->validVendorTypeCodes) &&
                !in_array($data['vendor_type'], $this->validVendorTypeCodes, true)) {
                throw new InvalidArgumentException("Field 'vendor_type' has invalid value");
            }
        }

        if (isset($data['store_type']) && !in_array($data['store_type'], ['individual','company','brand'], true)) {
            throw new InvalidArgumentException("Field 'store_type' has invalid value");
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Field 'email' is not a valid email");
        }

        if (isset($data['phone']) && !preg_match('/^[0-9+\-\s]{5,20}$/', $data['phone'])) {
            throw new InvalidArgumentException("Field 'phone' has invalid format");
        }

        if (isset($data['mobile']) && !empty($data['mobile']) && !preg_match('/^[0-9+\-\s]{5,20}$/', $data['mobile'])) {
            throw new InvalidArgumentException("Field 'mobile' has invalid format");
        }

        // التحقق من parent_id
        if (isset($data['parent_id']) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            if (!is_numeric($data['parent_id']) || (int)$data['parent_id'] <= 0) {
                throw new InvalidArgumentException("Field 'parent_id' must be a positive integer or null");
            }
            // لا يمكن أن يكون الكيان أبًا لنفسه
            if (isset($data['id']) && (int)$data['parent_id'] === (int)$data['id']) {
                throw new InvalidArgumentException("Entity cannot be its own parent");
            }
        }
    }
}