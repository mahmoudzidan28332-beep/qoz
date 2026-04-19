<?php declare(strict_types=1);
require_once __DIR__ . '/../core/repositories/NotificationRepository.php';
require_once __DIR__ . '/notification_push.php';
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
    use NotificationPushTrait;

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

        $repo = new NotificationRepository(self::$pdo);
        return $repo->insertNotification(
            $tenantId, $senderEntityId, $entityId, $title, $message,
            $dataJson, $typeId, $priority, $expiresAt
        );
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
            $repo = new NotificationRepository(self::$pdo);
            $repo->upsertNotificationCounter($tenantId, $recipientType, $recipientId);

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
    // 9️⃣  حفظ سجل التسليم في notification_deliveries
    // -------------------------------------------------------

    private static function insertDelivery(int $notificationId, ?int $channelId): ?int
    {
        if (!$channelId) return null;

        try {
            $repo = new NotificationRepository(self::$pdo);
            return $repo->insertDelivery($notificationId, $channelId);
        } catch (PDOException $e) {
            self::logError('insertDelivery: ' . $e->getMessage());
            return null;
        }
    }

    private static function updateDeliveryStatus(?int $deliveryId, string $status, ?string $errorMessage = null): void
    {
        if (!$deliveryId) return;

        try {
            $repo = new NotificationRepository(self::$pdo);
            $repo->updateDeliveryStatus($deliveryId, $status, $errorMessage);
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
            $repo = new NotificationRepository(self::$pdo);
            $id = $repo->resolveChannelId($code);
            self::$channelsCache[$code] = $id;
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
            $repo = new NotificationRepository(self::$pdo);
            $id = $repo->resolveTypeId($code);
            self::$typesCache[$code] = $id;
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
            $repo = new NotificationRepository(self::$pdo);
            return $repo->getUserData($userId);
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
            $repo = new NotificationRepository(self::$pdo);
            return $repo->getUserNotifications($recipientId, $tenantId, $limit, $offset);
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
            $repo = new NotificationRepository(self::$pdo);
            return $repo->getUnreadCount($recipientId, $recipientType, $tenantId);
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
            $repo = new NotificationRepository(self::$pdo);
            $repo->resetUnreadCount($recipientId, $recipientType, $tenantId);
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
            $repo = new NotificationRepository(self::$pdo);
            $repo->registerDeviceToken($userId, $fcmToken, $deviceType, $deviceName, $userAgent, $ip);
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
            $repo = new NotificationRepository(self::$pdo);
            $repo->deregisterDeviceToken($fcmToken);
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