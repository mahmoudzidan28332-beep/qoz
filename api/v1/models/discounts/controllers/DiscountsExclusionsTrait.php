<?php
declare(strict_types=1);

trait DiscountsControllerExclusionsTrait
{
    public function listExclusions(int $discountId): array
    {
        return $this->discountsService->listExclusions($discountId);
    }

    public function createExclusion(int $discountId, int $excludedDiscountId): int
    {
        return $this->discountsService->createExclusion($discountId, $excludedDiscountId);
    }

    public function deleteExclusion(int $id): bool
    {
        return $this->discountsService->deleteExclusion($id);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->subResourceService->redemptionStats($discountId);
    }
}