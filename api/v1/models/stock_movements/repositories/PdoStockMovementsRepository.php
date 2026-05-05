<?php
declare(strict_types=1);

/**
 * PDO repository for the product_stock_movements table.
 */
final class PdoStockMovementsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'product_id', 'variant_id', 'type', 'change_quantity', 'created_at'];
    private const ALLOWED_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id', 'variant_id', 'change_quantity', 'type', 'reference_id', 'notes'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    /**
     * @param array{product_id?: int, variant_id?: int, type?: string, date_from?: string, date_to?: string, search?: string} $filters
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
        $sql = "SELECT sm.*, pt.name AS product_name
                FROM product_stock_movements sm
                LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
                WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY sm.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM product_stock_movements sm WHERE 1=1";
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
        // Multi-tenant isolation: always scope by tenant_id when provided
        if (isset($filters['tenant_id']) && (int)$filters['tenant_id'] > 0) {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['entity_id']) && (int)$filters['entity_id'] > 0) {
            $sql .= " AND sm.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $sql .= " AND sm.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (isset($filters['variant_id']) && $filters['variant_id'] !== '') {
            $sql .= " AND sm.variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }

        if (isset($filters['type']) && $filters['type'] !== '') {
            $sql .= " AND sm.type = :type";
            $params[':type'] = $filters['type'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND sm.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND sm.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (EXISTS (
                SELECT 1 FROM product_translations pt2
                WHERE pt2.product_id = sm.product_id AND pt2.name LIKE :search
            ) OR EXISTS (
                SELECT 1 FROM products p2 /* tenant_id filtered via product_id JOIN */
                WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode)
            ))";
            $params[':search']         = '%' . trim($filters['search']) . '%';
            $params[':search_sku']     = '%' . trim($filters['search']) . '%';
            $params[':search_barcode'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id, int $tenantId = 0): ?array
    {
        $sql = "
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.id = :id
        ";
        $params = [':id' => $id];
        if ($tenantId > 0) {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
        $tenantId = (int)($data['tenant_id'] ?? 0);
        $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_stock_movements
                    (tenant_id, entity_id, product_id, variant_id, change_quantity, type, reference_id, notes, created_at)
                VALUES
                    (:tenant_id, :entity_id, :product_id, :variant_id, :change_quantity, :type, :reference_id, :notes, NOW())
            ");

            $stmt->execute([
                ':tenant_id'       => $tenantId,
                ':entity_id'       => $entityId,
                ':product_id'      => (int)$data['product_id'],
                ':variant_id'      => isset($data['variant_id']) ? (int)$data['variant_id'] : null,
                ':change_quantity'  => (int)$data['change_quantity'],
                ':type'            => $data['type'],
                ':reference_id'    => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                ':notes'           => $data['notes'] ?? null,
            ]);

            $movementId = (int)$this->pdo->lastInsertId();

            // Update products.stock_quantity — always scoped by tenant_id
            $updateProduct = $this->pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity + :qty
                WHERE id = :product_id
                  AND tenant_id = :tenant_id
            ");
            $updateProduct->execute([
                ':qty'        => (int)$data['change_quantity'],
                ':product_id' => (int)$data['product_id'],
                ':tenant_id'  => $tenantId,
            ]);

            // Update product_variants.stock_quantity if variant_id provided
            if (!empty($data['variant_id'])) {
                $updateVariant = $this->pdo->prepare("
                    UPDATE product_variants
                    SET stock_quantity = stock_quantity + :qty
                    WHERE id = :variant_id AND product_id = :product_id
                ");
                $updateVariant->execute([
                    ':qty'        => (int)$data['change_quantity'],
                    ':variant_id' => (int)$data['variant_id'],
                    ':product_id' => (int)$data['product_id'],
                ]);
            }

            // Update entity_products.stock_quantity if entity_id provided
            if ($entityId !== null) {
                $updateEntity = $this->pdo->prepare("
                    UPDATE entity_products
                    SET stock_quantity = stock_quantity + :qty
                    WHERE product_id = :product_id
                      AND entity_id  = :entity_id
                      AND tenant_id  = :tenant_id
                ");
                $updateEntity->execute([
                    ':qty'        => (int)$data['change_quantity'],
                    ':product_id' => (int)$data['product_id'],
                    ':entity_id'  => $entityId,
                    ':tenant_id'  => $tenantId,
                ]);
            }

            // Update products.stock_status based on new quantity
            $stmtQty = $this->pdo->prepare("
                SELECT stock_quantity FROM products
                WHERE id = :product_id AND tenant_id = :tenant_id
            ");
            $stmtQty->execute([':product_id' => (int)$data['product_id'], ':tenant_id' => $tenantId]);
            $newQty = (int)$stmtQty->fetchColumn();

            $stockStatus = $newQty > 0 ? 'in_stock' : 'out_of_stock';
            $updateStatus = $this->pdo->prepare("
                UPDATE products SET stock_status = :status
                WHERE id = :product_id AND tenant_id = :tenant_id
            ");
            $updateStatus->execute([
                ':status'     => $stockStatus,
                ':product_id' => (int)$data['product_id'],
                ':tenant_id'  => $tenantId,
            ]);

            $this->pdo->commit();
            return $movementId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    // ================================
    // Update movement and adjust stock
    // ================================
    public function updateMovement(int $id, array $data, array $oldMovement): void
    {
        $tenantId = (int)($oldMovement['tenant_id'] ?? 0);

        // Reverse old stock change on products (scoped by tenant)
        $reverseQty = -1 * (int)$oldMovement['change_quantity'];
        if ($oldMovement['variant_id']) {
            $this->pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                ->execute([':qty' => $reverseQty, ':vid' => $oldMovement['variant_id']]);
        }
        $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid AND tenant_id = :tid")
            ->execute([':qty' => $reverseQty, ':pid' => $oldMovement['product_id'], ':tid' => $tenantId]);

        // Update movement record (keep tenant_id/entity_id from original record)
        $this->pdo->prepare("
            UPDATE product_stock_movements
            SET product_id = :product_id, variant_id = :variant_id, change_quantity = :qty,
                type = :type, reference_id = :ref_id, notes = :notes
            WHERE id = :id AND tenant_id = :tenant_id
        ")->execute([
            ':product_id' => (int)$data['product_id'],
            ':variant_id' => isset($data['variant_id']) ? (int)$data['variant_id'] : null,
            ':qty'         => (int)$data['change_quantity'],
            ':type'        => $data['type'],
            ':ref_id'      => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
            ':notes'       => $data['notes'] ?? null,
            ':id'          => $id,
            ':tenant_id'   => $tenantId,
        ]);

        // Apply new stock change (scoped by tenant)
        $newQty = (int)$data['change_quantity'];
        if (isset($data['variant_id']) && $data['variant_id']) {
            $this->pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                ->execute([':qty' => $newQty, ':vid' => (int)$data['variant_id']]);
        }
        $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid AND tenant_id = :tid")
            ->execute([':qty' => $newQty, ':pid' => (int)$data['product_id'], ':tid' => $tenantId]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        // Find movement to reverse stock changes (load first to get tenant_id)
        $movement = $this->find($id);
        if (!$movement) {
            return false;
        }

        $tenantId = (int)($movement['tenant_id'] ?? 0);
        $entityId = isset($movement['entity_id']) ? (int)$movement['entity_id'] : null;

        $this->pdo->beginTransaction();
        try {
            // Reverse stock quantity on product (scoped by tenant)
            $updateProduct = $this->pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity - :qty
                WHERE id = :product_id AND tenant_id = :tenant_id
            ");
            $updateProduct->execute([
                ':qty'        => (int)$movement['change_quantity'],
                ':product_id' => (int)$movement['product_id'],
                ':tenant_id'  => $tenantId,
            ]);

            // Reverse stock quantity on variant if applicable
            if (!empty($movement['variant_id'])) {
                $updateVariant = $this->pdo->prepare("
                    UPDATE product_variants
                    SET stock_quantity = stock_quantity - :qty
                    WHERE id = :variant_id AND product_id = :product_id
                ");
                $updateVariant->execute([
                    ':qty'        => (int)$movement['change_quantity'],
                    ':variant_id' => (int)$movement['variant_id'],
                    ':product_id' => (int)$movement['product_id'],
                ]);
            }

            // Reverse entity stock quantity if entity_id present
            if ($entityId !== null) {
                $updateEntity = $this->pdo->prepare("
                    UPDATE entity_products
                    SET stock_quantity = stock_quantity - :qty
                    WHERE product_id = :product_id
                      AND entity_id  = :entity_id
                      AND tenant_id  = :tenant_id
                ");
                $updateEntity->execute([
                    ':qty'        => (int)$movement['change_quantity'],
                    ':product_id' => (int)$movement['product_id'],
                    ':entity_id'  => $entityId,
                    ':tenant_id'  => $tenantId,
                ]);
            }

            // Update stock_status based on new quantity
            $stmtQty = $this->pdo->prepare("
                SELECT stock_quantity FROM products
                WHERE id = :product_id AND tenant_id = :tenant_id
            ");
            $stmtQty->execute([':product_id' => (int)$movement['product_id'], ':tenant_id' => $tenantId]);
            $newQty = (int)$stmtQty->fetchColumn();
            $stockStatus = $newQty > 0 ? 'in_stock' : 'out_of_stock';
            $updateStatus = $this->pdo->prepare("
                UPDATE products SET stock_status = :status
                WHERE id = :product_id AND tenant_id = :tenant_id
            ");
            $updateStatus->execute([
                ':status'     => $stockStatus,
                ':product_id' => (int)$movement['product_id'],
                ':tenant_id'  => $tenantId,
            ]);

            // Delete the movement record (scoped by tenant for safety)
            $stmt = $this->pdo->prepare("
                DELETE FROM product_stock_movements
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

            $this->pdo->commit();
            return $result && $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    // ================================
    // Lookup by SKU
    // ================================
    public function lookupBySku(string $sku, string $lang, ?int $entityId = null): ?array
    {
        // Multi-tenant safety: require entity scoping to prevent cross-tenant data leakage
        if ($entityId === null) {
            return null;
        }

        // Pre-fetch tenant_id to avoid correlated subquery per row
        $tenantStmt = $this->pdo->prepare("SELECT tenant_id FROM entities WHERE id = :eid LIMIT 1");
        $tenantStmt->execute([':eid' => $entityId]);
        $tenantId = $tenantStmt->fetchColumn();
        if ($tenantId === false) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku, p.barcode, p.stock_quantity, p.stock_status,
                   pt.name AS product_name, NULL AS variant_id
            FROM products p
            INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
            WHERE p.sku = :sku
              AND p.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':sku' => $sku, ':lang' => $lang, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $stmt2 = $this->pdo->prepare("
            SELECT p.id, pv.sku, pv.barcode, pv.stock_quantity, 'variant' AS stock_status,
                   pt.name AS product_name, pv.id AS variant_id
            FROM product_variants pv
            JOIN products p ON p.id = pv.product_id
              AND p.tenant_id = :tenant_id
            INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
            WHERE pv.sku = :sku
            LIMIT 1
        ");
        $stmt2->execute([':sku' => $sku, ':lang' => $lang, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by ID (with product name)
    // ================================
    public function findWithProductName(int $id, int $tenantId = 0): ?array
    {
        $sql = "
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.id = :id
        ";
        $params = [':id' => $id];
        if ($tenantId > 0) {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // List with filters, count, and pagination (for route)
    // ================================
    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        $tenantId = (int)($filters['tenant_id'] ?? 0);
        $entityId = isset($filters['entity_id']) ? (int)$filters['entity_id'] : 0;

        $params = [
            ':has_tenant'    => $tenantId > 0 ? 1 : 0,
            ':tenant_id'     => $tenantId > 0 ? $tenantId : 0,
            ':has_entity'    => $entityId > 0 ? 1 : 0,
            ':entity_id'     => $entityId > 0 ? $entityId : 0,
            ':type'          => $filters['type'] ?? '',
            ':has_type'      => (isset($filters['type']) && $filters['type'] !== '') ? 1 : 0,
            ':date_from'     => $filters['date_from'] ?? '',
            ':has_date_from' => (isset($filters['date_from']) && $filters['date_from'] !== '') ? 1 : 0,
            ':date_to'       => (isset($filters['date_to']) && $filters['date_to'] !== '') ? $filters['date_to'] . ' 23:59:59' : '',
            ':has_date_to'   => (isset($filters['date_to']) && $filters['date_to'] !== '') ? 1 : 0,
            ':search'        => isset($filters['search']) ? '%' . $filters['search'] . '%' : '',
            ':search_sku'    => isset($filters['search']) ? '%' . $filters['search'] . '%' : '',
            ':search_barcode'=> isset($filters['search']) ? '%' . $filters['search'] . '%' : '',
            ':has_search'    => (isset($filters['search']) && $filters['search'] !== '') ? 1 : 0,
        ];

        $whereClause = "WHERE (:has_tenant = 0 OR sm.tenant_id = :tenant_id)
                          AND (:has_entity = 0 OR sm.entity_id = :entity_id)
                          AND (:has_type = 0 OR sm.type = :type)
                          AND (:has_date_from = 0 OR sm.created_at >= :date_from)
                          AND (:has_date_to = 0 OR sm.created_at <= :date_to)
                          AND (:has_search = 0 OR (
                               EXISTS (SELECT 1 FROM product_translations pt2 WHERE pt2.product_id = sm.product_id AND pt2.name LIKE :search)
                               OR EXISTS (SELECT 1 FROM products p2 WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode))
                          ))";

        $countSql  = "SELECT COUNT(*) FROM product_stock_movements sm " . $whereClause;
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT sm.id, sm.tenant_id, sm.entity_id, sm.product_id, sm.variant_id,
                       sm.change_quantity, sm.type, sm.reference_id, sm.notes, sm.created_at,
                       pt.name AS product_name
                FROM product_stock_movements sm
                LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
                " . $whereClause . "
                ORDER BY sm.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    // ================================
    // Get by Product (with product name)
    // ================================
    public function getByProduct(int $productId, int $tenantId = 0): array
    {
        $sql = "
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.product_id = :product_id
        ";
        $params = [':product_id' => $productId];
        if ($tenantId > 0) {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        $sql .= " ORDER BY sm.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Stats
    // ================================
    /**
     * @return array{total_movements: int, total_restocked: int, total_sold: int, total_returned: int, total_adjusted: int}
     */
    public function stats(array $filters = []): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_movements,
                COALESCE(SUM(CASE WHEN type = 'restock' THEN change_quantity ELSE 0 END), 0) AS total_restocked,
                COALESCE(SUM(CASE WHEN type = 'sale' THEN ABS(change_quantity) ELSE 0 END), 0) AS total_sold,
                COALESCE(SUM(CASE WHEN type = 'return' THEN change_quantity ELSE 0 END), 0) AS total_returned,
                COALESCE(SUM(CASE WHEN type = 'adjustment' THEN change_quantity ELSE 0 END), 0) AS total_adjusted
            FROM product_stock_movements sm
            WHERE 1=1
        ";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_movements' => (int)($row['total_movements'] ?? 0),
            'total_restocked' => (int)($row['total_restocked'] ?? 0),
            'total_sold'      => (int)($row['total_sold'] ?? 0),
            'total_returned'  => (int)($row['total_returned'] ?? 0),
            'total_adjusted'  => (int)($row['total_adjusted'] ?? 0),
        ];
    }

    // ================================
    // Lookup by Barcode
    // ================================
    public function lookupByBarcode(string $barcode, ?int $entityId = null): ?array
    {
        // Multi-tenant safety: require entity scoping to prevent cross-tenant data leakage
        if ($entityId === null) {
            return null;
        }

        // Pre-fetch tenant_id for scoping
        $tenantStmt = $this->pdo->prepare("SELECT tenant_id FROM entities WHERE id = :eid LIMIT 1");
        $tenantStmt->execute([':eid' => $entityId]);
        $tenantId = $tenantStmt->fetchColumn();
        if ($tenantId === false) {
            return null;
        }

        // Search in products table
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku, p.barcode, p.stock_quantity, p.stock_status, p.manage_stock,
                   pt.name AS product_name, NULL AS variant_id
            FROM products p
            INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'en'
            WHERE p.barcode = :barcode
              AND p.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':barcode' => $barcode, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        // Search in product_variants table
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku AS product_sku, p.barcode AS product_barcode,
                   p.stock_quantity AS product_stock_quantity, p.stock_status, p.manage_stock,
                   pt.name AS product_name,
                   pv.id AS variant_id, pv.sku AS variant_sku, pv.barcode AS variant_barcode,
                   pv.stock_quantity AS variant_stock_quantity
            FROM product_variants pv
            JOIN products p ON p.id = pv.product_id
              AND p.tenant_id = :tenant_id
            INNER JOIN entity_products ep ON ep.product_id = p.id AND ep.entity_id = :entity_id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'en'
            WHERE pv.barcode = :barcode
            LIMIT 1
        ");
        $stmt->execute([':barcode' => $barcode, ':entity_id' => $entityId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}