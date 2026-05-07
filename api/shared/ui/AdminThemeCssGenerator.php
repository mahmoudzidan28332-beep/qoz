<?php
declare(strict_types=1);

// api/shared/ui/AdminThemeCssGenerator.php

/**
 * AdminThemeCssGenerator
 *
 * Generates CSS variables and class rules from theme data arrays.
 * Extracted from AdminUiThemeLoader to apply Single Responsibility Principle.
 */
final class AdminThemeCssGenerator
{
    /** Card style card_type values that map to POS product/category CSS variables. */
    private const POS_CARD_TYPES = ['product', 'category'];

    /**
     * Generate complete CSS from theme data.
     *
     * CSS variable names are converted to kebab-case (primary_color → --primary-color)
     * to match the CSS framework's var() references.
     *
     * All values written into CSS are sanitized to prevent injection ({};).
     */
    public function generate(array $themeData): string
    {
        $hyphenateKey    = static fn(string $key): string => str_replace('_', '-', strtolower($key));
        $sanitizeCssValue = static fn(string $v): string => preg_replace('/[{};`]/', '', trim($v));
        $safeCssIdent    = static fn(string $s): string => preg_replace('/[^a-z0-9-]/', '-', strtolower($s));

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
