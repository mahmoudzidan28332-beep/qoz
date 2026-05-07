<?php
declare(strict_types=1);

// api/shared/ui/AdminThemeQueryRepository.php

if (!class_exists('PdoThemesRepository', false)) {
    require_once dirname(__DIR__, 2) . '/v1/models/themes/repositories/PdoThemesRepository.php';
}

/**
 * AdminThemeQueryRepository
 *
 * Handles all database queries needed to load theme data for the admin UI.
 * Extracted from AdminUiThemeLoader to apply Single Responsibility Principle.
 */
final class AdminThemeQueryRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function getTheme(int $tenantId, int $themeId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, slug, description, thumbnail_url, preview_url,
                       version, author, is_active, is_default, created_at, updated_at,
                       tenant_id, theme_scope, theme_target
                FROM themes
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$themeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log('AdminThemeQueryRepository: getTheme row: ' . json_encode($row));
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getTheme: ' . $e->getMessage());
            return null;
        }
    }

    public function findTenantOverride(int $tenantId, string $settingKey): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, t.slug, t.description, t.thumbnail_url, t.preview_url,
                   t.version, t.author, t.is_active, t.is_default, t.created_at, t.updated_at,
                   t.tenant_id, t.theme_scope, t.theme_target
            FROM tenant_theme_overrides o
            INNER JOIN themes t ON t.id = o.theme_id
            WHERE o.tenant_id = ?
              AND o.setting_type = 'theme_selection'
              AND o.setting_key = ?
            ORDER BY o.id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $settingKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPlatformTheme(string $target): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, description, thumbnail_url, preview_url,
                   version, author, is_active, is_default, created_at, updated_at,
                   tenant_id, theme_scope, theme_target
            FROM themes
            WHERE (tenant_id = ? OR tenant_id IS NULL)
              AND theme_scope = 'platform'
              AND theme_target = ?
              AND is_active = 1
            ORDER BY is_default DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([PdoThemesRepository::PLATFORM_TENANT_ID, $target]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findTenantOrGlobalTheme(int $tenantId, string $target): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, description, thumbnail_url, preview_url,
                   version, author, is_active, is_default, created_at, updated_at,
                   tenant_id, theme_scope, theme_target
            FROM themes
            WHERE theme_target = ?
              AND (
                    (theme_scope = 'tenant' AND tenant_id = ? AND is_active = 1)
                    OR
                    (theme_scope = 'global' AND (tenant_id = ? OR tenant_id IS NULL) AND is_default = 1)
                  )
            ORDER BY theme_scope = 'tenant' DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$target, $tenantId, PdoThemesRepository::PLATFORM_TENANT_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDesignSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, theme_id, setting_key, setting_name, setting_value, setting_type,
                       category, is_active, sort_order, created_at, updated_at, tenant_id
                FROM design_settings
                WHERE theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getDesignSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getColorSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, theme_id, setting_key, setting_name, color_value,
                       category, is_active, sort_order, created_at, updated_at, tenant_id
                FROM color_settings
                WHERE theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getColorSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getFontSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, theme_id, setting_key, setting_name, font_family, font_size,
                       font_weight, line_height, category, is_active, sort_order,
                       created_at, updated_at, tenant_id
                FROM font_settings
                WHERE theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getFontSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getButtonStyles(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, tenant_id, theme_id, name, slug, button_type,
                       background_color, text_color, border_color, border_width,
                       border_radius, padding, font_size, font_weight,
                       hover_background_color, hover_text_color, hover_border_color,
                       is_active, created_at, updated_at
                FROM button_styles
                WHERE theme_id = ? AND is_active = 1
                ORDER BY button_type, name
            ");
            $stmt->execute([$themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getButtonStyles: ' . $e->getMessage());
            return [];
        }
    }

    public function getCardStyles(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, tenant_id, theme_id, name, slug, card_type,
                       background_color, border_color, text_color, border_width,
                       border_radius, shadow_style, padding, hover_effect,
                       text_align, image_aspect_ratio, is_active, created_at, updated_at
                FROM card_styles
                WHERE theme_id = ? AND is_active = 1
                ORDER BY card_type, name
            ");
            $stmt->execute([$themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getCardStyles: ' . $e->getMessage());
            return [];
        }
    }

    public function getSystemSettings(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, tenant_id, setting_key, setting_value, setting_type,
                       category, description, is_public, is_editable, created_at, updated_at
                FROM system_settings
                WHERE tenant_id = ? AND is_public = 1
                ORDER BY category, setting_key
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getSystemSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getTenant(int $tenantId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name, t.owner_user_id, t.status, t.created_at, t.updated_at,
                       t.domain, u.username AS owner_username
                FROM tenants t
                LEFT JOIN tenant_users tu ON t.id = tu.tenant_id AND tu.user_id = t.owner_user_id
                LEFT JOIN users u ON tu.user_id = u.id
                WHERE t.id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getTenant: ' . $e->getMessage());
            return null;
        }
    }

    public function getTenantUsers(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT tu.id, tu.tenant_id, tu.user_id, tu.role_id, tu.entity_id,
                       tu.joined_at, tu.is_active, tu.updated_at,
                       u.username, r.name AS role_name
                FROM tenant_users tu
                LEFT JOIN users u ON tu.user_id = u.id
                LEFT JOIN roles r ON tu.role_id = r.id
                WHERE tu.tenant_id = ?
                ORDER BY tu.joined_at DESC
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AdminThemeQueryRepository: Error in getTenantUsers: ' . $e->getMessage());
            return [];
        }
    }
}
