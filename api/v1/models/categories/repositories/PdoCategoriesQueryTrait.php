<?php
declare(strict_types=1);

trait PdoCategoriesQueryTrait
{
    /* ============================================================
     * COUNT ALL (for pagination)
     *
     * نفس منطق parent_id المستخدم في all():
     *   -1   → كل الفئات
     *   null / 0 → roots فقط (ما لم يوجد search)
     *   > 0  → فئة محددة + أبناؤها
     * ============================================================ */
    public function countAll(?int $tenantId, array $filters = [], bool $skipTcFilter = false): int
    {
        $isSuperAdmin   = ($tenantId === null);
        $hasAssignments = (!$isSuperAdmin && !$skipTcFilter)
            ? $this->hasTenantCategoryAssignments($tenantId)
            : false;

        if ($hasAssignments) {
            $sql    = "
                SELECT COUNT(*) AS total
                FROM categories c
                LEFT JOIN tenant_categories tc_assign
                    ON c.id = tc_assign.category_id
                   AND tc_assign.tenant_id = :tenantId_tc
                   AND tc_assign.is_active = 1
                WHERE (c.tenant_id = :tenantId OR tc_assign.category_id IS NOT NULL)
            ";
            $params = [':tenantId' => $tenantId, ':tenantId_tc' => $tenantId];
        } elseif (!$isSuperAdmin) {
            $sql    = "SELECT COUNT(*) AS total FROM categories c WHERE c.tenant_id = :tenantId";
            $params = [':tenantId' => $tenantId];
        } else {
            $sql    = "SELECT COUNT(*) AS total FROM categories c WHERE 1=1";
            $params = [];
        }

        // ── Parent / Hierarchy filter ─────────────────────────────
        // ✅ FIX: cast to int لضمان المقارنة الصحيحة سواء جاءت القيمة
        //         كـ integer أو string من الـ filters array
        $pid = isset($filters['parent_id']) ? (int) $filters['parent_id'] : null;

        if ($pid === -1) {
            // show_all bypass — لا فلترة على الـ parent_id نهائياً
        } elseif ($pid === null || $pid === 0) {
            if (empty($filters['search'])) {
                $sql .= " AND (c.parent_id IS NULL OR c.parent_id = 0)";
            }
        } else {
            $sql .= " AND (c.id = :parent_id1 OR c.parent_id = :parent_id2)";
            $params[':parent_id1'] = $pid;
            $params[':parent_id2'] = $pid;
        }

        // ── Optional filters ──────────────────────────────────────
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND c.is_active = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $sql .= " AND c.is_featured = :is_featured";
            $params[':is_featured'] = (int) $filters['is_featured'];
        }

        if (!empty($filters['search'])) {
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
            $searchVal             = "%{$filters['search']}%";
            $params[':search1']    = $searchVal;
            $params[':search2']    = $searchVal;
            $params[':search3']    = $searchVal;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /* ============================================================
     * ADDITIONAL QUERY METHODS
     * ============================================================ */

    public function getActiveCategories(?int $tenantId, string $lang = 'en'): array
    {
        $sql    = "
            SELECT
                c.*,
                COALESCE(ct.name,        c.name)        AS name,
                COALESCE(ct.description, c.description) AS description,
                COALESCE(ct.slug,        c.slug)        AS slug,
                i.url AS image_url
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN image_types it_cat_a
                ON it_cat_a.name = 'category'
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = it_cat_a.id
               AND i.is_main = 1
            WHERE c.is_active = 1
        ";
        $params = [':lang' => $lang];

        if ($tenantId !== null) {
            $sql .= " AND c.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        $sql .= " ORDER BY c.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedCategories(?int $tenantId, string $lang = 'en'): array
    {
        $sql    = "
            SELECT
                c.*,
                COALESCE(ct.name,        c.name)        AS name,
                COALESCE(ct.description, c.description) AS description,
                COALESCE(ct.slug,        c.slug)        AS slug,
                i.url AS image_url
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN image_types it_cat_f
                ON it_cat_f.name = 'category'
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = it_cat_f.id
               AND i.is_main = 1
            WHERE c.is_active = 1 AND c.is_featured = 1
        ";
        $params = [':lang' => $lang];

        if ($tenantId !== null) {
            $sql .= " AND c.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        $sql .= " ORDER BY c.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * PRIVATE HELPERS (trait)
     * ============================================================ */

    private function hasTenantCategoryAssignments(?int $tenantId): bool
    {
        if ($tenantId === null) return false;

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt FROM tenant_categories WHERE tenant_id = :tenantId LIMIT 1"
        );
        $stmt->execute([':tenantId' => $tenantId]);
        return ((int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;
    }

    /* ============================================================
     * LOGGING
     * ============================================================ */
    private function logAction(
        int $tenantId,
        int $userId,
        string $action,
        int $entityId,
        ?array $oldData,
        ?array $newData
    ): void {
        $sensitiveFields = [
            'password', 'token', 'api_key', 'secret_key',
            'refresh_token', 'access_token', 'session_id',
        ];

        foreach ($sensitiveFields as $field) {
            if ($oldData) unset($oldData[$field]);
            if ($newData) unset($newData[$field]);
        }

        if (class_exists('AuditLogsService')) {
            AuditLogsService::log(
                'category.' . $action,
                'category',
                $entityId,
                null,
                $tenantId,
                $userId,
                $oldData,
                $newData
            );
            return;
        }

        try {
            $diff = null;
            if ($oldData !== null && $newData !== null && class_exists('PdoAuditLogsRepository')) {
                $diff = PdoAuditLogsRepository::computeDiff($oldData, $newData);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (tenant_id, entity_type, entity_id, user_id, action,
                     ip_address, user_agent, old_values, new_values, diff,
                     http_method, http_url, session_id)
                VALUES
                    (:tenantId, :entity_type, :entity_id, :userId, :action,
                     :ip, :user_agent, :old_values, :new_values, :diff,
                     :http_method, :http_url, :session_id)
            ");

            $stmt->execute([
                ':tenantId'    => $tenantId,
                ':entity_type' => 'category',
                ':entity_id'   => $entityId,
                ':userId'      => $userId,
                ':action'      => 'category.' . $action,
                ':ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
                ':user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':old_values'  => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                ':new_values'  => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                ':diff'        => $diff    !== null ? json_encode($diff,    JSON_UNESCAPED_UNICODE) : null,
                ':http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                ':http_url'    => $_SERVER['REQUEST_URI']    ?? null,
                ':session_id'  => session_id()               ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('AuditLog (categories fallback) failed: ' . $e->getMessage());
        }
    }
}
