<?php
declare(strict_types=1);

/**
 * QueryGuard
 *
 * Runtime SQL safety layer that ensures every executed query either:
 *   (a) Contains a tenant_id condition, OR
 *   (b) Targets a table that is explicitly whitelisted as a global/platform table.
 *
 * USAGE (inside BaseRepository):
 *
 *   QueryGuard::validate($sql);                      // throws if tenant_id missing
 *   QueryGuard::validate($sql, 'audit_logs');         // passes — audit_logs is global
 *
 * EXTENDING THE WHITELIST:
 *
 *   QueryGuard::allowGlobal(['my_platform_config']);  // add extra tables at bootstrap
 *
 * FAIL-FAST CONTRACT:
 *   Any SQL that neither carries "tenant_id" nor belongs to a whitelisted global
 *   table will throw \RuntimeException immediately, preventing silent data leakage.
 */
final class QueryGuard
{
    /**
     * Platform-level tables that legitimately have no tenant_id scope.
     *
     * These are shared reference data or super-admin-only tables where every row
     * belongs to the platform, not to a specific tenant.
     *
     * @var string[]
     */
    private static array $globalTables = [
        'audit_logs',
        'system_settings',
        'permissions',
        'roles',
        'role_permissions',
        'user_roles',
        'users',                 // user identity is platform-level; tenant membership is separate
        'subscription_plans',
        'countries',
        'cities',
        'currencies',
        'languages',
        'image_types',
        'product_types',
        'attribute_types',
        'units',
        'categories',
        'brands',
        'tenant_categories',
        'tenant_brands',
        'sessions',
        'password_reset_tokens',
    ];

    private function __construct() {}

    // =========================================================================
    // Core API
    // =========================================================================

    /**
     * Assert that $sql contains a tenant_id condition.
     *
     * Pass an optional $table name; if that table is whitelisted as global the
     * check is skipped automatically.
     *
     * @param  string $sql    Raw SQL string to inspect.
     * @param  string $table  Optional table name for whitelist look-up.
     *
     * @throws \RuntimeException  When tenant isolation is missing and the table
     *                            is not in the global whitelist.
     */
    public static function validate(string $sql, string $table = ''): void
    {
        // Skip check for explicitly whitelisted global tables.
        if ($table !== '' && self::isGlobal($table)) {
            return;
        }

        if (!str_contains(strtolower($sql), 'tenant_id')) {
            throw new \RuntimeException(
                'QueryGuard: tenant isolation missing in SQL query'
                . ($table !== '' ? " for table '{$table}'" : '')
                . '. Add a tenant_id condition or whitelist the table with'
                . ' QueryGuard::allowGlobal([\'table_name\']).'
            );
        }
    }

    // =========================================================================
    // Whitelist management
    // =========================================================================

    /**
     * Register additional platform-level tables as global (no tenant_id required).
     *
     * Call this once at application bootstrap when a table is legitimately shared
     * across all tenants (e.g. a global lookup table you added after deployment).
     *
     * @param  string[] $tables  Table names to whitelist (case-insensitive).
     */
    public static function allowGlobal(array $tables): void
    {
        $normalised = array_map('strtolower', $tables);
        self::$globalTables = array_unique(array_merge(self::$globalTables, $normalised));
    }

    /**
     * Returns true when a table is registered as a platform-level global table.
     *
     * @param  string $table  Table name (case-insensitive).
     */
    public static function isGlobal(string $table): bool
    {
        return in_array(strtolower($table), self::$globalTables, true);
    }

    /**
     * Return the full list of currently whitelisted global tables.
     *
     * @return string[]
     */
    public static function getGlobalTables(): array
    {
        return self::$globalTables;
    }

    /**
     * Remove a table from the global whitelist.
     *
     * Useful in tests when you want to assert that a formerly global table now
     * requires tenant scoping.
     *
     * @param  string $table  Table name (case-insensitive).
     */
    public static function removeGlobal(string $table): void
    {
        $lower = strtolower($table);
        self::$globalTables = array_values(
            array_filter(self::$globalTables, static fn(string $t): bool => $t !== $lower)
        );
    }
}
