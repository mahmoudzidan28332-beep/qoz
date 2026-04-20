<?php

declare(strict_types=1);

final class PdoEntityProductsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'price', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'tenant_id', 'is_active', 'is_featured',
    ];

    private const ENTITY_PRODUCT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id',
        'stock_quantity', 'low_stock_threshold', 'is_active', 'is_featured',
    ];

    // ── الأعمدة المحددة بدلاً من ep.* ──────────────────────────────────
    private const EP_COLUMNS = '
        ep.id, ep.tenant_id, ep.entity_id, ep.product_id,
        ep.stock_quantity, ep.low_stock_threshold,
        ep.is_active, ep.is_featured,
        ep.created_at, ep.updated_at
    ';

    private const TRANSLATION_JOINS = "
        LEFT JOIN product_translations pt_ar ON pt_ar.product_id = p.id AND pt_ar.language_code = 'ar'
        LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.language_code = 'en'
    ";

    // ────────────────────────────────────────────────────────────────────
    // buildFilterClauses
    // ────────────────────────────────────────────────────────────────────

    private function buildFilterClauses(array $filters): array
    {
        $sql    = '';
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (!isset($filters[$col]) || $filters[$col] === '') {
                continue;
            }
            if (is_numeric($filters[$col])) {
                $sql .= " AND ep.{$col} = :{$col}";
                $params[":{$col}"] = (int) $filters[$col];
            }
        }

        if (!empty($filters['store_name'])) {
            $sql .= ' AND e.store_name LIKE :store_name';
            $params[':store_name'] = '%' . $filters['store_name'] . '%';
        }

        if (!empty($filters['product_name'])) {
            $sql .= " AND COALESCE(pt_ar.name, pt_en.name, '') LIKE :product_name";
            $params[':product_name'] = '%' . $filters['product_name'] . '%';
        }

        if (!empty($filters['product_sku'])) {
            $sql .= ' AND p.sku LIKE :product_sku';
            $params[':product_sku'] = '%' . $filters['product_sku'] . '%';
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $sql .= " AND (COALESCE(pt_ar.name, pt_en.name, '') LIKE :search_name
                      OR p.sku LIKE :search_sku
                      OR e.store_name LIKE :search_store)";
            $params[':search_name']  = $term;
            $params[':search_sku']   = $term;
            $params[':search_store'] = $term;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    // ────────────────────────────────────────────────────────────────────
    // all — tenant_id إلزامي عبر $filters
    // ────────────────────────────────────────────────────────────────────

    public function all(
        ?int   $limit    = null,
        ?int   $offset   = null,
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
    ): array {
        // ✅ Multi-Tenant guard
        if (empty($filters['tenant_id'])) {
            return [];
        }

        $filterResult = $this->buildFilterClauses($filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT ' . self::EP_COLUMNS . ",
                   e.store_name,
                   e.status                                                           AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku
            FROM entity_products ep
            LEFT JOIN entities e ON e.id = ep.entity_id
            LEFT JOIN products p ON p.id = ep.product_id"
            . self::TRANSLATION_JOINS
            . ' WHERE 1=1 '
            . $filterResult['sql']
            . " ORDER BY ep.{$orderBy} {$orderDir}";

        if ($limit  !== null) $sql .= ' LIMIT :limit';
        if ($offset !== null) $sql .= ' OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($filterResult['params'] as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    // count
    // ────────────────────────────────────────────────────────────────────

    public function count(array $filters = []): int
    {
        if (empty($filters['tenant_id'])) {
            return 0;
        }

        $filterResult = $this->buildFilterClauses($filters);

        $sql = 'SELECT COUNT(*)
            FROM entity_products ep
            LEFT JOIN entities e ON e.id = ep.entity_id
            LEFT JOIN products p ON p.id = ep.product_id'
            . self::TRANSLATION_JOINS
            . ' WHERE 1=1 '
            . $filterResult['sql'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filterResult['params']);
        return (int) $stmt->fetchColumn();
    }

    // ────────────────────────────────────────────────────────────────────
    // find — tenant_id إلزامي
    // ────────────────────────────────────────────────────────────────────

    public function find(int $id, int $tenantId): ?array
    {
        $sql = 'SELECT ' . self::EP_COLUMNS . ",
                   e.store_name,
                   e.status                                                           AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku    AS product_sku,
                   pp.id    AS pricing_id,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate
            FROM entity_products ep
            LEFT JOIN entities e ON e.id = ep.entity_id
            LEFT JOIN products p ON p.id = ep.product_id"
            . self::TRANSLATION_JOINS
            . '
            LEFT JOIN product_pricing pp
                   ON pp.product_id = ep.product_id
                  AND pp.entity_id  = ep.entity_id
                  AND pp.variant_id IS NULL
                  AND pp.is_active  = 1
            WHERE ep.id        = :id
              AND ep.tenant_id = :tenant_id
            ORDER BY pp.id ASC
            LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ────────────────────────────────────────────────────────────────────
    // findByEntityAndProduct — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

    public function findByEntityAndProduct(int $entityId, int $productId, int $tenantId): ?array
    {
        $sql = 'SELECT ' . self::EP_COLUMNS . ",
                   e.store_name,
                   e.status                                                           AS entity_status,
                   COALESCE(pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku
            FROM entity_products ep
            LEFT JOIN entities e ON e.id = ep.entity_id
            LEFT JOIN products p ON p.id = ep.product_id"
            . self::TRANSLATION_JOINS
            . ' WHERE ep.entity_id  = :entity_id
              AND ep.product_id  = :product_id
              AND ep.tenant_id   = :tenant_id
            LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id'  => $entityId,
            ':product_id' => $productId,
            ':tenant_id'  => $tenantId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ────────────────────────────────────────────────────────────────────
    // getEntityProducts — tenant_id إلزامي + مُمرَّر للاستعلامات
    // ────────────────────────────────────────────────────────────────────

    public function getEntityProducts(int $entityId, string $lang = 'ar', int $tenantId = 0): array
    {
        if ($tenantId <= 0) {
            return [];
        }

        // Step 1: min pricing IDs لهذه الـ entity
        $minStmt = $this->pdo->prepare(
            'SELECT product_id, MIN(id) AS min_id
             FROM product_pricing
             WHERE entity_id = :eid AND variant_id IS NULL AND is_active = 1
             GROUP BY product_id'
        );
        $minStmt->execute([':eid' => $entityId]);
        $minRows = $minStmt->fetchAll(PDO::FETCH_ASSOC);

        $minMap = [];
        foreach ($minRows as $r) {
            $minMap[(int) $r['product_id']] = (int) $r['min_id'];
        }

        // Step 2: entity products مع tenant_id في الـ WHERE
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::EP_COLUMNS . ",
                   COALESCE(pt_lang.name, pt_ar.name, pt_en.name, CONCAT('Product #', ep.product_id)) AS product_name,
                   p.sku AS product_sku
            FROM entity_products ep
            LEFT JOIN products p ON p.id = ep.product_id
            LEFT JOIN product_translations pt_lang
                   ON pt_lang.product_id = p.id AND pt_lang.language_code = :lang
            LEFT JOIN product_translations pt_ar
                   ON pt_ar.product_id   = p.id AND pt_ar.language_code   = 'ar'
            LEFT JOIN product_translations pt_en
                   ON pt_en.product_id   = p.id AND pt_en.language_code   = 'en'
            WHERE ep.entity_id  = :entity_id
              AND ep.tenant_id  = :tenant_id
            ORDER BY ep.is_featured DESC, ep.id DESC"
        );
        $stmt->execute([
            ':entity_id' => $entityId,
            ':lang'      => $lang,
            ':tenant_id' => $tenantId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 3: pricing bulk fetch
        $ppMap = [];
        if (!empty($minMap)) {
            $ids          = array_values($minMap);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $ppStmt       = $this->pdo->prepare(
                "SELECT id AS pricing_id, product_id, price, compare_at_price,
                        cost_price, currency_code, tax_rate
                 FROM product_pricing WHERE id IN ($placeholders)"
            );
            $ppStmt->execute($ids);
            foreach ($ppStmt->fetchAll(PDO::FETCH_ASSOC) as $pp) {
                $ppMap[(int) $pp['product_id']] = $pp;
            }
        }

        // Step 4: merge
        foreach ($rows as &$row) {
            $pid       = (int) $row['product_id'];
            $pp        = $ppMap[$pid] ?? null;
            $row['pricing_id']       = $pp['pricing_id']       ?? null;
            $row['price']            = $pp['price']            ?? null;
            $row['compare_at_price'] = $pp['compare_at_price'] ?? null;
            $row['cost_price']       = $pp['cost_price']       ?? null;
            $row['currency_code']    = $pp['currency_code']    ?? null;
            $row['tax_rate']         = $pp['tax_rate']         ?? null;
        }
        unset($row);

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────
    // save
    // ────────────────────────────────────────────────────────────────────

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
                . ' WHERE id = :id AND tenant_id = :tenant_id'  // ✅ tenant guard
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

    // ────────────────────────────────────────────────────────────────────
    // saveEntityProducts
    // ────────────────────────────────────────────────────────────────────

    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($products as $productData) {
                $productData['entity_id'] = $entityId;
                $productData['tenant_id'] = $tenantId;

                // ✅ tenant_id مُمرَّر
                $existing = $this->findByEntityAndProduct(
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
                    $this->saveEntityProductPricing($entityId, (int) $productData['product_id'], $productData);
                }
            }

            $this->pdo->commit();
            return $savedIds;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException('Failed to save entity products: ' . $e->getMessage(), 0, $e);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // saveEntityProductPricing (private)
    // ────────────────────────────────────────────────────────────────────

    private function saveEntityProductPricing(int $entityId, int $productId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM product_pricing
             WHERE product_id = :product_id
               AND entity_id  = :entity_id
               AND variant_id IS NULL
             LIMIT 1'
        );
        $stmt->execute([':product_id' => $productId, ':entity_id' => $entityId]);
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

    // ────────────────────────────────────────────────────────────────────
    // delete — tenant_id إلزامي
    // ────────────────────────────────────────────────────────────────────

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_products WHERE id = :id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }

    // ────────────────────────────────────────────────────────────────────
    // deleteEntityProducts — tenant_id كان موجوداً، نحتفظ به
    // ────────────────────────────────────────────────────────────────────

    public function deleteEntityProducts(int $entityId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_products WHERE entity_id = :entity_id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
    }

    // ────────────────────────────────────────────────────────────────────
    // getStatistics — tenant_id إلزامي
    // ────────────────────────────────────────────────────────────────────

    public function getStatistics(int $tenantId): array
    {
        $base = 'FROM entity_products WHERE tenant_id = ?';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base");
        $stmt->execute([$tenantId]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT entity_id) $base");
        $stmt->execute([$tenantId]);
        $entities = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT product_id) $base");
        $stmt->execute([$tenantId]);
        $unique = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base AND is_active = 1");
        $stmt->execute([$tenantId]);
        $active = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base AND is_featured = 1");
        $stmt->execute([$tenantId]);
        $featured = (int) $stmt->fetchColumn();

        return [
            'total_records'           => $total,
            'entities_with_products'  => $entities,
            'unique_products'         => $unique,
            'active_records'          => $active,
            'featured_records'        => $featured,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // validateReferences — tenant_id إلزامي + تحقق فعلي
    // ────────────────────────────────────────────────────────────────────

    private function validateReferences(int $entityId, int $productId, int $tenantId): void
    {
        // إصلاح: حذف التعليق "/* tenant_id scoped via caller */" — التحقق الآن فعلي
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
