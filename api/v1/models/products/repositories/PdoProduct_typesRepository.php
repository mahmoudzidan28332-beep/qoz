<?php
declare(strict_types=1);

final class PdoProduct_typesRepository
{
    private PDO $pdo;

    private const TABLE = 'product_types';
    private const ALLOWED_ORDER_BY = ['id', 'code', 'name', 'is_active'];
    private const ALLOWED_DIRECTIONS = ['ASC', 'DESC'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List all product types with multilingual support - SQL INJECTION FREE
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $lang = $filters['lang'] ?? $filters['language'] ?? 'en';
        
        $sql = "
            SELECT 
                pt.id, 
                pt.code, 
                pt.description, 
                pt.is_active,
                COALESCE(ptt.name, pt.name) AS name
            FROM " . self::TABLE . " pt
            LEFT JOIN product_type_translations ptt 
                ON ptt.product_type_id = pt.id AND ptt.language_code = :lang
        ";

        $whereClause = '';
        $params = [':lang' => $lang];
        $conditions = $this->buildWhereConditions($filters, $params);
        
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= $whereClause;

        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }
        
        if ($orderBy === 'name') {
            $orderBy = 'COALESCE(ptt.name, pt.name)';
        }
        
        $orderDir = strtoupper($orderDir);
        if (!in_array($orderDir, self::ALLOWED_DIRECTIONS, true)) {
            $orderDir = 'DESC';
        }
        
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset ?? 0;
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhereConditions(array $filters, array &$params): array
    {
        $conditions = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(pt.code LIKE :search OR pt.name LIKE :search OR COALESCE(ptt.name, pt.name) LIKE :search OR pt.description LIKE :search)';
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        if (isset($filters['is_active'])) {
            $conditions[] = 'pt.is_active = :is_active';
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (!empty($filters['id'])) {
            $conditions[] = 'pt.id = :id';
            $params[':id'] = (int)$filters['id'];
        }

        if (!empty($filters['code'])) {
            $conditions[] = 'pt.code = :code';
            $params[':code'] = $filters['code'];
        }

        return $conditions;
    }

    public function count(array $filters = []): int
    {
        $lang = $filters['lang'] ?? $filters['language'] ?? 'en';
        $params = [':lang' => $lang];
        
        $sql = "
            SELECT COUNT(DISTINCT pt.id) 
            FROM " . self::TABLE . " pt
            LEFT JOIN product_type_translations ptt 
                ON ptt.product_type_id = pt.id AND ptt.language_code = :lang
        ";
        
        $conditions = $this->buildWhereConditions($filters, $params);
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id, ?string $lang = null): ?array
    {
        $lang = $lang ?? 'en';
        
        $sql = "
            SELECT 
                pt.id, 
                pt.code, 
                pt.description, 
                pt.is_active,
                COALESCE(ptt.name, pt.name) AS name
            FROM " . self::TABLE . " pt
            LEFT JOIN product_type_translations ptt 
                ON ptt.product_type_id = pt.id AND ptt.language_code = :lang
            WHERE pt.id = :id
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':lang', $lang, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByIds(array $ids, ?string $lang = null): array
    {
        if (empty($ids)) {
            return [];
        }
        
        $lang = $lang ?? 'en';
        
        // Generate placeholders for each ID
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "
            SELECT 
                pt.id, 
                pt.code, 
                pt.description, 
                pt.is_active,
                COALESCE(ptt.name, pt.name) AS name
            FROM " . self::TABLE . " pt
            LEFT JOIN product_type_translations ptt 
                ON ptt.product_type_id = pt.id AND ptt.language_code = ?
            WHERE pt.id IN ({$placeholders})
        ";
        
        // Bind language + all IDs as parameters
        $params = array_merge([$lang], $ids);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['id']] = $row;
        }
        
        return $indexed;
    }

    public function findByCode(string $code, ?int $ignoreId = null): ?array
    {
        $sql = "SELECT id, code, name, description, is_active FROM " . self::TABLE . " WHERE code = :code";
        $params = [':code' => $code];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * ✅ CRITICAL FIX - Line 184 SQL Injection completely eliminated
     * Batch find multiple by codes - FULLY PARAMETERIZED with positional placeholders
     * NO string concatenation of user input
     */
    public function findByCodes(array $codes, ?int $ignoreId = null): array
    {
        if (empty($codes)) {
            return [];
        }
        
        // ✅ Step 1: Create safe placeholders (?, ?, ?) for each code
        // This is SAFE because we're generating placeholders, not interpolating values
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        
        // ✅ Step 2: Build SQL with placeholders only - NO values embedded
        $sql = "SELECT id, code, name, description, is_active FROM " . self::TABLE . " WHERE code IN ({$placeholders})";
        
        // ✅ Step 3: Prepare parameters array with the actual code values
        $params = $codes; // Values will be bound, not interpolated
        
        // ✅ Step 4: Add ignore ID condition if needed - with placeholder
        if ($ignoreId !== null) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }
        
        // ✅ Step 5: Execute with bound parameters - SQL injection impossible
        $stmt = $this->pdo->prepare($sql);
        
        // Bind each parameter with correct type
        foreach ($params as $index => $value) {
            // Positional parameters start at 1 in PDO
            $paramIndex = $index + 1;
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($paramIndex, $value, $paramType);
        }
        
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Re-index by code for easy lookup
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['code']] = $row;
        }
        
        return $indexed;
    }

    /**
     * Save (Insert or Update)
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if (!empty($data['code'])) {
            $exists = $this->findByCode($data['code'], $isUpdate ? (int)$data['id'] : null);
            if ($exists) {
                throw new RuntimeException('Product type code already exists');
            }
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET code = :code, name = :name, description = :description, is_active = :is_active
                WHERE id = :id
            ");
            
            $stmt->bindValue(':code', $data['code'], PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':is_active', (int)($data['is_active'] ?? 1), PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$data['id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if (!empty($data['translations'])) {
                $this->batchUpsertTranslations((int)$data['id'], $data['translations']);
            }
            
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (code, name, description, is_active)
            VALUES (:code, :name, :description, :is_active)
        ");
        
        $stmt->bindValue(':code', $data['code'], PDO::PARAM_STR);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', (int)($data['is_active'] ?? 1), PDO::PARAM_INT);
        $stmt->execute();
        
        $id = (int)$this->pdo->lastInsertId();
        
        if (!empty($data['translations'])) {
            $this->batchUpsertTranslations($id, $data['translations']);
        }
        
        return $id;
    }

    private function batchUpsertTranslations(int $productTypeId, array $translations): void
    {
        if (empty($translations)) {
            return;
        }
        
        $values = [];
        $params = [':product_type_id' => $productTypeId];
        $counter = 1;
        
        foreach ($translations as $langCode => $trans) {
            if (empty($langCode)) continue;
            
            $values[] = "(:product_type_id, :lang_{$counter}, :name_{$counter}, :desc_{$counter})";
            $params[":lang_{$counter}"] = $langCode;
            $params[":name_{$counter}"] = $trans['name'] ?? '';
            $params[":desc_{$counter}"] = $trans['description'] ?? null;
            $counter++;
        }
        
        if (empty($values)) {
            return;
        }
        
        $sql = "
            INSERT INTO product_type_translations (product_type_id, language_code, name, description)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}