<?php
declare(strict_types=1);
/**
 * Public API sub-route: register
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'register') {
    $regSub     = strtolower($segments[1] ?? '');
    $regUser    = $_SESSION['user'] ?? null;
    $regUserId  = isset($regUser['id']) ? (int)$regUser['id'] : (int)($_SESSION['user_id'] ?? 0);

    if (!$regUserId) {
        ResponseFormatter::error('Login required', 401); exit;
    }

    if ($regSub === 'entity' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $storeName   = trim($_POST['store_name']   ?? '');
        $slug        = trim($_POST['slug']         ?? '');
        $phone       = trim($_POST['phone']        ?? '');
        $email       = trim($_POST['email']        ?? '');
        $vendorType  = trim($_POST['vendor_type']  ?? 'product_seller');
        $storeType   = trim($_POST['store_type']   ?? 'individual');
        $websiteUrl  = trim($_POST['website_url']  ?? '');

        if (!$storeName || !$phone || !$email) {
            ResponseFormatter::error('store_name, phone and email are required', 422); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseFormatter::error('Invalid email address', 422); exit;
        }
        // Normalise slug: lowercase, replace spaces with hyphens, strip non-alnum
        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $storeName));
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        $slug = trim($slug, '-');

        // Validate vendor_type and store_type enums
        $allowedVendorTypes = ['product_seller', 'service_provider', 'both'];
        $allowedStoreTypes  = ['individual', 'company', 'brand'];
        if (!in_array($vendorType, $allowedVendorTypes, true)) $vendorType = 'product_seller';
        if (!in_array($storeType, $allowedStoreTypes, true)) $storeType = 'individual';

        if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }
        try {
            // Check slug uniqueness
            $existing = $pdoOne('SELECT id FROM entities WHERE slug = ? LIMIT 1', [$slug]);
            if ($existing) {
                $slug = $slug . '-' . substr(md5(uniqid()), 0, 6);
            }
            $regTenantId = (int)($_POST['tenant_id'] ?? 0)
                ?: ($tenantId ?? (int)($_SESSION['pub_tenant_id'] ?? $_SESSION['tenant_id'] ?? 1));
            $entitiesRepo = new PdoEntitiesRepository($pdo);
            $entitiesService = new EntitiesService($entitiesRepo);
            $newEntityId = $entitiesRepo->createPublic(
                $regTenantId, $regUserId, $storeName, $slug,
                $vendorType, $storeType, $phone, $email,
                $websiteUrl ?: null
            );
            ResponseFormatter::success(['ok' => true, 'id' => $newEntityId, 'slug' => $slug, 'status' => 'pending'],
                'Application submitted — pending review', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Registration failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    if ($regSub === 'tenant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tName   = trim($_POST['name']   ?? '');
        $tDomain = trim($_POST['domain'] ?? '') ?: null;

        if (!$tName) { ResponseFormatter::error('name is required', 422); exit; }
        if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }
        try {
            if ($tDomain) {
                $existing = $pdoOne('SELECT id FROM tenants WHERE domain = ? LIMIT 1', [$tDomain]);
                if ($existing) { ResponseFormatter::error('Domain already in use', 409); exit; }
            }
            $tenantsRepo = new PdoTenantsRepository($pdo);
            $newTenantId = $tenantsRepo->createTenantPublic($tName, $tDomain, $regUserId);
            // Link the user as owner in tenant_users
            try {
                $tenantUsersRepo = new PdoTenant_usersRepository($pdo);
                $tenantUsersRepo->addUserToTenant($newTenantId, $regUserId);
            } catch (Throwable $_) { /* tenant_users is optional */ }
            ResponseFormatter::success(['ok' => true, 'id' => $newTenantId], 'Tenant created', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Tenant creation failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    ResponseFormatter::notFound('Unknown register endpoint');
    exit;
}

// ── Wishlist ─────────────────────────────────────────────────────────────────