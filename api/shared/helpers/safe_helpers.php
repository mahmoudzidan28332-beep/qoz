<?php
// api/helpers/safe_helpers.php
// Contains safe_htmlspecialchars helper to avoid type errors when non-strings are passed.
declare(strict_types=1);

if (!function_exists('resolve_tenant_id')) {
    /**
     * Resolve tenant_id from trusted sources only (NEVER from $_GET for regular users).
     *
     * - Regular/tenant users  → always use $_SESSION['tenant_id']; ignores any GET/POST value.
     * - Super-admins          → may optionally pass ?tenant_id=X as an admin filter;
     *                           falls back to their own session tenant when not provided.
     *
     * Returns null when no tenant can be established (caller should return 401).
     */
    function resolve_tenant_id(): ?int
    {
        if (isset($GLOBALS['ADMIN_UI'])) {
            if (!empty($GLOBALS['ADMIN_UI']['is_platform_admin']) && isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
                return (int)$_GET['tenant_id'];
            }
            return $GLOBALS['ADMIN_UI']['tenant_id'] ?? null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isPlatformAdmin = !empty($_SESSION['platform_admin']);
        $sessionTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;

        // 1. Platform Admins can override the scope via GET
        if ($isPlatformAdmin) {
            if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
                return (int)$_GET['tenant_id'];
            }
            // If no override, return their session tenant (which is usually null/0 for platform)
            return $sessionTenantId;
        }

        // 2. For regular users (including tenant admins), ALWAYS force their session tenant
        // Even if they have 'super_admin' role in their tenant, they cannot bypass isolation.
        return $sessionTenantId;
    }
}

if (!function_exists('admin_tenant_id')) {
    /**
     * Return the tenant_id that an admin/super-admin wants to act upon.
     *
     * Rules (mirrors resolve_tenant_id but named to make intent explicit):
     *  - Super-admins may pass ?tenant_id=X as a GET parameter.
     *    If absent, their own session tenant is used.
     *  - All other roles always receive their session tenant_id.
     *  - Returns null when no tenant can be established.
     *
     * This is the ONLY approved way to obtain a tenant_id for TenantContext::set().
     * Never read $_GET['tenant_id'] or $_POST['tenant_id'] directly in route files.
     */
    function admin_tenant_id(): ?int
    {
        return resolve_tenant_id();
    }
}

if (!function_exists('is_super_admin')) {
    /**
     * Returns true when the current session user has the 'super_admin' role or is platform admin.
     */
    function is_super_admin(): bool
    {
        if (isset($GLOBALS['ADMIN_UI'])) {
            return !empty($GLOBALS['ADMIN_UI']['is_super_admin']) || !empty($GLOBALS['ADMIN_UI']['is_platform_admin']);
        }

        if (isset($GLOBALS['ADMIN_IDENTITY']) && $GLOBALS['ADMIN_IDENTITY'] instanceof \Shared\Application\Auth\UserIdentity) {
            $identity = $GLOBALS['ADMIN_IDENTITY'];
            return in_array('super_admin', $identity->roles(), true) 
                || $identity->roleId() === 1 
                || $identity->isPlatformAdmin();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!empty($_SESSION['platform_admin'])) {
            return true;
        }

        $roles = $_SESSION['user']['roles'] ?? ($_SESSION['roles'] ?? []);
        $roleId = (int)($_SESSION['user']['role_id'] ?? ($_SESSION['role_id'] ?? 0));
        return in_array('super_admin', (array)$roles, true) || $roleId === 1;
    }
}

if (!function_exists('is_platform_admin')) {
    /**
     * Returns true when the current user is a platform-level administrator.
     */
    function is_platform_admin(): bool
    {
        if (isset($GLOBALS['ADMIN_UI'])) {
            return !empty($GLOBALS['ADMIN_UI']['is_platform_admin']);
        }

        if (isset($GLOBALS['ADMIN_IDENTITY']) && $GLOBALS['ADMIN_IDENTITY'] instanceof \Shared\Application\Auth\UserIdentity) {
            return $GLOBALS['ADMIN_IDENTITY']->isPlatformAdmin();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return !empty($_SESSION['platform_admin']);
    }
}

if (!function_exists('get_user_id')) {
    /**
     * Resolve the current authenticated user id from the most reliable context available.
     */
    function get_user_id(): ?int
    {
        if (!empty($GLOBALS['ADMIN_USER']['id'])) {
            return (int)$GLOBALS['ADMIN_USER']['id'];
        }

        if (isset($GLOBALS['ADMIN_IDENTITY']) && $GLOBALS['ADMIN_IDENTITY'] instanceof \Shared\Application\Auth\UserIdentity) {
            $id = $GLOBALS['ADMIN_IDENTITY']->id();
            return $id !== null ? (int)$id : null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user']['id'])) {
            return (int)$_SESSION['user']['id'];
        }

        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        return null;
    }
}

if (!function_exists('resolve_product_scope_tenant_id')) {
    /**
     * Resolve tenant scope for product-related routes.
     * Falls back to looking up tenant_id from product/variant identifiers when the
     * current session is a platform admin and no explicit tenant context was passed.
     */
    function resolve_product_scope_tenant_id(PDO $pdo, array $payload = []): ?int
    {
        $tenantId = resolve_tenant_id();
        if ($tenantId !== null && $tenantId > 0) {
            return $tenantId;
        }

        $productId = null;
        if (isset($payload['product_id']) && is_numeric($payload['product_id'])) {
            $productId = (int)$payload['product_id'];
        } elseif (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
            $productId = (int)$_GET['product_id'];
        }

        if ($productId !== null && $productId > 0) {
            if (!class_exists('PdoProductsRepository')) {
                require_once dirname(__DIR__, 2) . '/v1/models/products/repositories/PdoProductsRepository.php';
            }
            $repo = new PdoProductsRepository($pdo);
            $found = $repo->findTenantIdByProductId($productId);
            if ($found !== null) {
                return $found;
            }
        }

        $variantId = null;
        if (isset($payload['variant_id']) && is_numeric($payload['variant_id'])) {
            $variantId = (int)$payload['variant_id'];
        } elseif (isset($_GET['variant_id']) && is_numeric($_GET['variant_id'])) {
            $variantId = (int)$_GET['variant_id'];
        }

        if ($variantId !== null && $variantId > 0) {
            if (!class_exists('PdoProductsRepository')) {
                require_once dirname(__DIR__, 2) . '/v1/models/products/repositories/PdoProductsRepository.php';
            }
            $repo = new PdoProductsRepository($pdo);
            $found = $repo->findTenantIdByVariantId($variantId);
            if ($found !== null) {
                return $found;
            }
        }

        return $tenantId;
    }
}

if (!function_exists('safe_htmlspecialchars')) {
    /**
     * Convert input to string safely and call htmlspecialchars with UTF-8.
     * - Scalars cast to string
     * - null -> ''
     * - objects with __toString cast to string
     * - arrays/other objects -> print_r(...) to readable string
     */
    function safe_htmlspecialchars($value, int $flags = ENT_QUOTES, string $encoding = 'UTF-8', bool $double_encode = true): string {
        if (is_null($value)) return '';
        if (is_scalar($value)) return htmlspecialchars((string)$value, $flags, $encoding, $double_encode);
        if (is_object($value) && method_exists($value, '__toString')) {
            return htmlspecialchars((string)$value, $flags, $encoding, $double_encode);
        }
        // Arrays or other objects: safe printable representation
        return htmlspecialchars(print_r($value, true), $flags, $encoding, $double_encode);
    }
}

if (!function_exists('verify_entity_ownership')) {
    /**
     * Verify that the given entity_id belongs to the provided tenant_id.
     * Terminate request with 403 Forbidden if verification fails.
     *
     * @param PDO $pdo
     * @param int|string $entityId
     * @param int|null $tenantId
     * @return void
     */
    function verify_entity_ownership(PDO $pdo, $entityId, ?int $tenantId): void
    {
        if (empty($entityId) || !is_numeric($entityId)) {
            return; // invalid ID, let the controller handle it
        }

        if ($tenantId === null) {
            // If no tenant context, we cannot verify ownership. 
            // In a multi-tenant app, this is usually a 401/403.
            return;
        }

        // Super admins skip this check if they are acting on behalf of someone else
        if (function_exists('is_super_admin') && is_super_admin()) {
            return;
        }

        try {
            // 💡 Architecture: Move DB calls to Repositories
            if (!class_exists('PdoEntitiesRepository')) {
                require_once dirname(__DIR__, 2) . '/v1/models/entities/repositories/PdoEntitiesRepository.php';
            }
            $repo = new PdoEntitiesRepository($pdo);
            
            if (!$repo->verifyOwnership((int)$entityId, $tenantId)) {
                if (class_exists('ResponseFormatter')) {
                    ResponseFormatter::error('Forbidden: You do not have access to this entity.', 403);
                } else {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have access to this entity.']);
                }
                exit;
            }
        } catch (\PDOException|\RuntimeException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'ownership_verification_failed', ['error' => $e->getMessage()]);
            }
            if (class_exists('ResponseFormatter')) {
                ResponseFormatter::error('Internal Server Error during security check.', 500);
            } else {
                http_response_code(500);
                echo "Internal Security Error";
            }
            exit;
        }
    }
}
