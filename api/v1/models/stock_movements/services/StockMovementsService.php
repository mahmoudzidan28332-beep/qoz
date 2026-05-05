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

    public function getMovement(int $id, int $tenantId = 0): ?array
    {
        return $this->movements->find($id, $tenantId);
    }

    public function createMovement(array $data): int
    {
        return $this->movements->create($data);
    }

    public function deleteMovement(int $id, int $tenantId = 0): bool
    {
        $existing = $this->movements->find($id, $tenantId);
        if (!$existing) {
            throw new ApplicationException("Stock movement not found with ID: $id");
        }
        return $this->movements->delete($id);
    }

    public function getByProduct(int $productId, int $tenantId = 0): array
    {
        return $this->movements->getByProduct($productId, $tenantId);
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

    public function findWithProductName(int $id, int $tenantId = 0): ?array
    {
        return $this->movements->findWithProductName($id, $tenantId);
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