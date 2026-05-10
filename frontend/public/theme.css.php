<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/public_context.php';

$theme = $GLOBALS['PUB_CONTEXT']['theme'] ?? [];
$etagSource = json_encode([
    'tenant' => $GLOBALS['PUB_CONTEXT']['tenant_id'] ?? 0,
    'theme'  => $theme['_debug']['theme_id'] ?? null,
    'vars'   => [
        $theme['primary'] ?? '',
        $theme['background'] ?? '',
        $theme['header_bg'] ?? '',
        count($theme['buttons'] ?? []),
        count($theme['cards'] ?? []),
    ],
], JSON_UNESCAPED_SLASHES);
$etag = '"' . sha1((string)$etagSource) . '"';

if (!headers_sent()) {
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');
    header('ETag: ' . $etag);
}

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

echo "/* QOOQZ public theme tokens - generated from public_context.php */\n";
echo pub_theme_stylesheet_css(is_array($theme) ? $theme : []);
