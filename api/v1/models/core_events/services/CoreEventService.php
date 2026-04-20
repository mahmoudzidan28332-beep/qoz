<?php
declare(strict_types=1);

/**
 * Core Event Service
 */
final class CoreEventService
{
    private PdoCoreEventRepository $repo;
    private CoreEventValidator $validator;

    public function __construct(PdoCoreEventRepository $repo, CoreEventValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(array $filters, int $limit = 25, int $offset = 0, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $errors = $this->validator->validateFilters($filters);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $result = $this->repo->list($filters, $limit, $offset, $orderBy, $orderDir);
        return ['success' => true, 'data' => $result];
    }

    public function findById(int $id): array
    {
        $event = $this->repo->findById($id);
        if (!$event) {
            return ['success' => false, 'errors' => ['Event not found']];
        }
        return ['success' => true, 'data' => $event];
    }

    public function create(array $data): array
    {
        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $id = $this->repo->create($data);
        return ['success' => true, 'id' => $id];
    }

    public function delete(int $id): array
    {
        $deleted = $this->repo->delete($id);
        if (!$deleted) {
            return ['success' => false, 'errors' => ['Event not found']];
        }
        return ['success' => true];
    }

    public function aggregate(array $params): array
    {
        $entityType = $params['entity_type'] ?? 'product';
        $startDate  = ($params['start_date'] ?? date('Y-m-01')) . ' 00:00:00';
        $endDate    = ($params['end_date'] ?? date('Y-m-d')) . ' 23:59:59';
        $entityId   = isset($params['entity_id']) && $params['entity_id'] !== '' ? (int) $params['entity_id'] : null;

        if ($entityId !== null) {
            $metrics = $this->repo->aggregateByEntity($entityId, $entityType, $startDate, $endDate);
        } else {
            $metrics = $this->repo->aggregateByEntityType($entityType, $startDate, $endDate);
        }

        $groupBy = $params['group_by'] ?? 'day';
        $timeSeries = $this->repo->timeSeries($entityType, $startDate, $endDate, $groupBy);
        $topEntities = $this->repo->topEntities($entityType, 'view', $startDate, $endDate);

        return [
            'success'     => true,
            'metrics'     => $metrics,
            'time_series' => $timeSeries,
            'top_entities' => $topEntities,
        ];
    }
}
