<?php
declare(strict_types=1);

final class UserDevicesController
{
    private UserDevicesService $service;

    public function __construct(UserDevicesService $service)
    {
        $this->service = $service;
    }

    /**
     * List devices with pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->service->count($filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single device by ID
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Get devices for a user
     */
    public function getByUser(int $userId): array
    {
        return $this->service->getByUser($userId);
    }

    /**
     * Create a new device (or update if token exists)
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing device
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a device by ID
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Delete all devices of a user
     */
    public function deleteByUser(int $userId): bool
    {
        return $this->service->deleteByUser($userId);
    }

    /**
     * Update last_seen_at for a device
     */
    public function touch(int $id): bool
    {
        return $this->service->touch($id);
    }
}