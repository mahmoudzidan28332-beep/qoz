<?php
declare(strict_types=1);

// api/v1/models/store_pages/services/StorePagesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoStorePagesRepository.php';
require_once __DIR__ . '/../validators/StorePagesValidator.php';

final class StorePagesService
{
    private PdoStorePagesRepository $repo;
    private StorePagesValidator $validator;

    public function __construct(
        PdoStorePagesRepository $repo,
        StorePagesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    // =========================================================
    // Pages
    // =========================================================

    public function listPages(int $tenantId, ?int $entityId = null): array
    {
        return $this->repo->allPages($tenantId, $entityId);
    }

    public function getPage(int $tenantId, int $id): array
    {
        $row = $this->repo->findPage($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Store page not found', 404);
        }

        return $row;
    }

    public function getPageByType(int $tenantId, string $type, ?int $entityId = null): array
    {
        $row = $this->repo->findPageByType($tenantId, $type, $entityId);
        if (!$row) {
            throw new RuntimeException('Store page not found', 404);
        }

        return $row;
    }

    public function savePage(int $tenantId, array $data, ?int $userId = null): array
    {
        $errors = StorePagesValidator::validatePage($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->savePage($tenantId, $data, $userId);

        $row = $this->repo->findPage($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved store page');
        }

        return $row;
    }

    public function deletePage(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->deletePage($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete store page');
        }
    }

    // =========================================================
    // Sections
    // =========================================================

    public function listSections(int $pageId, string $lang = 'en'): array
    {
        return $this->repo->allSections($pageId, $lang);
    }

    public function getSection(int $pageId, int $sectionId, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->findSection($pageId, $sectionId, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Store section not found', 404);
        }

        return $row;
    }

    public function saveSection(int $pageId, array $data, ?int $userId = null): array
    {
        $errors = StorePagesValidator::validateSection($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->saveSection($pageId, $data, $userId);

        $row = $this->repo->findSection($pageId, $id, 'en', true);
        if (!$row) {
            throw new RuntimeException('Failed to load saved store section');
        }

        return $row;
    }

    public function deleteSection(int $pageId, int $sectionId, ?int $userId = null): void
    {
        if (!$this->repo->deleteSection($pageId, $sectionId, $userId)) {
            throw new RuntimeException('Failed to delete store section');
        }
    }

    public function reorderSections(int $pageId, array $positions): void
    {
        $this->repo->reorderSections($pageId, $positions);
    }

    // =========================================================
    // Translations
    // =========================================================

    public function saveSectionTranslations(int $sectionId, array $translations): void
    {
        $this->repo->saveSectionTranslations($sectionId, $translations);
    }

    public function getSectionTranslations(int $sectionId): array
    {
        return $this->repo->getSectionTranslations($sectionId);
    }

    // =========================================================
    // Direct lookups (nullable returns for route-level checks)
    // =========================================================

    public function findSectionByIdOnly(int $sectionId): ?array
    {
        return $this->repo->findSectionByIdOnly($sectionId);
    }

    public function findSection(int $pageId, int $sectionId, string $lang = 'en', bool $allTranslations = false): ?array
    {
        return $this->repo->findSection($pageId, $sectionId, $lang, $allTranslations);
    }
}