<?php
/**
 * frontend/includes/EntityResolutionService.php
 *
 * Centralized service for dynamic entity (branch) resolution.
 * This is the SINGLE SOURCE OF TRUTH for determining which branch
 * serves the user in any given context (Discovery, Product, or Cart).
 */

class EntityResolutionService {
    private static ?array $resolvedCache = null;

    /**
     * Resolves the active entity context.
     * 
     * @param int $tenantId
     * @param array $params Optional context hints (product_id, entity_id from URL, etc.)
     * @return array The resolved entity payload or null structure.
     */
    public static function resolve(int $tenantId, array $params = []): array {
        // 1. Request-level caching
        if (self::$resolvedCache !== null) {
            return self::$resolvedCache;
        }

        $lang     = (string)($params['lang'] ?? 'en');
        $userId   = (int)($params['user_id'] ?? 0);
        $location = $_SESSION['pub_entity_location'][$tenantId] ?? [];
        $lat = isset($location['lat']) ? (float)$location['lat'] : null;
        $lng = isset($location['lng']) ? (float)$location['lng'] : null;

        // --- RESOLUTION PRIORITY ---

        // A. Cart Lock (Highest Priority)
        // If an item is in the cart, it locks the fulfillment entity.
        $cartEntityId = self::getLockedCartEntityId($tenantId, $userId);
        if ($cartEntityId > 0) {
            $entity = self::loadEntityContext($tenantId, $cartEntityId, $lang, $lat, $lng);
            if ($entity) {
                return self::$resolvedCache = self::formatResponse($entity, 'cart_lock');
            }
        }

        // B. Product Context
        // If viewing a product, find the NEAREST branch with STOCK.
        $productId = (int)($params['product_id'] ?? 0);
        if ($productId > 0) {
            $entity = self::findNearestWithStock($tenantId, $productId, $lat, $lng, $lang);
            if ($entity) {
                return self::$resolvedCache = self::formatResponse($entity, 'product_nearest');
            }
        }

        // C. Explicit Request (URL param)
        $reqId = (int)($params['entity_id'] ?? 0);
        if ($reqId > 0) {
            $entity = self::loadEntityContext($tenantId, $reqId, $lang, $lat, $lng);
            if ($entity) {
                return self::$resolvedCache = self::formatResponse($entity, 'request');
            }
        }

        // D. Geography Resolution
        // If location is known, find the nearest overall available branch.
        if (!($params['is_discovery'] ?? false)) {
            if ($lat !== null && $lng !== null) {
                $nearest = self::findNearestOverall($tenantId, $lat, $lng, $lang);
                if ($nearest) {
                    return self::$resolvedCache = self::formatResponse($nearest, 'geo_nearest');
                }
            }
        }

        // E. Fallback: Tenant Default (always run, even in discovery mode)
        // Cart operations require a valid entity_id, so we must resolve one.
        $fallback = self::getTenantDefault($tenantId, $lang, $lat, $lng);
        if ($fallback) {
            $response = self::formatResponse($fallback, 'fallback');
            // In discovery mode, mark it as discovery so the UI doesn't show
            // branch-specific delivery info, but still provides an entity_id
            if ($params['is_discovery'] ?? false) {
                $response['mode'] = 'discovery';
            }
            return self::$resolvedCache = $response;
        }

        // Discovery Mode (Neutral) — no entities at all in DB
        return self::$resolvedCache = [
            'id'                 => null,
            'name'               => null,
            'slug'               => null,
            'distance_km'        => null,
            'delivery_radius_km' => 0,
            'is_available'       => false,
            'is_open_now'        => false,
            'mode'               => 'discovery',
            'source'             => 'none',
            'resolved_at'        => date('c'),
            'city'               => '...'
        ];
    }

    /**
     * Checks if there's a locked entity ID in the cart.
     */
    /**
     * Checks if there's a locked entity ID in the cart (Session or DB).
     */
    private static function getLockedCartEntityId(int $tenantId, int $userId = 0): int {
        // 1. Check session cart first (fastest)
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $first = reset($_SESSION['cart']);
            if (isset($first['entity_id'])) {
                return (int)$first['entity_id'];
            }
        }

        // 2. Check DB if user is logged in
        if ($userId > 0) {
            $pdo = pub_get_pdo();
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT entity_id FROM carts WHERE user_id = ? AND tenant_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$userId, $tenantId]);
                    return (int)$stmt->fetchColumn() ?: 0;
                } catch (\RuntimeException $e) {}
            }
        }

        return 0;
    }

    /**
     * Loads a single entity's context data.
     */
    private static function loadEntityContext(int $tenantId, int $entityId, string $lang, ?float $lat, ?float $lng): ?array {
        if (!function_exists('pub_list_entity_contexts')) return null;
        $list = pub_list_entity_contexts($tenantId, $lang, $lat, $lng, 1, [$entityId]);
        return $list[0] ?? null;
    }

    /**
     * Finds the nearest entity providing a specific product with stock > 0.
     */
    private static function findNearestWithStock(int $tenantId, int $productId, ?float $lat, ?float $lng, string $lang): ?array {
        $pdo = pub_get_pdo();
        if (!$pdo) return null;

        try {
            // Find all entities with stock for this product
            $stmt = $pdo->prepare("
                SELECT ep.entity_id 
                FROM entity_products ep
                JOIN entities e ON e.id = ep.entity_id
                WHERE ep.product_id = ? 
                  AND ep.stock_quantity > 0 
                  AND ep.is_active = 1
                  AND e.tenant_id = ?
                  AND e.status = 'active'
            ");
            $stmt->execute([$productId, $tenantId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($ids)) return null;

            // Resolve nearest from these IDs
            $candidates = pub_list_entity_contexts($tenantId, $lang, $lat, $lng, 8, $ids);
            
            // pub_list_entity_contexts already sorts by distance if lat/lng are provided
            foreach ($candidates as $c) {
                if ($c['is_available']) return $c;
            }
            return $candidates[0] ?? null;
        } catch (\RuntimeException $e) {
            error_log('[EntityResolutionService] findNearestWithStock error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Finds the nearest available branch regardless of product.
     */
    private static function findNearestOverall(int $tenantId, float $lat, float $lng, string $lang): ?array {
        $candidates = pub_list_entity_contexts($tenantId, $lang, $lat, $lng, 8);
        foreach ($candidates as $c) {
            if ($c['is_available']) return $c;
        }
        return $candidates[0] ?? null;
    }

    /**
     * Fallback to the first available branch.
     */
    private static function getTenantDefault(int $tenantId, string $lang, ?float $lat, ?float $lng): ?array {
        $candidates = pub_list_entity_contexts($tenantId, $lang, $lat, $lng, 5);
        foreach ($candidates as $c) {
            if ($c['is_available']) return $c;
        }
        return $candidates[0] ?? null;
    }

    /**
     * Formats the internal entity structure into a public context payload.
     */
    private static function formatResponse(array $entity, string $source): array {
        return [
            'id'                 => (int)$entity['id'],
            'name'               => (string)($entity['name'] ?? ''),
            'slug'               => (string)($entity['slug'] ?? ''),
            'distance_km'        => isset($entity['distance_km']) ? (float)$entity['distance_km'] : null,
            'delivery_radius_km' => (float)($entity['delivery_radius_km'] ?? 0),
            'is_available'       => !empty($entity['is_available']),
            'is_open_now'        => !empty($entity['is_open_now']),
            'mode'               => 'fulfillment', // Indicates a specific branch is decided
            'source'             => $source,
            'resolved_at'        => date('c'),
            'city'               => self::extractCity($entity) // Useful for header "Deliver to: [City]"
        ];
    }

    private static function extractCity(array $entity): string {
        $addr = (string)($entity['address_line2'] ?? $entity['address_line1'] ?? '');
        // Simple extraction for UI display
        $parts = explode(',', $addr);
        return trim(end($parts)) ?: '...';
    }
}
