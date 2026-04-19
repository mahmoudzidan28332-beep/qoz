<?php
declare(strict_types=1);

final class RecentlyViewedService
{
    private PdoRecentlyViewedRepository $repo;

    public function __construct(PdoRecentlyViewedRepository $repo)
    {
        $this->repo = $repo;
    }

    public function upsert(?int $userId, ?string $sessionId, int $productId): void
    {
        $this->repo->upsert($userId, $sessionId, $productId);
    }

    public function insertIgnore(?int $userId, ?string $sessionId, int $productId): void
    {
        $this->repo->insertIgnore($userId, $sessionId, $productId);
    }
}
