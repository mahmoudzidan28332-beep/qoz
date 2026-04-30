<?php

declare(strict_types=1);

/**
 * EntityProductVariantWriteRepository
 * Handles all write operations (create, update, delete) for entity product variants.
 */
final class EntityProductVariantWriteRepository
{
    private const VARIANT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id', 'variant_id',
        'stock_quantity', 'low_stock_threshold', 'manage_stock', 'stock_status',
        'is_active', 'is_featured',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityProductVariantQueryRepository $queryRepo
    ) {}

    /**
     * Save a single variant (insert or update)
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::VARIANT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            }
        }

        if (empty($params[':entity_id']) || empty($params[':variant_id']) || empty($params[':product_id'])) {
            throw new \InvalidArgumentException('entity_id, product_id and variant_id are required');
        }

        if (empty($params[':tenant_id'])) {
            throw new \InvalidArgumentException('tenant_id is required');
        }

        $this->validateReferences(
            (int) $params[':entity_id'],
            (int) $params[':product_id'],
            (int) $params[':variant_id'],
            (int) $params[':tenant_id'],
        );

        if ($isUpdate) {
            $params[':id'] = (int) $data['id'];
            $setClauses    = [];
            foreach (self::VARIANT_COLUMNS as $col) {
                if (array_key_exists(':' . $col, $params)) {
                    $setClauses[] = "{$col} = :{$col}";
                }
            }
            $stmt = $this->pdo->prepare(
                'UPDATE entity_product_variants SET '
                . implode(', ', $setClauses)
                . ' WHERE id = :id AND tenant_id = :tenant_id AND entity_id = :entity_id'
            );
            $stmt->execute($params);
            return (int) $data['id'];
        }

        $columns      = [];
        $placeholders = [];
        foreach (self::VARIANT_COLUMNS as $col) {
            if (array_key_exists(':' . $col, $params)) {
                $columns[]      = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO entity_product_variants (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Bulk save variants for an entity
     */
    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($variants as $variantData) {
                $variantData['entity_id'] = $entityId;
                $variantData['tenant_id'] = $tenantId;

                $existing = $this->queryRepo->findByEntityAndVariant(
                    $entityId,
                    (int) $variantData['variant_id'],
                    $tenantId,
                );

                if ($existing) {
                    $variantData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($variantData);

                $hasPrice = isset($variantData['price'])
                    && $variantData['price'] !== ''
                    && $variantData['price'] !== null;

                if ($hasPrice) {
                    $this->saveEntityVariantPricing(
                        $entityId,
                        (int) $variantData['product_id'],
                        (int) $variantData['variant_id'],
                        $variantData,
                        $tenantId
                    );
                }
            }

            $this->pdo->commit();
            return $savedIds;

        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \RuntimeException('Failed to save entity variants: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Manage entity-specific pricing for a variant
     */
    private function saveEntityVariantPricing(
        int   $entityId,
        int   $productId,
        int   $variantId,
        array $data,
        int   $tenantId
    ): void {
        $stVer = $this->pdo->prepare(
            'SELECT 1 FROM products WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stVer->execute([$productId, $tenantId]);
        if (!$stVer->fetchColumn()) {
            throw new \RuntimeException('Product ownership verification failed.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT pp.id FROM product_pricing pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.product_id = :product_id
               AND pp.variant_id = :variant_id
               AND pp.entity_id  = :entity_id
               AND p.tenant_id   = :tenant_id
             LIMIT 1'
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':variant_id' => $variantId,
            ':entity_id'  => $entityId,
            ':tenant_id'  => $tenantId,
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $price          = $data['price']            ?? 0;
        $compareAtPrice = ($data['compare_at_price'] ?? '') !== '' ? $data['compare_at_price'] : null;
        $costPrice      = ($data['cost_price']       ?? '') !== '' ? $data['cost_price']       : null;
        $currencyCode   = !empty($data['currency_code']) ? $data['currency_code'] : 'SAR';
        $taxRate        = ($data['tax_rate']         ?? '') !== '' ? $data['tax_rate']         : null;

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
                     (product_id, variant_id, entity_id, price, compare_at_price,
                      cost_price, currency_code, tax_rate, pricing_type, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'fixed', 1)"
            );
            $stmt->execute([
                $productId, $variantId, $entityId,
                $price, $compareAtPrice, $costPrice, $currencyCode, $taxRate,
            ]);
        }
    }

    /**
     * Delete a single record
     */
    public function delete(int $id, int $tenantId, int $entityId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_product_variants
             WHERE id        = :id
               AND tenant_id = :tenant_id
               AND entity_id = :entity_id'
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId, ':entity_id' => $entityId]);
    }

    /**
     * Delete all variants for an entity
     */
    public function deleteEntityVariants(int $entityId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_product_variants
             WHERE entity_id = :entity_id
               AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
    }

    /**
     * Delete variants for a specific entity product
     */
    public function deleteEntityProductVariants(int $entityId, int $productId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_product_variants
             WHERE entity_id  = :entity_id
               AND product_id = :product_id
               AND tenant_id  = :tenant_id'
        );
        return $stmt->execute([
            ':entity_id'  => $entityId,
            ':product_id' => $productId,
            ':tenant_id'  => $tenantId,
        ]);
    }

    /**
     * Internal reference validation
     */
    private function validateReferences(
        int $entityId,
        int $productId,
        int $variantId,
        int $tenantId,
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM entities WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $entityId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Entity not found or tenant mismatch');
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.id FROM products p
             INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
             WHERE p.id = :id AND p.tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $productId, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Product not found or does not belong to this entity/tenant');
        }

        $stmt = $this->pdo->prepare(
            'SELECT pv.id FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id AND p.tenant_id = :tenant_id
             WHERE pv.id = :id AND pv.product_id = :product_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $variantId, ':product_id' => $productId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Variant not found or does not belong to this product/tenant');
        }
    }
}
