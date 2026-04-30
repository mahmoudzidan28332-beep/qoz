<?php
declare(strict_types=1);
/**
 * Public API sub-route: wishlist
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'wishlist') {
    $wishUserId = intval($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
    if (empty($wishUserId)) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }

    $wishSub = isset($segments[1]) ? trim($segments[1]) : '';

    // Instantiate repository
    require_once dirname(__DIR__, 2) . '/models/wishlists/repositories/PdoWishlistsRepository.php';
    require_once dirname(__DIR__, 2) . '/models/wishlists/services/WishlistsService.php';
    $wishlistRepo = new PdoWishlistsRepository($pdo);
    $wishlistService = new WishlistsService($wishlistRepo);

    /** Helper: get or create default wishlist for user */
    $getDefaultWishlist = function() use ($wishlistRepo, $wishUserId, $tenantId) {
        // tenant_id from GET may be null for POST requests; fall back to session value
        $wlTenantId = $tenantId ?? (int)($_SESSION['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
        $wl = $wishlistRepo->findDefaultByUser($wishUserId);
        if ($wl) return (int)$wl['id'];
        // Create default wishlist
        $wlName = ($_GET['lang'] ?? $_SESSION['user']['preferred_language'] ?? 'en') === 'ar' ? 'قائمة مفضلتي' : 'My Wishlist';
        return $wishlistRepo->createDefault($wishUserId, $wlTenantId, $wlName);
    };

    /** Helper: refresh total_items count */
    $refreshWishlistCount = function(int $wlId) use ($wishlistRepo) {
        try {
            $wishlistRepo->updateTotalItems($wlId);
        } catch (\RuntimeException $__) { /* cached count is optional */ }
    };

    // GET /api/public/wishlist — list items in default wishlist
    if ($wishSub === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $wlId = $getDefaultWishlist();
            $lang = $_GET['lang'] ?? $lang;
            $items = $wishlistRepo->listItems($wlId, $lang);
            ResponseFormatter::success(['wishlist_id' => $wlId, 'items' => $items, 'total' => count($items)]);
        } catch (\RuntimeException $ex) {
            ResponseFormatter::error('Failed to load wishlist: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // GET /api/public/wishlist/ids — just product IDs (for heart button state on page load)
    if ($wishSub === 'ids' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $wlId = $getDefaultWishlist();
            $ids = $wishlistRepo->listItemProductIds($wlId);
            ResponseFormatter::success(['ids' => $ids]);
        } catch (\RuntimeException $ex) {
            ResponseFormatter::success(['ids' => []]);
        }
        exit;
    }

    // POST /api/public/wishlist/add
    if ($wishSub === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $entityId  = (int)($_POST['entity_id']  ?? 1);
        if (!$productId) { ResponseFormatter::error('product_id required', 422); exit; }

        // 🔒 SECURITY: Verify that the entity belongs to the tenant (IDOR Protection)
        if (class_exists('MultiTenantValidator')) {
            if ($entityId > 1 && !MultiTenantValidator::checkOwnership($pdo, 'entities', $entityId, $tenantId)) {
                ResponseFormatter::error('Invalid entity_id for this tenant', 403);
                exit;
            }
        }
        try {
            // Auto-resolve tenant_id from product's tenant if not provided
            $wlItemTenantId = $tenantId;
            if (!$wlItemTenantId) {
                $wlItemTenantId = $wishlistRepo->findProductTenantId($productId);
            }
            if (!$wlItemTenantId) {
                $wlItemTenantId = (int)($_SESSION['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
            }
            $wlId = $getDefaultWishlist();
            // Check if already in wishlist (including soft-deleted → restore)
            $row = $wishlistRepo->findItem($wlId, $productId);
            if ($row) {
                if ($row['removed_at'] !== null) {
                    // Restore soft-deleted item
                    $wishlistRepo->restoreItem((int)$row['id']);
                }
                // else already active — no-op
            } else {
                $wishlistRepo->addItem($wlId, $productId, $entityId, $wlItemTenantId);
            }
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true, 'wishlist_id' => $wlId], 'Added to wishlist', 201);
        } catch (\RuntimeException $ex) {
            ResponseFormatter::error('Failed to add to wishlist: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // POST /api/public/wishlist/remove
    if ($wishSub === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!$productId) { ResponseFormatter::error('product_id required', 422); exit; }
        try {
            $wlId = $getDefaultWishlist();
            // Soft delete
            $wishlistRepo->softRemoveItem($wlId, $productId);
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true]);
        } catch (\RuntimeException $ex) {
            ResponseFormatter::error('Failed to remove: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // POST /api/public/wishlist/clear
    if ($wishSub === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $wlId = $getDefaultWishlist();
            $wishlistRepo->softRemoveAllItems($wlId);
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true]);
        } catch (\RuntimeException $ex) {
            ResponseFormatter::error('Failed to clear: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    ResponseFormatter::notFound('Unknown wishlist endpoint');
    exit;
}

/* -------------------------------------------------------
 * Route: Recently Viewed Products
 * GET  /api/public/recent          — list (last 20, newest first)
 * POST /api/public/recent/add      — record a view
 * ----------------------------------------------------- */