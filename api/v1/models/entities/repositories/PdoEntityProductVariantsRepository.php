<?php

declare(strict_types=1);

/**
 * EntityProductVariantQueryRepository
 * Handles all read-only operations for entity product variants.
 */
final class EntityProductVariantQueryRepository
{
    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'variant_id', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'variant_id',
        'is_active', 'is_featured', 'stock_status',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Build dynamic filter clauses
     */
    public function buildFilterClauses(array $filters): array
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

        $tenantId     = (int) $filters['tenant_id'];
        $filterResult = $this->buildFilterClauses($filters);
        $orderBy      = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir     = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT
                epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
                epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
                epv.stock_status, epv.is_active, epv.is_featured,
                epv.created_at, epv.updated_at,
                COALESCE(pt.name, '') AS product_name,
                pv.sku                AS variant_sku,
                pp_v.price            AS variant_price
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            LEFT JOIN product_pricing pp_v
                ON pp_v.product_id = epv.product_id
               AND pp_v.variant_id  = epv.variant_id
               AND pp_v.entity_id  IS NULL
               AND pp_v.is_active   = 1
            WHERE epv.tenant_id = :tenant_id
            {$filterResult['sql']}
            ORDER BY epv.{$orderBy} {$orderDir}
        ";

        if ($limit  !== null) $sql .= ' LIMIT :limit';
        if ($offset !== null) $sql .= ' OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

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

        $tenantId     = (int) $filters['tenant_id'];
        $filterResult = $this->buildFilterClauses($filters);

        $sql = "
            SELECT COUNT(*)
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            WHERE epv.tenant_id = :tenant_id
            {$filterResult['sql']}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        foreach ($filterResult['params'] as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find single record by ID
     */
    public function find(int $id, int $tenantId, int $entityId): ?array
    {
        $sql = "
            SELECT
                epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
                epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
                epv.stock_status, epv.is_active, epv.is_featured,
                epv.created_at, epv.updated_at,
                COALESCE(pt.name, '') AS product_name,
                pv.sku                AS variant_sku,
                pp_v.price            AS variant_price
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            LEFT JOIN product_pricing pp_v
                ON pp_v.product_id = epv.product_id
               AND pp_v.variant_id  = epv.variant_id
               AND pp_v.entity_id  IS NULL
               AND pp_v.is_active   = 1
            WHERE epv.id        = :id
              AND epv.tenant_id = :tenant_id
              AND epv.entity_id = :entity_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId, ':entity_id' => $entityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find by entity and variant
     */
    public function findByEntityAndVariant(int $entityId, int $variantId, int $tenantId): ?array
    {
        $sql = "
            SELECT
                epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
                epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
                epv.stock_status, epv.is_active, epv.is_featured,
                epv.created_at, epv.updated_at,
                COALESCE(pt.name, '') AS product_name,
                pv.sku                AS variant_sku,
                pp_v.price            AS variant_price
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            LEFT JOIN product_pricing pp_v
                ON pp_v.product_id = epv.product_id
               AND pp_v.variant_id  = epv.variant_id
               AND pp_v.entity_id  IS NULL
               AND pp_v.is_active   = 1
            WHERE epv.entity_id  = :entity_id
              AND epv.variant_id  = :variant_id
              AND epv.tenant_id   = :tenant_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id'  => $entityId,
            ':variant_id' => $variantId,
            ':tenant_id'  => $tenantId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all variants for an entity
     */
    public function getEntityVariants(int $entityId, int $tenantId): array
    {
        $sql = "
            SELECT
                epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
                epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
                epv.stock_status, epv.is_active, epv.is_featured,
                epv.created_at, epv.updated_at,
                COALESCE(pt.name, '') AS product_name,
                pv.sku                AS variant_sku,
                pp.id                 AS pricing_id,
                pp.price,
                pp.compare_at_price,
                pp.cost_price,
                pp.currency_code,
                pp.tax_rate
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            LEFT JOIN product_pricing pp
                ON pp.product_id = epv.product_id
               AND pp.variant_id  = epv.variant_id
               AND pp.entity_id   = epv.entity_id
               AND pp.is_active   = 1
            WHERE epv.entity_id = :entity_id
              AND epv.tenant_id = :tenant_id
            ORDER BY epv.product_id, epv.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get variants for a specific entity product
     */
    public function getEntityProductVariants(int $entityId, int $productId, int $tenantId): array
    {
        $sql = "
            SELECT
                epv.id, epv.tenant_id, epv.entity_id, epv.product_id, epv.variant_id,
                epv.stock_quantity, epv.low_stock_threshold, epv.manage_stock,
                epv.stock_status, epv.is_active, epv.is_featured,
                epv.created_at, epv.updated_at,
                COALESCE(pt.name, '') AS product_name,
                pv.sku                AS variant_sku,
                pp_v.price            AS variant_price
            FROM entity_product_variants epv
            JOIN entities e
                ON e.id = epv.entity_id
               AND e.tenant_id = :tenant_id
            LEFT JOIN products p
                ON p.id = epv.product_id
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv
                ON pv.id = epv.variant_id
            LEFT JOIN product_pricing pp_v
                ON pp_v.product_id = epv.product_id
               AND pp_v.variant_id  = epv.variant_id
               AND pp_v.entity_id  IS NULL
               AND pp_v.is_active   = 1
            WHERE epv.entity_id  = :entity_id
              AND epv.product_id  = :product_id
              AND epv.tenant_id   = :tenant_id
            ORDER BY epv.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id'  => $entityId,
            ':product_id' => $productId,
            ':tenant_id'  => $tenantId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get aggregate statistics
     */
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
            'total_records'          => $total,
            'entities_with_variants' => $entities,
            'unique_variants'        => $unique,
            'active_records'         => $active,
        ];
    }
}
