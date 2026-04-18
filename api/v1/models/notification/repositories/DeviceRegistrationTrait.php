<?php
declare(strict_types=1);

trait DeviceRegistrationTrait
{
    public function updatePublicRegistration(?int $userId, ?string $anonToken, ?string $fcmToken, string $deviceType, string $deviceName, string $ip, int $deviceId): void
    {
        $this->pdo->prepare("
            UPDATE user_devices SET
                user_id       = COALESCE(:uid, user_id),
                anonymous_token = COALESCE(:anon, anonymous_token),
                fcm_token     = COALESCE(:fcm, fcm_token),
                device_type   = :type,
                device_name   = :name,
                ip            = :ip,
                last_seen_at  = NOW(),
                is_active     = 1,
                updated_at    = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute([
            ':uid'  => $userId,
            ':anon' => $anonToken,
            ':fcm'  => $fcmToken,
            ':type' => $deviceType,
            ':name' => $deviceName,
            ':ip'   => $ip,
            ':id'   => $deviceId
        ]);
    }

    /** Insert a new device for public registration. Returns the new ID. */
    public function insertPublicRegistration(?int $userId, ?string $anonToken, ?string $fcmToken, string $deviceType, string $deviceName, string $userAgent, string $ip): int
    {
        $this->pdo->prepare("
            INSERT INTO user_devices (
                user_id, anonymous_token, fcm_token,
                device_type, device_name, user_agent,
                ip, last_seen_at, is_active, created_at
            ) VALUES (
                :uid, :anon, :fcm,
                :type, :name, :ua,
                :ip, NOW(), 1, CURRENT_TIMESTAMP
            )
        ")->execute([
            ':uid'  => $userId,
            ':anon' => $anonToken,
            ':fcm'  => $fcmToken,
            ':type' => $deviceType,
            ':name' => $deviceName,
            ':ua'   => $userAgent,
            ':ip'   => $ip
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Deactivate device by FCM token (public DELETE). */
    public function deactivateByFcmToken(string $fcmToken): void
    {
        $this->pdo->prepare("
            UPDATE user_devices
            SET is_active = 0
            WHERE fcm_token = ?
        ")->execute([$fcmToken]);
    }

    /** List devices for a user (public route — specific columns). */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, device_type, device_name, ip, last_seen_at, is_active
               FROM user_devices
              WHERE user_id = ?
              ORDER BY last_seen_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Update device registration fields (public route). */
    public function updateRegistration(int $id, ?int $userId, ?string $anonToken, ?string $fcmToken, string $deviceType, string $deviceName, string $ip): void
    {
        $this->pdo->prepare("
            UPDATE user_devices SET
                user_id         = COALESCE(?, user_id),
                anonymous_token = COALESCE(?, anonymous_token),
                fcm_token       = COALESCE(?, fcm_token),
                device_type     = ?,
                device_name     = ?,
                ip              = ?,
                last_seen_at    = NOW(),
                is_active       = 1,
                updated_at      = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$userId, $anonToken, $fcmToken, $deviceType, $deviceName, $ip, $id]);
    }

    /** Insert a new device (public route). Returns new ID. */
    public function insertRegistration(?int $userId, ?string $anonToken, ?string $fcmToken, string $deviceType, string $deviceName, string $userAgent, string $ip): int
    {
        $this->pdo->prepare("
            INSERT INTO user_devices
                (user_id, anonymous_token, fcm_token,
                 device_type, device_name, user_agent,
                 ip, last_seen_at, is_active, created_at)
            VALUES
                (?, ?, ?,
                 ?, ?, ?,
                 ?, NOW(), 1, CURRENT_TIMESTAMP)
        ")->execute([$userId, $anonToken, $fcmToken, $deviceType, $deviceName, $userAgent, $ip]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Deactivate a device by FCM token (no user scope). */
    public function deactivateByFcmTokenOnly(string $fcmToken): void
    {
        $this->pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?")->execute([$fcmToken]);
    }
}
