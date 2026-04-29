<?php
declare(strict_types=1);

namespace Shared\Application\Auth;

use PDO;
use Throwable;

class IdentityHydrator
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hydrateFromDatabase(array $candidate, string $requestId, ?int $defaultTenantId): ?UserIdentity
    {
        if (!$this->pdo) return null;
        $userId = (int)($candidate['id'] ?? 0);
        if (!$userId) return null;

        try {
            $userStmt = $this->pdo->prepare("SELECT id, username, email, preferred_language, is_active FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) return null;

            $membershipStmt = $this->pdo->prepare(
                "SELECT tu.tenant_id, tu.role_id, r.key_name AS role_key
                 FROM tenant_users tu LEFT JOIN roles r ON r.id = tu.role_id
                 WHERE tu.user_id = ? AND tu.is_active = 1 ORDER BY tu.joined_at DESC LIMIT 1"
            );
            $membershipStmt->execute([$userId]);
            $membership = $membershipStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $tenantId = (int)($membership['tenant_id'] ?? ($candidate['tenant_id'] ?? $defaultTenantId));
            $roleId = isset($membership['role_id']) ? (int)$membership['role_id'] : null;
            $roleKey = $membership['role_key'] ?? null;

            $roles = $candidate['roles'] ?? [];
            $permissions = $candidate['permissions'] ?? [];
            
            if ($tenantId && class_exists('\RBAC', false)) {
                try {
                    $rbac = new \RBAC($this->pdo, null, $tenantId);
                    $rbacData = $rbac->loadPermissionsForUser($userId);
                    $roles = $rbacData['roles'] ?? $roles;
                    $permissions = $rbacData['permissions'] ?? $permissions;
                } catch (Throwable $e) {
                    if (function_exists('safe_log')) safe_log('warning', 'IdentityHydrator: RBAC failed', ['error' => $e->getMessage()]);
                }
            }

            if (!$roles && $roleKey) $roles = [$roleKey];

            $isSuperAdmin = ($roleId === 1 || in_array('super_admin', $roles, true));
            if ($isSuperAdmin) $permissions = $this->loadAllPermissionKeys();

            $resourcePermissions = $candidate['resource_permissions'] ?? [];
            if (!$resourcePermissions) {
                $resourcePermissions = $this->loadResourcePermissions($roleId, $tenantId, $isSuperAdmin);
            }

            $user = array_merge($candidate['user'] ?? [], [
                'id' => (int)$userRow['id'],
                'username' => $userRow['username'],
                'email' => $userRow['email'],
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'preferred_language' => $userRow['preferred_language'] ?? 'en',
                'is_active' => (bool)$userRow['is_active'],
            ]);

            return new UserIdentity($userId, $tenantId, $roleId, $roles, $permissions, $resourcePermissions, true, (string)($candidate['source'] ?? 'db'), $requestId, $user, ['hydrated_from_db' => true]);
        } catch (Throwable $e) {
            if (function_exists('safe_log')) safe_log('error', 'IdentityHydrator: Hydration failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function loadAllPermissionKeys(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT key_name FROM permissions WHERE key_name IS NOT NULL AND key_name <> ''");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable) { return []; }
    }

    private function loadResourcePermissions(?int $roleId, ?int $tenantId, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) return $this->loadSuperAdminResourcePermissions($tenantId);
        if (!$tenantId) return [];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT rp.resource_type, p.key_name AS permission_key, rp.can_view_all, rp.can_view_own, rp.can_view_tenant, rp.can_create, rp.can_edit_all, rp.can_edit_own, rp.can_delete_all, rp.can_delete_own
                 FROM resource_permissions rp LEFT JOIN permissions p ON p.id = rp.permission_id
                 WHERE (rp.role_id = ? OR rp.role_id IS NULL) AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)
                 ORDER BY rp.resource_type, (rp.role_id IS NULL) DESC, (rp.tenant_id IS NULL) DESC"
            );
            $stmt->execute([$roleId, $tenantId]);
            
            $permissions = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $type = $row['resource_type'];
                if (!$type) continue;
                if (!isset($permissions[$type])) {
                    $permissions[$type] = ['permission_key' => $row['permission_key']];
                }
                foreach (['can_view_all', 'can_view_own', 'can_view_tenant', 'can_create', 'can_edit_all', 'can_edit_own', 'can_delete_all', 'can_delete_own'] as $flag) {
                    if ($row[$flag] !== null) $permissions[$type][$flag] = (bool)$row[$flag];
                }
            }
            return $permissions;
        } catch (Throwable) { return []; }
    }

    private function loadSuperAdminResourcePermissions(?int $tenantId): array
    {
        try {
            $sql = "SELECT DISTINCT resource_type FROM resource_permissions WHERE resource_type IS NOT NULL AND resource_type <> ''";
            if ($tenantId) $sql .= " AND (tenant_id = $tenantId OR tenant_id IS NULL)";
            $stmt = $this->pdo->query($sql);
            $permissions = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $type) {
                $permissions[$type] = [
                    'can_view_all' => true, 'can_view_own' => true, 'can_view_tenant' => true,
                    'can_create' => true, 'can_edit_all' => true, 'can_edit_own' => true,
                    'can_delete_all' => true, 'can_delete_own' => true, 'permission_key' => '*'
                ];
            }
            return $permissions;
        } catch (Throwable) { return []; }
    }
}
