<?php
declare(strict_types=1);

final class PdoUsersRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE 1=1
        ";

        $params = [];

        // Filters
        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY u.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM users u WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.username = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        // Check username/email uniqueness
        if (!$isUpdate || ($oldData && $oldData['username'] !== $data['username'])) {
            if ($this->findByUsername($data['username'])) {
                throw new RuntimeException('Username already exists');
            }
        }
        if (!$isUpdate || ($oldData && $oldData['email'] !== $data['email'])) {
            if ($this->findByEmail($data['email'])) {
                throw new RuntimeException('Email already exists');
            }
        }

        $passwordHash = isset($data['password']) && $data['password'] ? password_hash($data['password'], PASSWORD_DEFAULT) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET username = :username, email = :email, " . ($passwordHash ? "password_hash = :password_hash, " : "") . "
                    preferred_language = :preferred_language,
                    phone = :phone, is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $params = [
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':phone' => $data['phone'] ?: null,
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':id' => (int)$data['id']
            ];
            if ($passwordHash) $params[':password_hash'] = $passwordHash;
            $stmt->execute($params);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, preferred_language, phone, is_active, created_at)
                VALUES (:username, :email, :password_hash, :preferred_language, :phone, :is_active, NOW())
            ");
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $passwordHash ?: '',
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':phone' => $data['phone'] ?: null,
                ':is_active' => (int)($data['is_active'] ?? 1)
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    /**
     * Find user for login by username or email, with tenant role info.
     */
    public function findForLogin(string $usernameOrEmail): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.phone, u.preferred_language, u.is_active, tu.role_id, tu.tenant_id
             FROM users u LEFT JOIN tenant_users tu ON tu.user_id = u.id AND tu.is_active = 1
             WHERE (u.username = ? OR u.email = ?) ORDER BY tu.joined_at DESC LIMIT 1'
        );
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find user by ID with basic profile fields.
     */
    public function findBasicById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, phone, preferred_language, is_active FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Activate user (set is_active = 1) only if currently inactive.
     * Returns number of affected rows.
     */
    public function activateUser(int $id): int
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ? AND is_active = 0');
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Activate user and set updated_at.
     * Returns number of affected rows.
     */
    public function activateUserWithTimestamp(int $id): int
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND is_active = 0');
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Re-activate user (force is_active = 1 with updated_at).
     */
    public function reactivateUser(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /**
     * Find user by username.
     */
    public function findByUsernameExact(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if username or email already exists.
     */
    public function existsByUsernameOrEmail(string $username, string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        return (bool)$stmt->fetch();
    }

    /**
     * Create a new user for registration (inactive by default). Returns new user ID.
     */
    public function createForRegistration(string $username, string $email, string $passwordHash, ?string $phone, string $lang): int
    {
        $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at) VALUES (?,?,?,?,?,0,NOW())'
        )->execute([$username, $email, $passwordHash, $phone, $lang]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create user via OAuth (active by default). Returns new user ID.
     */
    public function createOAuthUser(string $username, string $email, string $lang): int
    {
        $this->pdo->prepare(
            'INSERT INTO users (username, email, is_active, preferred_language, created_at) VALUES (?,?,1,?,NOW())'
        )->execute([$username, $email, $lang]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Find user by email.
     */
    public function findIdByEmail(string $email): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Load full user record with tenant info for OAuth login.
     */
    public function findWithTenantInfo(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.email, u.phone, u.preferred_language, u.is_active, tu.role_id, tu.tenant_id
             FROM users u LEFT JOIN tenant_users tu ON tu.user_id = u.id AND tu.is_active = 1
             WHERE u.id = ? ORDER BY tu.joined_at DESC LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find inactive user's phone and language by ID.
     */
    public function findInactiveUserPhone(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT phone, preferred_language FROM users WHERE id = ? AND is_active = 0 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find user profile fields for OTP verification.
     */
    public function findProfileById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, phone, preferred_language FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (1, :userId, 'user', :entityId, :action, :changes, :ip, NOW())
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':entityId' => $entityId,
            ':action' => $action,
            ':changes' => $changes,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}