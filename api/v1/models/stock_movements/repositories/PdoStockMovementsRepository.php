<?php
declare(strict_types=1);

/**
 * PDO repository for the product_stock_movements table.
 */
final class PdoStockMovementsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'product_id', 'variant_id', 'type', 'change_quantity', 'created_at'];

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
                SELECT 1 FROM products p2
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
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_stock_movements
                    (product_id, variant_id, change_quantity, type, reference_id, notes, created_at)
                VALUES
                    (:product_id, :variant_id, :change_quantity, :type, :reference_id, :notes, NOW())
            ");

            $stmt->execute([
                ':product_id'      => (int)$data['product_id'],
                ':variant_id'      => isset($data['variant_id']) ? (int)$data['variant_id'] : null,
                ':change_quantity'  => (int)$data['change_quantity'],
                ':type'            => $data['type'],
                ':reference_id'    => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                ':notes'           => $data['notes'] ?? null,
            ]);

            $movementId = (int)$this->pdo->lastInsertId();

            // Update products.stock_quantity
            $updateProduct = $this->pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity + :qty
                WHERE id = :product_id
            ");
            $updateProduct->execute([
                ':qty'        => (int)$data['change_quantity'],
                ':product_id' => (int)$data['product_id'],
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

            // Update products.stock_status based on new quantity
            $stmtQty = $this->pdo->prepare("SELECT stock_quantity FROM products WHERE id = :product_id");
            $stmtQty->execute([':product_id' => (int)$data['product_id']]);
            $newQty = (int)$stmtQty->fetchColumn();

            $stockStatus = $newQty > 0 ? 'in_stock' : 'out_of_stock';
            $updateStatus = $this->pdo->prepare("
                UPDATE products SET stock_status = :status WHERE id = :product_id
            ");
            $updateStatus->execute([
                ':status'     => $stockStatus,
                ':product_id' => (int)$data['product_id'],
            ]);

            $this->pdo->commit();
            return $movementId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Update movement and adjust stock
    // ================================
    public function updateMovement(int $id, array $data, array $oldMovement): void
    {
        // Reverse old stock change
        $reverseQty = -1 * (int)$oldMovement['change_quantity'];
        if ($oldMovement['variant_id']) {
            $this->pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                ->execute([':qty' => $reverseQty, ':vid' => $oldMovement['variant_id']]);
        }
        $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid")
            ->execute([':qty' => $reverseQty, ':pid' => $oldMovement['product_id']]);

        // Update movement record
        $this->pdo->prepare("
            UPDATE product_stock_movements
            SET product_id = :product_id, variant_id = :variant_id, change_quantity = :qty,
                type = :type, reference_id = :ref_id, notes = :notes
            WHERE id = :id
        ")->execute([
            ':product_id' => (int)$data['product_id'],
            ':variant_id' => isset($data['variant_id']) ? (int)$data['variant_id'] : null,
            ':qty' => (int)$data['change_quantity'],
            ':type' => $data['type'],
            ':ref_id' => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
            ':notes' => $data['notes'] ?? null,
            ':id' => $id
        ]);

        // Apply new stock change
        $newQty = (int)$data['change_quantity'];
        if (isset($data['variant_id']) && $data['variant_id']) {
            $this->pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                ->execute([':qty' => $newQty, ':vid' => (int)$data['variant_id']]);
        }
        $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid")
            ->execute([':qty' => $newQty, ':pid' => (int)$data['product_id']]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        // Find movement to reverse stock changes
        $movement = $this->find($id);
        if (!$movement) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            // Reverse stock quantity on product
            $updateProduct = $this->pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity - :qty
                WHERE id = :product_id
            ");
            $updateProduct->execute([
                ':qty'        => (int)$movement['change_quantity'],
                ':product_id' => (int)$movement['product_id'],
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

            // Update stock_status based on new quantity
            $stmtQty = $this->pdo->prepare("SELECT stock_quantity FROM products WHERE id = :product_id");
            $stmtQty->execute([':product_id' => (int)$movement['product_id']]);
            $newQty = (int)$stmtQty->fetchColumn();
            $stockStatus = $newQty > 0 ? 'in_stock' : 'out_of_stock';
            $updateStatus = $this->pdo->prepare("UPDATE products SET stock_status = :status WHERE id = :product_id");
            $updateStatus->execute([':status' => $stockStatus, ':product_id' => (int)$movement['product_id']]);

            // Delete the movement record
            $stmt = $this->pdo->prepare("DELETE FROM product_stock_movements WHERE id = :id");
            $result = $stmt->execute([':id' => $id]);

            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Lookup by SKU
    // ================================
    public function lookupBySku(string $sku, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku, p.barcode, p.stock_quantity, p.stock_status,
                   pt.name AS product_name, NULL AS variant_id
            FROM products p
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
            WHERE p.sku = :sku
            LIMIT 1
        ");
        $stmt->execute([':sku' => $sku, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $stmt2 = $this->pdo->prepare("
            SELECT p.id, pv.sku, pv.barcode, pv.stock_quantity, 'variant' AS stock_status,
                   pt.name AS product_name, pv.id AS variant_id
            FROM product_variants pv
            JOIN products p ON p.id = pv.product_id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
            WHERE pv.sku = :sku
            LIMIT 1
        ");
        $stmt2->execute([':sku' => $sku, ':lang' => $lang]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by ID (with product name)
    // ================================
    public function findWithProductName(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // List with filters, count, and pagination (for route)
    // ================================
    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if (isset($filters['type']) && $filters['type'] !== '') {
            $where[]        = 'sm.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $where[]             = 'sm.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $where[]           = 'sm.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[] = '(EXISTS (
                SELECT 1 FROM product_translations pt2
                WHERE pt2.product_id = sm.product_id AND pt2.name LIKE :search
            ) OR EXISTS (
                SELECT 1 FROM products p2
                WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode)
            ))';
            $params[':search']         = '%' . $filters['search'] . '%';
            $params[':search_sku']     = '%' . $filters['search'] . '%';
            $params[':search_barcode'] = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql  = "SELECT COUNT(*) FROM product_stock_movements sm" . $whereSql;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT sm.*, pt.name AS product_name
                FROM product_stock_movements sm
                LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'"
                . $whereSql . ' ORDER BY sm.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
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
    public function getByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, pt.name AS product_name
            FROM product_stock_movements sm
            LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
            WHERE sm.product_id = :product_id
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([':product_id' => $productId]);
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
    public function lookupByBarcode(string $barcode): ?array
    {
        // Search in products table
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku, p.barcode, p.stock_quantity, p.stock_status, p.manage_stock,
                   pt.name AS product_name, NULL AS variant_id
            FROM products p
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'en'
            WHERE p.barcode = :barcode
            LIMIT 1
        ");
        $stmt->execute([':barcode' => $barcode]);
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
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'en'
            WHERE pv.barcode = :barcode
            LIMIT 1
        ");
        $stmt->execute([':barcode' => $barcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
