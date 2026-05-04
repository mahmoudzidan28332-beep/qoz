<?php
declare(strict_types=1);

final class PdoCurrenciesRepository
{
    private PDO $pdo;
    private ?array $columns = null;

    private const ALLOWED_COLUMNS = [
        'code', 'name', 'symbol', 'symbol_position', 'decimal_places', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $stmt = $this->pdo->query('SHOW COLUMNS FROM currencies');
        $this->columns = array_map(
            static fn(array $row): string => (string)$row['Field'],
            $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []
        );

        return $this->columns;
    }

    private function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns(), true);
    }

    private function selectColumns(): array
    {
        $columns = [];

        if ($this->hasColumn('id')) {
            $columns[] = 'id';
        }

        foreach (['code', 'name', 'symbol', 'symbol_position', 'decimal_places', 'is_active'] as $column) {
            if ($this->hasColumn($column)) {
                $columns[] = $column;
            }
        }

        if (empty($columns)) {
            throw new RuntimeException('Currencies table has no readable columns');
        }

        return $columns;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = 'SELECT ' . implode(', ', $this->selectColumns()) . ' FROM currencies WHERE 1=1';
        $params = [];

        if ($this->hasColumn('is_active') && isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $searchClauses = [];
            foreach (['code', 'name', 'symbol'] as $column) {
                if ($this->hasColumn($column)) {
                    $searchClauses[] = $column . ' LIKE :search';
                }
            }
            if (!empty($searchClauses)) {
                $sql .= ' AND (' . implode(' OR ', $searchClauses) . ')';
            }
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= $this->hasColumn('name') ? ' ORDER BY name' : ' ORDER BY code';

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM currencies WHERE 1=1";
        $params = [];

        if ($this->hasColumn('is_active') && isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $searchClauses = [];
            foreach (['code', 'name', 'symbol'] as $column) {
                if ($this->hasColumn($column)) {
                    $searchClauses[] = $column . ' LIKE :search';
                }
            }
            if (!empty($searchClauses)) {
                $sql .= ' AND (' . implode(' OR ', $searchClauses) . ')';
                $params[':search'] = '%' . $filters['search'] . '%';
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', $this->selectColumns()) . ' FROM currencies WHERE code = :code LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): string
    {
        $availableColumns = array_filter(
            self::ALLOWED_COLUMNS,
            fn(string $column): bool => $this->hasColumn($column)
        );
        $data = array_intersect_key($data, array_flip($availableColumns));

        if (empty($data['code'])) {
            throw new InvalidArgumentException('Currency code is required');
        }

        $existing = $this->findByCode((string)$data['code']);

        if ($existing) {
            $sets = [];
            $params = [':code' => $data['code']];

            foreach ($availableColumns as $column) {
                if ($column === 'code' || !array_key_exists($column, $data)) {
                    continue;
                }
                $sets[] = $column . ' = :' . $column;
                $params[':' . $column] = $column === 'is_active' ? (int)$data[$column] : $data[$column];
            }

            if (!empty($sets)) {
                $stmt = $this->pdo->prepare(
                    'UPDATE currencies SET ' . implode(', ', $sets) . ' WHERE code = :code'
                );
                $stmt->execute($params);
            }
        } else {
            $insertColumns = [];
            $placeholders = [];
            $params = [];

            foreach ($availableColumns as $column) {
                if (!array_key_exists($column, $data)) {
                    continue;
                }
                $insertColumns[] = $column;
                $placeholders[] = ':' . $column;
                $params[':' . $column] = $column === 'is_active' ? (int)$data[$column] : $data[$column];
            }

            if (empty($insertColumns)) {
                throw new InvalidArgumentException('No writable currency fields were provided');
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO currencies (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
        }

        return (string)$data['code'];
    }

    public function delete(string $code): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM currencies WHERE code = :code");
        return $stmt->execute([':code' => $code]);
    }
}
