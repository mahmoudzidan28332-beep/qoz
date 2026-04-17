<?php
declare(strict_types=1);

final class PdoEntityContextRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getEntitiesWithContext(string $lang, int $tenantId, array $entityIds = []): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        $params = [$lang, $tenantId];
        $entityFilterSql = '';

        if (!empty($entityIds)) {
            $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
            $entityFilterSql = " AND e.id IN ($placeholders)";
            $params = array_merge($params, $entityIds);
        }

        $stmt = $this->pdo->prepare(
            "SELECT e.id,
                    COALESCE(et.store_name, e.store_name) AS store_name,
                    e.slug,
                    e.status,
                    COALESCE(es.delivery_radius_km, 0) AS delivery_radius_km,
                    COALESCE(es.preparation_time_minutes, 0) AS preparation_time_minutes,
                    COALESCE(es.min_order_amount, 0) AS min_order_amount,
                    COALESCE(es.allow_cod, 0) AS allow_cod,
                    COALESCE(es.is_visible, 1) AS is_visible,
                    COALESCE(es.maintenance_mode, 0) AS maintenance_mode,
                    (
                        SELECT a.address_line1
                        FROM addresses a
                        WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                        ORDER BY a.is_primary DESC, a.id ASC
                        LIMIT 1
                    ) AS address_line1,
                    (
                        SELECT a.address_line2
                        FROM addresses a
                        WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                        ORDER BY a.is_primary DESC, a.id ASC
                        LIMIT 1
                    ) AS address_line2,
                    (
                        SELECT a.latitude
                        FROM addresses a
                        WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                        ORDER BY a.is_primary DESC, a.id ASC
                        LIMIT 1
                    ) AS latitude,
                    (
                        SELECT a.longitude
                        FROM addresses a
                        WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                        ORDER BY a.is_primary DESC, a.id ASC
                        LIMIT 1
                    ) AS longitude,
                    (
                        SELECT COUNT(*)
                        FROM entity_pickup_points epp
                        WHERE epp.tenant_id = e.tenant_id
                          AND epp.entity_id = e.id
                          AND epp.is_active = 1
                    ) AS pickup_points_count
               FROM entities e
          LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
          LEFT JOIN entity_settings es ON es.entity_id = e.id
              WHERE e.tenant_id = ?
                AND e.status NOT IN ('suspended', 'rejected')
                $entityFilterSql
              ORDER BY e.id ASC
              LIMIT 100"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWorkingHours(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }
        $hoursSql = implode(',', array_fill(0, count($entityIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT entity_id, day_of_week, open_time, close_time, is_open
               FROM entities_working_hours
              WHERE entity_id IN ($hoursSql)
              ORDER BY entity_id ASC, day_of_week ASC"
        );
        $stmt->execute($entityIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
