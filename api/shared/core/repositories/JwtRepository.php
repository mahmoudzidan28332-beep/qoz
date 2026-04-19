<?php
declare(strict_types=1);

class JwtRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isJtiBlacklisted(string $jti): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM tokens_blacklist WHERE jti = ?");
        $stmt->execute([$jti]);
        return $stmt->rowCount() > 0;
    }

    public function insertJtiBlacklist(string $jti, int $userId, string $type, ?string $ip, ?string $userAgent): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO tokens_blacklist (jti, user_id, type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$jti, $userId, $type, $ip, $userAgent]);
    }

    public function findActiveUserWithRole(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.username, r.key_name as user_type, u.is_active "
            . "FROM users u JOIN roles r ON u.role_id = r.id "
            . "WHERE u.id = ? AND u.is_active = 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function userHasPermission(int $userId, string $permissionKey): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id FROM permissions p "
            . "JOIN role_permissions rp ON p.id = rp.permission_id "
            . "JOIN roles r ON rp.role_id = r.id "
            . "JOIN users u ON u.role_id = r.id "
            . "WHERE u.id = ? AND p.key_name = ? AND u.is_active = 1"
        );
        $stmt->execute([$userId, $permissionKey]);
        return $stmt->rowCount() > 0;
    }

    public function insertUserSession(int $userId, string $token, ?string $userAgent, ?string $ip, string $expiresAt): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO user_sessions (user_id, token, user_agent, ip, expires_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $token, $userAgent, $ip, $expiresAt]);
    }

    public function revokeUserSession(string $token): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE token = ?");
        return $stmt->execute([$token]);
    }
}
