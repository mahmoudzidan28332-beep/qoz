<?php
declare(strict_types=1);

/**
 * PdoAddressesRepository
 *
 * Handles address management for users, tenants, entities, and tenant users.
 *
 * TABLE SCHEMA (addresses):
 *  - id          BIGINT UNSIGNED PK AUTO_INCREMENT
 *  - tenant_id   INT(11) UNSIGNED NOT NULL DEFAULT 0  ← direct column on the table
 *  - owner_type  ENUM('user','entity') NOT NULL
 *  - owner_id    BIGINT UNSIGNED NOT NULL
 *  - address_line1 / address_line2 / city_id / country_id / postal_code / latitude / longitude
 *  - is_primary  TINYINT(1)
 *  - primary_marker VIRTUAL GENERATED
 *  - created_at / updated_at
 *
 * TENANT ISOLATION:
 *  Because `tenant_id` is a first-class column on the table every query uses
 *  a simple `a.tenant_id = :tenant_id` predicate.  There are no JOINs to
 *  `entities` or `tenant_users` for isolation purposes (those tables are
 *  irrelevant — tenant_id is stored directly on the address row at INSERT time).
 *
 *  tenant_id is scoped per-request via TenantContext.  Regular tenants have
 *  tid > 0 and every query carries `a.tenant_id = :tenant_id`.  Platform admins
 *  arrive with tid = 0; applyTenantFilter() returns early (no predicate added),
 *  and BaseRepository::ensureTenantScope() auto-injects `tenant_id = 0` as a
 *  safety net — so they see/mutate only rows whose tenant_id is 0.  Cross-tenant
 *  admin operations should use executeCrossTenant() or dedicated admin routes.
 *
 * SECURITY HARDENING:
 *  - ALLOWED_COLUMNS guards every write path against mass-assignment injection.
 *  - ALLOWED_ORDER_BY whitelist prevents ORDER BY SQL injection.
 *  - All SELECT paths route through BaseRepository::execute() /
 *    executePaginated() so QueryGuard + autoAudit always fire.
 *  - All PDO calls are wrapped in catch(PDOException) — errors are
 *    logged internally and re-thrown as RuntimeException.
 */
final class PdoAddressesRepository extends BaseRepository implements AddressesRepositoryInterface
{
    /**
     * Columns callers are permitted to set via create / update.
     * `tenant_id` is intentionally excluded — it is always sourced from
     * TenantContext, never from user-supplied data.
     *
     * @var string[]
     */
    private const ALLOWED_COLUMNS = [
        'owner_type', 'owner_id',
        'address_line1', 'address_line2',
        'city_id', 'country_id', 'postal_code',
        'latitude', 'longitude', 'is_primary',
    ];

    /**
     * Fully-qualified ORDER BY expressions that are safe to interpolate.
     *
     * @var string[]
     */
    private const ALLOWED_ORDER_BY = [
        'a.id', 'a.tenant_id', 'a.owner_id', 'a.owner_type',
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
     * Standard SELECT projection for addresses with translated country/city names.
     * Uses the direct `a.tenant_id` column — no JOIN-based derivation needed.
     */
    private function getBaseSelect(): string
    {
        return "
            SELECT
                a.id,
                a.tenant_id,
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
                COALESCE(cit.name, ci.name) AS city_name
            FROM addresses a
            LEFT JOIN countries c
                ON a.country_id = c.id
            LEFT JOIN country_translations ct
                ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci
                ON a.city_id = ci.id
            LEFT JOIN city_translations cit
                ON ci.id = cit.city_id AND cit.language_code = :lang_city
        ";
    }

    /**
     * Append the tenant-isolation predicate to $where / $params.
     *
     * Permission logic lives here in PHP; the SQL predicate is kept simple and
     * index-friendly (`a.tenant_id = :tenant_id` — no OR, no subqueries).
     *
     * When tid = 0 (platform-admin context) this method returns without adding
     * anything.  BaseRepository::ensureTenantScope() acts as a safety net and
     * auto-injects `tenant_id = 0`, scoping the admin to global/unowned rows.
     */
    private function applyTenantFilter(array &$where, array &$params): void
    {
        $tid = $this->getTenantId();
        if ($tid === 0) {
            return; // global context: ensureTenantScope safety-net handles this
        }

        $where[]              = 'a.tenant_id = :tenant_id';
        $params[':tenant_id'] = $tid;
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

        $this->applyTenantFilter($where, $params);

        if (!empty($filters['owner_type'])) {
            $where[]               = 'a.owner_type = :owner_type';
            $params[':owner_type'] = $filters['owner_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]             = 'a.owner_id = :owner_id';
            $params[':owner_id'] = (int) $filters['owner_id'];
        }

        $sql = 'SELECT COUNT(*) FROM addresses a WHERE ' . implode(' AND ', $where);

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

        // Mass-assignment guard — only ALLOWED_COLUMNS pass through.
        $safe = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));

        if (isset($safe['is_primary']) && (int) $safe['is_primary'] === 1) {
            $this->unsetPrimaryAddresses(
                (string) $safe['owner_type'],
                (int)    $safe['owner_id']
            );
        }

        // tenant_id is sourced from TenantContext, not from user-supplied $data.
        $tenantId = $this->getTenantId();

        $sql = '
            INSERT INTO addresses
                (tenant_id, owner_type, owner_id,
                 address_line1, address_line2,
                 city_id, country_id, postal_code,
                 latitude, longitude, is_primary)
            VALUES
                (:tenant_id, :owner_type, :owner_id,
                 :address_line1, :address_line2,
                 :city_id, :country_id, :postal_code,
                 :latitude, :longitude, :is_primary)
        ';

        try {
            $this->pdo->prepare($sql)->execute([
                ':tenant_id'     => $tenantId,
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
        // Verify row belongs to this tenant (and exists) before mutating.
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Address not found or access denied.', 404);
        }

        // Mass-assignment guard — only whitelisted columns may be updated.
        // Ownership fields (owner_type / owner_id) and tenant_id are immutable.
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

        // Build SET clause — secondary in_array() is defence-in-depth.
        $sets   = [];
        $params = [
            ':id'        => $id,
            ':tenant_id' => $this->getTenantId(),
        ];
        foreach ($safe as $col => $val) {
            if (!in_array($col, self::ALLOWED_COLUMNS, true)) {
                continue;
            }
            $sets[]            = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }

        if (empty($sets)) {
            return false;
        }

        // tenant_id in WHERE is defence-in-depth against cross-tenant writes.
        // Uses a simple equality predicate (index-friendly, no OR).
        $sql = 'UPDATE addresses SET ' . implode(', ', $sets)
             . ' WHERE id = :id AND tenant_id = :tenant_id';

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
        $params = [
            ':owner_type' => $ownerType,
            ':owner_id'   => $ownerId,
            ':tenant_id'  => $this->getTenantId(),
        ];

        // Simple equality predicate — index-friendly, no OR bypass.
        $sql = 'UPDATE addresses SET is_primary = 0
                WHERE owner_type = :owner_type
                  AND owner_id   = :owner_id
                  AND tenant_id  = :tenant_id';

        if ($excludeId !== null) {
            $sql                  .= ' AND id != :exclude_id';
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
        // Verify row belongs to this tenant (and exists) before deleting.
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Address not found or access denied.', 404);
        }

        // Simple equality predicate — index-friendly, no OR bypass.
        // rowCount() > 0 (no (bool) cast) avoids the PHP operator-precedence
        // pitfall where (bool)$n > 0 would cast before comparing.
        $sql    = 'DELETE FROM addresses WHERE id = :id AND tenant_id = :tenant_id';
        $params = [':id' => $id, ':tenant_id' => $this->getTenantId()];

        try {
            return $this->execute($sql, $params, 'addresses')->rowCount() > 0;
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
