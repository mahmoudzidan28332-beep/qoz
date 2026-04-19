<?php declare(strict_types=1);
// htdocs/api/shared/helpers/notification_push.php
// Push notification methods extracted from NotificationHelper (Firebase FCM)

require_once __DIR__ . '/../core/repositories/NotificationRepository.php';

trait NotificationPushTrait
{
    private static ?string $cachedAccessToken = null;
    private static int $tokenExpiresAt = 0;

    // -------------------------------------------------------
    // قناة Push — Firebase FCM
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
    // FCM v1 API — الطريقة الحديثة (OAuth2 + Service Account)
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
                $repo = new NotificationRepository(self::$pdo);
                $repo->deactivateDeviceByToken($badToken);
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
    // FCM Legacy API — fallback (deprecated)
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
    // FCM v1 API — OAuth2 Access Token من Service Account
    // -------------------------------------------------------

    private static function getFcmAccessToken(): ?string
    {
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
            $jwtTtlSeconds = 3600;
            $exp = $now + $jwtTtlSeconds;

            $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = self::base64UrlEncode(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $sa['token_uri'],
                'iat'   => $now,
                'exp'   => $exp,
            ]));

            $signingInput = "{$header}.{$claims}";

            $privateKey = openssl_pkey_get_private($sa['private_key']);
            if (!$privateKey) {
                self::logError('FCM: Failed to parse private key from service account');
                return null;
            }

            $signature = '';
            openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = $signingInput . '.' . self::base64UrlEncode($signature);

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
            $tokenExpiryBuffer = 60;
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
    // جلب FCM tokens من user_devices
    // -------------------------------------------------------

    private static function getFcmTokens(int $userId, string $recipientType, array $deviceIds = []): array
    {
        if (!self::$pdo) return [];

        if ($recipientType !== 'user') return [];

        try {
            $repo = new NotificationRepository(self::$pdo);
            if (!empty($deviceIds)) {
                return $repo->getFcmTokensForDevices($userId, $deviceIds);
            }
            return $repo->getFcmTokensForUser($userId);
        } catch (PDOException $e) {
            self::logError('getFcmTokens: ' . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------
    // تنظيف FCM tokens المنتهية الصلاحية
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
                        $repo = new NotificationRepository(self::$pdo);
                        $repo->deactivateDeviceByToken($invalidToken);
                    } catch (PDOException $e) {
                        self::logError('cleanInvalidTokens: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
