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
 * CWE-284 / CVSS 6.5 FIXES in this version:
 *
 *  1. execute() — $table parameter is now REQUIRED (no default '').
 *     An empty $table previously let QueryGuard::validate() run without a
 *     whitelist check, silently allowing queries on tenant tables to bypass
 *     tenant isolation enforcement.
 *
 *  2. guardedQuery() — REMOVED from the public/protected API.
 *     It prepared a statement without running ensureTenantScope() or
 *     autoAudit(), creating an audit-invisible, scope-unchecked path.
 *     Callers that needed separate prepare/execute for LIMIT/OFFSET now use
 *     executePaginated() which runs the full pipeline then binds pagination.
 *
 *  3. executePaginated() — NEW method that handles LIMIT/OFFSET via
 *     bindValue(PARAM_INT) while still routing through the full security
 *     pipeline (QueryGuard → ensureTenantScope → autoAudit).
 *
 *  4. ensureTenantScope() — unchanged safety net; auto-injects tenant_id
 *     when the developer forgets. Still a last resort, not a crutch.
 *
 *  5. globalQuery() — REMOVED from the protected API.
 *     Replaced by executeGlobal() which was already present and correct.
 *
 * USAGE:
 *
 *   final class PdoProductsRepository extends BaseRepository
 *   {
 *       public function listForCurrentTenant(): array
 *       {
 *           $this->assertTenantContext();
 *           $sql = 'SELECT * FROM products WHERE tenant_id = :tenant_id';
 *           return $this->execute($sql, $this->tenantParam(), 'products')
 *                       ->fetchAll(PDO::FETCH_ASSOC);
 *       }
 *
 *       public function paginated(int $limit, int $offset): array
 *       {
 *           $this->assertTenantContext();
 *           $sql = 'SELECT * FROM products WHERE tenant_id = :tenant_id
 *                   ORDER BY id DESC LIMIT :limit OFFSET :offset';
 *           return $this->executePaginated(
 *               $sql, $this->tenantParam(), 'products', $limit, $offset
 *           )->fetchAll(PDO::FETCH_ASSOC);
 *       }
 *   }
 */
abstract class BaseRepository
{
    /** @var \PDO */
    protected \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Exception helpers
    // =========================================================================

    /**
     * Wrap a PDOException in a DatabaseException with structured context.
     *
     * Centralises exception construction so subclasses never import or call
     * the global ExceptionFactory directly.
     *
     * @param \PDOException $e       The original PDO error.
     * @param array         $context Diagnostic context (table, sqlstate, …).
     * @param string        $message Human-readable summary.
     */
    protected function databaseException(
        \PDOException $e,
        array $context = [],
        string $message = 'Database error'
    ): DatabaseException {
        return new DatabaseException($message, $context, $e);
    }

    // =========================================================================
    // Tenant helpers
    // =========================================================================

    /**
     * Return the active tenant ID from TenantContext.
     * Throws if TenantContext has not been initialised.
     *
     * @throws \RuntimeException
     */
    protected function getTenantId(): int
    {
        return TenantContext::require();
    }

    /**
     * Assert that TenantContext has been initialised.
     * Call at the top of every repository method that must be tenant-scoped.
     *
     * @throws \RuntimeException
     */
    protected function assertTenantContext(): void
    {
        TenantContext::require();
    }

    /**
     * Build the SQL fragment that scopes a query to the current tenant.
     *
     *   $this->tenantCondition()       → "tenant_id = :tenant_id"
     *   $this->tenantCondition('p')    → "p.tenant_id = :tenant_id"
     *   $this->tenantCondition('p','t')→ "p.tenant_id = :t"
     */
    protected function tenantCondition(string $alias = '', string $param = 'tenant_id'): string
    {
        $column = ($alias !== '') ? "{$alias}.tenant_id" : 'tenant_id';
        return "{$column} = :{$param}";
    }

    /**
     * Return a PDO parameter array for the tenant condition.
     *
     *   $stmt->execute(array_merge($other, $this->tenantParam()));
     *
     * @throws \RuntimeException  Propagates from TenantContext if unset.
     */
    protected function tenantParam(string $param = 'tenant_id'): array
    {
        return [':' . $param => $this->getTenantId()];
    }

    // =========================================================================
    // Sensitive tables — SELECT audit
    // =========================================================================

    /** @var string[] */
    private const SENSITIVE_AUDIT_TABLES = [
        'users', 'user_roles', 'roles', 'permissions', 'role_permissions',
        'audit_logs', 'sessions', 'password_reset_tokens', 'addresses',
        'payment_methods', 'orders', 'invoices', 'tenant_users', 'products',
    ];

    // =========================================================================
    // Central query execution — ALL tenant-scoped queries MUST use these
    // =========================================================================

    /**
     * Execute a tenant-scoped SQL query through the full security pipeline.
     *
     * Pipeline:
     *   1. ensureTenantScope()    — auto-inject tenant_id if missing (last resort).
     *   2. QueryGuard::validate() — reject SQL still missing tenant_id.
     *   3. PDO prepare + execute.
     *   4. autoAudit()            — capture DML and sensitive SELECTs.
     *
     * $table is REQUIRED. An empty string disables QueryGuard and is rejected.
     *
     * @param  string $sql    SQL string.
     * @param  array  $params PDO parameter bindings.
     * @param  string $table  Table name for QueryGuard whitelist check. REQUIRED.
     *
     * @throws \InvalidArgumentException  When $table is empty.
     * @throws \RuntimeException          Via QueryGuard when isolation cannot be enforced.
     * @return \PDOStatement               Already-executed; ready for fetch.
     */
    protected function execute(string $sql, array $params = [], string $table): \PDOStatement
    {
        if ($table === '') {
            throw new \InvalidArgumentException(
                'BaseRepository::execute() requires a non-empty $table name. '
                . 'Pass the primary table being queried so QueryGuard can enforce '
                . 'the whitelist and tenant isolation checks.'
            );
        }

        $isGlobal = QueryGuard::isGlobal($table);

        if (!$isGlobal) {
            [$sql, $params] = $this->ensureTenantScope($sql, $params);
            QueryGuard::validate($sql, $table);
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (class_exists('Logger', false)) {
                Logger::error('DatabaseException in execute()', [
                    'table'     => $table,
                    'sql'       => $sql,
                    'error'     => $e->getMessage(),
                    'sqlstate'  => $e->getCode(),
                ]);
            }
            throw $this->databaseException(
                $e,
                ['table' => $table, 'sqlstate' => $e->getCode()],
                'Database query failed'
            );
        }

        $this->autoAudit($sql, $table);

        return $stmt;
    }

    /**
     * Execute a tenant-scoped query that needs LIMIT / OFFSET bound as PARAM_INT.
     *
     * Runs the identical security pipeline as execute(), then rebinds pagination
     * params with \PDO::PARAM_INT before calling execute() on the statement.
     * This is the ONLY approved way to handle paginated queries — it replaces
     * the removed guardedQuery() which bypassed autoAudit() and ensureTenantScope().
     *
     * $params must NOT include ':limit' or ':offset' — those are added here.
     *
     * EXAMPLE:
     *
     *   $sql = 'SELECT * FROM products WHERE tenant_id = :tenant_id
     *           ORDER BY id DESC LIMIT :limit OFFSET :offset';
     *
     *   $stmt = $this->executePaginated(
     *       $sql, $this->tenantParam(), 'products', $limit, $offset
     *   );
     *   return $stmt->fetchAll(PDO::FETCH_ASSOC);
     *
     * @param  string   $sql    SQL string containing :limit and :offset placeholders.
     * @param  array    $params PDO bindings WITHOUT :limit / :offset.
     * @param  string   $table  Table name for QueryGuard. REQUIRED.
     * @param  int|null $limit  Rows per page; null omits the LIMIT clause.
     * @param  int|null $offset Row offset; null omits the OFFSET clause.
     *
     * @throws \InvalidArgumentException  When $table is empty.
     * @throws \RuntimeException          Via QueryGuard.
     * @return \PDOStatement               Already-executed; ready for fetch.
     */
    protected function executePaginated(
        string $sql,
        array  $params,
        string $table,
        ?int   $limit  = null,
        ?int   $offset = null
    ): \PDOStatement {
        if ($table === '') {
            throw new \InvalidArgumentException(
                'BaseRepository::executePaginated() requires a non-empty $table name.'
            );
        }

        $isGlobal = QueryGuard::isGlobal($table);

        if (!$isGlobal) {
            [$sql, $params] = $this->ensureTenantScope($sql, $params);
            QueryGuard::validate($sql, $table);
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind non-pagination params.
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }

            // Bind pagination as PARAM_INT — required for MySQL LIMIT/OFFSET.
            if ($limit  !== null) $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
            if ($offset !== null) $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();
        } catch (\PDOException $e) {
            if (class_exists('Logger', false)) {
                Logger::error('DatabaseException in executePaginated()', [
                    'table'    => $table,
                    'sql'      => $sql,
                    'error'    => $e->getMessage(),
                    'sqlstate' => $e->getCode(),
                ]);
            }
            throw $this->databaseException(
                $e,
                ['table' => $table, 'sqlstate' => $e->getCode()],
                'Database query failed'
            );
        }

        $this->autoAudit($sql, $table);

        return $stmt;
    }

    /**
     * Execute a global (non-tenant-scoped) query for whitelisted tables.
     *
     * Use ONLY for platform-level tables (audit_logs, system_settings, etc.)
     * that are listed in QueryGuard's global whitelist.
     *
     * @throws \RuntimeException  When $table is not whitelisted as global.
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

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (class_exists('Logger', false)) {
                Logger::error('DatabaseException in executeGlobal()', [
                    'table'    => $table,
                    'sql'      => $sql,
                    'error'    => $e->getMessage(),
                    'sqlstate' => $e->getCode(),
                ]);
            }
            throw $this->databaseException(
                $e,
                ['table' => $table, 'sqlstate' => $e->getCode()],
                'Database query failed'
            );
        }
        return $stmt;
    }

    // =========================================================================
    // Tenant scope enforcement (internal)
    // =========================================================================

    /**
     * Ensure the SQL and params carry a tenant_id scope.
     *
     * No-op when SQL already contains "tenant_id".
     * Otherwise appends "AND tenant_id = :_auto_tenant_id" to an existing WHERE,
     * or inserts a brand-new WHERE clause before ORDER/GROUP/LIMIT.
     *
     * This is the LAST-RESORT safety net — always include tenant conditions
     * explicitly using tenantCondition() + tenantParam().
     *
     * @return array{0: string, 1: array}
     */
    private function ensureTenantScope(string $sql, array $params): array
    {
        if (str_contains(strtolower($sql), 'tenant_id')) {
            return [$sql, $params];
        }

        $tenantId = TenantContext::require();
        $sqlLower = strtolower(trim($sql));
        $isDml    = str_starts_with($sqlLower, 'select')
                 || str_starts_with($sqlLower, 'update')
                 || str_starts_with($sqlLower, 'delete');

        if ($isDml) {
            if (str_contains($sqlLower, ' where ')) {
                $sql .= ' AND tenant_id = :_auto_tenant_id';
            } else {
                $sql = preg_replace(
                    '/\s+(ORDER\s+BY|GROUP\s+BY|LIMIT|HAVING|;)/i',
                    ' WHERE tenant_id = :_auto_tenant_id $1',
                    $sql, 1, $count
                );
                if ($count === 0) {
                    $sql .= ' WHERE tenant_id = :_auto_tenant_id';
                }
            }
            $params[':_auto_tenant_id'] = $tenantId;
        }

        return [$sql, $params];
    }

    // =========================================================================
    // Audit (internal)
    // =========================================================================

    /**
     * Auto-audit a SQL operation when AuditContext is available.
     *
     * Audits INSERT/UPDATE/DELETE on any table, and SELECT on SENSITIVE_AUDIT_TABLES.
     * If AuditContext::capture() throws, the exception propagates (fail-fast).
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
            if ($table === '' || !in_array(strtolower($table), self::SENSITIVE_AUDIT_TABLES, true)) {
                return;
            }
            $event = 'data_select';
        } else {
            return;
        }

        AuditContext::capture($event, $table ?: 'unknown', null, ['auto_audited' => true]);
    }

    // =========================================================================
    // Cross-tenant execution (Platform Admin only)
    // =========================================================================

    /**
     * Execute a cross-tenant SQL query for Platform Admin support operations.
     *
     * INTENTIONALLY bypasses TenantContext so super-admins can access any tenant,
     * but enforces ALL other security controls:
     *
     *   ✔  PlatformContext::isSuperAdmin()           — identity check.
     *   ✔  AuditContext::isBooted()                  — audit must be active.
     *   ✔  Non-empty $reason                          — every action must be justified.
     *   ✔  $targetTenantId > 0                        — valid tenant required.
     *   ✔  QueryGuard::validate()                     — SQL must contain tenant_id.
     *   ✔  PlatformContext::logCrossTenantAction()    — always logged.
     *   ✔  AuditContext::capturePlatformAdminAction() — rich audit entry.
     *
     * The caller MUST include an explicit tenant_id condition in $sql and bind
     * ':tenant_id' => $targetTenantId in $params.
     *
     * @throws \RuntimeException          When actor is not super-admin or audit unbooted.
     * @throws \InvalidArgumentException  When $reason is empty or $targetTenantId <= 0.
     */
    protected function executeCrossTenant(
        string $sql,
        array  $params,
        string $table,
        int    $targetTenantId,
        string $reason
    ): \PDOStatement {
        if (!class_exists('PlatformContext', false) || !PlatformContext::isSuperAdmin()) {
            throw new \RuntimeException(
                'BaseRepository::executeCrossTenant() may only be called in Platform Admin '
                . 'context. Call PlatformContext::bootSuperAdmin() at the entry-point first.'
            );
        }

        if (!class_exists('AuditContext', false) || !AuditContext::isBooted()) {
            throw new \RuntimeException(
                'BaseRepository::executeCrossTenant() requires AuditContext to be booted. '
                . 'Call AuditContext::boot() at the API entry-point.'
            );
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException(
                'BaseRepository::executeCrossTenant() requires a non-empty reason. '
                . 'Every Platform Admin cross-tenant action MUST have a documented justification.'
            );
        }

        if ($targetTenantId <= 0) {
            throw new \InvalidArgumentException(
                'BaseRepository::executeCrossTenant() requires a positive target_tenant_id; '
                . $targetTenantId . ' given.'
            );
        }

        $isGlobal = ($table !== '') && QueryGuard::isGlobal($table);
        if (!$isGlobal) {
            QueryGuard::validate($sql, $table);
        }

        PlatformContext::logCrossTenantAction(
            sourceTenant: null,
            targetTenant: $targetTenantId,
            reason:       trim($reason)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (class_exists('Logger', false)) {
                Logger::error('DatabaseException in executeCrossTenant()', [
                    'table'            => $table,
                    'target_tenant_id' => $targetTenantId,
                    'error'            => $e->getMessage(),
                    'sqlstate'         => $e->getCode(),
                ]);
            }
            throw $this->databaseException(
                $e,
                ['table' => $table, 'target_tenant_id' => $targetTenantId, 'sqlstate' => $e->getCode()],
                'Cross-tenant database query failed'
            );
        }

        $this->autoAuditCrossTenant($sql, $table, $targetTenantId, $reason);

        return $stmt;
    }

    private function autoAuditCrossTenant(string $sql, string $table, int $targetTenantId, string $reason): void
    {
        if (!class_exists('AuditContext', false)) {
            return;
        }

        $sqlLower = strtolower(ltrim($sql));

        if (str_starts_with($sqlLower, 'insert')) {
            $action = 'create';
        } elseif (str_starts_with($sqlLower, 'update')) {
            $action = 'update';
        } elseif (str_starts_with($sqlLower, 'delete')) {
            $action = 'delete';
        } elseif (str_starts_with($sqlLower, 'select')) {
            if ($table === '' || !in_array(strtolower($table), self::SENSITIVE_AUDIT_TABLES, true)) {
                return;
            }
            $action = 'view';
        } else {
            return;
        }

        AuditContext::capturePlatformAdminAction(
            action:       $action,
            entityType:   $table ?: 'unknown',
            entityId:     null,
            targetTenant: $targetTenantId,
            reason:       $reason
        );
    }

    // =========================================================================
    // Ownership helpers
    // =========================================================================

    /**
     * SQL fragment restricting rows to those owned by a given user.
     *
     *   WHERE {$this->tenantCondition('o')} AND {$this->ownerCondition('o')}
     */
    protected function ownerCondition(
        string $alias = '',
        string $ownerColumn = 'user_id',
        string $param = 'owner_user_id'
    ): string {
        $column = ($alias !== '') ? "{$alias}.{$ownerColumn}" : $ownerColumn;
        return "{$column} = :{$param}";
    }

    protected function ownerParam(int $userId, string $param = 'owner_user_id'): array
    {
        return [':' . $param => $userId];
    }
}