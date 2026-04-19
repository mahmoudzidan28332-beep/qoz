<?php
declare(strict_types=1);

/**
 * Repository for user_phone_verifications table.
 */
final class PdoUserPhoneVerificationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a pending (unused) verification by token hash.
     */
    public function findPendingByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.user_id, v.device_hash, v.session_id, v.user_agent, v.ip, v.expires_at
             FROM user_phone_verifications v
             WHERE v.token_hash = ? AND v.used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a used token belongs to an active user.
     */
    public function findUsedTokenUserStatus(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.is_active
               FROM user_phone_verifications v
               JOIN users u ON u.id = v.user_id
              WHERE v.token_hash = ?
              LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Mark a verification record as used.
     */
    public function markUsed(int $id): void
    {
        $this->pdo->prepare('UPDATE user_phone_verifications SET used_at = NOW() WHERE id = ?')
            ->execute([$id]);
    }

    /**
     * Insert a new phone verification record. Returns the new row ID.
     */
    public function create(int $userId, string $tokenHash, string $deviceHash, string $sessionId, string $userAgent, string $ip, string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_phone_verifications (user_id, token_hash, device_hash, session_id, user_agent, ip, expires_at) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $tokenHash, $deviceHash, $sessionId, $userAgent, $ip, $expiresAt]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update the session_id of a verification record.
     */
    public function updateSessionId(int $id, string $sessionId): void
    {
        $this->pdo->prepare('UPDATE user_phone_verifications SET session_id = ? WHERE id = ?')
            ->execute([$sessionId, $id]);
    }

    /**
     * Count distinct user registrations from an IP in the last hour.
     */
    public function countRecentByIp(string $ip): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT user_id) FROM user_phone_verifications WHERE ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count recent verifications for a user (within last 60 seconds).
     */
    public function countRecentByUserId(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_phone_verifications WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
