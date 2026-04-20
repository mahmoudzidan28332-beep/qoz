<?php
declare(strict_types=1);

final class SupportTicketsService
{
    private PdoSupportTicketsRepository $repo;

    public const WHITELISTED_COLUMNS = [
        'user_id', 'entity_id', 'order_id', 'category_id', 'subject',
        'description', 'priority', 'status', 'assigned_to', 'attachments', 'id'
    ];

    public function __construct(PdoSupportTicketsRepository $repo)
    {
        $this->repo = $repo;
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
            throw new RuntimeException('Ticket not found');
        }
        return $data;
    }

    public function create(int $tenantId, array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validate($whitelisted, 'create');
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validate($whitelisted, 'update');
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    private function validate(array $data, string $scenario): void
    {
        $validator = new SupportTicketsValidator();
        if (!$validator->validate($data, $scenario)) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
    }
}