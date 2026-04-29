<?php
declare(strict_types=1);

/**
 * Ad Stat Service
 */
final class AdStatService
{
    private PdoAdStatRepository $repo;
    private AdStatValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'ad_id', 'user_id', 'session_id', 'ip_address', 'user_agent', 'views', 'clicks', 'event_type', 'date', 'id'
    ];

    public function __construct(PdoAdStatRepository $repo, AdStatValidator $validator)
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
        $stat = $this->repo->findById($id);
        if (!$stat) {
            return ['success' => false, 'errors' => ['Ad stat not found']];
        }
        return ['success' => true, 'data' => $stat];
    }

    public function create(array $data): array
    {
        // whitelist allowed fields to prevent mass assignment
        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $id = $this->repo->create($whitelisted);
        return ['success' => true, 'id' => $id];
    }

    public function delete(int $id): array
    {
        $deleted = $this->repo->delete($id);
        if (!$deleted) {
            return ['success' => false, 'errors' => ['Ad stat not found']];
        }
        return ['success' => true];
    }

    public function aggregate(array $params): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate   = $params['end_date'] ?? date('Y-m-d');
        $tenantId  = isset($params['tenant_id']) && $params['tenant_id'] !== '' ? (int) $params['tenant_id'] : null;
        $groupBy   = $params['group_by'] ?? 'day';

        $metrics    = $this->repo->aggregate($startDate, $endDate, $tenantId);
        $timeSeries = $this->repo->timeSeries($startDate, $endDate, $tenantId, $groupBy);
        $topAds     = $this->repo->topAds($startDate, $endDate, $tenantId);

        return [
            'success'     => true,
            'metrics'     => $metrics,
            'time_series' => $timeSeries,
            'top_ads'     => $topAds,
        ];
    }
}
