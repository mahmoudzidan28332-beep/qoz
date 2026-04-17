<?php
declare(strict_types=1);

final class PdoUserDevicesRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'user_id', 'device_type', 'device_name', 'is_active', 'last_seen_at', 'created_at', 'updated_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'user_id', 'device_type', 'device_name', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List devices with filters, sorting, pagination
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM user_devices WHERE 1=1";
        $params = [];

        // تطبيق الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'device_name') {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count devices for pagination
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM user_devices WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'device_name') {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find a device by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_devices WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find device by token (unique)
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_devices WHERE fcm_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find all devices for a user
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_devices WHERE user_id = :user_id ORDER BY last_seen_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update a device
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $fields = [
            'user_id', 'fcm_token', 'device_type', 'device_name', 'user_agent', 'ip', 'last_seen_at', 'is_active'
        ];

        if ($isUpdate) {
            $set = [];
            $params = [':id' => (int)$data['id']];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $set[] = "{$field} = :{$field}";
                    $params[":{$field}"] = $data[$field];
                }
            }
            $set[] = "updated_at = CURRENT_TIMESTAMP";
            $sql = "UPDATE user_devices SET " . implode(', ', $set) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        }

        // INSERT
        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $columns[] = $field;
                $placeholders[] = ":{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        $columns[] = 'created_at';
        $placeholders[] = 'CURRENT_TIMESTAMP';

        $sql = "INSERT INTO user_devices (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a device by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_devices WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Delete all devices for a user (optional)
     */
    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_devices WHERE user_id = :user_id");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Deactivate a device by FCM token scoped to a user.
     */
    public function deactivateByFcmTokenAndUser(string $fcmToken, int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE fcm_token = ? AND user_id = ?");
        return $stmt->execute([$fcmToken, $userId]);
    }

    /**
     * Find device by user_id and user_agent (for deduplication without FCM token)
     */
    public function findByUserAndAgent(int $userId, string $userAgent): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM user_devices WHERE user_id = :user_id AND user_agent = :user_agent AND is_active = 1 ORDER BY last_seen_at DESC LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId, ':user_agent' => $userAgent]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update last seen timestamp
     */
    public function touch(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user_devices SET last_seen_at = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Auth-context device methods ──────────────────────────────────────

    /**
     * Link anonymous device to user by anonymous_token.
     * Returns number of affected rows.
     */
    public function linkByAnonymousToken(int $userId, string $ip, string $anonToken): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_devices SET user_id=?, ip=?, last_seen_at=NOW(), updated_at=CURRENT_TIMESTAMP
             WHERE anonymous_token=? AND (user_id IS NULL OR user_id=?) AND is_active=1'
        );
        $stmt->execute([$userId, $ip, $anonToken, $userId]);
        return $stmt->rowCount();
    }

    /**
     * Find active device by user_agent for user linking.
     */
    public function findActiveByUserAgent(string $ua, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_devices WHERE user_agent=? AND is_active=1
             AND (user_id IS NULL OR user_id=?) ORDER BY last_seen_at DESC LIMIT 1'
        );
        $stmt->execute([$ua, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update user_id and ip for an existing device row.
     */
    public function linkUserToDevice(int $userId, string $ip, int $deviceId): void
    {
        $this->pdo->prepare(
            'UPDATE user_devices SET user_id=?, ip=?, last_seen_at=NOW(), updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$userId, $ip, $deviceId]);
    }

    /**
     * Insert a new device for login linking. Returns the new ID.
     */
    public function createForLogin(int $userId, string $anonToken, string $type, string $name, string $ua, string $ip): int
    {
        $this->pdo->prepare(
            'INSERT INTO user_devices (user_id, anonymous_token, device_type, device_name, user_agent, ip, last_seen_at, is_active, created_at)
             VALUES (?,?,?,?,?,?,NOW(),1,CURRENT_TIMESTAMP)'
        )->execute([$userId, $anonToken, $type, $name, $ua, $ip]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Find device by anonymous_token.
     */
    public function findByAnonymousToken(string $anonToken): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_devices WHERE anonymous_token=? LIMIT 1');
        $stmt->execute([$anonToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find active device by user_id and user_agent.
     */
    public function findActiveByUserIdAndAgent(int $userId, string $ua): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_devices WHERE user_id=? AND user_agent=? AND is_active=1 LIMIT 1');
        $stmt->execute([$userId, $ua]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update existing device with optional fields.
     */
    public function updateDeviceRegistration(?int $userId, ?string $fcmToken, string $deviceType, ?string $deviceName, string $ip, int $deviceId): void
    {
        $this->pdo->prepare(
            'UPDATE user_devices SET user_id=COALESCE(?,user_id), fcm_token=COALESCE(?,fcm_token),
                device_type=?, device_name=COALESCE(?,device_name), ip=?,
                last_seen_at=NOW(), updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$userId, $fcmToken, $deviceType, $deviceName, $ip, $deviceId]);
    }

    /**
     * Insert a new device for registration. Returns the new ID.
     */
    public function createDeviceRegistration(?int $userId, string $anonToken, ?string $fcmToken, string $type, ?string $name, string $ua, string $ip): int
    {
        $this->pdo->prepare(
            'INSERT INTO user_devices (user_id, anonymous_token, fcm_token, device_type, device_name, user_agent, ip, last_seen_at, is_active, created_at)
             VALUES (?,?,?,?,?,?,?,NOW(),1,CURRENT_TIMESTAMP)'
        )->execute([$userId, $anonToken, $fcmToken, $type, $name, $ua, $ip]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Clear stale FCM token binding on other users.
     */
    public function clearStaleFcmToken(string $fcmToken, int $excludeUserId): void
    {
        $this->pdo->prepare('UPDATE user_devices SET fcm_token=NULL, updated_at=CURRENT_TIMESTAMP WHERE fcm_token=? AND user_id!=?')
            ->execute([$fcmToken, $excludeUserId]);
    }

    /**
     * Find active device by user_id and user_agent ordered by last_seen.
     */
    public function findLatestActiveByUserIdAndAgent(int $userId, string $ua): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_devices WHERE user_id=? AND user_agent=? AND is_active=1 ORDER BY last_seen_at DESC LIMIT 1');
        $stmt->execute([$userId, $ua]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update FCM token and optionally user_id for a device.
     */
    public function updateFcmToken(string $fcmToken, ?int $userId, int $deviceId): void
    {
        $this->pdo->prepare('UPDATE user_devices SET fcm_token=?, user_id=COALESCE(?,user_id), last_seen_at=NOW(), updated_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$fcmToken, $userId, $deviceId]);
    }
}