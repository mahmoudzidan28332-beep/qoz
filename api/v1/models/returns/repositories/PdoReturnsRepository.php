<?php
declare(strict_types=1);

final class PdoReturnsRepository implements ReturnsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'returns';
    private const ALLOWED_ORDER_BY = [
        'id', 'return_number', 'status', 'order_id', 'user_id',
        'requested_at', 'processed_at', 'created_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'status', 'order_id', 'user_id', 'entity_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT r.*,
                   o.order_number,
                   u.email           AS user_email,
                   e.store_name      AS entity_name,
                   tn.name           AS tenant_name
            FROM " . self::TABLE . " r
            LEFT JOIN orders o    ON o.id    = r.order_id
            LEFT JOIN users u     ON u.id    = r.user_id
            LEFT JOIN entities e  ON e.id    = r.entity_id
            LEFT JOIN tenants tn  ON tn.id   = r.tenant_id
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND r.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.return_number LIKE :search OR r.reason LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY r.{$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   o.order_number,
                   u.email           AS user_email,
                   e.store_name      AS entity_name,
                   tn.name           AS tenant_name
            FROM " . self::TABLE . " r
            LEFT JOIN orders o    ON o.id    = r.order_id
            LEFT JOIN users u     ON u.id    = r.user_id
            LEFT JOIN entities e  ON e.id    = r.entity_id
            LEFT JOIN tenants tn  ON tn.id   = r.tenant_id
            WHERE r.tenant_id = :tenant_id AND r.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];

            // Verify the record exists and belongs to this tenant
            try {
                $existStmt = $this->pdo->prepare(
                    "SELECT id FROM " . self::TABLE . " WHERE tenant_id = :tenant_id AND id = :id LIMIT 1"
                );
                $existStmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
                if (!$existStmt->fetchColumn()) {
                    throw new \RuntimeException('Return not found or access denied', 404);
                }
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
            }

            // Build a partial SET clause.
            // FK-referencing columns (order_id, user_id, entity_id) are intentionally
            // excluded from the UPDATE unless the caller explicitly provides them.
            // MySQL re-validates FK constraints on every UPDATE row, so including an
            // unchanged-but-orphaned FK value would raise SQLSTATE 23000.
            $setClauses = [
                'status       = :status',
                'reason       = :reason',
                'admin_notes  = :admin_notes',
                'requested_at = :requested_at',
                'processed_at = :processed_at',
            ];
            $params = [
                ':tenant_id'    => $tenantId,
                ':id'           => $id,
                ':status'       => $data['status'] ?? 'pending',
                ':reason'       => $data['reason'] ?? null,
                ':admin_notes'  => $data['admin_notes'] ?? null,
                ':requested_at' => $data['requested_at'] ?? date('Y-m-d H:i:s'),
                ':processed_at' => $data['processed_at'] ?? null,
            ];

            // Only update return_number when explicitly supplied (avoid overwriting with an
            // auto-generated value on every status edit)
            if (isset($data['return_number']) && (string)$data['return_number'] !== '') {
                $setClauses[]          = 'return_number = :return_number';
                $params[':return_number'] = $data['return_number'];
            }

            // Only update FK columns when explicitly supplied
            foreach (['order_id', 'user_id', 'entity_id'] as $col) {
                if (isset($data[$col]) && $data[$col] !== '' && $data[$col] !== null) {
                    $setClauses[]       = "{$col} = :{$col}";
                    $params[":{$col}"] = (int)$data[$col];
                }
            }

            try {
                $sql  = "UPDATE " . self::TABLE . " SET " . implode(', ', $setClauses) .
                        " WHERE tenant_id = :tenant_id AND id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
            }

            return $id;
        }

        // INSERT case — auto-generate return number when not provided
        if (empty($data['return_number'])) {
            $data['return_number'] = 'RET-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO " . self::TABLE . " (
                    tenant_id, order_id, user_id, entity_id, return_number,
                    status, reason, admin_notes, requested_at, processed_at
                ) VALUES (
                    :tenant_id, :order_id, :user_id, :entity_id, :return_number,
                    :status, :reason, :admin_notes, :requested_at, :processed_at
                )
            ");
            $stmt->execute($this->buildParams($tenantId, $data, false));
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE tenant_id = :tenant_id AND id = :id"
        );
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    public function createPublicReturn(int $tenantId, int $userId, int $orderId, string $returnNumber, string $reason): int
    {
        $this->pdo->prepare(
            "INSERT INTO returns
               (tenant_id, user_id, order_id, return_number, reason, status, requested_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW(), NOW())"
        )->execute([$tenantId, $userId, $orderId, $returnNumber, $reason]);
        return (int)$this->pdo->lastInsertId();
    }

    private function buildParams(int $tenantId, array $data, bool $isUpdate): array
    {
        $params = [
            ':tenant_id'      => $tenantId,
            ':order_id'       => isset($data['order_id']) ? (int)$data['order_id'] : null,
            ':user_id'        => isset($data['user_id']) ? (int)$data['user_id'] : null,
            ':entity_id'      => isset($data['entity_id']) ? (int)$data['entity_id'] : null,
            ':return_number'  => $data['return_number'],
            ':status'         => $data['status'] ?? 'pending',
            ':reason'         => $data['reason'] ?? null,
            ':admin_notes'    => $data['admin_notes'] ?? null,
            ':requested_at'   => $data['requested_at'] ?? date('Y-m-d H:i:s'),
            ':processed_at'   => $data['processed_at'] ?? null,
        ];
        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
        }
        return $params;
    }
}