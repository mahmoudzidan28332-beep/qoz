<?php
declare(strict_types=1);

trait PlatformReportTimeSeriesTrait
{
    public function getRevenueTimeSeries(string $start, string $end, ?int $tenantId = null, string $groupBy = 'day'): array
    {
        return $this->getOrdersTimeSeries($start, $end, $tenantId, $groupBy);
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
}
