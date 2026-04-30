<?php
declare(strict_types=1);

/**
 * Public API sub-route: entity_context
 *
 * Endpoints:
 *   GET  /api/public/entity_context/current
 *   GET  /api/public/entity_context/options
 *   POST /api/public/entity_context/resolve
 *   POST /api/public/entity_context/select
 */

if ($first === 'entity_context') {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $sub    = strtolower($segments[1] ?? 'current');

    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    $ctxTenantId = $tenantId ?? (int)($_SESSION['pub_tenant_id'] ?? 1);
    if ($ctxTenantId <= 0) {
        ResponseFormatter::error('tenant_id is required', 400);
        exit;
    }

    $body = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $raw  = file_get_contents('php://input');
        $type = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if ($raw && str_starts_with($type, 'application/json')) {
            $body = json_decode($raw, true) ?: [];
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }
    }

    $ctxHoursState = static function (array $workingHours): array {
        if (empty($workingHours)) {
            return ['known' => false, 'is_open' => true];
        }

        $nowDow  = (int)date('w');
        $nowMins = (int)date('H') * 60 + (int)date('i');

        foreach ($workingHours as $h) {
            if ((int)($h['day_of_week'] ?? -1) !== $nowDow) {
                continue;
            }
            if (empty($h['is_open'])) {
                return ['known' => true, 'is_open' => false];
            }

            $openMin  = 0;
            $closeMin = 24 * 60;
            if (!empty($h['open_time'])) {
                [$oh, $om] = array_map('intval', explode(':', (string)$h['open_time']));
                $openMin = ($oh * 60) + $om;
            }
            if (!empty($h['close_time'])) {
                [$ch, $cm] = array_map('intval', explode(':', (string)$h['close_time']));
                $closeMin = ($ch * 60) + $cm;
            }

            return [
                'known'   => true,
                'is_open' => ($nowMins >= $openMin && ($closeMin === 0 || $nowMins < $closeMin)),
            ];
        }

        return ['known' => false, 'is_open' => true];
    };

    $ctxDistanceKm = static function (float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    };

    $ctxRepo = new PdoEntityContextRepository($pdo);
    $ctxService = new EntityContextService($ctxRepo);

    $ctxListEntities = static function (
        ?float $lat = null,
        ?float $lng = null,
        int $limit = 10,
        array $entityIds = []
    ) use ($ctxRepo, $ctxTenantId, $lang, $ctxHoursState, $ctxDistanceKm): array {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));

        $rows = $ctxRepo->getEntitiesWithContext($lang, $ctxTenantId, $entityIds);
        if (empty($rows)) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
        $hoursMap = [];
        if (!empty($ids)) {
            try {
                foreach ($ctxRepo->getWorkingHours($ids) as $hourRow) {
                    $hoursMap[(int)$hourRow['entity_id']][] = $hourRow;
                }
            } catch (\RuntimeException) {
                $hoursMap = [];
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $entityId = (int)($row['id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $hoursState = $ctxHoursState($hoursMap[$entityId] ?? []);
            $entityLat = isset($row['latitude']) && $row['latitude'] !== null ? (float)$row['latitude'] : null;
            $entityLng = isset($row['longitude']) && $row['longitude'] !== null ? (float)$row['longitude'] : null;
            $distanceKm = null;

            if ($lat !== null && $lng !== null && $entityLat !== null && $entityLng !== null) {
                $distanceKm = round($ctxDistanceKm($lat, $lng, $entityLat, $entityLng), 2);
            }

            $deliveryRadiusKm = (float)($row['delivery_radius_km'] ?? 0);
            $isVisible = (int)($row['is_visible'] ?? 1) !== 0;
            $inMaintenance = (int)($row['maintenance_mode'] ?? 0) !== 0;
            $isAvailable = $isVisible && !$inMaintenance && (!($hoursState['known'] ?? false) || !empty($hoursState['is_open']));

            $items[] = [
                'id'                       => $entityId,
                'name'                     => (string)($row['store_name'] ?? ''),
                'slug'                     => (string)($row['slug'] ?? ''),
                'status'                   => (string)($row['status'] ?? ''),
                'address_line1'            => (string)($row['address_line1'] ?? ''),
                'address_line2'            => (string)($row['address_line2'] ?? ''),
                'latitude'                 => $entityLat,
                'longitude'                => $entityLng,
                'distance_km'              => $distanceKm,
                'delivery_radius_km'       => $deliveryRadiusKm,
                'preparation_time_minutes' => (int)($row['preparation_time_minutes'] ?? 0),
                'min_order_amount'         => (float)($row['min_order_amount'] ?? 0),
                'pickup_points_count'      => (int)($row['pickup_points_count'] ?? 0),
                'allow_cod'                => (bool)($row['allow_cod'] ?? false),
                'is_visible'               => $isVisible,
                'is_available'             => $isAvailable,
                'is_open_now'              => (bool)($hoursState['is_open'] ?? true),
                'hours_known'              => (bool)($hoursState['known'] ?? false),
                'has_delivery_hint'        => $distanceKm !== null && $deliveryRadiusKm > 0
                    ? ($distanceKm <= $deliveryRadiusKm)
                    : ($deliveryRadiusKm > 0),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if (($a['is_available'] ?? false) !== ($b['is_available'] ?? false)) {
                return ($a['is_available'] ?? false) ? -1 : 1;
            }

            $aDist = $a['distance_km'] ?? null;
            $bDist = $b['distance_km'] ?? null;
            if ($aDist !== null && $bDist !== null && $aDist !== $bDist) {
                return $aDist <=> $bDist;
            }
            if ($aDist !== null && $bDist === null) {
                return -1;
            }
            if ($aDist === null && $bDist !== null) {
                return 1;
            }
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return array_slice($items, 0, max(1, $limit));
    };

    $ctxPickBest = static function (array $entities): ?array {
        foreach ($entities as $entity) {
            if (!empty($entity['is_available'])) {
                return $entity;
            }
        }
        return $entities[0] ?? null;
    };

    $ctxStoreLocation = static function (?float $lat, ?float $lng, string $source = 'unknown') use ($ctxTenantId): void {
        if ($lat === null || $lng === null) {
            return;
        }
        $_SESSION['pub_entity_location'] ??= [];
        $_SESSION['pub_entity_location'][$ctxTenantId] = [
            'lat'         => round($lat, 7),
            'lng'         => round($lng, 7),
            'source'      => $source,
            'resolved_at' => date('c'),
        ];
    };

    $ctxStoreSelection = static function (array $entity, string $source, ?float $lat = null, ?float $lng = null) use ($ctxTenantId, $ctxStoreLocation): array {
        $_SESSION['pub_active_entity'] ??= [];
        $_SESSION['pub_active_entity'][$ctxTenantId] = [
            'id'                  => (int)($entity['id'] ?? 0),
            'name'                => (string)($entity['name'] ?? ''),
            'slug'                => (string)($entity['slug'] ?? ''),
            'status'              => (string)($entity['status'] ?? ''),
            'latitude'            => isset($entity['latitude']) && $entity['latitude'] !== null ? (float)$entity['latitude'] : null,
            'longitude'           => isset($entity['longitude']) && $entity['longitude'] !== null ? (float)$entity['longitude'] : null,
            'distance_km'         => isset($entity['distance_km']) && $entity['distance_km'] !== null ? (float)$entity['distance_km'] : null,
            'delivery_radius_km'  => (float)($entity['delivery_radius_km'] ?? 0),
            'pickup_points_count' => (int)($entity['pickup_points_count'] ?? 0),
            'has_delivery_hint'   => !empty($entity['has_delivery_hint']),
            'is_available'        => !empty($entity['is_available']),
            'is_open_now'         => !empty($entity['is_open_now']),
            'source'              => $source,
            'resolved_at'         => date('c'),
        ];

        $ctxStoreLocation($lat, $lng, $source);

        return $_SESSION['pub_active_entity'][$ctxTenantId];
    };

    $ctxResolveCurrent = static function () use ($ctxTenantId, $ctxListEntities, $ctxPickBest, $ctxStoreSelection): array {
        $locationState = $_SESSION['pub_entity_location'][$ctxTenantId] ?? null;
        $lat = isset($locationState['lat']) ? (float)$locationState['lat'] : null;
        $lng = isset($locationState['lng']) ? (float)$locationState['lng'] : null;

        $sessionEntityId = (int)($_SESSION['pub_active_entity'][$ctxTenantId]['id'] ?? 0);
        if ($sessionEntityId > 0) {
            $current = $ctxListEntities($lat, $lng, 1, [$sessionEntityId]);
            if (!empty($current[0]) && !empty($current[0]['is_available'])) {
                return $ctxStoreSelection(
                    $current[0],
                    (string)($_SESSION['pub_active_entity'][$ctxTenantId]['source'] ?? 'session'),
                    $lat,
                    $lng
                );
            }
        }

        if ($lat !== null && $lng !== null) {
            $nearest = $ctxListEntities($lat, $lng, 8);
            $best = $ctxPickBest($nearest);
            if ($best) {
                return $ctxStoreSelection($best, 'nearest', $lat, $lng);
            }
        }

        $fallback = $ctxListEntities(null, null, 8);
        $best = $ctxPickBest($fallback);
        if ($best) {
            return $ctxStoreSelection($best, 'fallback', $lat, $lng);
        }

        return ['id' => 0, 'name' => '', 'source' => 'none'];
    };

    try {
        if ($sub === 'current' && $method === 'GET') {
            $active = $ctxResolveCurrent();
            $locationState = $_SESSION['pub_entity_location'][$ctxTenantId] ?? null;
            $lat = isset($locationState['lat']) ? (float)$locationState['lat'] : null;
            $lng = isset($locationState['lng']) ? (float)$locationState['lng'] : null;
            $candidates = $ctxListEntities($lat, $lng, 6);

            ResponseFormatter::success([
                'active_entity' => $active,
                'candidates'    => $candidates,
                'location'      => $locationState,
            ]);
            exit;
        }

        if ($sub === 'options' && $method === 'GET') {
            $locationState = $_SESSION['pub_entity_location'][$ctxTenantId] ?? null;
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : (isset($locationState['lat']) ? (float)$locationState['lat'] : null);
            $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : (isset($locationState['lng']) ? (float)$locationState['lng'] : null);
            $candidates = $ctxListEntities($lat, $lng, 12);

            ResponseFormatter::success([
                'candidates' => $candidates,
                'location'   => $locationState,
            ]);
            exit;
        }

        if ($sub === 'resolve' && $method === 'POST') {
            $prevId = (int)($_SESSION['pub_active_entity'][$ctxTenantId]['id'] ?? 0);
            $lat = isset($body['lat']) ? (float)$body['lat'] : null;
            $lng = isset($body['lng']) ? (float)$body['lng'] : null;

            if ($lat === null || $lng === null) {
                ResponseFormatter::error('lat and lng are required', 422);
                exit;
            }

            $ctxStoreLocation($lat, $lng, 'gps');
            $candidates = $ctxListEntities($lat, $lng, 12);
            $best = $ctxPickBest($candidates);

            if (!$best) {
                ResponseFormatter::success([
                    'active_entity' => null,
                    'candidates'    => [],
                    'changed'       => false,
                    'requires_manual_selection' => true,
                ]);
                exit;
            }

            $active = $ctxStoreSelection($best, 'gps', $lat, $lng);
            ResponseFormatter::success([
                'active_entity'              => $active,
                'candidates'                 => $candidates,
                'changed'                    => $prevId !== (int)$active['id'],
                'requires_manual_selection'  => empty($best['is_available']),
            ]);
            exit;
        }

        if ($sub === 'select' && $method === 'POST') {
            $prevId = (int)($_SESSION['pub_active_entity'][$ctxTenantId]['id'] ?? 0);
            $entityId = (int)($body['entity_id'] ?? 0);
            $locationState = $_SESSION['pub_entity_location'][$ctxTenantId] ?? null;
            $lat = isset($body['lat']) ? (float)$body['lat'] : (isset($locationState['lat']) ? (float)$locationState['lat'] : null);
            $lng = isset($body['lng']) ? (float)$body['lng'] : (isset($locationState['lng']) ? (float)$locationState['lng'] : null);

            if ($entityId <= 0) {
                ResponseFormatter::error('entity_id is required', 422);
                exit;
            }

            $selected = $ctxListEntities($lat, $lng, 1, [$entityId]);
            if (empty($selected[0])) {
                ResponseFormatter::notFound('Entity not found');
                exit;
            }

            $active = $ctxStoreSelection($selected[0], 'manual', $lat, $lng);
            ResponseFormatter::success([
                'active_entity' => $active,
                'changed'       => $prevId !== (int)$active['id'],
            ]);
            exit;
        }

        ResponseFormatter::error('Unknown entity_context action', 404);
        exit;
    } catch (\RuntimeException $e) {
        ResponseFormatter::error('Entity context error: ' . $e->getMessage(), 500);
        exit;
    }
}