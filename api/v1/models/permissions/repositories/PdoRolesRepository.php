<?php
declare(strict_types=1);


final class PdoRolesRepository
{
    private PDO $pdo;

    private const ALLOWED_COLS = ['key_name', 'display_name'];

    public function __construct(PDO $pdo)

    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE 1=1
            GROUP BY r.id
            ORDER BY r.created_at DESC
        ";

        $params = [];
        if ($tenantId > 0) {
            $sql = str_replace('WHERE 1=1', 'WHERE (r.tenant_id = :tenant_id OR r.tenant_id IS NULL OR r.tenant_id = 0)', $sql);
            $params[':tenant_id'] = $tenantId;
        } else {
            // For Platform Admin (tenantId 0), show EVERYTHING
            $sql = str_replace('WHERE 1=1', 'WHERE 1=1', $sql);
        }

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
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles WHERE tenant_id = :tenant_id");
            $stmt->execute([':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM roles');
        }
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $sql = "
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.id = :id
            GROUP BY r.id
            LIMIT 1
        ";
        $params = [':id' => $id];
        if ($tenantId > 0) {
            $sql = str_replace('WHERE r.id = :id', 'WHERE r.id = :id AND r.tenant_id = :tenant_id', $sql);
            $params[':tenant_id'] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByKey(int $tenantId, string $key): ?array
    {
        $sql = "
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.key_name = :key_name
            GROUP BY r.id
            LIMIT 1
        ";
        $params = [':key_name' => $key];

        if ($tenantId > 0) {
            $sql = str_replace('WHERE r.key_name = :key_name', 'WHERE r.key_name = :key_name AND r.tenant_id = :tenant_id', $sql);
            $params[':tenant_id'] = $tenantId;
        } else {
            $sql = str_replace('WHERE r.key_name = :key_name', 'WHERE r.key_name = :key_name AND (r.tenant_id IS NULL OR r.tenant_id = 0)', $sql);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find($tenantId, (int)$data['id']) : null;

        // 🔒 SECURITY: Mass Assignment Protection
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_COLS));

        // Check uniqueness
        if (!$isUpdate || ($oldData && $oldData['key_name'] !== ($filtered['key_name'] ?? ''))) {
            if ($this->findByKey($tenantId, $filtered['key_name'] ?? '')) {
                throw new ApplicationException('Role key already exists in this tenant');
            }
        }

        if ($isUpdate) {
            $sql = "
                UPDATE roles
                SET key_name = :key_name, display_name = :display_name
                WHERE id = :id
            ";
            $params = [
                ':key_name' => $filtered['key_name'] ?? $oldData['key_name'],
                ':display_name' => $filtered['display_name'] ?? $oldData['display_name'],
                ':id' => (int)$data['id'],
            ];
            if ($tenantId > 0) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO roles (tenant_id, key_name, display_name, created_at)
                VALUES (:tenant_id, :key_name, :display_name, NOW())
            ");
            $writeTenantId = $this->normalizeWriteTenantId($tenantId);
            $stmt->execute([
                ':tenant_id' => $writeTenantId,
                ':key_name' => $filtered['key_name'] ?? null,
                ':display_name' => $filtered['display_name'] ?? null
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }


        if ($userId) {
            $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }


    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($tenantId, $id);
        if (!$oldData) return false;

        // Delete role permissions first
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
            $stmt->execute([':role_id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmt->execute([':role_id' => $id]);
        }

        // Delete role
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = :id AND tenant_id = :tenant_id");
            $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = :id AND tenant_id = 0");
            $result = $stmt->execute([':id' => $id]);
        }

        if ($userId) {
            $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
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

    public function assignPermissions(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        // Delete existing
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
            $stmt->execute([':role_id' => $roleId, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmt->execute([':role_id' => $roleId]);
        }

        // Insert new
        if (!empty($permissionIds)) {
            $values = [];
            $params = [];
            $now = date('Y-m-d H:i:s');
            $i = 0;
            $writeTenantId = $this->normalizeWriteTenantId($tenantId);
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
            $this->logAction($tenantId, $userId, 'assign_permissions', $roleId, null, ['permission_ids' => $permissionIds]);
        }
    }


    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        } elseif ($action === 'assign_permissions' && $newData) {
            $changes = json_encode($newData);
        }

        try {
            $logTenantId = $this->resolveLogTenantId($tenantId, $entityId, $oldData, $newData);

            $stmt = $this->pdo->prepare("
                INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
                VALUES (?, ?, 'role', ?, ?, ?, ?, NOW())
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
            error_log('[PdoRolesRepository::logAction] ' . $e->getMessage());
        }
    }

    private function normalizeWriteTenantId(int $tenantId): int
    {
        return $tenantId > 0 ? $tenantId : 0;
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
            $stmt = $this->pdo->prepare("SELECT tenant_id FROM roles WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $entityId]);
            $value = $stmt->fetchColumn();
            if ($value !== false && $value !== null && is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        return null;
    }

}
