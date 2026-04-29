<?php
declare(strict_types=1);

namespace Shared\Application\Auth;

final class UserIdentity
{
    public function __construct(
        private readonly ?int   $id,
        private readonly ?int   $tenantId,
        private readonly ?int   $roleId,
        private readonly array  $roles,
        private readonly array  $permissions,
        private readonly array  $resourcePermissions,
        private readonly bool   $isAuthenticated,
        private readonly string $source,
        private readonly string $requestId,
        private readonly array  $user       = [],
        private readonly array  $attributes = []
    ) {}

    // ──────────────────────────────────────────────────
    // Factory
    // ──────────────────────────────────────────────────

    public static function guest(
        string $requestId,
        ?int   $tenantId   = null,
        string $source     = 'guest',
        array  $attributes = []
    ): self {
        return new self(
            null,
            $tenantId,
            null,
            [],
            [],
            [],
            false,
            $source,
            $requestId,
            [
                'id'                   => null,
                'tenant_id'            => $tenantId,
                'role_id'              => null,
                'roles'                => [],
                'permissions'          => [],
                'resource_permissions' => [],
                'is_active'            => false,
                'preferred_language'   => 'en',
            ],
            $attributes
        );
    }

    // ──────────────────────────────────────────────────
    // Core accessors
    // ──────────────────────────────────────────────────

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function roleId(): ?int
    {
        return $this->roleId;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function resourcePermissions(): array
    {
        return $this->resourcePermissions;
    }

    public function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function user(): array
    {
        return $this->user;
    }

    // ──────────────────────────────────────────────────
    // User profile helpers
    // ──────────────────────────────────────────────────

    public function preferredLanguage(): string
    {
        $lang = (string) ($this->user['preferred_language'] ?? 'en');
        return $lang !== '' ? $lang : 'en';
    }

    public function timezone(): string
    {
        $tz = (string) ($this->user['timezone'] ?? 'UTC');
        return $tz !== '' ? $tz : 'UTC';
    }

    public function username(): ?string
    {
        return isset($this->user['username']) ? (string) $this->user['username'] : null;
    }

    public function email(): ?string
    {
        return isset($this->user['email']) ? (string) $this->user['email'] : null;
    }

    public function isActive(): bool
    {
        return (bool) ($this->user['is_active'] ?? false);
    }

    // ──────────────────────────────────────────────────
    // Platform admin helpers
    // ──────────────────────────────────────────────────

    public function isPlatformAdmin(): bool
    {
        return (bool) (
            $this->attributes['is_platform_admin']
            ?? $this->user['is_platform_admin']
            ?? false
        );
    }

    public function platformRole(): ?string
    {
        $role = $this->user['platform_role'] ?? $this->attributes['platform_role'] ?? null;
        return $role !== null ? (string) $role : null;
    }

    // ──────────────────────────────────────────────────
    // Attribute bag
    // ──────────────────────────────────────────────────

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    // ──────────────────────────────────────────────────
    // Serialisation
    // ──────────────────────────────────────────────────

    public function toArray(): array
    {
        return array_merge($this->user, [
            'id'                   => $this->id,
            'tenant_id'            => $this->tenantId,
            'role_id'              => $this->roleId,
            'roles'                => $this->roles,
            'permissions'          => $this->permissions,
            'resource_permissions' => $this->resourcePermissions,
            'is_authenticated'     => $this->isAuthenticated,
            'identity_source'      => $this->source,
            'request_id'           => $this->requestId,
            'is_platform_admin'    => $this->isPlatformAdmin(),
            'platform_role'        => $this->platformRole(),
        ]);
    }
}