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
        'tenant_id', 'entity_id', 'entity_product_id', 'entity_product_variant_id',
        'product_id', 'variant_id', 'change_quantity', 'type', 'reference_id', 'notes'
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
                SELECT 1 FROM products p2
                WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode)
                  AND (:sm_tenant_id = 0 OR p2.tenant_id = :sm_tenant_id)
            ))";
            $params[':search']         = '%' . trim($filters['search']) . '%';
            $params[':search_sku']     = '%' . trim($filters['search']) . '%';
            $params[':search_barcode'] = '%' . trim($filters['search']) . '%';
            $params[':sm_tenant_id']   = isset($filters['tenant_id']) ? (int)$filters['tenant_id'] : 0;
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
        $tenantId        = (int)($data['tenant_id'] ?? 0);
        $entityId        = isset($data['entity_id']) ? (int)$data['entity_id'] : null;
        $entityProductId = isset($data['entity_product_id']) && (int)$data['entity_product_id'] > 0
                           ? (int)$data['entity_product_id'] : null;
        $entityVariantId = isset($data['entity_product_variant_id']) && (int)$data['entity_product_variant_id'] > 0
                           ? (int)$data['entity_product_variant_id'] : null;
        $changeQty       = (int)$data['change_quantity'];

        $this->pdo->beginTransaction();
        try {
            if ($entityProductId !== null) {
                // ── ENTITY MODE ──────────────────────────────────────────────────
                // Resolve product_id from entity_products and validate entity ownership
                $epStmt = $this->pdo->prepare("
                    SELECT product_id, entity_id FROM entity_products
                    WHERE id = :epid AND tenant_id = :tid
                    LIMIT 1
                ");
                $epStmt->execute([':epid' => $entityProductId, ':tid' => $tenantId]);
                $ep = $epStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ep) {
                    throw new \RuntimeException('Entity product not found or access denied');
                }
                $productId  = (int)$ep['product_id'];
                $epEntityId = (int)$ep['entity_id'];

                if ($entityId !== null && $entityId !== $epEntityId) {
                    throw new \RuntimeException(
                        "Entity mismatch: entity_product belongs to entity {$epEntityId}, but entity {$entityId} was specified"
                    );
                }
                if ($entityId === null) {
                    $entityId = $epEntityId;
                }

                // Resolve variant_id from entity_product_variants (if provided)
                $variantId = null;
                if ($entityVariantId !== null) {
                    $epvStmt = $this->pdo->prepare("
                        SELECT variant_id FROM entity_product_variants
                        WHERE id = :epvid AND tenant_id = :tid AND product_id = :pid
                        LIMIT 1
                    ");
                    $epvStmt->execute([':epvid' => $entityVariantId, ':tid' => $tenantId, ':pid' => $productId]);
                    $epv = $epvStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$epv) {
                        throw new \RuntimeException('Entity product variant not found or does not belong to this product');
                    }
                    $variantId = (int)$epv['variant_id'];
                }

                // Prevent negative stock
                if ($entityVariantId !== null) {
                    $stockStmt = $this->pdo->prepare("
                        SELECT stock_quantity FROM entity_product_variants
                        WHERE id = :epvid AND tenant_id = :tid
                    ");
                    $stockStmt->execute([':epvid' => $entityVariantId, ':tid' => $tenantId]);
                } else {
                    $stockStmt = $this->pdo->prepare("
                        SELECT stock_quantity FROM entity_products
                        WHERE id = :epid AND tenant_id = :tid
                    ");
                    $stockStmt->execute([':epid' => $entityProductId, ':tid' => $tenantId]);
                }
                $currentStock = (int)$stockStmt->fetchColumn();
                if ($currentStock + $changeQty < 0) {
                    throw new \RuntimeException(
                        'Insufficient stock: current stock is ' . $currentStock .
                        ', cannot reduce by ' . abs($changeQty)
                    );
                }

                // Insert movement record
                $stmt = $this->pdo->prepare("
                    INSERT INTO product_stock_movements
                        (tenant_id, entity_id, entity_product_id, entity_product_variant_id,
                         product_id, variant_id, change_quantity, type, reference_id, notes, created_at)
                    VALUES
                        (:tenant_id, :entity_id, :entity_product_id, :entity_product_variant_id,
                         :product_id, :variant_id, :change_quantity, :type, :reference_id, :notes, NOW())
                ");
                $stmt->execute([
                    ':tenant_id'                 => $tenantId,
                    ':entity_id'                 => $entityId,
                    ':entity_product_id'         => $entityProductId,
                    ':entity_product_variant_id' => $entityVariantId,
                    ':product_id'                => $productId,
                    ':variant_id'                => $variantId,
                    ':change_quantity'            => $changeQty,
                    ':type'                      => $data['type'],
                    ':reference_id'              => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                    ':notes'                     => $data['notes'] ?? null,
                ]);

                $movementId = (int)$this->pdo->lastInsertId();

                // Update entity stock
                if ($entityVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE entity_product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epvid AND tenant_id = :tid
                    ")->execute([':qty' => $changeQty, ':epvid' => $entityVariantId, ':tid' => $tenantId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE entity_products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epid AND tenant_id = :tid
                    ")->execute([':qty' => $changeQty, ':epid' => $entityProductId, ':tid' => $tenantId]);
                }

            } else {
                // ── GLOBAL MODE ──────────────────────────────────────────────────
                // product_id is required; variant_id is optional
                $productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
                $variantId = isset($data['variant_id']) && (int)$data['variant_id'] > 0
                             ? (int)$data['variant_id'] : null;

                if ($productId <= 0) {
                    throw new \RuntimeException("Field 'product_id' is required for global stock updates");
                }

                // Validate product belongs to tenant
                $pStmt = $this->pdo->prepare("
                    SELECT id FROM products WHERE id = :pid AND tenant_id = :tid LIMIT 1
                ");
                $pStmt->execute([':pid' => $productId, ':tid' => $tenantId]);
                if (!$pStmt->fetchColumn()) {
                    throw new \RuntimeException('Product not found or access denied');
                }

                // Validate variant belongs to product (if provided)
                if ($variantId !== null) {
                    $pvStmt = $this->pdo->prepare("
                        SELECT id FROM product_variants WHERE id = :vid AND product_id = :pid LIMIT 1
                    ");
                    $pvStmt->execute([':vid' => $variantId, ':pid' => $productId]);
                    if (!$pvStmt->fetchColumn()) {
                        throw new \RuntimeException('Product variant not found or does not belong to this product');
                    }
                }

                // Prevent negative stock
                if ($variantId !== null) {
                    $stockStmt = $this->pdo->prepare("
                        SELECT stock_quantity FROM product_variants WHERE id = :vid AND product_id = :pid
                    ");
                    $stockStmt->execute([':vid' => $variantId, ':pid' => $productId]);
                } else {
                    $stockStmt = $this->pdo->prepare("
                        SELECT stock_quantity FROM products WHERE id = :pid AND tenant_id = :tid
                    ");
                    $stockStmt->execute([':pid' => $productId, ':tid' => $tenantId]);
                }
                $currentStock = (int)$stockStmt->fetchColumn();
                if ($currentStock + $changeQty < 0) {
                    throw new \RuntimeException(
                        'Insufficient stock: current stock is ' . $currentStock .
                        ', cannot reduce by ' . abs($changeQty)
                    );
                }

                // Insert movement record (entity_product_id and entity_product_variant_id are NULL)
                $stmt = $this->pdo->prepare("
                    INSERT INTO product_stock_movements
                        (tenant_id, entity_id, entity_product_id, entity_product_variant_id,
                         product_id, variant_id, change_quantity, type, reference_id, notes, created_at)
                    VALUES
                        (:tenant_id, :entity_id, NULL, NULL,
                         :product_id, :variant_id, :change_quantity, :type, :reference_id, :notes, NOW())
                ");
                $stmt->execute([
                    ':tenant_id'       => $tenantId,
                    ':entity_id'       => $entityId,
                    ':product_id'      => $productId,
                    ':variant_id'      => $variantId,
                    ':change_quantity' => $changeQty,
                    ':type'            => $data['type'],
                    ':reference_id'    => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                    ':notes'           => $data['notes'] ?? null,
                ]);

                $movementId = (int)$this->pdo->lastInsertId();

                // Update global stock (product_variants OR products)
                if ($variantId !== null) {
                    $this->pdo->prepare("
                        UPDATE product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :vid AND product_id = :pid
                    ")->execute([':qty' => $changeQty, ':vid' => $variantId, ':pid' => $productId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :pid AND tenant_id = :tid
                    ")->execute([':qty' => $changeQty, ':pid' => $productId, ':tid' => $tenantId]);
                }
            }

            $this->pdo->commit();
            return $movementId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        } catch (\RuntimeException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Update movement and adjust stock
    // ================================
    public function updateMovement(int $id, array $data, array $oldMovement): void
    {
        $tenantId           = (int)($oldMovement['tenant_id'] ?? 0);
        $oldEntityProductId = isset($oldMovement['entity_product_id']) && (int)$oldMovement['entity_product_id'] > 0
                              ? (int)$oldMovement['entity_product_id'] : null;
        $oldEntityVariantId = isset($oldMovement['entity_product_variant_id']) && (int)$oldMovement['entity_product_variant_id'] > 0
                              ? (int)$oldMovement['entity_product_variant_id'] : null;
        $oldProductId       = isset($oldMovement['product_id']) && (int)$oldMovement['product_id'] > 0
                              ? (int)$oldMovement['product_id'] : null;
        $oldVariantId       = isset($oldMovement['variant_id']) && (int)$oldMovement['variant_id'] > 0
                              ? (int)$oldMovement['variant_id'] : null;
        $oldQty             = (int)$oldMovement['change_quantity'];

        $this->pdo->beginTransaction();
        try {
            // Reverse old stock change
            if ($oldEntityProductId !== null) {
                // Entity mode reversal
                $reverseQty = -1 * $oldQty;
                if ($oldEntityVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE entity_product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epvid AND tenant_id = :tid
                    ")->execute([':qty' => $reverseQty, ':epvid' => $oldEntityVariantId, ':tid' => $tenantId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE entity_products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epid AND tenant_id = :tid
                    ")->execute([':qty' => $reverseQty, ':epid' => $oldEntityProductId, ':tid' => $tenantId]);
                }
            } elseif ($oldProductId !== null) {
                // Global mode reversal
                $reverseQty = -1 * $oldQty;
                if ($oldVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :vid AND product_id = :pid
                    ")->execute([':qty' => $reverseQty, ':vid' => $oldVariantId, ':pid' => $oldProductId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :pid AND tenant_id = :tid
                    ")->execute([':qty' => $reverseQty, ':pid' => $oldProductId, ':tid' => $tenantId]);
                }
            }

            // Update movement record (keep entity/product ids from original record)
            $newQty = isset($data['change_quantity']) ? (int)$data['change_quantity'] : $oldQty;
            $this->pdo->prepare("
                UPDATE product_stock_movements
                SET change_quantity = :qty,
                    type = :type,
                    reference_id = :ref_id,
                    notes = :notes
                WHERE id = :id AND tenant_id = :tenant_id
            ")->execute([
                ':qty'       => $newQty,
                ':type'      => $data['type'] ?? $oldMovement['type'],
                ':ref_id'    => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                ':notes'     => $data['notes'] ?? null,
                ':id'        => $id,
                ':tenant_id' => $tenantId,
            ]);

            // Apply new stock change (same entity/product ids as original)
            if ($oldEntityProductId !== null) {
                // Entity mode apply
                if ($oldEntityVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE entity_product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epvid AND tenant_id = :tid
                    ")->execute([':qty' => $newQty, ':epvid' => $oldEntityVariantId, ':tid' => $tenantId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE entity_products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :epid AND tenant_id = :tid
                    ")->execute([':qty' => $newQty, ':epid' => $oldEntityProductId, ':tid' => $tenantId]);
                }
            } elseif ($oldProductId !== null) {
                // Global mode apply
                if ($oldVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE product_variants
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :vid AND product_id = :pid
                    ")->execute([':qty' => $newQty, ':vid' => $oldVariantId, ':pid' => $oldProductId]);
                } else {
                    $this->pdo->prepare("
                        UPDATE products
                        SET stock_quantity = stock_quantity + :qty
                        WHERE id = :pid AND tenant_id = :tid
                    ")->execute([':qty' => $newQty, ':pid' => $oldProductId, ':tid' => $tenantId]);
                }
            }

            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException('Failed to update stock movement: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $movement = $this->find($id);
        if (!$movement) {
            return false;
        }

        $tenantId        = (int)($movement['tenant_id'] ?? 0);
        $entityProductId = isset($movement['entity_product_id']) && (int)$movement['entity_product_id'] > 0
                           ? (int)$movement['entity_product_id'] : null;
        $entityVariantId = isset($movement['entity_product_variant_id']) && (int)$movement['entity_product_variant_id'] > 0
                           ? (int)$movement['entity_product_variant_id'] : null;
        $globalProductId = isset($movement['product_id']) && (int)$movement['product_id'] > 0
                           ? (int)$movement['product_id'] : null;
        $globalVariantId = isset($movement['variant_id']) && (int)$movement['variant_id'] > 0
                           ? (int)$movement['variant_id'] : null;

        $this->pdo->beginTransaction();
        try {
            // Reverse entity stock quantity
            if ($entityProductId !== null) {
                if ($entityVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE entity_product_variants
                        SET stock_quantity = stock_quantity - :qty
                        WHERE id = :epvid AND tenant_id = :tid
                    ")->execute([
                        ':qty'   => (int)$movement['change_quantity'],
                        ':epvid' => $entityVariantId,
                        ':tid'   => $tenantId,
                    ]);
                } else {
                    $this->pdo->prepare("
                        UPDATE entity_products
                        SET stock_quantity = stock_quantity - :qty
                        WHERE id = :epid AND tenant_id = :tid
                    ")->execute([
                        ':qty'  => (int)$movement['change_quantity'],
                        ':epid' => $entityProductId,
                        ':tid'  => $tenantId,
                    ]);
                }
            } elseif ($globalProductId !== null) {
                // Reverse global stock quantity
                if ($globalVariantId !== null) {
                    $this->pdo->prepare("
                        UPDATE product_variants
                        SET stock_quantity = stock_quantity - :qty
                        WHERE id = :vid AND product_id = :pid
                    ")->execute([
                        ':qty' => (int)$movement['change_quantity'],
                        ':vid' => $globalVariantId,
                        ':pid' => $globalProductId,
                    ]);
                } else {
                    $this->pdo->prepare("
                        UPDATE products
                        SET stock_quantity = stock_quantity - :qty
                        WHERE id = :pid AND tenant_id = :tid
                    ")->execute([
                        ':qty' => (int)$movement['change_quantity'],
                        ':pid' => $globalProductId,
                        ':tid' => $tenantId,
                    ]);
                }
            }

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
                               OR EXISTS (SELECT 1 FROM products p2 WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode) AND (:has_tenant = 0 OR p2.tenant_id = :tenant_id))
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