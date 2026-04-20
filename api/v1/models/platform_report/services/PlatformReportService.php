<?php
declare(strict_types=1);

/**
 * Platform Report Service
 * Orchestrates report generation by calling the appropriate repository aggregation methods.
 */
final class PlatformReportService
{
    private PdoPlatformReportRepository $repo;
    private PlatformReportValidator $validator;

    public function __construct(PdoPlatformReportRepository $repo, PlatformReportValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    // ════════════════════════════════════════════════════════════
    // REPORT TYPES
    // ════════════════════════════════════════════════════════════

    public function getReportTypes(): array
    {
        return $this->repo->allReportTypes();
    }

    // ════════════════════════════════════════════════════════════
    // GENERATE REPORT (live data)
    // ════════════════════════════════════════════════════════════

    public function generateReport(array $params): array
    {
        $errors = $this->validator->validateReportRequest($params);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $reportType = $params['report_type'];
        $startDate  = $params['start_date'];
        $endDate    = $params['end_date'];
        $tenantId   = isset($params['tenant_id']) && $params['tenant_id'] !== '' ? (int)$params['tenant_id'] : null;
        $entityId   = isset($params['entity_id']) && $params['entity_id'] !== '' ? (int)$params['entity_id'] : null;
        $groupBy    = $params['group_by'] ?? 'day';

        $start = $startDate . ' 00:00:00';
        $end   = $endDate . ' 23:59:59';

        $metrics = $this->getMetrics($reportType, $start, $end, $tenantId, $entityId);
        $timeSeries = $this->getTimeSeries($reportType, $start, $end, $tenantId, $groupBy, $entityId);

        return [
            'success'     => true,
            'report_type' => $reportType,
            'period'      => ['start' => $startDate, 'end' => $endDate],
            'tenant_id'   => $tenantId,
            'entity_id'   => $entityId,
            'metrics'     => $metrics,
            'time_series' => $timeSeries,
        ];
    }

    // ════════════════════════════════════════════════════════════
    // DASHBOARD SUMMARY (quick overview)
    // ════════════════════════════════════════════════════════════

    public function getDashboardSummary(?int $tenantId = null): array
    {
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayEnd   = date('Y-m-d') . ' 23:59:59';
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd   = date('Y-m-t') . ' 23:59:59';

        $todaySales = $this->repo->aggregateSalesOverview($todayStart, $todayEnd, $tenantId);
        $monthSales = $this->repo->aggregateSalesOverview($monthStart, $monthEnd, $tenantId);

        return [
            'today' => [
                'orders'    => (int)($todaySales['total_orders'] ?? 0),
                'revenue'   => (float)($todaySales['total_revenue'] ?? 0),
                'customers' => (int)($todaySales['unique_customers'] ?? 0),
            ],
            'month' => [
                'orders'    => (int)($monthSales['total_orders'] ?? 0),
                'revenue'   => (float)($monthSales['total_revenue'] ?? 0),
                'customers' => (int)($monthSales['unique_customers'] ?? 0),
                'avg_order' => (float)($monthSales['avg_order_value'] ?? 0),
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════
    // EXPORTS
    // ════════════════════════════════════════════════════════════

    public function requestExport(array $params): array
    {
        $errors = $this->validator->validateExportRequest($params);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $exportId = $this->repo->createExport($params);
        return [
            'success'   => true,
            'export_id' => $exportId,
            'message'   => 'Export request created. It will be processed shortly.',
        ];
    }

    public function listExports(?int $tenantId): array
    {
        return $this->repo->listExports($tenantId);
    }

    // ════════════════════════════════════════════════════════════
    // SCHEDULES
    // ════════════════════════════════════════════════════════════

    public function listSchedules(?int $tenantId): array
    {
        return $this->repo->listSchedules($tenantId);
    }

    public function createSchedule(array $data): array
    {
        $id = $this->repo->saveSchedule($data);
        return ['success' => true, 'id' => $id];
    }

    // ════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════

    private function getMetrics(string $type, string $start, string $end, ?int $tenantId, ?int $entityId = null): array
    {
        return match ($type) {
            'sales_overview'       => $this->repo->aggregateSalesOverview($start, $end, $tenantId, $entityId),
            'revenue_profit'       => $this->repo->aggregateRevenueProfit($start, $end, $tenantId, $entityId),
            'orders_performance'   => array_merge(
                $this->repo->aggregateOrdersPerformance($start, $end, $tenantId, $entityId),
                $this->repo->aggregateDeliveryStats($start, $end, $tenantId, $entityId)
            ),
            'products_performance' => array_merge(
                $this->repo->aggregateProductsPerformance($start, $end, $tenantId),
                ['top_products' => $this->repo->getTopProducts($start, $end, $tenantId, $entityId)]
            ),
            'ads_performance'      => $this->repo->aggregateAdsPerformance($start, $end, $tenantId),
            'returns_complaints'   => $this->repo->aggregateReturnsComplaints($start, $end, $tenantId),
            'entities_performance' => $this->repo->aggregateEntitiesPerformance($start, $end, $tenantId),
            'customer_behavior'    => $this->repo->aggregateCustomerBehavior($start, $end, $tenantId),
            'delivery_performance' => $this->repo->aggregateDeliveryStats($start, $end, $tenantId, $entityId),
            'platform_health'      => $this->repo->aggregatePlatformHealth($start, $end),
            default                => [],
        };
    }

    private function getTimeSeries(string $type, string $start, string $end, ?int $tenantId, string $groupBy, ?int $entityId = null): array
    {
        return match ($type) {
            'sales_overview', 'revenue_profit', 'entities_performance', 'platform_health'
                => $this->repo->getOrdersTimeSeries($start, $end, $tenantId, $groupBy, $entityId),
            'orders_performance'
                => $this->repo->getOrdersTimeSeries($start, $end, $tenantId, $groupBy, $entityId),
            'products_performance'
                => $this->repo->getProductsTimeSeries($start, $end, $tenantId, $groupBy, $entityId),
            'ads_performance'
                => $this->repo->getAdsTimeSeries($start, $end, $tenantId, $groupBy),
            'returns_complaints'
                => $this->repo->getReturnsTimeSeries($start, $end, $tenantId, $groupBy),
            'customer_behavior'
                => $this->repo->getCustomerTimeSeries($start, $end, $groupBy),
            'delivery_performance'
                => $this->repo->getDeliveryTimeSeries($start, $end, $tenantId, $groupBy, $entityId),
            default => [],
        };
    }
}
