<?php
declare(strict_types=1);

interface HomepageSectionsRepositoryInterface
{
    public function all(int $tenantId, ?string $sectionType = null, ?int $themeId = null, string $lang = 'en'): array;
    public function find(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): ?array;
    public function save(int $tenantId, array $data, ?int $userId = null): int;
    public function delete(int $tenantId, int $id, ?int $userId = null): bool;
    public function getSectionTypes(int $tenantId): array;
    public function getActiveSections(int $tenantId, string $lang = 'en', ?int $themeId = null): array;
    public function saveTranslations(int $sectionId, array $translations): void;
    public function getTranslations(int $sectionId): array;
}