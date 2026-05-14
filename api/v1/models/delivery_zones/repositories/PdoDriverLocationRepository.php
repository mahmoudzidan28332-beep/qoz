<?php
declare(strict_types=1);

final class PdoDriverLocationRepository implements DriverLocationRepositoryInterface
{
    private PDO $pdo;

    /** Columns returned by SELECT – excludes the binary GEOMETRY 'location' column */
    private const SELECT_COLS = 'dl.id, dl.provider_id, dl.latitude, dl.longitude, dl.updated_at';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'dl.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // Join with delivery_providers to ensure tenant scope
        // Exclude binary GEOMETRY column 'location' to avoid JSON encoding issues
        $sql = "
            SELECT " . self::SELECT_COLS . ", dp.provider_type
            FROM driver_locations dl
            INNER JOIN delivery_providers dp ON dl.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        if (isset($filters['provider_id']) && $filters['provider_id'] !== '') {
            $sql .= " AND dl.provider_id = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM driver_locations dl
            INNER JOIN delivery_providers dp ON dl.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        if (isset($filters['provider_id']) && $filters['provider_id'] !== '') {
            $sql .= " AND dl.provider_id = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT " . self::SELECT_COLS . "
            FROM driver_locations dl
            INNER JOIN delivery_providers dp ON dl.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id AND dl.id = :id
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function findByProviderId(int $tenantId, int $providerId): ?array
    {
        $sql = "
            SELECT " . self::SELECT_COLS . "
            FROM driver_locations dl
            INNER JOIN delivery_providers dp ON dl.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id AND dl.provider_id = :provider_id
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':tenant_id' => $tenantId, ':provider_id' => $providerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function create(int $tenantId, array $data): int
    {
        // Use SRID=0 explicitly for broad MySQL 5.7 / MySQL 8.x / MariaDB compatibility
        $sql = "
            INSERT INTO driver_locations (provider_id, latitude, longitude, location)
            VALUES (:provider_id, :latitude, :longitude, ST_GeomFromText(:point_wkt, 0))
        ";

        $wkt = "POINT({$data['longitude']} {$data['latitude']})";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':provider_id' => $data['provider_id'],
                ':latitude'    => $data['latitude'],
                ':longitude'   => $data['longitude'],
                ':point_wkt'   => $wkt
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }

        // Verify tenant ownership first via provider
        $existing = $this->find($tenantId, (int)$data['id']);
        if (!$existing) {
             throw new InvalidArgumentException('Location not found or access denied.');
        }

        $sql = "
            UPDATE driver_locations 
            SET latitude = :latitude, 
                longitude = :longitude, 
                location = ST_GeomFromText(:point_wkt, 0)
            WHERE id = :id
        ";

        $wkt = "POINT({$data['longitude']} {$data['latitude']})";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id'        => $data['id'],
                ':latitude'  => $data['latitude'],
                ':longitude' => $data['longitude'],
                ':point_wkt' => $wkt
            ]);
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        // Verify tenant ownership first
        $existing = $this->find($tenantId, $id);
        if (!$existing) return false;

        try {
            $stmt = $this->pdo->prepare("DELETE FROM driver_locations WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            throw new \DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }
}