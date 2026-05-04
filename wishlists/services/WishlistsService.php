<?php
declare(strict_types=1);

final class WishlistsService
{
    private PdoWishlistsRepository $repo;

    public function __construct(PdoWishlistsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function findDefaultByUser(int $userId): ?array
    {
        return $this->repo->findDefaultByUser($userId);
    }

    public function createDefault(int $userId, int $tenantId, string $name): int
    {
        return $this->repo->createDefault($userId, $tenantId, $name);
    }

    public function updateTotalItems(int $wishlistId): void
    {
        $this->repo->updateTotalItems($wishlistId);
    }

    public function listItems(int $wishlistId, string $lang): array
    {
        return $this->repo->listItems($wishlistId, $lang);
    }

    public function listItemProductIds(int $wishlistId): array
    {
        return $this->repo->listItemProductIds($wishlistId);
    }

    public function findProductTenantId(int $productId): int
    {
        return $this->repo->findProductTenantId($productId);
    }

    public function findItem(int $wishlistId, int $productId): ?array
    {
        return $this->repo->findItem($wishlistId, $productId);
    }

    public function restoreItem(int $itemId): void
    {
        $this->repo->restoreItem($itemId);
    }

    public function addItem(int $wishlistId, int $productId, int $entityId, int $tenantId): void
    {
        $this->repo->addItem($wishlistId, $productId, $entityId, $tenantId);
    }

    public function softRemoveItem(int $wishlistId, int $productId): void
    {
        $this->repo->softRemoveItem($wishlistId, $productId);
    }

    public function softRemoveAllItems(int $wishlistId): void
    {
        $this->repo->softRemoveAllItems($wishlistId);
    }
}
