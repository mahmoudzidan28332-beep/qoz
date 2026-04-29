<?php
declare(strict_types=1);

// api/v1/models/themes/controllers/ThemesController.php

final class ThemesController
{
    private ThemesService $service;

    public function __construct(ThemesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, array $options = []): array
    {
        return $this->service->list($tenantId, $options);
    }

    public function get(int $tenantId, string $slug, array $options = []): array
    {
        return $this->service->get($tenantId, $slug, $options);
    }

    public function getActive(int $tenantId, array $options = []): array
    {
        return $this->service->getActive($tenantId, $options);
    }

    public function getDefault(int $tenantId, array $options = []): array
    {
        return $this->service->getDefault($tenantId, $options);
    }

    public function create(int $tenantId, array $data): array
    {
        return $this->service->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id']) && empty($data['slug'])) {
            throw new InvalidArgumentException('ID or slug is required');
        }

        return $this->service->save($tenantId, $data);
    }

    public function delete(int $tenantId, array $data, array $options = []): void
    {
        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id'], $options);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($tenantId, $data['slug'], $options);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }

    public function activate(int $tenantId, array $data, array $options = []): array
    {
        if (empty($data['slug'])) {
            throw new InvalidArgumentException('Slug is required');
        }

        $this->service->activate($tenantId, $data['slug'], $options);
        return ['success' => true, 'message' => 'Theme activated'];
    }

    public function setDefault(int $tenantId, array $data, array $options = []): array
    {
        if (empty($data['slug'])) {
            throw new InvalidArgumentException('Slug is required');
        }

        $this->service->setDefault($tenantId, $data['slug'], $options);
        return ['success' => true, 'message' => 'Theme set as default'];
    }
}
