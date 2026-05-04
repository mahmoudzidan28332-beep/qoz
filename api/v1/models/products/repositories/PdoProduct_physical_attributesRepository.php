<?php
declare(strict_types=1);

final class PdoProductPhysicalAttributesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id',
        'product_id',
        'variant_id',
        'weight',
        'length',
        'width',
        'height',
        'created_at',
        'updated_at'
    ];

    private const ALLOWED_COLUMNS = [
        'product_id', 'variant_id', 'weight', 'weight_unit',
        'length', 'width', 'height', 'dimension_unit'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // تفعيل وضع الأخطاء
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // =========================================================
    // List + Filters + Pagination
    // =========================================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $tenantId = TenantContext::require();
        $params = [
            ':product_tenant_id' => $tenantId,
            ':variant_tenant_id' => $tenantId,
        ];
        
        $sql = "SELECT ppa.* 
                FROM product_physical_attributes ppa
                LEFT JOIN products p ON ppa.product_id = p.id
                LEFT JOIN product_variants pv ON ppa.variant_id = pv.id
                LEFT JOIN products pv_p ON pv.product_id = pv_p.id
                WHERE (p.tenant_id = :product_tenant_id OR pv_p.tenant_id = :variant_tenant_id)";

        if (!empty($filters['product_id'])) {
            $sql .= " AND ppa.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['variant_id'])) {
            $sql .= " AND ppa.variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }

        if (isset($filters['min_weight']) && $filters['min_weight'] !== '') {
            $sql .= " AND ppa.weight >= :min_weight";
            $params[':min_weight'] = (float)$filters['min_weight'];
        }

        if (isset($filters['max_weight']) && $filters['max_weight'] !== '') {
            $sql .= " AND ppa.weight <= :max_weight";
            $params[':max_weight'] = (float)$filters['max_weight'];
        }

        if (!empty($filters['weight_unit'])) {
            $sql .= " AND ppa.weight_unit = :weight_unit";
            $params[':weight_unit'] = $filters['weight_unit'];
        }

        if (!empty($filters['dimension_unit'])) {
            $sql .= " AND ppa.dimension_unit = :dimension_unit";
            $params[':dimension_unit'] = $filters['dimension_unit'];
        }

        // Ordering
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'created_at';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ppa.{$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // Count
    // =========================================================
    public function count(array $filters = []): int
    {
        $tenantId = TenantContext::require();
        $params = [
            ':product_tenant_id' => $tenantId,
            ':variant_tenant_id' => $tenantId,
        ];

        $sql = "SELECT COUNT(*) 
                FROM product_physical_attributes ppa
                LEFT JOIN products p ON ppa.product_id = p.id
                LEFT JOIN product_variants pv ON ppa.variant_id = pv.id
                LEFT JOIN products pv_p ON pv.product_id = pv_p.id
                WHERE (p.tenant_id = :product_tenant_id OR pv_p.tenant_id = :variant_tenant_id)";

        if (!empty($filters['product_id'])) {
            $sql .= " AND ppa.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['variant_id'])) {
            $sql .= " AND ppa.variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // =========================================================
    // Find (Product OR Variant)
    // =========================================================
    public function findByProduct(int $productId): ?array
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            SELECT ppa.*
            FROM product_physical_attributes ppa
            INNER JOIN products p ON ppa.product_id = p.id
            WHERE ppa.product_id = :product_id AND p.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':product_id' => $productId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByVariant(int $variantId): ?array
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            SELECT ppa.*
            FROM product_physical_attributes ppa
            INNER JOIN product_variants pv ON ppa.variant_id = pv.id
            INNER JOIN products p ON pv.product_id = p.id
            WHERE ppa.variant_id = :variant_id AND p.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':variant_id' => $variantId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================
    // Save (Insert / Update)
    // =========================================================
    public function save(array $data): int
    {
        $tenantId = TenantContext::require();
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
        $isProduct = !empty($data['product_id']);
        $isVariant = !empty($data['variant_id']);

        // التحقق من أن واحد فقط موجود
        if ($isProduct && $isVariant) {
            throw new InvalidArgumentException(
                'Cannot provide both product_id and variant_id.'
            );
        }

        if (!$isProduct && !$isVariant) {
            throw new InvalidArgumentException(
                'Either product_id or variant_id must be provided.'
            );
        }

        if ($isProduct) {
            $productId = (int)$data['product_id'];
            // Verify product belongs to tenant
            $check = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
            $check->execute([$productId, $tenantId]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException("Product not found or access denied.");
            }
            return $this->saveForProduct($productId, $data);
        } else {
            $variantId = (int)$data['variant_id'];
            // Verify variant belongs to tenant via product
            $check = $this->pdo->prepare("
                SELECT pv.id FROM product_variants pv 
                JOIN products p ON pv.product_id = p.id 
                WHERE pv.id = ? AND p.tenant_id = ?
            ");
            $check->execute([$variantId, $tenantId]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException("Variant not found or access denied.");
            }
            return $this->saveForVariant($variantId, $data);
        }
    }

    private function saveForProduct(int $productId, array $data): int
    {
        $existing = $this->findByProduct($productId);

        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE product_physical_attributes
                SET
                    weight = :weight,
                    length = :length,
                    width  = :width,
                    height = :height,
                    weight_unit = :weight_unit,
                    dimension_unit = :dimension_unit,
                    updated_at = CURRENT_TIMESTAMP
                WHERE product_id = :product_id
            ");

            $stmt->execute([
                ':weight' => $data['weight'] ?? null,
                ':length' => $data['length'] ?? null,
                ':width'  => $data['width'] ?? null,
                ':height' => $data['height'] ?? null,
                ':weight_unit' => $data['weight_unit'] ?? 'kg',
                ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
                ':product_id' => $productId,
            ]);

            return (int)$existing['id'];
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO product_physical_attributes
            (
                product_id,
                variant_id,
                weight, length, width, height,
                weight_unit, dimension_unit,
                created_at, updated_at
            )
            VALUES
            (
                :product_id,
                NULL,
                :weight, :length, :width, :height,
                :weight_unit, :dimension_unit,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':product_id' => $productId,
            ':weight' => $data['weight'] ?? null,
            ':length' => $data['length'] ?? null,
            ':width'  => $data['width'] ?? null,
            ':height' => $data['height'] ?? null,
            ':weight_unit' => $data['weight_unit'] ?? 'kg',
            ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function saveForVariant(int $variantId, array $data): int
    {
        $existing = $this->findByVariant($variantId);

        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE product_physical_attributes
                SET
                    weight = :weight,
                    length = :length,
                    width  = :width,
                    height = :height,
                    weight_unit = :weight_unit,
                    dimension_unit = :dimension_unit,
                    updated_at = CURRENT_TIMESTAMP
                WHERE variant_id = :variant_id
            ");

            $stmt->execute([
                ':weight' => $data['weight'] ?? null,
                ':length' => $data['length'] ?? null,
                ':width'  => $data['width'] ?? null,
                ':height' => $data['height'] ?? null,
                ':weight_unit' => $data['weight_unit'] ?? 'kg',
                ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
                ':variant_id' => $variantId,
            ]);

            return (int)$existing['id'];
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO product_physical_attributes
            (
                product_id,
                variant_id,
                weight, length, width, height,
                weight_unit, dimension_unit,
                created_at, updated_at
            )
            VALUES
            (
                NULL,
                :variant_id,
                :weight, :length, :width, :height,
                :weight_unit, :dimension_unit,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':variant_id' => $variantId,
            ':weight' => $data['weight'] ?? null,
            ':length' => $data['length'] ?? null,
            ':width'  => $data['width'] ?? null,
            ':height' => $data['height'] ?? null,
            ':weight_unit' => $data['weight_unit'] ?? 'kg',
            ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // =========================================================
    // Delete
    // =========================================================
    public function deleteByProduct(int $productId): bool
    {
        $tenantId = TenantContext::require();

        // Verify product belongs to tenant
        $check = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
        $check->execute([$productId, $tenantId]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM product_physical_attributes
            WHERE product_id = :product_id
        ");
        return $stmt->execute([':product_id' => $productId]);
    }

    public function deleteByVariant(int $variantId): bool
    {
        $tenantId = TenantContext::require();

        // Verify variant belongs to tenant via product
        $check = $this->pdo->prepare("
            SELECT pv.id FROM product_variants pv 
            JOIN products p ON pv.product_id = p.id 
            WHERE pv.id = ? AND p.tenant_id = ?
        ");
        $check->execute([$variantId, $tenantId]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM product_physical_attributes
            WHERE variant_id = :variant_id
        ");
        return $stmt->execute([':variant_id' => $variantId]);
    }
}
