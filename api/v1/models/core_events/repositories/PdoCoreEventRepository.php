<?php
declare(strict_types=1);

/**
 * Core Events Repository
 * Handles CRUD and aggregation operations for the core_events table.
 * Tracks user interactions: view, click, favorite, contact, add_to_cart, purchase
 * across entity types: product, entity, brand, category, job, auction.
 */
final class PdoCoreEventRepository
{
    private PDO $pdo;

    private const ALLOWED_ENTITY_TYPES = ['product', 'entity', 'brand', 'category', 'job', 'auction'];
    private const ALLOWED_EVENT_TYPES  = ['view', 'click', 'favorite', 'contact', 'add_to_cart', 'purchase'];
    private const ALLOWED_ORDER_BY     = ['id', 'entity_type', 'entity_id', 'event_type', 'created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ════════════════════════════════════════════════════════════
    // LIST / PAGINATE
    // ════════════════════════════════════════════════════════════

    public function list(array $filters, int $limit = 25, int $offset = 0, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $where[] = 'ce.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[] = 'ce.entity_id = :entity_id';
            $params[':entity_id'] = (int) $filters['entity_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'ce.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'ce.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['session_id'])) {
            $where[] = 'ce.session_id = :session_id';
            $params[':session_id'] = $filters['session_id'];
        }
        if (!empty($filters['start_date'])) {
            $where[] = 'ce.created_at >= :start_date';
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'ce.created_at <= :end_date';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countSql = "SELECT COUNT(*) FROM core_events ce {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Data
        $sql = "SELECT ce.id, ce.entity_type, ce.entity_id, ce.user_id, ce.session_id, ce.event_type, ce.value, ce.ip_address, ce.user_agent, ce.created_at FROM core_events ce {$whereClause} ORDER BY ce.{$orderBy} {$orderDir} LIMIT :lmt OFFSET :ofs";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lmt', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':ofs', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['items' => $items, 'total' => $total];
    }

    // ════════════════════════════════════════════════════════════
    // FIND BY ID
    // ════════════════════════════════════════════════════════════

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, entity_type, entity_id, user_id, session_id, event_type, value, ip_address, user_agent, created_at FROM core_events WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO core_events (entity_type, entity_id, user_id, session_id, event_type, value, ip_address, user_agent)
             VALUES (:entity_type, :entity_id, :user_id, :session_id, :event_type, :value, :ip_address, :user_agent)'
        );
        $stmt->execute([
            ':entity_type' => $data['entity_type'],
            ':entity_id'   => (int) $data['entity_id'],
            ':user_id'     => isset($data['user_id']) ? (int) $data['user_id'] : null,
            ':session_id'  => $data['session_id'] ?? null,
            ':event_type'  => $data['event_type'],
            ':value'       => isset($data['value']) ? (float) $data['value'] : null,
            ':ip_address'  => $data['ip_address'] ?? null,
            ':user_agent'  => $data['user_agent'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM core_events WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════
    // AGGREGATIONS (for reports)
    // ════════════════════════════════════════════════════════════

    public function aggregateByEntityType(string $entityType, string $startDate, string $endDate): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN ce.event_type = 'view' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN ce.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN ce.event_type = 'favorite' THEN 1 ELSE 0 END) AS favorites,
                    SUM(CASE WHEN ce.event_type = 'contact' THEN 1 ELSE 0 END) AS contacts,
                    SUM(CASE WHEN ce.event_type = 'add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart,
                    SUM(CASE WHEN ce.event_type = 'purchase' THEN 1 ELSE 0 END) AS purchases,
                    COUNT(*) AS total_events,
                    COUNT(DISTINCT ce.entity_id) AS unique_entities,
                    COUNT(DISTINCT ce.user_id) AS unique_users
                FROM core_events ce
                WHERE ce.entity_type = :et
                  AND ce.created_at BETWEEN :s AND :e";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':et' => $entityType, ':s' => $startDate, ':e' => $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function aggregateByEntity(int $entityId, string $entityType, string $startDate, string $endDate): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN ce.event_type = 'view' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN ce.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN ce.event_type = 'favorite' THEN 1 ELSE 0 END) AS favorites,
                    SUM(CASE WHEN ce.event_type = 'contact' THEN 1 ELSE 0 END) AS contacts,
                    SUM(CASE WHEN ce.event_type = 'add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart,
                    SUM(CASE WHEN ce.event_type = 'purchase' THEN 1 ELSE 0 END) AS purchases,
                    COUNT(*) AS total_events
                FROM core_events ce
                WHERE ce.entity_type = :et
                  AND ce.entity_id = :eid
                  AND ce.created_at BETWEEN :s AND :e";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':et' => $entityType, ':eid' => $entityId, ':s' => $startDate, ':e' => $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function timeSeries(string $entityType, string $startDate, string $endDate, string $groupBy = 'day'): array
    {
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(ce.created_at, '{$dateFormat}') AS period,
                    SUM(CASE WHEN ce.event_type = 'view' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN ce.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN ce.event_type = 'favorite' THEN 1 ELSE 0 END) AS favorites,
                    SUM(CASE WHEN ce.event_type = 'add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart,
                    SUM(CASE WHEN ce.event_type = 'purchase' THEN 1 ELSE 0 END) AS purchases,
                    COUNT(*) AS total_events
                FROM core_events ce
                WHERE ce.entity_type = :et
                  AND ce.created_at BETWEEN :s AND :e
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':et' => $entityType, ':s' => $startDate, ':e' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function topEntities(string $entityType, string $eventType, string $startDate, string $endDate, int $limit = 10): array
    {
        $sql = "SELECT ce.entity_id, COUNT(*) AS event_count
                FROM core_events ce
                WHERE ce.entity_type = :et
                  AND ce.event_type = :evt
                  AND ce.created_at BETWEEN :s AND :e
                GROUP BY ce.entity_id
                ORDER BY event_count DESC
                LIMIT :lmt";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':et', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':evt', $eventType, PDO::PARAM_STR);
        $stmt->bindValue(':s', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':e', $endDate, PDO::PARAM_STR);
        $stmt->bindValue(':lmt', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getAllowedEntityTypes(): array
    {
        return self::ALLOWED_ENTITY_TYPES;
    }

    public static function getAllowedEventTypes(): array
    {
        return self::ALLOWED_EVENT_TYPES;
    }
}
