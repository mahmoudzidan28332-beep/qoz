<?php
declare(strict_types=1);

final class EntityProductVariantsValidator
{
    private const VALID_STOCK_STATUSES = ['in_stock', 'out_of_stock', 'unlimited'];

    /**
     * Validate data for creating a new entity product variant
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric");
        }

        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException("Field 'product_id' is required and must be numeric");
        }

        if (empty($data['variant_id']) || !is_numeric($data['variant_id'])) {
            throw new InvalidArgumentException("Field 'variant_id' is required and must be numeric");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate data for updating an existing entity product variant
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        if (isset($data['id']) && !is_numeric($data['id'])) {
            throw new InvalidArgumentException("Field 'id' must be numeric");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate bulk save data
     */
    public static function validateBulkSave(int $entityId, array $variants): void
    {
        if ($entityId <= 0) {
            throw new InvalidArgumentException("Entity ID must be positive");
        }

        if (empty($variants)) {
            throw new InvalidArgumentException("No variants provided for bulk save");
        }

        foreach ($variants as $index => $variantData) {
            if (!is_array($variantData)) {
                throw new InvalidArgumentException("Variant at index $index must be an array");
            }

            $tempData = array_merge(['entity_id' => $entityId], $variantData);
            self::validateCreate($tempData);
        }
    }

    /**
     * Validate common fields
     */
    private static function validateCommonFields(array $data): void
    {
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric");
        }

        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException("product_id must be numeric");
        }

        if (isset($data['variant_id']) && !is_numeric($data['variant_id'])) {
            throw new InvalidArgumentException("variant_id must be numeric");
        }

        if (isset($data['tenant_id']) && !is_numeric($data['tenant_id'])) {
            throw new InvalidArgumentException("tenant_id must be numeric");
        }

        if (isset($data['stock_quantity'])) {
            if (!is_numeric($data['stock_quantity']) || (int)$data['stock_quantity'] < 0) {
                throw new InvalidArgumentException("stock_quantity must be a non-negative integer");
            }
        }

        if (isset($data['low_stock_threshold'])) {
            if (!is_numeric($data['low_stock_threshold']) || (int)$data['low_stock_threshold'] < 0) {
                throw new InvalidArgumentException("low_stock_threshold must be a non-negative integer");
            }
        }

        if (isset($data['stock_status'])) {
            if (!in_array($data['stock_status'], self::VALID_STOCK_STATUSES, true)) {
                throw new InvalidArgumentException("stock_status must be one of: " . implode(', ', self::VALID_STOCK_STATUSES));
            }
        }

        if (isset($data['manage_stock'])) {
            if (!in_array($data['manage_stock'], [0, 1, '0', '1', true, false], true)) {
                throw new InvalidArgumentException("manage_stock must be 0 or 1");
            }
        }

        if (isset($data['is_active'])) {
            if (!in_array($data['is_active'], [0, 1, '0', '1', true, false], true)) {
                throw new InvalidArgumentException("is_active must be 0 or 1");
            }
        }

        if (isset($data['is_featured'])) {
            if (!in_array($data['is_featured'], [0, 1, '0', '1', true, false], true)) {
                throw new InvalidArgumentException("is_featured must be 0 or 1");
            }
        }
    }
}
