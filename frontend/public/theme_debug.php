<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx = $GLOBALS['PUB_CONTEXT'] ?? [];
$theme = $ctx['theme'] ?? [];
$debug = $theme['_debug'] ?? [];
$identity = $ctx['identity'] ?? [];
$tenantId = (int)($ctx['tenant_id'] ?? 1);
$lang = (string)($ctx['lang'] ?? 'en');
$requestedTarget = strtolower(trim((string)($_GET['theme_target'] ?? '')));
$target = in_array($requestedTarget, ['tenant_store', 'platform_home'], true)
    ? $requestedTarget
    : (
        (!empty($identity['is_platform_admin']) || empty($identity['resolved_tenant_id']))
            ? 'platform_home'
            : (string)($debug['theme_target'] ?? 'tenant_store')
    );

$apiQuery = [
    'theme_target' => $target,
    'lang' => $lang,
];
if ($target !== 'platform_home') {
    $apiQuery['tenant_id'] = $tenantId;
}

$homeQuery = [
    'theme_target' => 'platform_home',
];

$apiUi = pub_fetch(
    pub_api_url('public/ui') . '?' . http_build_query($apiQuery),
    4
);
$apiData = $apiUi['data'] ?? [];
$apiTheme = $apiData['theme'] ?? [];

function dbg_e(mixed $value): string
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$rows = [
    'context_tenant_id' => $tenantId,
    'lang' => $lang,
    'resolved_user_id' => $identity['resolved_user_id'] ?? null,
    'resolved_tenant_id' => $identity['resolved_tenant_id'] ?? null,
    'identity_source' => $identity['identity_source'] ?? null,
    'is_platform_admin' => $identity['is_platform_admin'] ?? false,
    'platform_role' => $identity['platform_role'] ?? null,
    'script_name' => $debug['script_name'] ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
    'theme_target' => $target,
    'theme_source' => $debug['source'] ?? 'unknown',
    'selected_theme_id' => $debug['theme_id'] ?? null,
    'settings_tenant_id' => $debug['settings_tenant_id'] ?? null,
    'requested_tenant_id' => $debug['requested_tenant_id'] ?? null,
    'has_generated_css' => $debug['has_generated_css'] ?? false,
    'color_rows' => $debug['color_rows'] ?? 0,
    'font_rows' => $debug['font_rows'] ?? 0,
    'design_rows' => $debug['design_rows'] ?? 0,
    'button_rows' => $debug['button_rows'] ?? 0,
    'card_rows' => $debug['card_rows'] ?? 0,
    'api_theme_id' => $apiTheme['id'] ?? null,
    'api_theme_tenant_id' => $apiTheme['tenant_id'] ?? null,
    'api_theme_target' => $apiTheme['target'] ?? null,
    'api_theme_source' => $apiTheme['source'] ?? null,
];

$generatedCss = (string)($theme['generated_css'] ?? '');
$cssPreview = trim(substr($generatedCss, 0, 1500));
?><!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Theme Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f6f7fb; color: #1f2937; }
        h1, h2 { margin: 0 0 16px; }
        .panel { background: #fff; border: 1px solid #dbe1ea; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 10px 8px; vertical-align: top; }
        th { width: 220px; color: #374151; }
        code, pre { font-family: Consolas, monospace; font-size: 12px; }
        pre { margin: 0; white-space: pre-wrap; word-break: break-word; background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; }
        .links a { margin-right: 10px; }
    </style>
</head>
<body>
    <h1>Theme Debug</h1>

    <div class="panel links">
        <a href="?tenant_id=<?= $tenantId ?>&theme_target=tenant_store">tenant_store</a>
        <a href="?theme_target=platform_home">platform_home</a>
        <a href="/api/public/ui?<?= dbg_e(http_build_query($apiQuery)) ?>" target="_blank">open api/public/ui</a>
        <a href="/frontend/public/index.php?<?= dbg_e(http_build_query($homeQuery)) ?>" target="_blank">open homepage</a>
    </div>

    <div class="panel">
        <h2>Resolved Theme</h2>
        <table>
            <tbody>
            <?php foreach ($rows as $label => $value): ?>
                <tr>
                    <th><?= dbg_e($label) ?></th>
                    <td><code><?= dbg_e($value) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Key Colors</h2>
        <table>
            <tbody>
                <tr><th>primary</th><td><code><?= dbg_e($theme['primary'] ?? null) ?></code></td></tr>
                <tr><th>header_bg</th><td><code><?= dbg_e($theme['header_bg'] ?? null) ?></code></td></tr>
                <tr><th>background</th><td><code><?= dbg_e($theme['background'] ?? null) ?></code></td></tr>
                <tr><th>surface</th><td><code><?= dbg_e($theme['surface'] ?? null) ?></code></td></tr>
                <tr><th>text</th><td><code><?= dbg_e($theme['text'] ?? null) ?></code></td></tr>
                <tr><th>border</th><td><code><?= dbg_e($theme['border'] ?? null) ?></code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Generated CSS Preview</h2>
        <pre><?= dbg_e($cssPreview !== '' ? $cssPreview : '[empty generated_css]') ?></pre>
    </div>
</body>
</html>
