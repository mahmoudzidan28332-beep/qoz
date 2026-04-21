<?php
declare(strict_types=1);

final class PdoAddressesRepository extends BaseRepository
{
    private const ALLOWED_ORDER_BY = [
        'id','owner_type','owner_id','city_id','country_id',
        'is_primary','created_at','updated_at'
    ];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    // ================================
    // LIST - Supports both tenant users and regular users
    // ================================
    public function list(
        int $limit,
        int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = [];
        $params = [];
        $language = $filters['language'] ?? 'ar';

        // ============================================================
        // MULTI-TENANT SAFETY:
        // - If tenant_id is provided (tenant user/admin): filter by that tenant
        // - If no tenant_id (regular user): only show addresses with NULL tenant_id
        // - Also ensure owner_id is provided for regular users to prevent data leakage
        // ============================================================
        
        $hasTenantId = isset($filters['tenant_id']) && $filters['tenant_id'] !== null && $filters['tenant_id'] !== '';
        
        if ($hasTenantId) {
            // Tenant user or admin: filter by specific tenant
            $where[] = "a.tenant_id = :filter_tenant_id";
            $params['filter_tenant_id'] = (int)$filters['tenant_id'];
        } else {
            // Regular user: only show addresses with NULL tenant_id
            $where[] = "a.tenant_id IS NULL";
        }

        // Apply individual field filters
        foreach ([
            'id','owner_type','owner_id','city_id','country_id','is_primary'
        ] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null && $filters[$field] !== '') {
                $where[] = "a.$field = :filter_$field";
                $params["filter_$field"] = $filters[$field];
            }
        }

        // Security: For regular users (no tenant_id), we MUST have owner_id to prevent data leakage
        if (!$hasTenantId) {
            if (!isset($filters['owner_id']) || $filters['owner_id'] === null || $filters['owner_id'] === '') {
                // Regular user without owner_id - return empty for safety
                return ['items' => [], 'total' => 0];
            }
            // Ensure owner_type is 'user' for regular users
            $where[] = "a.owner_type = 'user'";
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        // ================================
        // DATA WITH TRANSLATIONS
        // ================================
        $sql = "
            SELECT 
                a.*,
                COALESCE(ct.name, c.name) AS country_name,
                COALESCE(cit.name, ci.name) AS city_name
            FROM addresses a
            LEFT JOIN countries c ON a.country_id = c.id
            LEFT JOIN country_translations ct ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci ON a.city_id = ci.id
            LEFT JOIN city_translations cit ON ci.id = cit.city_id AND cit.language_code = :lang_city
            $whereSql
            ORDER BY a.$orderBy $orderDir
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        // Bind language parameters
        $stmt->bindValue(':lang_country', $language, PDO::PARAM_STR);
        $stmt->bindValue(':lang_city', $language, PDO::PARAM_STR);
        
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue(":$k", $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
            }
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ================================
        // COUNT
        // ================================
        $countSql = "
            SELECT COUNT(*)
            FROM addresses a
            $whereSql
        ";

        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $countStmt->bindValue(":$k", $v, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue(":$k", $v, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();

        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    // ================================
    // FIND - Supports both tenant users and regular users
    // ================================
    public function find(int $id, string $language = 'ar', ?int $tenantId = null, ?int $ownerId = null): ?array
    {
        $where  = ['a.id = :id'];
        $params = [':id' => $id];
        $hasTenantId = $tenantId !== null && $tenantId > 0;

        // ============================================================
        // MULTI-TENANT SAFETY:
        // - If tenant_id provided: filter by tenant_id
        // - If no tenant_id: filter by NULL tenant_id AND owner_id
        // ============================================================
        
        if ($hasTenantId) {
            // Tenant user or admin: filter by specific tenant
            $where[] = "a.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        } else {
            // Regular user: only addresses with NULL tenant_id
            $where[] = "a.tenant_id IS NULL";
            
            // Regular user MUST provide owner_id for security
            if ($ownerId === null || $ownerId <= 0) {
                return null;
            }
            $where[] = "a.owner_id = :owner_id";
            $where[] = "a.owner_type = 'user'";
            $params[':owner_id'] = $ownerId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                a.*,
                COALESCE(ct.name, c.name) AS country_name,
                COALESCE(cit.name, ci.name) AS city_name
            FROM addresses a
            LEFT JOIN countries c ON a.country_id = c.id
            LEFT JOIN country_translations ct ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci ON a.city_id = ci.id
            LEFT JOIN city_translations cit ON ci.id = cit.city_id AND cit.language_code = :lang_city
            $whereSql
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lang_country', $language, PDO::PARAM_STR);
        $stmt->bindValue(':lang_city', $language, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($hasTenantId) {
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        }
        if (!$hasTenantId && $ownerId !== null) {
            $stmt->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
        }
        
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // CREATE - Supports both tenant users and regular users
    // ================================
    public function create(array $data): int
    {
        // Handle tenant_id (can be NULL for regular users)
        $tenantId = isset($data['tenant_id']) && $data['tenant_id'] !== '' ? (int)$data['tenant_id'] : null;

        // If setting as primary, unset other primary addresses for this owner
        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            $this->unsetPrimaryAddresses(
                $data['owner_type'], 
                (int)$data['owner_id'], 
                $tenantId
            );
        }

        $sql = "
            INSERT INTO addresses (
                tenant_id, owner_type, owner_id, address_line1, address_line2,
                city_id, country_id, postal_code,
                latitude, longitude, is_primary
            ) VALUES (
                :tenant_id, :owner_type, :owner_id, :address_line1, :address_line2,
                :city_id, :country_id, :postal_code,
                :latitude, :longitude, :is_primary
            )
        ";

        $params = [
            'tenant_id'     => $tenantId,
            'owner_type'    => $data['owner_type'] ?? null,
            'owner_id'      => $data['owner_id'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city_id'       => isset($data['city_id']) && $data['city_id'] !== '' ? (int)$data['city_id'] : null,
            'country_id'    => isset($data['country_id']) && $data['country_id'] !== '' ? (int)$data['country_id'] : null,
            'postal_code'   => $data['postal_code'] ?? null,
            'latitude'      => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null,
            'longitude'     => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null,
            'is_primary'    => isset($data['is_primary']) ? (int)$data['is_primary'] : 0,
        ];

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'entity_address') !== false) {
                throw new \RuntimeException('An address already exists. If you have a UNIQUE constraint on entity_address, please modify it to only apply when is_primary = 1', 400);
            }
            throw $e;
        }
    }

    // ================================
    // UPDATE - Supports both tenant users and regular users
    // ================================
    public function update(int $id, array $data, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        unset($data['id']);
        unset($data['csrf_token']);
        unset($data['tenant_id']);

        if (!$data) {
            return false;
        }

        $hasTenantId = $tenantId !== null && $tenantId > 0;

        // ============================================================
        // Build WHERE clause based on user type
        // ============================================================
        $whereClause = "id = :id";
        $params = ['id' => $id];

        if ($hasTenantId) {
            // Tenant user: filter by tenant_id
            $whereClause .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        } else {
            // Regular user: filter by NULL tenant_id AND owner_id
            $whereClause .= " AND tenant_id IS NULL";
            
            if ($ownerId === null || $ownerId <= 0) {
                return false;
            }
            $whereClause .= " AND owner_id = :owner_id AND owner_type = 'user'";
            $params['owner_id'] = $ownerId;
        }

        // If setting as primary, unset other primary addresses for this owner
        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            // Get current address to find owner
            $current = $this->find($id, 'ar', $tenantId, $ownerId);
            if ($current) {
                $this->unsetPrimaryAddresses(
                    $current['owner_type'], 
                    (int)$current['owner_id'], 
                    $tenantId,
                    $id
                );
            }
        }

        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[$key] = $value;
        }

        $sql = "UPDATE addresses SET ".implode(', ', $sets)." WHERE $whereClause";
        
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ================================
    // UNSET PRIMARY ADDRESSES - Supports both tenant users and regular users
    // ================================
    private function unsetPrimaryAddresses(string $ownerType, int $ownerId, ?int $tenantId = null, ?int $excludeId = null): void
    {
        $sql = "UPDATE addresses SET is_primary = 0 WHERE owner_type = :owner_type AND owner_id = :owner_id";
        $params = [
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
        ];

        $hasTenantId = $tenantId !== null && $tenantId > 0;
        
        if ($hasTenantId) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    // ================================
    // DELETE - Supports both tenant users and regular users
    // ================================
    public function delete(int $id, ?int $tenantId = null, ?int $ownerId = null): bool
    {
        $hasTenantId = $tenantId !== null && $tenantId > 0;
        
        // ============================================================
        // Build WHERE clause based on user type
        // ============================================================
        $whereClause = "id = :id";
        $params = ['id' => $id];

        if ($hasTenantId) {
            // Tenant user: filter by tenant_id
            $whereClause .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        } else {
            // Regular user: filter by NULL tenant_id AND owner_id
            $whereClause .= " AND tenant_id IS NULL";
            
            if ($ownerId === null || $ownerId <= 0) {
                return false;
            }
            $whereClause .= " AND owner_id = :owner_id AND owner_type = 'user'";
            $params['owner_id'] = $ownerId;
        }

        return $this->pdo
            ->prepare("DELETE FROM addresses WHERE $whereClause")
            ->execute($params);
    }

    // ================================
    // DELETE BY OWNER (for public user routes — no tenant_id)
    // ================================
    public function deleteByOwner(int $id, int $ownerId): bool
    {
        if ($ownerId <= 0) {
            return false;
        }
        return $this->pdo
            ->prepare("DELETE FROM addresses WHERE id = :id AND owner_id = :owner_id AND owner_type = 'user' AND tenant_id IS NULL")
            ->execute(['id' => $id, 'owner_id' => $ownerId]);
    }

    // ================================
    // RESET PRIMARY (for tenant users)
    // ================================
    public function resetPrimary(int $ownerId, int $tenantId = 0): bool
    {
        if ($tenantId <= 0) {
            return false;
        }
        return $this->pdo
            ->prepare('UPDATE addresses SET is_primary = 0 WHERE owner_id = ? AND owner_type = "user" AND tenant_id = ?')
            ->execute([$ownerId, $tenantId]);
    }

    // ================================
    // RESET PRIMARY BY OWNER (for regular users — tenant_id is NULL)
    // ================================
    public function resetPrimaryByOwner(int $ownerId): bool
    {
        if ($ownerId <= 0) {
            return false;
        }
        return $this->pdo
            ->prepare("UPDATE addresses SET is_primary = 0 WHERE owner_id = :owner_id AND owner_type = 'user' AND tenant_id IS NULL")
            ->execute(['owner_id' => $ownerId]);
    }

    // ================================
    // CREATE ADDRESS (simplified version for public routes)
    // ================================
    public function createAddress(int $ownerId, string $addressLine1, ?string $addressLine2, ?int $cityId, ?int $countryId, ?string $postalCode, int $isPrimary, ?int $tenantId = null): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO addresses (tenant_id, owner_type, owner_id, address_line1, address_line2, city_id, country_id, postal_code, is_primary)
             VALUES (?, "user", ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $ownerId, $addressLine1, $addressLine2, $cityId, $countryId, $postalCode, $isPrimary]);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // GET ADDRESSES BY OWNER (with tenant support)
    // ================================
    public function getByOwner(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): array
    {
        $filters = [
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
            'language' => 'ar'
        ];
        
        if ($tenantId !== null && $tenantId > 0) {
            $filters['tenant_id'] = $tenantId;
        }
        
        $result = $this->list(100, 0, $filters, 'is_primary', 'DESC');
        return $result['items'];
    }

    // ================================
    // GET PRIMARY ADDRESS (with tenant support)
    // ================================
    public function getPrimaryAddress(int $ownerId, string $ownerType = 'user', ?int $tenantId = null): ?array
    {
        $filters = [
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
            'is_primary' => 1,
            'language' => 'ar'
        ];
        
        if ($tenantId !== null && $tenantId > 0) {
            $filters['tenant_id'] = $tenantId;
        }
        
        $result = $this->list(1, 0, $filters, 'id', 'ASC');
        return $result['items'][0] ?? null;
    }
}
