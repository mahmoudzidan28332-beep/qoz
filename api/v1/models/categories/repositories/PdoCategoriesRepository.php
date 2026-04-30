<?php
declare(strict_types=1);

require_once __DIR__ . '/PdoCategoriesQueryTrait.php';

final class PdoCategoriesRepository implements CategoriesRepositoryInterface
{
    use PdoCategoriesQueryTrait;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /* ============================================================
     * GET ALL CATEGORIES
     *
     * parent_id values:
     *   null / 0  → roots فقط (السلوك الافتراضي، ما لم يوجد search)
     *   -1        → كل الفئات بدون فلتر (show_all bypass)
     *   > 0       → الفئة المحددة + أبناؤها المباشرون
     * ============================================================ */
    public function all(
        ?int $tenantId,
        ?int $parentId = null,
        bool $featuredOnly = false,
        string $lang = 'en',
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 50,
        int $offset = 0,
        bool $skipTcFilter = false
    ): array {
        $isSuperAdmin   = ($tenantId === null);
        $hasAssignments = (!$isSuperAdmin && !$skipTcFilter)
            ? $this->hasTenantCategoryAssignments($tenantId)
            : false;

        $sql = "
            SELECT
                c.id, c.tenant_id, c.parent_id, c.sort_order, c.is_active, c.is_featured,
                c.created_at, c.updated_at,
                COALESCE(ct.name,             c.name)        AS name,
                COALESCE(ct.description,      c.description) AS description,
                COALESCE(ct.slug,             c.slug)        AS slug,
                COALESCE(ct.meta_title,       '')            AS meta_title,
                COALESCE(ct.meta_description, '')            AS meta_description,
                COALESCE(ct.meta_keywords,    '')            AS meta_keywords,
                i.id        AS image_id,
                i.url       AS image_url,
                i.thumb_url AS image_thumb_url,
                p.name      AS parent_name
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN categories p
                ON c.parent_id = p.id
            LEFT JOIN image_types it_cat
                ON it_cat.name = 'category'
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = it_cat.id
               AND i.is_main = 1
        ";
        $params = [':lang' => $lang];

        // ── Tenant JOIN ──────────────────────────────────────────
        if ($hasAssignments) {
            $sql .= "
            LEFT JOIN tenant_categories tc_assign
                ON c.id = tc_assign.category_id
               AND tc_assign.tenant_id = :tenantId_tc
               AND tc_assign.is_active = 1
            ";
            $params[':tenantId_tc'] = $tenantId;
        }

        // ── WHERE base ───────────────────────────────────────────
        if ($isSuperAdmin) {
            $sql .= " WHERE 1=1";
        } elseif ($hasAssignments) {
            $sql .= " WHERE (c.tenant_id = :tenantId OR c.tenant_id IS NULL OR tc_assign.category_id IS NOT NULL)";
            $params[':tenantId'] = $tenantId;
        } else {
            $sql .= " WHERE (c.tenant_id = :tenantId OR c.tenant_id IS NULL)";
            $params[':tenantId'] = $tenantId;
        }

        // ── Parent / Hierarchy filter ─────────────────────────────
        // -1  → show_all bypass: لا فلترة على parent_id
        //  0 / null → roots فقط (ما لم يوجد search)
        // > 0 → فئة محددة + أبناؤها المباشرون
        if ($parentId === -1) {
            // لا شيء — عرض كل الفئات
        } elseif ($parentId === null || $parentId === 0) {
            if (empty($search)) {
                $sql .= " AND (c.parent_id IS NULL OR c.parent_id = 0)";
            }
        } else {
            $sql .= " AND (c.id = :parentId1 OR c.parent_id = :parentId2)";
            $params[':parentId1'] = $parentId;
            $params[':parentId2'] = $parentId;
        }

        // ── Optional filters ──────────────────────────────────────
        if ($featuredOnly) {
            $sql .= " AND c.is_featured = 1";
        }

        if ($isActive !== null) {
            $sql .= " AND c.is_active = :is_active";
            $params[':is_active'] = $isActive ? 1 : 0;
        }

        if (!empty($search)) {
            $sql .= "
                AND (
                    c.name LIKE :search1
                    OR c.slug LIKE :search2
                    OR EXISTS (
                        SELECT 1 FROM category_translations ct2
                        WHERE ct2.category_id = c.id
                          AND ct2.name LIKE :search3
                    )
                )";
            $searchVal             = "%{$search}%";
            $params[':search1']    = $searchVal;
            $params[':search2']    = $searchVal;
            $params[':search3']    = $searchVal;
        }

        $sql .= " ORDER BY c.sort_order ASC, c.id ASC";

        if ($limit > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit']  = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = in_array($key, [':limit', ':offset', ':is_active'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * GET CATEGORY BY ID
     * ============================================================ */
    public function findById(?int $tenantId, int $id): ?array
    {
        if ($tenantId === null) {
            $stmt = $this->pdo->prepare(
                "SELECT id, tenant_id, parent_id, slug, name, description, sort_order, is_active, is_featured, created_at, updated_at FROM categories WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, tenant_id, parent_id, slug, name, description, sort_order, is_active, is_featured, created_at, updated_at FROM categories
                WHERE (tenant_id = :tenantId OR tenant_id IS NULL) AND id = :id
                LIMIT 1
            ");
            $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByIdWithTranslations(?int $tenantId, int $id): ?array
    {
        $row = $this->findById($tenantId, $id);
        if (!$row) return null;

        $row['translations'] = $this->getTranslations($id);
        $row['image']        = $this->getMainImage($tenantId, $id);

        return $row;
    }

    /* ============================================================
     * CREATE / UPDATE CATEGORY
     * ============================================================ */
    public function save(?int $tenantId, array $data, ?int $userId = null): int
    {
        if ($tenantId === null) {
            if (array_key_exists('tenant_id', $data) && $data['tenant_id'] === null) {
                $tenantId = null;
            } elseif (!empty($data['tenant_id'])) {
                $tenantId = (int) $data['tenant_id'];
            } else {
                $tenantId = null;
            }
        }

        $isUpdate = !empty($data['id']);
        $oldData  = $isUpdate ? $this->findByIdWithTranslations($tenantId, (int) $data['id']) : null;

        $this->pdo->beginTransaction();
        try {
            $categoryId = $isUpdate
                ? $this->updateCategory($tenantId, $data)
                : $this->insertCategory($tenantId, $data);

            $this->saveCategoryRelations($tenantId, $categoryId, $data);

            if ($userId) { $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $categoryId, $oldData, $data); }

            $this->pdo->commit();
            return $categoryId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    private function updateCategory(?int $tenantId, array $data): int
    {
        $sql = "UPDATE categories SET parent_id = :parent_id, slug = :slug, name = :name, description = :description, sort_order = :sort_order, is_active = :is_active, is_featured = :is_featured, updated_at = NOW() WHERE id = :id";
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenantId";
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        $params = [
            ':parent_id'   => $data['parent_id'] ?? null,
            ':slug'        => $data['slug'],
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
            ':sort_order'  => (int)($data['sort_order'] ?? 0),
            ':is_active'   => (int)($data['is_active'] ?? 1),
            ':is_featured' => (int)($data['is_featured'] ?? 0),
            ':id'          => (int)$data['id']
        ];
        
        if ($tenantId !== null) {
            $params[':tenantId'] = $tenantId;
        }
        
        $stmt->execute($params);
        return (int)$data['id'];
    }

    private function insertCategory(?int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO categories (tenant_id, parent_id, slug, name, description, sort_order, is_active, is_featured, created_at) VALUES (:tenantId, :parent_id, :slug, :name, :description, :sort_order, :is_active, :is_featured, NOW())");
        $stmt->execute([':tenantId' => $tenantId, ':parent_id' => $data['parent_id'] ?? null, ':slug' => $data['slug'], ':name' => $data['name'], ':description' => $data['description'] ?? null, ':sort_order' => (int)($data['sort_order'] ?? 0), ':is_active' => (int)($data['is_active'] ?? 1), ':is_featured' => (int)($data['is_featured'] ?? 0)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function saveCategoryRelations(?int $tenantId, int $categoryId, array $data): void
    {
        if (!empty($data['image_id'])) {
            $sql = "UPDATE images SET owner_id = :owner_id, is_main = 1 WHERE id = :image_id";
            if ($tenantId === null) {
                // If it's a global category, the image should probably also be global (tenant_id IS NULL)
                // But it's safer to just link it by owner_id and image_id if we assume the image belongs to the global pool.
                $sql .= " AND tenant_id IS NULL";
            } else {
                $sql .= " AND tenant_id = :tenantId";
            }
            $stmt = $this->pdo->prepare($sql);
            
            $params = [
                ':owner_id' => $categoryId,
                ':image_id' => (int)$data['image_id']
            ];
            
            if ($tenantId !== null) {
                $params[':tenantId'] = $tenantId;
            }
            
            $stmt->execute($params);
        }
        if (isset($data['translations']) && is_array($data['translations'])) { $this->saveTranslations($categoryId, $data['translations']); }
        if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
            $langs = array_filter(array_column($data['deleted_translations'], 'language_code'));
            if (!empty($langs)) {
                $placeholders = [];
                $params = [':cat_id' => $categoryId];
                foreach ($langs as $i => $lang) {
                    $placeholders[] = ":lang_{$i}";
                    $params[":lang_{$i}"] = $lang;
                }
                $sql = "DELETE FROM category_translations WHERE category_id = :cat_id AND language_code IN (" . implode(',', $placeholders) . ")";
                $this->pdo->prepare($sql)->execute($params);
            }
        }
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function delete(?int $tenantId, int $categoryId, ?int $userId = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $oldData = $this->findByIdWithTranslations($tenantId, $categoryId);
            if (!$oldData) {
                $this->pdo->rollBack();
                return false;
            }

            // حذف الترجمات
            $this->pdo->prepare("
                DELETE FROM category_translations WHERE category_id = :categoryId
            ")->execute([':categoryId' => $categoryId]);

            // حذف الصور
            $this->pdo->prepare("
                DELETE FROM images
                WHERE owner_id = :categoryId
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
            ")->execute([':categoryId' => $categoryId]);

            // حذف الفئة
            $sql = "DELETE FROM categories WHERE id = :categoryId";
            $params = [':categoryId' => $categoryId];
            
            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tenantId";
                $params[':tenantId'] = $tenantId;
            }
            
            $this->pdo->prepare($sql)->execute($params);

            $logTenantId = $tenantId ?? (int) ($oldData['tenant_id'] ?? 0);

            if ($userId) {
                $this->logAction($logTenantId, $userId, 'delete', $categoryId, $oldData, null);
            }

            $this->pdo->commit();
            return true;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException($e->getMessage(), ['sqlstate' => $e->getCode()], $e);
        }
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(int $categoryId, string $lang): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM category_translations
            WHERE category_id = :categoryId AND language_code = :lang
        ");
        return $stmt->execute([':categoryId' => $categoryId, ':lang' => $lang]);
    }

    /* ============================================================
     * TRANSLATIONS
     * ============================================================ */
    public function saveTranslations(int $categoryId, array $translations): void
    {
        $normalized = [];
        foreach ($translations as $key => $trans) {
            if (!is_array($trans)) {
                continue;
            }

            $languageCode = $trans['language_code'] ?? (is_string($key) ? $key : null);
            $languageCode = is_string($languageCode) ? trim($languageCode) : '';
            if ($languageCode === '') {
                continue;
            }

            $trans['language_code'] = $languageCode;
            $normalized[] = $trans;
        }

        $translations = $normalized;

        if (empty($translations)) {
            return;
        }

        $values = [];
        $params = [];
        $i = 0;
        foreach ($translations as $trans) {
            $values[] = "(:category_id_{$i}, :lang_{$i}, :name_{$i}, :description_{$i}, :slug_{$i}, :meta_title_{$i}, :meta_description_{$i}, :meta_keywords_{$i})";
            $params[":category_id_{$i}"]      = $categoryId;
            $params[":lang_{$i}"]             = $trans['language_code'];
            $params[":name_{$i}"]             = $trans['name']             ?? null;
            $params[":description_{$i}"]      = $trans['description']      ?? null;
            $params[":slug_{$i}"]             = $trans['slug']             ?? null;
            $params[":meta_title_{$i}"]       = $trans['meta_title']       ?? null;
            $params[":meta_description_{$i}"] = $trans['meta_description'] ?? null;
            $params[":meta_keywords_{$i}"]    = $trans['meta_keywords']    ?? null;
            $i++;
        }

        $sql = "INSERT INTO category_translations (category_id, language_code, name, description, slug, meta_title, meta_description, meta_keywords) VALUES "
             . implode(', ', $values)
             . " ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), slug = VALUES(slug), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), meta_keywords = VALUES(meta_keywords)";
        $this->pdo->prepare($sql)->execute($params);
    }

    public function getTranslations(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description, slug,
                   meta_title, meta_description, meta_keywords
            FROM category_translations
            WHERE category_id = :id
        ");
        $stmt->execute([':id' => $categoryId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['language_code']] = [
                'name'             => $r['name'],
                'description'      => $r['description'],
                'slug'             => $r['slug'],
                'meta_title'       => $r['meta_title'],
                'meta_description' => $r['meta_description'],
                'meta_keywords'    => $r['meta_keywords'],
            ];
        }
        return $out;
    }

    /* ============================================================
     * IMAGES
     * ============================================================ */
    public function getMainImage(?int $tenantId, int $categoryId): ?array
    {
        if ($tenantId === null) {
            $stmt = $this->pdo->prepare("
                SELECT id, url, thumb_url FROM images
                WHERE owner_id = :owner_id
                  AND is_main = 1
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
                LIMIT 1
            ");
            $stmt->execute([':owner_id' => $categoryId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, url, thumb_url FROM images
                WHERE tenant_id = :tenantId
                  AND owner_id  = :owner_id
                  AND is_main   = 1
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
                LIMIT 1
            ");
            $stmt->execute([':tenantId' => $tenantId, ':owner_id' => $categoryId]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findIdBySlug(?int $tenantId, string $slug): ?int
    {
        if ($tenantId === null) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM categories WHERE slug = :slug LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id FROM categories
                WHERE (tenant_id = :tenantId OR tenant_id IS NULL) AND slug = :slug
                LIMIT 1
            ");
            $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['id'] : null;
    }

    public function bulkUpdateStatus(?int $tenantId, array $ids, bool $isActive, ?int $userId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($tenantId === null) {
            $params = array_merge([$isActive ? 1 : 0], $ids);
            $sql = "UPDATE categories SET is_active = ?, updated_at = NOW() WHERE id IN ($placeholders)";
        } else {
            $params = array_merge([$isActive ? 1 : 0, $tenantId], $ids);
            $sql = "UPDATE categories SET is_active = ?, updated_at = NOW() WHERE (tenant_id = ? OR tenant_id IS NULL) AND id IN ($placeholders)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function slugExists(?int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        if ($tenantId === null) {
            $sql    = "SELECT COUNT(*) AS count FROM categories WHERE slug = :slug";
            $params = [':slug' => $slug];
        } else {
            $sql    = "SELECT COUNT(*) AS count FROM categories WHERE (tenant_id = :tenantId OR tenant_id IS NULL) AND slug = :slug";
            $params = [':tenantId' => $tenantId, ':slug' => $slug];
        }

        if ($excludeId) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ((int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0)) > 0;
    }

    public function hasChildren(int $categoryId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS count FROM categories WHERE parent_id = :categoryId"
        );
        $stmt->execute([':categoryId' => $categoryId]);
        return ((int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0)) > 0;
    }
}
