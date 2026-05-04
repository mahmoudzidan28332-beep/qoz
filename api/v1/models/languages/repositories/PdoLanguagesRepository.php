<?php
declare(strict_types=1);

final class PdoLanguagesRepository
{
    private PDO $pdo;
    private ?array $columns = null;

    private const ALLOWED_COLUMNS = [
        'code', 'name', 'native_name', 'direction', 'is_active', 'flag_url'
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

        $stmt = $this->pdo->query('SHOW COLUMNS FROM languages');
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
            $columns[] = 'l.id';
        }

        foreach (['code', 'name', 'native_name', 'direction', 'is_active', 'flag_url', 'created_at'] as $column) {
            if ($this->hasColumn($column)) {
                $columns[] = 'l.' . $column;
            }
        }

        if (empty($columns)) {
            throw new RuntimeException('Languages table has no readable columns');
        }

        return $columns;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT " . implode(', ', $this->selectColumns()) . "
            FROM languages l
            WHERE 1=1
        ";

        $params = [];

        if ($this->hasColumn('is_active') && isset($filters['is_active'])) {
            $sql .= " AND l.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $searchClauses = [];
            foreach (['name', 'code', 'native_name'] as $column) {
                if ($this->hasColumn($column)) {
                    $searchClauses[] = 'l.' . $column . ' LIKE :search';
                }
            }
            if (!empty($searchClauses)) {
                $sql .= ' AND (' . implode(' OR ', $searchClauses) . ')';
                $params[':search'] = '%' . $filters['search'] . '%';
            }
        }

        $sql .= $this->hasColumn('name') ? ' ORDER BY l.name' : ' ORDER BY l.code';

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
        $sql = "SELECT COUNT(*) FROM languages l WHERE 1=1";
        $params = [];

        if ($this->hasColumn('is_active') && isset($filters['is_active'])) {
            $sql .= " AND l.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $searchClauses = [];
            foreach (['name', 'code', 'native_name'] as $column) {
                if ($this->hasColumn($column)) {
                    $searchClauses[] = 'l.' . $column . ' LIKE :search';
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

    public function find(int $id): ?array
    {
        if (!$this->hasColumn('id')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', array_map(
                static fn(string $column): string => str_starts_with($column, 'l.') ? substr($column, 2) : $column,
                $this->selectColumns()
            )) . ' FROM languages WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', array_map(
                static fn(string $column): string => str_starts_with($column, 'l.') ? substr($column, 2) : $column,
                $this->selectColumns()
            )) . ' FROM languages WHERE code = :code LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $availableColumns = array_filter(
            self::ALLOWED_COLUMNS,
            fn(string $column): bool => $this->hasColumn($column)
        );
        $data = array_intersect_key($data, array_flip($availableColumns)) + (isset($data['id']) ? ['id' => $data['id']] : []);
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if (empty($data['code'])) {
            throw new InvalidArgumentException('Language code is required');
        }

        // Check uniqueness
        if (!$isUpdate || ($oldData && $oldData['code'] !== $data['code'])) {
            if ($this->findByCode($data['code'])) {
                throw new ApplicationException('Language code already exists');
            }
        }

        if ($isUpdate) {
            if (!$this->hasColumn('id')) {
                throw new ApplicationException('Language updates require an id column in the current schema');
            }

            $sets = [];
            $params = [':id' => (int)$data['id']];
            foreach ($availableColumns as $column) {
                if (!array_key_exists($column, $data)) {
                    continue;
                }
                $sets[] = $column . ' = :' . $column;
                $params[':' . $column] = $column === 'is_active' ? (int)$data[$column] : ($data[$column] === '' ? null : $data[$column]);
            }

            $stmt = $this->pdo->prepare(
                'UPDATE languages SET ' . implode(', ', $sets) . ' WHERE id = :id'
            );
            $stmt->execute($params);
            $id = (int)$data['id'];
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
                $params[':' . $column] = $column === 'is_active' ? (int)$data[$column] : ($data[$column] === '' ? null : $data[$column]);
            }

            if ($this->hasColumn('created_at') && !in_array('created_at', $insertColumns, true)) {
                $insertColumns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO languages (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            $id = $this->hasColumn('id') ? (int)$this->pdo->lastInsertId() : 0;
        }

        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        if (!$this->hasColumn('id')) {
            throw new ApplicationException('Language deletes require an id column in the current schema');
        }

        $oldData = $this->find($id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM languages WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        if (!$this->hasColumn('id')) {
            return;
        }

        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (1, :userId, 'language', :entityId, :action, :changes, :ip, NOW())
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':entityId' => $entityId,
            ':action' => $action,
            ':changes' => $changes,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
