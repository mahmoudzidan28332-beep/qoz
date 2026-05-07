<?php
declare(strict_types=1);

final class PdoProductsRepository extends BaseRepository
{
    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id','sku','slug','barcode','brand_id','is_active',
        'is_featured','is_bestseller','is_new','stock_quantity',
        'low_stock_threshold','stock_status','manage_stock','allow_backorder',
        'total_sales','rating_average','rating_count','views_count',
        'created_at','updated_at','published_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'product_type_id','sku','slug','barcode','brand_id','is_active',
        'is_featured','is_bestseller','is_new','stock_status','manage_stock','allow_backorder'
    ];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $contextTenantId = $this->getTenantId();
        $effectiveTenantId = $contextTenantId > 0
            ? $contextTenantId
            : ((isset($filters['tenant_id']) && is_numeric($filters['tenant_id']) && (int)$filters['tenant_id'] > 0)
                ? (int)$filters['tenant_id']
                : 0);
        $sql = "
            SELECT p.*,
                   COALESCE(pt.name, '') AS name,
                   pt.short_description,
                   pt.description AS translated_description,
                   pt.meta_title,
                   pt.meta_description,
                   pt.meta_keywords,
                   i.id AS image_id,
                   i.url AS image_url,
                   i.thumb_url AS image_thumb_url,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate,
                   pp.pricing_type
            FROM products p
            LEFT JOIN product_translations pt
                ON p.id = pt.product_id AND pt.language_code = :lang
            LEFT JOIN image_types it ON it.name = 'product'
            LEFT JOIN images i
                ON i.owner_id = p.id
               AND i.is_main = 1
               AND i.image_type_id = it.id
            LEFT JOIN (
                SELECT product_id, MIN(id) AS min_id
                FROM product_pricing
                WHERE variant_id IS NULL AND is_active = 1
                GROUP BY product_id
            ) pp_min ON pp_min.product_id = p.id
            LEFT JOIN product_pricing pp ON pp.id = pp_min.min_id
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        if ($effectiveTenantId > 0) {
            $sql .= " AND p.tenant_id = :tenant_id";
            $params[':tenant_id'] = $effectiveTenantId;
        } else {
            // Prevent BaseRepository from auto-injecting tenant_id filter by mentioning it
            $sql .= " AND (p.tenant_id IS NULL OR p.tenant_id > 0)"; 
        }

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku','slug','barcode'])) {
                    $sql .= " AND p.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND p.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY p.{$orderBy} {$orderDir}";

        // Pagination
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

    // ================================
    // Count for pagination
    // ================================
    public function count(array $filters = []): int
    {
        $contextTenantId = $this->getTenantId();
        $effectiveTenantId = $contextTenantId > 0
            ? $contextTenantId
            : ((isset($filters['tenant_id']) && is_numeric($filters['tenant_id']) && (int)$filters['tenant_id'] > 0)
                ? (int)$filters['tenant_id']
                : 0);
        $sql = "SELECT COUNT(*) FROM products WHERE 1=1";
        $params = [];

        if ($effectiveTenantId > 0) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $effectiveTenantId;
        } else {
            // Prevent BaseRepository auto-injection
            $sql .= " AND (tenant_id IS NULL OR tenant_id > 0)";
        }

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku','slug','barcode'])) {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id, string $lang = 'ar'): ?array
    {
        $contextTenantId = $this->getTenantId();
        $sql = "
            SELECT p.*,
                   COALESCE(pt.name, '') AS name,
                   pt.short_description,
                   pt.description AS translated_description,
                   pt.specifications,
                   pt.meta_title,
                   pt.meta_description,
                   pt.meta_keywords,
                   i.id AS image_id,
                   i.url AS image_url,
                   i.thumb_url AS image_thumb_url
            FROM products p
            LEFT JOIN product_translations pt
                ON p.id = pt.product_id AND pt.language_code = :lang
            LEFT JOIN image_types it ON it.name = 'product'
            LEFT JOIN images i
                ON i.owner_id = p.id
               AND i.is_main = 1
               AND i.image_type_id = it.id
            WHERE p.id = :id
            LIMIT 1
        ";
        $params = [':id' => $id, ':lang' => $lang];
        if ($contextTenantId > 0) {
            $sql = str_replace('WHERE p.id = :id', 'WHERE p.tenant_id = :tenant_id AND p.id = :id', $sql);
            $params[':tenant_id'] = $contextTenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول products فقط
    private const PRODUCT_COLUMNS = [
        'product_type_id', 'sku', 'slug', 'barcode', 'brand_id',
        'is_active', 'is_featured', 'is_bestseller', 'is_new',
        'stock_quantity', 'low_stock_threshold', 'stock_status',
        'manage_stock', 'allow_backorder', 'total_sales',
        'rating_average', 'rating_count', 'views_count', 'published_at'
    ];

    public function save(array $data): int
    {
        $contextTenantId = $this->getTenantId();
        $tenantId = $contextTenantId > 0 ? $contextTenantId : (int)($data['tenant_id'] ?? 0);
        $isUpdate = !empty($data['id']);

        $params = $this->buildProductParams($data);

        return $isUpdate
            ? $this->updateProduct((int)$data['id'], $params, $tenantId, $contextTenantId)
            : $this->insertProduct($params, $tenantId);
    }

    private function buildProductParams(array $data): array
    {
        $params = [];
        foreach (self::PRODUCT_COLUMNS as $col) {
            $val = array_key_exists($col, $data) ? $data[$col] : null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }

        if (empty($params[':sku'])) {
            $params[':sku'] = 'PRD-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
        }

        if (empty($params[':slug'])) {
            $name = $data['name'] ?? $params[':sku'];
            $slug = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($name)));
            $slug = trim($slug, '-');
            $params[':slug'] = (empty($slug) ? 'product-' . time() : $slug) . '-' . mt_rand(1000, 9999);
        }

        if (empty($params[':product_type_id'])) {
            $params[':product_type_id'] = 1;
        }

        return $params;
    }

    private function updateProduct(int $id, array $params, int $tenantId, int $contextTenantId): int
    {
        if ($tenantId <= 0) {
            $existing = $this->find($id);
            if ($existing && array_key_exists('tenant_id', $existing)) {
                $tenantId = $existing['tenant_id'] !== null ? (int)$existing['tenant_id'] : 0;
            }
        }
        $params[':tenant_id'] = $tenantId > 0 ? $tenantId : null;
        $params[':id'] = $id;

        if ($contextTenantId > 0) {
            $params[':context_tenant_id'] = $contextTenantId;
            $stmt = $this->pdo->prepare("
                UPDATE products SET
                    tenant_id = :tenant_id,
                    product_type_id = :product_type_id,
                    sku = :sku,
                    slug = :slug,
                    barcode = :barcode,
                    brand_id = :brand_id,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    is_bestseller = :is_bestseller,
                    is_new = :is_new,
                    stock_quantity = :stock_quantity,
                    low_stock_threshold = :low_stock_threshold,
                    stock_status = :stock_status,
                    manage_stock = :manage_stock,
                    allow_backorder = :allow_backorder,
                    total_sales = :total_sales,
                    rating_average = :rating_average,
                    rating_count = :rating_count,
                    views_count = :views_count,
                    published_at = :published_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = :context_tenant_id AND id = :id
            ");
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE products SET
                    tenant_id = :tenant_id,
                    product_type_id = :product_type_id,
                    sku = :sku,
                    slug = :slug,
                    barcode = :barcode,
                    brand_id = :brand_id,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    is_bestseller = :is_bestseller,
                    is_new = :is_new,
                    stock_quantity = :stock_quantity,
                    low_stock_threshold = :low_stock_threshold,
                    stock_status = :stock_status,
                    manage_stock = :manage_stock,
                    allow_backorder = :allow_backorder,
                    total_sales = :total_sales,
                    rating_average = :rating_average,
                    rating_count = :rating_count,
                    views_count = :views_count,
                    published_at = :published_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
        }
        $stmt->execute($params);
        return $id;
    }

    private function insertProduct(array $params, int $tenantId): int
    {
        if ($tenantId < 0) {
            throw new InvalidArgumentException('tenant_id is required for product creation');
        }
        $params[':tenant_id'] = $tenantId > 0 ? $tenantId : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO products (
                tenant_id, product_type_id, sku, slug, barcode, brand_id,
                is_active, is_featured, is_bestseller, is_new,
                stock_quantity, low_stock_threshold, stock_status,
                manage_stock, allow_backorder, total_sales,
                rating_average, rating_count, views_count, published_at
            ) VALUES (
                :tenant_id, :product_type_id, :sku, :slug, :barcode, :brand_id,
                :is_active, :is_featured, :is_bestseller, :is_new,
                :stock_quantity, :low_stock_threshold, :stock_status,
                :manage_stock, :allow_backorder, :total_sales,
                :rating_average, :rating_count, :views_count, :published_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $tenantId = $this->getTenantId();
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM products WHERE tenant_id = :tenant_id AND id = :id"
            );
            return $stmt->execute([':tenant_id'=>$tenantId, ':id'=>$id]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Check subscription product limit for tenant
    // ================================
    public function getSubscriptionProductLimit(): ?array
    {
        $tenantId = $this->getTenantId();
        $stmt = $this->pdo->prepare(
            "SELECT s.id, sp.max_products, sp.plan_name
             FROM subscriptions s
             JOIN subscription_plans sp ON s.plan_id = sp.id
             WHERE s.tenant_id = :tid AND s.status IN ('active','trial')
             ORDER BY s.id DESC LIMIT 1"
        );
        $stmt->execute([':tid' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countByTenant(): int
    {
        $tenantId = $this->getTenantId();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = :tid");
        $stmt->execute([':tid' => $tenantId]);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Tenant-ID resolution helpers (used by safe_helpers)
    // ================================

    public function findTenantIdByProductId(int $productId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT tenant_id FROM products WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && isset($row['tenant_id'])) ? (int)$row['tenant_id'] : null;
    }

    public function findTenantIdByVariantId(int $variantId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.tenant_id
               FROM product_variants pv
               JOIN products p ON p.id = pv.product_id
              WHERE pv.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $variantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && isset($row['tenant_id'])) ? (int)$row['tenant_id'] : null;
    }
}
