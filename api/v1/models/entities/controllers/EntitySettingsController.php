<?php
declare(strict_types=1);

final class EntitySettingsController
{
    private EntitySettingsService $service;

    public function __construct(EntitySettingsService $service)
    {
        $this->service = $service;
    }

    /**
     * List entity settings with filters, ordering, and pagination
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @return array
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'entity_id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    /**
     * Get a single entity setting by ID
     *
     * @param int $entityId
     * @return array|null
     */
    public function get(int $entityId, int $tenantId): ?array
    {
        return $this->service->get($entityId, $tenantId);
    }

    /**
     * Get tenant ID by entity ID
     *
     * @param int $entityId
     * @return int|null
     */
    public function getTenantIdByEntityId(int $entityId): ?int
    {
        return $this->service->getTenantIdByEntityId($entityId);
    }

    /**
     * Create new entity settings
     *
     * @param int $entityId
     * @param array $data
     * @return bool
     */
    public function create(int $entityId, int $tenantId, array $data): bool
    {
        return $this->service->create($entityId, $tenantId, $data);
    }

    /**
     * Update existing entity settings
     *
     * @param int $entityId
     * @param array $data
     * @return bool
     */
    public function update(int $entityId, int $tenantId, array $data): bool
    {
        return $this->service->update($entityId, $tenantId, $data);
    }

    /**
     * Delete entity settings by ID
     *
     * @param int $entityId
     * @return bool
     */
    public function delete(int $entityId, int $tenantId): bool
    {
        return $this->service->delete($entityId, $tenantId);
    }

    /**
     * Toggle boolean field value
     *
     * @param int $entityId
     * @param string $field
     * @return bool
     */
    public function toggle(int $entityId, int $tenantId, string $field): bool
    {
        return $this->service->toggle($entityId, $tenantId, $field);
    }
}
