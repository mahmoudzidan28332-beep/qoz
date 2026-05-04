<?php
declare(strict_types=1);


final class PdoPermissionsRepository
{
    private PDO $pdo;

    private const ALLOWED_COLS = ['key_name', 'display_name', 'description'];

    public function __construct(PDO $pdo)

    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE 1=1
            GROUP BY p.id
        ";

        $params = [];
        if ($tenantId > 0) {
            $sql = str_replace('WHERE 1=1', 'WHERE (p.tenant_id = :tenant_id OR p.tenant_id IS NULL OR p.tenant_id = 0)', $sql);
            $params[':tenant_id'] = $tenantId;
        } else {
            // For Platform Admin (tenantId 0), show EVERYTHING
            $sql = str_replace('WHERE 1=1', 'WHERE 1=1', $sql);
        }

        if (isset($filters['search']) && $filters['search']) {
            $sql .= " HAVING (p.key_name LIKE :search OR p.display_name LIKE :search OR p.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY p.created_at DESC";

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

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM permissions WHERE 1=1";
        $params = [];

        if ($tenantId > 0) {
            $sql .= " AND (tenant_id = :tenant_id OR tenant_id IS NULL OR tenant_id = 0)";
            $params[':tenant_id'] = $tenantId;
        }

        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (key_name LIKE :search OR display_name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $sql = "
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE p.id = :id
            GROUP BY p.id
            LIMIT 1
        ";
        $params = [':id' => $id];
        if ($tenantId > 0) {
            $sql = str_replace('WHERE p.id = :id', 'WHERE p.id = :id AND p.tenant_id = :tenant_id', $sql);
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
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE p.key_name = :key_name
            GROUP BY p.id
            LIMIT 1
        ";
        $params = [':key_name' => $key];
        if ($tenantId > 0) {
            $sql = str_replace('WHERE p.key_name = :key_name', 'WHERE p.key_name = :key_name AND p.tenant_id = :tenant_id', $sql);
            $params[':tenant_id'] = $tenantId;
        } else {
            $sql = str_replace('WHERE p.key_name = :key_name', 'WHERE p.key_name = :key_name AND (p.tenant_id IS NULL OR p.tenant_id = 0)', $sql);
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
                throw new ApplicationException('Permission key already exists in this tenant');
            }
        }

        if ($isUpdate) {
            $sql = "
                UPDATE permissions
                SET key_name = :key_name, display_name = :display_name, description = :description
                WHERE id = :id
            ";
            $params = [
                ':key_name' => $filtered['key_name'] ?? $oldData['key_name'],
                ':display_name' => $filtered['display_name'] ?? $oldData['display_name'],
                ':description' => $filtered['description'] ?? ($oldData['description'] ?? null),
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
                INSERT INTO permissions (tenant_id, key_name, display_name, description, created_at)
                VALUES (:tenant_id, :key_name, :display_name, :description, NOW())
            ");
            $writeTenantId = $this->normalizeWriteTenantId($tenantId);
            $stmt->execute([
                ':tenant_id' => $writeTenantId,
                ':key_name' => $filtered['key_name'] ?? null,
                ':display_name' => $filtered['display_name'] ?? null,
                ':description' => $filtered['description'] ?? null
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
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE permission_id = :permission_id AND tenant_id = :tenant_id");
            $stmt->execute([':permission_id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE permission_id = :permission_id");
            $stmt->execute([':permission_id' => $id]);
        }

        // Delete permission
        if ($tenantId > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = :id AND tenant_id = :tenant_id");
            $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = :id AND tenant_id = 0");
            $result = $stmt->execute([':id' => $id]);
        }

        if ($userId) {
            $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
        }


        return $result;
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
        }

        try {
            $logTenantId = $this->resolveLogTenantId($tenantId, $entityId, $oldData, $newData);


            $stmt = $this->pdo->prepare("
                INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
                VALUES (?, ?, 'permission', ?, ?, ?, ?, NOW())
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
            error_log('[PdoPermissionsRepository::logAction] ' . $e->getMessage());
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
            $stmt = $this->pdo->prepare("SELECT tenant_id FROM permissions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $entityId]);
            $value = $stmt->fetchColumn();
            if ($value !== false && $value !== null && is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        return null;
    }
}
