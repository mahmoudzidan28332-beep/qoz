<?php
declare(strict_types=1);

/**
 * BaseRepository
 *
 * Abstract foundation for all PDO-backed repository classes.
 *
 * SECURITY GOAL:
 *  Prevent cross-tenant data access by making it impossible to issue a
 *  tenant-scoped query without the tenant_id from TenantContext (the only
 *  authoritative, session-validated source).
 *
 * USAGE:
 *
 *   final class PdoProductsRepository extends BaseRepository
 *   {
 *       public function listForCurrentTenant(): array
 *       {
 *           $sql = 'SELECT * FROM products WHERE ' . $this->tenantCondition('p');
 *           $stmt = $this->pdo->prepare($sql);
 *           $stmt->execute($this->tenantParam());
 *           return $stmt->fetchAll(\PDO::FETCH_ASSOC);
 *       }
 *   }
 *
 * BACKWARD COMPATIBILITY:
 *  Existing repositories that do NOT yet extend BaseRepository continue to work
 *  unchanged.  Extend incrementally — there is no forced migration.
 *
 * NOTE: TenantContext must be `require`d (or autoloaded) before any repository
 * that extends BaseRepository is instantiated.
 */
abstract class BaseRepository
{
    /** @var \PDO The PDO database connection shared across all methods. */
    protected \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Tenant helpers
    // =========================================================================

    /**
     * Return the active tenant ID from TenantContext.
     *
     * Throws \RuntimeException if TenantContext has not been initialized,
     * which means the entry-point failed to call TenantContext::set().
     *
     * @throws \RuntimeException
     */
    protected function getTenantId(): int
    {
        return TenantContext::require();
    }

    /**
     * Assert that the tenant context has been initialised.
     *
     * Call this at the top of any repository method that MUST be tenant-scoped.
     * It surfaces a loud, descriptive error instead of a silent data leak.
     *
     *   public function findAll(): array
     *   {
     *       $this->assertTenantContext();
     *       // ... tenant-scoped query ...
     *   }
     *
     * @throws \RuntimeException  If TenantContext is not set.
     */
    protected function assertTenantContext(): void
    {
        TenantContext::require();
    }

    /**
     * Build the SQL fragment that scopes a query to the current tenant.
     *
     * Examples:
     *   $this->tenantCondition()      → "tenant_id = :tenant_id"
     *   $this->tenantCondition('p')   → "p.tenant_id = :tenant_id"
     *   $this->tenantCondition('p', 'tid')  → "p.tenant_id = :tid"
     *
     * @param  string $alias  Table alias without dot, e.g. 'p' for 'p.tenant_id'.
     *                        Pass '' for no alias.
     * @param  string $param  Named PDO parameter (without colon), default 'tenant_id'.
     * @return string         SQL fragment ready to embed in WHERE / AND clause.
     */
    protected function tenantCondition(string $alias = '', string $param = 'tenant_id'): string
    {
        $column = ($alias !== '') ? "{$alias}.tenant_id" : 'tenant_id';
        return "{$column} = :{$param}";
    }

    /**
     * Return a PDO parameter array for the tenant condition.
     *
     * Pair this with tenantCondition() to bind the value:
     *
     *   $stmt->execute(array_merge($otherParams, $this->tenantParam()));
     *
     * @param  string $param  Named PDO parameter (without colon), default 'tenant_id'.
     * @return array          e.g. [':tenant_id' => 5]
     *
     * @throws \RuntimeException  Propagates from TenantContext::getId() if unset.
     */
    protected function tenantParam(string $param = 'tenant_id'): array
    {
        return [':' . $param => $this->getTenantId()];
    }

    // =========================================================================
    // Central query execution interceptor
    // =========================================================================

    /**
     * Tables that are security-sensitive and require SELECT audit logging.
     *
     * READ operations on these tables are automatically captured in AuditContext
     * so that any access to personal, financial, or privilege data is traceable.
     *
     * @var string[]
     */
    private const SENSITIVE_AUDIT_TABLES = [
        'users',
        'user_roles',
        'roles',
        'permissions',
        'role_permissions',
        'audit_logs',
        'sessions',
        'password_reset_tokens',
        'addresses',
        'payment_methods',
        'orders',
        'invoices',
        'tenant_users',
    ];

    /**
     * Execute a tenant-scoped SQL query through the full security pipeline.
     *
     * ALL tenant-scoped queries MUST go through this method.  It:
     *   1. Calls QueryGuard::validate() to assert tenant isolation.
     *   2. Auto-injects the tenant_id parameter if it is missing from $params.
     *   3. Auto-appends a WHERE tenant_id = :tenant_id clause when the SQL
     *      contains no tenant_id condition (last-resort safety net).
     *   4. Prepares and executes the statement, returning the PDOStatement.
     *   5. Auto-audits INSERT/UPDATE/DELETE operations via AuditContext.
     *   6. Auto-audits SELECT operations on sensitive tables.
     *
     * IMPORTANT: If AuditContext is available and an audit write fails,
     * the exception propagates upward — the request is blocked (fail-fast).
     *
     * EXAMPLE:
     *
     *   $stmt = $this->execute(
     *       'SELECT * FROM products WHERE status = :status',
     *       [':status' => 'active'],
     *       'products'
     *   );
     *   $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
     *
     * @param  string $sql    SQL string (SELECT / INSERT / UPDATE / DELETE).
     * @param  array  $params PDO parameter bindings (':param' => value).
     * @param  string $table  Table name for QueryGuard whitelist check.
     *
     * @throws \RuntimeException  Via QueryGuard when isolation cannot be enforced.
     * @return \PDOStatement       Already-executed statement ready for fetch.
     */
    protected function execute(string $sql, array $params = [], string $table = ''): \PDOStatement
    {
        // Skip tenant enforcement for whitelisted global tables.
        $isGlobal = ($table !== '') && QueryGuard::isGlobal($table);

        if (!$isGlobal) {
            // Auto-inject WHERE tenant_id if neither SQL nor params carry it.
            [$sql, $params] = $this->ensureTenantScope($sql, $params);

            // Validate through QueryGuard (will throw if still missing).
            QueryGuard::validate($sql, $table);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Auto-audit the DML operation (or sensitive SELECT) via AuditContext.
        $this->autoAudit($sql, $table);

        return $stmt;
    }

    /**
     * Execute a global (non-tenant-scoped) query for whitelisted tables.
     *
     * Use ONLY for platform-level tables (audit_logs, system_settings, etc.).
     *
     * @param  string $sql    SQL string.
     * @param  array  $params PDO parameter bindings.
     * @param  string $table  Table name — MUST be in QueryGuard's global whitelist.
     *
     * @throws \RuntimeException  When $table is not whitelisted.
     * @return \PDOStatement       Already-executed statement ready for fetch.
     */
    protected function executeGlobal(string $sql, array $params = [], string $table = ''): \PDOStatement
    {
        if ($table !== '' && !QueryGuard::isGlobal($table)) {
            throw new \RuntimeException(
                "BaseRepository::executeGlobal() called for table '{$table}' which is not "
                . 'in the QueryGuard global whitelist. Use execute() with a tenant_id condition, '
                . 'or whitelist the table with QueryGuard::allowGlobal().'
            );
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Ensure the SQL and params carry a tenant_id scope.
     *
     * If the SQL already contains "tenant_id" the method is a no-op.
     * Otherwise it appends "AND tenant_id = :_auto_tenant_id" to an existing
     * WHERE clause, or adds a brand-new WHERE clause when none is present.
     *
     * This is the last-resort safety net — developers should always include
     * tenant conditions explicitly.  The auto-injection exists to prevent
     * accidental leaks, not to encourage lazy coding.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array{0: string, 1: array}  [$rewrittenSql, $enrichedParams]
     */
    private function ensureTenantScope(string $sql, array $params): array
    {
        if (str_contains(strtolower($sql), 'tenant_id')) {
            return [$sql, $params]; // Already scoped — nothing to do.
        }

        // Obtain the tenant ID (fail-fast if TenantContext is not set).
        $tenantId = TenantContext::require();

        $sqlLower = strtolower(trim($sql));
        $isSelect = str_starts_with($sqlLower, 'select');
        $isUpdate = str_starts_with($sqlLower, 'update');
        $isDelete = str_starts_with($sqlLower, 'delete');

        // Only auto-inject for DML that reads/modifies rows.
        if ($isSelect || $isUpdate || $isDelete) {
            if (str_contains($sqlLower, ' where ')) {
                // Append to existing WHERE.
                $sql .= ' AND tenant_id = :_auto_tenant_id';
            } else {
                // Add a new WHERE clause before ORDER / GROUP / LIMIT / semicolon.
                $sql = preg_replace(
                    '/\s+(ORDER\s+BY|GROUP\s+BY|LIMIT|HAVING|;)/i',
                    ' WHERE tenant_id = :_auto_tenant_id $1',
                    $sql,
                    1,
                    $count
                );
                if ($count === 0) {
                    $sql .= ' WHERE tenant_id = :_auto_tenant_id';
                }
            }

            $params[':_auto_tenant_id'] = $tenantId;
        }

        return [$sql, $params];
    }

    /**
     * Auto-audit a SQL operation when AuditContext is available.
     *
     * Audits:
     *  - INSERT, UPDATE, DELETE on any table (mandatory DML audit).
     *  - SELECT on tables listed in SENSITIVE_AUDIT_TABLES.
     *
     * If AuditContext is loaded and its capture() call throws, the exception
     * propagates (fail-fast / block request) — audit failure is never silent.
     *
     * @param  string $sql    The SQL string that was just executed.
     * @param  string $table  Table name hint (may be empty string).
     */
    private function autoAudit(string $sql, string $table): void
    {
        if (!class_exists('AuditContext', false)) {
            return;
        }

        $sqlLower = strtolower(ltrim($sql));

        if (str_starts_with($sqlLower, 'insert')) {
            $event = 'data_create';
        } elseif (str_starts_with($sqlLower, 'update')) {
            $event = 'data_update';
        } elseif (str_starts_with($sqlLower, 'delete')) {
            $event = 'data_delete';
        } elseif (str_starts_with($sqlLower, 'select')) {
            // Only audit SELECT on explicitly sensitive tables.
            if ($table === '' || !in_array(strtolower($table), self::SENSITIVE_AUDIT_TABLES, true)) {
                return;
            }
            $event = 'data_select';
        } else {
            return; // DDL or other statements — not audited here.
        }

        // AuditContext::capture() is allowed to throw; we let it propagate
        // so the request is blocked when audit infrastructure is unavailable.
        AuditContext::capture($event, $table ?: 'unknown', null, [
            'auto_audited' => true,
        ]);
    }

    // =========================================================================
    // QueryGuard integration (prepare-only helpers — use execute() where possible)
    // =========================================================================

    /**
     * Prepare a PDO statement after asserting that the SQL is tenant-scoped.
     *
     * Prefer execute() over this method when you can provide params immediately.
     * This helper exists for callers that need to separate prepare from execute.
     *
     * @param  string $sql    SQL string to validate and prepare.
     * @param  string $table  Optional table name for whitelist check.
     *
     * @throws \RuntimeException  Via QueryGuard::validate() when tenant isolation is missing.
     * @return \PDOStatement
     */
    protected function guardedQuery(string $sql, string $table = ''): \PDOStatement
    {
        QueryGuard::validate($sql, $table);
        return $this->pdo->prepare($sql);
    }

    /**
     * Prepare a PDO statement for a global (non-tenant-scoped) query.
     *
     * Use ONLY for tables listed in QueryGuard::getGlobalTables() — e.g. audit_logs,
     * system_settings, countries.  The call is itself audited when audit_log() is
     * available.
     *
     * @param  string $sql    SQL string for the global query.
     * @param  string $table  Table name (must be whitelisted in QueryGuard).
     *
     * @throws \RuntimeException  When $table is NOT in the global whitelist.
     * @return \PDOStatement
     */
    protected function globalQuery(string $sql, string $table): \PDOStatement
    {
        if (!QueryGuard::isGlobal($table)) {
            throw new \RuntimeException(
                "BaseRepository::globalQuery() called for table '{$table}' which is not "
                . 'in the QueryGuard global whitelist. Either add a tenant_id condition and '
                . 'use guardedQuery(), or whitelist the table with QueryGuard::allowGlobal().'
            );
        }
        return $this->pdo->prepare($sql);
    }

    // =========================================================================
    // Ownership helpers
    // =========================================================================

    /**
     * SQL fragment that restricts rows to those owned by the given user.
     *
     * Combine with tenantCondition() for full isolation:
     *
     *   WHERE {$this->tenantCondition('o')} AND {$this->ownerCondition('o')}
     *
     * @param  string $alias       Table alias, e.g. 'o'.
     * @param  string $ownerColumn Column name that stores the owner user ID.
     * @param  string $param       Named PDO parameter (without colon).
     * @return string
     */
    protected function ownerCondition(
        string $alias = '',
        string $ownerColumn = 'user_id',
        string $param = 'owner_user_id'
    ): string {
        $column = ($alias !== '') ? "{$alias}.{$ownerColumn}" : $ownerColumn;
        return "{$column} = :{$param}";
    }

    /**
     * Return a PDO parameter array for the owner condition.
     *
     * @param  int    $userId  The owner's user ID.
     * @param  string $param   Named PDO parameter (without colon).
     * @return array
     */
    protected function ownerParam(int $userId, string $param = 'owner_user_id'): array
    {
        return [':' . $param => $userId];
    }
}
