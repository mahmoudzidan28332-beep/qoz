<?php
declare(strict_types=1);

/**
 * PdoProductsRepository
 *
 * Hardened repository for product management with strict tenant isolation (CWE-284).
 */
final class PdoProductsRepository extends BaseRepository implements ProductsRepositoryInterface
{
    private const ALLOWED_ORDER_BY = [
        'id', 'sku', 'slug', 'barcode', 'brand_id', 'is_active',
        'is_featured', 'is_bestseller', 'is_new', 'stock_quantity',
        'low_stock_threshold', 'stock_status', 'manage_stock', 'allow_backorder',
        'total_sales', 'rating_average', 'rating_count', 'views_count',
        'created_at', 'updated_at', 'published_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'product_type_id', 'sku', 'slug', 'barcode', 'brand_id', 'is_active',
        'is_featured', 'is_bestseller', 'is_new', 'stock_status', 'manage_stock', 'allow_backorder'
    ];

    private const PRODUCT_COLUMNS = [
        'product_type_id', 'sku', 'slug', 'barcode', 'brand_id',
        'is_active', 'is_featured', 'is_bestseller', 'is_new',
        'stock_quantity', 'low_stock_threshold', 'stock_status',
        'manage_stock', 'allow_backorder', 'total_sales',
        'rating_average', 'rating_count', 'views_count', 'published_at'
    ];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * @inheritDoc
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        try {
            $tenantId = $this->getTenantId();
            $params = [
                ':tenant_id_main' => $tenantId,
                ':tenant_id_sub'  => $tenantId,
                ':lang'           => $lang
            ];
            
            $sql = $this->buildListQuery($filters, $orderBy, $orderDir, $params);
            
            if ($limit !== null) {
                $sql .= " LIMIT :limit";
                $params[':limit'] = (int)$limit;
            }
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$offset;
            }

            return $this->executeList($sql, $params);
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] List failed: ' . $e->getMessage());
            throw new RuntimeException('Database error while listing products', 0, $e);
        }
    }

    private function buildListQuery(array $filters, string $orderBy, string $orderDir, array &$params): string
    {
        $sql = "
            SELECT p.id, p.tenant_id, p.product_type_id, p.sku, p.slug, p.barcode, p.brand_id,
                   p.is_active, p.is_featured, p.is_bestseller, p.is_new, p.stock_quantity,
                   p.stock_status, p.manage_stock, p.allow_backorder, p.total_sales,
                   p.rating_average, p.rating_count, p.views_count, p.created_at, p.updated_at,
                   COALESCE(pt.name, '') AS name,
                   pt.short_description, pt.meta_title,
                   pp.price, pp.compare_at_price, pp.currency_code
            FROM products p
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            LEFT JOIN (
                SELECT pricing.product_id, pricing.price, pricing.compare_at_price, pricing.currency_code
                FROM product_pricing pricing
                JOIN products prod ON prod.id = pricing.product_id
                WHERE pricing.variant_id IS NULL AND pricing.is_active = 1 AND prod.tenant_id = :tenant_id_sub
                GROUP BY pricing.product_id, pricing.price, pricing.compare_at_price, pricing.currency_code
            ) pp ON pp.product_id = p.id
            WHERE p.tenant_id = :tenant_id_main
        ";

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $placeholder = ':' . str_replace('.', '_', $col);
                if (in_array($col, ['sku', 'slug', 'barcode'], true)) {
                    $sql .= " AND p.{$col} LIKE {$placeholder}";
                    $params[$placeholder] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND p.{$col} = {$placeholder}";
                    $params[$placeholder] = $filters[$col];
                }
            }
        }

        $orderCol = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY p.{$orderCol} {$orderDir}";

        return $sql;
    }

    private function executeList(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function count(array $filters = []): int
    {
        try {
            $tenantId = $this->getTenantId();
            $params = [':tenant_id' => $tenantId];
            $sql = "SELECT COUNT(id) FROM products WHERE tenant_id = :tenant_id";

            foreach (self::FILTERABLE_COLUMNS as $col) {
                if (isset($filters[$col]) && $filters[$col] !== '') {
                    $placeholder = ':f_' . $col;
                    if (in_array($col, ['sku', 'slug', 'barcode'], true)) {
                        $sql .= " AND {$col} LIKE {$placeholder}";
                        $params[$placeholder] = '%' . $filters[$col] . '%';
                    } else {
                        $sql .= " AND {$col} = {$placeholder}";
                        $params[$placeholder] = $filters[$col];
                    }
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] Count failed: ' . $e->getMessage());
            throw new RuntimeException('Database error while counting products', 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function find(int $id, string $lang = 'ar'): ?array
    {
        try {
            $tenantId = $this->getTenantId();
            $sql = "
                SELECT p.id, p.tenant_id, p.product_type_id, p.sku, p.slug, p.barcode, p.brand_id,
                       p.is_active, p.is_featured, p.is_bestseller, p.is_new, p.stock_quantity,
                       p.low_stock_threshold, p.stock_status, p.manage_stock, p.allow_backorder,
                       p.total_sales, p.rating_average, p.rating_count, p.views_count, p.created_at, p.updated_at,
                       COALESCE(pt.name, '') AS name,
                       pt.short_description, pt.description AS translated_description,
                       pt.specifications, pt.meta_title, pt.meta_description, pt.meta_keywords
                FROM products p
                LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
                WHERE p.id = :id
            ";
            
            $params = [':id' => $id, ':lang' => $lang];
            
            if ($tenantId > 0) {
                $sql .= " AND p.tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            }

            $sql .= " LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] Find failed: ' . $e->getMessage());
            throw new RuntimeException('Database error while fetching product', 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): int
    {
        try {
            $tenantId = $this->getTenantId();
            $isUpdate = !empty($data['id']);
            $params = $this->prepareProductParams($data);
            $params[':tenant_id'] = $tenantId;

            if ($isUpdate) {
                return $this->updateProduct((int)$data['id'], $params);
            }
            return $this->insertProduct($params);
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] Save failed: ' . $e->getMessage());
            throw new RuntimeException('Database error while saving product', 0, $e);
        }
    }

    private function prepareProductParams(array $data): array
    {
        $params = [];
        foreach (self::PRODUCT_COLUMNS as $col) {
            $val = $data[$col] ?? null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }

        if (empty($params[':sku'])) {
            $params[':sku'] = 'PRD-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
        }

        if (empty($params[':slug'])) {
            $name = $data['name'] ?? $params[':sku'];
            $slug = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim((string)$name)));
            $params[':slug'] = trim((string)$slug, '-') . '-' . mt_rand(1000, 9999);
        }

        if (empty($params[':product_type_id'])) {
            $params[':product_type_id'] = 1;
        }

        return $params;
    }

    private function updateProduct(int $id, array $params): int
    {
        $params[':id'] = $id;
        $tenantId = (int)($params[':tenant_id'] ?? 0);
        
        $sql = "
            UPDATE products SET
                product_type_id = :product_type_id, sku = :sku, slug = :slug, barcode = :barcode,
                brand_id = :brand_id, is_active = :is_active, is_featured = :is_featured,
                is_bestseller = :is_bestseller, is_new = :is_new, stock_quantity = :stock_quantity,
                low_stock_threshold = :low_stock_threshold, stock_status = :stock_status,
                manage_stock = :manage_stock, allow_backorder = :allow_backorder,
                total_sales = :total_sales, rating_average = :rating_average,
                rating_count = :rating_count, views_count = :views_count,
                published_at = :published_at, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        if ($tenantId > 0) {
            $sql .= " AND tenant_id = :tenant_id";
        } else {
            // If Platform Admin (tenant_id = 0), we must ensure we DON'T update the tenant_id of the product to 0
            // but we also need to avoid the WHERE clause check for tenant_id.
            unset($params[':tenant_id']);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $id;
    }

    private function insertProduct(array $params): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO products (
                tenant_id, product_type_id, sku, slug, barcode, brand_id,
                is_active, is_featured, is_bestseller, is_new, stock_quantity,
                low_stock_threshold, stock_status, manage_stock, allow_backorder,
                total_sales, rating_average, rating_count, views_count, published_at
            ) VALUES (
                :tenant_id, :product_type_id, :sku, :slug, :barcode, :brand_id,
                :is_active, :is_featured, :is_bestseller, :is_new, :stock_quantity,
                :low_stock_threshold, :stock_status, :manage_stock, :allow_backorder,
                :total_sales, :rating_average, :rating_count, :views_count, :published_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool
    {
        try {
            $tenantId = $this->getTenantId();
            $sql = "DELETE FROM products WHERE id = :id";
            $params = [':id' => $id];
            
            if ($tenantId > 0) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            }

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] Delete failed: ' . $e->getMessage());
            throw new RuntimeException('Database error while deleting product', 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getSubscriptionProductLimit(): ?array
    {
        try {
            $tenantId = $this->getTenantId();
            $stmt = $this->pdo->prepare("
                SELECT s.id, sp.max_products, sp.plan_name
                FROM subscriptions s
                JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE s.tenant_id = :tid AND s.status IN ('active','trial')
                ORDER BY s.id DESC LIMIT 1
            ");
            $stmt->execute([':tid' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] Subscription limit check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function countByTenant(): int
    {
        try {
            $tenantId = $this->getTenantId();
            $stmt = $this->pdo->prepare("SELECT COUNT(id) FROM products WHERE tenant_id = :tid");
            $stmt->execute([':tid' => $tenantId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[PdoProductsRepository] CountByTenant failed: ' . $e->getMessage());
            return 0;
        }
    }
}