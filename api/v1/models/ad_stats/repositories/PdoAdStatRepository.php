<?php
declare(strict_types=1);

/**
 * Ad Stats Repository
 * Handles CRUD and aggregation operations for the ad_stats table.
 * Tracks ad performance: views and clicks per ad per day.
 */
final class PdoAdStatRepository
{
    private PDO $pdo;

    private const ALLOWED_EVENT_TYPES = ['view', 'click'];
    private const ALLOWED_ORDER_BY    = ['id', 'ad_id', 'views', 'clicks', 'date', 'created_at'];
    private const ALLOWED_COLS        = ['ad_id', 'user_id', 'session_id', 'ip_address', 'user_agent', 'views', 'clicks', 'event_type', 'date'];


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

        if (!empty($filters['ad_id'])) {
            $where[] = 'ast.ad_id = :ad_id';
            $params[':ad_id'] = (int) $filters['ad_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'ast.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'ast.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }
        if (!empty($filters['tenant_id'])) {
            $where[] = 'ac.tenant_id = :tenant_id';
            $params[':tenant_id'] = (int) $filters['tenant_id'];
        }
        if (!empty($filters['start_date'])) {
            $where[] = 'ast.date >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'ast.date <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countSql = "SELECT COUNT(*)
                     FROM ad_stats ast
                     INNER JOIN ads a ON a.id = ast.ad_id
                     INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                     {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Data
        $sql = "SELECT ast.*,
                    a.target_type AS ad_type,
                    a.target_value AS ad_target,
                    ac.name AS campaign_name,
                    ac.tenant_id
                FROM ad_stats ast
                INNER JOIN ads a ON a.id = ast.ad_id
                INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                {$whereClause}
                ORDER BY ast.{$orderBy} {$orderDir}
                LIMIT :lmt OFFSET :ofs";
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
        $stmt = $this->pdo->prepare(
            'SELECT ast.*, a.target_type AS ad_type, a.target_value AS ad_target, ac.name AS campaign_name, ac.tenant_id
             FROM ad_stats ast
             INNER JOIN ads a ON a.id = ast.ad_id
             INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
             WHERE ast.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection
        if (class_exists('SecurityValidators')) {
            $data = SecurityValidators::filterInput($data, self::ALLOWED_COLS);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ad_stats (ad_id, user_id, session_id, ip_address, user_agent, views, clicks, event_type, date)
             VALUES (:ad_id, :user_id, :session_id, :ip_address, :user_agent, :views, :clicks, :event_type, :date)'
        );

        $stmt->execute([
            ':ad_id'      => (int) $data['ad_id'],
            ':user_id'    => isset($data['user_id']) ? (int) $data['user_id'] : null,
            ':session_id' => $data['session_id'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':views'      => (int) ($data['views'] ?? 0),
            ':clicks'     => (int) ($data['clicks'] ?? 0),
            ':event_type' => $data['event_type'] ?? 'view',
            ':date'       => $data['date'] ?? date('Y-m-d'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ad_stats WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════
    // RECORD IMPRESSION (full upsert with tracking data)
    // ════════════════════════════════════════════════════════════

    public function recordImpression(int $adId, ?int $userId, ?string $sessionId, ?string $ipAddress, ?string $userAgent, int $views, int $clicks, string $eventType): void
    {
        $ins = $this->pdo->prepare(
            "INSERT INTO ad_stats
                 (ad_id, user_id, session_id, ip_address, user_agent,
                  date, created_at, views, clicks, event_type)
             VALUES
                 (?, ?, ?, ?, ?, CURDATE(), NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 views  = views  + VALUES(views),
                 clicks = clicks + VALUES(clicks)"
        );
        $ins->execute([
            $adId,
            $userId,
            $sessionId,
            $ipAddress,
            $userAgent,
            $views,
            $clicks,
            $eventType,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // INCREMENT STAT (minimal fallback upsert)
    // ════════════════════════════════════════════════════════════

    public function incrementStat(int $adId, int $views, int $clicks): void
    {
        $this->pdo->prepare(
            "INSERT INTO ad_stats (ad_id, date, views, clicks)
             VALUES (?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE
                 views  = views  + VALUES(views),
                 clicks = clicks + VALUES(clicks)"
        )->execute([$adId, $views, $clicks]);
    }

    // ════════════════════════════════════════════════════════════
    // AGGREGATIONS (for reports)
    // ════════════════════════════════════════════════════════════

    public function aggregate(string $startDate, string $endDate, ?int $tenantId = null): array
    {
        $tWhere = $tenantId !== null ? 'AND ac.tenant_id = :tid' : '';
        $params = [':s' => $startDate, ':e' => $endDate];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }

        $sql = "SELECT
                    (SELECT COUNT(*) FROM ad_campaigns ac WHERE ac.status = 'active' {$tWhere}) AS active_campaigns,
                    COALESCE(SUM(ast.views), 0) AS total_views,
                    COALESCE(SUM(ast.clicks), 0) AS total_clicks,
                    CASE WHEN SUM(ast.views) > 0
                         THEN ROUND(SUM(ast.clicks) * 100.0 / SUM(ast.views), 2)
                         ELSE 0 END AS ctr,
                    COUNT(DISTINCT ast.ad_id) AS unique_ads,
                    COUNT(DISTINCT ast.user_id) AS unique_users
                FROM ad_stats ast
                INNER JOIN ads a ON a.id = ast.ad_id
                INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                WHERE ast.date BETWEEN :s AND :e
                {$tWhere}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function timeSeries(string $startDate, string $endDate, ?int $tenantId = null, string $groupBy = 'day'): array
    {
        $tWhere = $tenantId !== null ? 'AND ac.tenant_id = :tid' : '';
        $params = [':s' => $startDate, ':e' => $endDate];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }

        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(ast.date, '{$dateFormat}') AS period,
                    COALESCE(SUM(ast.views), 0) AS views,
                    COALESCE(SUM(ast.clicks), 0) AS clicks
                FROM ad_stats ast
                INNER JOIN ads a ON a.id = ast.ad_id
                INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                WHERE ast.date BETWEEN :s AND :e
                {$tWhere}
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function topAds(string $startDate, string $endDate, ?int $tenantId = null, int $limit = 10): array
    {
        $tWhere = $tenantId !== null ? 'AND ac.tenant_id = :tid' : '';
        $params = [':s' => $startDate, ':e' => $endDate];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }

        $sql = "SELECT ast.ad_id,
                    COALESCE(a.target_type, '') AS ad_type,
                    COALESCE(a.target_value, '') AS ad_target,
                    ac.name AS campaign_name,
                    SUM(ast.views) AS total_views,
                    SUM(ast.clicks) AS total_clicks,
                    CASE WHEN SUM(ast.views) > 0
                         THEN ROUND(SUM(ast.clicks) * 100.0 / SUM(ast.views), 2)
                         ELSE 0 END AS ctr
                FROM ad_stats ast
                INNER JOIN ads a ON a.id = ast.ad_id
                INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                WHERE ast.date BETWEEN :s AND :e
                {$tWhere}
                GROUP BY ast.ad_id, a.target_type, a.target_value, ac.name
                ORDER BY total_views DESC
                LIMIT :lmt";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lmt', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
