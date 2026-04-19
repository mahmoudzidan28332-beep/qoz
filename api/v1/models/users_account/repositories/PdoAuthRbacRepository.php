<?php
declare(strict_types=1);

/**
 * Repository for RBAC lookups used during authentication.
 */
final class PdoAuthRbacRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if a table exists (e.g. user_roles, user_permissions).
     */
    public function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get role key_names via user_roles join.
     */
    public function getRoleKeysByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT r.key_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Get a single role key_name by role ID.
     */
    public function getRoleKeyById(int $roleId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT key_name FROM roles WHERE id = ? LIMIT 1");
        $stmt->execute([$roleId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    /**
     * Get permission key_names via user_permissions join.
     */
    public function getPermissionKeysByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.key_name FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Get permission key_names for a given role ID.
     */
    public function getPermissionKeysByRoleId(int $roleId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Get distinct permission key_names for a set of role key_names.
     */
    public function getPermissionKeysByRoleKeys(array $roleKeys): array
    {
        if (empty($roleKeys)) return [];
        $in = implode(',', array_fill(0, count($roleKeys), '?'));
        $stmt = $this->pdo->prepare("SELECT DISTINCT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.key_name IN ($in)");
        $stmt->execute($roleKeys);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
}
