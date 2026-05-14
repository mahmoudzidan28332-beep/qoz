<?php
declare(strict_types=1);

final class CartsService
{
    private PdoCartsRepository $repo;
    private ?CartEventLogger   $logger;

    public function __construct(PdoCartsRepository $repo, ?CartEventLogger $logger = null)
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

    public function getBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        return $this->repo->findBySession($tenantId, $sessionId, $entityId);
    }

    public function getByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        return $this->repo->findByUser($tenantId, $userId, $entityId);
    }

    public function create(int $tenantId, array $data): int
    {
        $newId = $this->repo->save($tenantId, $data);

        if ($this->logger !== null) {
            $cart = $this->repo->find($tenantId, $newId);
            if ($cart) {
                $this->logger->log($cart, 'cart_created', [
                    'new_value' => [
                        'status'    => $cart['status'],
                        'entity_id' => $cart['entity_id'],
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

        $oldCart = $this->logger !== null
            ? $this->repo->find($tenantId, (int)$data['id'])
            : null;

        $updatedId = $this->repo->save($tenantId, $data);

        if ($this->logger !== null && $oldCart) {
            $newCart = $this->repo->find($tenantId, $updatedId);
            if ($newCart) {
                $oldCoupon = $oldCart['coupon_code'] ?? null;
                $newCoupon = $newCart['coupon_code'] ?? null;

                if ($oldCoupon !== $newCoupon) {
                    if ($newCoupon !== null) {
                        $this->logger->log($newCart, 'coupon_applied', [
                            'old_value' => ['coupon_code' => $oldCoupon],
                            'new_value' => ['coupon_code' => $newCoupon],
                        ]);
                    } else {
                        $this->logger->log($newCart, 'coupon_removed', [
                            'old_value' => ['coupon_code' => $oldCoupon],
                            'new_value' => ['coupon_code' => null],
                        ]);
                    }
                } else {
                    $this->logger->log($newCart, 'cart_updated', [
                        'old_value' => [
                            'total_amount' => $oldCart['total_amount'],
                            'status'       => $oldCart['status'],
                        ],
                        'new_value' => [
                            'total_amount' => $newCart['total_amount'],
                            'status'       => $newCart['status'],
                        ],
                    ]);
                }
            }
        }

        return $updatedId;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $cart = $this->logger !== null
            ? $this->repo->find($tenantId, $id)
            : null;

        $result = $this->repo->delete($tenantId, $id);

        if ($this->logger !== null && $cart && $result) {
            $this->logger->log($cart, 'cart_expired', [
                'old_value' => ['status' => $cart['status']],
                'new_value' => ['status' => 'expired'],
            ]);
        }

        return $result;
    }

    public function convertToOrder(int $tenantId, int $cartId, int $orderId): bool
    {
        $cart = $this->logger !== null
            ? $this->repo->find($tenantId, $cartId)
            : null;

        $result = $this->repo->convertToOrder($tenantId, $cartId, $orderId);

        if ($this->logger !== null && $cart && $result) {
            $this->logger->log($cart, 'cart_converted', [
                'new_value' => ['order_id' => $orderId],
            ]);
        }

        return $result;
    }
}
