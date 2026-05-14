<?php
declare(strict_types=1);

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

        foreach (['id', 'user_id', 'timezone_id'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                if (!is_int($data[$field]) && !preg_match('/^[1-9][0-9]*$/', (string)$data[$field])) {
                    throw new InvalidArgumentException("Field '{$field}' must be a positive integer");
                }
            }
        }

        if (isset($data['slug'])) {
            if (!is_string($data['slug']) || !preg_match('/^[a-z0-9]+(?:[a-z0-9_-]*[a-z0-9])?$/i', $data['slug'])) {
                throw new InvalidArgumentException("Field 'slug' has invalid format");
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

        if (isset($data['status']) && !in_array($data['status'], ['pending','approved','suspended','rejected'], true)) {
            throw new InvalidArgumentException("Field 'status' has invalid value");
        }

        if (isset($data['is_verified']) && !in_array((int)$data['is_verified'], [0, 1], true)) {
            throw new InvalidArgumentException("Field 'is_verified' has invalid value");
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

        if (isset($data['website_url']) && $data['website_url'] !== null && $data['website_url'] !== '') {
            if (!filter_var($data['website_url'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("Field 'website_url' is not a valid URL");
            }
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
