<?php
declare(strict_types=1);
/**
 * Public API sub-route: delivery
 * Handles fee calculation and zone detection for customers.
 * Path: /api/public/delivery
 */

if ($first === 'delivery') {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'OPTIONS') {
        if (!headers_sent()) {
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            http_response_code(204);
        }
        exit;
    }

    if ($method !== 'GET') {
        ResponseFormatter::error('Method not allowed', 405);
        exit;
    }

    $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
    $eid = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
    
    if ($tenantId === null) {
        ResponseFormatter::error('tenant_id is required', 400);
        exit;
    }

    if ($lat === null || $lng === null) {
        ResponseFormatter::error('lat and lng are required', 400);
        exit;
    }

    /** @var PDO $pdo */
    /** @var callable $pdoList */
    
    // 1. Fetch available zones for this merchant and global courier zones.
    // We use a broader UNION-based approach to capture zones linked via different paths.
    $zones = $pdoList(
        "SELECT DISTINCT dz.*, 
                (SELECT dp.provider_type FROM delivery_providers dp WHERE dp.id = dz.provider_id LIMIT 1) as direct_type,
                (SELECT dp.entity_id FROM delivery_providers dp WHERE dp.id = dz.provider_id LIMIT 1) as direct_eid,
                c.latitude AS city_lat, c.longitude AS city_lng
           FROM delivery_zones dz 
           LEFT JOIN cities c ON dz.city_id = c.id
          WHERE dz.tenant_id = ? AND dz.is_active = 1 
            AND (
              -- Direct link
              dz.provider_id IN (SELECT id FROM delivery_providers WHERE tenant_id = ? AND (entity_id = ? OR entity_id IS NULL OR entity_id = 0 OR provider_type != 'entity_driver'))
              OR
              -- Many-to-many link
              dz.id IN (SELECT zone_id FROM provider_zones pz JOIN delivery_providers dp ON pz.provider_id = dp.id WHERE dp.tenant_id = ? AND (dp.entity_id = ? OR dp.entity_id IS NULL OR dp.entity_id = 0 OR dp.provider_type != 'entity_driver'))
              OR
              -- Default/Global zones for this tenant
              dz.provider_id = 0 OR dz.provider_id IS NULL
            )
          ORDER BY CASE WHEN dz.zone_type = 'polygon' THEN 1 WHEN dz.zone_type = 'radius' THEN 2 ELSE 3 END ASC",
        [$tenantId, $tenantId, $eid, $tenantId, $eid]
    );

    $finalZones = [];
    foreach ($zones as $z) {
        $isMerchant = false;
        // Check if this zone is linked to our entity via direct link OR pz table
        if ((isset($z['direct_eid']) && (int)$z['direct_eid'] === $eid) || (int)$z['entity_id'] === $eid) {
            $isMerchant = true;
        } else {
            // Check provider_zones for merchant link
            $check = $pdoOne("SELECT 1 FROM provider_zones pz JOIN delivery_providers dp ON pz.provider_id = dp.id WHERE pz.zone_id = ? AND dp.entity_id = ? LIMIT 1", [$z['id'], $eid]);
            if ($check) $isMerchant = true;
        }

        $finalZones[] = [
            'id'             => $z['id'],
            'name'           => $z['zone_name'],
            'type'           => $z['zone_type'],
            'value'          => $z['zone_value'],
            'center_lat'     => $z['center_lat'] ?? $z['city_lat'],
            'center_lng'     => $z['center_lng'] ?? $z['city_lng'],
            'radius_km'      => $z['radius_km'],
            'delivery_fee'   => (float)$z['delivery_fee'],
            'is_merchant'    => $isMerchant
        ];
    }
    
    // Add debug info to results if needed
    $debug = ['eid' => $eid, 'tenant' => $tenantId, 'count' => count($finalZones)];

    // If 'coverage' is requested, return all zones for drawing
    if (isset($_GET['coverage'])) {
        ResponseFormatter::success([
            'zones' => $finalZones
        ]);
        exit;
    }

    $zones = $finalZones; // Use normalized list for calculations

    $matchedZone = null;

    // Helper: Haversine distance
    function getDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    // Helper: Point in Polygon (simplified)
    function isPointInPolygon(float $lat, float $lng, $geojson): bool {
        if (empty($geojson)) return false;
        try {
            $data = is_string($geojson) ? json_decode($geojson, true) : $geojson;
            if (!$data || !isset($data['type']) || !isset($data['coordinates'])) return false;
            
            // Assuming GeoJSON format: {"type":"Polygon","coordinates":[[[lng,lat],...]]}
            if ($data['type'] === 'Polygon') {
                $polygon = $data['coordinates'][0];
                $inside = false;
                $n = count($polygon);
                for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                    $xi = $polygon[$i][1]; $yi = $polygon[$i][0];
                    $xj = $polygon[$j][1]; $yj = $polygon[$j][0];
                    if ((($yi > $lng) != ($yj > $lng)) && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi)) {
                        $inside = !$inside;
                    }
                }
                return $inside;
            }
        } catch (Throwable $e) { return false; }
        return false;
    }

    foreach ($zones as $zone) {
        $data = @json_decode($zone['zone_value'], true);
        if (!$data) continue;

        $type = strtolower($data['type'] ?? $zone['zone_type'] ?? '');
        $isInside = false;

        if ($type === 'polygon') {
            $isInside = isPointInPolygon($lat, $lng, $data);
        } elseif ($type === 'radius' || $type === 'circle') {
            $center = $data['center'] ?? [$zone['center_lat'], $zone['center_lng']];
            $radius = $data['radius'] ?? ($zone['radius_km'] * 1000); // meters or km? Admin uses meters in JSON
            if ($center && $radius) {
                // If it's the custom JSON radius, it's in meters. If DB radius_km, it's km.
                $r_km = isset($data['radius']) ? ($data['radius'] / 1000) : (float)$zone['radius_km'];
                $dist = getDistance($lat, $lng, (float)$center[0], (float)$center[1]);
                if ($dist <= $r_km) $isInside = true;
            }
        } elseif ($type === 'rectangle') {
            $b = $data['bounds']; // [[s,w],[n,e]]
            if ($b && count($b) >= 2) {
                if ($lat >= $b[0][0] && $lat <= $b[1][0] && $lng >= $b[0][1] && $lng <= $b[1][1]) {
                    $isInside = true;
                }
            }
        }

        if ($isInside) {
            $matchedZone = $zone;
            break;
        }
    }

    // 2. Fetch independent providers (Shipping Companies) always available as backup
    $couriers = $pdoList(
        "SELECT dp.id, dp.provider_type, dp.vehicle_type, du.name AS provider_name
           FROM delivery_providers dp
           JOIN tenant_users du ON dp.tenant_user_id = du.id
          WHERE dp.tenant_id = ? AND dp.provider_type IN ('company', 'independent_driver') AND dp.is_active = 1",
        [$tenantId]
    );

    $results = [];

    // If merchant delivery is available
    if ($matchedZone) {
        $results[] = [
            'type'         => 'merchant',
            'id'           => $matchedZone['id'],
            'name'         => $matchedZone['zone_name'],
            'fee'          => (float)$matchedZone['delivery_fee'],
            'estimated'    => $matchedZone['estimated_minutes'],
            'is_merchant'  => true
        ];
    }

    // Add couriers (External)
    foreach ($couriers as $courier) {
        // Here we could calculate standard fees or fetch from a courier_fees table
        // For now, assuming a flat fee or placeholder
        $results[] = [
            'type'         => 'courier',
            'id'           => $courier['id'],
            'name'         => $courier['provider_name'],
            'fee'          => 25.00, // Placeholder for external shipping
            'estimated'    => 120,    // 2 hours placeholder
            'is_merchant'  => false
        ];
    }

    ResponseFormatter::success([
        'lat'     => $lat,
        'lng'     => $lng,
        'options' => $results
    ]);
    exit;
}
