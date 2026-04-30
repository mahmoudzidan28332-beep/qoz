<?php
declare(strict_types=1);

// api/v1/models/system_settings/services/SystemSettingsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoSystemSettingsRepository.php';
require_once __DIR__ . '/../validators/SystemSettingsValidator.php';

final class SystemSettingsService
{
    private PdoSystemSettingsRepository $repo;
    private SystemSettingsValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'setting_key', 'setting_value', 'setting_type', 'category',
        'description', 'is_public', 'is_editable', 'id'
    ];

    public function __construct(
        PdoSystemSettingsRepository $repo,
        SystemSettingsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?string $category = null): array
    {
        return $this->repo->all($tenantId, $category);
    }

    public function get(int $tenantId, string $key): array
    {
        $row = $this->repo->find($tenantId, $key);
        if (!$row) {
            throw new ApplicationException('Setting not found');
        }

        return $row;
    }

    public function getById(int $tenantId, int $id): array
    {
        $row = $this->repo->findById($tenantId, $id);
        if (!$row) {
            throw new ApplicationException('Setting not found');
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
            $row = $this->repo->find($tenantId, $whitelisted['setting_key']);
        }

        if (!$row) {
            throw new ApplicationException('Failed to load saved setting');
        }

        return $row;
    }

    public function delete(int $tenantId, string $key): void
    {
        $this->repo->delete($tenantId, $key);
    }

    public function deleteById(int $tenantId, int $id): void
    {
        $this->repo->deleteById($tenantId, $id);
    }

    public function getCategories(int $tenantId): array
    {
        return $this->repo->getCategories($tenantId);
    }

    public function getPublicSettings(int $tenantId): array
    {
        return $this->repo->getPublicSettings($tenantId);
    }
}