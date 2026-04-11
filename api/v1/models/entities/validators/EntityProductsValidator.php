<?php
declare(strict_types=1);

final class EntityProductsValidator
{
    /**
     * Validate data for creating a new entity product
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric");
        }

        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException("Field 'product_id' is required and must be numeric");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate data for updating an existing entity product
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
    public static function validateBulkSave(int $entityId, array $products): void
    {
        if ($entityId <= 0) {
            throw new InvalidArgumentException("Entity ID must be positive");
        }

        if (empty($products)) {
            throw new InvalidArgumentException("No products provided for bulk save");
        }

        foreach ($products as $index => $productData) {
            if (!is_array($productData)) {
                throw new InvalidArgumentException("Product at index $index must be an array");
            }

            $tempData = array_merge(['entity_id' => $entityId], $productData);
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