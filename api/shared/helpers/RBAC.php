<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/repositories/RbacRepository.php';

/**
 * htdocs/api/shared/helpers/RBAC.php
 *
 * Production-ready RBAC helper with flexible resource_permissions handling.
 * - resource_permissions rows with tenant_id = NULL are global (defaults).
 * - Tenant-specific and role-specific rows override/extend global rows.
 * - Precedence (applied in this order): global -> role-global -> tenant-global -> role+tenant (most specific)
 *   * For each permission flag, a more-specific row overrides a less-specific value only if that column is present.
 *   * If a more-specific row omits a flag (NULL), value from prior level remains.
 *
 * Caching: optional Redis (configurable via env RBAC_REDIS_ENABLED)
 * Notes: requires PDO (passed in ctor or from $GLOBALS['ADMIN_DB']).
 */

final class RBAC
{
    private ?PDO $pdo = null;
    private $redis = null; // Redis instance (no strict typing to allow mocks)
    private ?CacheManager $cacheManager = null;
    private int $tenantId = 1;
    private array $config = [];

    private const CACHE_PREFIX = 'rbac:';
    private const DEFAULT_CACHE_TTL = 1800; // seconds
    private const CONFIG_KEYS = [
        'redis_enabled' => true,
        'cache_ttl' => self::DEFAULT_CACHE_TTL,
        'audit_enabled' => true,
        'rate_limit_enabled' => true,
        'async_enabled' => false,
        'max_bulk_size' => 100,
        'resource_permissions_enabled' => true,
    ];

    public function __construct(?PDO $pdo = null, $redis = null, int $tenantId = 1)
    {
        $this->pdo = $pdo ?? $this->resolvePdoConnection();
        $this->tenantId = $tenantId;
        $this->config = $this->loadConfiguration();
        $this->redis = $redis ?? $this->resolveRedisConnection();
        $this->cacheManager = class_exists('CacheManager', false) ? CacheManager::getInstance() : null;
        $this->ensureSession();
    }

    private function loadConfiguration(): array
    {
        $cfg = self::CONFIG_KEYS;
        foreach ($cfg as $k => $default) {
            $env = getenv('RBAC_' . strtoupper($k));
            if ($env !== false && $env !== null) {
                if (in_array(strtolower($env), ['true','false','1','0'], true)) {
                    $cfg[$k] = filter_var($env, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_numeric($env)) {
                    $cfg[$k] = (int)$env;
                } else {
                    $cfg[$k] = $env;
                }
            }
        }
        return $cfg;
    }

    private function resolvePdoConnection(): ?PDO
    {
        if (!empty($GLOBALS['ADMIN_DB']) && $GLOBALS['ADMIN_DB'] instanceof PDO) {
            return $GLOBALS['ADMIN_DB'];
        }
        if (!empty($GLOBALS['CONTAINER']['pdo']) && $GLOBALS['CONTAINER']['pdo'] instanceof PDO) {
            return $GLOBALS['CONTAINER']['pdo'];
        }
        return null;
    }

    private function resolveRedisConnection()
    {
        if (!$this->config['redis_enabled']) {
            return null;
        }
        if (!class_exists('Redis', false) && !class_exists('RedisHelper', false)) {
            return null;
        }
        try {
            if (class_exists('RedisHelper', false)) {
                return RedisHelper::instance();
            }
            // If a plain Redis is available via global, try it
            return $GLOBALS['REDIS'] ?? null;
        } catch (\RuntimeException $e) {
            $this->logError('Redis init failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
            $sessionConfig = dirname(__DIR__) . '/config/session.php';
            if (is_file($sessionConfig)) {
                require_once $sessionConfig;
            } else {
                @session_start([
                    'cookie_httponly' => true,
                    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'cookie_samesite' => 'Lax',
                ]);
            }
        }
    }

    private function cacheKey(string $suffix, int $userId): string
    {
        return self::CACHE_PREFIX . "{$this->tenantId}:{$suffix}:{$userId}";
    }

    private function getFromCache(string $key)
    {
        if (!empty($this->config['redis_enabled']) && $this->redis) {
            try {
                $v = $this->redis->get($key);
                if ($v !== false && $v !== null) {
                    return is_string($v) ? (json_decode($v, true) ?? $v) : $v;
                }
            } catch (\RuntimeException $e) {
                $this->logError('cache read failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        if ($this->cacheManager) {
            return $this->cacheManager->get($key);
        }

        return null;
    }

    private function setCache(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? (int)$this->config['cache_ttl'];

        if (!empty($this->config['redis_enabled']) && $this->redis) {
            try {
                return (bool)$this->redis->setex($key, $ttl, json_encode($value));
            } catch (\RuntimeException $e) {
                $this->logError('cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        if ($this->cacheManager) {
            $this->cacheManager->set($key, $value, $ttl, ['rbac', 'tenant_' . $this->tenantId]);
            return true;
        }

        return false;
    }

    private function invalidateCachePattern(string $pattern): void
    {
        if (!empty($this->config['redis_enabled']) && $this->redis) {
            try {
                $keys = $this->redis->keys(self::CACHE_PREFIX . $pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } catch (\RuntimeException $e) {
                $this->logError('cache invalidate failed', ['pattern' => $pattern, 'error' => $e->getMessage()]);
            }
        }

        if ($this->cacheManager) {
            $this->cacheManager->invalidateByTag('rbac');
            $this->cacheManager->invalidateByTag('tenant_' . $this->tenantId);
        }
    }

    private function logError(string $msg, array $ctx = []): void
    {
        $ctx['tenant_id'] = $this->tenantId;
        safe_log('error', 'RBAC: ' . $msg, $ctx);
    }

    // ------------------------
    // Public helpers
    // ------------------------

    public function getUserRoles(int $userId): array
    {
        $cacheKey = $this->cacheKey('roles', $userId);
        $fromCache = $this->getFromCache($cacheKey);
        if (is_array($fromCache)) return $fromCache;

        $roles = $this->fetchRolesFromDb($userId);
        $this->setCache($cacheKey, $roles);
        return $roles;
    }

    public function getUserPermissions(int $userId): array
    {
        $cacheKey = $this->cacheKey('permissions', $userId);
        $fromCache = $this->getFromCache($cacheKey);
        if (is_array($fromCache)) return $fromCache;

        $perms = $this->fetchPermissionsFromDb($userId);
        $this->setCache($cacheKey, $perms);
        return $perms;
    }

    public function getPermissions(int $userId): array
    {
        return $this->getUserPermissions($userId);
    }

    public function loadPermissionsForUser(int $userId): array
    {
        return [
            'roles' => $this->getUserRoles($userId),
            'permissions' => $this->getUserPermissions($userId),
        ];
    }

    public function reloadUserPermissions(int $userId): array
    {
        $this->invalidateCachePattern($this->tenantId . ':*:'.$userId);

        $data = $this->loadPermissionsForUser($userId);
        $_SESSION['roles'] = $data['roles'];
        $_SESSION['permissions'] = $data['permissions'];

        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['roles'] = $data['roles'];
            $_SESSION['user']['permissions'] = $data['permissions'];
        }

        return $data;
    }

    /**
     * Returns normalized effective resource permissions for a user + resourceType.
     * Behavior: precedence override (global -> role-global -> tenant-global -> role+tenant).
     * If $useTenantNullDefault=true and no tenant is provided, only global rows are used.
     */
    public function getUserResourcePermissions(int $userId, string $resourceType, ?int $tenantId = null): array
    {
        if (empty($this->config['resource_permissions_enabled'])) {
            return $this->emptyResourcePermissions();
        }

        $tenantId = $tenantId ?? $this->tenantId;

        $cacheKey = self::CACHE_PREFIX . "rp:{$tenantId}:{$resourceType}:{$userId}";
        $cached = $this->getFromCache($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $effective = $this->fetchResourcePermissionsFromDb($userId, $resourceType, $tenantId);
        // ensure boolean values
        foreach ($effective as $k => $v) {
            $effective[$k] = (bool)$v;
        }

        $this->setCache($cacheKey, $effective);
        return $effective;
    }

    // ------------------------
    // Low-level DB fetches
    // ------------------------

    /**
     * Fetch role id and role key_name for user in current tenant.
     * Returns array ['role_id' => int|null, 'role_key' => string|null]
     */
    private function fetchUserRoleInfo(int $userId, int $tenantId): array
    {
        if (!$this->pdo) return ['role_id' => null, 'role_key' => null];

        try {
            $repo = new RbacRepository($this->pdo);
            return $repo->fetchUserRoleInfo($userId, $tenantId);
        } catch (\PDOException|\RuntimeException $e) {
            $this->logError('fetchUserRoleInfo error', ['user_id' => $userId, 'tenant_id' => $tenantId, 'err' => $e->getMessage()]);
            return ['role_id' => null, 'role_key' => null];
        }
    }

    private function fetchRolesFromDb(int $userId): array
    {
        $info = $this->fetchUserRoleInfo($userId, $this->tenantId);
        if ($info['role_key'] !== null) {
            return [(string)$info['role_key']];
        }
        return [];
    }

    private function fetchPermissionsFromDb(int $userId): array
    {
        if (!$this->pdo) return [];
        try {
            $repo = new RbacRepository($this->pdo);
            return $repo->fetchPermissions($userId, $this->tenantId);
        } catch (\PDOException|\RuntimeException $e) {
            $this->logError('fetchPermissionsFromDb error', ['err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Core: fetch and merge resource_permissions rows for a user/resource type.
     * Precedence (applied in order):
     * 1) Global rows: role_id IS NULL AND tenant_id IS NULL
     * 2) Role-global: role_id = user's role AND tenant_id IS NULL
     * 3) Tenant-global: role_id IS NULL AND tenant_id = :tenant
     * 4) Role+Tenant: role_id = user's role AND tenant_id = :tenant
     *
     * Merge strategy: override — more specific rows replace values provided by previous levels.
     * If a more-specific row has NULL for a flag, it will NOT unset the previous value.
     */
    private function fetchResourcePermissionsFromDb(int $userId, string $resourceType, int $tenantId): array
    {
        $default = $this->emptyResourcePermissions();
        if (!$this->pdo) return $default;

        try {
            // determine user's role_id for tenant
            $info = $this->fetchUserRoleInfo($userId, $tenantId);
            $roleId = $info['role_id']; // may be null

            $applyRow = function(array $row, array &$effective) {
                // for each flag, if column exists and is not null, override
                $flags = [
                    'can_view_all','can_view_own','can_view_tenant',
                    'can_create','can_edit_all','can_edit_own',
                    'can_delete_all','can_delete_own'
                ];
                foreach ($flags as $f) {
                    if (array_key_exists($f, $row) && $row[$f] !== null) {
                        $effective[$f] = (bool)((int)$row[$f]);
                    }
                }
            };

            $effective = $default;
            $repo = new RbacRepository($this->pdo);

            // 1) global rows
            foreach ($repo->fetchGlobalResourcePermissions($resourceType) as $r) {
                $applyRow($r, $effective);
            }

            // If tenant not provided or tenant==0: return global-only
            if (empty($tenantId)) {
                return $effective;
            }

            // 2) role-global
            if ($roleId !== null) {
                foreach ($repo->fetchRoleGlobalResourcePermissions($resourceType, $roleId) as $r) {
                    $applyRow($r, $effective);
                }
            }

            // 3) tenant-global (role_id IS NULL, tenant_id = current)
            foreach ($repo->fetchTenantGlobalResourcePermissions($resourceType, $tenantId) as $r) {
                $applyRow($r, $effective);
            }

            // 4) role+tenant (most specific)
            if ($roleId !== null) {
                foreach ($repo->fetchRoleTenantResourcePermissions($resourceType, $roleId, $tenantId) as $r) {
                    $applyRow($r, $effective);
                }
            }

            return $effective;
        } catch (\PDOException|\RuntimeException $e) {
            $this->logError('fetchResourcePermissionsFromDb failed', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'resource' => $resourceType,
                'err' => $e->getMessage()
            ]);
            return $default;
        }
    }

    private function emptyResourcePermissions(): array
    {
        return [
            'can_view_all' => false,
            'can_view_own' => false,
            'can_view_tenant' => false,
            'can_create' => false,
            'can_edit_all' => false,
            'can_edit_own' => false,
            'can_delete_all' => false,
            'can_delete_own' => false,
        ];
    }

    // ------------------------
    // Utility & static wrappers
    // ------------------------

    public static function createForTenant(int $tenantId): self
    {
        return new self(null, null, $tenantId);
    }

    public static function getResourcePermissions(int $userId, string $resourceType, int $tenantId = 1): array
    {
        return (new self(null, null, $tenantId))->getUserResourcePermissions($userId, $resourceType, $tenantId);
    }

    public static function reload_user_permissions_static(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? (int)($_SESSION['tenant_id'] ?? 1);
        return (new self(null, null, $tenantId))->reloadUserPermissions($userId);
    }

    // Convenience: check specific action
    public static function allows(int $userId, string $resourceType, string $action, int $tenantId = 1): bool
    {
        $map = [
            'view_all' => 'can_view_all',
            'view_own' => 'can_view_own',
            'view_tenant' => 'can_view_tenant',
            'create' => 'can_create',
            'edit_all' => 'can_edit_all',
            'edit_own' => 'can_edit_own',
            'delete_all' => 'can_delete_all',
            'delete_own' => 'can_delete_own',
        ];
        if (!isset($map[$action])) return false;
        $perms = self::getResourcePermissions($userId, $resourceType, $tenantId);
        return !empty($perms[$map[$action]]);
    }
}
