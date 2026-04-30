<?php
declare(strict_types=1);

// api/v1/models/categories/repositories/PdoTenantCategoriesRepository.php

final class PdoTenantCategoriesRepository extends BaseRepository
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Get all tenant categories with pagination and filters
     *
     * @param  int|null $tenantId   Preferred tenant ID (ignored if Context > 0)
     * @param  int|null $categoryId Filter by category
     * @param  int|null $isActive   Filter by status
     * @param  int      $offset
     * @param  int|null $limit
     * @param  string   $lang
     * @return array
     */
    public function all(?int $tenantId = null, ?int $categoryId = null, ?int $isActive = null, int $offset = 0, int $limit = null, string $lang = 'ar'): array
    {
        $contextTenantId = $this->getTenantId();
        
        // 🔒 SECURITY: Enforce tenant isolation. 
        // If Context is > 0, we MUST filter by that tenant.
        // Only if Context is 0 (Platform Admin) do we allow the filter to choose the tenant (or 0 for all).
        if ($contextTenantId > 0) {
            $effectiveTenantId = $contextTenantId;
        } else {
            $effectiveTenantId = ($tenantId !== null && $tenantId > 0) ? $tenantId : 0;
        }

        $sql = "SELECT tc.*, t.name AS tenant_name,
                       COALESCE(
                           (SELECT name FROM category_translations WHERE category_id = c.id AND language_code = :lang LIMIT 1),
                           (SELECT name FROM category_translations WHERE category_id = c.id ORDER BY language_code LIMIT 1),
                           c.name
                       ) AS category_name
                FROM tenant_categories tc
                LEFT JOIN tenants t ON tc.tenant_id = t.id
                LEFT JOIN categories c ON tc.category_id = c.id
                WHERE 1=1";

        $params = [':lang' => $lang];

        if ($effectiveTenantId > 0) {
            $sql .= " AND tc.tenant_id = :tenantId";
            $params[':tenantId'] = $effectiveTenantId;
        }

        if ($categoryId !== null) {
            $sql .= " AND tc.category_id = :categoryId";
            $params[':categoryId'] = $categoryId;
        }

        if ($isActive !== null) {
            $sql .= " AND tc.is_active = :isActive";
            $params[':isActive'] = $isActive;
        }

        $sql .= " ORDER BY tc.sort_order ASC, tc.created_at DESC";

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total categories matching filters (for pagination)
     */
    public function count(?int $tenantId = null, ?int $categoryId = null, ?int $isActive = null): int
    {
        $contextTenantId = $this->getTenantId();
        
        if ($contextTenantId > 0) {
            $effectiveTenantId = $contextTenantId;
        } else {
            $effectiveTenantId = ($tenantId !== null && $tenantId > 0) ? $tenantId : 0;
        }

        $sql = "SELECT COUNT(*) FROM tenant_categories tc WHERE 1=1";
        $params = [];

        if ($effectiveTenantId > 0) {
            $sql .= " AND tc.tenant_id = :tenantId";
            $params[':tenantId'] = $effectiveTenantId;
        }

        if ($categoryId !== null) {
            $sql .= " AND tc.category_id = :categoryId";
            $params[':categoryId'] = $categoryId;
        }

        if ($isActive !== null) {
            $sql .= " AND tc.is_active = :isActive";
            $params[':isActive'] = $isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id, string $lang = 'ar'): ?array
    {
        $contextTenantId = $this->getTenantId();
        
        $sql = "SELECT tc.*, t.name AS tenant_name,
                       COALESCE(
                           (SELECT name FROM category_translations WHERE category_id = c.id AND language_code = :lang LIMIT 1),
                           (SELECT name FROM category_translations WHERE category_id = c.id ORDER BY language_code LIMIT 1),
                           c.name
                       ) AS category_name
                FROM tenant_categories tc
                LEFT JOIN tenants t ON tc.tenant_id = t.id
                LEFT JOIN categories c ON tc.category_id = c.id
                WHERE tc.id = :id";
        
        $params = [':id' => $id, ':lang' => $lang];

        if ($contextTenantId > 0) {
            $sql .= " AND tc.tenant_id = :tenantId";
            $params[':tenantId'] = $contextTenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            // Verify ownership if tenant-scoped
            $existing = $this->find((int)$data['id']);
            if (!$existing) {
                throw new RuntimeException('Tenant Category not found or access denied');
            }

            $updateFields = [];
            $params = [':id' => $data['id']];

            if (isset($data['tenant_id'])) {
                $updateFields[] = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $data['tenant_id'];
            }
            if (isset($data['category_id'])) {
                $updateFields[] = "category_id = :category_id";
                $params[':category_id'] = $data['category_id'];
            }
            if (isset($data['is_active'])) {
                $updateFields[] = "is_active = :is_active";
                $params[':is_active'] = $data['is_active'];
            }
            if (isset($data['sort_order'])) {
                $updateFields[] = "sort_order = :sort_order";
                $params[':sort_order'] = $data['sort_order'];
            }

            if (empty($updateFields)) {
                return (int)$data['id'];
            }

            $sql = "UPDATE tenant_categories SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO tenant_categories (tenant_id, category_id, is_active, sort_order, created_at)
                VALUES (:tenant_id, :category_id, :is_active, :sort_order, NOW())
            ");
            $stmt->execute([
                ':tenant_id'   => $data['tenant_id'],
                ':category_id' => $data['category_id'],
                ':is_active'   => $data['is_active'] ?? 1,
                ':sort_order'  => $data['sort_order'] ?? 0,
            ]);
            return (int)$this->pdo->lastInsertId();
        }
    }

    public function getDescendantIds(array $rootIds): array
    {
        if (empty($rootIds)) {
            return [];
        }

        $all = array_flip($rootIds);
        $queue = $rootIds;

        while (!empty($queue)) {
            $placeholders = implode(',', array_fill(0, count($queue), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id FROM categories WHERE parent_id IN ($placeholders) AND (is_active IS NULL OR is_active = 1)"
            );
            $stmt->execute(array_values($queue));
            $children = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            $queue = [];
            foreach ($children as $childId) {
                $childId = (int)$childId;
                if (!isset($all[$childId])) {
                    $all[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($all);
    }

    public function findMissingCategoryIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
        return array_values(array_diff($ids, $found));
    }

    public function syncForTenant(int $tenantId, array $categoryIds, int $isActive = 1): array
    {
        // 🔒 Verify context
        $contextTenantId = $this->getTenantId();
        if ($contextTenantId > 0 && $contextTenantId !== $tenantId) {
            throw new RuntimeException('Access denied to sync categories for another tenant');
        }

        $this->pdo->beginTransaction();
        try {
            $removed = 0;
            $added   = 0;

            if (empty($categoryIds)) {
                $stmt = $this->pdo->prepare("DELETE FROM tenant_categories WHERE tenant_id = :tid");
                $stmt->execute([':tid' => $tenantId]);
                $removed = $stmt->rowCount();
            } else {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $params = array_merge([$tenantId], array_values($categoryIds));
                $stmt = $this->pdo->prepare(
                    "DELETE FROM tenant_categories WHERE tenant_id = ? AND category_id NOT IN ($placeholders)"
                );
                $stmt->execute($params);
                $removed = $stmt->rowCount();

                $stmt = $this->pdo->prepare(
                    "SELECT category_id FROM tenant_categories WHERE tenant_id = ? AND category_id IN ($placeholders)"
                );
                $stmt->execute($params);
                $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
                $toInsert = array_values(array_diff($categoryIds, $existing));

                if (!empty($toInsert)) {
                    $rowPlaceholders = implode(',', array_fill(0, count($toInsert), '(?,?,?,?,NOW())'));
                    $insertParams = [];
                    foreach ($toInsert as $catId) {
                        $insertParams[] = $tenantId;
                        $insertParams[] = $catId;
                        $insertParams[] = $isActive;
                        $insertParams[] = 0;
                    }
                    $stmt = $this->pdo->prepare(
                        "INSERT IGNORE INTO tenant_categories (tenant_id, category_id, is_active, sort_order, created_at)
                         VALUES $rowPlaceholders"
                    );
                    $stmt->execute($insertParams);
                    $added = $stmt->rowCount();
                }
            }

            $this->pdo->commit();
            return ['added' => $added, 'removed' => $removed];
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $contextTenantId = $this->getTenantId();
        
        $sql = "DELETE FROM tenant_categories WHERE id = :id";
        $params = [':id' => $id];

        if ($contextTenantId > 0) {
            $sql .= " AND tenant_id = :tenantId";
            $params[':tenantId'] = $contextTenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}