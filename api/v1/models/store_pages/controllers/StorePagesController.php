<?php
declare(strict_types=1);

// api/v1/models/store_pages/controllers/StorePagesController.php

final class StorePagesController
{
    private StorePagesService $service;

    public function __construct(StorePagesService $service)
    {
        $this->service = $service;
    }

    // =========================================================
    // Pages
    // =========================================================

    public function listPages(int $tenantId, ?int $entityId = null): array
    {
        return $this->service->listPages($tenantId, $entityId);
    }

    public function getPage(int $tenantId, int $id): array
    {
        return $this->service->getPage($tenantId, $id);
    }

    public function getPageByType(int $tenantId, string $type, ?int $entityId = null): array
    {
        return $this->service->getPageByType($tenantId, $type, $entityId);
    }

    public function createPage(int $tenantId, array $data, ?int $userId = null): array
    {
        return $this->service->savePage($tenantId, $data, $userId);
    }

    public function updatePage(int $tenantId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Page ID is required for update');
        }

        return $this->service->savePage($tenantId, $data, $userId);
    }

    public function deletePage(int $tenantId, int $id, ?int $userId = null): void
    {
        $this->service->deletePage($tenantId, $id, $userId);
    }

    // =========================================================
    // Sections
    // =========================================================

    public function listSections(int $pageId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->listSections($pageId, $lang);
    }

    public function getSection(int $pageId, int $sectionId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->getSection($pageId, $sectionId, $lang, $allTranslations);
    }

    public function createSection(int $pageId, array $data, ?int $userId = null): array
    {
        return $this->service->saveSection($pageId, $data, $userId);
    }

    public function updateSection(int $pageId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Section ID is required for update');
        }

        return $this->service->saveSection($pageId, $data, $userId);
    }

    public function deleteSection(int $pageId, int $sectionId, ?int $userId = null): void
    {
        $this->service->deleteSection($pageId, $sectionId, $userId);
    }

    public function reorderSections(int $pageId, array $positions): void
    {
        $this->service->reorderSections($pageId, $positions);
    }

    // =========================================================
    // Translations
    // =========================================================

    public function getSectionTranslations(int $sectionId): array
    {
        return $this->service->getSectionTranslations($sectionId);
    }

    public function saveSectionTranslations(int $sectionId, array $translations): void
    {
        $this->service->saveSectionTranslations($sectionId, $translations);
    }
}