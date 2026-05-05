<?php

declare(strict_types=1);

require_once __DIR__ . '/EntityProductVariantQueryRepository.php';
require_once __DIR__ . '/EntityProductVariantWriteRepository.php';

/**
 * PdoEntityProductVariantsRepository
 *
 * Facade delegating to focused Query and Write repositories to adhere
 * to the Single Responsibility Principle.
 */
final class PdoEntityProductVariantsRepository
{
    private EntityProductVariantQueryRepository $queryRepo;
    private EntityProductVariantWriteRepository $writeRepo;

    public function __construct(private readonly PDO $pdo)
    {
        $this->queryRepo = new EntityProductVariantQueryRepository($pdo);
        $this->writeRepo = new EntityProductVariantWriteRepository($pdo, $this->queryRepo);
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

    public function findByEntityAndVariant(int $entityId, int $variantId, int $tenantId): ?array
    {
        return $this->queryRepo->findByEntityAndVariant($entityId, $variantId, $tenantId);
    }

    public function getEntityVariants(int $entityId, int $tenantId): array
    {
        return $this->queryRepo->getEntityVariants($entityId, $tenantId);
    }

    public function getEntityProductVariants(int $entityId, int $productId, int $tenantId): array
    {
        return $this->queryRepo->getEntityProductVariants($entityId, $productId, $tenantId);
    }

    public function getStatistics(int $tenantId): array
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

    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        return $this->writeRepo->saveEntityVariants($entityId, $tenantId, $variants);
    }

    public function delete(int $id, int $tenantId, int $entityId): bool
    {
        return $this->writeRepo->delete($id, $tenantId, $entityId);
    }

    public function deleteEntityVariants(int $entityId, int $tenantId): bool
    {
        return $this->writeRepo->deleteEntityVariants($entityId, $tenantId);
    }

    public function deleteEntityProductVariants(int $entityId, int $productId, int $tenantId): bool
    {
        return $this->writeRepo->deleteEntityProductVariants($entityId, $productId, $tenantId);
    }
}
