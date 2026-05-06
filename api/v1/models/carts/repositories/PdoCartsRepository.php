<?php
declare(strict_types=1);

final class PdoCartsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'user_id', 'session_id', 'total_items', 'subtotal', 
        'total_amount', 'status', 'last_activity_at', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'user_id', 'session_id', 'device_id', 'status', 'entity_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
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
            SELECT c.*,
                   COALESCE(et.store_name, e.store_name) AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            LEFT JOIN entity_translations et ON e.id = et.entity_id AND et.language_code = :lang
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // Apply dynamic filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id', 'device_id'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.{$orderBy} {$orderDir}";

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
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM carts c
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id', 'device_id'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
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
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   COALESCE(et.store_name, e.store_name) AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            LEFT JOIN entity_translations et ON e.id = et.entity_id AND et.language_code = :lang
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by session
    // ================================
    public function findBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM carts c
            WHERE c.entity_id = :entity_id
            AND c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND c.session_id = :session_id
            AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':entity_id' => $entityId,
            ':session_id' => $sessionId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by user
    // ================================
    public function findByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM carts c
            WHERE c.entity_id = :entity_id
            AND c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND c.user_id = :user_id
            AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':entity_id' => $entityId,
            ':user_id' => $userId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================
    private const CART_COLUMNS = [
        'entity_id', 'user_id', 'session_id', 'device_id', 'ip_address',
        'total_items', 'subtotal', 'tax_amount', 'shipping_cost', 
        'discount_amount', 'total_amount', 'currency_code', 'coupon_code',
        'discount_id', 'loyalty_points_used', 'status',
        'last_activity_at', 'converted_to_order_id', 'expires_at'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        $params = $this->buildCartParams($data);

        if ($isUpdate) {
            return $this->updateCart($tenantId, $data, $params);
        }
        return $this->insertCart($tenantId, $params);
    }

    private function buildCartParams(array $data): array
    {
        $params = [];
        foreach (self::CART_COLUMNS as $col) {
            $val = $data[$col] ?? null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }
        if (empty($params[':currency_code'])) { $params[':currency_code'] = 'SAR'; }
        if (empty($params[':status'])) { $params[':status'] = 'active'; }
        if (empty($params[':total_items'])) { $params[':total_items'] = 0; }
        $params[':last_activity_at'] = date('Y-m-d H:i:s');
        return $params;
    }

    private function updateCart(int $tenantId, array $data, array $params): int
    {
        $checkStmt = $this->pdo->prepare("SELECT id FROM carts WHERE id = :id AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)");
        $checkStmt->execute([':id' => $data['id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) { throw new ApplicationException('Cart not found or access denied'); }
        $params[':id'] = (int)$data['id'];
        $setParts = array_map(fn($c) => "$c = :$c", self::CART_COLUMNS);
        $stmt = $this->pdo->prepare("UPDATE carts SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute($params);
        return (int)$data['id'];
    }

    private function insertCart(int $tenantId, array $params): int
    {
        $checkStmt = $this->pdo->prepare("SELECT id FROM entities WHERE id = :entity_id AND tenant_id = :tenant_id");
        $checkStmt->execute([':entity_id' => $params[':entity_id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) { throw new ApplicationException('Entity not found or access denied'); }
        $colStr = implode(', ', self::CART_COLUMNS);
        $phStr = implode(', ', array_map(fn($c) => ":$c", self::CART_COLUMNS));
        $stmt = $this->pdo->prepare("INSERT INTO carts ($colStr) VALUES ($phStr)");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete (soft delete - mark as expired)
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts 
            SET status = 'expired', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id 
            AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    public function convertToOrderById(int $cartId, int $orderId): void
    {
        $this->pdo->prepare(
            "UPDATE carts SET status = 'converted', converted_to_order_id = ?, updated_at = NOW()
               WHERE id = ?"
        )->execute([$orderId, $cartId]);
    }

    // ================================
    // Convert to order
    // ================================
    public function convertToOrder(int $tenantId, int $cartId, int $orderId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts 
            SET status = 'converted', 
                converted_to_order_id = :order_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :cart_id 
            AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':cart_id' => $cartId,
            ':order_id' => $orderId
        ]);
    }

    // =========================================================================
    // Public-route helpers
    // =========================================================================

    /** Find active cart for a user + entity. */
    public function findActiveForUser(int $userId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id
               FROM carts
              WHERE user_id = ?
                AND entity_id = ?
                AND status = 'active'
              ORDER BY id DESC
              LIMIT 1"
        );
        $stmt->execute([$userId, $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Create a new active cart and return its ID. */
    public function createActive(int $entityId, int $userId, ?string $sessionId, ?string $ipAddress): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO carts (entity_id, user_id, session_id, status, ip_address)
             VALUES (?, ?, ?, 'active', ?)"
        );
        $stmt->execute([$entityId, $userId, $sessionId, $ipAddress]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Refresh cart totals from cart_items. */
    public function refreshTotals(int $cartId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT SUM(quantity) AS ti, SUM(total) AS tot FROM cart_items /* tenant_id filtered via cart_id */ WHERE cart_id = ?"
        );
        $stmt->execute([$cartId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->pdo->prepare(
            "UPDATE carts SET total_items = ?, subtotal = ?, total_amount = ?, last_activity_at = NOW() WHERE id = ?"
        )->execute([(int)($row['ti'] ?? 0), (float)($row['tot'] ?? 0), (float)($row['tot'] ?? 0), $cartId]);
    }

    /** Clear cart: zero out totals. */
    public function clearTotals(int $cartId): void
    {
        $this->pdo->prepare(
            "UPDATE carts SET total_items = 0, subtotal = 0, total_amount = 0, last_activity_at = NOW() WHERE id = ?"
        )->execute([$cartId]);
    }

    /** Get or create an active cart (public route). Returns cart_id. */
    public function getOrCreateActiveCart(int $userId, int $entityId, ?string $sessionId, ?string $ipAddress): int
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM carts
              WHERE user_id = ? AND entity_id = ? AND status = 'active'
              ORDER BY last_activity_at DESC LIMIT 1"
        );
        $st->execute([$userId, $entityId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];

        $ins = $this->pdo->prepare(
            "INSERT INTO carts
               (entity_id, user_id, session_id, status, ip_address, last_activity_at)
             VALUES (?, ?, ?, 'active', ?, NOW())"
        );
        $ins->execute([$entityId, $userId, $sessionId, $ipAddress]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Recalculate cart totals including currency (public route). */
    public function refreshTotalsWithCurrency(int $cartId): void
    {
        $st = $this->pdo->prepare(
            "SELECT
               COALESCE(SUM(quantity), 0)  AS ti,
               COALESCE(SUM(subtotal), 0)  AS sub,
               COALESCE(SUM(total), 0)     AS tot,
               MAX(currency_code)          AS cur
             FROM cart_items /* tenant_id filtered via cart_id */ WHERE cart_id = ?"
        );
        $st->execute([$cartId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $cur = !empty($r['cur']) ? $r['cur'] : 'SAR';

        $this->pdo->prepare(
            "UPDATE carts
                SET total_items    = ?,
                    subtotal       = ?,
                    total_amount   = ?,
                    currency_code  = ?,
                    last_activity_at = NOW()
              WHERE id = ?"
        )->execute([(int)$r['ti'], (float)$r['sub'], (float)$r['tot'], $cur, $cartId]);
    }
}