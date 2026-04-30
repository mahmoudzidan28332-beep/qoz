<?php
declare(strict_types=1);

final class PdoProductPricingRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','product_id','variant_id','price','tax_rate','cost_price',
        'compare_at_price','currency_code','pricing_type',
        'start_at','end_at','country_id','city_id',
        'is_active','created_at','updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'product_id','variant_id','entity_id','currency_code','pricing_type',
        'country_id','city_id','is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $tenantId = TenantContext::require();

        $sql = "
            SELECT pp.*
            FROM product_pricing pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE p.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pp.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY pp.$orderBy $orderDir";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $tenantId = TenantContext::require();

        $sql = "
            SELECT COUNT(*)
            FROM product_pricing pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE p.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pp.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find
    // ================================
    public function find(int $id): ?array
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            SELECT pp.*
            FROM product_pricing pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE p.tenant_id = :tenant_id AND pp.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Save (Create / Update)
    // ================================
    public function save(array $data): int
    {
        $tenantId = TenantContext::require();
        $isUpdate = !empty($data['id']);

        // Extract only valid pricing columns to prevent SQLSTATE[HY093]
        $params = [
            ':product_id'       => $data['product_id'] ?? null,
            ':variant_id'       => $data['variant_id'] ?? null,
            ':entity_id'        => $data['entity_id'] ?? null,
            ':price'            => $data['price'] ?? 0,
            ':tax_rate'         => $data['tax_rate'] ?? null,
            ':cost_price'       => $data['cost_price'] ?? null,
            ':compare_at_price' => $data['compare_at_price'] ?? null,
            ':currency_code'    => $data['currency_code'] ?? 'SAR',
            ':pricing_type'     => $data['pricing_type'] ?? 'fixed',
            ':start_at'         => $data['start_at'] ?? null,
            ':end_at'           => $data['end_at'] ?? null,
            ':country_id'       => $data['country_id'] ?? null,
            ':city_id'          => $data['city_id'] ?? null,
            ':is_active'        => $data['is_active'] ?? 1,
        ];

        // Security check: Verify product belongs to tenant
        $productId = (int)$params[':product_id'];
        $checkStmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
        $checkStmt->execute([$productId, $tenantId]);
        if (!$checkStmt->fetch()) {
            throw new \InvalidArgumentException('Product not found or access denied.');
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;

            $stmt = $this->pdo->prepare("
                UPDATE product_pricing SET
                    product_id = :product_id,
                    variant_id = :variant_id,
                    entity_id = :entity_id,
                    price = :price,
                    tax_rate = :tax_rate,
                    cost_price = :cost_price,
                    compare_at_price = :compare_at_price,
                    currency_code = :currency_code,
                    pricing_type = :pricing_type,
                    start_at = :start_at,
                    end_at = :end_at,
                    country_id = :country_id,
                    city_id = :city_id,
                    is_active = :is_active,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND EXISTS (SELECT 1 FROM products WHERE id = :product_id AND tenant_id = :tenant_id)
            ");

            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                // Either ID doesn't exist or tenant ownership check failed
                $check = $this->find((int)$data['id']);
                if (!$check) {
                    throw new ApplicationException('Pricing record not found or access denied.');
                }
            }
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_pricing (
                product_id, variant_id, entity_id, price, tax_rate,
                cost_price, compare_at_price, currency_code,
                pricing_type, start_at, end_at,
                country_id, city_id, is_active
            ) VALUES (
                :product_id, :variant_id, :entity_id, :price, :tax_rate,
                :cost_price, :compare_at_price, :currency_code,
                :pricing_type, :start_at, :end_at,
                :country_id, :city_id, :is_active
            )
        ");

        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            DELETE pp
            FROM product_pricing pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE p.tenant_id = :tenant_id AND pp.id = :id
        ");

        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id
        ]);
    }
}