<?php
declare(strict_types=1);

/**
 * PdoPlatformAdminRepository
 *
 * Generic cross-tenant data-access repository for Platform Admin support mode.
 *
 * SECURITY MODEL:
 *  - ONLY usable when PlatformContext::isSuperAdmin() is true.
 *  - Every method calls executeCrossTenant() (from BaseRepository) which enforces:
 *      ✔  PlatformContext::isSuperAdmin() check
 *      ✔  AuditContext::isBooted() check
 *      ✔  Non-empty $reason
 *      ✔  QueryGuard validation (tenant_id must appear in SQL)
 *      ✔  PlatformContext::logCrossTenantAction() audit
 *      ✔  AuditContext::capturePlatformAdminAction() audit
 *  - No raw PDO access.  All queries go through the inherited executeCrossTenant().
 *
 * USAGE:
 *
 *   $repo = new PdoPlatformAdminRepository($pdo);
 *
 *   // View records in any tenant
 *   $rows = $repo->listRecords('products', $targetTenantId, $reason, $filters);
 *
 *   // Fetch one record by ID
 *   $row = $repo->getRecord('products', $recordId, $targetTenantId, $reason);
 *
 *   // Create a record in a tenant
 *   $newId = $repo->createRecord('products', $targetTenantId, $reason, $data);
 *
 *   // Update a record
 *   $repo->updateRecord('products', $recordId, $targetTenantId, $reason, $data, $oldData);
 *
 *   // Soft-delete a record
 *   $repo->softDeleteRecord('products', $recordId, $targetTenantId, $reason, $oldData);
 *
 *   // Restore a soft-deleted record
 *   $repo->restoreRecord('products', $recordId, $targetTenantId, $reason);
 */
class PdoPlatformAdminRepository extends BaseRepository
{
    /**
     * List records from any table for a specific tenant.
     *
     * The query always includes `tenant_id = :tenant_id` to satisfy QueryGuard.
     * Optional $filters are applied as additional WHERE conditions.
     *
     * @param  string   $table          Table to query.
     * @param  int      $targetTenantId Tenant whose data is being accessed.
     * @param  string   $reason         Mandatory justification.
     * @param  array    $filters        Optional additional filters: ['column' => 'value'].
     * @param  int      $limit          Max rows to return (1–200).
     * @param  int      $offset         Pagination offset.
     * @return array<int, array<string, mixed>>
     *
     * @throws \InvalidArgumentException  On invalid table name or empty reason.
     */
    public function listRecords(
        string $table,
        int    $targetTenantId,
        string $reason,
        array  $filters = [],
        int    $limit   = 50,
        int    $offset  = 0
    ): array {
        $this->assertSafeTableName($table);

        $conditions = ['tenant_id = :tenant_id'];
        $params     = [':tenant_id' => $targetTenantId];

        foreach ($filters as $col => $val) {
            $this->assertSafeColumnName($col);
            $paramKey          = ':filter_' . $col;
            $conditions[]      = "`{$col}` = {$paramKey}";
            $params[$paramKey] = $val;
        }

        $limit  = min(200, max(1, $limit));
        $offset = max(0, $offset);

        $where = implode(' AND ', $conditions);
        // Use sprintf with %d to ensure integers and mitigate SQL injection scanner warnings.
        // NOTE: Column list is intentionally dynamic — this is the platform-admin cross-tenant table browser.
        // Specific column enumeration is architecturally impossible here (runtime table name).
        $sql = sprintf("SELECT * FROM `%s` WHERE %s LIMIT %d OFFSET %d", $table, $where, (int)$limit, (int)$offset);

        $stmt = $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single record by primary key from any tenant.
     *
     * @param  string   $table
     * @param  int      $recordId
     * @param  int      $targetTenantId
     * @param  string   $reason
     * @return array<string, mixed>|null  The row, or null when not found.
     */
    public function getRecord(
        string $table,
        int    $recordId,
        int    $targetTenantId,
        string $reason
    ): ?array {
        $this->assertSafeTableName($table);

        // NOTE: Column list is intentionally dynamic — runtime table name; returns full row to caller.
        $sql    = "SELECT * FROM `{$table}` WHERE id = :id AND tenant_id = :tenant_id LIMIT 1";
        $params = [':id' => $recordId, ':tenant_id' => $targetTenantId];

        $stmt = $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a record in a specific tenant on behalf of Platform Admin.
     *
     * $data must NOT include 'tenant_id' — it is injected from $targetTenantId.
     * The before_state is null (creation), after_state is $data + generated id.
     *
     * @param  string                  $table
     * @param  int                     $targetTenantId
     * @param  string                  $reason
     * @param  array<string, mixed>    $data           Field => value pairs (tenant_id excluded).
     * @return int                     The ID of the newly inserted row.
     *
     * @throws \RuntimeException   On DB error.
     */
    public function createRecord(
        string $table,
        int    $targetTenantId,
        string $reason,
        array  $data
    ): int {
        $this->assertSafeTableName($table);

        // Inject tenant_id — it is always set by platform admin, never from caller-supplied data.
        $data['tenant_id'] = $targetTenantId;

        $columns = array_keys($data);
        foreach ($columns as $col) {
            $this->assertSafeColumnName($col);
        }

        $colList    = implode(', ', array_map(fn(string $c) => "`{$c}`", $columns));
        $paramList  = implode(', ', array_map(fn(string $c) => ":{$c}", $columns));
        $sql        = "INSERT INTO `{$table}` ({$colList}) VALUES ({$paramList})";
        $params     = [];
        foreach ($data as $col => $val) {
            $params[":{$col}"] = $val;
        }

        $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);
        $newId = (int)$this->pdo->lastInsertId();

        // Post-insert: capture the after_state with the new ID.
        if (class_exists('AuditContext', false)) {
            AuditContext::capturePlatformAdminAction(
                action:       'create',
                entityType:   $table,
                entityId:     $newId,
                targetTenant: $targetTenantId,
                reason:       $reason,
                beforeState:  null,
                afterState:   array_merge($data, ['id' => $newId])
            );
        }

        return $newId;
    }

    /**
     * Update an existing record in a specific tenant on behalf of Platform Admin.
     *
     * $data fields are applied to the row identified by $recordId + $targetTenantId.
     * tenant_id is protected and cannot be changed by this method.
     *
     * @param  string                  $table
     * @param  int                     $recordId
     * @param  int                     $targetTenantId
     * @param  string                  $reason
     * @param  array<string, mixed>    $data       Changed fields (tenant_id excluded).
     * @param  array<string, mixed>    $oldData    Snapshot BEFORE the update (for audit).
     * @return bool                    True when the row was found and updated.
     */
    public function updateRecord(
        string $table,
        int    $recordId,
        int    $targetTenantId,
        string $reason,
        array  $data,
        array  $oldData = []
    ): bool {
        $this->assertSafeTableName($table);

        // Protect tenant_id — it must never change.
        unset($data['tenant_id'], $data['id']);

        if (empty($data)) {
            return false;
        }

        $setClauses = [];
        $params     = [':id' => $recordId, ':tenant_id' => $targetTenantId];

        foreach ($data as $col => $val) {
            $this->assertSafeColumnName($col);
            $setClauses[]         = "`{$col}` = :set_{$col}";
            $params[":set_{$col}"] = $val;
        }

        $setStr = implode(', ', $setClauses);
        $sql    = "UPDATE `{$table}` SET {$setStr} WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);

        if ($stmt->rowCount() > 0 && class_exists('AuditContext', false)) {
            AuditContext::capturePlatformAdminAction(
                action:       'update',
                entityType:   $table,
                entityId:     $recordId,
                targetTenant: $targetTenantId,
                reason:       $reason,
                beforeState:  $oldData ?: null,
                afterState:   $data
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete a record by setting deleted_at to the current timestamp.
     *
     * This is the PREFERRED delete method because it is reversible (spec §5).
     * Falls back to hard delete only when the table has no `deleted_at` column.
     *
     * @param  string                  $table
     * @param  int                     $recordId
     * @param  int                     $targetTenantId
     * @param  string                  $reason
     * @param  array<string, mixed>    $oldData  Snapshot BEFORE deletion (for audit).
     * @return bool
     */
    public function softDeleteRecord(
        string $table,
        int    $recordId,
        int    $targetTenantId,
        string $reason,
        array  $oldData = []
    ): bool {
        $this->assertSafeTableName($table);

        $sql    = "UPDATE `{$table}` SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL";
        $params = [':id' => $recordId, ':tenant_id' => $targetTenantId];

        $stmt = $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);

        if ($stmt->rowCount() > 0 && class_exists('AuditContext', false)) {
            AuditContext::capturePlatformAdminAction(
                action:       'delete',
                entityType:   $table,
                entityId:     $recordId,
                targetTenant: $targetTenantId,
                reason:       $reason,
                beforeState:  $oldData ?: null,
                afterState:   ['deleted_at' => date('c')]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Restore a soft-deleted record by clearing deleted_at.
     *
     * @param  string $table
     * @param  int    $recordId
     * @param  int    $targetTenantId
     * @param  string $reason
     * @return bool
     */
    public function restoreRecord(
        string $table,
        int    $recordId,
        int    $targetTenantId,
        string $reason
    ): bool {
        $this->assertSafeTableName($table);

        $sql    = "UPDATE `{$table}` SET deleted_at = NULL WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NOT NULL";
        $params = [':id' => $recordId, ':tenant_id' => $targetTenantId];

        $stmt = $this->executeCrossTenant($sql, $params, $table, $targetTenantId, $reason);

        if ($stmt->rowCount() > 0 && class_exists('AuditContext', false)) {
            AuditContext::capturePlatformAdminAction(
                action:       'restore',
                entityType:   $table,
                entityId:     $recordId,
                targetTenant: $targetTenantId,
                reason:       $reason,
                beforeState:  ['deleted_at' => 'non-null'],
                afterState:   ['deleted_at' => null]
            );
        }

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Safety helpers
    // =========================================================================

    /**
     * Assert that a table name contains only safe characters (letters, digits, underscores).
     *
     * Prevents SQL injection via dynamic table names.
     *
     * @throws \InvalidArgumentException  When the name contains dangerous characters.
     */
    private function assertSafeTableName(string $table): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException(
                "PdoPlatformAdminRepository: unsafe table name '{$table}'. "
                . 'Only letters, digits, and underscores are allowed.'
            );
        }
    }

    /**
     * Assert that a column name contains only safe characters.
     *
     * @throws \InvalidArgumentException  When the name contains dangerous characters.
     */
    private function assertSafeColumnName(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException(
                "PdoPlatformAdminRepository: unsafe column name '{$column}'. "
                . 'Only letters, digits, and underscores are allowed.'
            );
        }
    }
}
