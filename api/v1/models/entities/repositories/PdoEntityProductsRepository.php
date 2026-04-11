<?php
declare(strict_types=1);

final class PdoEntityProductsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'price', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'tenant_id', 'is_active', 'is_featured'
    ];

    private const ENTITY_PRODUCT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id',
        'stock_quantity', 'low_stock_threshold', 'is_active', 'is_featured'
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
                if (is_numeric($filters[$col])) {
                    $sql .= " AND ep.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . $filters['store_name'] . '%';
        }

        if (isset($filters['product_name']) && !empty($filters['product_name'])) {
            $sql .= " AND COALESCE(pt_ar.name, pt_en.name, '') LIKE :product_name";
            $params[":product_name"] = '%' . $filters['product_name'] . '%';
        }

        if (isset($filters['product_sku']) && !empty($filters['product_sku'])) {
            $sql .= " AND p.sku LIKE :product_sku";
            $params[":product_sku"] = '%' . $filters['product_sku'] . '%';
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (COALESCE(pt_ar.name, pt_en.name, '') LIKE :search_name OR p.sku LIKE :search_sku OR e.store_name LIKE :search_store)";
            $params[":search_name"]  = $searchTerm;
            $params[":search_sku"]   = $searchTerm;
            $params[":search_store"] = $searchTerm;
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
            SELECT ep.*,
                   e.store_name,
                   e.status AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt_ar ON pt_ar.product_id = p.id AND pt_ar.language_code = 'ar'
            LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.language_code = 'en'
            WHERE 1=1
        " . $filterResult['sql'];
        $params = $filterResult['params'];

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ep.{$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
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
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt_ar ON pt_ar.product_id = p.id AND pt_ar.language_code = 'ar'
            LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.language_code = 'en'
            WHERE 1=1
        " . $filterResult['sql'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filterResult['params']);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   e.store_name,
                   e.status AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku,
                   pp.id            AS pricing_id,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt_ar ON pt_ar.product_id = p.id AND pt_ar.language_code = 'ar'
            LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.language_code = 'en'
            LEFT JOIN product_pricing pp
                   ON pp.product_id = ep.product_id
                  AND pp.entity_id  = ep.entity_id
                  AND pp.variant_id IS NULL
                  AND pp.is_active  = 1
                  AND pp.id = (
                      SELECT MIN(id) FROM product_pricing
                      WHERE product_id = ep.product_id
                        AND entity_id  = ep.entity_id
                        AND variant_id IS NULL
                        AND is_active  = 1
                  )
            WHERE ep.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find by entity and product
     */
    public function findByEntityAndProduct(int $entityId, int $productId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   e.store_name,
                   e.status AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt_ar ON pt_ar.product_id = p.id AND pt_ar.language_code = 'ar'
            LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.language_code = 'en'
            WHERE ep.entity_id = :entity_id AND ep.product_id = :product_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all products for an entity (with pricing — one row per product, no duplicates)
     *
     * FIX: استخدام subquery لجلب أحدث سعر نشط بدلاً من JOIN مباشر
     *      يمنع تكرار الصفوف إذا كان المنتج عنده أكثر من سعر نشط
     *
     * @param int    $entityId
     * @param string $lang  رمز اللغة المطلوبة (fallback: ar ثم en ثم 'Product #id')
     */
    public function getEntityProducts(int $entityId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ep.*,
                COALESCE(pt_lang.name, pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                p.sku AS product_sku,
                pp.id            AS pricing_id,
                pp.price,
                pp.compare_at_price,
                pp.cost_price,
                pp.currency_code,
                pp.tax_rate
            FROM entity_products ep
            LEFT JOIN products p
                   ON p.id = ep.product_id
            LEFT JOIN product_translations pt_lang
                   ON pt_lang.product_id = p.id AND pt_lang.language_code = :lang
            LEFT JOIN product_translations pt_ar
                   ON pt_ar.product_id   = p.id AND pt_ar.language_code   = 'ar'
            LEFT JOIN product_translations pt_en
                   ON pt_en.product_id   = p.id AND pt_en.language_code   = 'en'
            -- جلب سجل التسعير الوحيد (أصغر id نشط) لكل منتج في هذا الكيان
            LEFT JOIN product_pricing pp
                   ON pp.id = (
                       SELECT MIN(pp2.id)
                       FROM product_pricing pp2
                       WHERE pp2.product_id = ep.product_id
                         AND pp2.entity_id  = ep.entity_id
                         AND pp2.variant_id IS NULL
                         AND pp2.is_active  = 1
                   )
            WHERE ep.entity_id = :entity_id
            ORDER BY ep.is_featured DESC, ep.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or Update
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
            throw new \InvalidArgumentException("entity_id and product_id are required");
        }

        $this->validateReferences((int)$params[':entity_id'], (int)$params[':product_id']);

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $setClauses = [];
            foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
                if (array_key_exists(':' . $col, $params)) {
                    $setClauses[] = "{$col} = :{$col}";
                }
            }
            $stmt = $this->pdo->prepare(
                "UPDATE entity_products SET " . implode(', ', $setClauses) . " WHERE id = :id"
            );
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $columns      = [];
        $placeholders = [];
        foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
            if (array_key_exists(':' . $col, $params)) {
                $columns[]      = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO entity_products (" . implode(', ', $columns) . ")
             VALUES (" . implode(', ', $placeholders) . ")"
        );
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Bulk save products for an entity (with optional pricing)
     */
    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($products as $productData) {
                $productData['entity_id'] = $entityId;
                $productData['tenant_id'] = $tenantId;

                $existing = $this->findByEntityAndProduct($entityId, (int)$productData['product_id']);
                if ($existing) {
                    $productData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($productData);

                // حفظ التسعير فقط إذا كان السعر موجوداً وغير فارغ
                $hasPrice = isset($productData['price'])
                    && $productData['price'] !== ''
                    && $productData['price'] !== null;

                if ($hasPrice) {
                    $this->saveEntityProductPricing($entityId, (int)$productData['product_id'], $productData);
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
     * Save or update entity-specific pricing in product_pricing table
     * يحفظ سجلاً واحداً فقط (variant_id = NULL يعني تسعير المنتج الأساسي)
     */
    private function saveEntityProductPricing(int $entityId, int $productId, array $data): void
    {
        // ابحث عن السجل الموجود (variant_id IS NULL = تسعير المنتج وليس المتغير)
        $stmt = $this->pdo->prepare("
            SELECT id FROM product_pricing
            WHERE product_id = :product_id
              AND entity_id  = :entity_id
              AND variant_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([':product_id' => $productId, ':entity_id' => $entityId]);
        $existingPricing = $stmt->fetch(PDO::FETCH_ASSOC);

        $price          = $data['price'] ?? 0;
        $compareAtPrice = (isset($data['compare_at_price']) && $data['compare_at_price'] !== '') ? $data['compare_at_price'] : null;
        $costPrice      = (isset($data['cost_price'])      && $data['cost_price']      !== '') ? $data['cost_price']      : null;
        $currencyCode   = !empty($data['currency_code']) ? $data['currency_code'] : 'SAR';
        $taxRate        = (isset($data['tax_rate'])        && $data['tax_rate']        !== '') ? $data['tax_rate']        : null;

        if ($existingPricing) {
            $stmt = $this->pdo->prepare("
                UPDATE product_pricing
                SET price            = ?,
                    compare_at_price = ?,
                    cost_price       = ?,
                    currency_code    = ?,
                    tax_rate         = ?,
                    is_active        = 1,
                    updated_at       = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$price, $compareAtPrice, $costPrice, $currencyCode, $taxRate, $existingPricing['id']]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_pricing
                    (product_id, entity_id, variant_id, price, compare_at_price,
                     cost_price, currency_code, tax_rate, pricing_type, is_active)
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 'fixed', 1)
            ");
            $stmt->execute([$productId, $entityId, $price, $compareAtPrice, $costPrice, $currencyCode, $taxRate]);
        }
    }

    /**
     * Delete
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Delete all products for an entity
     */
    public function deleteEntityProducts(int $entityId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE entity_id = :entity_id");
        return $stmt->execute([':entity_id' => $entityId]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products");
        $stats['total_records'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT entity_id) FROM entity_products");
        $stats['entities_with_products'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT product_id) FROM entity_products");
        $stats['unique_products'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products WHERE is_active = 1");
        $stats['active_records'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products WHERE is_featured = 1");
        $stats['featured_records'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * Validate entity and product exist
     */
    private function validateReferences(int $entityId, int $productId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM entities WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $entityId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException("Entity not found");
        }

        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException("Product not found");
        }
    }
}