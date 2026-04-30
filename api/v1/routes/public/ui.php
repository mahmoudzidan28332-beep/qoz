<?php
declare(strict_types=1);
/**
 * Public API sub-route: ui
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'ui') {
    $tid = $tenantId ?? 1;
    $requestedThemeTarget = strtolower(trim((string)($_GET['theme_target'] ?? 'tenant_store')));
    $themeTarget = in_array($requestedThemeTarget, ['tenant_store', 'platform_home'], true)
        ? $requestedThemeTarget
        : 'tenant_store';

    $themeSelection = null;

    if ($themeTarget === 'platform_home') {
        $themeSelection = $pdoOne(
            "SELECT id, COALESCE(tenant_id, 1) AS tenant_id
               FROM themes
              WHERE theme_scope = 'platform'
                AND theme_target = 'platform_home'
                AND is_active = 1
                AND (tenant_id = 1 OR tenant_id IS NULL)
              ORDER BY is_default DESC, id ASC
              LIMIT 1"
        );

        if (!$themeSelection) {
            $themeSelection = $pdoOne(
                "SELECT id, COALESCE(tenant_id, 1) AS tenant_id
                   FROM themes
                  WHERE theme_scope = 'platform'
                    AND theme_target = 'platform_home'
                    AND is_default = 1
                    AND (tenant_id = 1 OR tenant_id IS NULL)
                  ORDER BY is_active DESC, id ASC
                  LIMIT 1"
            );
        }
    } else {
        $themeSelection = $pdoOne(
            "SELECT t.id, COALESCE(t.tenant_id, 1) AS tenant_id
               FROM tenant_theme_overrides o
               INNER JOIN themes t ON t.id = o.theme_id
              WHERE o.tenant_id = ?
                AND o.setting_type = 'theme_selection'
                AND o.setting_key = 'tenant_store_active_theme_id'
              ORDER BY o.id DESC
              LIMIT 1",
            [$tid]
        );
        if (!$themeSelection) {
            $themeSelection = $pdoOne(
                "SELECT id, COALESCE(tenant_id, 1) AS tenant_id
                   FROM themes
                  WHERE theme_target = 'tenant_store'
                    AND (
                         (theme_scope = 'tenant' AND tenant_id = ? AND is_active = 1)
                         OR
                         (theme_scope = 'global' AND (tenant_id = 1 OR tenant_id IS NULL) AND is_default = 1)
                    )
                  ORDER BY theme_scope = 'tenant' DESC, id ASC
                  LIMIT 1",
                [$tid]
            );
        }
    }

    $uiThemeId = $themeSelection ? (int)$themeSelection['id'] : null;
    $uiThemeTenantId = $themeSelection ? max(1, (int)($themeSelection['tenant_id'] ?? 1)) : $tid;

    if ($uiThemeId) {
        if ($themeTarget === 'platform_home') {
            $colors = $pdoList('SELECT setting_key AS key, color_value AS value, category FROM color_settings WHERE is_active = 1 AND theme_id = ? ORDER BY sort_order, id', [$uiThemeId]);
            $fonts = $pdoList('SELECT setting_key, font_family, font_size, font_weight, line_height, category FROM font_settings WHERE is_active = 1 AND theme_id = ? ORDER BY sort_order', [$uiThemeId]);
            $designs = $pdoList('SELECT setting_key, setting_value, setting_type, category FROM design_settings WHERE is_active = 1 AND theme_id = ? ORDER BY sort_order', [$uiThemeId]);
            $buttons = $pdoList('SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color FROM button_styles WHERE is_active = 1 AND theme_id = ? ORDER BY button_type', [$uiThemeId]);
            $cards = $pdoList('SELECT id, tenant_id, theme_id, name, slug, card_type, background_color, text_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active FROM card_styles WHERE is_active = 1 AND theme_id = ? ORDER BY card_type', [$uiThemeId]);
        } else {
            $colors = $pdoList('SELECT setting_key AS key, color_value AS value, category FROM color_settings WHERE is_active = 1 AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL))) ORDER BY sort_order, id', [$uiThemeId, $uiThemeTenantId]);
            $fonts = $pdoList('SELECT setting_key, font_family, font_size, font_weight, line_height, category FROM font_settings WHERE is_active = 1 AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL))) ORDER BY sort_order', [$uiThemeId, $uiThemeTenantId]);
            $designs = $pdoList('SELECT setting_key, setting_value, setting_type, category FROM design_settings WHERE is_active = 1 AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL))) ORDER BY sort_order', [$uiThemeId, $uiThemeTenantId]);
            $buttons = $pdoList('SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color FROM button_styles WHERE is_active = 1 AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL))) ORDER BY button_type', [$uiThemeId, $uiThemeTenantId]);
            $cards = $pdoList('SELECT id, tenant_id, theme_id, name, slug, card_type, background_color, text_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active FROM card_styles WHERE is_active = 1 AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL))) ORDER BY card_type', [$uiThemeId, $uiThemeTenantId]);
        }
    } else {
        $colors = $pdoList('SELECT setting_key AS key, color_value AS value, category FROM color_settings WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, id', [$tid]);
        $fonts = $pdoList('SELECT setting_key, font_family, font_size, font_weight, line_height, category FROM font_settings WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order', [$tid]);
        $designs = $pdoList('SELECT setting_key, setting_value, setting_type, category FROM design_settings WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order', [$tid]);
        $buttons = $pdoList('SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color FROM button_styles WHERE tenant_id = ? AND is_active = 1 ORDER BY button_type', [$tid]);
        $cards = $pdoList('SELECT id, tenant_id, theme_id, name, slug, card_type, background_color, text_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active FROM card_styles WHERE tenant_id = ? AND is_active = 1 ORDER BY card_type', [$tid]);
    }

    // Generate CSS string â€” mirrors AdminUiThemeLoader::generateCss() exactly.
    // Sanitize values: strip {}, ; and backticks to prevent CSS injection.
    $sanitize = static fn(string $v): string => preg_replace('/[{};`]/', '', trim($v));
    $safeCssIdent = static fn(string $s): string => preg_replace('/[^a-z0-9-]/', '-', strtolower($s));

    $css = ":root {\n";

    // â”€â”€ Color settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $colorKeyVal = [];
    foreach ($colors as $c) {
        if (empty($c['key']) || empty($c['value'])) continue;
        $colorKeyVal[(string)$c['key']] = (string)$c['value'];
        $hyphen = str_replace('_', '-', strtolower((string)$c['key']));
        $css .= "  --{$hyphen}: " . $sanitize((string)$c['value']) . ";\n";
    }
    // CSS variable aliases: bridge DB underscore names to --pub-* / --color-*
    $uiAliases = [
        'primary_color'        => ['color-primary',  'pub-primary'],
        'secondary_color'      => ['color-secondary', 'pub-secondary'],
        'accent_color'         => ['color-accent',    'pub-accent'],
        'background_main'      => ['pub-bg'],
        'background_secondary' => ['pub-surface'],
        'text_primary'         => ['pub-text'],
        'text_secondary'       => ['pub-muted'],
        'border_color'         => ['pub-border'],
    ];
    foreach ($uiAliases as $srcKey => $aliases) {
        if (empty($colorKeyVal[$srcKey])) continue;
        $val = $sanitize($colorKeyVal[$srcKey]);
        foreach ($aliases as $alias) {
            $css .= "  --{$alias}: {$val};\n";
        }
    }

    // â”€â”€ Font settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    foreach ($fonts as $f) {
        if (empty($f['setting_key'])) continue;
        $sk = $safeCssIdent((string)$f['setting_key']);
        if (!empty($f['font_family'])) $css .= "  --{$sk}-family: " . $sanitize((string)$f['font_family']) . ";\n";
        if (!empty($f['font_size']))   $css .= "  --{$sk}-size: "   . $sanitize((string)$f['font_size'])   . ";\n";
        if (!empty($f['font_weight'])) $css .= "  --{$sk}-weight: " . $sanitize((string)$f['font_weight']) . ";\n";
        if (!empty($f['line_height'])) $css .= "  --{$sk}-line-height: " . $sanitize((string)$f['line_height']) . ";\n";
    }

    // â”€â”€ Design settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    foreach ($designs as $d) {
        if (empty($d['setting_key']) || empty($d['setting_value'])) continue;
        $type = strtolower($d['setting_type'] ?? 'text');
        if (!in_array($type, ['color', 'text', 'number'], true)) continue;
        $css .= '  --' . $safeCssIdent((string)$d['setting_key']) . ': ' . $sanitize((string)$d['setting_value']) . ";\n";
    }

    // â”€â”€ Card styles â†’ :root CSS variables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $posCardTypes = ['product', 'category'];
    $posCardSeen  = [];
    foreach ($cards as $c) {
        if (empty($c['slug'])) continue;
        $slug = $safeCssIdent((string)$c['slug']);
        if (!empty($c['background_color'])) $css .= "  --card-{$slug}-bg: "          . $sanitize((string)$c['background_color']) . ";\n";
        if (!empty($c['border_color']))     $css .= "  --card-{$slug}-border: "       . $sanitize((string)$c['border_color']) . ";\n";
        if (!empty($c['border_radius']))    $css .= "  --card-{$slug}-radius: "       . (int)$c['border_radius'] . "px;\n";
        if (!empty($c['shadow_style']))     $css .= "  --card-{$slug}-shadow: "       . $sanitize((string)$c['shadow_style']) . ";\n";
        if (!empty($c['padding']))          $css .= "  --card-{$slug}-padding: "      . $sanitize((string)$c['padding']) . ";\n";
        if (!empty($c['text_color']))       $css .= "  --card-{$slug}-text: "         . $sanitize((string)$c['text_color']) . ";\n";
        if (!empty($c['border_width']))     $css .= "  --card-{$slug}-border-width: " . (int)$c['border_width'] . "px;\n";

        // POS card_type aliases â€” first active card per type wins
        $cardType = $c['card_type'] ?? '';
        if (in_array($cardType, $posCardTypes, true) && !isset($posCardSeen[$cardType])) {
            $posCardSeen[$cardType] = true;
            $tp = "--card-{$cardType}";
            if (!empty($c['background_color'])) $css .= "  {$tp}-bg: "          . $sanitize((string)$c['background_color']) . ";\n";
            if (!empty($c['text_color']))       $css .= "  {$tp}-text: "         . $sanitize((string)$c['text_color']) . ";\n";
            if (!empty($c['border_color']))     $css .= "  {$tp}-border: "       . $sanitize((string)$c['border_color']) . ";\n";
            if (!empty($c['border_width']))     $css .= "  {$tp}-border-width: " . (int)$c['border_width'] . "px;\n";
            if (!empty($c['border_radius']))    $css .= "  {$tp}-radius: "       . (int)$c['border_radius'] . "px;\n";
            if (!empty($c['shadow_style']))     $css .= "  {$tp}-shadow: "       . $sanitize((string)$c['shadow_style']) . ";\n";
            if (!empty($c['padding']))          $css .= "  {$tp}-padding: "      . $sanitize((string)$c['padding']) . ";\n";
        }
    }

    $css .= "}\n";

    // â”€â”€ Button styles â†’ concrete CSS classes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    foreach ($buttons as $b) {
        if (empty($b['slug'])) continue;
        $slug = $safeCssIdent((string)$b['slug']);
        $css .= ".btn-{$slug} {\n";
        if (!empty($b['background_color'])) $css .= "  background-color: " . $sanitize((string)$b['background_color']) . ";\n";
        if (!empty($b['text_color']))       $css .= "  color: "             . $sanitize((string)$b['text_color']) . ";\n";
        if (!empty($b['border_color'])) {
            $bw = (int)($b['border_width'] ?? 1);
            $css .= "  border: {$bw}px solid " . $sanitize((string)$b['border_color']) . ";\n";
        }
        if (!empty($b['border_radius'])) $css .= "  border-radius: " . (int)$b['border_radius'] . "px;\n";
        if (!empty($b['padding']))        $css .= "  padding: "       . $sanitize((string)$b['padding']) . ";\n";
        if (!empty($b['font_size']))      $css .= "  font-size: "     . $sanitize((string)$b['font_size']) . ";\n";
        if (!empty($b['font_weight']))    $css .= "  font-weight: "   . $sanitize((string)$b['font_weight']) . ";\n";
        $css .= "  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;\n";
        $css .= "}\n";
        $hasHover = !empty($b['hover_background_color'])
                 || !empty($b['hover_text_color'])
                 || !empty($b['hover_border_color']);
        if ($hasHover) {
            $css .= ".btn-{$slug}:hover,\n.btn-{$slug}:focus-visible {\n";
            if (!empty($b['hover_background_color'])) $css .= "  background-color: " . $sanitize((string)$b['hover_background_color']) . ";\n";
            if (!empty($b['hover_text_color']))       $css .= "  color: "             . $sanitize((string)$b['hover_text_color']) . ";\n";
            if (!empty($b['hover_border_color']))     $css .= "  border-color: "      . $sanitize((string)$b['hover_border_color']) . ";\n";
            $css .= "}\n";
        }
    }

    // â”€â”€ Card styles â†’ concrete CSS classes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $hoverEffectMap = [
        'lift'       => "transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.15);",
        'zoom'       => "transform: scale(1.03);",
        'shadow'     => "box-shadow: 0 8px 24px rgba(0,0,0,0.2);",
        'border'     => "border-color: var(--primary-color, #3B82F6);",
        'brightness' => "filter: brightness(1.08);",
    ];
    foreach ($cards as $c) {
        if (empty($c['slug'])) continue;
        $slug   = $safeCssIdent((string)$c['slug']);
        $effect = strtolower(trim((string)($c['hover_effect'] ?? 'none')));
        $css .= ".card-{$slug} {\n";
        if (!empty($c['background_color'])) $css .= "  background-color: " . $sanitize((string)$c['background_color']) . ";\n";
        if (!empty($c['border_color'])) {
            $bw = (int)($c['border_width'] ?? 1);
            $css .= "  border: {$bw}px solid " . $sanitize((string)$c['border_color']) . ";\n";
        }
        if (!empty($c['border_radius'])) $css .= "  border-radius: " . (int)$c['border_radius'] . "px;\n";
        if (!empty($c['shadow_style']))  $css .= "  box-shadow: "    . $sanitize((string)$c['shadow_style']) . ";\n";
        if (!empty($c['padding']))       $css .= "  padding: "       . $sanitize((string)$c['padding']) . ";\n";
        if (!empty($c['text_align']) && in_array($c['text_align'], ['left', 'center', 'right'], true)) {
            $css .= "  text-align: {$c['text_align']};\n";
        }
        if ($effect !== 'none' && isset($hoverEffectMap[$effect])) {
            $css .= "  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, filter 0.2s ease;\n";
        }
        $css .= "}\n";
        if (!empty($c['image_aspect_ratio'])) {
            $ratio = preg_replace('/[^0-9:]/', '', (string)$c['image_aspect_ratio']);
            $ratio = str_replace(':', '/', $ratio);
            if ($ratio) $css .= ".card-{$slug} .pub-cat-img-wrap { aspect-ratio: {$ratio}; }\n";
        }
        if ($effect !== 'none' && isset($hoverEffectMap[$effect])) {
            $css .= ".card-{$slug}:hover {\n  " . $hoverEffectMap[$effect] . "\n}\n";
        }
    }

    ResponseFormatter::success([
        'ok'           => true,
        'theme'        => [
            'id'         => $uiThemeId,
            'tenant_id'  => $uiThemeTenantId,
            'target'     => $themeTarget,
            'source'     => $themeSelection ? 'resolved' : 'fallback',
        ],
        'ui'           => $GLOBALS['PUBLIC_UI'] ?? [],
        'colors'       => $colors,
        'fonts'        => $fonts,
        'design'       => $designs,
        'buttons'      => $buttons,
        'cards'        => $cards,
        'generated_css'=> $css,
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Products
 * GET /api/public/products[/{id}]
 * ----------------------------------------------------- */
