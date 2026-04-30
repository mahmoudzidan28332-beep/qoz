<?php
declare(strict_types=1);

final class PdoAuctionBidsRepository implements AuctionBidsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auction_bids';
    private const ALLOWED_ORDER_BY = ['id', 'bid_amount', 'bid_type', 'is_winning', 'created_at'];
    private const ALLOWED_COLUMNS = [
        'auction_id', 'user_id', 'bid_amount', 'max_auto_bid',
        'bid_type', 'is_winning', 'is_auto_outbid', 'ip_address', 'user_agent'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $auctionId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql    = "SELECT id, auction_id, user_id, bid_amount, max_auto_bid, bid_type, is_winning, is_auto_outbid, ip_address, user_agent, created_at FROM " . self::TABLE . " WHERE auction_id = :auction_id";
        $params = [':auction_id' => $auctionId];

        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (isset($filters['bid_type']) && $filters['bid_type'] !== '') {
            $sql .= " AND bid_type = :bid_type";
            $params[':bid_type'] = $filters['bid_type'];
        }

        if (isset($filters['is_winning'])) {
            $sql .= " AND is_winning = :is_winning";
            $params[':is_winning'] = (int)$filters['is_winning'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $auctionId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE auction_id = :auction_id";
        $params = [':auction_id' => $auctionId];

        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (isset($filters['bid_type']) && $filters['bid_type'] !== '') {
            $sql .= " AND bid_type = :bid_type";
            $params[':bid_type'] = $filters['bid_type'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, auction_id, user_id, bid_amount, max_auto_bid, bid_type, is_winning, is_auto_outbid, ip_address, user_agent, created_at FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (auction_id, user_id, bid_amount, max_auto_bid, bid_type, is_winning, is_auto_outbid, ip_address, user_agent)
            VALUES (:auction_id, :user_id, :bid_amount, :max_auto_bid, :bid_type, :is_winning, :is_auto_outbid, :ip_address, :user_agent)
        ");
        $stmt->execute([
            ':auction_id'    => (int)$data['auction_id'],
            ':user_id'       => (int)$data['user_id'],
            ':bid_amount'    => $data['bid_amount'],
            ':max_auto_bid'  => $data['max_auto_bid'] ?? null,
            ':bid_type'      => $data['bid_type'] ?? 'manual',
            ':is_winning'    => isset($data['is_winning']) ? (int)$data['is_winning'] : 0,
            ':is_auto_outbid'=> isset($data['is_auto_outbid']) ? (int)$data['is_auto_outbid'] : 0,
            ':ip_address'    => $data['ip_address'] ?? null,
            ':user_agent'    => $data['user_agent'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // Public-route helpers
    // =========================================================================

    /** Lock auction row for update (bid race-condition prevention). */
    public function lockForUpdate(int $auctionId): void
    {
        $this->pdo->prepare('SELECT id FROM auctions /* tenant_id scoped via caller */ WHERE id=? FOR UPDATE')->execute([$auctionId]);
    }

    /** Clear winning flag on all bids for an auction. */
    public function clearWinningBids(int $auctionId): void
    {
        $this->pdo->prepare('UPDATE auction_bids SET is_winning=0 WHERE auction_id=? AND is_winning=1')->execute([$auctionId]);
    }

    /** Insert a manual bid and return the new bid ID. */
    public function insertManualBid(int $auctionId, int $userId, float $amount, ?string $ip): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auction_bids (auction_id, user_id, bid_amount, bid_type, is_winning, ip_address, created_at) VALUES (?,?,?,?,1,?,NOW())'
        );
        $stmt->execute([$auctionId, $userId, $amount, 'manual', $ip]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Update auction price, bid counts and winner after a bid. */
    public function updateAuctionAfterBid(int $auctionId, float $price, int $winnerUserId, int $winnerBidId): void
    {
        $this->pdo->prepare(
            'UPDATE auctions SET current_price=?, total_bids=total_bids+1,
             total_bidders=(SELECT COUNT(DISTINCT user_id) FROM auction_bids WHERE auction_id=?),
             winner_user_id=?, winner_bid_id=? WHERE id=?'
        )->execute([$price, $auctionId, $winnerUserId, $winnerBidId, $auctionId]);
    }

    /** Insert a buy-now bid and return the new bid ID. */
    public function insertBuyNowBid(int $auctionId, int $userId, float $amount): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auction_bids (auction_id, user_id, bid_amount, bid_type, is_winning, created_at) VALUES (?,?,?,?,1,NOW())'
        );
        $stmt->execute([$auctionId, $userId, $amount, 'buy_now']);
        return (int)$this->pdo->lastInsertId();
    }

    /** Mark auction as sold after buy-now. */
    public function markAsSold(int $auctionId, float $price, int $winnerUserId, int $winnerBidId): void
    {
        $this->pdo->prepare(
            "UPDATE auctions SET status='sold', current_price=?, winner_user_id=?, winner_bid_id=?, winning_amount=?, ended_at=NOW() WHERE id=?"
        )->execute([$price, $winnerUserId, $winnerBidId, $price, $auctionId]);
    }

    /** Insert auction order and return the new order ID. */
    public function insertAuctionOrder(int $tenantId, int $entityId, string $orderNum, int $userId, int $auctionId, float $amount, ?string $ip): int
    {
        $this->pdo->prepare(
            "INSERT INTO orders (tenant_id, entity_id, order_number, user_id, cart_id, auction_id,
                order_type, status, payment_status, subtotal, total_amount, grand_total,
                currency_code, ip_address)
             VALUES (?,?,?,?,NULL,?,'online','pending','pending',?,?,?,'SAR',?)"
        )->execute([
            $tenantId, $entityId, $orderNum, $userId, $auctionId,
            $amount, $amount, $amount, $ip,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Insert order item for an auction product. */
    public function insertAuctionOrderItem(int $tenantId, int $orderId, int $entityId, int $productId, string $name, string $sku, float $price): void
    {
        $this->pdo->prepare(
            "INSERT INTO order_items (tenant_id, order_id, entity_id, product_id, product_name, sku, quantity, unit_price, subtotal, total)
             VALUES (?,?,?,?,?,?,1,?,?,?)"
        )->execute([$tenantId, $orderId, $entityId, $productId, $name, $sku, $price, $price, $price]);
    }

    /** Insert payment record for an auction purchase. */
    public function insertAuctionPayment(int $entityId, string $paymentNum, int $orderId, int $userId, float $amount, ?string $ip): void
    {
        $this->pdo->prepare(
            "INSERT INTO payments (entity_id, payment_number, order_id, user_id, payment_method,
                amount, currency_code, status, payment_type, ip_address, created_at, updated_at)
             VALUES (?,?,?,?,'buy_now',?,'SAR','pending','order',?,NOW(),NOW())"
        )->execute([$entityId, $paymentNum, $orderId, $userId, $amount, $ip]);
    }

    /** Cancel an order (for expire flow). */
    public function cancelOrder(int $orderId): void
    {
        $this->pdo->prepare(
            "UPDATE orders SET status='cancelled', cancellation_reason='Payment deadline expired', cancelled_at=NOW() WHERE id=?"
        )->execute([$orderId]);
    }

    /** Update auction winner and winning amount. */
    public function updateWinner(int $auctionId, ?int $winnerUserId, ?float $winningAmount): void
    {
        $this->pdo->prepare(
            "UPDATE auctions SET winner_user_id=?, winning_amount=? WHERE id=?"
        )->execute([$winnerUserId, $winningAmount, $auctionId]);
    }

    /** Insert a new expire-transfer order and return its ID. */
    public function insertExpireOrder(int $tenantId, int $entityId, string $orderNum, int $userId, float $amount, ?string $ip): int
    {
        $this->pdo->prepare(
            "INSERT INTO orders (tenant_id, entity_id, order_number, user_id, auction_id, order_type, status, payment_status, subtotal, total_amount, grand_total, currency_code, ip_address) VALUES (?,?,?,?,NULL,'online','pending','pending',?,?,?,'SAR',?)"
        )->execute([$tenantId, $entityId, $orderNum, $userId, $amount, $amount, $amount, $ip]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Mark auction as ended with no winner. */
    public function markEndedNoWinner(int $auctionId): void
    {
        $this->pdo->prepare(
            "UPDATE auctions SET winner_user_id=NULL, winning_amount=NULL, status='ended' WHERE id=?"
        )->execute([$auctionId]);
    }
}