<?php
declare(strict_types=1);

/**
 * PDO repository for the discounts table.
 */
final class PdoDiscountsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'code', 'type', 'priority', 'status', 'starts_at', 'ends_at', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'entity_id', 'type', 'code', 'auto_apply', 'priority', 'is_stackable',
        'currency_code', 'max_redemptions', 'max_redemptions_per_user',
        'current_redemptions', 'starts_at', 'ends_at', 'status', 'created_by',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    /**
     * @param array{entity_id?: string, status?: string, type?: string, search?: string} $filters
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->query($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    // ================================
    // Query rows
    // ================================
    private function query(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $sql = "SELECT d.id, d.entity_id, d.type, d.code, d.auto_apply, d.priority, d.is_stackable, d.currency_code, d.max_redemptions, d.max_redemptions_per_user, d.current_redemptions, d.starts_at, d.ends_at, d.status, d.created_by, d.created_at, d.updated_at FROM discounts d WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY d.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM discounts d WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Apply filters (shared by query & count)
    // ================================
    private function applyFilters(string &$sql, array &$params, array $filters): void
    {
        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND d.entity_id = :entity_id";
            $params[':entity_id'] = $filters['entity_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND d.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['type']) && $filters['type'] !== '') {
            $sql .= " AND d.type = :type";
            $params[':type'] = $filters['type'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (d.code LIKE :search OR EXISTS (
                SELECT 1 FROM discount_translations dt
                WHERE dt.discount_id = d.id AND dt.name LIKE :search_t
            ))";
            $params[':search']   = '%' . trim($filters['search']) . '%';
            $params[':search_t'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, entity_id, type, code, auto_apply, priority, is_stackable, currency_code, max_redemptions, max_redemptions_per_user, current_redemptions, starts_at, ends_at, status, created_by, created_at, updated_at FROM discounts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection
        if (class_exists('SecurityValidators')) {
            $data = SecurityValidators::filterInput($data, self::ALLOWED_COLUMNS);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO discounts
                (entity_id, type, code, auto_apply, priority, is_stackable, currency_code,
                 max_redemptions, max_redemptions_per_user, current_redemptions,
                 starts_at, ends_at, status, created_by, created_at, updated_at)
            VALUES
                (:entity_id, :type, :code, :auto_apply, :priority, :is_stackable, :currency_code,
                 :max_redemptions, :max_redemptions_per_user, :current_redemptions,
                 :starts_at, :ends_at, :status, :created_by, NOW(), NOW())
        ");

        $stmt->execute([
            ':entity_id'                => $data['entity_id'],
            ':type'                     => $data['type'],
            ':code'                     => $data['code'] ?? null,
            ':auto_apply'               => (int)($data['auto_apply'] ?? 0),
            ':priority'                 => (int)($data['priority'] ?? 0),
            ':is_stackable'             => (int)($data['is_stackable'] ?? 0),
            ':currency_code'            => $data['currency_code'],
            ':max_redemptions'          => $data['max_redemptions'] ?? null,
            ':max_redemptions_per_user' => $data['max_redemptions_per_user'] ?? null,
            ':current_redemptions'      => (int)($data['current_redemptions'] ?? 0),
            ':starts_at'                => $data['starts_at'] ?? null,
            ':ends_at'                  => $data['ends_at'] ?? null,
            ':status'                   => $data['status'] ?? 'active',
            ':created_by'               => $data['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach (self::ALLOWED_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        $setClauses[] = "updated_at = NOW()";
        $sql = "UPDATE discounts SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discounts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Public discount listing methods
    // ================================
    public function publicCount(?int $tenantId, ?int $entityId, string $type, bool $activeOnly, bool $expiresToday): int
    {
        $where = [];
        $params = [];
        $this->buildPublicFilters($where, $params, $tenantId, $entityId, $type, $activeOnly, $expiresToday);
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM discounts d $whereSQL");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function publicList(
        ?int $tenantId,
        ?int $entityId,
        string $type,
        bool $activeOnly,
        bool $expiresToday,
        string $lang,
        int $perPage,
        int $offset
    ): array {
        $where = [];
        $params = [];
        $this->buildPublicFilters($where, $params, $tenantId, $entityId, $type, $activeOnly, $expiresToday);
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.entity_id, d.code, d.type, d.auto_apply, d.priority,
                    d.is_stackable, d.currency_code, d.status,
                    d.max_redemptions, d.max_redemptions_per_user, d.current_redemptions,
                    d.starts_at, d.ends_at, d.created_at, d.updated_at,
                    COALESCE(dt.name, d.code, d.type) AS title,
                    dt.description, dt.terms_conditions, dt.marketing_badge,
                    e.id   AS merchant_id,
                    COALESCE(et.name, e.slug) AS merchant_name
             FROM discounts d
             LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
             LEFT JOIN entities e ON e.id = d.entity_id
             LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
             $whereSQL
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT " . $perPage . " OFFSET " . $offset
        );
        $stmt->execute(array_merge([$lang, $lang], $params));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActionsForIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $phs = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT discount_id, action_type, action_value
             FROM discount_actions
             WHERE discount_id IN ($phs)
             ORDER BY id ASC"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScopesForIds(array $ids, string $lang): array
    {
        if (empty($ids)) {
            return [];
        }
        $phs = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT ds.discount_id, ds.scope_type, 
                    COALESCE(pt.name, p.slug) as product_name,
                    COALESCE(ct.name, c.slug) as category_name,
                    COALESCE(et.name, e.slug) as entity_name
             FROM discount_scopes ds
             LEFT JOIN products p ON ds.scope_type = 'product' AND p.id = ds.scope_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
             LEFT JOIN categories c ON ds.scope_type = 'category' AND c.id = ds.scope_id
             LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
             LEFT JOIN entities e ON ds.scope_type = 'entity' AND e.id = ds.scope_id
             LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
             WHERE ds.discount_id IN ($phs)"
        );
        $stmt->execute(array_merge([$lang, $lang, $lang], $ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildPublicFilters(array &$where, array &$params, ?int $tenantId, ?int $entityId, string $type, bool $activeOnly, bool $expiresToday): void
    {
        if ($tenantId) {
            $where[]  = "(d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?) 
                         OR EXISTS (SELECT 1 FROM discount_scopes ds_t 
                                    LEFT JOIN categories c_t ON ds_t.scope_type = 'category' AND c_t.id = ds_t.scope_id
                                    LEFT JOIN products p_t ON ds_t.scope_type = 'product' AND p_t.id = ds_t.scope_id
                                    WHERE ds_t.discount_id = d.id 
                                      AND (c_t.tenant_id = ? OR p_t.tenant_id = ?)))";
            $params[] = $tenantId;
            $params[] = $tenantId;
            $params[] = $tenantId;
        }
        if ($entityId) {
            $where[]  = 'd.entity_id = ?';
            $params[] = $entityId;
        }
        if ($activeOnly) {
            $where[]  = "d.status = 'active'";
            $where[]  = '(d.starts_at IS NULL OR d.starts_at <= NOW())';
            $where[]  = '(d.ends_at IS NULL OR d.ends_at >= NOW())';
        }
        if ($expiresToday) {
            $where[] = 'd.ends_at IS NOT NULL AND d.ends_at >= NOW() AND d.ends_at <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        }
        if ($type !== '') {
            $where[]  = 'd.type = ?';
            $params[] = $type;
        }
    }

    // ================================
    // Stats
    // ================================
    /**
     * @return array{total: int, active: int, inactive: int, expired: int, scheduled: int, total_redemptions: int}
     */
    public function stats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' AND (ends_at IS NULL OR ends_at >= NOW()) THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN ends_at IS NOT NULL AND ends_at < NOW() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN starts_at IS NOT NULL AND starts_at > NOW() AND status = 'active' THEN 1 ELSE 0 END) AS scheduled,
                COALESCE(SUM(current_redemptions), 0) AS total_redemptions
            FROM discounts
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'             => (int)($row['total'] ?? 0),
            'active'            => (int)($row['active'] ?? 0),
            'inactive'          => (int)($row['inactive'] ?? 0),
            'expired'           => (int)($row['expired'] ?? 0),
            'scheduled'         => (int)($row['scheduled'] ?? 0),
            'total_redemptions' => (int)($row['total_redemptions'] ?? 0),
        ];
    }
}