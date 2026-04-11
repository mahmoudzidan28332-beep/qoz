<?php
declare(strict_types=1);

/**
 * Platform Report Repository
 * Handles all database operations for the reporting/analytics system.
 * Reads from live tables (orders, products, etc.) and writes aggregated data
 * to platform_report_stats.
 */
final class PdoPlatformReportRepository
{
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
            'SELECT * FROM report_types WHERE is_active = 1 ORDER BY sort_order ASC'
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
        $sql = 'SELECT * FROM platform_report_stats
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

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Products Performance
    // ════════════════════════════════════════════════════════════

    public function aggregateProductsPerformance(string $start, string $end, ?int $tenantId = null): array
    {
        // Product counts (no date range needed)
        $pWhere = $tenantId !== null ? 'WHERE p.tenant_id = :tid' : '';
        $pParams = [];
        if ($tenantId !== null) {
            $pParams[':tid'] = $tenantId;
        }

        $sql1 = "SELECT
                    COUNT(*) AS total_products,
                    SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) AS active_products,
                    SUM(CASE WHEN p.stock_status = 'out_of_stock' THEN 1 ELSE 0 END) AS out_of_stock,
                    SUM(CASE WHEN p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0 THEN 1 ELSE 0 END) AS low_stock
                 FROM products p {$pWhere}";

        $stmt1 = $this->pdo->prepare($sql1);
        $stmt1->execute($pParams);
        $productCounts = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];

        // Order items stats (date range + tenant)
        $oiWhere = 'WHERE oi.created_at BETWEEN :s AND :e';
        $oiParams = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $oiWhere .= ' AND oi.tenant_id = :tid';
            $oiParams[':tid'] = $tenantId;
        }

        $sql2 = "SELECT
                    COUNT(DISTINCT oi.product_id) AS products_sold_count,
                    COALESCE(SUM(oi.quantity), 0) AS total_units_sold
                 FROM order_items oi {$oiWhere}";

        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($oiParams);
        $salesStats = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

        $productStats = array_merge($productCounts, $salesStats);

        // Core events for product analytics (views, clicks, add_to_cart, favorites)
        $evParams = [':s' => $start, ':e' => $end];
        $evSql = "SELECT
                    SUM(CASE WHEN ce.event_type = 'view' THEN 1 ELSE 0 END) AS product_views,
                    SUM(CASE WHEN ce.event_type = 'click' THEN 1 ELSE 0 END) AS product_clicks,
                    SUM(CASE WHEN ce.event_type = 'add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart_events,
                    SUM(CASE WHEN ce.event_type = 'favorite' THEN 1 ELSE 0 END) AS product_favorites,
                    SUM(CASE WHEN ce.event_type = 'purchase' THEN 1 ELSE 0 END) AS product_purchases
                 FROM core_events ce
                 WHERE ce.entity_type = 'product'
                   AND ce.created_at BETWEEN :s AND :e";

        $evStmt = $this->pdo->prepare($evSql);
        $evStmt->execute($evParams);
        $eventStats = $evStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($productStats, $eventStats);
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
    // LIVE AGGREGATIONS – Ads Performance
    // ════════════════════════════════════════════════════════════

    public function aggregateAdsPerformance(string $start, string $end, ?int $tenantId = null): array
    {
        // Active campaigns count (separate query to avoid param reuse)
        $acParams = [];
        if ($tenantId !== null) {
            $sqlCamp = "SELECT COUNT(*) FROM ad_campaigns ac WHERE ac.tenant_id = :tid AND ac.status = 'active'";
            $acParams[':tid'] = $tenantId;
        } else {
            $sqlCamp = "SELECT COUNT(*) FROM ad_campaigns ac WHERE ac.status = 'active'";
        }
        $stmtCamp = $this->pdo->prepare($sqlCamp);
        $stmtCamp->execute($acParams);
        $activeCampaigns = (int)$stmtCamp->fetchColumn();

        // Ad stats with date range
        $tWhere = $tenantId !== null ? 'AND ac.tenant_id = :tid' : '';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }

        $sql = "SELECT
                    COALESCE(SUM(ast.views), 0) AS total_impressions,
                    COALESCE(SUM(ast.clicks), 0) AS total_clicks,
                    CASE WHEN SUM(ast.views) > 0
                         THEN ROUND(SUM(ast.clicks) * 100.0 / SUM(ast.views), 2)
                         ELSE 0 END AS ctr,
                    COALESCE(SUM(ast.views + ast.clicks), 0) AS total_interactions
                FROM ad_stats ast
                INNER JOIN ads a ON a.id = ast.ad_id
                INNER JOIN ad_campaigns ac ON ac.id = a.campaign_id
                WHERE ast.date BETWEEN :s AND :e
                {$tWhere}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $adsData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $adsData['active_campaigns'] = $activeCampaigns;

        // Top ads by views
        $tWhere2 = $tenantId !== null ? 'AND ac2.tenant_id = :tid2' : '';
        $params2 = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $params2[':tid2'] = $tenantId;
        }
        $sql2 = "SELECT ast.ad_id,
                    COALESCE(a.target_type, '') AS ad_type,
                    COALESCE(a.target_value, '') AS ad_target,
                    SUM(ast.views) AS total_views,
                    SUM(ast.clicks) AS total_clicks
                 FROM ad_stats ast
                 INNER JOIN ads a ON a.id = ast.ad_id
                 INNER JOIN ad_campaigns ac2 ON ac2.id = a.campaign_id
                 WHERE ast.date BETWEEN :s AND :e
                 {$tWhere2}
                 GROUP BY ast.ad_id, a.target_type, a.target_value
                 ORDER BY total_views DESC
                 LIMIT 10";

        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($params2);
        $adsData['top_ads'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $adsData;
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Returns & Complaints
    // ════════════════════════════════════════════════════════════

    public function aggregateReturnsComplaints(string $start, string $end, ?int $tenantId = null): array
    {
        $where = 'WHERE r.created_at BETWEEN :s AND :e';
        $params = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $where .= ' AND r.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }

        $sql = "SELECT
                    COUNT(r.id) AS total_returns,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending_returns,
                    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) AS approved_returns,
                    SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_returns,
                    SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) AS completed_returns
                FROM returns r
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $returnsData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Tickets/complaints
        $tWhere2 = 'WHERE st.created_at BETWEEN :s2 AND :e2';
        $params2 = [':s2' => $start, ':e2' => $end];

        $sql2 = "SELECT
                    COUNT(st.id) AS total_tickets,
                    SUM(CASE WHEN st.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                    SUM(CASE WHEN st.status = 'closed' OR st.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets
                 FROM support_tickets st
                 {$tWhere2}";

        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($params2);
        $ticketsData = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($returnsData, $ticketsData);
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Entities Performance
    // ════════════════════════════════════════════════════════════

    public function aggregateEntitiesPerformance(string $start, string $end, ?int $tenantId = null): array
    {
        // Entity counts (no date range, avoid param reuse with COUNT+CASE)
        $eWhere = $tenantId !== null ? 'WHERE e.tenant_id = :tid' : '';
        $eParams = [];
        if ($tenantId !== null) {
            $eParams[':tid'] = $tenantId;
        }

        $sql = "SELECT
                    COUNT(*) AS total_entities,
                    SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) AS active_entities,
                    SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pending_entities,
                    SUM(CASE WHEN e.status = 'suspended' THEN 1 ELSE 0 END) AS suspended_entities
                FROM entities e {$eWhere}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($eParams);
        $entityStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Top entities by revenue
        $oWhere = 'WHERE o.created_at BETWEEN :s AND :e';
        $oParams = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $oWhere .= ' AND o.tenant_id = :tid';
            $oParams[':tid'] = $tenantId;
        }

        $sql2 = "SELECT o.entity_id, e.store_name,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.grand_total), 0) AS total_revenue
                 FROM orders o
                 LEFT JOIN entities e ON e.id = o.entity_id
                 {$oWhere}
                 GROUP BY o.entity_id, e.store_name
                 ORDER BY total_revenue DESC
                 LIMIT 10";

        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($oParams);
        $topEntities = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $entityStats['top_entities'] = $topEntities;
        return $entityStats;
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Customer Behavior
    // ════════════════════════════════════════════════════════════

    public function aggregateCustomerBehavior(string $start, string $end, ?int $tenantId = null): array
    {
        $params = [':s' => $start, ':e' => $end];

        // New users registered
        $sql1 = "SELECT COUNT(*) AS new_users FROM users WHERE created_at BETWEEN :s AND :e";
        $stmt1 = $this->pdo->prepare($sql1);
        $stmt1->execute($params);
        $newUsers = (int)($stmt1->fetchColumn() ?: 0);

        // Cart stats
        $sql2 = "SELECT
                    COUNT(*) AS total_carts,
                    SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) AS abandoned_carts,
                    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_carts
                 FROM carts WHERE created_at BETWEEN :s AND :e";
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($params);
        $cartData = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

        // Repeat customers
        $oWhere = 'WHERE o.created_at BETWEEN :s AND :e';
        $oParams = [':s' => $start, ':e' => $end];
        if ($tenantId !== null) {
            $oWhere .= ' AND o.tenant_id = :tid';
            $oParams[':tid'] = $tenantId;
        }
        $sql3 = "SELECT COUNT(*) AS repeat_customers
                 FROM (
                    SELECT o.user_id FROM orders o {$oWhere}
                    GROUP BY o.user_id HAVING COUNT(o.id) > 1
                 ) sub";
        $stmt3 = $this->pdo->prepare($sql3);
        $stmt3->execute($oParams);
        $repeatCustomers = (int)($stmt3->fetchColumn() ?: 0);

        // Wishlists
        $sql4 = "SELECT COUNT(DISTINCT wi.id) AS total_wishlist_items
                 FROM wishlist_items wi WHERE wi.created_at BETWEEN :s AND :e";
        $stmt4 = $this->pdo->prepare($sql4);
        $stmt4->execute($params);
        $wishlistItems = (int)($stmt4->fetchColumn() ?: 0);

        return [
            'new_users'          => $newUsers,
            'total_carts'        => (int)($cartData['total_carts'] ?? 0),
            'abandoned_carts'    => (int)($cartData['abandoned_carts'] ?? 0),
            'converted_carts'    => (int)($cartData['converted_carts'] ?? 0),
            'cart_conversion_rate' => ($cartData['total_carts'] ?? 0) > 0
                ? round((($cartData['converted_carts'] ?? 0) / $cartData['total_carts']) * 100, 2)
                : 0,
            'repeat_customers'   => $repeatCustomers,
            'wishlist_items'     => $wishlistItems,
        ];
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Platform Health
    // ════════════════════════════════════════════════════════════

    public function aggregatePlatformHealth(string $start, string $end): array
    {
        // Platform counts (no date range needed)
        $sql1 = "SELECT
                    (SELECT COUNT(*) FROM users) AS total_users,
                    (SELECT COUNT(*) FROM users WHERE is_active = 1) AS active_users,
                    (SELECT COUNT(*) FROM tenants) AS total_tenants,
                    (SELECT COUNT(*) FROM tenants WHERE status = 'active') AS active_tenants,
                    (SELECT COUNT(*) FROM entities) AS total_entities,
                    (SELECT COUNT(*) FROM products) AS total_products,
                    (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') AS active_subscriptions";

        $stmt1 = $this->pdo->prepare($sql1);
        $stmt1->execute();
        $platformCounts = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];

        // Period order stats (date range)
        $params = [':s' => $start, ':e' => $end];
        $sql2 = "SELECT
                    COUNT(*) AS period_orders,
                    COALESCE(SUM(grand_total), 0) AS period_revenue
                 FROM orders
                 WHERE created_at BETWEEN :s AND :e";

        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute($params);
        $orderStats = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($platformCounts, $orderStats);
    }

    // ════════════════════════════════════════════════════════════
    // LIVE AGGREGATIONS – Delivery Stats
    // ════════════════════════════════════════════════════════════

    public function aggregateDeliveryStats(string $start, string $end, ?int $tenantId = null, ?int $entityId = null): array
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

        $sql = "SELECT
                    COUNT(do2.id) AS total_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'pending' THEN 1 ELSE 0 END) AS pending_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'assigned' OR do2.delivery_status = 'accepted' THEN 1 ELSE 0 END) AS assigned_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'picked_up' OR do2.delivery_status = 'on_the_way' THEN 1 ELSE 0 END) AS in_transit_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'delivered' THEN 1 ELSE 0 END) AS completed_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_deliveries,
                    SUM(CASE WHEN do2.delivery_status = 'failed' THEN 1 ELSE 0 END) AS failed_deliveries,
                    COALESCE(SUM(do2.delivery_fee), 0) AS total_delivery_fees,
                    COALESCE(SUM(do2.provider_payout), 0) AS total_provider_payouts,
                    COALESCE(AVG(TIMESTAMPDIFF(MINUTE, do2.assigned_at, do2.delivered_at)), 0) AS avg_delivery_minutes
                FROM delivery_orders do2
                {$join}
                {$where}";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // delivery_orders table may not exist
            return [];
        }
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

    public function getRevenueTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day'): array
    {
        return $this->getOrdersTimeSeries($start, $end, $tenantId, $groupBy);
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

    public function getCoreEventsTimeSeries(string $start, string $end, string $entityType = 'product', string $groupBy = 'day'): array
    {
        $params = [':s' => $start, ':e' => $end, ':et' => $entityType];

        $dateFormat = match($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $sql = "SELECT
                    DATE_FORMAT(ce.created_at, '{$dateFormat}') AS period,
                    SUM(CASE WHEN ce.event_type = 'view' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN ce.event_type = 'click' THEN 1 ELSE 0 END) AS clicks
                FROM core_events ce
                WHERE ce.entity_type = :et
                  AND ce.created_at BETWEEN :s AND :e
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
        $sql = 'SELECT * FROM report_exports';
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
        $sql = 'SELECT * FROM report_schedules';
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