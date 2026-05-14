<?php
declare(strict_types=1);

final class CartItemsService
{
    private PdoCartItemsRepository $repo;
    private ?CartEventLogger       $logger;

    public function __construct(PdoCartItemsRepository $repo, ?CartEventLogger $logger = null)
    {
        $this->repo   = $repo;
        $this->logger = $logger;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        return $this->repo->all(
            $tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang
        );
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    public function getByCart(int $tenantId, int $cartId): array
    {
        return $this->repo->findByCart($tenantId, $cartId);
    }

    public function create(int $tenantId, array $data): int
    {
        $newId = $this->repo->save($tenantId, $data);

        if ($this->logger !== null) {
            $item = $this->repo->find($tenantId, $newId);
            if ($item) {
                $cartRef = [
                    'id'        => (int)$item['cart_id'],
                    'entity_id' => (int)$item['entity_id'],
                ];
                $this->logger->log($cartRef, 'item_added', [
                    'related_item_id' => $newId,
                    'new_value'       => [
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ],
                ]);
            }
        }

        return $newId;
    }

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $oldItem = $this->logger !== null
            ? $this->repo->find($tenantId, (int)$data['id'])
            : null;

        $updatedId = $this->repo->save($tenantId, $data);

        if ($this->logger !== null && $oldItem) {
            $newItem = $this->repo->find($tenantId, $updatedId);
            if ($newItem) {
                $cartRef = [
                    'id'        => (int)$newItem['cart_id'],
                    'entity_id' => (int)$newItem['entity_id'],
                ];
                $oldQty = (int)$oldItem['quantity'];
                $newQty = (int)$newItem['quantity'];

                if ($oldQty !== $newQty) {
                    $this->logger->log($cartRef, 'quantity_updated', [
                        'related_item_id' => $updatedId,
                        'old_value'       => ['quantity' => $oldQty],
                        'new_value'       => ['quantity' => $newQty],
                    ]);
                }
            }
        }

        return $updatedId;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $item = $this->logger !== null
            ? $this->repo->find($tenantId, $id)
            : null;

        $result = $this->repo->delete($tenantId, $id);

        if ($this->logger !== null && $item && $result) {
            $cartRef = [
                'id'        => (int)$item['cart_id'],
                'entity_id' => (int)$item['entity_id'],
            ];
            $this->logger->log($cartRef, 'item_removed', [
                'related_item_id' => $id,
                'old_value'       => [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                ],
            ]);
        }

        return $result;
    }
}
