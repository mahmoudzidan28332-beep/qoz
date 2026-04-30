<?php
declare(strict_types=1);

final class PdoBrandsRepository extends BaseRepository
{
    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'entity_id', 'slug', 'is_active', 'is_featured', 'sort_order', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'tenant_id', 'entity_id', 'is_active', 'is_featured', 'slug'
    ];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * جلب قائمة العلامات التجارية مع الفلاتر والترتيب والصفحات
     */
    public function all(
        ?int $tenantId = null,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'en'
    ): array {
        $contextTenantId = $this->getTenantId();
        
        // 🔒 SECURITY: Enforce tenant isolation.
        if ($contextTenantId > 0) {
            $effectiveTenantId = $contextTenantId;
        } else {
            $effectiveTenantId = ($tenantId !== null && $tenantId > 0) ? $tenantId : 0;
        }

        $sql = "
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        if ($effectiveTenantId > 0) {
            $sql .= " AND b.tenant_id = :tenant_id";
            $params[':tenant_id'] = $effectiveTenantId;
        }

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'tenant_id') continue;
                if ($col === 'slug') {
                    $sql .= " AND b.slug LIKE :slug";
                    $params[':slug'] = '%' . $filters['slug'] . '%';
                } elseif (in_array($col, ['is_active', 'is_featured'])) {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                } else {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY b.{$orderBy} {$orderDir}";

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$limit;
            $params[':offset'] = (int)($offset ?? 0);
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(?int $tenantId = null, array $filters = []): int
    {
        $contextTenantId = $this->getTenantId();
        
        if ($contextTenantId > 0) {
            $effectiveTenantId = $contextTenantId;
        } else {
            $effectiveTenantId = ($tenantId !== null && $tenantId > 0) ? $tenantId : 0;
        }

        $sql = "SELECT COUNT(*) FROM brands b WHERE 1=1";
        $params = [];

        if ($effectiveTenantId > 0) {
            $sql .= " AND b.tenant_id = :tenant_id";
            $params[':tenant_id'] = $effectiveTenantId;
        }

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'tenant_id') continue;
                if ($col === 'slug') {
                    $sql .= " AND b.slug LIKE :slug";
                    $params[':slug'] = '%' . $filters['slug'] . '%';
                } elseif (in_array($col, ['is_active', 'is_featured'])) {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                } else {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * العثور على علامة تجارية بواسطة slug مع الترجمات
     */
    public function find(string $slug, string $lang = 'en', bool $allTranslations = false): ?array
    {
        $contextTenantId = $this->getTenantId();

        $sql = "
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.slug = :slug
        ";
        
        $params = [':lang' => $lang, ':slug' => $slug];

        if ($contextTenantId > 0) {
            $sql .= " AND b.tenant_id = :tenantId";
            $params[':tenantId'] = $contextTenantId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $allTranslations) {
            $row['translations'] = $this->getTranslationsAsArray((int)$row['id']);
        }

        return $row ?: null;
    }

    /**
     * العثور على علامة تجارية بواسطة ID مع الترجمات
     */
    public function findById(int $id, string $lang = 'en', bool $allTranslations = false): ?array
    {
        $contextTenantId = $this->getTenantId();

        $sql = "
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.id = :id
        ";
        
        $params = [':id' => $id, ':lang' => $lang];

        if ($contextTenantId > 0) {
            $sql .= " AND b.tenant_id = :tenantId";
            $params[':tenantId'] = $contextTenantId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $allTranslations) {
            $row['translations'] = $this->getTranslationsAsArray((int)$row['id']);
        }

        return $row ?: null;
    }

    /**
     * جلب جميع الترجمات كمصفوفة (لكل لغة عنصر مع language_code)
     */
    public function getTranslationsAsArray(int $brandId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description, meta_title, meta_description
            FROM brand_translations
            WHERE brand_id = :brand_id
        ");
        $stmt->execute([':brand_id' => $brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * حفظ (إدراج أو تحديث) علامة تجارية
     */
    public function save(array $data, ?int $userId = null): int
    {
        $contextTenantId = $this->getTenantId();
        $isUpdate = !empty($data['id']);
        
        if ($isUpdate) {
            $existing = $this->findById((int)$data['id']);
            if (!$existing) {
                throw new RuntimeException('Brand not found or access denied');
            }
            $id = (int)$data['id'];
            $targetTenantId = (int)$existing['tenant_id'];
        } else {
            $targetTenantId = ($contextTenantId > 0) ? $contextTenantId : (int)($data['tenant_id'] ?? 0);
            if ($targetTenantId <= 0) {
                throw new InvalidArgumentException('Valid tenant_id is required for new brands');
            }
        }

        $fields = [
            'tenant_id'    => $targetTenantId,
            'entity_id'    => $data['entity_id']    ?? null,
            'slug'         => $data['slug']         ?? null,
            'website_url'  => $data['website_url']  ?? null,
            'is_active'    => (int)($data['is_active'] ?? 1),
            'is_featured'  => (int)($data['is_featured'] ?? 0),
            'sort_order'   => (int)($data['sort_order'] ?? 0),
        ];

        if ($isUpdate) {
            $sets = [];
            $params = [':id' => $id, ':tenantId' => $targetTenantId];
            foreach ($fields as $col => $val) {
                if (array_key_exists($col, $data)) {
                    if ($col === 'tenant_id') continue;
                    $sets[] = "$col = :$col";
                    $params[":$col"] = $val;
                }
            }
            $sets[] = "updated_at = NOW()";

            $sql = "UPDATE brands SET " . implode(', ', $sets) . " WHERE tenant_id = :tenantId AND id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $cols = array_keys($fields);
            $placeholders = array_map(fn($c) => ":$c", $cols);
            $sql = "INSERT INTO brands (" . implode(', ', $cols) . ", created_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($fields);
            $id = (int)$this->pdo->lastInsertId();
        }

        // حفظ الترجمات
        if (!empty($data['translations'])) {
            $this->saveTranslations($id, $data['translations']);
        }

        // حذف الترجمات المحددة (deleted_translations)
        if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
            $this->deleteTranslations($id, $data['deleted_translations']);
        }

        if ($userId) {
            $this->logAction($targetTenantId, $userId, $isUpdate ? 'update' : 'create', $id, $isUpdate ? $existing : null, $data);
        }

        return $id;
    }

    /**
     * حذف الترجمات المحددة
     */
    private function deleteTranslations(int $brandId, array $deletedTranslations): void
    {
        $langs = array_filter(array_column($deletedTranslations, 'language_code'));
        if (empty($langs)) return;

        $placeholders = [];
        $params = [':brand_id' => $brandId];
        foreach ($langs as $i => $lang) {
            $placeholders[] = ":lang_{$i}";
            $params[":lang_{$i}"] = $lang;
        }

        $sql = "DELETE FROM brand_translations WHERE brand_id = :brand_id AND language_code IN (" . implode(',', $placeholders) . ")";
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * حذف بواسطة slug
     */
    public function delete(string $slug, ?int $userId = null): bool
    {
        $existing = $this->find($slug);
        if (!$existing) return false;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $existing['id']]);

            $stmt = $this->pdo->prepare("DELETE FROM brands WHERE id = :id");
            $result = $stmt->execute([':id' => $existing['id']]);

            if ($userId) {
                $this->logAction((int)$existing['tenant_id'], $userId, 'delete', (int)$existing['id'], $existing, null);
            }
            $this->pdo->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * حذف بواسطة ID
     */
    public function deleteById(int $id, ?int $userId = null): bool
    {
        $existing = $this->findById($id);
        if (!$existing) return false;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $id]);

            $stmt = $this->pdo->prepare("DELETE FROM brands WHERE id = :id");
            $result = $stmt->execute([':id' => $id]);

            if ($userId) {
                $this->logAction((int)$existing['tenant_id'], $userId, 'delete', $id, $existing, null);
            }
            $this->pdo->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * حفظ الترجمات (إدراج أو تحديث)
     */
    public function saveTranslations(int $brandId, array $translations): void
    {
        if (empty($translations)) return;

        $values = [];
        $params = [];
        $i = 0;
        foreach ($translations as $lang => $data) {
            $values[] = "(:brand_id_{$i}, :lang_{$i}, :name_{$i}, :description_{$i}, :meta_title_{$i}, :meta_description_{$i})";
            $params[":brand_id_{$i}"]         = $brandId;
            $params[":lang_{$i}"]             = $lang;
            $params[":name_{$i}"]             = $data['name']             ?? null;
            $params[":description_{$i}"]      = $data['description']      ?? null;
            $params[":meta_title_{$i}"]       = $data['meta_title']       ?? null;
            $params[":meta_description_{$i}"] = $data['meta_description'] ?? null;
            $i++;
        }

        $sql = "INSERT INTO brand_translations (brand_id, language_code, name, description, meta_title, meta_description) VALUES "
             . implode(', ', $values)
             . " ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description)";
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * تسجيل العملية في سجل الأحداث
     */
    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
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
            VALUES (:tenantId, :userId, 'brand', :entityId, :action, :changes, :ip, NOW())
        ");
        $stmt->execute([
            ':tenantId' => $tenantId,
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}