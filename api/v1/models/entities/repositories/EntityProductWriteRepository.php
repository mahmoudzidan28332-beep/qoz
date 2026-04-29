<?php

declare(strict_types=1);

/**
 * EntityProductWriteRepository
 * Handles all write operations (create, update, delete) for entity products.
 */
final class EntityProductWriteRepository
{
    private const ENTITY_PRODUCT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id',
        'stock_quantity', 'low_stock_threshold', 'is_active', 'is_featured',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityProductQueryRepository $queryRepo
    ) {}

    /**
     * Save a single record (insert or update)
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            }
        }

        if (empty($params[':entity_id']) || empty($params[':product_id'])) {
            throw new \InvalidArgumentException('entity_id and product_id are required');
        }

        if (empty($params[':tenant_id'])) {
            throw new \InvalidArgumentException('tenant_id is required');
        }

        $this->validateReferences(
            (int) $params[':entity_id'],
            (int) $params[':product_id'],
            (int) $params[':tenant_id'],
        );

        if ($isUpdate) {
            $params[':id'] = (int) $data['id'];
            $setClauses    = [];
            foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
                if (array_key_exists(':' . $col, $params)) {
                    $setClauses[] = "{$col} = :{$col}";
                }
            }
            $stmt = $this->pdo->prepare(
                'UPDATE entity_products SET '
                . implode(', ', $setClauses)
                . ' WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute($params);
            return (int) $data['id'];
        }

        $columns = $placeholders = [];
        foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
            if (array_key_exists(':' . $col, $params)) {
                $columns[]      = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO entity_products (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Bulk save products for an entity
     */
    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($products as $productData) {
                $productData['entity_id'] = $entityId;
                $productData['tenant_id'] = $tenantId;

                $existing = $this->queryRepo->findByEntityAndProduct(
                    $entityId,
                    (int) $productData['product_id'],
                    $tenantId,
                );
                if ($existing) {
                    $productData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($productData);

                $hasPrice = isset($productData['price'])
                    && $productData['price'] !== ''
                    && $productData['price'] !== null;

                if ($hasPrice) {
                    $this->saveEntityProductPricing($entityId, (int) $productData['product_id'], $productData, $tenantId);
                }
            }

            $this->pdo->commit();
            return $savedIds;

        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \RuntimeException('Failed to save entity products: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Manage entity-specific pricing for a product
     */
    private function saveEntityProductPricing(int $entityId, int $productId, array $data, int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT pp.id FROM product_pricing pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.product_id = :product_id
               AND pp.entity_id  = :entity_id
               AND pp.variant_id IS NULL
               AND p.tenant_id   = :tenant_id
             LIMIT 1'
        );
        $stmt->execute([':product_id' => $productId, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $price          = $data['price']          ?? 0;
        $compareAtPrice = ($data['compare_at_price'] ?? '') !== '' ? $data['compare_at_price'] : null;
        $costPrice      = ($data['cost_price']      ?? '') !== '' ? $data['cost_price']      : null;
        $currencyCode   = !empty($data['currency_code']) ? $data['currency_code'] : 'SAR';
        $taxRate        = ($data['tax_rate']        ?? '') !== '' ? $data['tax_rate']        : null;

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE product_pricing
                 SET price            = ?,
                     compare_at_price = ?,
                     cost_price       = ?,
                     currency_code    = ?,
                     tax_rate         = ?,
                     is_active        = 1,
                     updated_at       = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([$price, $compareAtPrice, $costPrice, $currencyCode, $taxRate, $existing['id']]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO product_pricing
                     (product_id, entity_id, variant_id, price, compare_at_price,
                      cost_price, currency_code, tax_rate, pricing_type, is_active)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 'fixed', 1)"
            );
            $stmt->execute([
                $productId, $entityId,
                $price, $compareAtPrice, $costPrice, $currencyCode, $taxRate,
            ]);
        }
    }

    /**
     * Delete a single record
     */
    public function delete(int $id, int $tenantId, int $entityId): bool
    {
        $sql = 'DELETE FROM entity_products WHERE id = :id AND tenant_id = :tenant_id AND entity_id = :entity_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId, ':entity_id' => $entityId]);
    }

    /**
     * Delete all products for an entity
     */
    public function deleteEntityProducts(int $entityId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_products WHERE entity_id = :entity_id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
    }

    /**
     * Validate foreign references
     */
    private function validateReferences(int $entityId, int $productId, int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM entities WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $entityId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Entity not found or tenant mismatch');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM products WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $productId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Product not found or tenant mismatch');
        }
    }
}
