<?php
// htdocs/api/shared/helpers/notification.php
// ملف دوال الإشعارات - معدّل حسب هيكل قاعدة البيانات الفعلي
// يدعم: Database, Email, SMS, Push (Firebase FCM)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/sms.php';

// ===========================================
// إعدادات Firebase FCM (v1 API + Legacy fallback)
// ===========================================
if (!defined('FCM_SERVER_KEY'))    define('FCM_SERVER_KEY',    getenv('FCM_SERVER_KEY')    ?: ($_ENV['FCM_SERVER_KEY'] ?? ''));
if (!defined('FCM_ENDPOINT'))      define('FCM_ENDPOINT',      'https://fcm.googleapis.com/fcm/send');
// APP_LOGO_URL must be an absolute HTTPS URL for FCM notification icons/images to work
if (!defined('APP_LOGO_URL')) {
    $appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
    $iconPath = getenv('APP_NOTIFICATION_ICON') ?: '/admin/assets/img/default-image.png';
    define('APP_LOGO_URL', $appUrl ? rtrim($appUrl, '/') . $iconPath : $iconPath);
}

// FCM v1 API settings
if (!defined('FCM_PROJECT_ID'))    define('FCM_PROJECT_ID',    getenv('FCM_PROJECT_ID')    ?: '');
if (!defined('FCM_SERVICE_ACCOUNT_PATH')) {
    $saPath = getenv('FCM_SERVICE_ACCOUNT_PATH') ?: '';
    if (!$saPath) {
        // Default locations for service account file
        $candidates = [
            __DIR__ . '/../config/firebase-service-account.json',
            __DIR__ . '/../../firebase-service-account.json',
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) { $saPath = $c; break; }
        }
    }
    define('FCM_SERVICE_ACCOUNT_PATH', $saPath);
}

// Define MAIL_ENABLED / SMS_ENABLED if not defined (prevents fatal errors)
if (!defined('MAIL_ENABLED')) define('MAIL_ENABLED', (bool)(getenv('MAIL_ENABLED') ?: false));
if (!defined('SMS_ENABLED'))  define('SMS_ENABLED',  (bool)(getenv('SMS_ENABLED')  ?: false));

// ===========================================
// Notification Class
// ===========================================

class Notification
{
    private static ?PDO $pdo = null;

    // كاش محلي للقنوات والأنواع لتفادي استعلامات متكررة
    private static array $channelsCache = [];
    private static array $typesCache    = [];

    // -------------------------------------------------------
    // تهيئة PDO
    // -------------------------------------------------------

    public static function setPDO(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    // -------------------------------------------------------
    // 1️⃣  إرسال إشعار (نقطة الدخول الرئيسية)
    // -------------------------------------------------------

    /**
     * @param int    $recipientId    معرف المستلم (user أو entity)
     * @param string $recipientType  'user' | 'entity' | 'tenant'
     * @param int    $tenantId       معرف الـ tenant
     * @param string $typeCode       كود نوع الإشعار (يُطابق notification_types.code)
     * @param string $title          العنوان
     * @param string $message        نص الرسالة
     * @param array  $data           بيانات إضافية (JSON)
     * @param array  $channels       ['database','email','sms','push']
     * @param string $priority       'low'|'normal'|'high'|'urgent'
     * @param string|null $expiresAt  تاريخ انتهاء الصلاحية 'Y-m-d H:i:s' أو null
     * @param int|null $senderEntityId معرف المُرسل (entity) أو null
     * @return array
     */
    public static function send(
        int     $recipientId,
        string  $recipientType = 'user',
        int     $tenantId      = 1,
        string  $typeCode      = 'general',
        string  $title         = '',
        string  $message       = '',
        array   $data          = [],
        array   $channels      = ['database'],
        string  $priority      = 'normal',
        ?string $expiresAt     = null,
        ?int    $senderEntityId = null,
        array   $deviceIds     = []
    ): array {
        if (!self::$pdo) {
            return ['success' => false, 'message' => 'PDO not initialized'];
        }

        $results = [
            'success'  => false,
            'channels' => [],
        ];

        try {
            // جلب معرف نوع الإشعار
            $typeId = self::resolveTypeId($typeCode);

            // حفظ الإشعار الرئيسي في notifications
            $notificationId = self::insertNotification(
                $tenantId,
                $senderEntityId,
                $recipientId,        // entity_id
                $title,
                $message,
                $data,
                $typeId,
                $priority,
                $expiresAt
            );

            if (!$notificationId) {
                return ['success' => false, 'message' => 'Failed to insert notification'];
            }

            $results['notification_id'] = $notificationId;

            // جلب بيانات المستخدم إن كان النوع 'user'
            $user = ($recipientType === 'user') ? self::getUserData($recipientId) : null;

            // المعالجة لكل قناة
            foreach ($channels as $channel) {
                $channelId = self::resolveChannelId($channel);
                $deliveryId = self::insertDelivery($notificationId, $channelId);

                $channelResult = match ($channel) {
                    'database' => self::handleDatabaseChannel(
                        $recipientId, $recipientType, $tenantId
                    ),
                    'email' => self::handleEmailChannel(
                        $user, $title, $message, $typeCode
                    ),
                    'sms' => self::handleSmsChannel(
                        $user, $message
                    ),
                    'push' => self::handlePushChannel(
                        $recipientId, $recipientType, $title, $message, $data, $notificationId, $deviceIds
                    ),
                    default => ['success' => false, 'message' => "Unknown channel: {$channel}"]
                };

                // تحديث حالة التسليم
                $errorMsg = $channelResult['error'] ?? $channelResult['message'] ?? null;
                // لا نسجل الرسالة إن كانت عملية الإرسال ناجحة
                self::updateDeliveryStatus(
                    $deliveryId,
                    $channelResult['success'] ? 'sent' : 'failed',
                    $channelResult['success'] ? null : $errorMsg
                );

                $results['channels'][$channel] = $channelResult;
            }

            $results['success'] = true;

        } catch (Throwable $e) {
            self::logError('Notification::send — ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    // -------------------------------------------------------
    // 2️⃣  حفظ الإشعار في جدول notifications
    // -------------------------------------------------------

    private static function insertNotification(
        int     $tenantId,
        ?int    $senderEntityId,
        int     $entityId,
        string  $title,
        string  $message,
        array   $data,
        ?int    $typeId,
        string  $priority,
        ?string $expiresAt
    ): ?int {
        $dataJson = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

        $stmt = self::$pdo->prepare("
            INSERT INTO notifications
                (tenant_id, sender_entity_id, entity_id, title, message, data,
                 notification_type_id, priority, expires_at, sent_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $senderEntityId,
            $entityId,
            $title,
            $message,
            $dataJson,
            $typeId,
            $priority,
            $expiresAt,
        ]);

        return (int) self::$pdo->lastInsertId() ?: null;
    }

    // -------------------------------------------------------
    // 3️⃣  قناة Database — تحديث عداد الإشعارات غير المقروءة
    // -------------------------------------------------------

    private static function handleDatabaseChannel(
        int    $recipientId,
        string $recipientType,
        int    $tenantId
    ): array {
        try {
            // upsert في notification_counters
            $stmt = self::$pdo->prepare("
                INSERT INTO notification_counters
                    (tenant_id, recipient_type, recipient_id, unread_count)
                VALUES
                    (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    unread_count = unread_count + 1
            ");
            $stmt->execute([$tenantId, $recipientType, $recipientId]);

            self::logNotification('database', $recipientId, 'counter_updated');
            return ['success' => true];

        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->errorInfo()[2]];
        }
    }

    // -------------------------------------------------------
    // 4️⃣  قناة Email
    // -------------------------------------------------------

    private static function handleEmailChannel(?array $user, string $title, string $message, string $type): array
    {
        if (!$user || empty($user['email'])) {
            return ['success' => false, 'message' => 'No email address'];
        }

        $sent = Mail::send($user['email'], $title, $message);
        self::logNotification('email', $user['email'], $type . ':' . ($sent ? 'sent' : 'failed'));

        return ['success' => $sent, 'message' => $sent ? 'Email sent' : 'Email failed'];
    }

    // -------------------------------------------------------
    // 5️⃣  قناة SMS
    // -------------------------------------------------------

    private static function handleSmsChannel(?array $user, string $message): array
    {
        if (!$user || empty($user['phone'])) {
            return ['success' => false, 'message' => 'No phone number'];
        }

        $result = SMS::send($user['phone'], $message);
        self::logNotification('sms', $user['phone'], $result['success'] ? 'sent' : 'failed');

        return $result;
    }

    // -------------------------------------------------------
    // 6️⃣  قناة Push — Firebase FCM
    // -------------------------------------------------------

    private static function handlePushChannel(
        int    $recipientId,
        string $recipientType,
        string $title,
        string $message,
        array  $data,
        int    $notificationId,
        array  $deviceIds = []
    ): array {
        // جلب FCM tokens من user_devices
        $tokens = self::getFcmTokens($recipientId, $recipientType, $deviceIds);

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No active FCM tokens found'];
        }

        // محاولة FCM v1 API أولاً (الطريقة الحديثة)
        $accessToken = self::getFcmAccessToken();
        if ($accessToken) {
            if (empty(FCM_PROJECT_ID)) {
                return ['success' => false, 'message' => 'FCM_PROJECT_ID not configured in .env'];
            }
            return self::sendViaFcmV1($tokens, $title, $message, $data, $notificationId, $recipientId, $accessToken);
        }

        // Fallback: Legacy FCM API (deprecated)
        if (!empty(FCM_SERVER_KEY) && FCM_SERVER_KEY !== 'REPLACE_WITH_YOUR_SERVER_KEY') {
            return self::sendViaFcmLegacy($tokens, $title, $message, $data, $notificationId, $recipientId);
        }

        return ['success' => false, 'message' => 'FCM not configured: set service account JSON file or FCM_SERVER_KEY'];
    }

    // -------------------------------------------------------
    // 6️⃣.1  FCM v1 API — الطريقة الحديثة (OAuth2 + Service Account)
    // -------------------------------------------------------

    private static function sendViaFcmV1(
        array  $tokens,
        string $title,
        string $message,
        array  $data,
        int    $notificationId,
        int    $recipientId,
        string $accessToken
    ): array {
        $projectId = FCM_PROJECT_ID;
        $endpoint  = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $successCount = 0;
        $failureCount = 0;
        $invalidTokens = [];

        // v1 API يرسل رسالة واحدة لكل جهاز
        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $message,
                        'image' => APP_LOGO_URL,
                    ],
                    'data' => array_map('strval', array_merge($data, [
                        'notification_id' => (string) $notificationId,
                        'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                    ])),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'icon'         => 'ic_notification',
                        ],
                    ],
                    'webpush' => [
                        'notification' => [
                            'icon'  => APP_LOGO_URL,
                            'badge' => APP_LOGO_URL,
                        ],
                        'fcm_options' => [
                            'link' => '/',
                        ],
                    ],
                ],
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT    => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                self::logError("FCM v1 cURL error: {$curlErr}");
                $failureCount++;
                continue;
            }

            if ($httpCode === 200) {
                $successCount++;
            } else {
                $failureCount++;
                $decoded = json_decode($response, true);
                $errorCode = $decoded['error']['details'][0]['errorCode'] ?? ($decoded['error']['status'] ?? '');

                // Token منتهي الصلاحية أو غير صالح
                if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)
                    || ($httpCode === 404)) {
                    $invalidTokens[] = $token;
                }

                self::logError("FCM v1 error [{$httpCode}]: " . ($response ?: 'empty'));
            }
        }

        // تنظيف tokens غير صالحة
        foreach ($invalidTokens as $badToken) {
            try {
                $stmt = self::$pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?");
                $stmt->execute([$badToken]);
            } catch (PDOException $e) {
                self::logError('cleanInvalidToken: ' . $e->getMessage());
            }
        }

        $success = $successCount > 0;
        self::logNotification('push', $recipientId, $success ? 'sent' : 'failed');

        return [
            'success'      => $success,
            'tokens_sent'  => count($tokens),
            'fcm_success'  => $successCount,
            'fcm_failure'  => $failureCount,
            'api_version'  => 'v1',
        ];
    }

    // -------------------------------------------------------
    // 6️⃣.2  FCM Legacy API — fallback (deprecated)
    // -------------------------------------------------------

    private static function sendViaFcmLegacy(
        array  $tokens,
        string $title,
        string $message,
        array  $data,
        int    $notificationId,
        int    $recipientId
    ): array {
        $payload = [
            'registration_ids' => $tokens,
            'notification'     => [
                'title' => $title,
                'body'  => $message,
                'icon'  => APP_LOGO_URL,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'data' => array_merge($data, [
                'notification_id' => $notificationId,
                'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
            ]),
            'priority' => 'high',
        ];

        $ch = curl_init(FCM_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: key=' . FCM_SERVER_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT    => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            self::logError("FCM Legacy cURL error: {$curlErr}");
            return ['success' => false, 'error' => $curlErr];
        }

        $decoded = json_decode($response, true);
        $success = ($httpCode === 200 && isset($decoded['success']) && $decoded['success'] > 0);

        if (isset($decoded['results'])) {
            self::cleanInvalidTokens($tokens, $decoded['results']);
        }

        self::logNotification('push', $recipientId, $success ? 'sent' : 'failed');

        return [
            'success'      => $success,
            'tokens_sent'  => count($tokens),
            'fcm_success'  => $decoded['success']  ?? 0,
            'fcm_failure'  => $decoded['failure']  ?? 0,
            'http_code'    => $httpCode,
            'api_version'  => 'legacy',
        ];
    }

    // -------------------------------------------------------
    // 6️⃣.3  FCM v1 API — OAuth2 Access Token من Service Account
    // -------------------------------------------------------

    private static ?string $cachedAccessToken = null;
    private static int $tokenExpiresAt = 0;

    private static function getFcmAccessToken(): ?string
    {
        // استخدام التوكن المحفوظ إن لم ينتهِ
        if (self::$cachedAccessToken && time() < self::$tokenExpiresAt) {
            return self::$cachedAccessToken;
        }

        $saPath = FCM_SERVICE_ACCOUNT_PATH;
        if (!$saPath || !file_exists($saPath)) {
            return null;
        }

        $sa = json_decode(file_get_contents($saPath), true);
        if (!$sa || empty($sa['private_key']) || empty($sa['client_email']) || empty($sa['token_uri'])) {
            self::logError('FCM service account JSON invalid or missing required fields');
            return null;
        }

        try {
            $now = time();
            $jwtTtlSeconds = 3600; // JWT valid for 1 hour
            $exp = $now + $jwtTtlSeconds;

            // بناء JWT header + claims
            $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = self::base64UrlEncode(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $sa['token_uri'],
                'iat'   => $now,
                'exp'   => $exp,
            ]));

            $signingInput = "{$header}.{$claims}";

            // توقيع RS256
            $privateKey = openssl_pkey_get_private($sa['private_key']);
            if (!$privateKey) {
                self::logError('FCM: Failed to parse private key from service account');
                return null;
            }

            $signature = '';
            openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = $signingInput . '.' . self::base64UrlEncode($signature);

            // تبادل JWT بـ access token
            $ch = curl_init($sa['token_uri']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                self::logError("FCM OAuth2 cURL error: {$curlErr}");
                return null;
            }

            $tokenData = json_decode($response, true);
            if ($httpCode !== 200 || empty($tokenData['access_token'])) {
                self::logError("FCM OAuth2 failed [{$httpCode}]: " . ($response ?: 'empty'));
                return null;
            }

            self::$cachedAccessToken = $tokenData['access_token'];
            $tokenExpiryBuffer = 60; // refresh 60 seconds before actual expiry
            self::$tokenExpiresAt    = $now + (int)($tokenData['expires_in'] ?? 3500) - $tokenExpiryBuffer;

            return self::$cachedAccessToken;

        } catch (Throwable $e) {
            self::logError('FCM getAccessToken: ' . $e->getMessage());
            return null;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // -------------------------------------------------------
    // 7️⃣  جلب FCM tokens من user_devices
    // -------------------------------------------------------

    private static function getFcmTokens(int $userId, string $recipientType, array $deviceIds = []): array
    {
        if (!self::$pdo) return [];

        // حالياً يدعم النوع 'user' فقط عبر جدول user_devices
        if ($recipientType !== 'user') return [];

        try {
            // إذا تم تحديد أجهزة معينة، جلب tokens لتلك الأجهزة فقط
            if (!empty($deviceIds)) {
                $deviceIds = array_slice($deviceIds, 0, 100); // حد أقصى 100 جهاز
                $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
                $stmt = self::$pdo->prepare("
                    SELECT fcm_token FROM user_devices
                    WHERE user_id = ? AND is_active = 1 AND fcm_token IS NOT NULL
                      AND id IN ({$placeholders})
                ");
                $params = array_merge([$userId], array_map('intval', $deviceIds));
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $stmt = self::$pdo->prepare("
                SELECT fcm_token FROM user_devices
                WHERE user_id = ? AND is_active = 1 AND fcm_token IS NOT NULL
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            self::logError('getFcmTokens: ' . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------
    // 8️⃣  تنظيف FCM tokens المنتهية الصلاحية
    // -------------------------------------------------------

    private static function cleanInvalidTokens(array $tokens, array $results): void
    {
        foreach ($results as $index => $result) {
            if (isset($result['error']) && in_array($result['error'], [
                'InvalidRegistration',
                'NotRegistered',
            ], true)) {
                $invalidToken = $tokens[$index] ?? null;
                if ($invalidToken) {
                    try {
                        $stmt = self::$pdo->prepare("
                            UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?
                        ");
                        $stmt->execute([$invalidToken]);
                    } catch (PDOException $e) {
                        self::logError('cleanInvalidTokens: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    // -------------------------------------------------------
    // 9️⃣  حفظ سجل التسليم في notification_deliveries
    // -------------------------------------------------------

    private static function insertDelivery(int $notificationId, ?int $channelId): ?int
    {
        if (!$channelId) return null;

        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO notification_deliveries
                    (notification_id, channel_id, delivery_status, attempts, created_at)
                VALUES
                    (?, ?, 'pending', 0, NOW())
            ");
            $stmt->execute([$notificationId, $channelId]);
            return (int) self::$pdo->lastInsertId();
        } catch (PDOException $e) {
            self::logError('insertDelivery: ' . $e->getMessage());
            return null;
        }
    }

    private static function updateDeliveryStatus(?int $deliveryId, string $status, ?string $errorMessage = null): void
    {
        if (!$deliveryId) return;

        try {
            $stmt = self::$pdo->prepare("
                UPDATE notification_deliveries
                SET delivery_status = ?,
                    attempts        = attempts + 1,
                    sent_at         = IF(? = 'sent', NOW(), sent_at),
                    error_message   = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $status, $errorMessage, $deliveryId]);
        } catch (PDOException $e) {
            self::logError('updateDeliveryStatus: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // 🔟  دوال حل معرفات القنوات والأنواع
    // -------------------------------------------------------

    private static function resolveChannelId(string $code): ?int
    {
        if (isset(self::$channelsCache[$code])) {
            return self::$channelsCache[$code];
        }

        try {
            $stmt = self::$pdo->prepare("
                SELECT id FROM notification_channels WHERE code = ? AND is_active = 1 LIMIT 1
            ");
            $stmt->execute([$code]);
            $id = $stmt->fetchColumn();
            self::$channelsCache[$code] = $id ?: null;
            return self::$channelsCache[$code];
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function resolveTypeId(string $code): ?int
    {
        if (isset(self::$typesCache[$code])) {
            return self::$typesCache[$code];
        }

        try {
            $stmt = self::$pdo->prepare("
                SELECT id FROM notification_types WHERE code = ? AND is_active = 1 LIMIT 1
            ");
            $stmt->execute([$code]);
            $id = $stmt->fetchColumn();
            self::$typesCache[$code] = $id ?: null;
            return self::$typesCache[$code];
        } catch (PDOException $e) {
            return null;
        }
    }

    // -------------------------------------------------------
    // 1️⃣1️⃣  جلب بيانات المستخدم
    // -------------------------------------------------------

    private static function getUserData(int $userId): ?array
    {
        if (!self::$pdo) return null;

        try {
            $stmt = self::$pdo->prepare("
                SELECT id, username, email, phone FROM users WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // -------------------------------------------------------
    // 1️⃣2️⃣  عمليات القراءة وإدارة الإشعارات
    // -------------------------------------------------------

    /**
     * جلب إشعارات المستخدم مع بيانات التسليم
     */
    public static function getUserNotifications(
        int    $recipientId,
        int    $tenantId,
        int    $limit  = 20,
        int    $offset = 0
    ): array {
        if (!self::$pdo) return [];

        try {
            $stmt = self::$pdo->prepare("
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
        } catch (PDOException $e) {
            self::logError('getUserNotifications: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب عدد الإشعارات غير المقروءة
     */
    public static function getUnreadCount(
        int    $recipientId,
        string $recipientType = 'user',
        int    $tenantId      = 1
    ): int {
        if (!self::$pdo) return 0;

        try {
            $stmt = self::$pdo->prepare("
                SELECT unread_count FROM notification_counters
                WHERE tenant_id      = ?
                  AND recipient_type = ?
                  AND recipient_id   = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $recipientType, $recipientId]);
            return (int) ($stmt->fetchColumn() ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * إعادة تعيين عداد القراءة إلى صفر
     */
    public static function markAllRead(
        int    $recipientId,
        string $recipientType = 'user',
        int    $tenantId      = 1
    ): bool {
        if (!self::$pdo) return false;

        try {
            $stmt = self::$pdo->prepare("
                UPDATE notification_counters
                SET unread_count = 0
                WHERE tenant_id      = ?
                  AND recipient_type = ?
                  AND recipient_id   = ?
            ");
            $stmt->execute([$tenantId, $recipientType, $recipientId]);
            return true;
        } catch (PDOException $e) {
            self::logError('markAllRead: ' . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------
    // 1️⃣3️⃣  إدارة FCM Token للجهاز
    // -------------------------------------------------------

    /**
     * تسجيل أو تحديث FCM token للمستخدم
     */
    public static function registerDeviceToken(
        int    $userId,
        string $fcmToken,
        string $deviceType = 'web',
        string $deviceName = '',
        string $userAgent  = '',
        string $ip         = ''
    ): bool {
        if (!self::$pdo) return false;

        try {
            $stmt = self::$pdo->prepare("
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
            $stmt->execute([$userId, $fcmToken, $deviceType, $deviceName, $userAgent, $ip]);
            return true;
        } catch (PDOException $e) {
            self::logError('registerDeviceToken: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إلغاء تسجيل FCM token (تسجيل خروج)
     */
    public static function deregisterDeviceToken(string $fcmToken): bool
    {
        if (!self::$pdo) return false;

        try {
            $stmt = self::$pdo->prepare("
                UPDATE user_devices SET is_active = 0 WHERE fcm_token = ?
            ");
            $stmt->execute([$fcmToken]);
            return true;
        } catch (PDOException $e) {
            self::logError('deregisterDeviceToken: ' . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------
    // 1️⃣4️⃣  اختصارات (Shorthand methods)
    // -------------------------------------------------------

    /** تأكيد الطلب */
    public static function orderCreated(int $userId, array $order, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'order_created',
            'تأكيد الطلب - Order Confirmation',
            "تم استلام طلبك #{$order['order_number']} بنجاح. المبلغ: {$order['grand_total']} " . (defined('DEFAULT_CURRENCY_SYMBOL') ? DEFAULT_CURRENCY_SYMBOL : ''),
            ['order_id' => $order['id'], 'order_number' => $order['order_number'], 'total' => $order['grand_total']],
            ['database', 'email', 'sms', 'push'],
            'high'
        );
    }

    /** تغيير حالة الطلب */
    public static function orderStatusChanged(int $userId, string $orderNumber, string $status, int $tenantId = 1): array
    {
        $labels = [
            'confirmed'  => 'تم تأكيد طلبك',
            'processing' => 'جاري تجهيز طلبك',
            'shipped'    => 'تم شحن طلبك',
            'delivered'  => 'تم توصيل طلبك',
            'cancelled'  => 'تم إلغاء طلبك',
        ];
        $title = $labels[$status] ?? 'تحديث الطلب';

        return self::send(
            $userId, 'user', $tenantId,
            'order_status',
            $title,
            "طلبك #{$orderNumber}: {$title}",
            ['order_number' => $orderNumber, 'status' => $status],
            ['database', 'sms', 'push']
        );
    }

    /** شحن الطلب */
    public static function orderShipped(int $userId, string $orderNumber, string $trackingNumber, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'order_shipped',
            'تم شحن طلبك - Order Shipped',
            "طلبك #{$orderNumber} في الطريق إليك. رقم التتبع: {$trackingNumber}",
            ['order_number' => $orderNumber, 'tracking_number' => $trackingNumber],
            ['database', 'email', 'sms', 'push']
        );
    }

    /** نجاح الدفع */
    public static function paymentSuccess(int $userId, string $orderNumber, float $amount, int $tenantId = 1): array
    {
        $currency = defined('DEFAULT_CURRENCY_SYMBOL') ? DEFAULT_CURRENCY_SYMBOL : '';
        return self::send(
            $userId, 'user', $tenantId,
            'payment_success',
            'دفع ناجح - Payment Success',
            "تم استلام دفعتك بنجاح. المبلغ: {$amount} {$currency} للطلب #{$orderNumber}",
            ['order_number' => $orderNumber, 'amount' => $amount],
            ['database', 'email', 'push'],
            'high'
        );
    }

    /** فشل الدفع */
    public static function paymentFailed(int $userId, string $orderNumber, string $reason, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'payment_failed',
            'فشل الدفع - Payment Failed',
            "فشلت عملية الدفع للطلب #{$orderNumber}. السبب: {$reason}",
            ['order_number' => $orderNumber, 'reason' => $reason],
            ['database', 'email', 'sms', 'push'],
            'urgent'
        );
    }

    /** طلب إرجاع */
    public static function returnRequested(int $userId, string $returnNumber, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'return_requested',
            'طلب إرجاع - Return Request',
            "تم استلام طلب الإرجاع #{$returnNumber}. سيتم مراجعته خلال 24 ساعة.",
            ['return_number' => $returnNumber],
            ['database', 'email']
        );
    }

    /** تسجيل دخول من جهاز جديد */
    public static function newDeviceLogin(int $userId, string $device, string $location, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'new_device_login',
            'تسجيل دخول جديد - New Login',
            "تم تسجيل دخول إلى حسابك من جهاز جديد: {$device} في {$location}",
            ['device' => $device, 'location' => $location],
            ['database', 'email'],
            'high'
        );
    }

    /** تغيير كلمة المرور */
    public static function passwordChanged(int $userId, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'password_changed',
            'تم تغيير كلمة المرور - Password Changed',
            'تم تغيير كلمة المرور لحسابك بنجاح. إذا لم تقم بذلك، يرجى التواصل معنا فوراً.',
            [],
            ['database', 'email', 'sms'],
            'urgent'
        );
    }

    /** رد على تذكرة دعم */
    public static function supportTicketReply(int $userId, string $ticketNumber, int $tenantId = 1): array
    {
        return self::send(
            $userId, 'user', $tenantId,
            'support_reply',
            'رد على تذكرتك - Ticket Reply',
            "تم الرد على تذكرة الدعم #{$ticketNumber}. تحقق من الردود الجديدة.",
            ['ticket_number' => $ticketNumber],
            ['database', 'email', 'push']
        );
    }

    /** إرسال جماعي */
    public static function sendBulk(
        array  $userIds,
        string $typeCode,
        string $title,
        string $message,
        array  $data      = [],
        array  $channels  = ['database'],
        int    $tenantId  = 1
    ): array {
        $successCount = 0;
        $failCount    = 0;
        $results      = [];

        foreach ($userIds as $userId) {
            $result = self::send($userId, 'user', $tenantId, $typeCode, $title, $message, $data, $channels);
            $results[] = ['user_id' => $userId, 'result' => $result];
            $result['success'] ? $successCount++ : $failCount++;
        }

        return [
            'total'         => count($userIds),
            'success_count' => $successCount,
            'fail_count'    => $failCount,
            'results'       => $results,
        ];
    }

    // -------------------------------------------------------
    // 🔧  Logging
    // -------------------------------------------------------

    private static function logNotification(string $channel, mixed $recipient, string $status): void
    {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $line = sprintf(
                "[%s] [Notification] channel=%s recipient=%s status=%s\n",
                date('Y-m-d H:i:s'), $channel, $recipient, $status
            );
            $logFile = defined('LOG_FILE_API') ? LOG_FILE_API : ini_get('error_log');
            error_log($line, 3, $logFile);
        }
    }

    private static function logError(string $message): void
    {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $logFile = defined('LOG_FILE_ERROR') ? LOG_FILE_ERROR : ini_get('error_log');
            error_log('[Notification Error] ' . $message . "\n", 3, $logFile);
        }
    }
}

// ✅ تم تحميل Notification Helper بنجاح