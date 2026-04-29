<?php
declare(strict_types=1);

final class BrandsController
{
    private BrandsService $service;

    public function __construct(BrandsService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /brands?page=1&limit=20&order_by=sort_order&order_dir=ASC&lang=en&filter[entity_id]=5&filter[is_active]=1
     */
    public function list(): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $orderBy = $_GET['order_by'] ?? 'sort_order';
        $orderDir = $_GET['order_dir'] ?? 'ASC';

        $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

        $filters = [];
        if (isset($_GET['filter']) && is_array($_GET['filter'])) {
            $filters = $_GET['filter'];
        }

        return $this->service->list($tenantId, $filters, $orderBy, $orderDir, $limit, $offset, $lang);
    }

    public function get(string $slug): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($slug, $lang, $allTranslations);
    }

    public function getById(int $id): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->getById($id, $lang, $allTranslations);
    }

    public function create(array $data): array
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id']) && empty($data['slug'])) {
            throw new InvalidArgumentException('Either id or slug is required for update');
        }
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById((int)$data['id'], $userId);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($data['slug'], $userId);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }
}