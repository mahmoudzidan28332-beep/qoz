<?php
declare(strict_types=1);

/**
 * Security Helper - Production Version
 *
 * Enterprise-grade security utilities
 * - Password hashing & validation (Argon2id/bcrypt)
 * - AES-256-GCM encryption with key rotation support
 * - CSRF protection
 * - Rate limiting (Session/Redis)
 * - Input sanitization & validation
 * - Brute force protection
 * - XSS/SQL injection prevention
 *
 * ## Key Rotation Support
 *
 * Encryption supports versioning for seamless key rotation:
 *
 * ```php
 * // Encrypt with version 1 (default)
 * $encrypted = Security::encryptForEntity($data, $tenantId, $entityId);
 *
 * // Later, rotate to version 2
 * $newEncrypted = Security::rotateEntityKey($encrypted, $tenantId, $entityId, 2);
 *
 * // Decryption automatically uses correct version
 * $decrypted = Security::decryptForEntity($newEncrypted, $tenantId, $entityId);
 *
 * // Check current version
 * $version = Security::getEncryptionVersion($encrypted); // Returns: 1 or 2
 * ```
 *
 * ## Migration from Legacy Methods
 *
 * Legacy `encrypt()`/`decrypt()` methods are deprecated:
 * - They trigger E_USER_DEPRECATED warnings
 * - They log security events for audit trails
 * - Use `encryptForEntity()`/`decryptForEntity()` instead
 *
 * @version 2.0.0
 * @package SecurityCore
 */

// ===========================================
// Dependencies
// ===========================================

if (!defined('SECURITY_HELPER_LOADED')) {
    define('SECURITY_HELPER_LOADED', true);

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../core/CryptoConfig.php';
    require_once __DIR__ . '/security_validation.php';
    require_once __DIR__ . '/security_utils.php';
}

// ===========================================
// Security Class
// ===========================================

final class Security
{
    use SecurityValidationTrait;
    use SecurityUtilsTrait;

    // Cache للمفاتيح المشتقة لتحسين الأداء
    private static array $keyCache = [];
    private static int $keyCacheLimit = 100;

    // منع إنشاء instance
    private function __construct() {}
    private function __clone() {}

    // ===========================================
    // 1️⃣ Password Management
    // ===========================================

    /**
     * Hash password using Argon2id or bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     * @throws InvalidArgumentException
     */
    public static function hashPassword(string $password): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }

        if (strlen($password) > 72) {
            // bcrypt limitation workaround
            $password = hash('sha256', $password);
        }

        $algo = defined('PASSWORD_HASH_ALGO') ? PASSWORD_HASH_ALGO : PASSWORD_ARGON2ID;
        $cost = defined('PASSWORD_HASH_COST') ? PASSWORD_HASH_COST : 12;

        $options = $algo === PASSWORD_ARGON2ID ? [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ] : ['cost' => $cost];

        $hash = password_hash($password, $algo, $options);

        if ($hash === false) {
            throw new RuntimeException('Password hashing failed');
        }

        return $hash;
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Stored hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        // Timing-safe verification
        return password_verify($password, $hash);
    }

    /**
     * Validate password strength with detailed feedback
     *
     * @param string $password
     * @param array $options Custom validation rules
     * @return array ['valid' => bool, 'errors' => array, 'strength' => string, 'score' => int]
     */
    public static function validatePasswordStrength(string $password, array $options = []): array
    {
        $minLength = $options['min_length'] ?? (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8);
        $requireLower = $options['require_lowercase'] ?? true;
        $requireUpper = $options['require_uppercase'] ?? true;
        $requireNumber = $options['require_number'] ?? true;
        $requireSpecial = $options['require_special'] ?? true;

        $errors = [];
        $score = 0;

        // Length check
        $length = strlen($password);
        if ($length < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters";
        } else {
            $score += min(25, floor($length / 2) * 5);
        }

        // Character variety checks
        if ($requireLower && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (preg_match('/[a-z]/', $password)) {
            $score += 15;
        }

        if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (preg_match('/[A-Z]/', $password)) {
            $score += 15;
        }

        if ($requireNumber && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (preg_match('/[0-9]/', $password)) {
            $score += 15;
        }

        if ($requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        } elseif (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 20;
        }

        // Bonus points
        if (preg_match_all('/[^a-zA-Z0-9]/', $password) > 1) {
            $score += 10; // Multiple special chars
        }

        // Common patterns penalty
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 10; // Repeated characters
        }

        if (preg_match('/^[0-9]+$/', $password)) {
            $score -= 20; // Only numbers
        }

        // Determine strength
        $score = max(0, min(100, $score));

        if ($score >= 80) {
            $strength = 'very_strong';
        } elseif ($score >= 60) {
            $strength = 'strong';
        } elseif ($score >= 40) {
            $strength = 'medium';
        } elseif ($score >= 20) {
            $strength = 'weak';
        } else {
            $strength = 'very_weak';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength,
            'score' => $score
        ];
    }

    /**
     * Check if password needs rehashing
     *
     * @param string $password Plain password
     * @param string $hash Current hash
     * @return string|null New hash or null
     */
    public static function rehashPasswordIfNeeded(string $password, string $hash): ?string
    {
        $algo = defined('PASSWORD_HASH_ALGO') ? PASSWORD_HASH_ALGO : PASSWORD_ARGON2ID;
        $cost = defined('PASSWORD_HASH_COST') ? PASSWORD_HASH_COST : 12;

        $options = $algo === PASSWORD_ARGON2ID ? [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ] : ['cost' => $cost];

        if (password_needs_rehash($hash, $algo, $options)) {
            return self::hashPassword($password);
        }

        return null;
    }

    // ===========================================
    // 2️⃣ Advanced Encryption (AES-256-GCM)
    // ===========================================

    /**
     * Derive entity-specific encryption key
     *
     * @param int $tenantId
     * @param int $entityId
     * @param int $version Encryption version for key rotation
     * @return string Binary key
     */
    private static function deriveEntityKey(int $tenantId, int $entityId, int $version = 1): string
    {
        $cacheKey = "{$tenantId}:{$entityId}:v{$version}";

        if (isset(self::$keyCache[$cacheKey])) {
            return self::$keyCache[$cacheKey];
        }

        // Key derivation using HKDF with version support
        $info = "tenant:{$tenantId}|entity:{$entityId}";
        $salt = "entity-encryption-v{$version}";

        $key = hash_hkdf(
            'sha256',
            CryptoConfig::masterKey(),
            32,
            $info,
            $salt
        );

        // Cache management
        if (count(self::$keyCache) >= self::$keyCacheLimit) {
            self::$keyCache = array_slice(self::$keyCache, -50, null, true);
        }

        self::$keyCache[$cacheKey] = $key;

        return $key;
    }

    /**
     * Encrypt data for specific entity using AES-256-GCM
     *
     * @param string $plainText
     * @param int $tenantId
     * @param int $entityId
     * @param int $version Encryption version (1-255)
     * @return string Base64 encoded: version(1) + iv(12) + tag(16) + cipher
     * @throws RuntimeException
     */
    public static function encryptForEntity(
        string $plainText,
        int $tenantId,
        int $entityId,
        int $version = 1
    ): string {
        if (empty($plainText)) {
            throw new InvalidArgumentException('Plain text cannot be empty');
        }

        if ($version < 1 || $version > 255) {
            throw new InvalidArgumentException('Encryption version must be between 1 and 255');
        }

        try {
            $key = self::deriveEntityKey($tenantId, $entityId, $version);
            $iv = random_bytes(12);
            $tag = '';

            $cipher = openssl_encrypt(
                $plainText,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );

            if ($cipher === false) {
                throw new RuntimeException('Encryption failed');
            }

            // Version byte for key rotation support
            $versionByte = chr($version);

            return base64_encode($versionByte . $iv . $tag . $cipher);

        } catch (Exception $e) {
            self::logError('Encryption error: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'version' => $version
            ]);
            throw new RuntimeException('Encryption failed', 0, $e);
        }
    }

    /**
     * Decrypt entity-specific data
     *
     * @param string $encrypted Base64 encoded encrypted data
     * @param int $tenantId
     * @param int $entityId
     * @return string Decrypted plain text
     * @throws RuntimeException
     */
    public static function decryptForEntity(
        string $encrypted,
        int $tenantId,
        int $entityId
    ): string {
        if (empty($encrypted)) {
            throw new InvalidArgumentException('Encrypted data cannot be empty');
        }

        try {
            $data = base64_decode($encrypted, true);

            if ($data === false || strlen($data) < 29) {
                throw new RuntimeException('Invalid encrypted data format');
            }

            // Extract version for key derivation
            $version = ord($data[0]);

            if ($version < 1 || $version > 255) {
                throw new RuntimeException('Unsupported encryption version');
            }

            $key = self::deriveEntityKey($tenantId, $entityId, $version);
            $iv = substr($data, 1, 12);
            $tag = substr($data, 13, 16);
            $cipher = substr($data, 29);

            $plainText = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plainText === false) {
                throw new RuntimeException('Decryption failed - invalid tag or corrupted data');
            }

            return $plainText;

        } catch (Exception $e) {
            self::logError('Decryption error: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId
            ]);
            throw new RuntimeException('Decryption failed', 0, $e);
        }
    }

    /**
     * Legacy encryption method (backward compatibility)
     *
     * @deprecated Use encryptForEntity for new implementations
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public static function encrypt(string $data, ?string $key = null): string
    {
        // Trigger deprecation warning
        trigger_error(
            'Security::encrypt() is deprecated. Use Security::encryptForEntity() for sensitive data. ' .
            'This method uses weaker CBC mode and should not be used for new implementations.',
            E_USER_DEPRECATED
        );

        self::logSecurityEvent('LEGACY_ENCRYPTION_USED', 'Legacy encrypt() method called', [
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
        ]);

        $key = $key ?? (defined('JWT_SECRET') ? JWT_SECRET : CryptoConfig::masterKey());
        $method = 'AES-256-CBC';

        $ivLength = openssl_cipher_iv_length($method);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Legacy decryption method
     *
     * @deprecated Use decryptForEntity for new implementations
     * @param string $encryptedData
     * @param string|null $key
     * @return string
     */
    public static function decrypt(string $encryptedData, ?string $key = null): string
    {
        // Trigger deprecation warning
        trigger_error(
            'Security::decrypt() is deprecated. Use Security::decryptForEntity() for sensitive data. ' .
            'This method uses weaker CBC mode and should not be used for new implementations.',
            E_USER_DEPRECATED
        );

        self::logSecurityEvent('LEGACY_DECRYPTION_USED', 'Legacy decrypt() method called', [
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
        ]);

        $key = $key ?? (defined('JWT_SECRET') ? JWT_SECRET : CryptoConfig::masterKey());
        $method = 'AES-256-CBC';

        try {
            $data = base64_decode($encryptedData, true);

            if ($data === false) {
                throw new RuntimeException('Invalid base64 data');
            }

            $ivLength = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);

            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                throw new RuntimeException('Decryption failed');
            }

            return $decrypted;

        } catch (Exception $e) {
            self::logError('Legacy decryption failed: ' . $e->getMessage());
            throw new RuntimeException('Decryption failed', 0, $e);
        }
    }

    /**
     * Re-encrypt data with new version (for key rotation)
     *
     * @param string $encrypted Old encrypted data
     * @param int $tenantId
     * @param int $entityId
     * @param int $newVersion New encryption version
     * @return string New encrypted data
     * @throws RuntimeException
     */
    public static function rotateEntityKey(
        string $encrypted,
        int $tenantId,
        int $entityId,
        int $newVersion
    ): string {
        if ($newVersion < 2 || $newVersion > 255) {
            throw new InvalidArgumentException('New version must be between 2 and 255');
        }

        try {
            // Decrypt with old key
            $plainText = self::decryptForEntity($encrypted, $tenantId, $entityId);

            // Re-encrypt with new version
            $newEncrypted = self::encryptForEntity($plainText, $tenantId, $entityId, $newVersion);

            self::logSecurityEvent('KEY_ROTATION', "Entity key rotated to version {$newVersion}", [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'new_version' => $newVersion
            ]);

            return $newEncrypted;

        } catch (Exception $e) {
            self::logError('Key rotation failed: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'target_version' => $newVersion
            ]);
            throw new RuntimeException('Key rotation failed', 0, $e);
        }
    }

    /**
     * Get encryption version from encrypted data
     *
     * @param string $encrypted
     * @return int Version number
     * @throws RuntimeException
     */
    public static function getEncryptionVersion(string $encrypted): int
    {
        try {
            $data = base64_decode($encrypted, true);

            if ($data === false || strlen($data) < 1) {
                throw new RuntimeException('Invalid encrypted data');
            }

            return ord($data[0]);

        } catch (Exception $e) {
            throw new RuntimeException('Cannot determine encryption version', 0, $e);
        }
    }

    // ===========================================
    // 3️⃣ Token Generation
    // ===========================================

    /**
     * Generate cryptographically secure random token
     *
     * @param int $length Length in bytes (result will be 2x in hex)
     * @return string Hex token
     */
    public static function generateToken(int $length = 32): string
    {
        if ($length < 16) {
            throw new InvalidArgumentException('Token length must be at least 16 bytes');
        }

        return bin2hex(random_bytes($length));
    }

    /**
     * Generate numeric OTP code
     *
     * @param int $length Number of digits
     * @return string Numeric OTP
     */
    public static function generateOTP(int $length = 6): string
    {
        if ($length < 4 || $length > 10) {
            throw new InvalidArgumentException('OTP length must be between 4 and 10');
        }

        $min = (int)pow(10, $length - 1);
        $max = (int)pow(10, $length) - 1;

        return str_pad((string)random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Generate alphanumeric coupon code
     *
     * @param int $length Code length
     * @param string $prefix Optional prefix
     * @return string Coupon code
     */
    public static function generateCouponCode(int $length = 8, string $prefix = ''): string
    {
        if ($length < 4 || $length > 32) {
            throw new InvalidArgumentException('Coupon length must be between 4 and 32');
        }

        // Exclude confusing characters: I, O, 0, 1
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($characters) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $maxIndex)];
        }

        return $prefix . $code;
    }

    /**
     * Generate UUID v4
     *
     * @return string UUID
     */
    public static function generateUUID(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ===========================================
    // 6️⃣ CSRF Protection
    // ===========================================

    /**
     * Generate CSRF token
     *
     * @param string $formId Optional form identifier
     * @return string
     */
    public static function generateCSRFToken(string $formId = 'default'): string
    {
        self::ensureSession();

        $token = self::generateToken(32);

        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        $_SESSION['csrf_tokens'][$formId] = [
            'token' => $token,
            'time' => time()
        ];

        // Cleanup old tokens
        self::cleanupCSRFTokens();

        return $token;
    }

    /**
     * Verify CSRF token
     *
     * @param string $token
     * @param string $formId
     * @param int $maxAge Max age in seconds
     * @return bool
     */
    public static function verifyCSRFToken(string $token, string $formId = 'default', int $maxAge = 3600): bool
    {
        self::ensureSession();

        if (!isset($_SESSION['csrf_tokens'][$formId])) {
            return false;
        }

        $stored = $_SESSION['csrf_tokens'][$formId];

        // Check expiration
        if (time() - $stored['time'] > $maxAge) {
            unset($_SESSION['csrf_tokens'][$formId]);
            return false;
        }

        // Timing-safe comparison
        return hash_equals($stored['token'], $token);
    }

    /**
     * Cleanup expired CSRF tokens
     */
    private static function cleanupCSRFTokens(int $maxAge = 3600): void
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }

        $now = time();

        foreach ($_SESSION['csrf_tokens'] as $formId => $data) {
            if ($now - $data['time'] > $maxAge) {
                unset($_SESSION['csrf_tokens'][$formId]);
            }
        }
    }

}

// ===========================================
// Auto-cleanup on script shutdown
// ===========================================

register_shutdown_function(function() {
    // Cleanup expired CSRF tokens
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrf_tokens'])) {
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $formId => $data) {
            if ($now - $data['time'] > 3600) {
                unset($_SESSION['csrf_tokens'][$formId]);
            }
        }
    }
});

// ===========================================
// ✅ Security Helper Production Ready
// ===========================================