<?php
declare(strict_types=1);

/**
 * Controller for discount sub-resources (Translations, Scopes, Conditions, Actions, Redemptions).
 * Extracts logic from the main DiscountsController to satisfy SRP and avoid God Class issues.
 */
final class DiscountSubResourceController
{
    private DiscountSubResourceService $service;

    public function __construct(DiscountSubResourceService $service)
    {
        $this->service = $service;
    }

    // ================================
    // Translations
    // ================================

    public function listTranslations(int $discountId): array
    {
        return $this->service->listTranslations($discountId);
    }

    public function findTranslation(int $id): ?array
    {
        return $this->service->findTranslation($id);
    }

    public function upsertTranslation(int $discountId, string $langCode, array $data): int
    {
        return $this->service->upsertTranslation($discountId, $langCode, $data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->service->deleteTranslation($id);
    }

    public function deleteTranslationsByDiscount(int $discountId): bool
    {
        return $this->service->deleteTranslationsByDiscount($discountId);
    }

    // ================================
    // Scopes
    // ================================

    public function listScopes(int $discountId): array
    {
        return $this->service->listScopes($discountId);
    }

    public function createScope(array $data): int
    {
        return $this->service->createScope($data);
    }

    public function deleteScope(int $id): bool
    {
        return $this->service->deleteScope($id);
    }

    // ================================
    // Conditions
    // ================================

    public function listConditions(int $discountId): array
    {
        return $this->service->listConditions($discountId);
    }

    public function createCondition(array $data): int
    {
        return $this->service->createCondition($data);
    }

    public function updateCondition(int $id, array $data): bool
    {
        return $this->service->updateCondition($id, $data);
    }

    public function deleteCondition(int $id): bool
    {
        return $this->service->deleteCondition($id);
    }

    // ================================
    // Actions
    // ================================

    public function listActions(int $discountId): array
    {
        return $this->service->listActions($discountId);
    }

    public function createAction(array $data): int
    {
        return $this->service->createAction($data);
    }

    public function updateAction(int $id, array $data): bool
    {
        return $this->service->updateAction($id, $data);
    }

    public function deleteAction(int $id): bool
    {
        return $this->service->deleteAction($id);
    }

    // ================================
    // Redemptions
    // ================================

    public function listRedemptions(int $discountId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->service->listRedemptions($discountId, $limit, $offset);
    }

    public function createRedemption(array $data): int
    {
        return $this->service->createRedemption($data);
    }

    public function deleteRedemption(int $id): bool
    {
        return $this->service->deleteRedemption($id);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->service->redemptionStats($discountId);
    }
}
