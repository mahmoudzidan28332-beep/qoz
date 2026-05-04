<?php
declare(strict_types=1);

require_once __DIR__ . '/DiscountsExclusionsTrait.php';

/**
 * Controller for core discount management.
 * Satisfies SRP by handling only main Discount CRUD and Exclusions.
 */
final class DiscountsController
{
    use DiscountsControllerExclusionsTrait;
    
    private $discountsService;

    public function __construct($discountsService)
    {
        $this->discountsService = $discountsService;
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
        return $this->discountsService->listDiscounts($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getDiscount(int $id, ?int $tenantId = null, bool $isSuperAdmin = false): ?array
    {
        return $this->discountsService->getDiscount($id, $tenantId, $isSuperAdmin);
    }

    public function createDiscount(array $data, ?int $tenantId = null, bool $isSuperAdmin = false): int
    {
        return $this->discountsService->createDiscount($data, $tenantId, $isSuperAdmin);
    }

    public function updateDiscount(int $id, array $data, ?int $tenantId = null, bool $isSuperAdmin = false): bool
    {
        return $this->discountsService->updateDiscount($id, $data, $tenantId, $isSuperAdmin);
    }

    public function deleteDiscount(int $id, ?int $tenantId = null, bool $isSuperAdmin = false): bool
    {
        return $this->discountsService->deleteDiscount($id, $tenantId, $isSuperAdmin);
    }

    public function discountStats(array $filters = []): array
    {
        return $this->discountsService->discountStats($filters);
    }
}
