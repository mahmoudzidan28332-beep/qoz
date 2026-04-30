<?php
declare(strict_types=1);

/**
 * PdoAddressesRepository
 * 
 * Handles address management for users and entities.
 * HARDENED: All write operations and finds now enforce tenant isolation.
 */
final class PdoAddressesRepository extends BaseRepository
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Build the standard SELECT clause for addresses with resolved tenant_id.
     */
    private function getBaseSelect(): string
    {
        return "
            SELECT 
                a.*,
                COALESCE(ct.name, c.name) AS country_name,
                COALESCE(cit.name, ci.name) AS city_name,
                COALESCE(e.tenant_id, tu.tenant_id) AS tenant_id
            FROM addresses a
            LEFT JOIN countries c ON a.country_id = c.id
            LEFT JOIN country_translations ct ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci ON a.city_id = ci.id
            LEFT JOIN city_translations cit ON ci.id = cit.city_id AND cit.language_code = :lang_city
            LEFT JOIN entities e ON a.owner_type = 'entity' AND a.owner_id = e.id
            LEFT JOIN tenant_users tu ON a.owner_type = 'user' AND a.owner_id = tu.user_id
        ";
    }

    private function applyTenantFilter(array &$where, array &$params): void
    {
        $tid = $this->getTenantId();
        if ($tid === 0) return;

        $where[] = "(e.tenant_id = :tid OR tu.tenant_id = :tid_usr)";
        $params[':tid'] = $tid;
        $params[':tid_usr'] = $tid;
    }

    public function list(int $limit, int $offset, array $filters = [], string $orderBy = 'a.id', string $orderDir = 'DESC'): array
    {
        $where  = ['1=1'];
        $params = [
            ':lang_country' => $filters['language'] ?? 'ar',
            ':lang_city'    => $filters['language'] ?? 'ar'
        ];

        $this->applyTenantFilter($where, $params);

        if (!empty($filters['owner_type'])) {
            $where[] = "a.owner_type = :owner_type";
            $params[':owner_type'] = $filters['owner_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[] = "a.owner_id = :owner_id";
            $params[':owner_id'] = (int)$filters['owner_id'];
        }

        $sql = $this->getBaseSelect() . " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY $orderBy $orderDir LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        $tid = $this->getTenantId();
        $join = "";
        if ($tid > 0) {
            $join = "
                LEFT JOIN entities e ON a.owner_type = 'entity' AND a.owner_id = e.id
                LEFT JOIN tenant_users tu ON a.owner_type = 'user' AND a.owner_id = tu.user_id
            ";
            $where[] = "(e.tenant_id = :tid OR tu.tenant_id = :tid_usr)";
            $params[':tid'] = $tid;
            $params[':tid_usr'] = $tid;
        }

        if (!empty($filters['owner_type'])) {
            $where[] = "a.owner_type = :owner_type";
            $params[':owner_type'] = $filters['owner_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[] = "a.owner_id = :owner_id";
            $params[':owner_id'] = (int)$filters['owner_id'];
        }

        $sql = "SELECT COUNT(*) FROM addresses a $join WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id, string $language = 'ar'): ?array
    {
        $where  = ['a.id = :id'];
        $params = [
            ':id'           => $id,
            ':lang_country' => $language,
            ':lang_city'    => $language
        ];

        $this->applyTenantFilter($where, $params);

        $sql = $this->getBaseSelect() . " WHERE " . implode(' AND ', $where) . " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $this->update((int)$data['id'], $data);
            return (int)$data['id'];
        }

        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            $this->unsetPrimaryAddresses($data['owner_type'], (int)$data['owner_id']);
        }

        $sql = "
            INSERT INTO addresses (
                owner_type, owner_id, address_line1, address_line2,
                city_id, country_id, postal_code,
                latitude, longitude, is_primary
            ) VALUES (
                :owner_type, :owner_id, :address_line1, :address_line2,
                :city_id, :country_id, :postal_code,
                :latitude, :longitude, :is_primary
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'owner_type'    => $data['owner_type'],
            'owner_id'      => $data['owner_id'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city_id'       => $data['city_id'] ?? null,
            'country_id'    => $data['country_id'] ?? null,
            'postal_code'   => $data['postal_code'] ?? null,
            'latitude'      => $data['latitude'] ?? null,
            'longitude'     => $data['longitude'] ?? null,
            'is_primary'    => $data['is_primary'] ?? 0
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new ApplicationException('Address not found or access denied.', 404);
        }

        unset($data['id'], $data['csrf_token'], $data['tenant_id']);
        if (!$data) return false;

        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            $this->unsetPrimaryAddresses($existing['owner_type'], (int)$existing['owner_id'], $id);
        }

        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $key => $val) {
            $sets[] = "$key = :$key";
            $params[":$key"] = $val;
        }

        return $this->pdo->prepare(
            "UPDATE addresses SET " . implode(', ', $sets) . " WHERE id = :id"
        )->execute($params);
    }

    private function unsetPrimaryAddresses(string $ownerType, int $ownerId, ?int $excludeId = null): void
    {
        $sql    = "UPDATE addresses SET is_primary = 0 WHERE owner_type = :owner_type AND owner_id = :owner_id";
        $params = ['owner_type' => $ownerType, 'owner_id' => $ownerId];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new ApplicationException('Address not found or access denied.', 404);
        }

        return $this->pdo
            ->prepare("DELETE FROM addresses WHERE id = :id")
            ->execute(['id' => $id]);
    }

    public function getByOwner(int $ownerId, string $ownerType = 'user'): array
    {
        $where = [
            'a.owner_id = :oid',
            'a.owner_type = :otype',
        ];
        $params = [
            ':oid'          => $ownerId,
            ':otype'        => $ownerType,
            ':lang_country' => 'ar',
            ':lang_city'    => 'ar',
        ];

        $this->applyTenantFilter($where, $params);

        $sql = $this->getBaseSelect()
            . " WHERE " . implode(' AND ', $where)
            . " ORDER BY a.is_primary DESC, a.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
