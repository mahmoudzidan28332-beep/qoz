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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionTenantId = isset($_SESSION['tenant_id']) && (int)$_SESSION['tenant_id'] > 0
            ? (int)$_SESSION['tenant_id']
            : null;

        $roles        = $_SESSION['user']['roles'] ?? [];
        $isSuperAdmin = in_array('super_admin', $roles, true);

        if ($isSuperAdmin) {
            // Super-admins may filter by any tenant via ?tenant_id=X
            if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
                return (int)$_GET['tenant_id'];
            }
            return $sessionTenantId; // their own tenant or null (all tenants view)
        }

        // All other users: trust only the session
        return $sessionTenantId;
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