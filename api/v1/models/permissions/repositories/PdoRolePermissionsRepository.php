<?php
declare(strict_types=1);


final class PdoRolePermissionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT rp.*, 
                   r.display_name as role_name, r.key_name as role_key,
                   p.display_name as permission_name, p.key_name as permission_key
            FROM role_permissions rp
            INNER JOIN roles r ON rp.role_id = r.id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE 1=1
        ";

        $params = [];

        if ($tenantId > 0) {
            $sql .= " AND (rp.tenant_id = :tenant_id OR rp.tenant_id IS NULL OR rp.tenant_id = 0)";
            $params[':tenant_id'] = $tenantId;
        }

        $sql .= " ORDER BY rp.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId): int
    {
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE (tenant_id = :tenant_id OR tenant_id IS NULL OR tenant_id = 0)");
            $stmt->execute([':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM role_permissions");
            $stmt->execute();
        }
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT rp.*, 
                       r.display_name as role_name, r.key_name as role_key,
                       p.display_name as permission_name, p.key_name as permission_key
                FROM role_permissions rp
                INNER JOIN roles r ON rp.role_id = r.id
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.id = :id AND rp.tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT rp.*, 
                       r.display_name as role_name, r.key_name as role_key,
                       p.display_name as permission_name, p.key_name as permission_key
                FROM role_permissions rp
                INNER JOIN roles r ON rp.role_id = r.id
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function assign(int $tenantId, int $roleId, int $permissionId, ?int $userId = null): int
    {
        $writeTenantId = $this->resolveAssignmentTenantId($tenantId, $roleId, $permissionId);

        // Check if already exists
        if ($writeTenantId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM role_permissions 
                WHERE tenant_id = :tenant_id AND role_id = :role_id AND permission_id = :permission_id
                LIMIT 1
            ");
            $stmt->execute([
                ':tenant_id' => $writeTenantId,
                ':role_id' => $roleId,
                ':permission_id' => $permissionId
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id FROM role_permissions 
                WHERE tenant_id IS NULL AND role_id = :role_id AND permission_id = :permission_id
                LIMIT 1
            ");
            $stmt->execute([
                ':role_id' => $roleId,
                ':permission_id' => $permissionId
            ]);
        }
        
        if ($stmt->fetch()) {
            throw new ApplicationException('Role permission already exists');
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO role_permissions (tenant_id, role_id, permission_id, created_at)
            VALUES (:tenant_id, :role_id, :permission_id, NOW())
        ");
        $stmt->bindValue(':tenant_id', $writeTenantId, $writeTenantId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $stmt->execute();
        $id = (int)$this->pdo->lastInsertId();

        if ($userId) {
            $this->logAction($writeTenantId ?? 0, $userId, 'assign', $id, null, [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'tenant_id' => $writeTenantId,
            ]);
        }

        return $id;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($tenantId, $id);
        if (!$oldData) return false;

        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE id = :id AND tenant_id = :tenant_id");
            $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE id = :id");
            $result = $stmt->execute([':id' => $id]);
        }

        if ($userId) {
            $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function deleteByRolePermission(int $tenantId, int $roleId, int $permissionId, ?int $userId = null): bool
    {
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("
                DELETE FROM role_permissions 
                WHERE tenant_id = :tenant_id AND role_id = :role_id AND permission_id = :permission_id
            ");
            $result = $stmt->execute([
                ':tenant_id' => $tenantId,
                ':role_id' => $roleId,
                ':permission_id' => $permissionId
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                DELETE FROM role_permissions 
                WHERE role_id = :role_id AND permission_id = :permission_id
            ");
            $result = $stmt->execute([
                ':role_id' => $roleId,
                ':permission_id' => $permissionId
            ]);
        }

        if ($userId) {
            $this->logAction($tenantId, $userId, 'delete_by_ids', 0, null, [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
        }

        return $result;
    }

    public function getRolePermissions(int $tenantId, int $roleId): array
    {
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id AND rp.tenant_id = :tenant_id
                ORDER BY p.display_name
            ");
            $stmt->execute([':role_id' => $roleId, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
                ORDER BY p.display_name
            ");
            $stmt->execute([':role_id' => $roleId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignMultiple(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        // Delete existing
        $writeTenantId = $tenantId > 0 ? $tenantId : $this->resolveAssignmentTenantId($tenantId, $roleId, null);

        if ($writeTenantId !== null) {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
            $stmt->execute([':role_id' => $roleId, ':tenant_id' => $writeTenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id IS NULL");
            $stmt->execute([':role_id' => $roleId]);
        }

        // Insert new
        if (!empty($permissionIds)) {
            $values = [];
            $params = [];
            $now = date('Y-m-d H:i:s');
            $i = 0;
            foreach ($permissionIds as $permId) {
                $values[] = "(:tenant_id_{$i}, :role_id_{$i}, :permission_id_{$i}, :created_at_{$i})";
                $params[":tenant_id_{$i}"] = $writeTenantId;
                $params[":role_id_{$i}"] = $roleId;
                $params[":permission_id_{$i}"] = (int)$permId;
                $params[":created_at_{$i}"] = $now;
                $i++;
            }
            $sql = "INSERT INTO role_permissions (tenant_id, role_id, permission_id, created_at) VALUES " . implode(', ', $values);
            $this->pdo->prepare($sql)->execute($params);
        }

        if ($userId) {
            $this->logAction($writeTenantId ?? 0, $userId, 'assign_multiple', $roleId, null, ['permission_ids' => $permissionIds, 'tenant_id' => $writeTenantId]);
        }
    }

    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'assign' && $newData) {
            $changes = json_encode(['assigned' => $newData]);
        } elseif ($action === 'assign_multiple' && $newData) {
            $changes = json_encode($newData);
        }

        try {
            $logTenantId = $this->resolveLogTenantId($tenantId, $entityId, $oldData, $newData);

            $stmt = $this->pdo->prepare("
                INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
                VALUES (?, ?, 'role_permission', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $logTenantId,
                $userId,
                $entityId,
                $action,
                $changes,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('[PdoRolePermissionsRepository::logAction] ' . $e->getMessage());
        }
    }

    private function resolveAssignmentTenantId(int $tenantId, ?int $roleId, ?int $permissionId): ?int
    {
        if ($tenantId > 0) {
            return $tenantId;
        }

        if ($roleId !== null && $roleId > 0) {
            $stmt = $this->pdo->prepare("SELECT tenant_id FROM roles WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $roleId]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return ($value !== null && (int)$value > 0) ? (int)$value : 0;
            }
        }

        if ($permissionId !== null && $permissionId > 0) {
            $stmt = $this->pdo->prepare("SELECT tenant_id FROM permissions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $permissionId]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return ($value !== null && (int)$value > 0) ? (int)$value : 0;
            }
        }

        return 0;
    }

    private function resolveLogTenantId(int $tenantId, int $entityId, ?array $oldData, ?array $newData): ?int
    {
        if ($tenantId > 0) {
            return $tenantId;
        }

        foreach ([$oldData, $newData] as $row) {
            if (is_array($row) && isset($row['tenant_id']) && is_numeric($row['tenant_id']) && (int)$row['tenant_id'] > 0) {
                return (int)$row['tenant_id'];
            }
        }

        if ($entityId > 0) {
            $stmt = $this->pdo->prepare("SELECT tenant_id FROM role_permissions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $entityId]);
            $value = $stmt->fetchColumn();
            if ($value !== false && $value !== null && is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        return null;
    }
}
