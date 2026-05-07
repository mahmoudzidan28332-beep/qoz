<?php
declare(strict_types=1);

final class ReturnsService
{
    private PdoReturnsRepository $repo;
    private ?PdoReturnStatusHistoryRepository $historyRepo;

    public const WHITELISTED_COLUMNS = [
        'order_id', 'user_id', 'entity_id', 'return_number', 'status',
        'reason', 'admin_notes', 'requested_at', 'processed_at', 'id'
    ];

    public function __construct(
        PdoReturnsRepository $repo,
        ?PdoReturnStatusHistoryRepository $historyRepo = null
    ) {
        $this->repo        = $repo;
        $this->historyRepo = $historyRepo;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): array
    {
        $data = $this->repo->find($tenantId, $id, $lang);
        if (!$data) {
            throw new ApplicationException('Return request not found');
        }
        return $data;
    }

    public function create(int $tenantId, array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validate($whitelisted, false);
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function update(int $tenantId, array $data, ?int $changedBy = null): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validate($whitelisted, true);
        $result = $this->repo->save($tenantId, $whitelisted);

        // Log status change to return_status_history
        if ($this->historyRepo !== null && isset($whitelisted['status'])) {
            try {
                $this->historyRepo->save($tenantId, [
                    'return_id'  => (int)$data['id'],
                    'status'     => $whitelisted['status'],
                    'changed_by' => $changedBy,
                    'notes'      => $whitelisted['admin_notes'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // History logging must never break the main operation; log for diagnostics
                if (function_exists('safe_log')) {
                    safe_log('warning', 'return_status_history_failed', [
                        'return_id' => $data['id'] ?? null,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    private function validate(array $data, bool $isUpdate): void
    {
        $validator = new ReturnsValidator();
        if (!$validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
    }
}