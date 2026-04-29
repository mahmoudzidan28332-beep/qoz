<?php
declare(strict_types=1);

/**
 * ProductsValidator
 *
 * Strict validation logic for product data to ensure schema integrity
 * and prevent invalid data entry.
 */
final class ProductsValidator
{
    /**
     * Validate product data.
     *
     * @param array $data
     * @param bool $isUpdate
     * @throws InvalidArgumentException
     */
    public function validate(array $data, bool $isUpdate = false): void
    {
        $this->checkRequiredFields($data, $isUpdate);
        $this->checkStringLengths($data);
        $this->checkNumericFields($data);
        $this->checkStatusFields($data);
    }

    private function checkRequiredFields(array $data, bool $isUpdate): void
    {
        if ($isUpdate) {
            if (empty($data['id'])) {
                throw new InvalidArgumentException("ID is required for update.");
            }
            return;
        }

        $required = ['product_type_id', 'tenant_id', 'sku', 'slug', 'is_active'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Field '$field' is required.");
            }
        }
    }

    private function checkStringLengths(array $data): void
    {
        $limits = [
            'sku' => 100,
            'slug' => 255,
            'barcode' => 100
        ];

        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && mb_strlen((string)$data[$field]) > $limit) {
                throw new InvalidArgumentException("Field '$field' must be at most $limit characters.");
            }
        }
    }

    private function checkNumericFields(array $data): void
    {
        $fields = [
            'stock_quantity', 'low_stock_threshold', 'total_sales', 'rating_count',
            'weight', 'length', 'width', 'height', 'rating_average', 'tax_rate'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("Field '$field' must be numeric.");
            }
        }
    }

    private function checkStatusFields(array $data): void
    {
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException("is_active must be 0 or 1.");
        }

        if (isset($data['stock_status']) && !in_array($data['stock_status'], ['in_stock', 'out_of_stock', 'on_backorder'], true)) {
            throw new InvalidArgumentException("Invalid stock_status.");
        }
    }
}
