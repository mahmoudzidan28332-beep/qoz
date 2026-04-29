<?php
declare(strict_types=1);

/**
 * PdoAddressesRepository
 *
 * Handles address management for users and entities.
 *
 * SECURITY HARDENING:
 *  - ALLOWED_COLUMNS constant guards against mass-assignment injection.
 *  - ALLOWED_ORDER_BY whitelist prevents ORDER BY SQL injection.
 *  - All SELECT queries route through BaseRepository::execute() /
 *    executePaginated() so QueryGuard and autoAudit always fire.
 *  - UPDATE and DELETE use a JOIN-based multi-table form that includes
 *    tenant_id in the WHERE clause for defence-in-depth (the addresses
 *    table has no direct tenant_id column; isolation is via JOINs).
 *  - All PDO calls are wrapped in try/catch(PDOException) — errors are
 *    logged internally and re-thrown as RuntimeException so raw DB
 *    details never reach the client.
 */
final class PdoAddressesRepository extends BaseRepository implements AddressesRepositoryInterface
{
    /**
     * Columns callers are permitted to set via create / update.
     * Every other key supplied by the user is silently discarded.
     */
    private const ALLOWED_COLUMNS = [
        'owner_type', 'owner_id',
        'address_line1', 'address_line2',
        'city_id', 'country_id', 'postal_code',
        'latitude', 'longitude', 'is_primary',
    ];

    /**
     * Fully-qualified ORDER BY expressions that are safe to interpolate.
     */
    private const ALLOWED_ORDER_BY = [
        'a.id', 'a.owner_id', 'a.owner_type',
        'a.city_id', 'a.country_id',
        'a.is_primary', 'a.created_at', 'a.updated_at',
    ];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    // =========================================================================
    // Private query helpers
    // =========================================================================

    /**
     * Standard SELECT projection for addresses (explicit columns — no SELECT *).
     * The result always includes a derived `tenant_id` column from the JOINs.
     */
    private function getBaseSelect(): string
    {
        return "
            SELECT
                a.id,
                a.owner_type,
                a.owner_id,
                a.address_line1,
                a.address_line2,
                a.city_id,
                a.country_id,
                a.postal_code,
                a.latitude,
                a.longitude,
                a.is_primary,
                a.created_at,
                a.updated_at,
                COALESCE(ct.name, c.name)   AS country_name,
                COALESCE(cit.name, ci.name) AS city_name,
                COALESCE(e.tenant_id, tu.tenant_id) AS tenant_id
            FROM addresses a
            LEFT JOIN countries c
                ON a.country_id = c.id
            LEFT JOIN country_translations ct
                ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci
                ON a.city_id = ci.id
            LEFT JOIN city_translations cit
                ON ci.id = cit.city_id AND cit.language_code = :lang_city
            LEFT JOIN entities e
                ON a.owner_type = 'entity' AND a.owner_id = e.id
            LEFT JOIN tenant_users tu
                ON a.owner_type = 'user' AND a.owner_id = tu.user_id
        ";
    }

    /**
     * Append tenant-isolation WHERE fragments when a non-zero tenant is active.
     * The addresses table has no direct tenant_id column; isolation is via JOINs.
     */
    private function applyTenantFilter(array &$where, array &$params): void
    {
        $tid = $this->getTenantId();
        if ($tid === 0) {
            return; // platform-admin: global view, no tenant filter
        }

        $where[]            = '(e.tenant_id = :tid OR tu.tenant_id = :tid_usr)';
        $params[':tid']     = $tid;
        $params[':tid_usr'] = $tid;
    }

    /**
     * Build the JOIN + condition fragment used in DML (UPDATE / DELETE) queries
     * so that tenant_id isolation is enforced at the SQL level as defence-in-depth.
     *
     * @return array{join: string, cond: string, params: array}
     */
    private function tenantDmlFragment(): array
    {
        $join = "
            LEFT JOIN entities e
                ON a.owner_type = 'entity' AND a.owner_id = e.id
            LEFT JOIN tenant_users tu
                ON a.owner_type = 'user' AND a.owner_id = tu.user_id
        ";

        $tid = $this->getTenantId();
        if ($tid === 0) {
            // Platform-admin: allow operation on any tenant row.
            return ['join' => $join, 'cond' => '1 = 1', 'params' => []];
        }

        return [
            'join'   => $join,
            'cond'   => '(e.tenant_id = :tid OR tu.tenant_id = :tid_usr)',
            'params' => [':tid' => $tid, ':tid_usr' => $tid],
        ];
    }

    // =========================================================================
    // LIST
    // =========================================================================

    public function list(
        int    $limit,
        int    $offset,
        array  $filters  = [],
        string $orderBy  = 'a.id',
        string $orderDir = 'DESC'
    ): array {
        // Whitelist ORDER BY to prevent SQL injection via user-supplied values.
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'a.id';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = ['1 = 1'];
        $params = [
            ':lang_country' => $filters['language'] ?? 'ar',
            ':lang_city'    => $filters['language'] ?? 'ar',
        ];

        $this->applyTenantFilter($where, $params);

        if (!empty($filters['owner_type'])) {
            $where[]               = 'a.owner_type = :owner_type';
            $params[':owner_type'] = $filters['owner_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]             = 'a.owner_id = :owner_id';
            $params[':owner_id'] = (int) $filters['owner_id'];
        }

        $sql = $this->getBaseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY {$orderBy} {$orderDir}"
            . ' LIMIT :limit OFFSET :offset';

        try {
            return $this->executePaginated($sql, $params, 'addresses', $limit, $offset)
                        ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::list] ' . $e->getMessage());
            throw new \RuntimeException('Database error while listing addresses', 0, $e);
        }
    }

    // =========================================================================
    // COUNT
    // =========================================================================

    public function count(array $filters = []): int
    {
        $where  = ['1 = 1'];
        $params = [];

        // Always include the JOINs so that the SQL string contains 'tenant_id'
        // (required for QueryGuard validation).
        $join = "
            LEFT JOIN entities e
                ON a.owner_type = 'entity' AND a.owner_id = e.id
            LEFT JOIN tenant_users tu
                ON a.owner_type = 'user' AND a.owner_id = tu.user_id
        ";

        $tid = $this->getTenantId();
        if ($tid > 0) {
            $where[]            = '(e.tenant_id = :tid OR tu.tenant_id = :tid_usr)';
            $params[':tid']     = $tid;
            $params[':tid_usr'] = $tid;
        }

        if (!empty($filters['owner_type'])) {
            $where[]               = 'a.owner_type = :owner_type';
            $params[':owner_type'] = $filters['owner_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]             = 'a.owner_id = :owner_id';
            $params[':owner_id'] = (int) $filters['owner_id'];
        }

        $sql = 'SELECT COUNT(*) FROM addresses a ' . $join
             . ' WHERE ' . implode(' AND ', $where);

        try {
            return (int) $this->execute($sql, $params, 'addresses')->fetchColumn();
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::count] ' . $e->getMessage());
            throw new \RuntimeException('Database error while counting addresses', 0, $e);
        }
    }

    // =========================================================================
    // FIND
    // =========================================================================

    public function find(int $id, string $language = 'ar'): ?array
    {
        $where  = ['a.id = :id'];
        $params = [
            ':id'           => $id,
            ':lang_country' => $language,
            ':lang_city'    => $language,
        ];

        $this->applyTenantFilter($where, $params);

        $sql = $this->getBaseSelect()
             . ' WHERE ' . implode(' AND ', $where)
             . ' LIMIT 1';

        try {
            $row = $this->execute($sql, $params, 'addresses')->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::find] ' . $e->getMessage());
            throw new \RuntimeException('Database error while fetching address', 0, $e);
        }
    }

    // =========================================================================
    // SAVE (INSERT or delegate to UPDATE when id is present)
    // =========================================================================

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $this->update((int) $data['id'], $data);
            return (int) $data['id'];
        }

        // Mass-assignment guard.
        $safe = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));

        if (isset($safe['is_primary']) && (int) $safe['is_primary'] === 1) {
            $this->unsetPrimaryAddresses(
                (string) $safe['owner_type'],
                (int)    $safe['owner_id']
            );
        }

        $sql = '
            INSERT INTO addresses
                (owner_type, owner_id, address_line1, address_line2,
                 city_id, country_id, postal_code, latitude, longitude, is_primary)
            VALUES
                (:owner_type, :owner_id, :address_line1, :address_line2,
                 :city_id, :country_id, :postal_code, :latitude, :longitude, :is_primary)
        ';

        try {
            $this->pdo->prepare($sql)->execute([
                ':owner_type'    => $safe['owner_type'],
                ':owner_id'      => $safe['owner_id'],
                ':address_line1' => $safe['address_line1'],
                ':address_line2' => $safe['address_line2'] ?? null,
                ':city_id'       => $safe['city_id']       ?? null,
                ':country_id'    => $safe['country_id']    ?? null,
                ':postal_code'   => $safe['postal_code']   ?? null,
                ':latitude'      => $safe['latitude']      ?? null,
                ':longitude'     => $safe['longitude']     ?? null,
                ':is_primary'    => $safe['is_primary']    ?? 0,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::save] ' . $e->getMessage());
            throw new \RuntimeException('Database error while saving address', 0, $e);
        }
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(int $id, array $data): bool
    {
        // Verify ownership and tenant scope first (throws 404 on mismatch).
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Address not found or access denied.', 404);
        }

        // Mass-assignment guard — only whitelisted columns may be updated.
        // Ownership (owner_type / owner_id) is immutable after creation.
        $safe = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
        unset($safe['owner_type'], $safe['owner_id']);

        if (empty($safe)) {
            return false;
        }

        if (isset($safe['is_primary']) && (int) $safe['is_primary'] === 1) {
            $this->unsetPrimaryAddresses(
                (string) $existing['owner_type'],
                (int)    $existing['owner_id'],
                $id
            );
        }

        // Build SET clause using only whitelisted column names.
        $sets   = [];
        $params = [':id' => $id];
        foreach ($safe as $col => $val) {
            $sets[]           = "a.{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }

        // Multi-table UPDATE includes tenant_id via JOINs for defence-in-depth.
        $tf  = $this->tenantDmlFragment();
        $sql = "
            UPDATE addresses a
            {$tf['join']}
            SET " . implode(', ', $sets) . "
            WHERE a.id = :id
              AND {$tf['cond']}
        ";
        $params = array_merge($params, $tf['params']);

        try {
            return (bool) $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::update] ' . $e->getMessage());
            throw new \RuntimeException('Database error while updating address', 0, $e);
        }
    }

    // =========================================================================
    // UNSET PRIMARY (internal helper)
    // =========================================================================

    private function unsetPrimaryAddresses(
        string $ownerType,
        int    $ownerId,
        ?int   $excludeId = null
    ): void {
        $tf = $this->tenantDmlFragment();

        $sql = "
            UPDATE addresses a
            {$tf['join']}
            SET a.is_primary = 0
            WHERE a.owner_type = :owner_type
              AND a.owner_id   = :owner_id
              AND {$tf['cond']}
        ";

        $params = array_merge(
            [':owner_type' => $ownerType, ':owner_id' => $ownerId],
            $tf['params']
        );

        if ($excludeId !== null) {
            $sql                  .= ' AND a.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::unsetPrimaryAddresses] ' . $e->getMessage());
            throw new \RuntimeException('Database error while unsetting primary address', 0, $e);
        }
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function delete(int $id): bool
    {
        // Verify ownership and tenant scope first (throws 404 on mismatch).
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Address not found or access denied.', 404);
        }

        // Multi-table DELETE includes tenant_id via JOINs for defence-in-depth.
        $tf  = $this->tenantDmlFragment();
        $sql = "
            DELETE a FROM addresses a
            {$tf['join']}
            WHERE a.id = :id
              AND {$tf['cond']}
        ";
        $params = array_merge([':id' => $id], $tf['params']);

        try {
            return (bool) $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::delete] ' . $e->getMessage());
            throw new \RuntimeException('Database error while deleting address', 0, $e);
        }
    }

    // =========================================================================
    // GET BY OWNER
    // =========================================================================

    public function getByOwner(int $ownerId, string $ownerType = 'user'): array
    {
        $where  = ['a.owner_id = :oid', 'a.owner_type = :otype'];
        $params = [
            ':oid'          => $ownerId,
            ':otype'        => $ownerType,
            ':lang_country' => 'ar',
            ':lang_city'    => 'ar',
        ];

        $this->applyTenantFilter($where, $params);

        $sql = $this->getBaseSelect()
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY a.is_primary DESC, a.id DESC';

        try {
            return $this->execute($sql, $params, 'addresses')
                        ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[PdoAddressesRepository::getByOwner] ' . $e->getMessage());
            throw new \RuntimeException('Database error while fetching addresses by owner', 0, $e);
        }
    }
}
