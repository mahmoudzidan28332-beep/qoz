<?php
declare(strict_types=1);

final class PdoNotificationRecipientsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY   = ['id', 'notification_id', 'tenant_id', 'recipient_type', 'recipient_id', 'is_read', 'read_at', 'created_at'];
    private const FILTERABLE_COLUMNS = ['notification_id', 'tenant_id', 'recipient_type', 'recipient_id', 'is_read'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql    = "SELECT nr.*,
                          n.title AS notification_title,
                          CASE
                              WHEN nr.recipient_type = 'user'   THEN u.username
                              WHEN nr.recipient_type = 'entity' THEN e.store_name
                              WHEN nr.recipient_type = 'tenant' THEN t.name
                              ELSE NULL
                          END AS recipient_name,
                          ten.name AS tenant_name
                   FROM notification_recipients nr
                   LEFT JOIN notifications n ON n.id  = nr.notification_id
                   LEFT JOIN tenants ten      ON ten.id = nr.tenant_id
                   LEFT JOIN users u          ON u.id  = nr.recipient_id AND nr.recipient_type = 'user'
                   LEFT JOIN entities e       ON e.id  = nr.recipient_id AND nr.recipient_type = 'entity'
                   LEFT JOIN tenants t        ON t.id  = nr.recipient_id AND nr.recipient_type = 'tenant'
                   WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND nr.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY nr.$orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM notification_recipients nr WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND nr.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT nr.*, n.title AS notification_title
               FROM notification_recipients nr
               LEFT JOIN notifications n ON n.id = nr.notification_id
              WHERE nr.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark a single recipient row as read.
     */
    public function markRead(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_recipients
                SET is_read = 1, read_at = NOW()
              WHERE id        = :id
                AND tenant_id = :tenant_id
                AND is_read   = 0"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark a single recipient row as unread.
     */
    public function markUnread(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_recipients
                SET is_read = 0, read_at = NULL
              WHERE id        = :id
                AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM notification_recipients
              WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
