<?php
declare(strict_types=1);

final class AuctionsService
{
    private PdoAuctionsRepository $repo;

    public const WHITELISTED_COLUMNS = [
        'entity_id', 'product_id', 'title', 'slug', 'auction_type',
        'status', 'starting_price', 'reserve_price', 'current_price',
        'buy_now_price', 'bid_increment', 'currency_id', 'auto_bid_enabled',
        'start_date', 'end_date', 'auto_extend', 'extend_minutes',
        'min_extend_bid_time', 'is_featured', 'condition_type',
        'quantity', 'shipping_cost', 'payment_deadline_hours', 'notes'
    ];

    public function __construct(PdoAuctionsRepository $repo)
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
            throw new ApplicationException('Auction not found');
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

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));
        $whitelisted['id'] = (int)$data['id']; // preserve id for validator and repository save

        $this->validate($whitelisted, true);
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    private function validate(array $data, bool $isUpdate): void
    {
        $validator = new AuctionsValidator();
        if (!$validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
    }
}

