<?php
declare(strict_types=1);

final class PdoEntityRatingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function updateRating(int $id, float $rating, ?string $review): void
    {
        $this->pdo->prepare(
            'UPDATE entity_ratings SET rating = ?, review = ?, is_active = 1, updated_at = NOW() WHERE id = ?'
        )->execute([$rating, $review, $id]);
    }

    public function createRating(int $entityId, int $userId, float $rating, ?string $review): void
    {
        $this->pdo->prepare(
            'INSERT INTO entity_ratings (entity_id, user_id, rating, review, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())'
        )->execute([$entityId, $userId, $rating, $review]);
    }
}
