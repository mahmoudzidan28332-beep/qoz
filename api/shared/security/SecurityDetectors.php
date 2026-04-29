<?php

declare(strict_types=1);

// ════════════════════════════════════════════════════════════════════════════════
// SQL Injection Detector
// ════════════════════════════════════════════════════════════════════════════════

final class SqlInjectionDetector
{
    /** Patterns ordered from most specific (lowest FP) to more general. */
    private static array $patterns = [
        // Tautologies
        '/\b(?:OR|AND)\s+[\'"0-9][^\s]*\s*=\s*[\'"0-9]/i',
        '/\b1\s*=\s*1\b|\btrue\s*=\s*true\b/i',

        // UNION SELECT attacks
        '/\bUNION\b[\s\S]{0,100}\bSELECT\b/i',

        // Comment terminators
        '/--[\s\S]|#[\s\S]/i',
        '/\/\*[\s\S]*?\*\//i',

        // DML statements in input (not config)
        '/\b(?:DROP|TRUNCATE|ALTER)\s+(?:TABLE|DATABASE|SCHEMA)\b/i',
        '/\bINSERT\s+INTO\b[\s\S]{0,100}\bVALUES\b/i',
        '/\bDELETE\s+FROM\b/i',
        '/\bUPDATE\b[\s\S]{0,100}\bSET\b/i',

        // Dangerous functions
        '/\b(?:SLEEP|BENCHMARK|WAITFOR\s+DELAY|PG_SLEEP)\s*\(/i',
        '/\b(?:LOAD_FILE|INTO\s+OUTFILE|INTO\s+DUMPFILE)\b/i',
        '/\b(?:INFORMATION_SCHEMA|SYSOBJECTS|SYSCOLUMNS|SYS\.TABLES)\b/i',
        '/\b(?:CHAR|NCHAR|VARCHAR)\s*\(\s*\d+\s*\)/i',
        '/\bCONCAT\s*\([^)]*(?:CHAR|0x[0-9a-f]+)/i',
        '/\bEXTRACTVALUE\s*\(/i',
        '/\bXMLTYPE\s*\(/i',
        '/\bHEX\s*\(|0x[0-9a-f]{4,}/i',

        // Stacked queries
        '/;\s*(?:SELECT|INSERT|UPDATE|DELETE|DROP|EXEC)\b/i',

        // Boolean-based blind
        '/\bIF\s*\(\s*\d+\s*=\s*\d+\s*,\s*[\'"]/i',
        '/\bCASE\s+WHEN\b[\s\S]{0,100}\bTHEN\b/i',

        // NoSQL injection style
        '/\$(?:ne|eq|gt|lt|gte|lte|in|nin|or|and|not|nor|exists|type|mod|regex)\b/i',
        '/\{\s*["\']\s*\$(?:where|regex|in|ne)\s*["\']/i',
    ];

    /** Inputs considered safe even if matched (e.g. encoded URL in a whitelist). */
    private static array $whitelist = [];

    /**
     * Analyse a value (string or array) for SQL injection patterns.
     *
     * @return array{detected: bool, pattern: string, value: string}
     */
    public static function analyse(mixed $value, string $fieldName = ''): array
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $result = self::analyse($v, $fieldName . '[' . $k . ']');
                if ($result['detected']) {
                    return $result;
                }
            }
            return ['detected' => false, 'pattern' => '', 'value' => ''];
        }

        if (!is_string($value)) {
            return ['detected' => false, 'pattern' => '', 'value' => ''];
        }

        // Decode URL encoding before analysis
        $decoded = urldecode($value);

        foreach (self::$patterns as $pattern) {
            if (preg_match($pattern, $decoded)) {
                return [
                    'detected' => true,
                    'pattern'  => $pattern,
                    'value'    => substr($decoded, 0, 200),
                ];
            }
        }

        return ['detected' => false, 'pattern' => '', 'value' => ''];
    }

    /**
     * Scan all inputs ($GET, $POST, JSON body) and return first detected hit.
     *
     * @return array{detected: bool, field: string, pattern: string, value: string}
     */
    public static function scanRequest(array $get, array $post, mixed $json): array
    {
        foreach (['GET' => $get, 'POST' => $post] as $source => $data) {
            foreach ($data as $field => $value) {
                $result = self::analyse($value, "{$source}[{$field}]");
                if ($result['detected']) {
                    return array_merge($result, ['field' => "{$source}[{$field}]"]);
                }
            }
        }

        if (is_array($json)) {
            foreach ($json as $field => $value) {
                $result = self::analyse($value, "JSON[{$field}]");
                if ($result['detected']) {
                    return array_merge($result, ['field' => "JSON[{$field}]"]);
                }
            }
        }

        return ['detected' => false, 'field' => '', 'pattern' => '', 'value' => ''];
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// XSS Detector / Sanitizer
// ════════════════════════════════════════════════════════════════════════════════

final class XssDetector
{
    private static array $patterns = [
        // Script tags
        '/<\s*script[^>]*>/i',
        '/<\s*\/\s*script\s*>/i',

        // Event handlers
        '/\bon(?:load|error|click|mouse\w+|key\w+|focus|blur|change|submit|reset|select|copy|cut|paste|drag\w*|drop|scroll|resize|unload|beforeunload|hash\w*|message|online|offline|storage|popstate|pagehide|pageshow)\s*=/i',

        // javascript: protocol
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:\s*(?:text\/html|application\/x-javascript)/i',

        // XSS via HTML attributes
        '/<\s*(?:img|iframe|embed|object|form|input|button|select|textarea|meta|link|base|applet)\b[^>]*(?:src|href|action|formaction|data|srcdoc)\s*=/i',

        // SVG attack vectors
        '/<\s*svg\b[^>]*>/i',
        '/<\s*(?:animate|set|feImage|use)\b/i',

        // CSS expressions
        '/expression\s*\(/i',
        '/-moz-binding\s*:/i',

        // Unicode / encoding tricks
        '/&#(?:x[0-9a-f]+|[0-9]+)\s*;/i',
    ];

    /**
     * True if the value contains XSS patterns.
     */
    public static function isXss(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Double-decode before checking
        $decoded = html_entity_decode(urldecode($value), ENT_QUOTES, 'UTF-8');

        foreach (self::$patterns as $pattern) {
            if (preg_match($pattern, $decoded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a value by HTML-encoding all special characters.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }

        return $value;
    }

    /**
     * Recursively scan input array for XSS.
     *
     * @return array{detected: bool, field: string, value: string}
     */
    public static function scanInputs(array $inputs, string $source = ''): array
    {
        foreach ($inputs as $field => $value) {
            if (is_array($value)) {
                $result = self::scanInputs($value, "{$source}[{$field}]");
                if ($result['detected']) {
                    return $result;
                }
            } elseif (self::isXss((string) $value)) {
                return [
                    'detected' => true,
                    'field'    => "{$source}[{$field}]",
                    'value'    => substr((string) $value, 0, 200),
                ];
            }
        }

        return ['detected' => false, 'field' => '', 'value' => ''];
    }
}
