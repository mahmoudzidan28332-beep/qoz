<?php
declare(strict_types=1);

final class PdoCountriesRepository
{
    private PDO $pdo;
    private const ALLOWED_COLS = ['iso2', 'iso3', 'name', 'currency_code'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List countries with optional filters and pagination.
     * Returns ['items'=>[], 'meta'=>[]]
     */
    public function list(array $filters = []): array
    {
        $lang = $filters['lang'] ?? $filters['language'] ?? 'en';
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $where = []; 
        $selectParams = []; 
        $countParams = [];
        
        $this->applyCountryFilters($filters, $lang, $where, $selectParams, $countParams);
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->countCountries($lang, $whereSql, $countParams);
        $items = $this->fetchCountries($lang, $whereSql, $selectParams, $perPage, $offset);

        $meta = [
            'total' => $total, 
            'page' => $page, 
            'per_page' => $perPage, 
            'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0
        ];
        
        return ['items' => $items, 'meta' => $meta];
    }

    private function applyCountryFilters(array $filters, ?string $lang, array &$where, array &$selectParams, array &$countParams): void
    {
        if (!empty($filters['id'])) { 
            $where[] = 'c.id = :id'; 
            $selectParams[':id'] = (int)$filters['id']; 
            $countParams[':id'] = (int)$filters['id']; 
        }
        if (!empty($filters['iso2'])) { 
            $where[] = 'c.iso2 = :iso2'; 
            $selectParams[':iso2'] = $filters['iso2']; 
            $countParams[':iso2'] = $filters['iso2']; 
        }
        if (!empty($filters['iso3'])) { 
            $where[] = 'c.iso3 = :iso3'; 
            $selectParams[':iso3'] = $filters['iso3']; 
            $countParams[':iso3'] = $filters['iso3']; 
        }
        if (!empty($filters['currency_code'])) { 
            $where[] = 'c.currency_code = :currency_code'; 
            $selectParams[':currency_code'] = $filters['currency_code']; 
            $countParams[':currency_code'] = $filters['currency_code']; 
        }
        if (!empty($filters['name'])) {
            $where[] = $lang ? '(c.name LIKE :name OR ct.name LIKE :name)' : 'c.name LIKE :name';
            $selectParams[':name'] = '%' . trim($filters['name']) . '%';
            $countParams[':name'] = '%' . trim($filters['name']) . '%';
        }
    }

    private function countCountries(?string $lang, string $whereSql, array $countParams): int
    {
        if ($lang) {
            $countParams[':_count_lang'] = $lang;
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT c.id) as total 
                 FROM countries c 
                 LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = :_count_lang 
                 {$whereSql}"
            );
            $stmt->execute($countParams);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM countries c {$whereSql}");
            $stmt->execute($countParams);
        }
        return (int)$stmt->fetchColumn();
    }

    private function fetchCountries(?string $lang, string $whereSql, array $selectParams, int $perPage, int $offset): array
    {
        // First, try to get with translation
        if ($lang) {
            $selectParams[':lang'] = $lang;
            $selectParams[':limit'] = $perPage;
            $selectParams[':offset'] = $offset;
            
            $sql = "
                SELECT 
                    c.id, 
                    c.iso2, 
                    c.iso3, 
                    c.name as base_name, 
                    c.currency_code,
                    COALESCE(ct.name, c.name) AS name
                FROM countries c 
                LEFT JOIN country_translations ct 
                    ON ct.country_id = c.id AND ct.language_code = :lang 
                {$whereSql} 
                ORDER BY COALESCE(ct.name, c.name) ASC 
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($selectParams);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Clean up - remove base_name if exists
            foreach ($items as &$it) {
                if (isset($it['base_name'])) {
                    unset($it['base_name']);
                }
            }
            
            return $items;
        }
        
        // No language specified - use base name
        $selectParams[':limit'] = $perPage;
        $selectParams[':offset'] = $offset;
        
        $sql = "
            SELECT 
                c.id, 
                c.iso2, 
                c.iso3, 
                c.name, 
                c.currency_code
            FROM countries c 
            {$whereSql} 
            ORDER BY c.name ASC 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($selectParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get country by id, optionally include translation for $lang.
     */
    public function getById(int $id, ?string $lang = null): ?array
    {
        $sql = "
            SELECT 
                c.id, 
                c.iso2, 
                c.iso3, 
                c.name as base_name, 
                c.currency_code
        ";
        
        if ($lang) {
            $sql .= ", COALESCE(ct.name, c.name) AS name";
        } else {
            $sql .= ", c.name";
        }
        
        $sql .= " FROM countries c";
        
        if ($lang) {
            $sql .= " LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = :lang";
        }
        
        $sql .= " WHERE c.id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($lang) {
            $stmt->bindValue(':lang', $lang, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }

        // Remove base_name if it exists
        if (isset($row['base_name'])) {
            unset($row['base_name']);
        }

        $row['translations'] = $this->getTranslations((int)$row['id']);
        
        return $row;
    }

    /**
     * Get country by iso2 or iso3 or name (first match)
     */
    public function getByIdentifier(string $identifier, ?string $lang = null): ?array
    {
        // numeric id
        if (ctype_digit($identifier)) {
            return $this->getById((int)$identifier, $lang);
        }

        // iso2 exact
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE iso2 = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) {
            return $this->getById((int)$id, $lang);
        }

        // iso3 exact
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE iso3 = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) {
            return $this->getById((int)$id, $lang);
        }

        // translation match if lang specified
        if ($lang) {
            $stmt = $this->pdo->prepare("
                SELECT country_id 
                FROM country_translations 
                WHERE language_code = :lang AND name = :v 
                LIMIT 1
            ");
            $stmt->execute([':lang' => $lang, ':v' => $identifier]);
            if ($id = $stmt->fetchColumn()) {
                return $this->getById((int)$id, $lang);
            }
        }

        // name match (base)
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE name = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) {
            return $this->getById((int)$id, $lang);
        }

        return null;
    }

    /**
     * Check if identifier exists
     */
    public function getByIdentifierExists(?string $identifier): bool
    {
        if (empty($identifier)) return false;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM countries WHERE iso2 = :v OR iso3 = :v");
        $stmt->execute([':v' => $identifier]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Insert country and its translations
     */
    public function insert(array $data): int
    {
        if (class_exists('SecurityValidators')) {
            $data = SecurityValidators::filterInput($data, self::ALLOWED_COLS);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO countries (iso2, iso3, name, currency_code)
            VALUES (:iso2, :iso3, :name, :currency_code)
        ");
        
        $stmt->execute([
            ':iso2' => $data['iso2'] ?? null,
            ':iso3' => $data['iso3'] ?? null,
            ':name' => $data['name'] ?? null,
            ':currency_code' => $data['currency_code'] ?? null
        ]);
        
        $id = (int)$this->pdo->lastInsertId();

        if (!empty($data['translations']) && is_array($data['translations'])) {
            $this->upsertTranslations($id, $data['translations']);
        }

        return $id;
    }

    /**
     * Update country
     */
    public function update(int $id, array $data): bool
    {
        if (class_exists('SecurityValidators')) {
            $data = SecurityValidators::filterInput($data, self::ALLOWED_COLS);
        }

        $stmt = $this->pdo->prepare("
            UPDATE countries SET
                iso2 = :iso2,
                iso3 = :iso3,
                name = :name,
                currency_code = :currency_code
            WHERE id = :id
        ");
        
        $ok = $stmt->execute([
            ':iso2' => $data['iso2'] ?? null,
            ':iso3' => $data['iso3'] ?? null,
            ':name' => $data['name'] ?? null,
            ':currency_code' => $data['currency_code'] ?? null,
            ':id' => $id
        ]);

        if (!empty($data['translations']) && is_array($data['translations'])) {
            $this->upsertTranslations($id, $data['translations']);
        }

        return (bool)$ok;
    }

    /**
     * Delete country
     */
    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM country_translations WHERE country_id = :id");
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare("DELETE FROM countries WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Get translations for a country
     */
    public function getTranslations(int $countryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name 
            FROM country_translations 
            WHERE country_id = :id
        ");
        $stmt->execute([':id' => $countryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upsert translations
     */
    private function upsertTranslations(int $countryId, array $translations): void
    {
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO country_translations (country_id, language_code, name)
            VALUES (:country_id, :language_code, :name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");

        foreach ($translations as $t) {
            if (empty($t['language_code']) || !isset($t['name'])) {
                continue;
            }
            $stmtInsert->execute([
                ':country_id' => $countryId,
                ':language_code' => $t['language_code'],
                ':name' => $t['name']
            ]);
        }
    }
}