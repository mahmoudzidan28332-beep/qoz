<?php

declare(strict_types=1);

/**
 * EntityProductQueryRepository
 * Handles all read-only operations for entity products.
 */
final class EntityProductQueryRepository
{
    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'price', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'tenant_id', 'is_active', 'is_featured',
    ];

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

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Build dynamic filter clauses
     */
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

    /**
     * Get paginated list
     */
    public function all(
        ?int   $limit    = null,
        ?int   $offset   = null,
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
    ): array {
        if (empty($filters['tenant_id'])) {
            return [];
        }

        $filterResult = $this->buildFilterClauses($filters);
        $orderBy      = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir     = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

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

    /**
     * Count items
     */
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

    /**
     * Find single record by ID
     */
    public function find(int $id, int $tenantId, int $entityId): ?array
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
            WHERE ep.id = :id AND ep.tenant_id = :tenant_id AND ep.entity_id = :entity_id'
            . '
            ORDER BY pp.id ASC
            LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId, ':entity_id' => $entityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find by entity and product
     */
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

        $params = [
            ':entity_id'  => $entityId,
            ':product_id' => $productId,
            ':tenant_id'  => $tenantId
        ];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all products for an entity
     */
    public function getEntityProducts(int $entityId, string $lang = 'ar', ?int $tenantId = null): array
    {
        if ($tenantId === null || $tenantId <= 0) {
            return [];
        }

        // Step 1: min pricing IDs
        $minStmt = $this->pdo->prepare(
            'SELECT pp.product_id, MIN(pp.id) AS min_id
             FROM product_pricing pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.entity_id = :eid 
               AND pp.variant_id IS NULL 
               AND pp.is_active = 1
               AND p.tenant_id = :tenant_id
             GROUP BY pp.product_id'
        );
        $minStmt->execute([':eid' => $entityId, ':tenant_id' => $tenantId]);
        $minRows = $minStmt->fetchAll(PDO::FETCH_ASSOC);

        $minMap = [];
        foreach ($minRows as $r) {
            $minMap[(int) $r['product_id']] = (int) $r['min_id'];
        }

        // Step 2: fetch products
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
                "SELECT pp.id AS pricing_id, pp.product_id, pp.price, pp.compare_at_price,
                        pp.cost_price, pp.currency_code, pp.tax_rate
                 FROM product_pricing pp
                 INNER JOIN products p ON p.id = pp.product_id
                 WHERE pp.id IN ($placeholders) AND p.tenant_id = ?"
            );
            $ppStmt->execute(array_merge($ids, [$tenantId]));
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

    /**
     * Get aggregate statistics
     */
    public function getStatistics(?int $tenantId = null): array
    {
        $where = $tenantId !== null ? 'WHERE tenant_id = ?' : 'WHERE 1=1';
        $args  = $tenantId !== null ? [$tenantId] : [];
        $base  = "FROM entity_products $where";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base");
        $stmt->execute($args);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT entity_id) $base");
        $stmt->execute($args);
        $entities = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT product_id) $base");
        $stmt->execute($args);
        $unique = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base AND is_active = 1");
        $stmt->execute($args);
        $active = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $base AND is_featured = 1");
        $stmt->execute($args);
        $featured = (int) $stmt->fetchColumn();

        return [
            'total_records'           => $total,
            'entities_with_products'  => $entities,
            'unique_products'         => $unique,
            'active_records'          => $active,
            'featured_records'        => $featured,
        ];
    }
}
