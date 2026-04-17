<?php
declare(strict_types=1);
/**
 * Public API sub-route: delivery
 *
 * GET /api/public/delivery?lat=&lng=&entity_id=   → zone match + all options
 * GET /api/public/delivery?coverage=1&entity_id=  → all zones for map
 * GET /api/public/delivery?pickup=1&entity_id=    → pickup points
 * GET /api/public/delivery/zones                  → alias coverage
 * GET /api/public/delivery/providers              → providers list
 */

if ($first !== 'delivery') return;

$method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$subPath = strtolower($segments[1] ?? '');

if ($method === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        http_response_code(204);
    }
    exit;
}
if ($method !== 'GET') { ResponseFormatter::error('Method not allowed', 405); exit; }
if (!$tenantId)        { ResponseFormatter::error('tenant_id is required', 400); exit; }

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$eid = (int)($_GET['entity_id'] ?? 0);

/* ════════════════════════════════════════════════════════════
 *  GEOMETRY HELPERS
 * ════════════════════════════════════════════════════════════ */

function dlv_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function dlv_point_in_polygon(float $lat, float $lng, array $rings): bool {
    // rings = GeoJSON coordinates[0]: [[lng, lat], ...]
    $polygon = $rings;
    $inside  = false;
    $n       = count($polygon);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        // GeoJSON stores [lng, lat] — index 0 = lng, index 1 = lat
        $pLat = (float)$polygon[$i][1]; $pLng = (float)$polygon[$i][0];
        $qLat = (float)$polygon[$j][1]; $qLng = (float)$polygon[$j][0];
        if ((($pLng > $lng) !== ($qLng > $lng)) &&
            ($lat < ($qLat - $pLat) * ($lng - $pLng) / ($qLng - $pLng) + $pLat)) {
            $inside = !$inside;
        }
    }
    return $inside;
}

function dlv_in_zone(?float $lat, ?float $lng, array $zone): bool {
    if ($lat === null || $lng === null) return false;
    $raw = $zone['zone_value'] ?? '';
    if (empty($raw)) return false;

    $data = is_array($raw) ? $raw : @json_decode($raw, true);
    if (!$data) return false;

    $type = strtolower($data['type'] ?? $zone['zone_type'] ?? '');

    switch ($type) {
        case 'polygon':
            $coords = $data['coordinates'][0] ?? [];
            return $coords ? dlv_point_in_polygon($lat, $lng, $coords) : false;

        case 'multipolygon':
            foreach ($data['coordinates'] as $poly) {
                if (!empty($poly[0]) && dlv_point_in_polygon($lat, $lng, $poly[0])) return true;
            }
            return false;

        case 'radius':
        case 'circle':
            $center = $data['center'] ?? null;
            if (!$center && !empty($zone['center_lat']) && !empty($zone['center_lng'])) {
                $center = [$zone['center_lat'], $zone['center_lng']];
            }
            if (!$center) return false;
            $r_km = isset($data['radius'])
                  ? ((float)$data['radius'] / 1000)
                  : (float)($zone['radius_km'] ?? 0);
            return dlv_distance($lat, $lng, (float)$center[0], (float)$center[1]) <= $r_km;

        case 'rectangle':
            $b = $data['bounds'] ?? null; // [[south,west],[north,east]]
            if (!$b || count($b) < 2) return false;
            return $lat >= (float)$b[0][0] && $lat <= (float)$b[1][0]
                && $lng >= (float)$b[0][1] && $lng <= (float)$b[1][1];

        case 'city':
            $cid = (int)($_GET['city_id'] ?? 0);
            return $cid > 0 && (int)($zone['city_id'] ?? 0) === $cid;

        case 'district':
            $did = (int)($_GET['district_id'] ?? 0);
            return $did > 0 && (int)($zone['district_id'] ?? 0) === $did;
    }
    return false;
}

/* ════════════════════════════════════════════════════════════
 *  CORE QUERY: zones for this tenant/entity
 *
 *  ROOT CAUSE FIX:
 *  The old code required entity_id IS NULL/0 — but ALL providers
 *  have entity_id = 1 (entity_driver). We now JOIN directly on
 *  provider_id so zones linked to ANY provider of this entity
 *  are returned, regardless of provider_type.
 * ════════════════════════════════════════════════════════════ */
$allZonesRaw = $pdoList(
    "SELECT
        dz.id,
        dz.provider_id,
        dz.zone_name,
        dz.zone_type,
        dz.zone_value,
        dz.city_id,
        dz.center_lat,
        dz.center_lng,
        dz.radius_km,
        dz.delivery_fee,
        dz.free_delivery_over,
        dz.min_order_value,
        dz.estimated_minutes,
        dz.is_active,

        dp.provider_type,
        dp.vehicle_type,
        dp.is_online,
        dp.is_active   AS provider_active,
        dp.rating      AS provider_rating,
        dp.total_deliveries,
        dp.entity_id   AS provider_entity_id,
        dp.tenant_user_id,

        c.name         AS city_name,
        c.latitude     AS city_lat,
        c.longitude    AS city_lng

     FROM delivery_zones dz

     /* JOIN providers directly — covers entity_driver, company, independent */
LEFT JOIN delivery_providers dp ON dp.id = dz.provider_id

     /* City info for city/radius zones */
LEFT JOIN cities c ON c.id = dz.city_id

     WHERE dz.tenant_id = ?
       AND dz.is_active  = 1
       AND (
             /* ── Case 1: Zone linked directly to a provider of this entity ── */
             (dz.provider_id IS NOT NULL AND dz.provider_id > 0
              AND dp.tenant_id = ?
              AND dp.is_active = 1
              AND (dp.entity_id = ? OR dp.entity_id IS NULL OR dp.entity_id = 0)
             )
             OR
             /* ── Case 2: Zone linked via provider_zones pivot table ── */
             dz.id IN (
                 SELECT pz.zone_id
                   FROM provider_zones pz
                   JOIN delivery_providers dp2 ON dp2.id = pz.provider_id
                  WHERE dp2.tenant_id = ?
                    AND dp2.is_active = 1
                    AND pz.is_active  = 1
                    AND (dp2.entity_id = ? OR dp2.entity_id IS NULL OR dp2.entity_id = 0)
             )
             OR
             /* ── Case 3: Global zones (no provider assigned) ── */
             (dz.provider_id IS NULL OR dz.provider_id = 0)
           )

     ORDER BY
         /* Best match first: polygon > radius > city > district > rectangle */
         FIELD(dz.zone_type, 'polygon', 'radius', 'district', 'city', 'rectangle') ASC,
         dz.delivery_fee ASC",
    [$tenantId, $tenantId, $eid, $tenantId, $eid]
);

/* ── Normalise raw rows ─────────────────────────────────── */
$allZones = [];
foreach ($allZonesRaw as $z) {
    $provEid        = (int)($z['provider_entity_id'] ?? 0);
    $isMerchantZone = ($eid > 0 && ($provEid === $eid || $provEid === 0));

    /* Verify via pivot if still uncertain */
    if (!$isMerchantZone && $eid > 0 && $z['provider_id']) {
        $pzCheck = $pdoOne(
            "SELECT 1 FROM provider_zones pz
               JOIN delivery_providers dp ON dp.id = pz.provider_id
              WHERE pz.zone_id = ? AND dp.entity_id = ? AND pz.is_active = 1 LIMIT 1",
            [(int)$z['id'], $eid]
        );
        if ($pzCheck) $isMerchantZone = true;
    }

    /* Resolve provider display name from users table */
    $providerName = null;
    if (!empty($z['tenant_user_id'])) {
        $tu = $pdoOne(
            "SELECT u.name FROM users u
               JOIN tenant_users tu ON tu.user_id = u.id
              WHERE tu.id = ? LIMIT 1",
            [(int)$z['tenant_user_id']]
        );
        $providerName = $tu['name'] ?? null;
    }

    $allZones[] = [
        'id'                 => (int)$z['id'],
        'provider_id'        => (int)($z['provider_id'] ?? 0),
        'zone_name'          => $z['zone_name'],
        'zone_type'          => $z['zone_type'],
        'zone_value'         => $z['zone_value'],
        'city_id'            => (int)($z['city_id'] ?? 0),
        'city_name'          => $z['city_name'] ?? null,
        'center_lat'         => $z['center_lat'] ?? $z['city_lat'] ?? null,
        'center_lng'         => $z['center_lng'] ?? $z['city_lng'] ?? null,
        'radius_km'          => $z['radius_km'] !== null ? (float)$z['radius_km'] : null,
        'delivery_fee'       => (float)$z['delivery_fee'],
        'free_delivery_over' => $z['free_delivery_over'] !== null ? (float)$z['free_delivery_over'] : null,
        'min_order_value'    => $z['min_order_value']    !== null ? (float)$z['min_order_value']    : null,
        'estimated_minutes'  => (int)($z['estimated_minutes'] ?? 45),
        'is_merchant_zone'   => $isMerchantZone,
        'provider'           => $z['provider_id'] ? [
            'id'           => (int)$z['provider_id'],
            'name'         => $providerName,
            'type'         => $z['provider_type']  ?? null,
            'vehicle_type' => $z['vehicle_type']   ?? null,
            'is_online'    => (bool)($z['is_online'] ?? false),
            'is_active'    => (bool)($z['provider_active'] ?? true),
            'rating'       => $z['provider_rating'] !== null ? (float)$z['provider_rating'] : null,
            'entity_id'    => (int)($z['provider_entity_id'] ?? 0),
        ] : null,
    ];
}

/* ════════════════════════════════════════════════════════════
 *  ROUTE: coverage / zones
 * ════════════════════════════════════════════════════════════ */
if (isset($_GET['coverage']) || $subPath === 'zones') {
    ResponseFormatter::success([
        'zones' => $allZones,
        'count' => count($allZones),
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════════
 *  ROUTE: pickup points
 * ════════════════════════════════════════════════════════════ */
if (isset($_GET['pickup']) || $subPath === 'pickup') {
    $points = [];
    if ($eid > 0) {
        $points = $pdoList(
            "SELECT ep.id, ep.name, ep.address, ep.latitude, ep.longitude,
                    ep.city_id, ep.working_hours, ep.phone, ep.sort_order,
                    c.name AS city_name
               FROM entity_pickup_points ep
          LEFT JOIN cities c ON c.id = ep.city_id
              WHERE ep.entity_id = ? AND ep.tenant_id = ? AND ep.is_active = 1
              ORDER BY ep.sort_order ASC, ep.id ASC",
            [$eid, $tenantId]
        );
        if ($lat !== null && $lng !== null) {
            foreach ($points as &$pp) {
                $pp['distance_km'] = ($pp['latitude'] && $pp['longitude'])
                    ? round(dlv_distance($lat, $lng, (float)$pp['latitude'], (float)$pp['longitude']), 2)
                    : null;
            }
            unset($pp);
            usort($points, fn($a, $b) =>
                ($a['distance_km'] ?? PHP_INT_MAX) <=> ($b['distance_km'] ?? PHP_INT_MAX)
            );
        }
    }
    ResponseFormatter::success(['pickup_points' => $points]);
    exit;
}

/* ════════════════════════════════════════════════════════════
 *  ROUTE: providers list
 * ════════════════════════════════════════════════════════════ */
if ($subPath === 'providers') {
    $providers = $pdoList(
        "SELECT
            dp.id, dp.provider_type, dp.vehicle_type, dp.is_online,
            dp.is_active, dp.rating, dp.total_deliveries, dp.entity_id,
            dp.license_number,
            (SELECT u.name FROM users u JOIN tenant_users tu ON tu.user_id = u.id WHERE tu.id = dp.tenant_user_id LIMIT 1) AS name,
            dl.latitude  AS current_lat,
            dl.longitude AS current_lng,
            dl.updated_at AS location_updated_at,
            GROUP_CONCAT(DISTINCT pz.zone_id ORDER BY pz.zone_id SEPARATOR ',') AS zone_ids
           FROM delivery_providers dp
      LEFT JOIN driver_locations dl ON dl.provider_id = dp.id
      LEFT JOIN provider_zones   pz ON pz.provider_id = dp.id AND pz.is_active = 1
          WHERE dp.tenant_id = ? AND dp.is_active = 1
            AND (dp.entity_id = ? OR dp.entity_id IS NULL OR dp.entity_id = 0)
          GROUP BY dp.id
          ORDER BY dp.is_online DESC, dp.rating DESC",
        [$tenantId, $eid]
    );
    foreach ($providers as &$p) {
        $p['zone_ids']  = $p['zone_ids'] ? array_map('intval', explode(',', $p['zone_ids'])) : [];
        $p['is_online'] = (bool)$p['is_online'];
        $p['rating']    = $p['rating'] !== null ? (float)$p['rating'] : null;
    }
    unset($p);
    ResponseFormatter::success(['providers' => $providers]);
    exit;
}

/* ════════════════════════════════════════════════════════════
 *  MAIN ROUTE: lat + lng required beyond this point
 * ════════════════════════════════════════════════════════════ */
if ($lat === null || $lng === null) {
    ResponseFormatter::error('lat and lng are required', 400);
    exit;
}

/* ── 1. Zone matching ───────────────────────────────────── */
$matchedZone = null;
foreach ($allZones as $zone) {
    if (dlv_in_zone($lat, $lng, $zone)) {
        $matchedZone = $zone;
        break;
    }
}

/* ── 2. All providers for this entity ───────────────────── */
$providers = $pdoList(
    "SELECT
        dp.id, dp.provider_type, dp.vehicle_type, dp.is_online,
        dp.rating, dp.total_deliveries, dp.entity_id,
        (SELECT u.name FROM users u JOIN tenant_users tu ON tu.user_id = u.id WHERE tu.id = dp.tenant_user_id LIMIT 1) AS name,
        dl.latitude  AS current_lat,
        dl.longitude AS current_lng,
        dl.updated_at AS location_updated_at
       FROM delivery_providers dp
  LEFT JOIN driver_locations dl ON dl.provider_id = dp.id
      WHERE dp.tenant_id = ? AND dp.is_active = 1
        AND (dp.entity_id = ? OR dp.entity_id IS NULL OR dp.entity_id = 0)
      ORDER BY dp.is_online DESC, dp.rating DESC",
    [$tenantId, $eid]
);
foreach ($providers as &$p) {
    $p['is_online']   = (bool)$p['is_online'];
    $p['rating']      = $p['rating'] !== null ? (float)$p['rating'] : null;
    $p['distance_km'] = ($p['current_lat'] && $p['current_lng'])
        ? round(dlv_distance($lat, $lng, (float)$p['current_lat'], (float)$p['current_lng']), 2)
        : null;
}
unset($p);

/* ── 3. Pickup points ───────────────────────────────────── */
$pickupPoints = [];
if ($eid > 0) {
    $pickupPoints = $pdoList(
        "SELECT ep.id, ep.name, ep.address, ep.latitude, ep.longitude,
                ep.city_id, ep.working_hours, ep.phone, ep.sort_order,
                c.name AS city_name
           FROM entity_pickup_points ep
      LEFT JOIN cities c ON c.id = ep.city_id
          WHERE ep.entity_id = ? AND ep.tenant_id = ? AND ep.is_active = 1
          ORDER BY ep.sort_order ASC, ep.id ASC",
        [$eid, $tenantId]
    );
    foreach ($pickupPoints as &$pp) {
        $pp['distance_km'] = ($pp['latitude'] && $pp['longitude'])
            ? round(dlv_distance($lat, $lng, (float)$pp['latitude'], (float)$pp['longitude']), 2)
            : null;
    }
    unset($pp);
    usort($pickupPoints, fn($a, $b) =>
        ($a['distance_km'] ?? PHP_INT_MAX) <=> ($b['distance_km'] ?? PHP_INT_MAX)
    );
}

/* ── 4. Entity settings ─────────────────────────────────── */
$entitySettings = null;
if ($eid > 0) {
    $es = $pdoOne(
        "SELECT allow_cod, min_order_amount, preparation_time_minutes,
                free_delivery_min_order, delivery_radius_km,
                allow_preorders, max_daily_orders,
                default_payment_method, allow_multiple_payment_methods
           FROM entity_settings WHERE entity_id = ?",
        [$eid]
    );
    if ($es) {
        $entitySettings = [
            'allow_cod'                       => (bool)$es['allow_cod'],
            'min_order_amount'                => (float)$es['min_order_amount'],
            'preparation_time_minutes'        => (int)$es['preparation_time_minutes'],
            'free_delivery_min_order'         => (float)$es['free_delivery_min_order'],
            'delivery_radius_km'              => (int)$es['delivery_radius_km'],
            'allow_preorders'                 => (bool)$es['allow_preorders'],
            'max_daily_orders'                => (int)$es['max_daily_orders'],
            'default_payment_method'          => $es['default_payment_method'],
            'allow_multiple_payment_methods'  => (bool)$es['allow_multiple_payment_methods'],
        ];
    }
}

/* ── 5. Build options list ──────────────────────────────── */
$options = [];

if ($matchedZone) {
    $options[] = [
        'method'             => $matchedZone['is_merchant_zone'] ? 'merchant' : 'courier',
        'label'              => $matchedZone['zone_name'],
        'delivery_fee'       => $matchedZone['delivery_fee'],
        'free_delivery_over' => $matchedZone['free_delivery_over'],
        'min_order_value'    => $matchedZone['min_order_value'],
        'estimated_minutes'  => $matchedZone['estimated_minutes'],
        'zone_id'            => $matchedZone['id'],
        'provider'           => $matchedZone['provider'],
    ];
}

/* External courier companies */
foreach ($providers as $prov) {
    $options[] = [
        'method'             => 'courier',
        'label'              => $prov['name'] ?? ('Provider #' . $prov['id']),
        'delivery_fee'       => null,
        'free_delivery_over' => null,
        'min_order_value'    => null,
        'estimated_minutes'  => null,
        'zone_id'            => null,
        'provider'           => [
            'id'           => (int)$prov['id'],
            'name'         => $prov['name'],
            'type'         => $prov['provider_type'],
            'vehicle_type' => $prov['vehicle_type'],
            'is_online'    => $prov['is_online'],
            'rating'       => $prov['rating'],
            'distance_km'  => $prov['distance_km'],
        ],
    ];
}

/* Pickup option */
if (count($pickupPoints) > 0) {
    $options[] = [
        'method'             => 'pickup',
        'label'              => 'Self Pickup',
        'delivery_fee'       => 0.0,
        'free_delivery_over' => null,
        'min_order_value'    => null,
        'estimated_minutes'  => $entitySettings['preparation_time_minutes'] ?? null,
        'zone_id'            => null,
        'provider'           => null,
    ];
}

/* ── 6. Response ────────────────────────────────────────── */
ResponseFormatter::success([
    'coordinate'      => ['lat' => $lat, 'lng' => $lng],
    'matched_zone'    => $matchedZone ? [
        'id'                 => $matchedZone['id'],
        'name'               => $matchedZone['zone_name'],
        'type'               => $matchedZone['zone_type'],
        'delivery_fee'       => $matchedZone['delivery_fee'],
        'free_delivery_over' => $matchedZone['free_delivery_over'],
        'min_order_value'    => $matchedZone['min_order_value'],
        'estimated_minutes'  => $matchedZone['estimated_minutes'],
        'is_merchant_zone'   => $matchedZone['is_merchant_zone'],
        'provider'           => $matchedZone['provider'],
    ] : null,
    'options'         => $options,
    'all_zones'       => $allZones,
    'providers'       => $providers,
    'pickup_points'   => $pickupPoints,
    'entity_settings' => $entitySettings,
    'debug'           => [
        'zones_found'     => count($allZones),
        'entity_id'       => $eid,
        'tenant_id'       => $tenantId,
        'zone_matched'    => $matchedZone !== null,
    ],
]);
exit;