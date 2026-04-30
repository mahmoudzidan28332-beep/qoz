<?php
declare(strict_types=1);

namespace Shared\Application\Auth;

require_once __DIR__ . '/CandidateResolver.php';
require_once __DIR__ . '/IdentityHydrator.php';

use PDO;

final class UserIdentityResolver
{
    private static ?UserIdentity $resolvedIdentity = null;

    // ═══════════════════════════════════════════════════════════════════════════
    // Public API
    // ═══════════════════════════════════════════════════════════════════════════

    public static function resolve(?PDO $pdo = null, array $options = []): UserIdentity
    {
        if (($options['force'] ?? false) !== true && self::$resolvedIdentity instanceof UserIdentity) {
            return self::$resolvedIdentity;
        }

        self::ensureSession();

        $requestId = (string) ($options['request_id'] ?? self::newRequestId());
        $defaultTenantId = isset($options['default_tenant_id']) ? (int)$options['default_tenant_id'] : null;

        $candidateResolver = new CandidateResolver();
        $candidate = $candidateResolver->resolve();

        $pdo ??= self::resolvePdo();
        $container = $GLOBALS['app_container']
            ?? throw new \SystemException('Container not initialized');

        $identity = null;

        if (($candidate['id'] ?? null) !== null) {
            if (!empty($candidate['is_platform_admin'])) {
                $identity = self::hydratePlatformAdmin($candidate, $requestId, $defaultTenantId);
            } elseif ($pdo instanceof PDO) {
                $hydrator = $container->identityHydrator();
                $identity = $hydrator->hydrateFromDatabase($candidate, $requestId, $defaultTenantId);
            }

            if (!$identity instanceof UserIdentity) {
                $identity = self::hydrateFromSnapshot($candidate, $requestId, $defaultTenantId);
            }
        }

        if (!$identity instanceof UserIdentity) {
            $identity = UserIdentity::guest(
                $requestId,
                $defaultTenantId,
                (string) ($candidate['source'] ?? 'guest'),
                ['resolved_from' => (string) ($candidate['source'] ?? 'guest')]
            );
        }

        self::syncSession($identity);
        self::$resolvedIdentity = $identity;

        return $identity;
    }

    /**
     * Clear session identity and optionally destroy the session
     * Use this ONLY for logout operations
     */
    public static function clearSessionIdentity(bool $destroySession = false): void
    {
        self::ensureSession();
        self::$resolvedIdentity = null;

        unset(
            $_SESSION['user'],
            $_SESSION['user_id'],
            $_SESSION['tenant_id'],
            $_SESSION['permissions'],
            $_SESSION['roles'],
            $_SESSION['resource_permissions'],
            $_SESSION['identity_debug'],
            $_SESSION['platform_admin'],
            $_SESSION['platform_role'],
            $_SESSION['platform_user_id']
        );

        if ($destroySession && function_exists('destroySession')) {
            destroySession();
        }

        if (function_exists('safe_log')) {
            safe_log('info', 'IdentityResolver: Session identity cleared', [
                'destroy_session' => $destroySession
            ]);
        }
    }

    /**
     * Forget the resolved identity cache without touching the session
     * Forces a fresh resolution on the next resolve() call
     * Use this after login or permission changes
     */
    public static function forgetResolvedIdentity(): void
    {
        self::$resolvedIdentity = null;

        if (function_exists('safe_log')) {
            safe_log('debug', 'IdentityResolver: Resolved identity cache cleared');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Session bootstrap
    // ═══════════════════════════════════════════════════════════════════════════

    private static function ensureSession(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $sessionConfig = dirname(__DIR__, 2) . '/config/session.php';
        if (is_file($sessionConfig)) {
            require_once $sessionConfig;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_name('APP_SESSID');
            @session_start();
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PDO resolution
    // ═══════════════════════════════════════════════════════════════════════════

    private static function resolvePdo(): ?PDO
    {
        if (($GLOBALS['ADMIN_DB'] ?? null) instanceof PDO) {
            return $GLOBALS['ADMIN_DB'];
        }

        if (($GLOBALS['CONTAINER']['pdo'] ?? null) instanceof PDO) {
            return $GLOBALS['CONTAINER']['pdo'];
        }

        if (class_exists('DatabaseConnection', false)) {
            try {
                return \DatabaseConnection::getConnection();
            } catch (\PDOException $e) {
                if (function_exists('safe_log')) {
                    safe_log('warning', 'IdentityResolver: PDO resolution failed', ['error' => $e->getMessage()]);
                }
                return null;
            } catch (\RuntimeException $e) {
                if (function_exists('safe_log')) {
                    safe_log('error', 'IdentityResolver: Critical failure during PDO resolution', ['error' => $e->getMessage()]);
                }
                return null;
            }
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Hydration
    // ═══════════════════════════════════════════════════════════════════════════

    private static function hydratePlatformAdmin(array $candidate, string $requestId, ?int $defaultTenantId): UserIdentity
    {
        $userId = (int) $candidate['id'];
        $tenantId = $candidate['tenant_id'] ?? $defaultTenantId;
        if ($tenantId !== null) {
            $tenantId = (int)$tenantId;
        }
        $roles = $candidate['roles'] ?? ['super_admin'];
        $platformRole = (string) ($candidate['platform_role'] ?? 'super_admin');
        $user = is_array($candidate['user'] ?? null) ? $candidate['user'] : [];

        $user = array_merge($user, [
            'id' => $userId,
            'tenant_id' => $tenantId,
            'role_id' => null,
            'roles' => $roles,
            'permissions' => [],
            'resource_permissions' => [],
            'is_active' => true,
            'preferred_language' => $user['preferred_language'] ?? 'en',
            'is_platform_admin' => true,
            'platform_role' => $platformRole,
        ]);

        return new UserIdentity(
            $userId,
            $tenantId,
            null,
            $roles,
            [],
            [],
            true,
            'platform_session',
            $requestId,
            $user,
            [
                'is_platform_admin' => true,
                'platform_role' => $platformRole,
            ]
        );
    }

    private static function hydrateFromSnapshot(array $candidate, string $requestId, ?int $defaultTenantId): UserIdentity
    {
        $user = is_array($candidate['user'] ?? null) ? $candidate['user'] : [];
        $userId = (int) ($candidate['id'] ?? ($user['id'] ?? 0));
        $userId = $userId !== 0 ? $userId : null;
        $tenantId = (int) ($candidate['tenant_id'] ?? ($user['tenant_id'] ?? $defaultTenantId));
        $tenantId = $tenantId !== 0 ? $tenantId : null;
        $roleId = isset($candidate['role_id']) ? (int) $candidate['role_id'] : (isset($user['role_id']) ? (int) $user['role_id'] : null);
        $roles = $candidate['roles'] ?? ($user['roles'] ?? []);
        $permissions = $candidate['permissions'] ?? ($user['permissions'] ?? []);
        $resourcePermissions = $candidate['resource_permissions'] ?? ($user['resource_permissions'] ?? []);

        return new UserIdentity(
            $userId,
            $tenantId,
            $roleId,
            $roles,
            $permissions,
            $resourcePermissions,
            $userId !== null,
            (string) ($candidate['source'] ?? 'session'),
            $requestId,
            $user
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Session sync
    // ═══════════════════════════════════════════════════════════════════════════

    private static function syncSession(UserIdentity $identity): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if ($identity->isAuthenticated()) {
            $_SESSION['user_id'] = $identity->id();

            if ($identity->tenantId() !== null) {
                $_SESSION['tenant_id'] = $identity->tenantId();
            } else {
                unset($_SESSION['tenant_id']);
            }

            $_SESSION['roles'] = $identity->roles();
            $_SESSION['permissions'] = $identity->permissions();
            $_SESSION['resource_permissions'] = $identity->resourcePermissions();
            $_SESSION['user'] = $identity->toArray();

            if ($identity->isPlatformAdmin()) {
                $_SESSION['platform_admin'] = true;
                $_SESSION['platform_role'] = $identity->platformRole();
            }
        }

        $_SESSION['identity_debug'] = [
            'resolved_user_id' => $identity->id(),
            'resolved_tenant_id' => $identity->tenantId(),
            'identity_source' => $identity->source(),
            'session_id' => session_id(),
            'request_id' => $identity->requestId(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Utility
    // ═══════════════════════════════════════════════════════════════════════════

    private static function newRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\RuntimeException $e) {
            return uniqid('rid_', true);
        }
    }
}