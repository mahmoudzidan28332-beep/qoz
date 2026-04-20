<?php
declare(strict_types=1);

// api/v1/models/themes/services/ThemesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoThemesRepository.php';
require_once __DIR__ . '/../validators/ThemesValidator.php';

final class ThemesService
{
    private PdoThemesRepository $repo;
    private ThemesValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'name', 'slug', 'description', 'thumbnail_url', 'preview_url',
        'version', 'author', 'is_active', 'is_default', 'id'
    ];

    public function __construct(
        PdoThemesRepository $repo,
        ThemesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, bool $activeOnly = false): array
    {
        return $this->repo->all($tenantId, $activeOnly);
    }

    public function get(int $tenantId, string $slug): array
    {
        $row = $this->repo->find($tenantId, $slug);
        if (!$row) {
            throw new RuntimeException('Theme not found');
        }

        return $row;
    }

    public function getActive(int $tenantId): array
    {
        $row = $this->repo->getActive($tenantId);
        if (!$row) {
            throw new RuntimeException('No active theme found');
        }

        return $row;
    }

    public function getDefault(int $tenantId): array
    {
        $row = $this->repo->getDefault($tenantId);
        if (!$row) {
            throw new RuntimeException('No default theme found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data): array
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $errors = $this->validator->validate($whitelisted);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($tenantId, $whitelisted);

        if (isset($whitelisted['id'])) {
            $row = $this->repo->findById($tenantId, $id);
        } else {
            $row = $this->repo->find($tenantId, $whitelisted['slug']);
        }

        if (!$row) {
            throw new RuntimeException('Failed to load saved theme');
        }

        return $row;
    }

    public function delete(int $tenantId, string $slug): void
    {
        $this->repo->delete($tenantId, $slug);
    }

    public function deleteById(int $tenantId, int $id): void
    {
        $this->repo->deleteById($tenantId, $id);
    }

    public function activate(int $tenantId, string $slug): void
    {
        if (!$this->repo->activate($tenantId, $slug)) {
            throw new RuntimeException('Failed to activate theme');
        }
    }

    public function setDefault(int $tenantId, string $slug): void
    {
        if (!$this->repo->setDefault($tenantId, $slug)) {
            throw new RuntimeException('Failed to set default theme');
        }
    }
}