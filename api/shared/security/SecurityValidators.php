<?php

declare(strict_types=1);

// ════════════════════════════════════════════════════════════════════════════════
// Input Validator & Sanitizer
// ════════════════════════════════════════════════════════════════════════════════

final class InputValidator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    /**
     * Validate a field value against a set of rules.
     *
     * Rule format:  'required|string|min:3|max:100|email|numeric|in:a,b,c|regex:/^[a-z]+$/'
     *
     * @param mixed $value
     * @return bool  True if valid
     */
    public function validate(string $field, mixed $value, string $rules): bool
    {
        $ruleList = explode('|', $rules);
        $valid    = true;

        foreach ($ruleList as $rule) {
            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, '');

            $passed = match ($ruleName) {
                'required' => $value !== null && $value !== '' && $value !== [],
                'string'   => is_string($value),
                'numeric'  => is_numeric($value),
                'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false,
                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'url'      => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'boolean'  => is_bool($value) || in_array($value, ['true', 'false', '0', '1', 0, 1], true),
                'array'    => is_array($value),
                'nullable' => true,  // always passes — just marks field as nullable
                'min'      => is_string($value) ? mb_strlen($value) >= (int) $ruleParam
                                                 : (is_numeric($value) && $value >= (float) $ruleParam),
                'max'      => is_string($value) ? mb_strlen($value) <= (int) $ruleParam
                                                 : (is_numeric($value) && $value <= (float) $ruleParam),
                'in'       => in_array($value, explode(',', $ruleParam), false),
                'not_in'   => !in_array($value, explode(',', $ruleParam), false),
                'regex'    => is_string($value) && preg_match($ruleParam, $value) === 1,
                'uuid'     => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value),
                'alpha'    => is_string($value) && ctype_alpha($value),
                'alphanum' => is_string($value) && ctype_alnum($value),
                'date'     => is_string($value) && strtotime($value) !== false,
                'ip'       => filter_var($value, FILTER_VALIDATE_IP) !== false,
                default    => true,
            };

            if (!$passed) {
                $this->errors[$field][] = $this->message($ruleName, $field, $ruleParam);
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Validate multiple fields at once.
     *
     * @param array<string, mixed>  $data   Input data
     * @param array<string, string> $rules  [field => 'rule|rule|rule']
     */
    public function validateAll(array $data, array $rules): bool
    {
        $this->errors = [];
        $valid = true;

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            if (!$this->validate($field, $value, $fieldRules)) {
                $valid = false;
            }
        }

        return $valid;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    protected function firstError(): string
    {
        foreach ($this->errors as $field => $messages) {
            return "{$field}: " . $messages[0];
        }
        return '';
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function message(string $rule, string $field, string $param): string
    {
        return match ($rule) {
            'required' => "{$field} is required",
            'string'   => "{$field} must be a string",
            'numeric'  => "{$field} must be numeric",
            'integer'  => "{$field} must be an integer",
            'email'    => "{$field} must be a valid email address",
            'url'      => "{$field} must be a valid URL",
            'min'      => "{$field} must be at least {$param}",
            'max'      => "{$field} must not exceed {$param}",
            'in'       => "{$field} must be one of: {$param}",
            'not_in'   => "{$field} must not be one of: {$param}",
            'regex'    => "{$field} format is invalid",
            'uuid'     => "{$field} must be a valid UUID",
            'alpha'    => "{$field} must contain only letters",
            'alphanum' => "{$field} must contain only letters and numbers",
            'date'     => "{$field} must be a valid date",
            'ip'       => "{$field} must be a valid IP address",
            default    => "{$field} is invalid",
        };
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// JWT Validator
// ════════════════════════════════════════════════════════════════════════════════

final class JwtValidator
{
    /**
     * Validate a JWT token and return its claims.
     *
     * @return array{valid: bool, claims: array<string, mixed>, error: string}
     */
    public static function validate(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'claims' => [], 'error' => 'Malformed token'];
        }

        [$headerB64, $payloadB64, $signature] = $parts;

        // Decode header
        $header = json_decode(
            (string) base64_decode(strtr($headerB64, '-_', '+/') . str_repeat('=', (4 - strlen($headerB64) % 4) % 4)),
            true
        );

        if (!is_array($header)) {
            return ['valid' => false, 'claims' => [], 'error' => 'Cannot decode JWT header'];
        }

        // Algorithm check
        $alg = $header['alg'] ?? null;
        if (!$alg) {
            return ['valid' => false, 'claims' => [], 'error' => 'JWT algorithm (alg) header is missing'];
        }

        if (!in_array($alg, SecurityConfig::$allowedJwtAlgs, true)) {
            return ['valid' => false, 'claims' => [], 'error' => "Algorithm not allowed"];
        }

        // Decode payload
        $claims = json_decode(
            (string) base64_decode(strtr($payloadB64, '-_', '+/') . str_repeat('=', (4 - strlen($payloadB64) % 4) % 4)),
            true
        );

        if (!is_array($claims)) {
            return ['valid' => false, 'claims' => [], 'error' => 'Cannot decode JWT payload'];
        }

        // Expiry check
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return ['valid' => false, 'claims' => $claims, 'error' => 'Token expired'];
        }

        // Not-before check
        if (isset($claims['nbf']) && $claims['nbf'] > time()) {
            return ['valid' => false, 'claims' => $claims, 'error' => 'Token not yet valid'];
        }

        // Signature verification (HMAC)
        if (in_array($alg, ['HS256', 'HS384', 'HS512'], true)) {
            if (empty($secret)) {
                return ['valid' => false, 'claims' => $claims, 'error' => 'Server misconfiguration: JWT secret is missing'];
            }
            $hashAlg = str_replace('HS', 'sha', $alg);
            $expected = rtrim(strtr(base64_encode(hash_hmac($hashAlg, "{$headerB64}.{$payloadB64}", $secret, true)), '+/', '-_'), '=');
            if (!hash_equals($expected, $signature)) {
                return ['valid' => false, 'claims' => $claims, 'error' => 'Invalid signature'];
            }
        } 
        // RS256 verification (if enabled in config)
        elseif ($alg === 'RS256') {
            // Note: This would require a public key. Since it's not currently implemented,
            // we return valid false to prevent bypass.
            return ['valid' => false, 'claims' => $claims, 'error' => 'RS256 verification not implemented'];
        }
        else {
            // Safety fallback: if algorithm is in allowed list but not handled here, it's a security risk
            return ['valid' => false, 'claims' => $claims, 'error' => 'Unsupported algorithm signature verification'];
        }

        return ['valid' => true, 'claims' => $claims, 'error' => ''];
    }

    /**
     * Extract token from Authorization header.
     */
    public static function fromHeader(): string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// Multi-Tenant Validator
// ════════════════════════════════════════════════════════════════════════════════

final class MultiTenantValidator
{
    /**
     * Verify that a record in a table belongs to the given tenant.
     * 
     * @param PDO    $pdo      Database connection
     * @param string $table    Table name
     * @param int    $id       Record ID
     * @param int    $tenantId Tenant ID
     * @param string $idCol    Primary key column name
     * @param string $tCol     Tenant foreign key column name
     * @return bool
     */
    public static function checkOwnership(
        PDO $pdo,
        string $table,
        int $id,
        int $tenantId,
        string $idCol = 'id',
        string $tCol = 'tenant_id'
    ): bool {
        // 🔒 SECURITY: Use literal SQL templates to satisfy security scanners.
        // Even with whitelisting, dynamic SQL in prepare() is often flagged.
        
        $allowedTables = ['entities', 'products', 'categories', 'orders', 'users', 'addresses', 'flash_sales', 'roles', 'permissions', 'product_bundles', 'discounts'];
        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        // We use a switch to provide literal query strings.
        // This is the most scanner-safe way to handle dynamic tables in PDO.
        switch ($table) {
            case 'entities':    $stmt = $pdo->prepare("SELECT 1 FROM `entities` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'products':    $stmt = $pdo->prepare("SELECT 1 FROM `products` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'categories':  $stmt = $pdo->prepare("SELECT 1 FROM `categories` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'orders':      $stmt = $pdo->prepare("SELECT 1 FROM `orders` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'users':       $stmt = $pdo->prepare("SELECT 1 FROM `users` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'addresses':   $stmt = $pdo->prepare("SELECT 1 FROM `addresses` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'flash_sales': $stmt = $pdo->prepare("SELECT 1 FROM `flash_sales` WHERE `id` = ? AND `entity_id` IN (SELECT id FROM entities WHERE tenant_id = ?) LIMIT 1"); break;
            case 'roles':       $stmt = $pdo->prepare("SELECT 1 FROM `roles` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'permissions': $stmt = $pdo->prepare("SELECT 1 FROM `permissions` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1"); break;
            case 'product_bundles': $stmt = $pdo->prepare("SELECT 1 FROM `product_bundles` b JOIN `entities` e ON e.id = b.entity_id WHERE b.`id` = ? AND e.`tenant_id` = ? LIMIT 1"); break;
            case 'discounts':       $stmt = $pdo->prepare("SELECT 1 FROM `discounts` d JOIN `entities` e ON e.id = d.entity_id WHERE d.`id` = ? AND e.`tenant_id` = ? LIMIT 1"); break;
            default: return false;
        }

        $stmt->execute([$id, $tenantId]);
        return (bool)$stmt->fetchColumn();
    }


    /**
     * filterInput
     * Common utility to prevent Mass Assignment (CWE-915).
     */
    public static function filterInput(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }
}


/**
 * RateLimitValidator
 * Provides DB-backed rate limiting.
 */
final class RateLimitValidator
{
    /**
     * Check rate limit for a given key.
     * Returns true if allowed, false if blocked.
     */
    public static function checkLimit(PDO $pdo, string $key, int $limit = 5, int $windowSeconds = 3600): bool
    {
        try {
            // Ensure table exists (Simplified for portable remediation)
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                `key_hash` VARCHAR(64) PRIMARY KEY,
                `hits` INT NOT NULL DEFAULT 1,
                `reset_at` INT NOT NULL
            )");

            $now = time();
            $hash = hash('sha256', $key);

            // 1. Prune expired
            $pdo->prepare("DELETE FROM rate_limits WHERE reset_at < ?")->execute([$now]);

            // 2. Get current
            $stmt = $pdo->prepare("SELECT hits, reset_at FROM rate_limits WHERE key_hash = ?");
            $stmt->execute([$hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                // First hit in this window
                $stmt = $pdo->prepare("INSERT INTO rate_limits (key_hash, hits, reset_at) VALUES (?, 1, ?)");
                $stmt->execute([$hash, $now + $windowSeconds]);
                return true;
            }

            if ($row['hits'] >= $limit) {
                return false;
            }

            // Increment
            $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE key_hash = ?")->execute([$hash]);
            return true;
        } catch (Throwable $e) {
            error_log('[RateLimitValidator] Error: ' . $e->getMessage());
            return true; // Fail open to not block users on DB issues
        }
    }
}



