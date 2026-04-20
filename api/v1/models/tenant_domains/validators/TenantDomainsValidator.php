<?php
declare(strict_types=1);

/**
 * TenantDomainsValidator
 *
 * Input validation for tenant domain records.
 */
final class TenantDomainsValidator
{
    private const ALLOWED_TYPES        = ['primary', 'custom', 'subdomain', 'alias'];
    private const ALLOWED_SSL_STATUSES = ['none', 'pending', 'active', 'failed'];
    private const MAX_DOMAIN_LENGTH    = 255;

    /**
     * Validate create payload
     */
    public static function validateCreate(array $data): array
    {
        $errors = [];

        // tenant_id
        if (
            empty($data['tenant_id']) ||
            !is_numeric($data['tenant_id']) ||
            (int)$data['tenant_id'] < 1
        ) {
            $errors['tenant_id'] = 'tenant_id is required and must be a positive integer';
        }

        // domain
        $domainError = self::validateDomainString($data['domain'] ?? '');
        if ($domainError !== '') {
            $errors['domain'] = $domainError;
        }

        // type
        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors['type'] = 'type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
        }

        // ssl_status
        if (isset($data['ssl_status']) && !in_array($data['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $errors['ssl_status'] = 'ssl_status must be one of: ' . implode(', ', self::ALLOWED_SSL_STATUSES);
        }

        return $errors;
    }

    /**
     * Validate update payload
     */
    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['domain'])) {
            $domainError = self::validateDomainString($data['domain']);
            if ($domainError !== '') {
                $errors['domain'] = $domainError;
            }
        }

        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors['type'] = 'type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
        }

        if (isset($data['ssl_status']) && !in_array($data['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $errors['ssl_status'] = 'ssl_status must be one of: ' . implode(', ', self::ALLOWED_SSL_STATUSES);
        }

        return $errors;
    }

    /**
     * Validate domain string (robust)
     */
    public static function validateDomainString(string $domain): string
    {
        $domain = trim($domain);

        if ($domain === '') {
            return 'domain is required';
        }

        if (mb_strlen($domain) > self::MAX_DOMAIN_LENGTH) {
            return 'domain must not exceed ' . self::MAX_DOMAIN_LENGTH . ' characters';
        }

        // =========================
        // Normalize input
        // =========================

        // إذا المستخدم أدخل URL كامل
        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            if (!empty($parsed['host'])) {
                $domain = $parsed['host'];
            } else {
                return 'invalid domain format';
            }
        }

        // إزالة port أو path
        $domain = preg_replace('/[:\/].*/', '', $domain);

        // lowercase
        $domain = strtolower($domain);

        // =========================
        // Validation
        // =========================

        // دعم wildcard مثل *.example.com
        $pattern = '/^(\*\.)?([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

        if (!preg_match($pattern, $domain)) {
            return 'domain must be a valid hostname (e.g. example.com, sub.example.co.uk)';
        }

        return '';
    }
}