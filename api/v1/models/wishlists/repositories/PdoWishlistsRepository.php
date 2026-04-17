<?php
declare(strict_types=1);

/**
 * PdoWishlistsRepository
 *
 * Repository for wishlist and wishlist_items queries used by the public
 * wishlist route.
 */
final class PdoWishlistsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── wishlists table ─────────────────────────────────────────

    public function findDefaultByUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM wishlists WHERE user_id = ? AND is_default = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createDefault(int $userId, int $tenantId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO wishlists (user_id, tenant_id, entity_id, wishlist_name, is_default, total_items, created_at, updated_at)
             VALUES (?, ?, 1, ?, 1, 0, NOW(), NOW())'
        );
        $stmt->execute([$userId, $tenantId, $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTotalItems(int $wishlistId): void
    {
        $cnt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id = ? AND removed_at IS NULL'
        );
        $cnt->execute([$wishlistId]);
        $this->pdo->prepare('UPDATE wishlists SET total_items = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$cnt->fetchColumn(), $wishlistId]);
    }

    // ── wishlist_items table ────────────────────────────────────

    public function listItems(int $wishlistId, string $lang): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT wi.id, wi.product_id, wi.priority, wi.notes, wi.created_at,
                    COALESCE(pt.name, p.slug) AS product_name,
                    (SELECT i2.url FROM images i2 WHERE i2.owner_id = p.id AND i2.owner_type = 'product' ORDER BY i2.id ASC LIMIT 1) AS image_url,
                    (SELECT pp2.price FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS price,
                    (SELECT pp2.currency_code FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS currency_code
             FROM wishlist_items wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
             WHERE wi.wishlist_id = ? AND wi.removed_at IS NULL
             ORDER BY wi.priority DESC, wi.created_at DESC"
        );
        $stmt->execute([$lang, $wishlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listItemProductIds(int $wishlistId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id FROM wishlist_items WHERE wishlist_id = ? AND removed_at IS NULL'
        );
        $stmt->execute([$wishlistId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');
    }

    public function findProductTenantId(int $productId): int
    {
        $stmt = $this->pdo->prepare('SELECT tenant_id FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['tenant_id'] : 0;
    }

    public function findItem(int $wishlistId, int $productId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, removed_at FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1'
        );
        $stmt->execute([$wishlistId, $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function restoreItem(int $itemId): void
    {
        $this->pdo->prepare(
            'UPDATE wishlist_items SET removed_at = NULL, updated_at = NOW() WHERE id = ?'
        )->execute([$itemId]);
    }

    public function addItem(int $wishlistId, int $productId, int $entityId, int $tenantId): void
    {
        $this->pdo->prepare(
            'INSERT INTO wishlist_items (wishlist_id, product_id, entity_id, tenant_id, product_variant_id, priority, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 0, NOW(), NOW())'
        )->execute([$wishlistId, $productId, $entityId, $tenantId]);
    }

    public function softRemoveItem(int $wishlistId, int $productId): void
    {
        $this->pdo->prepare(
            'UPDATE wishlist_items SET removed_at = NOW(), updated_at = NOW() WHERE wishlist_id = ? AND product_id = ? AND removed_at IS NULL'
        )->execute([$wishlistId, $productId]);
    }

    public function softRemoveAllItems(int $wishlistId): void
    {
        $this->pdo->prepare(
            'UPDATE wishlist_items SET removed_at = NOW(), updated_at = NOW() WHERE wishlist_id = ? AND removed_at IS NULL'
        )->execute([$wishlistId]);
    }
}
