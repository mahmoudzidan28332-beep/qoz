<?php

declare(strict_types=1);

require_once __DIR__ . '/EntityProductQueryRepository.php';
require_once __DIR__ . '/EntityProductWriteRepository.php';

/**
 * PdoEntityProductsRepository
 * 
 * Refactored: This class now acts as a facade delegating to focused 
 * Query and Write repositories to adhere to Single Responsibility Principle.
 */
final class PdoEntityProductsRepository
{
    private EntityProductQueryRepository $queryRepo;
    private EntityProductWriteRepository $writeRepo;

    public function __construct(private readonly PDO $pdo)
    {
        $this->queryRepo = new EntityProductQueryRepository($pdo);
        $this->writeRepo = new EntityProductWriteRepository($pdo, $this->queryRepo);
    }

    // ────────────────────────────────────────────────────────────────────
    // Read Operations (Delegated to QueryRepository)
    // ────────────────────────────────────────────────────────────────────

    public function all(
        ?int   $limit    = null,
        ?int   $offset   = null,
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
    ): array {
        return $this->queryRepo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->queryRepo->count($filters);
    }

    public function find(int $id, int $tenantId, int $entityId): ?array
    {
        return $this->queryRepo->find($id, $tenantId, $entityId);
    }

    public function findByEntityAndProduct(int $entityId, int $productId, int $tenantId): ?array
    {
        return $this->queryRepo->findByEntityAndProduct($entityId, $productId, $tenantId);
    }

    public function getEntityProducts(int $entityId, string $lang = 'ar', ?int $tenantId = null): array
    {
        return $this->queryRepo->getEntityProducts($entityId, $lang, $tenantId);
    }

    public function getStatistics(?int $tenantId = null): array
    {
        return $this->queryRepo->getStatistics($tenantId);
    }

    // ────────────────────────────────────────────────────────────────────
    // Write Operations (Delegated to WriteRepository)
    // ────────────────────────────────────────────────────────────────────

    public function save(array $data): int
    {
        return $this->writeRepo->save($data);
    }

    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        return $this->writeRepo->saveEntityProducts($entityId, $tenantId, $products);
    }

    public function delete(int $id, int $tenantId, int $entityId): bool
    {
        return $this->writeRepo->delete($id, $tenantId, $entityId);
    }

    public function deleteEntityProducts(int $entityId, int $tenantId): bool
    {
        return $this->writeRepo->deleteEntityProducts($entityId, $tenantId);
    }
}