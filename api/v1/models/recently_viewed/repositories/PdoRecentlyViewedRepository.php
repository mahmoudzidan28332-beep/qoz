<?php
declare(strict_types=1);

final class PdoRecentlyViewedRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(?int $userId, ?string $sessionId, int $productId): void
    {
        $this->pdo->prepare(
            'INSERT INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = NOW()'
        )->execute([$userId, $sessionId, $productId]);
    }

    public function insertIgnore(?int $userId, ?string $sessionId, int $productId): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
             VALUES (?, ?, ?, NOW())'
        )->execute([$userId, $sessionId, $productId]);
    }
}
