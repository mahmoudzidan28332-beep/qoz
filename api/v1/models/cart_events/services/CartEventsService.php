<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoCartEventsRepository.php';

final class CartEventsService
{
    private PdoCartEventsRepository $repo;

    public function __construct(PdoCartEventsRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Find a single cart event by ID.
     */
    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Count cart events matching filters.
     *
     * @param string[] $where
     * @param array    $params
     */
    public function count(array $where, array $params): int
    {
        return $this->repo->count($where, $params);
    }

    /**
     * List cart events with filters, ordering, and pagination.
     *
     * @param string[] $where
     * @param array    $params
     */
    public function list(array $where, array $params, string $orderBy, string $orderDir, int $limit, int $offset): array
    {
        return $this->repo->list($where, $params, $orderBy, $orderDir, $limit, $offset);
    }

    /**
     * Insert a new cart event. Returns the new ID.
     */
    public function create(array $data): int
    {
        return $this->repo->create($data);
    }

    /**
     * Delete a cart event by ID.
     */
    public function deleteById(int $id): bool
    {
        return $this->repo->deleteById($id);
    }
}
