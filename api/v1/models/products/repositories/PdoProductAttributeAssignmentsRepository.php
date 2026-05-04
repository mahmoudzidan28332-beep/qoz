<?php
declare(strict_types=1);

// api/v1/models/product_attribute_assignments/repositories/PdoProductAttributeAssignmentsRepository.php

final class PdoProductAttributeAssignmentsRepository
{
    private PDO $pdo;

    private const ALLOWED_COLUMNS = [
        'product_id', 'attribute_id', 'attribute_value_id', 'custom_value'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(?int $productId = null, ?int $attributeId = null): array
    {
        $tenantId = TenantContext::require();

        $sql = "
            SELECT paa.id, paa.product_id, paa.attribute_id, paa.attribute_value_id, paa.custom_value, paa.created_at, paa.updated_at,
                   pa.slug AS attribute_slug, pav.slug AS attribute_value_slug
            FROM product_attribute_assignments paa
            INNER JOIN products p ON paa.product_id = p.id
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE p.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        if ($productId) {
            $sql .= " AND paa.product_id = :productId";
            $params[':productId'] = $productId;
        }

        if ($attributeId) {
            $sql .= " AND paa.attribute_id = :attributeId";
            $params[':attributeId'] = $attributeId;
        }

        $sql .= " ORDER BY paa.product_id ASC, paa.attribute_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            SELECT paa.*, pa.slug AS attribute_slug, pav.slug AS attribute_value_slug
            FROM product_attribute_assignments paa
            INNER JOIN products p ON paa.product_id = p.id
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE paa.id = :id AND p.tenant_id = :tenant_id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByProduct(int $productId): array
    {
        $tenantId = TenantContext::require();

        $stmt = $this->pdo->prepare("
            SELECT paa.id, paa.attribute_id, paa.attribute_value_id, paa.custom_value,
                   pa.slug AS attribute_slug, pav.slug AS attribute_value_slug, pav.value AS attribute_value
            FROM product_attribute_assignments paa
            INNER JOIN products p ON paa.product_id = p.id
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE paa.product_id = :productId AND p.tenant_id = :tenant_id
            ORDER BY pa.sort_order ASC
        ");

        $stmt->execute([':productId' => $productId, ':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data, ?int $userId = null): int
    {
        $tenantId = TenantContext::require();
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS)) + (isset($data['id']) ? ['id' => $data['id']] : []);
        $isUpdate = !empty($data['id']);

        // Verify product belongs to tenant
        $productId = (int)$data['product_id'];
        if ($tenantId > 0) {
            $checkStmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
            $checkStmt->execute([$productId, $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new InvalidArgumentException("Product not found or access denied.");
            }
        }

        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $id = (int)$data['id'];
            
            // Security check: Verify assignment belongs to tenant
            if ($tenantId > 0) {
                $assignCheck = $this->pdo->prepare("
                    SELECT paa.id FROM product_attribute_assignments paa 
                    JOIN products p ON paa.product_id = p.id 
                    WHERE paa.id = ? AND p.tenant_id = ?
                ");
                $assignCheck->execute([$id, $tenantId]);
                if (!$assignCheck->fetch()) {
                    throw new InvalidArgumentException("Assignment record not found or access denied.");
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE product_attribute_assignments
                SET product_id = :product_id,
                    attribute_id = :attribute_id,
                    attribute_value_id = :attribute_value_id,
                    custom_value = :custom_value,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':product_id'        => $productId,
                ':attribute_id'      => (int)$data['attribute_id'],
                ':attribute_value_id' => $data['attribute_value_id'] ? (int)$data['attribute_value_id'] : null,
                ':custom_value'      => $data['custom_value'] ?? null,
                ':id'                => $id
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attribute_assignments
                    (product_id, attribute_id, attribute_value_id, custom_value, created_at)
                VALUES
                    (:product_id, :attribute_id, :attribute_value_id, :custom_value, NOW())
            ");

            $stmt->execute([
                ':product_id'        => $productId,
                ':attribute_id'      => (int)$data['attribute_id'],
                ':attribute_value_id' => $data['attribute_value_id'] ? (int)$data['attribute_value_id'] : null,
                ':custom_value'      => $data['custom_value'] ?? null
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $tenantId = TenantContext::require();
        $oldData = $this->find($id);

        if (!$oldData) {
            return false;
        }

        $sql = "
            DELETE paa FROM product_attribute_assignments paa
            INNER JOIN products p ON paa.product_id = p.id
            WHERE paa.id = :id
        ";
        $params = [':id' => $id];
        
        if ($tenantId > 0) {
            $sql .= " AND p.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        // Log the action
        if ($userId && $result) {
            $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function deleteByProduct(int $productId, ?int $userId = null): bool
    {
        $tenantId = TenantContext::require();
        $assignments = $this->getByProduct($productId);

        if (empty($assignments)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            $sql = "
                DELETE paa FROM product_attribute_assignments paa
                INNER JOIN products p ON paa.product_id = p.id
                WHERE paa.product_id = :productId
            ";
            $params = [':productId' => $productId];
            
            if ($tenantId > 0) {
                $sql .= " AND p.tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            }

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            // Log the action for each deleted assignment
            if ($userId && $result) {
                foreach ($assignments as $assignment) {
                    $this->logAction($tenantId, $userId, 'delete', $assignment['id'], $assignment, null);
                }
            }

            $this->pdo->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode([
                'old' => $oldData,
                'new' => $newData
            ]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (:tenantId, :userId, 'product_attribute_assignment', :entityId, :action, :changes, :ip, NOW())
        ");

        $stmt->execute([
            ':tenantId' => $tenantId,
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}