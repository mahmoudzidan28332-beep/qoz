<?php
declare(strict_types=1);

/**
 * Service layer for discount sub-resources (Translations, Scopes, Conditions, Actions, Redemptions).
 */
final class DiscountSubResourceService
{
    private PdoDiscountTranslationsRepository $translations;
    private PdoDiscountScopesRepository $scopes;
    private PdoDiscountConditionsRepository $conditions;
    private PdoDiscountActionsRepository $actions;
    private PdoDiscountRedemptionsRepository $redemptions;

    public function __construct(PDO $pdo)
    {
        $this->translations = new PdoDiscountTranslationsRepository($pdo);
        $this->scopes       = new PdoDiscountScopesRepository($pdo);
        $this->conditions   = new PdoDiscountConditionsRepository($pdo);
        $this->actions      = new PdoDiscountActionsRepository($pdo);
        $this->redemptions  = new PdoDiscountRedemptionsRepository($pdo);
    }

    // ================================
    // Translations
    // ================================

    public function listTranslations(int $discountId): array
    {
        return $this->translations->listByDiscount($discountId);
    }

    public function findTranslation(int $id): ?array
    {
        return $this->translations->find($id);
    }

    public function upsertTranslation(int $discountId, string $langCode, array $data): int
    {
        return $this->translations->upsert($discountId, $langCode, $data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->translations->delete($id);
    }

    public function deleteTranslationsByDiscount(int $discountId): bool
    {
        return $this->translations->deleteByDiscount($discountId);
    }

    // ================================
    // Scopes
    // ================================

    public function listScopes(int $discountId): array
    {
        return $this->scopes->listByDiscount($discountId);
    }

    public function createScope(array $data): int
    {
        return $this->scopes->create($data);
    }

    public function deleteScope(int $id): bool
    {
        return $this->scopes->delete($id);
    }

    // ================================
    // Conditions
    // ================================

    public function listConditions(int $discountId): array
    {
        return $this->conditions->listByDiscount($discountId);
    }

    public function createCondition(array $data): int
    {
        return $this->conditions->create($data);
    }

    public function updateCondition(int $id, array $data): bool
    {
        return $this->conditions->update($id, $data);
    }

    public function deleteCondition(int $id): bool
    {
        return $this->conditions->delete($id);
    }

    // ================================
    // Actions
    // ================================

    public function listActions(int $discountId): array
    {
        return $this->actions->listByDiscount($discountId);
    }

    public function createAction(array $data): int
    {
        return $this->actions->create($data);
    }

    public function updateAction(int $id, array $data): bool
    {
        return $this->actions->update($id, $data);
    }

    public function deleteAction(int $id): bool
    {
        return $this->actions->delete($id);
    }

    // ================================
    // Redemptions
    // ================================

    public function listRedemptions(int $discountId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->redemptions->listByDiscount($discountId, $limit, $offset);
    }

    public function createRedemption(array $data): int
    {
        return $this->redemptions->create($data);
    }

    public function deleteRedemption(int $id): bool
    {
        return $this->redemptions->delete($id);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->redemptions->stats($discountId);
    }
}
