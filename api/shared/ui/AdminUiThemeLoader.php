<?php
declare(strict_types=1);

// api/shared/ui/AdminUiThemeLoader.php

if (!class_exists('PdoThemesRepository', false)) {
    require_once dirname(__DIR__, 2) . '/v1/models/themes/repositories/PdoThemesRepository.php';
}
if (!class_exists('AdminThemeQueryRepository', false)) {
    require_once __DIR__ . '/AdminThemeQueryRepository.php';
}
if (!class_exists('AdminThemeCssGenerator', false)) {
    require_once __DIR__ . '/AdminThemeCssGenerator.php';
}

/**
 * AdminUiThemeLoader
 *
 * Orchestrates theme loading for the admin UI by delegating to
 * AdminThemeQueryRepository (DB access) and AdminThemeCssGenerator (CSS generation).
 */
final class AdminUiThemeLoader
{
    private AdminThemeQueryRepository $queryRepo;
    private AdminThemeCssGenerator $cssGenerator;

    public function __construct(PDO $pdo)
    {
        $this->queryRepo    = new AdminThemeQueryRepository($pdo);
        $this->cssGenerator = new AdminThemeCssGenerator();
    }

    /**
     * Get full theme data for admin UI.
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

        $resolvedThemeId  = (int)$theme['id'];
        $settingsTenantId = (int)($theme['tenant_id'] ?? $tenantId);

        $data = [
            'theme'           => $theme,
            'design_settings' => $this->queryRepo->getDesignSettings($settingsTenantId, $resolvedThemeId),
            'color_settings'  => $this->queryRepo->getColorSettings($settingsTenantId, $resolvedThemeId),
            'font_settings'   => $this->queryRepo->getFontSettings($settingsTenantId, $resolvedThemeId),
            'button_styles'   => $this->queryRepo->getButtonStyles($settingsTenantId, $resolvedThemeId),
            'card_styles'     => $this->queryRepo->getCardStyles($settingsTenantId, $resolvedThemeId),
            'system_settings' => $this->queryRepo->getSystemSettings($tenantId),
            'tenant'          => $this->queryRepo->getTenant($tenantId),
            'tenant_users'    => $this->queryRepo->getTenantUsers($tenantId),
            'pos_card_colors' => $this->buildPosCardColors($settingsTenantId, $resolvedThemeId),
        ];

        $data['generated_css'] = $this->cssGenerator->generate($data);
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
        return $this->queryRepo->getTheme($tenantId, $themeId);
    }

    // ── Delegating accessors preserved for backwards compatibility ───────

    public function getDesignSettings(int $tenantId, int $themeId): array
    {
        return $this->queryRepo->getDesignSettings($tenantId, $themeId);
    }

    public function getColorSettings(int $tenantId, int $themeId): array
    {
        return $this->queryRepo->getColorSettings($tenantId, $themeId);
    }

    public function getFontSettings(int $tenantId, int $themeId): array
    {
        return $this->queryRepo->getFontSettings($tenantId, $themeId);
    }

    public function getButtonStyles(int $tenantId, int $themeId): array
    {
        return $this->queryRepo->getButtonStyles($tenantId, $themeId);
    }

    public function getCardStyles(int $tenantId, int $themeId): array
    {
        return $this->queryRepo->getCardStyles($tenantId, $themeId);
    }

    public function getSystemSettings(int $tenantId): array
    {
        return $this->queryRepo->getSystemSettings($tenantId);
    }

    public function getTenant(int $tenantId): ?array
    {
        return $this->queryRepo->getTenant($tenantId);
    }

    public function getTenantUsers(int $tenantId): array
    {
        return $this->queryRepo->getTenantUsers($tenantId);
    }

    public function generateCss(array $themeData): string
    {
        return $this->cssGenerator->generate($themeData);
    }

    /**
     * Return a keyed array of POS-specific card color CSS variable maps for quick lookup.
     */
    public function getPosCardColors(int $tenantId, int $themeId): array
    {
        return $this->buildPosCardColors($tenantId, $themeId);
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function resolveThemeForTenant(int $tenantId, string $target, ?int $themeId = null): ?array
    {
        try {
            if ($themeId !== null) {
                return $this->queryRepo->getTheme($tenantId, $themeId);
            }

            $row = $this->queryRepo->findTenantOverride($tenantId, $target . '_active_theme_id');
            if ($row) return $row;

            if ($target !== PdoThemesRepository::TARGET_TENANT_STORE) {
                $row = $this->queryRepo->findTenantOverride($tenantId, PdoThemesRepository::TARGET_TENANT_STORE . '_active_theme_id');
                if ($row) return $row;
            }

            if ($target === PdoThemesRepository::TARGET_PLATFORM_ADMIN || $target === PdoThemesRepository::TARGET_PLATFORM_HOME) {
                $row = $this->queryRepo->findPlatformTheme($target);
                if ($row) return $row;
            }

            return $this->queryRepo->findTenantOrGlobalTheme($tenantId, $target);
        } catch (\PDOException $e) {
            error_log('AdminUiThemeLoader: Error resolving theme: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPosCardColors(int $tenantId, int $themeId): array
    {
        $posCardTypes = ['product', 'category'];
        $defaults = [
            'product'  => [
                '--card-product-bg'          => 'var(--card-bg, var(--background-secondary, #1e293b))',
                '--card-product-text'         => 'var(--text-primary, #e2e8f0)',
                '--card-product-border'       => 'var(--border-color, #334155)',
                '--card-product-border-width' => '1px',
                '--card-product-radius'       => '10px',
                '--card-product-shadow'       => 'none',
                '--card-product-padding'      => '12px 10px',
            ],
            'category' => [
                '--card-category-bg'     => 'transparent',
                '--card-category-text'   => 'var(--text-secondary, #94a3b8)',
                '--card-category-border' => 'var(--border-color, #334155)',
                '--card-category-radius' => '20px',
            ],
        ];

        $result = $defaults;
        $seen   = [];
        foreach ($this->queryRepo->getCardStyles($tenantId, $themeId) as $card) {
            $type = $card['card_type'] ?? '';
            if (!in_array($type, $posCardTypes, true) || isset($seen[$type])) continue;
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

    private static function resolvePlatformTarget(): string
    {
        $requested = strtolower(trim((string)($_GET['theme_target'] ?? '')));
        return in_array($requested, [PdoThemesRepository::TARGET_PLATFORM_ADMIN, PdoThemesRepository::TARGET_PLATFORM_HOME], true)
            ? $requested
            : PdoThemesRepository::TARGET_PLATFORM_ADMIN;
    }
}