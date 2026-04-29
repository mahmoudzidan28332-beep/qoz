<?php
declare(strict_types=1);

final class PdoProduct_categoriesRepository
{
    private PDO $pdo;
    private const TABLE = 'product_categories';
    private const ALLOWED_ORDER_BY = ['id', 'product_id', 'category_id', 'is_primary', 'sort_order'];
    private const ALLOWED_COLUMNS = [
        'product_id', 'category_id', 'is_primary', 'sort_order'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================================
     * List + Filters + Pagination
     * ===================================================== */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $tenantId = TenantContext::require();
        $sql = 'SELECT pc.* FROM ' . self::TABLE . ' pc
                INNER JOIN products p ON pc.product_id = p.id
                WHERE 1=1';
        $params = [];
        if ($tenantId > 0) {
            $sql .= ' AND p.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if (!empty($filters['product_id'])) {
            $sql .= ' AND pc.product_id = :product_id';
            $params['product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND pc.category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['is_primary'])) {
            $sql .= ' AND pc.is_primary = :is_primary';
            $params['is_primary'] = (int)$filters['is_primary'];
        }

        // ORDER BY safe
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY pc.{$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . ltrim($key, ':'), $value, PDO::PARAM_INT);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
     * Count
     * ===================================================== */
    public function count(array $filters = []): int
    {
        $tenantId = TenantContext::require();
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE . ' pc
                INNER JOIN products p ON pc.product_id = p.id
                WHERE 1=1';
        $params = [];
        if ($tenantId > 0) {
            $sql .= ' AND p.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if (!empty($filters['product_id'])) {
            $sql .= ' AND pc.product_id = :product_id';
            $params['product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND pc.category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['is_primary'])) {
            $sql .= ' AND pc.is_primary = :is_primary';
            $params['is_primary'] = (int)$filters['is_primary'];
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . ltrim($key, ':'), $value, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /* =====================================================
     * Find by ID
     * ===================================================== */
    public function find(int $id): ?array
    {
        $tenantId = TenantContext::require();
        
        $sql = '
            SELECT pc.* FROM ' . self::TABLE . ' pc
            INNER JOIN products p ON pc.product_id = p.id
            WHERE pc.id = :id
        ';
        $params = ['id' => $id];
        if ($tenantId > 0) {
            $sql .= ' AND p.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Save (Insert / Update)
     * ===================================================== */
    public function save(array $data): int
    {
        $tenantId = TenantContext::require();
        
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS)) + (isset($data['id']) ? ['id' => $data['id']] : []);
        $isUpdate = !empty($data['id']);

        // Verify product belongs to tenant (if not platform admin)
        $productId = (int)$data['product_id'];
        if ($tenantId > 0) {
            $checkStmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
            $checkStmt->execute([$productId, $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new InvalidArgumentException("Product not found or access denied.");
            }
        }

        if ($isUpdate) {
            $id = (int)$data['id'];
            
            if ($tenantId > 0) {
                // Verify assignment record belongs to tenant via product
                $assignCheck = $this->pdo->prepare("
                    SELECT pc.id FROM " . self::TABLE . " pc 
                    JOIN products p ON pc.product_id = p.id 
                    WHERE pc.id = ? AND p.tenant_id = ?
                ");
                $assignCheck->execute([$id, $tenantId]);
                if (!$assignCheck->fetch()) {
                    throw new InvalidArgumentException("Assignment record not found or access denied.");
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET product_id = :product_id, category_id = :category_id, is_primary = :is_primary, sort_order = :sort_order
                WHERE id = :id
            ");
            $stmt->execute([
                'product_id'  => $productId,
                'category_id' => (int)$data['category_id'],
                'is_primary'  => (int)($data['is_primary'] ?? 0),
                'sort_order'  => (int)($data['sort_order'] ?? 0),
                'id'          => $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (product_id, category_id, is_primary, sort_order)
            VALUES (:product_id, :category_id, :is_primary, :sort_order)
        ");
        $stmt->execute([
            'product_id'  => $productId,
            'category_id' => (int)$data['category_id'],
            'is_primary'  => (int)($data['is_primary'] ?? 0),
            'sort_order'  => (int)($data['sort_order'] ?? 0),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /* =====================================================
     * Delete
     * ===================================================== */
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::require();
        
        if ($tenantId > 0) {
            // Verify assignment record belongs to tenant via product
            $assignCheck = $this->pdo->prepare("
                SELECT pc.id FROM " . self::TABLE . " pc 
                JOIN products p ON pc.product_id = p.id 
                WHERE pc.id = ? AND p.tenant_id = ?
            ");
            $assignCheck->execute([$id, $tenantId]);
            if (!$assignCheck->fetch()) {
                return false;
            }
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}