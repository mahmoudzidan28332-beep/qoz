<?php
declare(strict_types=1);

require_once __DIR__ . '/PlatformReportTimeSeriesTrait.php';
require_once __DIR__ . '/PlatformReportAggregationTrait.php';

/**
 * Platform Report Repository
 * Handles all database operations for the reporting/analytics system.
 * Reads from live tables (orders, products, etc.) and writes aggregated data
 * to platform_report_stats.
 */
final class PdoPlatformReportRepository
{
    use PlatformReportTimeSeriesTrait;
    use PlatformReportAggregationTrait;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ════════════════════════════════════════════════════════════
    // REPORT TYPES
    // ════════════════════════════════════════════════════════════

    public function allReportTypes(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, type_key, title_en, title_ar, description_en, description_ar, category, is_active, sort_order, created_at FROM report_types WHERE is_active = 1 ORDER BY sort_order ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════
    // STORED STATS (read cached aggregations)
    // ════════════════════════════════════════════════════════════

    public function getStoredStats(
        string $reportType,
        string $periodType,
        string $startDate,
        string $endDate,
        ?int $tenantId = null,
        ?int $entityId = null
    ): array {
        $sql = 'SELECT id, tenant_id, entity_id, report_type, period_type, period_date, period_start, period_end, metrics, generated_at, created_at, updated_at FROM platform_report_stats
                WHERE report_type = :rt
                  AND period_type = :pt
                  AND period_date BETWEEN :sd AND :ed';
        $params = [
            ':rt' => $reportType,
            ':pt' => $periodType,
            ':sd' => $startDate,
            ':ed' => $endDate,
        ];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        } else {
            $sql .= ' AND tenant_id IS NULL';
        }
        if ($entityId !== null) {
            $sql .= ' AND entity_id = :eid';
            $params[':eid'] = $entityId;
        } else {
            $sql .= ' AND entity_id IS NULL';
        }
        $sql .= ' ORDER BY period_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveStats(array $data): int
    {
        $sql = 'INSERT INTO platform_report_stats
                (tenant_id, entity_id, report_type, period_type, period_date, period_start, period_end, metrics, generated_at)
                VALUES (:tid, :eid, :rt, :pt, :pd, :ps, :pe, :m, NOW())
                ON DUPLICATE KEY UPDATE
                    metrics = VALUES(metrics),
                    generated_at = NOW(),
                    updated_at = NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tid' => $data['tenant_id'] ?? null,
            ':eid' => $data['entity_id'] ?? null,
            ':rt'  => $data['report_type'],
            ':pt'  => $data['period_type'],
            ':pd'  => $data['period_date'],
            ':ps'  => $data['period_start'],
            ':pe'  => $data['period_end'],
            ':m'   => is_string($data['metrics']) ? $data['metrics'] : json_encode($data['metrics']),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Sales Overview
    // ════════════════════════════════════════════════════════════

    public function aggregateSalesOverview(string $start, string $end, ?int $tenantId = null, ?int $entityId = null): array
    {
        $where = 'WHERE o.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND o.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND o.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $sql = "SELECT
                    COUNT(o.id) AS total_orders,
                    COALESCE(SUM(o.grand_total), 0) AS total_revenue,
                    COALESCE(SUM(o.tax_amount), 0) AS total_tax,
                    COALESCE(SUM(o.shipping_cost), 0) AS total_shipping,
                    COALESCE(SUM(o.discount_amount + o.coupon_discount), 0) AS total_discounts,
                    COALESCE(AVG(o.grand_total), 0) AS avg_order_value,
                    COUNT(DISTINCT o.user_id) AS unique_customers,
                    SUM(CASE WHEN o.status = 'completed' OR o.status = 'delivered' THEN 1 ELSE 0 END) AS completed_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                    SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_orders,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_orders
                FROM orders o
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Revenue & Profit
    // ════════════════════════════════════════════════════════════

    public function aggregateRevenueProfit(string $start, string $end, ?int $tenantId = null, ?int $entityId = null): array
    {
        $where = 'WHERE o.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND o.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND o.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $sql = "SELECT
                    COALESCE(SUM(o.grand_total), 0) AS gross_revenue,
                    COALESCE(SUM(o.discount_amount + o.coupon_discount), 0) AS total_discounts,
                    COALESCE(SUM(o.grand_total) - SUM(o.discount_amount + o.coupon_discount), 0) AS net_revenue,
                    COALESCE(SUM(o.tax_amount), 0) AS total_tax,
                    COALESCE(SUM(o.shipping_cost), 0) AS total_shipping,
                    (SELECT COALESCE(SUM(ci.total_commission), 0)
                     FROM commission_invoices ci
                     WHERE ci.created_at BETWEEN :s2 AND :e2
                     " . ($tenantId !== null ? 'AND ci.tenant_id = :tid2' : '') . "
                    ) AS total_commissions
                FROM orders o
                {$where}";

        $params[':s2'] = $start;
        $params[':e2'] = $end;
        if ($tenantId !== null) {
            $params[':tid2'] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Orders Performance
    // ════════════════════════════════════════════════════════════

    public function aggregateOrdersPerformance(string $start, string $end, ?int $tenantId = null, ?int $entityId = null): array
    {
        $where = 'WHERE o.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND o.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND o.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $sql = "SELECT
                    COUNT(o.id) AS total_orders,
                    SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                    SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
                    SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) AS processing_orders,
                    SUM(CASE WHEN o.status = 'shipped' THEN 1 ELSE 0 END) AS shipped_orders,
                    SUM(CASE WHEN o.status = 'delivered' OR o.status = 'completed' THEN 1 ELSE 0 END) AS delivered_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                    SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_orders,
                    SUM(CASE WHEN o.order_type = 'online' THEN 1 ELSE 0 END) AS online_orders,
                    SUM(CASE WHEN o.order_type = 'pos' THEN 1 ELSE 0 END) AS pos_orders,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN o.payment_status = 'pending' THEN 1 ELSE 0 END) AS payment_pending_count,
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.delivered_at)), 0) AS avg_delivery_hours
                FROM orders o
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTopProducts(string $start, string $end, ?int $tenantId = null, ?int $entityId = null, int $limit = 10): array
    {
        $where = 'WHERE oi.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND oi.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND oi.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $sql = "SELECT oi.product_id, oi.product_name,
                    SUM(oi.quantity) AS total_quantity,
                    SUM(oi.total) AS total_revenue
                FROM order_items oi
                {$where}
                GROUP BY oi.product_id, oi.product_name
                ORDER BY total_revenue DESC
                LIMIT :lmt";

        $params[':lmt'] = $limit;
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':lmt' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════
    // CHART DATA – Time Series
    // ════════════════════════════════════════════════════════════

    public function getOrdersTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day', ?int $entityId = null): array
    {
        $where = 'WHERE o.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND o.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND o.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(o.created_at, '{$dateFormat}') AS period,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.grand_total), 0) AS revenue
                FROM orders o
                {$where}
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAdsTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day'): array
    {
        $tWhere = $tenantId !== null ? 'AND ac.tenant_id = :tid' : '';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }

        $dateFormat = match($groupBy) {
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

    public function getProductsTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day', ?int $entityId = null): array
    {
        $where = 'WHERE oi.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND oi.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $where .= ' AND oi.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(oi.created_at, '{$dateFormat}') AS period,
                    COALESCE(SUM(oi.quantity), 0) AS units_sold,
                    COALESCE(SUM(oi.total), 0) AS revenue
                FROM order_items oi
                {$where}
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getReturnsTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day'): array
    {
        $where = 'WHERE r.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND r.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(r.created_at, '{$dateFormat}') AS period,
                    COUNT(r.id) AS return_count
                FROM returns r
                {$where}
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCustomerTimeSeries(string $start, string $end, string $groupBy = 'day'): array
    {
        $params = [':s' => $start, ':e' => $end];

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(u.created_at, '{$dateFormat}') AS period,
                    COUNT(u.id) AS new_users
                FROM users u
                WHERE u.created_at BETWEEN :s AND :e
                GROUP BY period
                ORDER BY period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDeliveryTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day', ?int $entityId = null): array
    {
        $join = '';
        $where = 'WHERE do2.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND do2.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($entityId !== null) {
            $join = 'INNER JOIN orders o ON o.id = do2.order_id';
            $where .= ' AND o.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(do2.created_at, '{$dateFormat}') AS period,
                    COUNT(do2.id) AS delivery_count,
                    COALESCE(SUM(do2.delivery_fee), 0) AS delivery_fees
                FROM delivery_orders do2
                {$join}
                {$where}
                GROUP BY period
                ORDER BY period ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            return [];
        }
    }

    // ════════════════════════════════════════════════════════════
    // REPORT EXPORTS
    // ════════════════════════════════════════════════════════════

    public function createExport(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_exports (tenant_id, report_type, export_format, filters, requested_by)
             VALUES (:tid, :rt, :ef, :f, :rb)'
        );
        $stmt->execute([
            ':tid' => $data['tenant_id'] ?? null,
            ':rt'  => $data['report_type'],
            ':ef'  => $data['export_format'] ?? 'excel',
            ':f'   => isset($data['filters']) ? json_encode($data['filters']) : null,
            ':rb'  => $data['requested_by'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listExports(?int $tenantId, int $limit = 20): array
    {
        $sql = 'SELECT id, tenant_id, report_type, export_format, filters, status, file_path, file_size, error_message, requested_by, completed_at, expires_at, created_at FROM report_exports';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' WHERE tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :lmt';
        $params[':lmt'] = $limit;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':lmt' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════
    // REPORT SCHEDULES
    // ════════════════════════════════════════════════════════════

    public function listSchedules(?int $tenantId): array
    {
        $sql = 'SELECT id, tenant_id, report_type, frequency, recipients_email, is_active, last_run_at, next_run_at, created_by, created_at, updated_at FROM report_schedules';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' WHERE tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveSchedule(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_schedules (tenant_id, report_type, frequency, recipients_email, is_active, created_by)
             VALUES (:tid, :rt, :freq, :emails, :active, :cb)'
        );
        $stmt->execute([
            ':tid'    => $data['tenant_id'] ?? null,
            ':rt'     => $data['report_type'],
            ':freq'   => $data['frequency'] ?? 'daily',
            ':emails' => $data['recipients_email'] ?? null,
            ':active' => $data['is_active'] ?? 1,
            ':cb'     => $data['created_by'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}