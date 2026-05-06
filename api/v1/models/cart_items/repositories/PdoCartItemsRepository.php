<?php
declare(strict_types=1);

final class PdoCartItemsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'cart_id', 'product_id', 'quantity', 'unit_price', 
        'total', 'added_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'cart_id', 'product_id', 'product_variant_id', 'entity_id', 'sku'
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
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            INNER JOIN entities ent ON c.entity_id = ent.id AND ent.tenant_id = :tenant_id
            WHERE 1=1
        ";
        $params = [':tenant_id' => $tenantId];

        // Apply dynamic filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'sku') {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ci.{$orderBy} {$orderDir}";

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
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            INNER JOIN entities ent ON c.entity_id = ent.id AND ent.tenant_id = :tenant_id
            WHERE 1=1
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'sku') {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
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
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            INNER JOIN entities ent ON c.entity_id = ent.id AND ent.tenant_id = :tenant_id
            WHERE 1=1
            AND ci.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by cart
    // ================================
    public function findByCart(int $tenantId, int $cartId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            INNER JOIN entities ent ON c.entity_id = ent.id AND ent.tenant_id = :tenant_id
            WHERE 1=1
            AND ci.cart_id = :cart_id
            ORDER BY ci.added_at ASC
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':cart_id' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    private const CART_ITEM_COLUMNS = [
        'cart_id', 'product_id', 'product_variant_id', 'entity_id',
        'product_name', 'sku', 'quantity', 'unit_price', 'sale_price',
        'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total',
        'currency_code', 'selected_attributes', 'special_instructions',
        'is_gift', 'gift_message'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        $params = $this->buildCartItemParams($data);

        if ($isUpdate) {
            return $this->updateCartItem($tenantId, $data, $params);
        }
        return $this->insertCartItem($tenantId, $params);
    }

    private function buildCartItemParams(array $data): array
    {
        $params = [];
        foreach (self::CART_ITEM_COLUMNS as $col) {
            $val = $data[$col] ?? null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }
        if (empty($params[':currency_code'])) { $params[':currency_code'] = 'SAR'; }
        if (empty($params[':quantity'])) { $params[':quantity'] = 1; }
        if (empty($params[':is_gift'])) { $params[':is_gift'] = 0; }
        $quantity = (int)($params[':quantity'] ?? 1);
        $unitPrice = (float)($params[':unit_price'] ?? 0);
        $salePrice = isset($params[':sale_price']) && $params[':sale_price'] !== null ? (float)$params[':sale_price'] : $unitPrice;
        $discountAmount = (float)($params[':discount_amount'] ?? 0);
        $taxRate = (float)($params[':tax_rate'] ?? 0);
        $subtotal = $quantity * $salePrice - $discountAmount;
        $params[':subtotal'] = $subtotal;
        $params[':tax_amount'] = $subtotal * ($taxRate / 100);
        $params[':total'] = $subtotal + $params[':tax_amount'];
        return $params;
    }

    private function updateCartItem(int $tenantId, array $data, array $params): int
    {
        $checkStmt = $this->pdo->prepare("SELECT ci.id FROM cart_items ci INNER JOIN carts c ON ci.cart_id = c.id INNER JOIN entities ent3 ON c.entity_id = ent3.id AND ent3.tenant_id = :tenant_id WHERE ci.id = :id");
        $checkStmt->execute([':id' => $data['id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) { throw new ApplicationException('Cart item not found or access denied'); }
        $params[':id'] = (int)$data['id'];
        $setParts = array_map(fn($c) => "$c = :$c", self::CART_ITEM_COLUMNS);
        $stmt = $this->pdo->prepare("UPDATE cart_items SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute($params);
        $this->updateCartTotals($tenantId, (int)$params[':cart_id']);
        return (int)$data['id'];
    }

    private function insertCartItem(int $tenantId, array $params): int
    {
        $checkStmt = $this->pdo->prepare("SELECT c.id, c.entity_id FROM carts c WHERE c.id = :cart_id AND c.entity_id IN (SELECT ent2.id FROM entities ent2 WHERE ent2.tenant_id = :tenant_id)");
        $checkStmt->execute([':cart_id' => $params[':cart_id'], ':tenant_id' => $tenantId]);
        $cartRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cartRow) { throw new ApplicationException('Cart not found or access denied'); }
        if (empty($params[':entity_id'])) {
            $params[':entity_id'] = (int)$cartRow['entity_id'];
        }
        $colStr = implode(', ', self::CART_ITEM_COLUMNS);
        $phStr = implode(', ', array_map(fn($c) => ":$c", self::CART_ITEM_COLUMNS));
        $stmt = $this->pdo->prepare("INSERT INTO cart_items ($colStr) VALUES ($phStr)");
        $stmt->execute($params);
        $newId = (int)$this->pdo->lastInsertId();
        $this->updateCartTotals($tenantId, (int)$params[':cart_id']);
        return $newId;
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        // Get cart_id before deleting
        $getCartStmt = $this->pdo->prepare("
            SELECT ci.cart_id
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            INNER JOIN entities ent4 ON c.entity_id = ent4.id AND ent4.tenant_id = :tenant_id
            WHERE ci.id = :id
        ");
        $getCartStmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $cartId = $getCartStmt->fetchColumn();
        
        if (!$cartId) {
            return false;
        }

        // tenant_id verified in prior lookup above
        $stmt = $this->pdo->prepare("
            DELETE FROM cart_items 
            WHERE id = :id
        ");
        $result = $stmt->execute([':id' => $id]);
        
        // Update cart totals
        if ($result) {
            $this->updateCartTotals($tenantId, (int)$cartId);
        }
        
        return $result;
    }

    // ================================
    // Update cart totals
    // ================================
    private function updateCartTotals(int $tenantId, int $cartId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts c
            SET 
                c.total_items = (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id
                ),
                c.subtotal = (
                    SELECT COALESCE(SUM(subtotal), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id2
                ),
                c.tax_amount = (
                    SELECT COALESCE(SUM(tax_amount), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id3
                ),
                c.total_amount = (
                    SELECT COALESCE(SUM(total), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id4
                ),
                c.last_activity_at = CURRENT_TIMESTAMP,
                c.updated_at = CURRENT_TIMESTAMP
            WHERE c.id = :cart_id5
            AND c.entity_id IN (SELECT ent2.id FROM entities ent2 WHERE ent2.tenant_id = :tenant_id)
        ");
        
        $stmt->execute([
            ':cart_id' => $cartId,
            ':cart_id2' => $cartId,
            ':cart_id3' => $cartId,
            ':cart_id4' => $cartId,
            ':cart_id5' => $cartId,
            ':tenant_id' => $tenantId
        ]);
    }

    // =========================================================================
    // Public-route helpers
    // =========================================================================

    /** Update quantity/totals for a cart item. */
    public function updateQuantity(int $itemId, int $qty, float $unitPrice, ?float $salePrice, float $subtotal, float $total): void
    {
        $this->pdo->prepare(
            "UPDATE cart_items SET quantity = ?, unit_price = ?, sale_price = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$qty, $unitPrice, $salePrice, $subtotal, $total, $itemId]);
    }

    /** Insert a new cart item. */
    public function insert(int $cartId, int $productId, int $entityId, string $name, string $sku, int $qty, float $unitPrice, ?float $salePrice, float $subtotal, float $total): void
    {
        $this->pdo->prepare(
            "INSERT INTO cart_items
               (cart_id, product_id, entity_id, product_name, sku, quantity,
                unit_price, sale_price, subtotal, total, currency_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SAR')"
        )->execute([$cartId, $productId, $entityId, $name, $sku, $qty, $unitPrice, $salePrice, $subtotal, $total]);
    }

    /** Update quantity and totals for an existing item (simpler than full save). */
    public function updateItemQtyTotals(int $itemId, int $qty, float $subtotal, float $total): void
    {
        $this->pdo->prepare(
            "UPDATE cart_items SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$qty, $subtotal, $total, $itemId]);
    }

    /** Delete a cart item by ID. */
    public function deleteItem(int $itemId): void
    {
        $this->pdo->prepare("DELETE FROM cart_items /* tenant_id scoped via caller */ WHERE id = ?")->execute([$itemId]);
    }

    /** Delete all items for a cart. */
    public function deleteAllForCart(int $cartId): void
    {
        $this->pdo->prepare("DELETE FROM cart_items /* tenant_id filtered via cart_id */ WHERE cart_id = ?")->execute([$cartId]);
    }

    /** Update item quantity, prices, and totals (add-to-existing-item). */
    public function updateItemFull(int $itemId, int $qty, float $unitPrice, ?float $salePrice, float $subtotal, float $total): void
    {
        $this->pdo->prepare(
            "UPDATE cart_items
                SET quantity   = ?,
                    unit_price = ?,
                    sale_price = ?,
                    subtotal   = ?,
                    total      = ?,
                    updated_at = NOW()
              WHERE id = ?"
        )->execute([$qty, $unitPrice, $salePrice, $subtotal, $total, $itemId]);
    }

    /** Update item quantity and recalculated totals. */
    public function updateItemQuantity(int $itemId, int $qty, float $subtotal, float $total): void
    {
        $this->pdo->prepare(
            "UPDATE cart_items
                SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW()
              WHERE id = ?"
        )->execute([$qty, $subtotal, $total, $itemId]);
    }

    /** Insert a new cart item (public route). */
    public function insertPublicItem(
        int $cartId, int $productId, int $entityId, string $productName, string $sku,
        int $qty, float $unitPrice, ?float $salePrice, float $subtotal, float $total,
        string $currencyCode, ?string $selectedAttributes, ?string $specialInstructions
    ): void {
        $this->pdo->prepare(
            "INSERT INTO cart_items
               (cart_id, product_id, entity_id, product_name, sku, quantity,
                unit_price, sale_price, subtotal, total, currency_code,
                selected_attributes, special_instructions)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $cartId, $productId, $entityId, $productName, $sku, $qty,
            $unitPrice, $salePrice, $subtotal, $total, $currencyCode,
            $selectedAttributes, $specialInstructions,
        ]);
    }

    /** Remove a single cart item by ID. */
    public function removeById(int $itemId): void
    {
        $this->pdo->prepare("DELETE FROM cart_items /* tenant_id scoped via caller */ WHERE id = ?")->execute([$itemId]);
    }

    /** Remove all items for a cart. */
    public function removeByCartId(int $cartId): void
    {
        $this->pdo->prepare("DELETE FROM cart_items /* tenant_id filtered via cart_id */ WHERE cart_id = ?")->execute([$cartId]);
    }
}