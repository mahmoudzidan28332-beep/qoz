<?php
declare(strict_types=1);

// api/v1/models/homepage_sections/controllers/HomepageSectionsController.php

final class HomepageSectionsController
{
    private HomepageSectionsService $service;

    public function __construct(HomepageSectionsService $service)
    {
        $this->service = $service;
    }

    /** GET /homepage_sections */
    public function list(int $tenantId, ?int $userId = null): array
    {
        $sectionType = $_GET['section_type'] ?? null;
        $themeId = isset($_GET['theme_id']) ? (int) $_GET['theme_id'] : null;
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->list($tenantId, $sectionType, $themeId, $lang);
    }

    /** GET /homepage_sections/active */
    public function getActive(int $tenantId, ?int $userId = null): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $themeId = isset($_GET['theme_id']) ? (int) $_GET['theme_id'] : null;
        return $this->service->getActiveSections($tenantId, $lang, $themeId);
    }

    /** GET /homepage_sections/types */
    public function sectionTypes(int $tenantId): array
    {
        return $this->service->getSectionTypes($tenantId);
    }

    /** GET /homepage_sections/{id} */
    public function get(int $tenantId, int $id, ?int $userId = null): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($tenantId, $id, $lang, $allTranslations);
    }

    /** POST /homepage_sections */
    public function create(int $tenantId, array $data, ?int $userId = null): array
    {
        return $this->service->save($tenantId, $data, $userId);
    }

    /** PUT /homepage_sections/{id} */
    public function update(int $tenantId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        return $this->service->save($tenantId, $data, $userId);
    }

    /** DELETE /homepage_sections/{id} */
    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        $this->service->delete($tenantId, $id, $userId);
    }

    /** GET /homepage_sections/{id}/translations */
    public function translations(int $tenantId, int $id): array
    {
        return $this->service->getTranslations($tenantId, $id);
    }

    /** POST /homepage_sections/{id}/translations */
    public function saveTranslations(int $tenantId, int $id, array $translations, ?int $userId = null): array
    {
        return $this->service->saveTranslations($tenantId, $id, $translations, $userId);
    }

    /** GET /homepage_sections/languages */
    public function languages(): array
    {
        $langDir = $_SERVER['DOCUMENT_ROOT'] . '/languages/HomepageSections';
        $languages = [];
        if (is_dir($langDir)) {
            foreach (glob($langDir . '/*.json') as $file) {
                $code = basename($file, '.json');
                $languages[] = $code;
            }
        }
        return $languages;
    }
}