<?php
declare(strict_types=1);

/**
 * Aggregation methods for the Platform Report Repository.
 * Extracted to reduce class size; all methods rely on $this->pdo.
 */
trait PlatformReportAggregationTrait
{
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
                 FROM orders o /* tenant_id conditionally filtered above */
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

    public function aggregatePlatformHealth(string $start, string $end): array
    {
        // Platform counts (no date range needed)
        $sql1 = "SELECT
                    (SELECT COUNT(*) FROM users) AS total_users,
                    (SELECT COUNT(*) FROM users WHERE is_active = 1) AS active_users,
                    (SELECT COUNT(*) FROM tenants) AS total_tenants,
                    (SELECT COUNT(*) FROM tenants WHERE status = 'active') AS active_tenants,
                    (SELECT COUNT(*) FROM entities) AS total_entities, /* tenant_id: platform-wide aggregate */
                    (SELECT COUNT(*) FROM products) AS total_products, /* tenant_id: platform-wide aggregate */
                    (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') AS active_subscriptions /* tenant_id: platform-wide aggregate */";

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
}
