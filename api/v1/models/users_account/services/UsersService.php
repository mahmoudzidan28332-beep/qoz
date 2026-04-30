<?php
declare(strict_types=1);

require_once __DIR__ . '/UsersVerificationTrait.php';
require_once __DIR__ . '/../repositories/PdoUsersRepository.php';
require_once __DIR__ . '/../validators/UsersValidator.php';
require_once __DIR__ . '/../repositories/PdoUserPhoneVerificationsRepository.php';
require_once __DIR__ . '/../repositories/PdoUserAuthProvidersRepository.php';
require_once __DIR__ . '/../repositories/PdoAuthRbacRepository.php';

final class UsersService
{
    use UsersVerificationTrait;
    private PdoUsersRepository $repo;
    private UsersValidator $validator;
    private ?PdoUserPhoneVerificationsRepository $phoneVerifRepo;
    private ?PdoUserAuthProvidersRepository $authProvRepo;
    private ?PdoAuthRbacRepository $rbacRepo;

    public const WHITELISTED_COLUMNS = [
        'username', 'email', 'password', 'preferred_language',
        'phone', 'is_active', 'id'
    ];

    public function __construct(
        PdoUsersRepository $repo,
        UsersValidator $validator,
        ?PdoUserPhoneVerificationsRepository $phoneVerifRepo = null,
        ?PdoUserAuthProvidersRepository $authProvRepo = null,
        ?PdoAuthRbacRepository $rbacRepo = null
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
        $this->phoneVerifRepo = $phoneVerifRepo;
        $this->authProvRepo = $authProvRepo;
        $this->rbacRepo = $rbacRepo;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $filterErrors = UsersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Filter validation failed: ' . json_encode($filterErrors));
        }

        return $this->repo->all($limit, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        $filterErrors = UsersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Filter validation failed: ' . json_encode($filterErrors));
        }

        return $this->repo->count($filters);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('User not found');
        }

        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        // For partial updates, merge with existing data so validation passes
        if ($isUpdate) {
            $existing = $this->repo->find((int)$whitelisted['id']);
            if (!$existing) {
                throw new RuntimeException('User not found');
            }
            $whitelisted = array_merge($existing, $whitelisted);
        }

        $errors = UsersValidator::validate($whitelisted, $isUpdate);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($whitelisted, $userId);

        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved user');
        }

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete user');
        }
    }

    // ── User auth methods ────────────────────────────────────────────────

    public function findForLogin(string $usernameOrEmail): ?array
    {
        return $this->repo->findForLogin($usernameOrEmail);
    }

    public function findBasicById(int $id): ?array
    {
        return $this->repo->findBasicById($id);
    }

    public function activateUser(int $id): int
    {
        return $this->repo->activateUser($id);
    }

    public function activateUserWithTimestamp(int $id): int
    {
        return $this->repo->activateUserWithTimestamp($id);
    }

    public function reactivateUser(int $id): void
    {
        $this->repo->reactivateUser($id);
    }

    public function existsByUsernameOrEmail(string $username, string $email): bool
    {
        return $this->repo->existsByUsernameOrEmail($username, $email);
    }

    public function createForRegistration(string $username, string $email, string $passwordHash, ?string $phone, string $lang): int
    {
        return $this->repo->createForRegistration($username, $email, $passwordHash, $phone, $lang);
    }

    public function findByUsernameExact(string $username): ?array
    {
        return $this->repo->findByUsernameExact($username);
    }

    public function createOAuthUser(string $username, string $email, string $lang): int
    {
        return $this->repo->createOAuthUser($username, $email, $lang);
    }

    public function findIdByEmail(string $email): ?int
    {
        return $this->repo->findIdByEmail($email);
    }

    public function findWithTenantInfo(int $id): ?array
    {
        return $this->repo->findWithTenantInfo($id);
    }

    public function findInactiveUserPhone(int $id): ?array
    {
        return $this->repo->findInactiveUserPhone($id);
    }

    public function findProfileById(int $id): ?array
    {
        return $this->repo->findProfileById($id);
    }

    // ── Auth provider methods ────────────────────────────────────────────

    public function findUserIdByProvider(string $provider, string $providerUserId): ?int
    {
        return $this->authProvRepo->findUserIdByProvider($provider, $providerUserId);
    }

    public function linkAuthProvider(int $userId, string $provider, string $providerUserId, string $providerExtra): void
    {
        $this->authProvRepo->linkProvider($userId, $provider, $providerUserId, $providerExtra);
    }

    public function findProviderExtra(string $provider, string $providerUserId): ?string
    {
        return $this->authProvRepo->findProviderExtra($provider, $providerUserId);
    }

    // ── RBAC methods ─────────────────────────────────────────────────────

    public function loadRbac(int $userId, ?int $roleId = null): array
    {
        $perms = [];
        $roles = [];
        try {
            if ($this->rbacRepo->tableExists('user_roles')) {
                $roles = array_merge($roles, $this->rbacRepo->getRoleKeysByUserId($userId));
            } elseif ($roleId) {
                $r = $this->rbacRepo->getRoleKeyById($roleId);
                if ($r) $roles[] = $r;
            }
            if ($this->rbacRepo->tableExists('user_permissions')) {
                $perms = array_merge($perms, $this->rbacRepo->getPermissionKeysByUserId($userId));
            }
            if ($roleId) {
                $perms = array_merge($perms, $this->rbacRepo->getPermissionKeysByRoleId($roleId));
            } elseif (!empty($roles)) {
                $perms = array_merge($perms, $this->rbacRepo->getPermissionKeysByRoleKeys($roles));
            }
        } catch (\RuntimeException $e) {
            if (class_exists('Logger')) Logger::error('RBAC: ' . $e->getMessage());
        }
        return [
            'permissions' => array_values(array_unique($perms)),
            'roles'       => array_values(array_unique($roles)),
        ];
    }
}
