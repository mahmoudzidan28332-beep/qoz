<?php
declare(strict_types=1);

class NotificationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Insert / Update ──────────────────────────────────────────

    public function insertNotification(
        int $tenantId,
        ?int $senderEntityId,
        int $entityId,
        string $title,
        string $message,
        ?string $dataJson,
        ?int $typeId,
        string $priority,
        ?string $expiresAt
    ): ?int {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications
                (tenant_id, sender_entity_id, entity_id, title, message, data,
                 notification_type_id, priority, expires_at, sent_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId, $senderEntityId, $entityId, $title, $message,
            $dataJson, $typeId, $priority, $expiresAt,
        ]);
        return (int)$this->pdo->lastInsertId() ?: null;
    }

    /**
     * Insert a row into notification_recipients so the user sees it in their inbox.
     * tenant_id is stored directly for fast per-tenant filtering.
     */
    public function insertRecipient(
        int    $notificationId,
        int    $recipientId,
        string $recipientType,
        ?int   $tenantId
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_recipients
                (notification_id, recipient_id, recipient_type, tenant_id, is_read, created_at)
            VALUES
                (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$notificationId, $recipientId, $recipientType, $tenantId]);
    }

    public function upsertNotificationCounter(int $tenantId, string $recipientType, int $recipientId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_counters
                (tenant_id, recipient_type, recipient_id, unread_count)
            VALUES
                (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                unread_count = unread_count + 1
        ");
        $stmt->execute([$tenantId, $recipientType, $recipientId]);
    }

    public function deactivateDeviceByToken(string $fcmToken): void
    {
        $stmt = $this->pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?");
        $stmt->execute([$fcmToken]);
    }

    // ── FCM tokens ───────────────────────────────────────────────

    public function getFcmTokensForDevices(int $userId, array $deviceIds): array
    {
        $deviceIds = array_slice($deviceIds, 0, 100);
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT fcm_token FROM user_devices
            WHERE user_id = ? AND is_active = 1 AND fcm_token IS NOT NULL
              AND id IN ({$placeholders})
        ");
        $params = array_merge([$userId], array_map('intval', $deviceIds));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getFcmTokensForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fcm_token FROM user_devices
            WHERE user_id = ? AND is_active = 1 AND fcm_token IS NOT NULL
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Delivery tracking ────────────────────────────────────────

    public function insertDelivery(int $notificationId, int $channelId): ?int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_deliveries
                (notification_id, channel_id, delivery_status, attempts, created_at)
            VALUES
                (?, ?, 'pending', 0, NOW())
        ");
        $stmt->execute([$notificationId, $channelId]);
        return (int)$this->pdo->lastInsertId() ?: null;
    }

    public function updateDeliveryStatus(int $deliveryId, string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_deliveries
            SET delivery_status = ?,
                attempts        = attempts + 1,
                sent_at         = IF(? = 'sent', NOW(), sent_at),
                error_message   = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $status, $errorMessage, $deliveryId]);
    }

    // ── Lookups ──────────────────────────────────────────────────

    public function resolveChannelId(string $code): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM notification_channels WHERE code = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    public function resolveTypeId(string $code): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM notification_types WHERE code = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    public function getUserData(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, phone FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Read operations ──────────────────────────────────────────

    public function getUserNotifications(int $recipientId, int $tenantId, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                n.id,
                n.title,
                n.message,
                n.data,
                n.priority,
                n.sent_at,
                n.expires_at,
                nt.code  AS type_code,
                nt.name  AS type_name
            FROM notifications n
            LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
            WHERE n.entity_id  = ?
              AND n.tenant_id  = ?
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
            ORDER BY n.sent_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$recipientId, $tenantId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount(int $recipientId, string $recipientType, int $tenantId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT unread_count FROM notification_counters
            WHERE tenant_id      = ?
              AND recipient_type = ?
              AND recipient_id   = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $recipientType, $recipientId]);
        return (int)($stmt->fetchColumn() ?? 0);
    }

    public function resetUnreadCount(int $recipientId, string $recipientType, int $tenantId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_counters
            SET unread_count = 0
            WHERE tenant_id      = ?
              AND recipient_type = ?
              AND recipient_id   = ?
        ");
        $stmt->execute([$tenantId, $recipientType, $recipientId]);
    }

    // ── Device management ────────────────────────────────────────

    public function registerDeviceToken(
        int $userId,
        string $fcmToken,
        string $deviceType,
        string $deviceName,
        string $userAgent,
        string $ip
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_devices
                (user_id, fcm_token, device_type, device_name, user_agent, ip,
                 is_active, last_seen_at, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                user_id      = VALUES(user_id),
                device_type  = VALUES(device_type),
                device_name  = VALUES(device_name),
                user_agent   = VALUES(user_agent),
                ip           = VALUES(ip),
                is_active    = 1,
                last_seen_at = NOW()
        ");
        return $stmt->execute([$userId, $fcmToken, $deviceType, $deviceName, $userAgent, $ip]);
    }

    public function deregisterDeviceToken(string $fcmToken): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?"
        );
        return $stmt->execute([$fcmToken]);
    }
}
