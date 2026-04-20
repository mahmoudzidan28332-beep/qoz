<?php
declare(strict_types=1);

trait DiscountsExclusionsTrait
{
    public function listExclusions(int $discountId): array
    {
        return $this->exclusions->listByDiscount($discountId);
    }

    public function createExclusion(int $discountId, int $excludedDiscountId): int
    {
        return $this->exclusions->create($discountId, $excludedDiscountId);
    }

    public function deleteExclusion(int $id): bool
    {
        return $this->exclusions->delete($id);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->redemptions->stats($discountId);
    }
}