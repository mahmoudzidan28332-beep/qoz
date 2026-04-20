<?php
declare(strict_types=1);

/**
 * Cities Controller - FIXED FOR TRANSLATION SUPPORT
 */

final class CitiesController
{
    private CitiesService $service;

    public function __construct(CitiesService $service)
    {
        $this->service = $service;
    }

    // ✅ إصلاح: استلام filters بدلاً من قراءة $_GET مباشرة
    public function list(array $filters = []): array
    {
        $lang = $filters['lang'] ?? 'en';
        $countryId = isset($filters['country_id']) ? (int)$filters['country_id'] : null;
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 20);

        return $this->service->list($lang, $countryId, $page, $perPage);
    }

    // ✅ إصلاح: استلام lang من parameters
    public function show(int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        return $this->service->get($id, $lang, $allTranslations);
    }

    public function findWithTranslation(string $identifier, ?string $lang = null): ?array
    {
        return $this->service->findWithTranslation($identifier, $lang);
    }

    public function create(array $data): array
    {
        // 🔒 SECURITY: Whitelist allowed fields (Mass Assignment Protection)
        $allowed = ['country_id', 'name', 'latitude', 'longitude', 'is_active'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        return $this->service->save($filtered);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        // 🔒 SECURITY: Whitelist allowed fields (Mass Assignment Protection)
        $allowed = ['id', 'country_id', 'name', 'latitude', 'longitude', 'is_active'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        return $this->service->save($filtered);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $this->service->delete((int) $data['id']);
    }
}
