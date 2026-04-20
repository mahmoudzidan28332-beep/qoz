<?php
declare(strict_types=1);

trait DiscountsExclusionsTrait
{
    public function listExclusions(int $discountId): array
    {
        return $this->service->listExclusions($discountId);
    }

    public function createExclusion(int $discountId, int $excludedDiscountId): int
    {
        return $this->service->createExclusion($discountId, $excludedDiscountId);
    }

    public function deleteExclusion(int $id): bool
    {
        return $this->service->deleteExclusion($id);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->service->redemptionStats($discountId);
    }
}