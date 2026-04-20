<?php

declare(strict_types=1);

final class PdoEntityProductVariantsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'variant_id', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'variant_id', 'tenant_id',
        'is_active', 'is_featured', 'stock_status',
    ];

    private const VARIANT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id', 'variant_id',
        'stock_quantity', 'low_stock_threshold', 'manage_stock', 'stock_status',
        'is_active', 'is_featured',
    ];

    // ── الأعمدة المحددة بدلاً من epv.* ─────────────────────────────────
    // إصلاح: SELECT * → أعمدة صريحة (Performance + لا leak لأعمدة مستقبلية)
    private const EPV_COLUMNS = '
        epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
        epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
        epv.stock_status, epv.is_active, epv.is_featured,
        epv.created_at, epv.updated_at
    ';

    private const JOIN_FRAGMENT = "
        LEFT JOIN products p             ON p.id  = epv.product_id
        LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
        LEFT JOIN product_variants pv    ON pv.id = epv.variant_id
    ";

    private const PRICING_JOIN = "
        LEFT JOIN product_pricing pp_v
               ON pp_v.product_id = epv.product_id
              AND pp_v.variant_id  = epv.variant_id
              AND pp_v.entity_id  IS NULL
              AND pp_v.is_active   = 1
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
            if ($col === 'stock_status') {
                $sql .= ' AND epv.stock_status = :stock_status';
                $params[':stock_status'] = $filters[$col];
            } elseif (is_numeric($filters[$col])) {
                $sql .= " AND epv.{$col} = :{$col}";
                $params[":{$col}"] = (int) $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $sql .= ' AND (pt.name LIKE :search_name OR pv.sku LIKE :search_sku)';
            $params[':search_name'] = $term;
            $params[':search_sku']  = $term;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    // ────────────────────────────────────────────────────────────────────
    // all  — إلزامي: tenant_id في الـ filters
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح Multi-Tenant: tenant_id إلزامي الآن عبر $filters['tenant_id']
     * إذا لم يُمرَّر يُعيد [] مباشرة — لا cross-tenant leak ممكن
     */
    public function all(
        ?int   $limit    = null,
        ?int   $offset   = null,
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
    ): array {
        // ✅ Multi-Tenant guard — tenant_id إلزامي
        if (empty($filters['tenant_id'])) {
            return [];
        }

        $filterResult = $this->buildFilterClauses($filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT ' . self::EPV_COLUMNS . ',
                   COALESCE(pt.name, \'\') AS product_name,
                   pv.sku                  AS variant_sku,
                   pp_v.price              AS variant_price
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . self::PRICING_JOIN
            . ' WHERE 1=1 '
            . $filterResult['sql']
            . " ORDER BY epv.{$orderBy} {$orderDir}";

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
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . ' WHERE 1=1 '
            . $filterResult['sql'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filterResult['params']);
        return (int) $stmt->fetchColumn();
    }

    // ────────────────────────────────────────────────────────────────────
    // find — tenant_id إلزامي
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح: tenant_id أصبح إلزامياً (int بدلاً من ?int)
     */
    public function find(int $id, int $tenantId): ?array
    {
        $sql = 'SELECT ' . self::EPV_COLUMNS . ',
                   COALESCE(pt.name, \'\') AS product_name,
                   pv.sku                  AS variant_sku,
                   pp_v.price              AS variant_price
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . self::PRICING_JOIN
            . ' WHERE epv.id = :id AND epv.tenant_id = :tenant_id
            LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ────────────────────────────────────────────────────────────────────
    // findByEntityAndVariant — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح Multi-Tenant: أضفنا tenant_id — كان غائباً تماماً
     */
    public function findByEntityAndVariant(int $entityId, int $variantId, int $tenantId): ?array
    {
        $sql = 'SELECT ' . self::EPV_COLUMNS . ',
                   COALESCE(pt.name, \'\') AS product_name,
                   pv.sku                  AS variant_sku,
                   pp_v.price              AS variant_price
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . self::PRICING_JOIN
            . ' WHERE epv.entity_id  = :entity_id
              AND epv.variant_id  = :variant_id
              AND epv.tenant_id   = :tenant_id
            LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id'  => $entityId,
            ':variant_id' => $variantId,
            ':tenant_id'  => $tenantId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ────────────────────────────────────────────────────────────────────
    // getEntityVariants — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح Multi-Tenant: tenant_id إلزامي — كان غائباً
     */
    public function getEntityVariants(int $entityId, int $tenantId): array
    {
        $sql = 'SELECT ' . self::EPV_COLUMNS . ',
                   COALESCE(pt.name, \'\') AS product_name,
                   pv.sku                  AS variant_sku,
                   pp.id                   AS pricing_id,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . '
            LEFT JOIN product_pricing pp
                   ON pp.product_id = epv.product_id
                  AND pp.variant_id  = epv.variant_id
                  AND pp.entity_id   = epv.entity_id
                  AND pp.is_active   = 1
            WHERE epv.entity_id  = :entity_id
              AND epv.tenant_id  = :tenant_id
            ORDER BY epv.product_id, epv.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    // getEntityProductVariants — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح Multi-Tenant: tenant_id إلزامي — كان غائباً
     */
    public function getEntityProductVariants(int $entityId, int $productId, int $tenantId): array
    {
        $sql = 'SELECT ' . self::EPV_COLUMNS . ',
                   COALESCE(pt.name, \'\') AS product_name,
                   pv.sku                  AS variant_sku,
                   pp_v.price              AS variant_price
            FROM entity_product_variants epv'
            . self::JOIN_FRAGMENT
            . self::PRICING_JOIN
            . ' WHERE epv.entity_id  = :entity_id
              AND epv.product_id  = :product_id
              AND epv.tenant_id   = :tenant_id
            ORDER BY epv.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id'  => $entityId,
            ':product_id' => $productId,
            ':tenant_id'  => $tenantId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    // save
    // ────────────────────────────────────────────────────────────────────

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

        // tenant_id إلزامي للحفظ
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
                . ' WHERE id = :id AND tenant_id = :tenant_id'   // ✅ tenant guard على UPDATE
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

    // ────────────────────────────────────────────────────────────────────
    // saveEntityVariants
    // إصلاح: catch محدد + tenant_id مُمرَّر لـ findByEntityAndVariant
    // ────────────────────────────────────────────────────────────────────

    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($variants as $variantData) {
                $variantData['entity_id'] = $entityId;
                $variantData['tenant_id'] = $tenantId;

                // ✅ tenant_id مُمرَّر الآن
                $existing = $this->findByEntityAndVariant(
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
                    );
                }
            }

            $this->pdo->commit();
            return $savedIds;

        } catch (\PDOException $e) {
            // إصلاح: catch محدد لأخطاء قاعدة البيانات فقط
            $this->pdo->rollBack();
            throw new \RuntimeException('Failed to save entity variants: ' . $e->getMessage(), 0, $e);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // أخطاء منطقية — نتراجع ونُعيد الرمي بدون تغيير
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // saveEntityVariantPricing (private)
    // ────────────────────────────────────────────────────────────────────

    private function saveEntityVariantPricing(
        int   $entityId,
        int   $productId,
        int   $variantId,
        array $data,
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM product_pricing
             WHERE product_id = :product_id
               AND variant_id  = :variant_id
               AND entity_id   = :entity_id
             LIMIT 1'
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':variant_id' => $variantId,
            ':entity_id'  => $entityId,
        ]);
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

    // ────────────────────────────────────────────────────────────────────
    // delete — tenant_id إلزامي
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح: tenant_id إلزامي (int بدلاً من ?int)
     */
    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_product_variants WHERE id = :id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }

    // ────────────────────────────────────────────────────────────────────
    // deleteEntityVariants — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

    /**
     * إصلاح Multi-Tenant: tenant_id إلزامي — كان غائباً
     */
    public function deleteEntityVariants(int $entityId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM entity_product_variants WHERE entity_id = :entity_id AND tenant_id = :tenant_id'
        );
        return $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
    }

    // ────────────────────────────────────────────────────────────────────
    // deleteEntityProductVariants — إضافة tenant_id
    // ────────────────────────────────────────────────────────────────────

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

    // ────────────────────────────────────────────────────────────────────
    // getStatistics — إصلاح: tenant_id إلزامي (لا platform-wide aggregate)
    // ────────────────────────────────────────────────────────────────────

    public function getStatistics(int $tenantId): array
    {
        $base = 'FROM entity_product_variants WHERE tenant_id = ?';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base");
        $stmt->execute([$tenantId]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT entity_id) $base");
        $stmt->execute([$tenantId]);
        $entities = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT variant_id) $base");
        $stmt->execute([$tenantId]);
        $unique = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base AND is_active = 1");
        $stmt->execute([$tenantId]);
        $active = (int) $stmt->fetchColumn();

        return [
            'total_records'        => $total,
            'entities_with_variants' => $entities,
            'unique_variants'      => $unique,
            'active_records'       => $active,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // validateReferences — tenant_id إلزامي الآن
    // ────────────────────────────────────────────────────────────────────

    private function validateReferences(
        int $entityId,
        int $productId,
        int $variantId,
        int $tenantId,       // ✅ إلزامي — لا ?int
    ): void {
        // تحقق أن الـ entity موجودة وتنتمي لنفس الـ tenant
        $stmt = $this->pdo->prepare(
            'SELECT id FROM entities WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $entityId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Entity not found or tenant mismatch');
        }

        // تحقق أن المنتج مرتبط بهذه الـ entity وهذا الـ tenant
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

        // تحقق أن المتغير مرتبط بهذا المنتج والـ tenant
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
