<?php
declare(strict_types=1);

/**
 * Core Event Controller
 */
final class CoreEventController
{
    private CoreEventService $service;

    public function __construct(CoreEventService $service)
    {
        $this->service = $service;
    }

    public function list(array $filters, int $limit, int $offset, string $orderBy, string $orderDir): array
    {
        return $this->service->list($filters, $limit, $offset, $orderBy, $orderDir);
    }

    public function findById(int $id): array
    {
        return $this->service->findById($id);
    }

    public function create(array $data): array
    {
        return $this->service->create($data);
    }

    public function delete(int $id): array
    {
        return $this->service->delete($id);
    }

    public function aggregate(array $params): array
    {
        return $this->service->aggregate($params);
    }
}
