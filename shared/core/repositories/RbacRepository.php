<?php
declare(strict_types=1);

class RbacRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertAuditLog(
        int $tenantId,
        ?int $userId,
        string $action,
        ?string $ipAddress,
        string $userAgent,
        string $payloadJson
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (tenant_id, user_id, action, ip_address, user_agent, payload, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId,
            $userId,
            $action,
            $ipAddress,
            $userAgent,
            $payloadJson,
        ]);
    }

    public function fetchUserRoleInfo(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tu.role_id, r.key_name
            FROM tenant_users tu
            LEFT JOIN roles r ON r.id = tu.role_id
            WHERE tu.user_id = ? AND tu.tenant_id = ? AND tu.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['role_id' => null, 'role_key' => null];
        return [
            'role_id' => isset($row['role_id']) ? (int)$row['role_id'] : null,
            'role_key' => $row['key_name'] ?? null,
        ];
    }

    public function fetchPermissions(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.key_name
            FROM tenant_users tu
            INNER JOIN role_permissions rp ON rp.role_id = tu.role_id AND rp.tenant_id = tu.tenant_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE tu.user_id = ? AND tu.tenant_id = ? AND tu.is_active = 1
        ");
        $stmt->execute([$userId, $tenantId]);
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['key_name'])) $out[] = (string)$r['key_name'];
        }
        return array_values(array_unique($out));
    }

    public function fetchGlobalResourcePermissions(string $resourceType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM resource_permissions
            WHERE resource_type = :resource AND role_id IS NULL AND tenant_id IS NULL
        ");
        $stmt->execute([':resource' => $resourceType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchRoleGlobalResourcePermissions(string $resourceType, int $roleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM resource_permissions
            WHERE resource_type = :resource AND role_id = :role AND tenant_id IS NULL
        ");
        $stmt->execute([':resource' => $resourceType, ':role' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchTenantGlobalResourcePermissions(string $resourceType, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM resource_permissions
            WHERE resource_type = :resource AND role_id IS NULL AND tenant_id = :tenant
        ");
        $stmt->execute([':resource' => $resourceType, ':tenant' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchRoleTenantResourcePermissions(string $resourceType, int $roleId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM resource_permissions
            WHERE resource_type = :resource AND role_id = :role AND tenant_id = :tenant
        ");
        $stmt->execute([':resource' => $resourceType, ':role' => $roleId, ':tenant' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
