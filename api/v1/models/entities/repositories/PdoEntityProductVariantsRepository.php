<?php
declare(strict_types=1);

final class PdoEntityProductVariantsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'variant_id', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'variant_id', 'tenant_id', 'is_active', 'is_featured', 'stock_status'
    ];

    private const VARIANT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id', 'variant_id',
        'stock_quantity', 'low_stock_threshold', 'manage_stock', 'stock_status',
        'is_active', 'is_featured'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Build shared WHERE clause and params for filters
     */
    private function buildFilterClauses(array $filters): array
    {
        $sql = '';
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'stock_status') {
                    $sql .= " AND epv.stock_status = :stock_status";
                    $params[":stock_status"] = $filters[$col];
                } elseif (is_numeric($filters[$col])) {
                    $sql .= " AND epv.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (pt.name LIKE :search_name OR pv.sku LIKE :search_sku)";
            $params[":search_name"] = $searchTerm;
            $params[":search_sku"] = $searchTerm;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * List with dynamic filters, search, ordering, pagination
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $filterResult = $this->buildFilterClauses($filters);

        $sql = "
            SELECT epv.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pp_v.price as variant_price
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp_v ON pp_v.product_id = epv.product_id
                AND pp_v.variant_id = epv.variant_id
                AND pp_v.entity_id IS NULL
                AND pp_v.is_active = 1
            WHERE 1=1
        " . $filterResult['sql'];
        $params = $filterResult['params'];

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY epv.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count for pagination
     */
    public function count(array $filters = []): int
    {
        $filterResult = $this->buildFilterClauses($filters);

        $sql = "
            SELECT COUNT(*)
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp_v ON pp_v.product_id = epv.product_id
                AND pp_v.variant_id = epv.variant_id
                AND pp_v.entity_id IS NULL
                AND pp_v.is_active = 1
            WHERE 1=1
        " . $filterResult['sql'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filterResult['params']);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find by ID (with optional tenant_id for multi-tenant safety)
     */
    public function find(int $id, ?int $tenantId = null): ?array
    {
        $params = [':id' => $id];
        $sql = "
            SELECT epv.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pp_v.price as variant_price
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp_v ON pp_v.product_id = epv.product_id
                AND pp_v.variant_id = epv.variant_id
                AND pp_v.entity_id IS NULL
                AND pp_v.is_active = 1
            WHERE epv.id = :id";
        if ($tenantId !== null) {
            $sql .= ' AND epv.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find by entity and variant
     */
    public function findByEntityAndVariant(int $entityId, int $variantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT epv.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pp_v.price as variant_price
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp_v ON pp_v.product_id = epv.product_id
                AND pp_v.variant_id = epv.variant_id
                AND pp_v.entity_id IS NULL
                AND pp_v.is_active = 1
            WHERE epv.entity_id = :entity_id AND epv.variant_id = :variant_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId, ':variant_id' => $variantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all variants for an entity (with pricing)
     */
    public function getEntityVariants(int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT epv.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pp.id as pricing_id,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp ON pp.product_id = epv.product_id
                AND pp.variant_id = epv.variant_id
                AND pp.entity_id = epv.entity_id
                AND pp.is_active = 1
            WHERE epv.entity_id = :entity_id
            ORDER BY epv.product_id, epv.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get variants for a specific entity product
     */
    public function getEntityProductVariants(int $entityId, int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT epv.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pp_v.price as variant_price
            FROM entity_product_variants epv
            LEFT JOIN products p ON epv.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON epv.variant_id = pv.id
            LEFT JOIN product_pricing pp_v ON pp_v.product_id = epv.product_id
                AND pp_v.variant_id = epv.variant_id
                AND pp_v.entity_id IS NULL
                AND pp_v.is_active = 1
            WHERE epv.entity_id = :entity_id AND epv.product_id = :product_id
            ORDER BY epv.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or Update
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
            throw new InvalidArgumentException("entity_id, product_id and variant_id are required");
        }

        $this->validateReferences(
            (int)$params[':entity_id'],
            (int)$params[':product_id'],
            (int)$params[':variant_id'],
            isset($params[':tenant_id']) ? (int)$params[':tenant_id'] : null
        );

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $setClauses = [];
            foreach (self::VARIANT_COLUMNS as $col) {
                if (array_key_exists(':' . $col, $params)) {
                    $setClauses[] = "{$col} = :{$col}";
                }
            }

            $stmt = $this->pdo->prepare(
                "UPDATE entity_product_variants SET " . implode(', ', $setClauses) . " WHERE id = :id"
            );
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $columns = [];
        $placeholders = [];
        foreach (self::VARIANT_COLUMNS as $col) {
            if (array_key_exists(':' . $col, $params)) {
                $columns[] = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO entity_product_variants (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")"
        );
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Bulk save variants for an entity (with optional pricing)
     */
    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($variants as $variantData) {
                $variantData['entity_id'] = $entityId;
                $variantData['tenant_id'] = $tenantId;

                $existing = $this->findByEntityAndVariant($entityId, (int)$variantData['variant_id']);

                if ($existing) {
                    $variantData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($variantData);

                // Save entity-specific variant pricing if price is provided
                if (isset($variantData['price']) && $variantData['price'] !== '' && $variantData['price'] !== null) {
                    $this->saveEntityVariantPricing(
                        $entityId,
                        (int)$variantData['product_id'],
                        (int)$variantData['variant_id'],
                        $variantData
                    );
                }
            }

            $this->pdo->commit();
            return $savedIds;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Save or update entity-specific variant pricing in product_pricing table
     */
    private function saveEntityVariantPricing(int $entityId, int $productId, int $variantId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM product_pricing WHERE product_id = :product_id AND variant_id = :variant_id AND entity_id = :entity_id LIMIT 1"
        );
        $stmt->execute([':product_id' => $productId, ':variant_id' => $variantId, ':entity_id' => $entityId]);
        $existingPricing = $stmt->fetch(PDO::FETCH_ASSOC);

        $price = $data['price'] ?? 0;
        $compareAtPrice = (isset($data['compare_at_price']) && $data['compare_at_price'] !== '') ? $data['compare_at_price'] : null;
        $costPrice = (isset($data['cost_price']) && $data['cost_price'] !== '') ? $data['cost_price'] : null;
        $currencyCode = $data['currency_code'] ?? 'SAR';
        $taxRate = (isset($data['tax_rate']) && $data['tax_rate'] !== '') ? $data['tax_rate'] : null;

        if ($existingPricing) {
            $stmt = $this->pdo->prepare(
                "UPDATE product_pricing SET price = ?, compare_at_price = ?, cost_price = ?,
                 currency_code = ?, tax_rate = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $stmt->execute([$price, $compareAtPrice, $costPrice, $currencyCode, $taxRate, $existingPricing['id']]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO product_pricing (product_id, variant_id, entity_id, price, compare_at_price, cost_price,
                 currency_code, tax_rate, pricing_type, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'fixed', 1)"
            );
            $stmt->execute([$productId, $variantId, $entityId, $price, $compareAtPrice, $costPrice, $currencyCode, $taxRate]);
        }
    }

    /**
     * Delete (with optional tenant_id for multi-tenant safety)
     */
    public function delete(int $id, ?int $tenantId = null): bool
    {
        $params = [':id' => $id];
        $sql = 'DELETE FROM entity_product_variants WHERE id = :id';
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete all variants for an entity
     */
    public function deleteEntityVariants(int $entityId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_product_variants WHERE entity_id = :entity_id");
        return $stmt->execute([':entity_id' => $entityId]);
    }

    /**
     * Delete all variants for a specific entity product
     */
    public function deleteEntityProductVariants(int $entityId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entity_product_variants WHERE entity_id = :entity_id AND product_id = :product_id"
        );
        return $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_product_variants");
        $stats['total_records'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT entity_id) FROM entity_product_variants");
        $stats['entities_with_variants'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT variant_id) FROM entity_product_variants");
        $stats['unique_variants'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_product_variants WHERE is_active = 1");
        $stats['active_records'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * Validate entity, product, and variant exist
     */
    private function validateReferences(int $entityId, int $productId, int $variantId, ?int $expectedTenantId = null): void
    {
        // Multi-tenant safety: verify entity exists and optionally confirm tenant_id matches
        $sql = "SELECT id, tenant_id FROM entities WHERE id = :id";
        $params = [':id' => $entityId];
        if ($expectedTenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $expectedTenantId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entity) {
            throw new RuntimeException("Entity not found");
        }
        $tenantId = (int)$entity['tenant_id'];

        // Ensure product belongs to this entity and tenant via entity_products
        $stmt = $this->pdo->prepare("
            SELECT p.id FROM products p
            INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
            WHERE p.id = :id AND p.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $productId, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Product not found");
        }

        // Ensure variant belongs to the validated product (tenant-scoped via product chain)
        $stmt = $this->pdo->prepare("
            SELECT pv.id FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id AND p.tenant_id = :tenant_id
            WHERE pv.id = :id AND pv.product_id = :product_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $variantId, ':product_id' => $productId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Variant not found");
        }
    }
}