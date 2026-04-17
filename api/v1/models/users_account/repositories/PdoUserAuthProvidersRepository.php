<?php
declare(strict_types=1);

/**
 * Repository for user_auth_providers table.
 */
final class PdoUserAuthProvidersRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find user_id linked to a provider.
     */
    public function findUserIdByProvider(string $provider, string $providerUserId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM user_auth_providers WHERE provider = ? AND provider_user_id = ? LIMIT 1');
        $stmt->execute([$provider, $providerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['user_id'] : null;
    }

    /**
     * Link a provider to a user (INSERT IGNORE for race conditions).
     */
    public function linkProvider(int $userId, string $provider, string $providerUserId, string $providerExtra): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO user_auth_providers (user_id, provider, provider_user_id, provider_extra) VALUES (?,?,?,?)'
        )->execute([$userId, $provider, $providerUserId, $providerExtra]);
    }

    /**
     * Get provider_extra for a specific provider/user combo (e.g. Apple email lookup).
     */
    public function findProviderExtra(string $provider, string $providerUserId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT provider_extra FROM user_auth_providers WHERE provider = ? AND provider_user_id = ? LIMIT 1");
        $stmt->execute([$provider, $providerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ($row['provider_extra'] ?? null) : null;
    }
}
