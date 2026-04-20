<?php

declare(strict_types=1);

// ===========================================
// jwt.php  —  PRODUCTION-HARDENED VERSION
// Algorithm: RS256 (asymmetric, RSA-SHA256)
// Fixes: CWE-347 / CVSS 7.4  (RS256 asymmetric)
//        CWE-347 / CVSS 9.8  (algorithm confusion / "none" attack)
//
// SECURITY MODEL:
//   - Algorithm is NEVER read from the token header.
//   - ALLOWED_ALGORITHM is a server-side constant — attacker cannot override it.
//   - "alg: none", "alg: HS256", or any other value in the header is irrelevant:
//     we always verify with RS256 using our public key.
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/repositories/JwtRepository.php';

class JWT
{
    /**
     * الخوارزمية الوحيدة المقبولة — ثابتة في الكود، لا تُقرأ من الـ token.
     * أي token يحتوي على alg مختلف يُرفض فوراً بعد التحقق من التوقيع.
     */
    private const ALLOWED_ALGORITHM = 'RS256';
    private const OPENSSL_ALGO      = OPENSSL_ALGO_SHA256;

    /**
     * قائمة سوداء صريحة على مستوى Library — CWE-347 / CVSS 9.8
     *
     * هذه القائمة تُرفض قبل أي عملية أخرى، حتى قبل محاولة التحقق من التوقيع.
     * الهدف: منع هجوم "alg: none" حتى لو فشل openssl_verify بطريقة غير متوقعة.
     *
     * "none"    → token بدون توقيع — الهجوم الأساسي (RFC 7518 §3.6)
     * "None"    → تحايل بالحالة (case bypass)
     * "NONE"    → تحايل بالحالة
     * "nOnE"    → تحايل بالحالة
     * "HS256"   → algorithm confusion: توقيع بـ public key كـ HMAC secret
     * "HS384"   → نفس الهجوم
     * "HS512"   → نفس الهجوم
     * ""        → header فارغ
     */
    private const BLOCKED_ALGORITHMS = [
        'none', 'None', 'NONE', 'nOnE', 'nONE', 'NoNe', 'NONe', 'noNE',
        'HS256', 'HS384', 'HS512',
        'RS384', 'RS512',   // نقبل RS256 فقط — أي RSA آخر مرفوض
        'PS256', 'PS384', 'PS512',
        'ES256', 'ES384', 'ES512',
        '',
    ];

    // ------------------------------------------
    // 1. ENCODE  (إنشاء Token)
    // ------------------------------------------

    /**
     * إنشاء JWT Token موقَّع بـ RS256
     *
     * @param array    $payload  البيانات المراد تشفيرها
     * @param int|null $expiry   مدة الصلاحية بالثواني
     * @return string
     */
    public static function encode(array $payload, ?int $expiry = null): string
    {
        $expiry ??= JWT_EXPIRY;

        // Header — نُحدد الخوارزمية نحن، لا يُقبل أي إدخال خارجي هنا
        $header = ['typ' => 'JWT', 'alg' => self::ALLOWED_ALGORITHM];

        // Claims
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['jti'] = self::generateJTI();

        $headerEncoded  = self::base64UrlEncode(json_encode($header,  JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // التوقيع بالمفتاح الخاص (Private Key)
        $privateKey = self::loadPrivateKey();
        $signingInput = "$headerEncoded.$payloadEncoded";

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privateKey, self::OPENSSL_ALGO)) {
            throw new \RuntimeException('JWT signing failed: ' . openssl_error_string());
        }

        return "$headerEncoded.$payloadEncoded." . self::base64UrlEncode($signature);
    }

    // ------------------------------------------
    // 2. DECODE  (فك تشفير والتحقق)
    // ------------------------------------------

    /**
     * فك تشفير والتحقق من JWT Token
     *
     * ⚠️  SECURITY — CWE-347 / CVSS 9.8 (algorithm confusion):
     *   نتجاهل قيمة `alg` الموجودة في الـ token header تماماً عند اتخاذ قرار
     *   الخوارزمية. نحن دائماً نستخدم RS256 + المفتاح العام للتحقق.
     *   بعد نجاح التحقق من التوقيع، نتأكد أن `alg` في الـ header == RS256
     *   لرفض أي token مُصطنع يدّعي خوارزمية مختلفة.
     *
     * @param string $token
     * @return array|false
     */
    public static function decode(string $token): array|false
    {
        try {
            // ══════════════════════════════════════════════════════════════
            // الخطوة 1 — تقسيم الـ token
            // ══════════════════════════════════════════════════════════════

            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                self::logError('Invalid token format: must have 3 parts');
                return false;
            }

            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // ══════════════════════════════════════════════════════════════
            // الخطوة 2 — LIBRARY-LEVEL ALGORITHM REJECTION (CWE-347 / CVSS 9.8)
            //
            // نقرأ `alg` من الـ header الآن لغرض واحد فقط: رفضه إذا لم يكن RS256.
            // هذا الفحص يحدث قبل أي عملية أخرى — قبل base64UrlDecode للتوقيع،
            // وقبل استدعاء openssl_verify تماماً.
            //
            // لماذا هذا ضروري رغم أننا نستخدم self::OPENSSL_ALGO ثابتاً؟
            //
            //   سيناريو 1 — "alg: none":
            //     المهاجم يرسل {"alg":"none"} مع signatureEncoded = ""
            //     base64UrlDecode("") = "" (string فارغ، لا exception)
            //     openssl_verify("", ...) = 0  ← يُرفض ✅
            //     لكن: بعض إصدارات PHP/OpenSSL تُعيد -1 وليس 0 على توقيع فارغ،
            //     وسلوك -1 قد يُعامَل بشكل مختلف في المستقبل.
            //     الرفض المبكر هنا يُغلق هذا الاحتمال نهائياً. ✅
            //
            //   سيناريو 2 — "alg: HS256" (algorithm confusion):
            //     المهاجم يوقّع بـ HMAC-SHA256 مستخدماً الـ public key كـ secret.
            //     openssl_verify بـ OPENSSL_ALGO_SHA256 سيفشل لأنه يتوقع RSA signature.
            //     لكن الرفض المبكر هنا يمنع أي محاولة قبل أن تبدأ. ✅
            //
            //   سيناريو 3 — "alg" مفقود كلياً من الـ header:
            //     يُرفض هنا (MISSING ليس في ALLOWED_ALGORITHM). ✅
            //
            // ══════════════════════════════════════════════════════════════

            // parse الـ header فقط — لا نلمس الـ payload أو التوقيع بعد
            $headerDecoded = self::base64UrlDecode($headerEncoded);
            $header        = json_decode($headerDecoded, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($header)) {
                self::logError('Header is not a valid JSON object');
                return false;
            }

            $claimedAlg = $header['alg'] ?? 'MISSING';

            // ❌ رفض صريح بالاسم — "none" بجميع حالاته وكل خوارزمية غير RS256
            if (in_array($claimedAlg, self::BLOCKED_ALGORITHMS, strict: true)) {
                self::logError('BLOCKED algorithm rejected at library level: "' . $claimedAlg . '"');
                return false;
            }

            // ❌ رفض أي خوارزمية غير RS256 — allowlist بدلاً من blocklist فقط
            // (defense-in-depth: حتى لو ظهرت خوارزمية جديدة لم تُضَف للقائمة السوداء)
            if ($claimedAlg !== self::ALLOWED_ALGORITHM) {
                self::logError('Algorithm not in allowlist: "' . $claimedAlg . '" — only ' . self::ALLOWED_ALGORITHM . ' accepted');
                return false;
            }

            // ══════════════════════════════════════════════════════════════
            // الخطوة 3 — التحقق من التوقيع (بعد التأكد من الخوارزمية)
            //
            // نستخدم self::OPENSSL_ALGO الثابت — لا نمرر $claimedAlg لـ openssl.
            // المهاجم لا يستطيع التأثير على الخوارزمية المستخدمة هنا.
            // ══════════════════════════════════════════════════════════════

            $publicKey    = self::loadPublicKey();
            $signingInput = "$headerEncoded.$payloadEncoded";
            $signature    = self::base64UrlDecode($signatureEncoded);

            // التحقق من أن التوقيع ليس فارغاً (none attack — طبقة إضافية)
            if ($signature === '') {
                self::logError('Empty signature rejected');
                return false;
            }

            // openssl_verify: 1=صحيح، 0=خاطئ، -1=خطأ داخلي
            $verified = openssl_verify($signingInput, $signature, $publicKey, self::OPENSSL_ALGO);

            if ($verified !== 1) {
                $opensslErr = openssl_error_string() ?: 'no openssl error';
                self::logError('Signature verification failed (result=' . $verified . ', openssl=' . $opensslErr . ')');
                return false;
            }

            // ══════════════════════════════════════════════════════════════
            // الخطوة 4 — parse الـ payload (بعد التحقق من التوقيع)
            // ══════════════════════════════════════════════════════════════

            $payload = json_decode(self::base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                self::logError('Payload is not a valid JSON object');
                return false;
            }

            // ══════════════════════════════════════════════════════════════
            // الخطوة 5 — التحقق من Claims (كلها إلزامية)
            // ══════════════════════════════════════════════════════════════

            // exp — انتهاء الصلاحية
            if (!isset($payload['exp']) || !is_int($payload['exp'])) {
                self::logError('Token missing or invalid exp claim');
                return false;
            }
            if ($payload['exp'] < time()) {
                self::logError('Token expired at: ' . date('Y-m-d H:i:s', $payload['exp']));
                return false;
            }

            // iat — وقت الإصدار
            if (!isset($payload['iat']) || !is_int($payload['iat'])) {
                self::logError('Token missing or invalid iat claim');
                return false;
            }
            if ($payload['iat'] > time() + 60) {
                self::logError('Token issued in future (iat=' . $payload['iat'] . ')');
                return false;
            }

            // jti — معرف فريد للقائمة السوداء
            if (empty($payload['jti']) || !is_string($payload['jti'])) {
                self::logError('Token missing or invalid jti claim');
                return false;
            }
            if (self::isJTIRevoked($payload['jti'])) {
                self::logError('Token JTI is revoked: ' . $payload['jti']);
                return false;
            }

            return $payload;

        } catch (\JsonException $e) {
            self::logError('JSON decode error: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            self::logError('Exception during decode: ' . $e->getMessage());
            return false;
        }
    }

    // ------------------------------------------
    // 3. Bearer Token من الـ Headers
    // ------------------------------------------

    public static function getBearerToken(): ?string
    {
        $header = self::getAuthorizationHeader();

        if ($header !== null && preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    // ------------------------------------------
    // 4. إنشاء Access Token
    // ------------------------------------------

    public static function createAccessToken(array $user): string
    {
        return self::encode([
            'user_id'   => $user['id'],
            'email'     => $user['email'],
            'user_type' => $user['user_type'],
            'username'  => $user['username'] ?? null,
            'type'      => 'access',
        ], JWT_EXPIRY);
    }

    // ------------------------------------------
    // 5. إنشاء Refresh Token
    // ------------------------------------------

    public static function createRefreshToken(int $userId): string
    {
        return self::encode([
            'user_id' => $userId,
            'type'    => 'refresh',
            'random'  => bin2hex(random_bytes(32)), // 256-bit entropy
        ], REFRESH_TOKEN_EXPIRY);
    }

    // ------------------------------------------
    // 6. التحقق من Refresh Token
    // ------------------------------------------

    public static function verifyRefreshToken(string $token): array|false
    {
        $payload = self::decode($token);

        if ($payload === false) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            self::logError('Not a refresh token');
            return false;
        }

        return $payload;
    }

    // ------------------------------------------
    // 7. OTP Token
    // ------------------------------------------

    public static function createOTPToken(string $identifier, string $otp): string
    {
        return self::encode([
            'identifier' => $identifier,
            'otp'        => hash('sha256', $otp),
            'type'       => 'otp',
        ], OTP_EXPIRY);
    }

    public static function verifyOTPToken(string $token, string $otp): array|false
    {
        $payload = self::decode($token);

        if ($payload === false) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'otp') {
            self::logError('Not an OTP token');
            return false;
        }

        if (!hash_equals($payload['otp'] ?? '', hash('sha256', $otp))) {
            self::logError('OTP mismatch');
            return false;
        }

        return $payload;
    }

    // ------------------------------------------
    // 8. Password Reset Token
    // ------------------------------------------

    public static function createPasswordResetToken(int $userId, string $email): string
    {
        return self::encode([
            'user_id' => $userId,
            'email'   => $email,
            'type'    => 'password_reset',
            'random'  => bin2hex(random_bytes(32)),
        ], 3600);
    }

    public static function verifyPasswordResetToken(string $token): array|false
    {
        $payload = self::decode($token);

        if ($payload === false) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'password_reset') {
            self::logError('Not a password reset token');
            return false;
        }

        return $payload;
    }

    // ------------------------------------------
    // 9. دوال مساعدة
    // ------------------------------------------

    public static function getUserIdFromToken(?string $token = null): ?int
    {
        $token ??= self::getBearerToken();

        if ($token === null) {
            return null;
        }

        $payload = self::decode($token);

        return ($payload !== false) ? ($payload['user_id'] ?? null) : null;
    }

    public static function getCurrentPayload(): ?array
    {
        $token = self::getBearerToken();

        if ($token === null) {
            return null;
        }

        $payload = self::decode($token);

        return ($payload !== false) ? $payload : null;
    }

    public static function isValid(?string $token = null): bool
    {
        $token ??= self::getBearerToken();

        return $token !== null && self::decode($token) !== false;
    }

    public static function getTimeRemaining(string $token): ?int
    {
        $payload = self::decode($token);

        if ($payload === false || !isset($payload['exp'])) {
            return null;
        }

        $remaining = $payload['exp'] - time();

        return max(0, $remaining);
    }

    // ------------------------------------------
    // 10. إنشاء Token Pair
    // ------------------------------------------

    public static function createTokenPair(array $user): array
    {
        return [
            'access_token'       => self::createAccessToken($user),
            'refresh_token'      => self::createRefreshToken($user['id']),
            'token_type'         => 'Bearer',
            'expires_in'         => JWT_EXPIRY,
            'refresh_expires_in' => REFRESH_TOKEN_EXPIRY,
        ];
    }

    // ------------------------------------------
    // 11. تجديد Access Token
    // ------------------------------------------

    public static function refreshAccessToken(string $refreshToken, PDO $pdo): array|false
    {
        $payload = self::verifyRefreshToken($refreshToken);

        if ($payload === false) {
            return false;
        }

        $repo = new JwtRepository($pdo);
        $user = $repo->findActiveUserWithRole($payload['user_id']);

        if (!$user) {
            self::logError('User not found or inactive for refresh token');
            return false;
        }

        return [
            'access_token' => self::createAccessToken($user),
            'token_type'   => 'Bearer',
            'expires_in'   => JWT_EXPIRY,
        ];
    }

    // ------------------------------------------
    // 12. Revoke / Blacklist
    // ------------------------------------------

    public static function revokeJTI(string $jti, int $userId, string $type, PDO $pdo): bool
    {
        $repo = new JwtRepository($pdo);

        return $repo->insertJtiBlacklist(
            $jti,
            $userId,
            $type,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );
    }

    // ------------------------------------------
    // 13. Session helpers (تمرير PDO صريح)
    // ------------------------------------------

    public static function saveUserSession(int $userId, string $token, PDO $pdo): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
        $repo = new JwtRepository($pdo);

        return $repo->insertUserSession(
            $userId,
            $token,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $expiresAt,
        );
    }

    public static function revokeUserSession(string $token, PDO $pdo): bool
    {
        $repo = new JwtRepository($pdo);
        return $repo->revokeUserSession($token);
    }

    public static function hasPermission(int $userId, string $permissionKey, PDO $pdo): bool
    {
        $repo = new JwtRepository($pdo);
        return $repo->userHasPermission($userId, $permissionKey);
    }

    // ------------------------------------------
    // 🔧 Private Helpers
    // ------------------------------------------

    /**
     * تحميل المفتاح الخاص (Private Key) من الملف
     * تأكد من أن المسار خارج الـ webroot وصلاحياته 600
     */
    private static function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        $path = defined('JWT_PRIVATE_KEY_PATH') ? JWT_PRIVATE_KEY_PATH : '';

        if (empty($path) || !is_readable($path)) {
            throw new \RuntimeException('JWT private key file not found or not readable');
        }

        $key = openssl_pkey_get_private(file_get_contents($path));

        if ($key === false) {
            throw new \RuntimeException('Failed to load JWT private key: ' . openssl_error_string());
        }

        return $key;
    }

    /**
     * تحميل المفتاح العام (Public Key) من الملف
     */
    private static function loadPublicKey(): \OpenSSLAsymmetricKey
    {
        $path = defined('JWT_PUBLIC_KEY_PATH') ? JWT_PUBLIC_KEY_PATH : '';

        if (empty($path) || !is_readable($path)) {
            throw new \RuntimeException('JWT public key file not found or not readable');
        }

        $key = openssl_pkey_get_public(file_get_contents($path));

        if ($key === false) {
            throw new \RuntimeException('Failed to load JWT public key: ' . openssl_error_string());
        }

        return $key;
    }

    private static function isJTIRevoked(string $jti): bool
    {
        // ملاحظة: يجب تمرير $pdo صريحاً عبر Dependency Injection
        // هذا حل مؤقت — راجع قسم "التوصيات" في الـ README
        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return false;
        }

        $repo = new JwtRepository($pdo);
        return $repo->isJtiBlacklisted($jti);
    }

    private static function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (function_exists('apache_request_headers')) {
            $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
            if (isset($headers['authorization'])) {
                return trim($headers['authorization']);
            }
        }

        return null;
    }

    private static function generateJTI(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $base64 = strtr($data, '-_', '+/');
        $pad    = strlen($base64) % 4;

        if ($pad) {
            $base64 .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($base64, true) ?: throw new \RuntimeException('base64url decode failed');
    }

    private static function logError(string $message): void
    {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log('[JWT Error] ' . $message, 3, LOG_FILE_AUTH);
        }

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[JWT Debug] ' . $message);
        }
    }
}
