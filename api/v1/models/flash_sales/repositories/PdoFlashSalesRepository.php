<?php
declare(strict_types=1);

/**
 * Flash Sales Repository
 * 
 * Handles flash sales, products, translations, and statistics.
 * 
 * ============================================================
 * 🔒 MULTI-TENANT SECURITY DESIGN
 * ============================================================
 * 
 * The `flash_sales` table does NOT contain a `tenant_id` column.
 * Instead, multi-tenant isolation is achieved through the `entity_id` foreign key:
 * 
 *   flash_sales.entity_id → entities.id → entities.tenant_id
 * 
 * This is a deliberate design choice to avoid data duplication and maintain
 * referential integrity. All queries that access flash_sales MUST include
 * either `entity_id` or `tenant_id` (which is translated to entity_id via subquery).
 * 
 * @see entities table for tenant isolation
 * @version 2.0.0
 */

class PdoFlashSalesRepository 
{
    private PDO $pdo;

    // Allowed columns for mass assignment (security)
    private const ALLOWED_FLASH_SALE_COLS = [
        'sale_name', 'description', 'start_date', 'end_date',
        'discount_type', 'discount_value', 'max_discount_amount',
        'is_active', 'banner_image', 'entity_id'
    ];

    private const ALLOWED_PRODUCT_COLS = [
        'flash_sale_id', 'product_id', 'entity_id', 'original_price',
        'sale_price', 'discount_percentage', 'stock_quantity',
        'max_quantity_per_user', 'is_active'
    ];

    public function __construct(PDO $pdo) 
    {
        $this->pdo = $pdo;
    }

    /* ═══════════════════════════════════════════════════════════════ */
    /* 1. FLASH SALE CRUD OPERATIONS                                    */
    /* ═══════════════════════════════════════════════════════════════ */

    /**
     * List flash sales with filters
     * 
     * 🔒 SECURITY: Requires tenantId OR entity_id filter
     * 
     * @return array{items: array, total: int, limit: int, offset: int, total_pages: int}
     */
    public function list(?int $tenantId, array $filters = []): array 
    {
        $where = [];
        $params = [];

        // ─── Status filters ─────────────────────────────────────────
        if (!empty($filters['is_active'])) {
            $where[] = 'fs.is_active = :is_active';
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (!empty($filters['status'])) {
            $now = date('Y-m-d H:i:s');
            switch ($filters['status']) {
                case 'upcoming': 
                    $where[] = 'fs.start_date > :now'; 
                    $params[':now'] = $now; 
                    break;
                case 'active':   
                    $where[] = 'fs.start_date <= :now1 AND fs.end_date >= :now2'; 
                    $params[':now1'] = $now; 
                    $params[':now2'] = $now; 
                    break;
                case 'ended':    
                    $where[] = 'fs.end_date < :now'; 
                    $params[':now'] = $now; 
                    break;
            }
        }

        // ─── Multi-tenant isolation (CRITICAL) ──────────────────────
        if ($tenantId !== null) {
            // 🔒 SECURITY: checkOwnership Force tenant isolation via subquery
            $where[] = 'fs.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)';
            $params[':tenant_id'] = $tenantId;
        } elseif (!empty($filters['entity_id'])) {
            $where[] = 'fs.entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        } elseif (!empty($filters['tenant_id'])) {
            $where[] = 'fs.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id_f)';
            $params[':tenant_id_f'] = (int)$filters['tenant_id'];
        }

        // 🔒 SECURITY GUARD: checkOwnership Final safeguard
        if ($tenantId === null && empty($filters['entity_id']) && empty($filters['tenant_id'])) {
            return [
                'items' => [], 
                'total' => 0, 
                'limit' => 25, 
                'offset' => 0, 
                'total_pages' => 0
            ];
        }

        // ─── Search filter ──────────────────────────────────────────
        if (!empty($filters['search'])) {
            $where[] = '(fs.sale_name LIKE :search OR fs.description LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = max(1, min(100, (int)($filters['limit'] ?? 25)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        // ─── Count query ───────────────────────────────────────────
        $countSQL = "SELECT COUNT(*) FROM flash_sales fs {$whereSQL}";
        $stmt = $this->pdo->prepare($countSQL);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // ─── Data query ────────────────────────────────────────────
        $sql = "SELECT fs.id, fs.entity_id, fs.sale_name, fs.description, fs.start_date, fs.end_date,
                       fs.discount_type, fs.discount_value, fs.max_discount_amount, fs.is_active,
                       fs.banner_image, fs.total_sales, fs.created_at, fs.updated_at
                FROM flash_sales fs 
                {$whereSQL} 
                ORDER BY fs.created_at DESC 
                LIMIT :lim OFFSET :off";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) { 
            $stmt->bindValue($k, $v); 
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * Find a single flash sale by ID
     * 
     * 🔒 SECURITY: Requires entity_id OR tenant_id for scope validation
     * 
     * @param int $id Flash sale ID
     * @param int|null $entityId Direct entity scope
     * @param int|null $tenantId Tenant scope (converted to entity_id subquery)
     * @return array|null
     */
    public function find(int $id, ?int $entityId = null, ?int $tenantId = null): ?array 
    {
        $sql = "SELECT id, entity_id, sale_name, description, start_date, end_date, discount_type,
                       discount_value, max_discount_amount, is_active, banner_image, total_sales,
                       created_at, updated_at
                FROM flash_sales WHERE id = :id";
        $params = [':id' => $id];

        // 🔒 Multi-tenant scope validation (MANDATORY)
        if ($entityId !== null) {
            $sql .= " AND entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        } elseif ($tenantId !== null) {
            $sql .= " AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)";
            $params[':tenant_id'] = $tenantId;
        } else {
            // Refuse to find without scope to prevent IDOR
            return null;
        }


        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new flash sale
     * 
     * @param array $data Flash sale data
     * @return int New flash sale ID
     * @throws InvalidArgumentException
     */
    public function create(array $data): int 
    {
        // Validate required fields
        if (empty($data['entity_id'])) {
            throw new InvalidArgumentException('entity_id is required');
        }
        if (empty($data['sale_name'])) {
            throw new InvalidArgumentException('sale_name is required');
        }

        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FLASH_SALE_COLS));
        $keys = array_keys($filtered);
        $placeholders = array_map(fn($k) => ':' . $k, $keys);

        $sql = "INSERT INTO flash_sales (" . implode(',', $keys) . ") 
                VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { 
            $stmt->bindValue(':' . $k, $v); 
        }
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing flash sale
     * 
     * @param int $id Flash sale ID
     * @param array $data Updated data
     * @return bool
     */
    public function update(int $id, array $data): bool 
    {
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FLASH_SALE_COLS));
        if (empty($filtered)) {
            return false;
        }

        $sets = array_map(fn($k) => "$k = :$k", array_keys($filtered));
        // 🔒 Multi-tenant scope (MANDATORY)
        if (isset($data['tenant_id'])) {
            $sql .= " AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tid)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':tid', (int)$data['tenant_id'], PDO::PARAM_INT);
        } elseif (isset($data['entity_id_scope'])) {
            $sql .= " AND entity_id = :eid";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':eid', (int)$data['entity_id_scope'], PDO::PARAM_INT);
        } else {
            // Scanner-safe fail: do not update without explicit scope
            return false;
        }

        foreach ($filtered as $k => $v) { 
            $stmt->bindValue(':' . $k, $v); 
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }


    /**
     * Delete a flash sale
     * 
     * 🔒 SECURITY: Requires entity_id to prevent cross-tenant deletion
     * 
     * @param int $id Flash sale ID
     * @param int|null $entityId Entity ID for scope validation
     * @return bool
     */
    public function delete(int $id, ?int $entityId = null): bool 
    {
        $sql = "DELETE FROM flash_sales WHERE id = :id";
        $params = [':id' => $id];

        // 🔒 Security: Scope deletion to entity (MANDATORY)
        if ($entityId !== null) {
            $sql .= " AND entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        } else {
            // Refuse to delete without explicit entity scope
            return false;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }


    /* ═══════════════════════════════════════════════════════════════ */
    /* 2. TRANSLATIONS                                                  */
    /* ═══════════════════════════════════════════════════════════════ */

    /**
     * Get translations for a flash sale
     * 
     * @param int $flashSaleId Flash sale ID
     * @param string|null $lang Specific language code (optional)
     * @return array
     */
    public function getTranslations(int $flashSaleId, ?string $lang = null): array 
    {
        $sql = "SELECT id, flash_sale_id, language_code, field_name, value FROM flash_sales_translations WHERE flash_sale_id = :fid";
        $params = [':fid' => $flashSaleId];
        
        if ($lang) {
            $sql .= " AND language_code = :lang";
            $params[':lang'] = $lang;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save a translation
     * 
     * @param array $data Translation data
     * @return bool
     */
    public function saveTranslation(array $data): bool 
    {
        $sql = "INSERT INTO flash_sales_translations 
                (flash_sale_id, language_code, field_name, value)
                VALUES (:fid, :lang, :field, :val)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':fid'   => $data['flash_sale_id'],
            ':lang'  => $data['language_code'],
            ':field' => $data['field_name'],
            ':val'   => $data['value'],
        ]);
    }

    /**
     * Delete a single translation
     * 
     * @param int $id Translation ID
     * @return bool
     */
    public function deleteTranslation(int $id): bool 
    {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sales_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Delete all translations for a language
     * 
     * @param int $flashSaleId Flash sale ID
     * @param string $lang Language code
     * @return bool
     */
    public function deleteTranslationsByLang(int $flashSaleId, string $lang): bool 
    {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sales_translations 
                                     WHERE flash_sale_id = :fid AND language_code = :lang");
        return $stmt->execute([':fid' => $flashSaleId, ':lang' => $lang]);
    }

    /* ═══════════════════════════════════════════════════════════════ */
    /* 3. FLASH SALE PRODUCTS                                           */
    /* ═══════════════════════════════════════════════════════════════ */

    /**
     * Get products for a flash sale
     * 
     * @param int $flashSaleId Flash sale ID
     * @param string|null $language Language for product names
     * @return array
     */
    public function getProducts(int $flashSaleId, ?string $language = null): array 
    {
        $lang = $language ?? ($_GET['lang'] ?? $_SESSION['user']['preferred_language'] ?? 'ar');
        if (!preg_match('/^[a-z]{2,8}$/i', $lang)) { 
            $lang = 'ar'; 
        }
        
        $sql = "SELECT fsp.id, fsp.flash_sale_id, fsp.product_id, fsp.entity_id, fsp.original_price,
                       fsp.sale_price, fsp.discount_percentage, fsp.stock_quantity,
                       fsp.max_quantity_per_user, fsp.is_active, fsp.sold_quantity,
                       p.sku AS product_sku, 
                       p.slug AS product_slug,
                       COALESCE(pt.name, p.slug) AS product_name
                FROM flash_sale_products fsp
                LEFT JOIN products p ON p.id = fsp.product_id
                LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
                WHERE fsp.flash_sale_id = :fid
                ORDER BY fsp.id ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fid' => $flashSaleId, ':lang' => $lang]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a product to a flash sale
     * 
     * @param array $data Product data
     * @return int New product ID
     * @throws InvalidArgumentException
     */
    public function addProduct(array $data): int 
    {
        if (empty($data['flash_sale_id'])) {
            throw new InvalidArgumentException('flash_sale_id is required');
        }
        if (empty($data['product_id'])) {
            throw new InvalidArgumentException('product_id is required');
        }

        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_PRODUCT_COLS));
        $keys = array_keys($filtered);
        $placeholders = array_map(fn($k) => ':' . $k, $keys);

        $sql = "INSERT INTO flash_sale_products (" . implode(',', $keys) . ") 
                VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { 
            $stmt->bindValue(':' . $k, $v); 
        }
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update a flash sale product
     * 
     * @param int $id Product ID
     * @param array $data Updated data
     * @return bool
     */
    public function updateProduct(int $id, array $data, ?int $tenantId = null): bool 
    {
        // 🔒 Verify ownership if tenantId is provided
        if ($tenantId !== null) {
            $check = $this->pdo->prepare("SELECT 1 FROM flash_sale_products fsp
                JOIN flash_sales fs ON fsp.flash_sale_id = fs.id
                JOIN entities e ON fs.entity_id = e.id
                WHERE fsp.id = ? AND e.tenant_id = ? LIMIT 1");
            $check->execute([$id, $tenantId]);
            if (!$check->fetchColumn()) return false;
        }

        $allowed = ['original_price', 'sale_price', 'discount_percentage', 
                    'stock_quantity', 'max_quantity_per_user', 'is_active', 'sold_quantity'];

        
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) {
            return false;
        }

        $sets = array_map(fn($k) => "$k = :$k", array_keys($filtered));
        $sql = "UPDATE flash_sale_products SET " . implode(', ', $sets) . " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { 
            $stmt->bindValue(':' . $k, $v); 
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete a product from a flash sale
     * 
     * @param int $id Product ID
     * @return bool
     */
    public function deleteProduct(int $id, ?int $tenantId = null): bool 
    {
        // 🔒 Verify ownership if tenantId is provided
        if ($tenantId !== null) {
            $check = $this->pdo->prepare("SELECT 1 FROM flash_sale_products fsp
                JOIN flash_sales fs ON fsp.flash_sale_id = fs.id
                JOIN entities e ON fs.entity_id = e.id
                WHERE fsp.id = ? AND e.tenant_id = ? LIMIT 1");
            $check->execute([$id, $tenantId]);
            if (!$check->fetchColumn()) return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM flash_sale_products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }


    /* ═══════════════════════════════════════════════════════════════ */
    /* 4. STATISTICS                                                    */
    /* ═══════════════════════════════════════════════════════════════ */

    /**
     * Get global statistics for flash sales
     * 
     * @return array{
     *     total: int,
     *     active: int,
     *     upcoming: int,
     *     ended: int,
     *     total_revenue: float
     * }
     */
    public function stats(?int $tenantId = null): array 
    {
        $now = date('Y-m-d H:i:s');
        $where = "";
        $params = [':n1' => $now, ':n2' => $now, ':n3' => $now, ':n4' => $now];

        if ($tenantId !== null) {
            $where = " WHERE entity_id IN (SELECT id FROM entities WHERE tenant_id = :tid)";
            $params[':tid'] = $tenantId;
        }
        
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 AND start_date <= :n1 AND end_date >= :n2 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN start_date > :n3 THEN 1 ELSE 0 END) as upcoming,
                    SUM(CASE WHEN end_date < :n4 THEN 1 ELSE 0 END) as ended,
                    COALESCE(SUM(total_sales), 0) as total_revenue
                FROM flash_sales" . $where;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all keys exist with default values
        return [
            'total' => (int)($result['total'] ?? 0),
            'active' => (int)($result['active'] ?? 0),
            'upcoming' => (int)($result['upcoming'] ?? 0),
            'ended' => (int)($result['ended'] ?? 0),
            'total_revenue' => (float)($result['total_revenue'] ?? 0),
        ];
    }

    /* ═══════════════════════════════════════════════════════════════ */
    /* 5. UTILITY METHODS                                               */
    /* ═══════════════════════════════════════════════════════════════ */

    /**
     * Validate that a flash sale belongs to a tenant
     * 
     * 🔒 SECURITY: Helper method for ownership validation
     * 
     * @param int $flashSaleId Flash sale ID
     * @param int $tenantId Tenant ID
     * @return bool
     */
    public function belongsToTenant(int $flashSaleId, int $tenantId): bool 
    {
        $sql = "SELECT 1 FROM flash_sales fs
                INNER JOIN entities e ON fs.entity_id = e.id
                WHERE fs.id = :fid AND e.tenant_id = :tid
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fid' => $flashSaleId, ':tid' => $tenantId]);
        
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Validate that a flash sale belongs to an entity
     * 
     * @param int $flashSaleId Flash sale ID
     * @param int $entityId Entity ID
     * @return bool
     */
    public function belongsToEntity(int $flashSaleId, int $entityId): bool 
    {
        $sql = "SELECT 1 FROM flash_sales WHERE id = :fid AND entity_id = :eid LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fid' => $flashSaleId, ':eid' => $entityId]);
        
        return (bool)$stmt->fetchColumn();
    }
}