<?php
declare(strict_types=1);

final class PdoThemesRepository
{
    public const PLATFORM_TENANT_ID = 1;
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_PLATFORM = 'platform';
    public const TARGET_TENANT_STORE = 'tenant_store';
    public const TARGET_PLATFORM_ADMIN = 'platform_admin';
    public const TARGET_PLATFORM_HOME = 'platform_home';
    private const OVERRIDE_TYPE = 'theme_selection';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $viewerTenantId, array $options = []): array
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        $scope = $this->normalizeScope($options['theme_scope'] ?? null);
        $includeInactive = !empty($options['include_inactive']);
        $ownerTenantId = $this->normalizeOwnerTenantId($options['owner_tenant_id'] ?? null, $scope, $target, $viewerTenantId);
        $includeTenantOwned = !array_key_exists('include_tenant_owned', $options) || (bool)$options['include_tenant_owned'];

        $params = [];
        $sql = "
            SELECT id, name, slug, description, thumbnail_url, preview_url, version, author,
                   is_active, is_default, created_at, updated_at, tenant_id, theme_scope, theme_target
            FROM themes
            WHERE theme_target = :theme_target
        ";
        $params[':theme_target'] = $target;

        if (!$includeInactive) {
            $sql .= " AND is_active = 1";
        }

        if ($scope !== null) {
            $sql .= " AND theme_scope = :theme_scope";
            $params[':theme_scope'] = $scope;
        }

        if ($scope === self::SCOPE_GLOBAL || $scope === self::SCOPE_PLATFORM) {
            $sql .= " AND (tenant_id = :owner_tenant_id OR tenant_id IS NULL)";
            $params[':owner_tenant_id'] = $ownerTenantId;
        } elseif ($scope === self::SCOPE_TENANT) {
            $sql .= " AND tenant_id = :viewer_tenant_id";
            $params[':viewer_tenant_id'] = $viewerTenantId;
        } elseif ($target === self::TARGET_TENANT_STORE) {
            $sql .= "
                AND (
                    (theme_scope = :global_scope AND (tenant_id = :platform_tenant_id OR tenant_id IS NULL))
                    OR
                    (theme_scope = :tenant_scope AND tenant_id = :viewer_tenant_id)
                )
            ";
            $params[':global_scope'] = self::SCOPE_GLOBAL;
            $params[':tenant_scope'] = self::SCOPE_TENANT;
            $params[':platform_tenant_id'] = self::PLATFORM_TENANT_ID;
            $params[':viewer_tenant_id'] = $includeTenantOwned ? $viewerTenantId : -1;
        } else {
            $sql .= " AND theme_scope = :platform_scope AND (tenant_id = :owner_tenant_id OR tenant_id IS NULL)";
            $params[':platform_scope'] = self::SCOPE_PLATFORM;
            $params[':owner_tenant_id'] = $ownerTenantId;
        }

        $sql .= " ORDER BY is_default DESC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $viewerTenantId, string $slug, array $options = []): ?array
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        $candidates = $this->all($viewerTenantId, $options + ['theme_target' => $target, 'include_inactive' => true]);
        foreach ($candidates as $row) {
            if ((string)$row['slug'] === $slug) {
                return $row;
            }
        }
        return null;
    }

    public function findById(int $viewerTenantId, int $id, array $options = []): ?array
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        $candidates = $this->all($viewerTenantId, $options + ['theme_target' => $target, 'include_inactive' => true]);
        foreach ($candidates as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function getActive(int $viewerTenantId, array $options = []): ?array
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        if ($target === self::TARGET_TENANT_STORE) {
            return $this->resolveTenantSelectedTheme($viewerTenantId, $target, 'active');
        }

        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at, updated_at, tenant_id, theme_scope, theme_target
            FROM themes
            WHERE (tenant_id = :tenant_id OR tenant_id IS NULL)
              AND theme_scope = :theme_scope
              AND theme_target = :theme_target
              AND is_active = 1
            ORDER BY is_default DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $this->normalizeOwnerTenantId($options['owner_tenant_id'] ?? null, self::SCOPE_PLATFORM, $target, $viewerTenantId),
            ':theme_scope' => self::SCOPE_PLATFORM,
            ':theme_target' => $target,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefault(int $viewerTenantId, array $options = []): ?array
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        if ($target === self::TARGET_TENANT_STORE) {
            return $this->resolveTenantSelectedTheme($viewerTenantId, $target, 'default');
        }

        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at, updated_at, tenant_id, theme_scope, theme_target
            FROM themes
            WHERE (tenant_id = :tenant_id OR tenant_id IS NULL)
              AND theme_scope = :theme_scope
              AND theme_target = :theme_target
              AND is_default = 1
            ORDER BY is_active DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $this->normalizeOwnerTenantId($options['owner_tenant_id'] ?? null, self::SCOPE_PLATFORM, $target, $viewerTenantId),
            ':theme_scope' => self::SCOPE_PLATFORM,
            ':theme_target' => $target,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $viewerTenantId, array $data): int
    {
        $scope = $this->normalizeScope($data['theme_scope'] ?? null) ?? self::SCOPE_GLOBAL;
        $target = $this->normalizeTarget($data['theme_target'] ?? null);
        $ownerTenantId = $this->normalizeOwnerTenantId($data['owner_tenant_id'] ?? ($data['tenant_id'] ?? null), $scope, $target, $viewerTenantId);
        if ($scope !== self::SCOPE_TENANT && $viewerTenantId !== self::PLATFORM_TENANT_ID) {
            throw new ApplicationException('Only platform themes owner can create shared themes');
        }

        if (!empty($data['id'])) {
            $existing = $this->findById($viewerTenantId, (int)$data['id'], [
                'theme_target' => $target,
                'theme_scope' => $scope,
                'owner_tenant_id' => $ownerTenantId,
            ]);
            if (!$existing) {
                throw new ApplicationException('Theme not found');
            }
            if (!$this->canMutateTheme($viewerTenantId, $existing)) {
                throw new ApplicationException('Theme is read only for this tenant');
            }

            $stmt = $this->pdo->prepare("
                UPDATE themes
                SET name = :name,
                    slug = :slug,
                    description = :description,
                    thumbnail_url = :thumbnail_url,
                    preview_url = :preview_url,
                    version = :version,
                    author = :author,
                    is_active = :is_active,
                    is_default = :is_default,
                    theme_scope = :theme_scope,
                    theme_target = :theme_target,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':preview_url' => $data['preview_url'] ?? null,
                ':version' => $data['version'] ?? '1.0.0',
                ':author' => $data['author'] ?? null,
                ':is_active' => (int)($data['is_active'] ?? 0),
                ':is_default' => (int)($data['is_default'] ?? 0),
                ':theme_scope' => $scope,
                ':theme_target' => $target,
                ':id' => (int)$data['id'],
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO themes
                (tenant_id, name, slug, description, thumbnail_url, preview_url, version, author,
                 is_active, is_default, theme_scope, theme_target, created_at)
            VALUES
                (:tenant_id, :name, :slug, :description, :thumbnail_url, :preview_url, :version, :author,
                 :is_active, :is_default, :theme_scope, :theme_target, NOW())
        ");
        $stmt->execute([
            ':tenant_id' => $ownerTenantId,
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'] ?? null,
            ':thumbnail_url' => $data['thumbnail_url'] ?? null,
            ':preview_url' => $data['preview_url'] ?? null,
            ':version' => $data['version'] ?? '1.0.0',
            ':author' => $data['author'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 0),
            ':is_default' => (int)($data['is_default'] ?? 0),
            ':theme_scope' => $scope,
            ':theme_target' => $target,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $viewerTenantId, string $slug, array $options = []): bool
    {
        $row = $this->find($viewerTenantId, $slug, $options);
        if (!$row) {
            return false;
        }
        return $this->deleteById($viewerTenantId, (int)$row['id'], $options);
    }

    public function deleteById(int $viewerTenantId, int $id, array $options = []): bool
    {
        $row = $this->findById($viewerTenantId, $id, $options);
        if (!$row) {
            return false;
        }
        if (!$this->canMutateTheme($viewerTenantId, $row)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM tenant_theme_overrides WHERE theme_id = :theme_id")
                ->execute([':theme_id' => $id]);
            $this->pdo->prepare("DELETE FROM themes WHERE id = :id")
                ->execute([':id' => $id]);
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function activate(int $viewerTenantId, string $slug, array $options = []): bool
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        $theme = $this->find($viewerTenantId, $slug, $options + ['theme_target' => $target]);
        if (!$theme) {
            return false;
        }

        if ($target === self::TARGET_TENANT_STORE) {
            return $this->setTenantSelection($viewerTenantId, $target, 'active', (int)$theme['id']);
        }

        $ownerTenantId = (int)($theme['tenant_id'] ?? self::PLATFORM_TENANT_ID);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                UPDATE themes
                SET is_active = 0
                WHERE tenant_id = :tenant_id
                  AND theme_scope = :theme_scope
                  AND theme_target = :theme_target
            ")->execute([
                ':tenant_id' => $ownerTenantId,
                ':theme_scope' => self::SCOPE_PLATFORM,
                ':theme_target' => $target,
            ]);

            $this->pdo->prepare("UPDATE themes SET is_active = 1 WHERE id = :id")
                ->execute([':id' => (int)$theme['id']]);
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function setDefault(int $viewerTenantId, string $slug, array $options = []): bool
    {
        $target = $this->normalizeTarget($options['theme_target'] ?? null);
        $theme = $this->find($viewerTenantId, $slug, $options + ['theme_target' => $target]);
        if (!$theme) {
            return false;
        }

        if ($target === self::TARGET_TENANT_STORE) {
            return $this->setTenantSelection($viewerTenantId, $target, 'default', (int)$theme['id']);
        }

        $ownerTenantId = (int)($theme['tenant_id'] ?? self::PLATFORM_TENANT_ID);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                UPDATE themes
                SET is_default = 0
                WHERE tenant_id = :tenant_id
                  AND theme_scope = :theme_scope
                  AND theme_target = :theme_target
            ")->execute([
                ':tenant_id' => $ownerTenantId,
                ':theme_scope' => self::SCOPE_PLATFORM,
                ':theme_target' => $target,
            ]);

            $this->pdo->prepare("UPDATE themes SET is_default = 1 WHERE id = :id")
                ->execute([':id' => (int)$theme['id']]);
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function resolveTenantSelectedTheme(int $viewerTenantId, string $target, string $mode): ?array
    {
        $key = $this->selectionKey($target, $mode);
        $stmt = $this->pdo->prepare("
            SELECT value
            FROM tenant_theme_overrides
            WHERE tenant_id = :tenant_id
              AND setting_type = :setting_type
              AND setting_key = :setting_key
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $viewerTenantId,
            ':setting_type' => self::OVERRIDE_TYPE,
            ':setting_key' => $key,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && is_numeric($row['value'])) {
            // Override found — load the theme directly by ID (no target filter)
            $stmtTheme = $this->pdo->prepare("SELECT id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at, updated_at, tenant_id, theme_scope, theme_target FROM themes WHERE id = ? LIMIT 1");
            $stmtTheme->execute([(int)$row['value']]);
            $theme = $stmtTheme->fetch(PDO::FETCH_ASSOC);
            if ($theme) {
                return $theme;
            }
        }

        $legacyColumn = $mode === 'active' ? 'is_active' : 'is_default';
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at, updated_at, tenant_id, theme_scope, theme_target
            FROM themes
            WHERE theme_target = :theme_target
              AND (
                    (theme_scope = :tenant_scope AND tenant_id = :tenant_id)
                    OR
                    (theme_scope = :global_scope AND (tenant_id = :platform_tenant_id OR tenant_id IS NULL))
                  )
              AND {$legacyColumn} = 1
            ORDER BY theme_scope = :tenant_scope_order DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':theme_target' => $target,
            ':tenant_scope' => self::SCOPE_TENANT,
            ':tenant_scope_order' => self::SCOPE_TENANT,
            ':global_scope' => self::SCOPE_GLOBAL,
            ':tenant_id' => $viewerTenantId,
            ':platform_tenant_id' => self::PLATFORM_TENANT_ID,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function setTenantSelection(int $viewerTenantId, string $target, string $mode, int $themeId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                DELETE FROM tenant_theme_overrides
                WHERE tenant_id = :tenant_id
                  AND setting_type = :setting_type
                  AND setting_key = :setting_key
            ")->execute([
                ':tenant_id' => $viewerTenantId,
                ':setting_type' => self::OVERRIDE_TYPE,
                ':setting_key' => $this->selectionKey($target, $mode),
            ]);

            $this->pdo->prepare("
                INSERT INTO tenant_theme_overrides
                    (tenant_id, theme_id, setting_type, setting_key, value, created_at)
                VALUES
                    (:tenant_id, :theme_id, :setting_type, :setting_key, :value, NOW())
            ")->execute([
                ':tenant_id' => $viewerTenantId,
                ':theme_id' => $themeId,
                ':setting_type' => self::OVERRIDE_TYPE,
                ':setting_key' => $this->selectionKey($target, $mode),
                ':value' => (string)$themeId,
            ]);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function selectionKey(string $target, string $mode): string
    {
        return $target . '_' . $mode . '_theme_id';
    }

    private function canMutateTheme(int $viewerTenantId, array $theme): bool
    {
        $scope = $theme['theme_scope'] ?? self::SCOPE_TENANT;
        $ownerTenantId = (int)($theme['tenant_id'] ?? 0);
        if ($scope === self::SCOPE_TENANT) {
            return $ownerTenantId === $viewerTenantId;
        }
        return $viewerTenantId === $ownerTenantId && $ownerTenantId === self::PLATFORM_TENANT_ID;
    }

    private function normalizeScope(?string $scope): ?string
    {
        $scope = $scope !== null ? strtolower(trim($scope)) : null;
        return in_array($scope, [self::SCOPE_GLOBAL, self::SCOPE_TENANT, self::SCOPE_PLATFORM], true) ? $scope : null;
    }

    private function normalizeTarget(?string $target): string
    {
        $target = $target !== null ? strtolower(trim($target)) : self::TARGET_TENANT_STORE;
        return in_array($target, [self::TARGET_TENANT_STORE, self::TARGET_PLATFORM_ADMIN, self::TARGET_PLATFORM_HOME], true)
            ? $target
            : self::TARGET_TENANT_STORE;
    }

    private function normalizeOwnerTenantId(?int $ownerTenantId, ?string $scope, string $target, int $viewerTenantId): int
    {
        if ($scope === self::SCOPE_TENANT) {
            return $viewerTenantId;
        }

        if ($scope === self::SCOPE_GLOBAL || $scope === self::SCOPE_PLATFORM) {
            return $ownerTenantId !== null && $ownerTenantId > 0 ? $ownerTenantId : self::PLATFORM_TENANT_ID;
        }

        if ($target === self::TARGET_PLATFORM_ADMIN || $target === self::TARGET_PLATFORM_HOME) {
            return self::PLATFORM_TENANT_ID;
        }

        return $ownerTenantId !== null && $ownerTenantId > 0 ? $ownerTenantId : $viewerTenantId;
    }
}
