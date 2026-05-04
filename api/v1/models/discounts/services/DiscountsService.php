<?php
declare(strict_types=1);

require_once __DIR__ . '/DiscountsExclusionsTrait.php';

/**
 * Service layer for core discount management.
 */
final class DiscountsService
{
    use DiscountsServiceExclusionsTrait;
    private $discounts;
    private $exclusions;

    public function __construct($pdo)
    {
        $this->discounts    = new PdoDiscountsRepository($pdo);
        $this->exclusions   = new PdoDiscountExclusionsRepository($pdo);
    }

    // ================================
    // Discounts CRUD
    // ================================

    public function listDiscounts(
        ?int $limit = 25,
        ?int $offset = 0,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->discounts->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getDiscount(int $id, ?int $tenantId = null, bool $isSuperAdmin = false): ?array
    {
        return $this->discounts->findAccessible($id, $tenantId, $isSuperAdmin);
    }

    public function createDiscount(array $data, ?int $tenantId = null, bool $isSuperAdmin = false): int
    {
        if (!$isSuperAdmin && $tenantId !== null && $tenantId > 0) {
            $data['tenant_id'] = $tenantId;
            $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
            if ($entityId > 0 && !$this->discounts->entityBelongsToTenant($entityId, $tenantId)) {
                throw new ApplicationException('Access denied to selected entity', 403);
            }
        }

        return $this->discounts->create($data);
    }

    public function updateDiscount(int $id, array $data, ?int $tenantId = null, bool $isSuperAdmin = false): bool
    {
        $existing = $this->discounts->findAccessible($id, $tenantId, $isSuperAdmin);
        if (!$existing) {
            throw new ApplicationException("Discount not found with ID: $id");
        }

        if (!$isSuperAdmin && $tenantId !== null && $tenantId > 0) {
            $data['tenant_id'] = $tenantId;
            if (array_key_exists('entity_id', $data) && $data['entity_id'] !== null && (int)$data['entity_id'] > 0) {
                if (!$this->discounts->entityBelongsToTenant((int)$data['entity_id'], $tenantId)) {
                    throw new ApplicationException('Access denied to selected entity', 403);
                }
            }
        }

        return $this->discounts->update($id, $data);
    }

    public function deleteDiscount(int $id, ?int $tenantId = null, bool $isSuperAdmin = false): bool
    {
        $existing = $this->discounts->findAccessible($id, $tenantId, $isSuperAdmin);
        if (!$existing) {
            throw new ApplicationException("Discount not found with ID: $id");
        }
        return $this->discounts->delete($id);
    }

    public function discountStats(array $filters = []): array
    {
        return $this->discounts->stats($filters);
    }
}
