<?php
declare(strict_types=1);

final class EntityRatingsService
{
    private PdoEntityRatingsRepository $repo;

    public function __construct(PdoEntityRatingsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function updateRating(int $id, float $rating, ?string $review): void
    {
        $this->repo->updateRating($id, $rating, $review);
    }

    public function createRating(int $entityId, int $userId, float $rating, ?string $review): void
    {
        $this->repo->createRating($entityId, $userId, $rating, $review);
    }
}
