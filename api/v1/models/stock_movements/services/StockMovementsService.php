<?php
declare(strict_types=1);

/**
 * Service layer for stock movement management.
 * Creates repository internally from a single PDO instance.
 */
final class StockMovementsService
{
    private PdoStockMovementsRepository $movements;

    public function __construct(PDO $pdo)
    {
        $this->movements = new PdoStockMovementsRepository($pdo);
    }

    // ================================
    // Stock Movements CRUD
    // ================================

    public function listMovements(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->movements->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getMovement(int $id): ?array
    {
        return $this->movements->find($id);
    }

    public function createMovement(array $data): int
    {
        return $this->movements->create($data);
    }

    public function deleteMovement(int $id): bool
    {
        $existing = $this->movements->find($id);
        if (!$existing) {
            throw new RuntimeException("Stock movement not found with ID: $id");
        }
        return $this->movements->delete($id);
    }

    public function getByProduct(int $productId): array
    {
        return $this->movements->getByProduct($productId);
    }

    public function movementStats(array $filters = []): array
    {
        return $this->movements->stats($filters);
    }

    public function lookupByBarcode(string $barcode, ?int $entityId = null): ?array
    {
        return $this->movements->lookupByBarcode($barcode, $entityId);
    }

    public function lookupBySku(string $sku, string $lang, ?int $entityId = null): ?array
    {
        return $this->movements->lookupBySku($sku, $lang, $entityId);
    }

    public function findWithProductName(int $id): ?array
    {
        return $this->movements->findWithProductName($id);
    }

    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        return $this->movements->listPaginated($filters, $limit, $offset);
    }

    public function updateMovement(int $id, array $data, array $oldMovement): void
    {
        $this->movements->updateMovement($id, $data, $oldMovement);
    }
}
