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
    // QueryGuard integration
    // =========================================================================

    /**
     * Prepare a PDO statement after asserting that the SQL is tenant-scoped.
     *
     * Drop-in replacement for $this->pdo->prepare() when you want QueryGuard
     * enforcement baked in:
     *
     *   $stmt = $this->guardedQuery(
     *       'SELECT * FROM products WHERE ' . $this->tenantCondition('p'),
     *       'products'
     *   );
     *   $stmt->execute($this->tenantParam());
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
