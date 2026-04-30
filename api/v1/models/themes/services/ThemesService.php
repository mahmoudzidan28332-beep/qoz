<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoThemesRepository.php';
require_once __DIR__ . '/../validators/ThemesValidator.php';

final class ThemesService
{
    private PdoThemesRepository $repo;
    private ThemesValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'name', 'slug', 'description', 'thumbnail_url', 'preview_url',
        'version', 'author', 'is_active', 'is_default', 'id',
        'theme_scope', 'theme_target', 'tenant_id', 'owner_tenant_id'
    ];

    public function __construct(
        PdoThemesRepository $repo,
        ThemesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, array $options = []): array
    {
        return $this->repo->all($tenantId, $options);
    }

    public function get(int $tenantId, string $slug, array $options = []): array
    {
        $row = $this->repo->find($tenantId, $slug, $options);
        if (!$row) {
            throw new ApplicationException('Theme not found');
        }

        return $row;
    }

    public function getActive(int $tenantId, array $options = []): array
    {
        $row = $this->repo->getActive($tenantId, $options);
        if (!$row) {
            throw new ApplicationException('No active theme found');
        }

        return $row;
    }

    public function getDefault(int $tenantId, array $options = []): array
    {
        $row = $this->repo->getDefault($tenantId, $options);
        if (!$row) {
            throw new ApplicationException('No default theme found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data): array
    {
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $errors = $this->validator->validate($whitelisted);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $id = $this->repo->save($tenantId, $whitelisted);
        $lookupOptions = [
            'theme_target' => $whitelisted['theme_target'] ?? null,
            'theme_scope' => $whitelisted['theme_scope'] ?? null,
            'owner_tenant_id' => $whitelisted['owner_tenant_id'] ?? ($whitelisted['tenant_id'] ?? null),
        ];

        $row = $this->repo->findById($tenantId, $id, $lookupOptions);
        if (!$row) {
            throw new ApplicationException('Failed to load saved theme');
        }

        return $row;
    }

    public function delete(int $tenantId, string $slug, array $options = []): void
    {
        $this->repo->delete($tenantId, $slug, $options);
    }

    public function deleteById(int $tenantId, int $id, array $options = []): void
    {
        $this->repo->deleteById($tenantId, $id, $options);
    }

    public function activate(int $tenantId, string $slug, array $options = []): void
    {
        if (!$this->repo->activate($tenantId, $slug, $options)) {
            throw new ApplicationException('Failed to activate theme');
        }
    }

    public function setDefault(int $tenantId, string $slug, array $options = []): void
    {
        if (!$this->repo->setDefault($tenantId, $slug, $options)) {
            throw new ApplicationException('Failed to set default theme');
        }
    }
}
