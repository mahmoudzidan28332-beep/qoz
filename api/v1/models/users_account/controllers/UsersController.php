<?php
declare(strict_types=1);

require_once __DIR__ . '/UsersVerificationTrait.php';

final class UsersController
{
    use UsersControllerVerificationTrait;
    private UsersService $service;

    public function __construct(UsersService $service)
    {
        $this->service = $service;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        return $this->service->list($limit, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        return $this->service->count($filters);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->service->delete((int)$data['id'], $userId);
    }

    // ── User auth methods ────────────────────────────────────────────────

    public function findForLogin(string $usernameOrEmail): ?array
    {
        return $this->service->findForLogin($usernameOrEmail);
    }

    public function findBasicById(int $id): ?array
    {
        return $this->service->findBasicById($id);
    }

    public function activateUser(int $id): int
    {
        return $this->service->activateUser($id);
    }

    public function activateUserWithTimestamp(int $id): int
    {
        return $this->service->activateUserWithTimestamp($id);
    }

    public function reactivateUser(int $id): void
    {
        $this->service->reactivateUser($id);
    }

    public function existsByUsernameOrEmail(string $username, string $email): bool
    {
        return $this->service->existsByUsernameOrEmail($username, $email);
    }

    public function createForRegistration(string $username, string $email, string $passwordHash, ?string $phone, string $lang): int
    {
        return $this->service->createForRegistration($username, $email, $passwordHash, $phone, $lang);
    }

    public function findByUsernameExact(string $username): ?array
    {
        return $this->service->findByUsernameExact($username);
    }

    public function createOAuthUser(string $username, string $email, string $lang): int
    {
        return $this->service->createOAuthUser($username, $email, $lang);
    }

    public function findIdByEmail(string $email): ?int
    {
        return $this->service->findIdByEmail($email);
    }

    public function findWithTenantInfo(int $id): ?array
    {
        return $this->service->findWithTenantInfo($id);
    }

    public function findInactiveUserPhone(int $id): ?array
    {
        return $this->service->findInactiveUserPhone($id);
    }

    public function findProfileById(int $id): ?array
    {
        return $this->service->findProfileById($id);
    }

    // ── Auth provider methods ────────────────────────────────────────────

    public function findUserIdByProvider(string $provider, string $providerUserId): ?int
    {
        return $this->service->findUserIdByProvider($provider, $providerUserId);
    }

    public function linkAuthProvider(int $userId, string $provider, string $providerUserId, string $providerExtra): void
    {
        $this->service->linkAuthProvider($userId, $provider, $providerUserId, $providerExtra);
    }

    public function findProviderExtra(string $provider, string $providerUserId): ?string
    {
        return $this->service->findProviderExtra($provider, $providerUserId);
    }

    // ── RBAC methods ─────────────────────────────────────────────────────

    public function loadRbac(int $userId, ?int $roleId = null): array
    {
        return $this->service->loadRbac($userId, $roleId);
    }
}