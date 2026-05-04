<?php
declare(strict_types=1);

// api/shared/ui/AdminUiThemeLoader.php

if (!class_exists('PdoThemesRepository', false)) {
    require_once dirname(__DIR__, 2) . '/v1/models/themes/repositories/PdoThemesRepository.php';
}

final class AdminUiThemeLoader
{
    /** Card style card_type values that map to POS product/category CSS variables. */
    private const POS_CARD_TYPES = ['product', 'category'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get full theme data for admin UI
     */
    public function getFullThemeData(int $tenantId, ?int $themeId = null): array
    {
        $target = !empty($GLOBALS['ADMIN_UI']['is_platform_admin'])
            ? self::resolvePlatformTarget()
            : PdoThemesRepository::TARGET_PLATFORM_ADMIN;
        $theme = $this->resolveThemeForTenant($tenantId, $target, $themeId);
        error_log('AdminUiThemeLoader: Tenant ID: ' . $tenantId . ', Theme Target: ' . $target);

        if (!$theme) {
            error_log('AdminUiThemeLoader: No active theme found');
            return [];
        }

        $themeId = (int)$theme['id'];
        $settingsTenantId = (int)($theme['tenant_id'] ?? $tenantId);

        $data = [
            'theme'           => $theme,
            'design_settings' => $this->getDesignSettings($settingsTenantId, $themeId),
            'color_settings'  => $this->getColorSettings($settingsTenantId, $themeId),
            'font_settings'   => $this->getFontSettings($settingsTenantId, $themeId),
            'button_styles'   => $this->getButtonStyles($settingsTenantId, $themeId),
            'card_styles'     => $this->getCardStyles($settingsTenantId, $themeId),
            'system_settings' => $this->getSystemSettings($tenantId),
            'tenant'          => $this->getTenant($tenantId),
            'tenant_users'    => $this->getTenantUsers($tenantId),
            'pos_card_colors' => $this->getPosCardColors($settingsTenantId, $themeId),
        ];

        // Generate CSS
        $data['generated_css'] = $this->generateCss($data);
        error_log('AdminUiThemeLoader: Theme data loaded successfully');
        return $data;
    }

    public function getActiveThemeId(int $tenantId): ?int
    {
        $target = !empty($GLOBALS['ADMIN_UI']['is_platform_admin'])
            ? self::resolvePlatformTarget()
            : PdoThemesRepository::TARGET_PLATFORM_ADMIN;
        $theme = $this->resolveThemeForTenant($tenantId, $target);
        return $theme ? (int)$theme['id'] : null;
    }

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
            error_log('AdminUiThemeLoader: getTheme row: ' . json_encode($row));
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('AdminUiThemeLoader: Error in getTheme: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveThemeForTenant(int $tenantId, string $target, ?int $themeId = null): ?array
    {
        try {
            if ($themeId !== null) {
                return $this->getTheme($tenantId, $themeId);
            }

            // 1. Check for tenant-specific override FIRST
            //    Try the exact target key first, then fallback to tenant_store
            $key = $target . '_active_theme_id';
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
            $stmt->execute([$tenantId, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
            
            // Fallback: if no override for current target, try tenant_store override
            if ($target !== PdoThemesRepository::TARGET_TENANT_STORE) {
                $fallbackKey = PdoThemesRepository::TARGET_TENANT_STORE . '_active_theme_id';
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
                $stmt->execute([$tenantId, $fallbackKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row;
                }
            }

            // 2. Fallback for platform targets — handle tenant_id = NULL or PLATFORM_TENANT_ID
            if ($target === PdoThemesRepository::TARGET_PLATFORM_ADMIN || $target === PdoThemesRepository::TARGET_PLATFORM_HOME) {
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
                if ($row) {
                    return $row;
                }
            }

            // 3. Tenant-specific or global fallback
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
        } catch (\PDOException $e) {
            error_log('AdminUiThemeLoader: Error resolving theme: ' . $e->getMessage());
            return null;
        }
    }

    private static function resolvePlatformTarget(): string
    {
        $requested = strtolower(trim((string)($_GET['theme_target'] ?? '')));
        return in_array($requested, [PdoThemesRepository::TARGET_PLATFORM_ADMIN, PdoThemesRepository::TARGET_PLATFORM_HOME], true)
            ? $requested
            : PdoThemesRepository::TARGET_PLATFORM_ADMIN;
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
            error_log('AdminUiThemeLoader: Error in getDesignSettings: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getColorSettings: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getFontSettings: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getButtonStyles: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getCardStyles: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return a keyed array of POS-specific card color CSS variable maps for quick lookup.
     * Keys: 'product' and 'category' – each value is an array of CSS var => value pairs
     * that match the variables used by admin/assets/css/pages/pos.css.
     * Matches card_styles rows by card_type ('product' / 'category') rather than slug
     * so that slugs like 'product-default' or 'product-minimal' are picked up correctly.
     * Falls back to defaults when the card_styles rows are not configured.
     */
    public function getPosCardColors(int $tenantId, int $themeId): array
    {
        $defaults = [
            'product'  => [
                '--card-product-bg'           => 'var(--card-bg, var(--background-secondary, #1e293b))',
                '--card-product-text'          => 'var(--text-primary, #e2e8f0)',
                '--card-product-border'        => 'var(--border-color, #334155)',
                '--card-product-border-width'  => '1px',
                '--card-product-radius'        => '10px',
                '--card-product-shadow'        => 'none',
                '--card-product-padding'       => '12px 10px',
            ],
            'category' => [
                '--card-category-bg'           => 'transparent',
                '--card-category-text'         => 'var(--text-secondary, #94a3b8)',
                '--card-category-border'       => 'var(--border-color, #334155)',
                '--card-category-radius'       => '20px',
            ],
        ];

        $result = $defaults;
        $cardStyles = $this->getCardStyles($tenantId, $themeId);
        // Use the first active card per card_type; subsequent rows are ignored
        $seen = [];
        foreach ($cardStyles as $card) {
            $type = $card['card_type'] ?? '';
            if (!in_array($type, self::POS_CARD_TYPES, true)) continue;
            if (isset($seen[$type])) continue; // keep first active entry
            $seen[$type] = true;
            $prefix = "--card-{$type}";
            if (!empty($card['background_color'])) $result[$type]["{$prefix}-bg"]           = $card['background_color'];
            if (!empty($card['text_color']))        $result[$type]["{$prefix}-text"]         = $card['text_color'];
            if (!empty($card['border_color']))      $result[$type]["{$prefix}-border"]       = $card['border_color'];
            if (!empty($card['border_width']))      $result[$type]["{$prefix}-border-width"] = $card['border_width'] . 'px';
            if (!empty($card['border_radius']))     $result[$type]["{$prefix}-radius"]       = $card['border_radius'] . 'px';
            if (!empty($card['shadow_style']))      $result[$type]["{$prefix}-shadow"]       = $card['shadow_style'];
            if (!empty($card['padding']))           $result[$type]["{$prefix}-padding"]      = $card['padding'];
        }
        return $result;
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
            error_log('AdminUiThemeLoader: Error in getSystemSettings: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getTenant: ' . $e->getMessage());
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
            error_log('AdminUiThemeLoader: Error in getTenantUsers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate CSS from theme data.
     *
     * CSS variable names are converted to kebab-case (primary_color → --primary-color)
     * to match the CSS framework's var() references.
     *
     * All values written into CSS are sanitized to prevent injection ({};).
     */
    public function generateCss(array $themeData): string
    {
        $hyphenateKey = static fn(string $key): string => str_replace('_', '-', strtolower($key));
        $sanitizeCssValue = static fn(string $v): string => preg_replace('/[{};`]/', '', trim($v));
        $safeCssIdent = static fn(string $s): string => preg_replace('/[^a-z0-9-]/', '-', strtolower($s));

        $css = ":root {\n";
        $css .= $this->generateColorVars($themeData, $hyphenateKey, $sanitizeCssValue);
        $css .= $this->generateFontVars($themeData, $hyphenateKey, $sanitizeCssValue);
        $css .= $this->generateDesignVars($themeData, $hyphenateKey, $sanitizeCssValue);
        $css .= $this->generateButtonVars($themeData, $safeCssIdent, $sanitizeCssValue);
        $css .= $this->generateCardVars($themeData, $safeCssIdent, $sanitizeCssValue);
        $css .= "}\n";
        $css .= $this->generateButtonClasses($themeData, $safeCssIdent, $sanitizeCssValue);
        $css .= $this->generateCardClasses($themeData, $safeCssIdent, $sanitizeCssValue);
        return $css;
    }

    private function generateColorVars(array $themeData, callable $hyphenateKey, callable $sanitizeCssValue): string
    {
        $css = '';
        $bgTertiary = null; $bgSecondary = null;
        foreach ($themeData['color_settings'] ?? [] as $color) {
            if (empty($color['setting_key']) || empty($color['color_value'])) { continue; }
            $val = $sanitizeCssValue((string)$color['color_value']);
            $hyphen = $hyphenateKey($color['setting_key']);
            $css .= "  --{$hyphen}: {$val};\n";
            if ($color['setting_key'] !== $hyphen) { $css .= "  --{$color['setting_key']}: {$val};\n"; }
            if ($hyphen === 'background-tertiary') { $bgTertiary = $val; }
            elseif ($hyphen === 'background-secondary') { $bgSecondary = $val; }
        }
        $theadBg = $bgTertiary ?? $bgSecondary;
        if ($theadBg !== null) { $css .= "  --thead-bg: {$theadBg};\n"; }
        if ($bgSecondary !== null) { $css .= "  --input-background: {$bgSecondary};\n"; }

        $aliasMap = ['--danger-color' => '--error-color', '--card-bg' => '--background-secondary'];
        foreach ($aliasMap as $target => $source) {
            $sourceKey = ltrim($source, '-');
            $sourceVal = null;
            foreach ($themeData['color_settings'] ?? [] as $color) {
                if (!empty($color['setting_key']) && !empty($color['color_value']) && $hyphenateKey($color['setting_key']) === $sourceKey) { $sourceVal = $sanitizeCssValue((string)$color['color_value']); break; }
            }
            if ($sourceVal === null) { continue; }
            $targetKey = ltrim($target, '-');
            $alreadySet = false;
            foreach ($themeData['color_settings'] ?? [] as $color) {
                if (!empty($color['setting_key']) && $hyphenateKey($color['setting_key']) === $targetKey) { $alreadySet = true; break; }
            }
            if (!$alreadySet) { $css .= "  {$target}: {$sourceVal};\n"; }
        }
        return $css;
    }

    private function generateFontVars(array $themeData, callable $hyphenateKey, callable $sanitizeCssValue): string
    {
        $css = '';
        foreach ($themeData['font_settings'] ?? [] as $font) {
            if (empty($font['setting_key'])) { continue; }
            $hyphen = $hyphenateKey($font['setting_key']);
            if (!empty($font['font_family'])) { $css .= "  --{$hyphen}-family: " . $sanitizeCssValue((string)$font['font_family']) . ";\n"; }
            if (!empty($font['font_size'])) { $css .= "  --{$hyphen}-size: " . $sanitizeCssValue((string)$font['font_size']) . ";\n"; }
            if (!empty($font['font_weight'])) { $css .= "  --{$hyphen}-weight: " . $sanitizeCssValue((string)$font['font_weight']) . ";\n"; }
            if (!empty($font['line_height'])) { $css .= "  --{$hyphen}-line-height: " . $sanitizeCssValue((string)$font['line_height']) . ";\n"; }
        }
        return $css;
    }

    private function generateDesignVars(array $themeData, callable $hyphenateKey, callable $sanitizeCssValue): string
    {
        $css = '';
        foreach ($themeData['design_settings'] ?? [] as $ds) {
            if (empty($ds['setting_key']) || empty($ds['setting_value'])) { continue; }
            $type = strtolower($ds['setting_type'] ?? 'text');
            if (!in_array($type, ['color', 'text', 'number'], true)) { continue; }
            $css .= "  --" . $hyphenateKey($ds['setting_key']) . ": " . $sanitizeCssValue((string)$ds['setting_value']) . ";\n";
        }
        return $css;
    }

    private function generateButtonVars(array $themeData, callable $safeCssIdent, callable $sanitizeCssValue): string
    {
        $css = '';
        foreach ($themeData['button_styles'] ?? [] as $button) {
            if (empty($button['slug'])) { continue; }
            $slug = $safeCssIdent((string)$button['slug']);
            if (!empty($button['background_color']))       { $css .= "  --btn-{$slug}-bg: " . $sanitizeCssValue((string)$button['background_color']) . ";\n"; }
            if (!empty($button['text_color']))             { $css .= "  --btn-{$slug}-color: " . $sanitizeCssValue((string)$button['text_color']) . ";\n"; }
            if (!empty($button['border_color']))           { $css .= "  --btn-{$slug}-border: " . $sanitizeCssValue((string)$button['border_color']) . ";\n"; }
            if (!empty($button['border_width']))           { $css .= "  --btn-{$slug}-border-width: " . (int)$button['border_width'] . "px;\n"; }
            if (!empty($button['border_radius']))          { $css .= "  --btn-{$slug}-radius: " . (int)$button['border_radius'] . "px;\n"; }
            if (!empty($button['padding']))                { $css .= "  --btn-{$slug}-padding: " . $sanitizeCssValue((string)$button['padding']) . ";\n"; }
            if (!empty($button['font_size']))              { $css .= "  --btn-{$slug}-font-size: " . $sanitizeCssValue((string)$button['font_size']) . ";\n"; }
            if (!empty($button['font_weight']))            { $css .= "  --btn-{$slug}-font-weight: " . $sanitizeCssValue((string)$button['font_weight']) . ";\n"; }
            if (!empty($button['hover_background_color'])) { $css .= "  --btn-{$slug}-hover-bg: " . $sanitizeCssValue((string)$button['hover_background_color']) . ";\n"; }
            if (!empty($button['hover_text_color']))       { $css .= "  --btn-{$slug}-hover-color: " . $sanitizeCssValue((string)$button['hover_text_color']) . ";\n"; }
            if (!empty($button['hover_border_color']))     { $css .= "  --btn-{$slug}-hover-border: " . $sanitizeCssValue((string)$button['hover_border_color']) . ";\n"; }
        }
        return $css;
    }

    private function generateCardVars(array $themeData, callable $safeCssIdent, callable $sanitizeCssValue): string
    {
        $css = '';
        $posCardTypesSeen = [];
        foreach ($themeData['card_styles'] ?? [] as $card) {
            if (empty($card['slug'])) { continue; }
            $slug = $safeCssIdent((string)$card['slug']);
            if (!empty($card['background_color'])) { $css .= "  --card-{$slug}-bg: " . $sanitizeCssValue((string)$card['background_color']) . ";\n"; }
            if (!empty($card['border_color']))     { $css .= "  --card-{$slug}-border: " . $sanitizeCssValue((string)$card['border_color']) . ";\n"; }
            if (!empty($card['border_radius']))    { $css .= "  --card-{$slug}-radius: " . $sanitizeCssValue((string)$card['border_radius']) . "px;\n"; }
            if (!empty($card['shadow_style']))     { $css .= "  --card-{$slug}-shadow: " . $sanitizeCssValue((string)$card['shadow_style']) . ";\n"; }
            if (!empty($card['padding']))          { $css .= "  --card-{$slug}-padding: " . $sanitizeCssValue((string)$card['padding']) . ";\n"; }
            if (!empty($card['text_color']))       { $css .= "  --card-{$slug}-text: " . $sanitizeCssValue((string)$card['text_color']) . ";\n"; }
            if (!empty($card['border_width']))     { $css .= "  --card-{$slug}-border-width: " . $sanitizeCssValue((string)$card['border_width']) . "px;\n"; }
            $cardType = $card['card_type'] ?? '';
            if (in_array($cardType, self::POS_CARD_TYPES, true) && !isset($posCardTypesSeen[$cardType])) {
                $posCardTypesSeen[$cardType] = true;
                $tp = "--card-{$cardType}";
                if (!empty($card['background_color'])) { $css .= "  {$tp}-bg: " . $sanitizeCssValue((string)$card['background_color']) . ";\n"; }
                if (!empty($card['text_color']))       { $css .= "  {$tp}-text: " . $sanitizeCssValue((string)$card['text_color']) . ";\n"; }
                if (!empty($card['border_color']))     { $css .= "  {$tp}-border: " . $sanitizeCssValue((string)$card['border_color']) . ";\n"; }
                if (!empty($card['border_width']))     { $css .= "  {$tp}-border-width: " . $sanitizeCssValue((string)$card['border_width']) . "px;\n"; }
                if (!empty($card['border_radius']))    { $css .= "  {$tp}-radius: " . $sanitizeCssValue((string)$card['border_radius']) . "px;\n"; }
                if (!empty($card['shadow_style']))     { $css .= "  {$tp}-shadow: " . $sanitizeCssValue((string)$card['shadow_style']) . ";\n"; }
                if (!empty($card['padding']))          { $css .= "  {$tp}-padding: " . $sanitizeCssValue((string)$card['padding']) . ";\n"; }
            }
        }
        return $css;
    }

    private function generateButtonClasses(array $themeData, callable $safeCssIdent, callable $sanitizeCssValue): string
    {
        $css = '';
        foreach ($themeData['button_styles'] ?? [] as $button) {
            if (empty($button['slug'])) { continue; }
            $slug = $safeCssIdent((string)$button['slug']);
            $css .= ".btn-{$slug} {\n";
            if (!empty($button['background_color'])) { $css .= "  background-color: " . $sanitizeCssValue((string)$button['background_color']) . ";\n"; }
            if (!empty($button['text_color']))       { $css .= "  color: " . $sanitizeCssValue((string)$button['text_color']) . ";\n"; }
            if (!empty($button['border_color']))     { $bw = (int)($button['border_width'] ?? 1); $css .= "  border: {$bw}px solid " . $sanitizeCssValue((string)$button['border_color']) . ";\n"; }
            if (!empty($button['border_radius']))    { $css .= "  border-radius: " . (int)$button['border_radius'] . "px;\n"; }
            if (!empty($button['padding']))          { $css .= "  padding: " . $sanitizeCssValue((string)$button['padding']) . ";\n"; }
            if (!empty($button['font_size']))        { $css .= "  font-size: " . $sanitizeCssValue((string)$button['font_size']) . ";\n"; }
            if (!empty($button['font_weight']))      { $css .= "  font-weight: " . $sanitizeCssValue((string)$button['font_weight']) . ";\n"; }
            $css .= "  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;\n}\n";
            $hasHover = !empty($button['hover_background_color']) || !empty($button['hover_text_color']) || !empty($button['hover_border_color']);
            if ($hasHover) {
                $css .= ".btn-{$slug}:hover,\n.btn-{$slug}:focus-visible {\n";
                if (!empty($button['hover_background_color'])) { $css .= "  background-color: " . $sanitizeCssValue((string)$button['hover_background_color']) . ";\n"; }
                if (!empty($button['hover_text_color']))       { $css .= "  color: " . $sanitizeCssValue((string)$button['hover_text_color']) . ";\n"; }
                if (!empty($button['hover_border_color']))     { $css .= "  border-color: " . $sanitizeCssValue((string)$button['hover_border_color']) . ";\n"; }
                $css .= "}\n";
            }
        }
        return $css;
    }

    private function generateCardClasses(array $themeData, callable $safeCssIdent, callable $sanitizeCssValue): string
    {
        $css = '';
        $hoverEffectMap = ['lift' => "transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.15);", 'zoom' => "transform: scale(1.03);", 'shadow' => "box-shadow: 0 8px 24px rgba(0,0,0,0.2);", 'border' => "border-color: var(--primary-color, #3B82F6);", 'brightness' => "filter: brightness(1.08);"];
        foreach ($themeData['card_styles'] ?? [] as $card) {
            if (empty($card['slug'])) { continue; }
            $slug = $safeCssIdent((string)$card['slug']);
            $effect = strtolower((string)($card['hover_effect'] ?? 'none'));
            $css .= ".card-{$slug} {\n";
            if (!empty($card['background_color'])) { $css .= "  background-color: " . $sanitizeCssValue((string)$card['background_color']) . ";\n"; }
            if (!empty($card['border_color']))     { $bw = (int)($card['border_width'] ?? 1); $css .= "  border: {$bw}px solid " . $sanitizeCssValue((string)$card['border_color']) . ";\n"; }
            if (!empty($card['border_radius']))    { $css .= "  border-radius: " . (int)$card['border_radius'] . "px;\n"; }
            if (!empty($card['shadow_style']))     { $css .= "  box-shadow: " . $sanitizeCssValue((string)$card['shadow_style']) . ";\n"; }
            if (!empty($card['padding']))          { $css .= "  padding: " . $sanitizeCssValue((string)$card['padding']) . ";\n"; }
            if (!empty($card['text_align']) && in_array($card['text_align'], ['left', 'center', 'right'], true)) { $css .= "  text-align: {$card['text_align']};\n"; }
            if ($effect !== 'none' && isset($hoverEffectMap[$effect])) { $css .= "  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, filter 0.2s ease;\n"; }
            $css .= "}\n";
            if ($effect !== 'none' && isset($hoverEffectMap[$effect])) { $css .= ".card-{$slug}:hover {\n  " . $hoverEffectMap[$effect] . "\n}\n"; }
        }
        return $css;
    }
}