<?php
declare(strict_types=1);

final class PdoCartEventsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'cart_id', 'event_type', 'actor_type', 'created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a single cart event by ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cart_events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Count cart events matching filters.
     * 
     * @param string[] $where  Safe WHERE clause fragments (e.g. ['1=1', 'ce.cart_id = :cart_id'])
     * @param array    $params Named parameters for the WHERE fragments
     */
    public function count(array $where, array $params): int
    {
        $whereStr = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM cart_events ce WHERE {$whereStr}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * List cart events with filters, ordering, and pagination.
     * 
     * @param string[] $where  Safe WHERE clause fragments (e.g. ['1=1', 'ce.cart_id = :cart_id'])
     * @param array    $params Named parameters for the WHERE fragments
     */
    public function list(array $where, array $params, string $orderBy, string $orderDir, int $limit, int $offset): array
    {
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $whereStr = implode(' AND ', $where);

        $sql = "SELECT ce.* FROM cart_events ce WHERE {$whereStr} ORDER BY ce.{$orderBy} {$orderDir} LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert a new cart event. Returns the new ID.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cart_events (entity_id, cart_id, event_type, actor_type, actor_id, related_item_id, old_value, new_value, note)
            VALUES (:entity_id, :cart_id, :event_type, :actor_type, :actor_id, :related_item_id, :old_value, :new_value, :note)
        ");
        $stmt->execute([
            ':entity_id'       => $data['entity_id'],
            ':cart_id'         => $data['cart_id'],
            ':event_type'      => $data['event_type'],
            ':actor_type'      => $data['actor_type'],
            ':actor_id'        => $data['actor_id'] ?? null,
            ':related_item_id' => $data['related_item_id'] ?? null,
            ':old_value'       => $data['old_value'] ?? null,
            ':new_value'       => $data['new_value'] ?? null,
            ':note'            => $data['note'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a cart event by ID.
     */
    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM cart_events WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
