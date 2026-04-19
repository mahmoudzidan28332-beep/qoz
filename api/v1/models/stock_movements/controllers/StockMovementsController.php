<?php
declare(strict_types=1);

/**
 * Thin controller pass-through to StockMovementsService.
 */
final class StockMovementsController
{
    private StockMovementsService $service;

    public function __construct(StockMovementsService $service)
    {
        $this->service = $service;
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
        return $this->service->listMovements($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getMovement(int $id): ?array
    {
        return $this->service->getMovement($id);
    }

    public function createMovement(array $data): int
    {
        return $this->service->createMovement($data);
    }

    public function deleteMovement(int $id): bool
    {
        return $this->service->deleteMovement($id);
    }

    public function getByProduct(int $productId): array
    {
        return $this->service->getByProduct($productId);
    }

    public function movementStats(array $filters = []): array
    {
        return $this->service->movementStats($filters);
    }

    public function lookupByBarcode(string $barcode): ?array
    {
        return $this->service->lookupByBarcode($barcode);
    }

    public function lookupBySku(string $sku, string $lang, ?int $entityId = null): ?array
    {
        return $this->service->lookupBySku($sku, $lang, $entityId);
    }

    public function findWithProductName(int $id): ?array
    {
        return $this->service->findWithProductName($id);
    }

    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        return $this->service->listPaginated($filters, $limit, $offset);
    }

    public function updateMovement(int $id, array $data, array $oldMovement): void
    {
        $this->service->updateMovement($id, $data, $oldMovement);
    }
}
