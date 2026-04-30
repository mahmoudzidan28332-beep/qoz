<?php
declare(strict_types=1);

namespace Shared\Application\Auth;

class CandidateResolver
{
    public function resolve(): array
    {
        // 1. Platform admin session (highest priority)
        $platformCandidate = $this->resolveFromPlatformSession();
        if (($platformCandidate['id'] ?? null) !== null) {
            return $platformCandidate;
        }

        // 2. Bearer token
        $tokenCandidate = $this->resolveFromToken();
        if (($tokenCandidate['id'] ?? null) !== null) {
            return $tokenCandidate;
        }

        // 3. Regular session
        $sessionCandidate = $this->resolveFromSession();
        if (($sessionCandidate['id'] ?? null) !== null) {
            return $sessionCandidate;
        }

        return ['source' => 'guest'];
    }

    private function resolveFromPlatformSession(): array
    {
        if (empty($_SESSION['platform_admin'])) {
            return ['source' => 'guest'];
        }

        $sessionUser = $_SESSION['user'] ?? [];
        $userId = $this->normalizeNullableInt($_SESSION['user_id'] ?? ($sessionUser['id'] ?? null));

        if ($userId === null) return ['source' => 'guest'];

        $platformRole = (string) ($_SESSION['platform_role'] ?? 'support');
        $roles = array_values(array_unique(array_filter([$platformRole, 'super_admin'])));

        return [
            'id'                   => $userId,
            'tenant_id'            => $this->normalizeNullableInt($_SESSION['tenant_id'] ?? null),
            'role_id'              => null,
            'roles'                => $roles,
            'permissions'          => [],
            'resource_permissions' => [],
            'user'                 => is_array($sessionUser) ? $sessionUser : [],
            'source'               => 'platform_session',
            'is_platform_admin'    => true,
            'platform_role'        => $platformRole,
        ];
    }

    private function resolveFromSession(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return ['source' => 'guest'];

        $sessionUser = $_SESSION['user'] ?? null;
        $userId = $this->normalizeNullableInt($sessionUser['id'] ?? ($_SESSION['user_id'] ?? null));

        if ($userId === null) return ['source' => 'guest'];

        return [
            'id'                   => $userId,
            'tenant_id'            => $this->normalizeNullableInt($sessionUser['tenant_id'] ?? ($_SESSION['tenant_id'] ?? null)),
            'role_id'              => $this->normalizeNullableInt($sessionUser['role_id'] ?? null),
            'roles'                => $this->normalizeStringList($sessionUser['roles'] ?? ($_SESSION['roles'] ?? [])),
            'permissions'          => $this->normalizeStringList($sessionUser['permissions'] ?? ($_SESSION['permissions'] ?? [])),
            'resource_permissions' => is_array($sessionUser['resource_permissions'] ?? null) ? $sessionUser['resource_permissions'] : ($_SESSION['resource_permissions'] ?? []),
            'user'   => is_array($sessionUser) ? $sessionUser : [],
            'source' => 'session',
        ];
    }

    private function resolveFromToken(): array
    {
        if (!class_exists('\JWT', false)) return ['source' => 'guest'];

        try {
            $token = \JWT::getBearerToken();
            if (!$token) return ['source' => 'guest'];

            $payload = \JWT::decode($token);
            if (!is_array($payload)) return ['source' => 'guest'];

            $userId = $this->normalizeNullableInt($payload['user_id'] ?? ($payload['sub'] ?? null));
            if ($userId === null) return ['source' => 'guest'];

            return [
                'id'                   => $userId,
                'tenant_id'            => $this->normalizeNullableInt($payload['tenant_id'] ?? null),
                'role_id'              => $this->normalizeNullableInt($payload['role_id'] ?? null),
                'roles'                => $this->normalizeStringList($payload['roles'] ?? []),
                'permissions'          => $this->normalizeStringList($payload['permissions'] ?? []),
                'resource_permissions' => is_array($payload['resource_permissions'] ?? null) ? $payload['resource_permissions'] : [],
                'user'   => [
                    'id'        => $userId,
                    'username'  => $payload['username'] ?? null,
                    'email'     => $payload['email'] ?? null,
                    'tenant_id' => $this->normalizeNullableInt($payload['tenant_id'] ?? null),
                ],
                'source' => 'token',
            ];
        } catch (\RuntimeException $e) {
            if (function_exists('safe_log')) {
                safe_log('warning', 'CandidateResolver: Token resolution failed', ['error' => $e->getMessage()]);
            }
            return ['source' => 'guest'];
        } catch (\Error $e) {
                if (function_exists('safe_log')) {
                    safe_log('error', 'CandidateResolver: Fatal error in resolution', ['error' => $e->getMessage()]);
                }
                return ['source' => 'guest'];
            }
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        return (is_numeric($value)) ? (int)$value : null;
    }

    private function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) return [];
        return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $values)))));
    }
}
