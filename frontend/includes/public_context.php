<?php
/**
 * frontend/includes/public_context.php
 *
 * Public context bootstrap for QOOQZ frontend pages.
 * - Loads theme colors/settings from API
 * - Auto-detects language from HTTP_ACCEPT_LANGUAGE (no visible language button)
 * - Loads translations from frontend/languages/{code}.json
 * - Supports all world languages; RTL auto-detected from translation file
 * - No auth required (guest-friendly)
 */

if (!defined('FRONTEND_PUBLIC_CONTEXT')) {
    define('FRONTEND_PUBLIC_CONTEXT', true);
}

/* -------------------------------------------------------
 * 1. Base path & environment
 * ----------------------------------------------------- */
defined('FRONTEND_BASE') || define('FRONTEND_BASE', dirname(__DIR__));

$envFile = FRONTEND_BASE . '/config/app.php';
$appConfig = is_readable($envFile) ? (require $envFile) : [];

$apiConfigFile = FRONTEND_BASE . '/config/api.php';
$apiConfig = is_readable($apiConfigFile) ? (require $apiConfigFile) : [];

/* -------------------------------------------------------
 * 2. Session â€” mirrors admin/includes/admin_context.php exactly.
 *    DOCUMENT_ROOT is the primary path (same as admin uses).
 * ----------------------------------------------------- */
// If PHP session.auto_start=1 (common on shared hosting) started a session with the
// wrong name/settings before our code runs, close it so we can restart correctly.
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE && session_name() !== 'APP_SESSID') {
    session_write_close();
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // Primary: DOCUMENT_ROOT (same as admin_context.php line 28)
    // Fallback: dirname(FRONTEND_BASE) for CLI / non-standard webroot setups
    $__sharedSession = null;
    foreach ([
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php',
        dirname(FRONTEND_BASE)           . '/api/shared/config/session.php',
    ] as $__c) {
        if ($__c && file_exists($__c)) { $__sharedSession = $__c; break; }
    }
    unset($__c);

    if ($__sharedSession) {
        require_once $__sharedSession;
    } else {
        // Last-resort manual fallback
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        if (!is_dir($__sp)) $__sp = dirname(FRONTEND_BASE) . '/api/storage/sessions';
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
        unset($__sp);
    }
    unset($__sharedSession);
}

/* -------------------------------------------------------
 * 2b. Cache-Control â€” prevent proxies/CDNs from caching
 *     PHP pages (ensures fresh content after every deploy).
 * ----------------------------------------------------- */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* -------------------------------------------------------
 * 3. Language resolution (no visible language button)
 *    Priority: URL ?lang=xx â†’ session â†’ app default_lang
 *    Browser Accept-Language is NOT used (platform default takes precedence).
 * ----------------------------------------------------- */
if (!function_exists('pub_detect_lang')) {
    /**
     * Detect best language code from HTTP_ACCEPT_LANGUAGE.
     * Returns the 2-letter language code if a translation file exists.
     * (Kept for potential future use but not called in the main flow.)
     */
    function pub_detect_lang(string $default = 'en'): string {
        $langDir = FRONTEND_BASE . '/languages';
        $avail   = [];

        // Collect available language codes from frontend/languages/*.json
        foreach (glob($langDir . '/*.json') ?: [] as $f) {
            $code = basename($f, '.json');
            if (preg_match('/^[a-z]{2,5}$/', $code)) {
                $avail[$code] = true;
            }
        }

        if (empty($avail)) {
            return $default;
        }

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!$header) {
            return isset($avail[$default]) ? $default : array_key_first($avail);
        }

        // Parse quality-weighted list, e.g. "ar,en-US;q=0.9,en;q=0.8,fr;q=0.7"
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', $part, $m)) {
                $q    = isset($m[2]) ? (float)$m[2] : 1.0;
                $code = strtolower(substr($m[1], 0, 2));
                $candidates[$code] = max($candidates[$code] ?? 0, $q);
            }
        }
        // Sort by quality descending
        arsort($candidates);

        foreach (array_keys($candidates) as $code) {
            if (isset($avail[$code])) {
                return $code;
            }
        }
        return isset($avail[$default]) ? $default : array_key_first($avail);
    }
}

/* -------------------------------------------------------
 * 4. Resolve active language
 * ----------------------------------------------------- */
// Priority: URL ?lang=xx â†’ user preferred_language â†’ session pub_lang â†’ app default_lang
if (isset($_GET['lang']) && preg_match('/^[a-z]{2,5}$/', $_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['pub_lang'] = $lang;   // save explicit user choice
} elseif (!empty($_SESSION['user']['preferred_language'])) {
    // Use the logged-in user's preferred language
    $lang = (string)$_SESSION['user']['preferred_language'];
} elseif (!empty($_SESSION['pub_lang'])) {
    $lang = $_SESSION['pub_lang'];
} else {
    $lang = pub_detect_lang($appConfig['default_lang'] ?? 'en');
}

// Fallback to 'en' if no translation file exists
$langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
if (!is_readable($langFile)) {
    $lang     = $appConfig['default_lang'] ?? 'en';
    $langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
}

/* -------------------------------------------------------
 * 5. Load translations
 * ----------------------------------------------------- */
if (!function_exists('pub_load_translations')) {
    /**
     * Load translation file and return array.
     * Falls back to English then empty array.
     */
    function pub_load_translations(string $langCode): array {
        $base = FRONTEND_BASE . '/languages/';
        $f    = $base . $langCode . '.json';
        if (!is_readable($f)) {
            $f = $base . 'en.json';
        }
        if (!is_readable($f)) {
            return [];
        }
        $data = json_decode(file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('t')) {
    /**
     * Translate a dot-separated key, e.g. t('nav.home') or t('hero.title').
     *
     * @param string            $key     Dot-separated translation key.
     * @param string|array      $replace When a string, used as the fallback default if
     *                                   the key is missing.  When an array, used for
     *                                   {placeholder} substitution (existing behaviour).
     */
    function t(string $key, string|array $replace = []): string {
        $strings = $GLOBALS['PUB_STRINGS'] ?? [];
        $parts   = explode('.', $key, 2);
        $group   = $parts[0] ?? '';
        $sub     = $parts[1] ?? '';

        // Determine the default fallback value.
        $default = is_string($replace) ? $replace : $key;

        $val = $sub !== ''
            ? ($strings[$group][$sub] ?? $default)
            : ($strings[$group] ?? $default);

        if (!is_string($val)) {
            $val = $default;
        }

        // Simple placeholder replacement {key} => value (only when $replace is an array)
        if (is_array($replace)) {
            foreach ($replace as $k => $v) {
                $val = str_replace('{' . $k . '}', (string)$v, $val);
            }
        }
        return $val;
    }
}

$_translations = pub_load_translations($lang);
$dir = $_translations['dir'] ?? (in_array($lang, ['ar','fa','ur','he']) ? 'rtl' : 'ltr');
$GLOBALS['PUB_STRINGS'] = $_translations;

/* -------------------------------------------------------
 * 4. API base URL (used for server-side fetch)
 * ----------------------------------------------------- */
if (!function_exists('pub_api_url')) {
    function pub_api_url(string $path = ''): string {
        // Detect scheme + host for self-referencing API calls
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim($scheme . '://' . $host . '/api', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/* -------------------------------------------------------
 * 5. Lightweight HTTP fetch (curl/file_get_contents)
 * ----------------------------------------------------- */
if (!function_exists('pub_fetch')) {
    /**
     * Fetch JSON from the internal API.
     * Returns decoded array or [] on failure.
     */
    function pub_fetch(string $url, int $timeout = 4): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx  = stream_context_create(['http' => ['timeout' => $timeout]]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return [];
        $decoded = @json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/* -------------------------------------------------------
 * 6. Dynamic Product Discounts Resolver
 * ----------------------------------------------------- */
if (!function_exists('pub_get_product_discounts')) {
    function pub_get_product_discounts(?PDO $pdo, array $productIds): array {
        if (!$pdo || empty($productIds)) return [];
        $pids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($pids)) return [];
        $phds = implode(',', array_fill(0, count($pids), '?'));
        $discounts = [];
        try {
            $stmt = $pdo->prepare("
                SELECT p.id AS product_id,
                       da.action_type, da.action_value, d.currency_code
                FROM products p
                JOIN discount_scopes ds ON 
                   (ds.scope_type = 'product' AND ds.scope_id = p.id) 
                   OR (ds.scope_type = 'category' AND ds.scope_id IN (SELECT category_id FROM product_categories WHERE product_id = p.id))
                   OR (ds.scope_type = 'entity' AND ds.scope_id IN (SELECT pp.entity_id FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id IS NOT NULL))
                JOIN discounts d ON d.id = ds.discount_id
                JOIN discount_actions da ON da.discount_id = d.id
                WHERE p.id IN ($phds)
                  AND d.status = 'active'
                  AND (d.starts_at IS NULL OR d.starts_at <= NOW())
                  AND (d.ends_at IS NULL OR d.ends_at >= NOW())
                ORDER BY p.id ASC, d.priority DESC, da.id ASC
            ");
            $stmt->execute($pids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $drow) {
                $pid = (int)$drow['product_id'];
                if (isset($discounts[$pid])) continue; // Highest priority wins
                $v = $drow['action_value'] ?? '';
                $discounts[$pid] = match(true) {
                    in_array($drow['action_type'], ['percentage_discount','percent_discount','percentage'], true)
                        => t('discounts.percent_off', ['value' => number_format((float)$v, 0), 'default' => '-' . number_format((float)$v, 0) . '%']),
                    in_array($drow['action_type'], ['fixed_discount','fixed_amount','fixed'], true)
                        => t('discounts.fixed_off', ['value' => number_format((float)$v, 2) . ' ' . trim($drow['currency_code'] ?? ''), 'default' => '-' . number_format((float)$v, 2) . ' ' . trim($drow['currency_code'] ?? '')]),
                    $drow['action_type'] === 'free_shipping' => t('discounts.free_shipping', 'Free Shipping'),
                    default => (string)$v,
                };
            }
        } catch (Throwable $e) {
            error_log('[pub_get_product_discounts] Error: ' . $e->getMessage());
        }
        return $discounts;
    }
}

/* -------------------------------------------------------
 * 6. Theme / color settings â€” loaded directly from DB via PDO
 *    Also loads: design_settings, font_settings, button_styles, card_styles
 *    Generates complete CSS string stored in $theme['generated_css']
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    function pub_load_theme(int $tenantId = 1, array $identity = []): array {
        // Defaults (fallback when DB is unreachable)
        $defaults = [
            'primary'    => '#03874e',
            'primary_hover' => '#00ff00',
            'secondary'  => '#10B981',
            'accent'     => '#F59E0B',
            'background' => '#0d0d0d',
            'surface'    => '#4f4f4f',
            'text'       => '#FFFFFF',
            'text_muted' => '#B0B0B0',
            'border'     => '#333333',
            'header_bg'        => '#1e2533',
            'header_text_color'=> '#FFFFFF',
            'footer_bg'        => '#1e2a38',
            'footer_text_color'=> '#B0B0B0',
            'sidebar_bg'          => '#3f363f',
            'sidebar_text'        => '#e8e8e8',
            'sidebar_toggle'      => '#633b3b',
            'sidebar_toggle_hover'=> '#c49121',
            'sidebar_card_bg'     => '#3f363f',
            'sidebar_card_text'   => '#de1717',
            'success'    => '#10b981',
            'warning'    => '#f59e0b',
            'danger'     => '#ef4444',
            'error'      => '#EF4444',
            'info'       => '#22C55E',
            'input_text'       => '#494646',
            'input_placeholder'=> '#6B7280',
            'logo_url'   => '',          // set from design_settings WHERE setting_key='logo_url'
            'generated_css' => '',
            'fonts'      => [],
            'design'     => [],
            'buttons'    => [],
            'cards'      => [],
        ];

        // Session cache is checked after theme_id lookup (inside PDO block below)
        // to ensure cache key includes theme_id and stale entries are not returned.

        // Reuse the shared PDO connection (avoids opening a second connection per request).
        // pub_get_pdo() is defined later in this file (line ~538) but will already be
        // registered by the time pub_load_theme() is *called* at line ~695.
        $pdo = pub_get_pdo();

        if ($pdo instanceof PDO) {
            try {

                $colors  = [];
                $fonts   = [];
                $designs = [];
                $buttons = [];
                $cards   = [];

                // Resolve theme using the unified public identity + target model.
                $scriptName = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
                $themeTarget = pub_resolve_theme_target($identity, $tenantId);

                $themeDbId = null;
                $settingsTenantId = ($themeTarget === 'platform_home')
                    ? 1
                    : ($tenantId > 0 ? $tenantId : 1);
                try {
                    $thRow = null;

                    if (!$thRow && $themeTarget === 'platform_home') {
                        $thSt = $pdo->prepare(
                            "SELECT id, COALESCE(tenant_id, 1) AS settings_tenant_id
                               FROM themes
                              WHERE theme_scope = 'platform'
                                AND theme_target = 'platform_home'
                                AND is_active = 1
                                AND (tenant_id = 1 OR tenant_id IS NULL)
                              ORDER BY is_default DESC, id ASC
                              LIMIT 1"
                        );
                        $thSt->execute();
                        $thRow = $thSt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if (!$thRow) {
                            $thSt = $pdo->prepare(
                                "SELECT id, COALESCE(tenant_id, 1) AS settings_tenant_id
                                   FROM themes
                                  WHERE theme_scope = 'platform'
                                    AND theme_target = 'platform_home'
                                    AND is_default = 1
                                    AND (tenant_id = 1 OR tenant_id IS NULL)
                                  ORDER BY is_active DESC, id ASC
                                  LIMIT 1"
                            );
                            $thSt->execute();
                            $thRow = $thSt->fetch(PDO::FETCH_ASSOC) ?: null;
                        }
                    }

                    if (!$thRow && $themeTarget === 'tenant_store' && $tenantId > 0) {
                        $thSt = $pdo->prepare(
                            "SELECT t.id, COALESCE(t.tenant_id, 1) AS settings_tenant_id
                               FROM tenant_theme_overrides o
                               INNER JOIN themes t ON t.id = o.theme_id
                              WHERE o.tenant_id = ?
                                AND o.setting_type = 'theme_selection'
                                AND o.setting_key = 'tenant_store_active_theme_id'
                              ORDER BY o.id DESC
                              LIMIT 1"
                        );
                        $thSt->execute([$tenantId]);
                        $thRow = $thSt->fetch(PDO::FETCH_ASSOC) ?: null;
                    }

                    if (!$thRow && $themeTarget === 'tenant_store') {
                        $thSt = $pdo->prepare(
                            "SELECT id, COALESCE(tenant_id, 1) AS settings_tenant_id
                               FROM themes
                              WHERE theme_target = 'tenant_store'
                                AND (
                                      (theme_scope = 'tenant' AND tenant_id = ? AND is_active = 1)
                                      OR
                                      (theme_scope = 'global' AND (tenant_id = 1 OR tenant_id IS NULL) AND is_default = 1)
                                    )
                              ORDER BY theme_scope = 'tenant' DESC, id ASC
                              LIMIT 1"
                        );
                        $thSt->execute([$tenantId]);
                        $thRow = $thSt->fetch(PDO::FETCH_ASSOC) ?: null;
                    }

                    $themeDbId = $thRow ? (int)$thRow['id'] : null;
                    if ($thRow && isset($thRow['settings_tenant_id'])) {
                        $settingsTenantId = max(1, (int)$thRow['settings_tenant_id']);
                    }
                } catch (Throwable $_) {
                    // themes table missing or inaccessible â€” continue without theme_id filter.
                    // To log: error_log('[pub_load_theme] themes table unavailable: ' . $_->getMessage());
                    $themeDbId = null;
                }

                $thIdCond = '';
                $thExtraParams = [];
                if ($themeDbId) {
                    if ($themeTarget === 'platform_home') {
                        $thIdCond = ' AND theme_id = ?';
                        $thExtraParams = [$themeDbId];
                    } else {
                        $thIdCond = ' AND (theme_id = ? OR (theme_id IS NULL AND (tenant_id = ? OR tenant_id IS NULL)))';
                        $thExtraParams = [$themeDbId, $settingsTenantId];
                    }
                }
                $thExtraParamCount = count($thExtraParams);
                $thP = static function(array $base) use ($thExtraParams): array {
                    return array_merge($base, $thExtraParams);
                };

                // Helper: run a query with optional theme_id filter; if the column is
                // absent in the target table, automatically retry without the filter.
                $safeList = static function(string $sql, array $params) use ($pdo, $themeDbId, $thIdCond, $thExtraParamCount): array {
                    try {
                        $st = $pdo->prepare($sql);
                        $st->execute($params);
                        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $_) {
                        if ($themeDbId !== null && $thExtraParamCount > 0) {
                            // theme_id column may not exist in the target table â€” retry without the filter.
                            // $thP() always appends theme_id as the LAST param, so array_pop() removes it.
                            try {
                                $sqlFallback = str_replace($thIdCond, '', $sql);
                                $params = array_slice($params, 0, count($params) - $thExtraParamCount);
                                $st2 = $pdo->prepare($sqlFallback);
                                $st2->execute($params);
                                return $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            } catch (Throwable $__) {}
                        }
                        return [];
                    }
                };

                if ($themeTarget === 'platform_home' && $themeDbId) {
                    $colorRows = $safeList(
                        'SELECT setting_key, color_value FROM color_settings WHERE is_active = 1' . $thIdCond . ' ORDER BY sort_order, id',
                        $thP([])
                    );

                    $fonts = $safeList(
                        'SELECT setting_key, font_family, font_size, font_weight, line_height FROM font_settings WHERE is_active = 1' . $thIdCond . ' ORDER BY sort_order',
                        $thP([])
                    );

                    $designs = $safeList(
                        'SELECT setting_key, setting_value, setting_type FROM design_settings WHERE is_active = 1' . $thIdCond . ' ORDER BY sort_order',
                        $thP([])
                    );
                } else {
                    // color_settings: setting_key, color_value
                    $colorRows = $safeList(
                        'SELECT setting_key, color_value FROM color_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order, id',
                        $thP([$settingsTenantId])
                    );

                    // font_settings: setting_key, font_family, font_size, font_weight, line_height
                    $fonts = $safeList(
                        'SELECT setting_key, font_family, font_size, font_weight, line_height FROM font_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order',
                        $thP([$settingsTenantId])
                    );

                    // design_settings: setting_key, setting_value
                    $designs = $safeList(
                        'SELECT setting_key, setting_value, setting_type FROM design_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order',
                        $thP([$settingsTenantId])
                    );
                }

                // button_styles
                $buttons = $safeList(
                    (($themeTarget === 'platform_home' && $themeDbId)
                        ? 'SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color FROM button_styles WHERE is_active = 1'
                        : 'SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color FROM button_styles WHERE tenant_id = ? AND is_active = 1')
                    . $thIdCond . ' ORDER BY button_type',
                    ($themeTarget === 'platform_home' && $themeDbId) ? $thP([]) : $thP([$settingsTenantId])
                );

                // card_styles â€” use SELECT * to match AdminUiThemeLoader::getCardStyles() and remain
                // safe even when optional columns (e.g. text_color) haven't been added via migration yet.
                $cards = $safeList(
                    (($themeTarget === 'platform_home' && $themeDbId)
                        ? 'SELECT * FROM card_styles WHERE is_active = 1'
                        : 'SELECT * FROM card_styles WHERE tenant_id = ? AND is_active = 1')
                    . $thIdCond . ' ORDER BY card_type',
                    ($themeTarget === 'platform_home' && $themeDbId) ? $thP([]) : $thP([$settingsTenantId])
                );

                if ($colorRows || $fonts || $designs || $buttons || $cards) {
                    $theme = $defaults;

                    // Map color_settings keys to theme keys
                    // Covers both possible naming conventions (noun_adjective vs adjective_noun)
                    $colorMap = [
                        'primary_color'        => 'primary',
                        'primary_hover'        => 'primary_hover',
                        'secondary_color'      => 'secondary',
                        'accent_color'         => 'accent',
                        // "Main Background" â€” try both naming variants
                        'background_main'      => 'background',
                        'main_background'      => 'background',
                        'background_color_main'=> 'background',
                        // "Secondary Background"
                        'background_secondary' => 'surface',
                        'secondary_background' => 'surface',
                        // "Primary Text"
                        'text_primary'         => 'text',
                        'primary_text'         => 'text',
                        'text_color_primary'   => 'text',
                        // "Secondary Text"
                        'text_secondary'       => 'text_muted',
                        'secondary_text'       => 'text_muted',
                        // Border
                        'border_color'         => 'border',
                        // Header/Footer background and text â€” all naming variants
                        'header_bg'            => 'header_bg',
                        'header_bg_color'      => 'header_bg',
                        'header_background'    => 'header_bg',
                        'header_text'          => 'header_text_color',
                        'header_text_color'    => 'header_text_color',
                        'footer_bg'            => 'footer_bg',
                        'footer_bg_color'      => 'footer_bg',
                        'footer_background'    => 'footer_bg',
                        'footer_text'          => 'footer_text_color',
                        'footer_text_color'    => 'footer_text_color',
                        // Sidebar
                        'sidebar_background'      => 'sidebar_bg',
                        'sidebar_text'            => 'sidebar_text',
                        'sidebar_toggle_bg'       => 'sidebar_toggle',
                        'sidebar_toggle_bg_hover' => 'sidebar_toggle_hover',
                        'sidebar_card_background' => 'sidebar_card_bg',
                        'sidebar_card_text'       => 'sidebar_card_text',
                        // Status
                        'success_color'        => 'success',
                        'warning_color'        => 'warning',
                        'danger_color'         => 'danger',
                        'error_color'          => 'error',
                        'info_color'           => 'info',
                        // Input
                        'input_text'           => 'input_text',
                        'input_placeholder'    => 'input_placeholder',
                    ];
                    foreach ($colorRows as $row) {
                        $k = $row['setting_key'] ?? '';
                        $v = $row['color_value'] ?? '';
                        if (!$v) continue;
                        $mapped = $colorMap[$k] ?? null;
                        if ($mapped) $theme[$mapped] = $v;
                        $colors[$k] = $v;
                    }
                    // Track whether any color_settings row explicitly configured header_bg
                    $_headerBgSet = !empty($colors['header_bg']) || !empty($colors['header_bg_color']) || !empty($colors['header_background']);
                    // Also fill theme keys from color-type design_settings when color_settings is absent.
                    // Covers all naming variants; only overwrites if color_settings didn't already set it.
                    $dColorThemeMap = [
                        'header_bg'          => 'header_bg',
                        'header_bg_color'    => 'header_bg',
                        'header_background'  => 'header_bg',
                        'footer_bg'          => 'footer_bg',
                        'footer_bg_color'    => 'footer_bg',
                        'footer_background'  => 'footer_bg',
                        'header_text'        => 'header_text_color',
                        'header_text_color'  => 'header_text_color',
                        'footer_text'        => 'footer_text_color',
                        'footer_text_color'  => 'footer_text_color',
                    ];
                    foreach ($designs as $_d) {
                        if (($_d['setting_type'] ?? '') !== 'color' || empty($_d['setting_value'])) continue;
                        $_dk = $_d['setting_key'] ?? '';
                        if (!isset($dColorThemeMap[$_dk])) continue;
                        $_thKey = $dColorThemeMap[$_dk];
                        // Only overwrite from design_settings if color_settings didn't already set it
                        if (isset($theme[$_thKey], $defaults[$_thKey]) && $theme[$_thKey] === $defaults[$_thKey]) {
                            $theme[$_thKey] = $_d['setting_value'];
                        }
                        // Track that header_bg was explicitly configured (even via design_settings)
                        if ($_thKey === 'header_bg') $_headerBgSet = true;
                    }
                    unset($dColorThemeMap, $_d, $_dk, $_thKey);

                    // header_bg defaults to primary only when NO source explicitly configured it
                    if (!$_headerBgSet) {
                        $theme['header_bg'] = $theme['primary'];
                    }
                    unset($_headerBgSet);

                    $theme['fonts']   = $fonts;
                    $theme['design']  = $designs;
                    $theme['buttons'] = $buttons;
                    $theme['cards']   = $cards;
                    // Store raw DB rows so header.php can build CSS vars from them
                    $theme['color_settings']  = $colorRows;
                    $theme['font_settings']   = $fonts;
                    $theme['design_settings'] = $designs;

                    // Extract logo_url from design_settings (setting_key = 'logo_url')
                    foreach ($designs as $d) {
                        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
                            $theme['logo_url'] = (string)$d['setting_value'];
                            break;
                        }
                    }
                    // Fallback: check images table for a tenant logo (image_type code='logo' or 'entity_logo')
                    if (empty($theme['logo_url'])) {
                        try {
                            $logoSt = $pdo->prepare(
                                "SELECT i.url FROM images i
                                 LEFT JOIN image_types it ON it.id = i.image_type_id
                                 WHERE i.tenant_id = ?
                                   AND (it.code = 'logo' OR it.code = 'entity_logo' OR it.code = 'tenant_logo')
                                 ORDER BY i.id ASC LIMIT 1"
                            );
                            $logoSt->execute([$settingsTenantId]);
                            $logoRow = $logoSt->fetch(PDO::FETCH_ASSOC);
                            if ($logoRow && !empty($logoRow['url'])) {
                                $theme['logo_url'] = (string)$logoRow['url'];
                            }
                        } catch (Throwable $_) {}
                    }

                    // Generate complete CSS string (mirrors AdminUiThemeLoader::generateCss)
                    // Escape values to prevent CSS/HTML injection (</style> breakout)
                    $cssEsc = function(string $v): string { return str_replace('</style', '<\\/style', htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
                    $css = ":root {\n";
                    foreach ($colors as $k => $v) {
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($k)) . ': ' . $cssEsc($v) . ";\n";
                    }
                    // CSS variable aliases: bridge DB setting_key names (underscore) to
                    // --pub-* and --color-* names used in public.css / variables.css so
                    // DB colours render correctly without relying solely on the PHP bridge.
                    $pubAliases = [
                        'primary_color'        => ['color-primary',  'pub-primary'],
                        'primary_hover'        => ['color-primary-hover', 'pub-primary-hover'],
                        'secondary_color'      => ['color-secondary', 'pub-secondary'],
                        'accent_color'         => ['color-accent',    'pub-accent'],
                        'background_main'      => ['pub-bg'],
                        'background_secondary' => ['pub-surface'],
                        'text_primary'         => ['pub-text'],
                        'text_secondary'       => ['pub-muted'],
                        'border_color'         => ['pub-border'],
                        // Header background: all naming conventions â†’ --pub-header-bg
                        'header_bg'            => ['pub-header-bg'],
                        'header_bg_color'      => ['pub-header-bg'],
                        'header_background'    => ['pub-header-bg'],
                        // Footer background: all naming conventions â†’ --pub-footer-bg
                        'footer_bg'            => ['pub-footer-bg'],
                        'footer_bg_color'      => ['pub-footer-bg'],
                        'footer_background'    => ['pub-footer-bg'],
                        // Header / footer text
                        'header_text'          => ['pub-header-text'],
                        'header_text_color'    => ['pub-header-text'],
                        'footer_text'          => ['pub-footer-text'],
                        'footer_text_color'    => ['pub-footer-text'],
                        // Sidebar â€” bridge DB keys to --pub-sidebar-* and --color-sidebar-*
                        'sidebar_background'      => ['pub-sidebar-bg',       'color-sidebar-bg'],
                        'sidebar_text'            => ['pub-sidebar-text',     'color-sidebar-text'],
                        'sidebar_toggle_bg'       => ['pub-sidebar-toggle-bg','color-sidebar-toggle'],
                        'sidebar_toggle_bg_hover' => ['pub-sidebar-hover',    'color-sidebar-toggle-hover'],
                        'sidebar_card_background' => ['pub-sidebar-card-bg',  'color-sidebar-card-bg'],
                        'sidebar_card_text'       => ['pub-sidebar-card-text','color-sidebar-card-text'],
                        // Status colors â€” bridge to --pub-* and --color-*
                        'success_color'        => ['pub-success', 'color-success'],
                        'warning_color'        => ['pub-warning', 'color-warning'],
                        'danger_color'         => ['pub-danger',  'color-danger'],
                        'error_color'          => ['pub-error',   'color-error'],
                        'info_color'           => ['pub-info',    'color-info'],
                        // Input
                        'input_text'           => ['pub-input-text',       'color-input-text'],
                        'input_placeholder'    => ['pub-input-placeholder','color-input-placeholder'],
                    ];
                    foreach ($pubAliases as $srcKey => $aliases) {
                        if (empty($colors[$srcKey])) continue;
                        $val = $cssEsc($colors[$srcKey]);
                        foreach ($aliases as $alias) {
                            $css .= '  --' . $alias . ': ' . $val . ";\n";
                        }
                    }
                    foreach ($fonts as $f) {
                        if (empty($f['setting_key'])) continue;
                        $sk = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($f['setting_key']));
                        if (!empty($f['font_family'])) $css .= '  --' . $sk . '-family: ' . $cssEsc((string)$f['font_family']) . ";\n";
                        if (!empty($f['font_size']))   $css .= '  --' . $sk . '-size: '   . $cssEsc((string)$f['font_size'])   . ";\n";
                        if (!empty($f['font_weight'])) $css .= '  --' . $sk . '-weight: ' . $cssEsc((string)$f['font_weight']) . ";\n";
                        if (!empty($f['line_height'])) $css .= '  --' . $sk . '-line-height: ' . $cssEsc((string)$f['line_height']) . ";\n";
                    }
                    // design_settings â†’ raw CSS vars + --pub-* aliases for color and layout keys
                    $dColorToCssVar = [
                        'header_bg'          => 'pub-header-bg',
                        'header_bg_color'    => 'pub-header-bg',
                        'header_background'  => 'pub-header-bg',
                        'footer_bg'          => 'pub-footer-bg',
                        'footer_bg_color'    => 'pub-footer-bg',
                        'footer_background'  => 'pub-footer-bg',
                        'header_text'        => 'pub-header-text',
                        'header_text_color'  => 'pub-header-text',
                        'footer_text'        => 'pub-footer-text',
                        'footer_text_color'  => 'pub-footer-text',
                        'sidebar_bg_color'   => 'pub-sidebar-bg',
                        'sidebar_text_color' => 'pub-sidebar-text',
                    ];
                    $dLayoutToCssVar = [
                        'container_max_width' => 'pub-max-width',
                        'header_height'       => 'pub-header-height',
                        'sidebar_width'       => 'pub-sidebar-width',
                        'default_padding'     => 'pub-padding',
                        'logo_height'         => 'pub-logo-height',
                    ];
                    foreach ($designs as $d) {
                        if (empty($d['setting_key']) || empty($d['setting_value'])) continue;
                        $dk = $d['setting_key'];
                        $dv = (string)$d['setting_value'];
                        $dt = $d['setting_type'] ?? '';
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($dk)) . ': ' . $cssEsc($dv) . ";\n";
                        // Generate --pub-* alias for color-type entries
                        if ($dt === 'color' && isset($dColorToCssVar[$dk])) {
                            $css .= '  --' . $dColorToCssVar[$dk] . ': ' . $cssEsc($dv) . ";\n";
                        }
                        // Generate --pub-* alias for layout/size entries
                        if (in_array($dt, ['number', 'text'], true) && isset($dLayoutToCssVar[$dk])) {
                            $cssVal = $dv;
                            if ($dt === 'number' && !preg_match('/[a-z%]$/i', $cssVal)) {
                                $cssVal .= 'px';
                            }
                            $css .= '  --' . $dLayoutToCssVar[$dk] . ': ' . $cssEsc($cssVal) . ";\n";
                        }
                    }
                    // Always emit the resolved --pub-* vars last in :root {} from the
                    // mapped $theme values.  This guarantees correct colors regardless of
                    // which setting_key name the DB used (e.g. main_background vs background_main).
                    $css .= '  --pub-primary: '     . $cssEsc($theme['primary'])            . ";\n";
                    $css .= '  --pub-primary-hover: '. $cssEsc($theme['primary_hover'])     . ";\n";
                    $css .= '  --pub-secondary: '   . $cssEsc($theme['secondary'])          . ";\n";
                    $css .= '  --pub-accent: '      . $cssEsc($theme['accent'])             . ";\n";
                    $css .= '  --pub-bg: '          . $cssEsc($theme['background'])         . ";\n";
                    $css .= '  --pub-surface: '     . $cssEsc($theme['surface'])            . ";\n";
                    $css .= '  --pub-text: '        . $cssEsc($theme['text'])               . ";\n";
                    $css .= '  --pub-muted: '       . $cssEsc($theme['text_muted'])         . ";\n";
                    $css .= '  --pub-border: '      . $cssEsc($theme['border'])             . ";\n";
                    $css .= '  --pub-header-bg: '   . $cssEsc($theme['header_bg'])          . ";\n";
                    $css .= '  --pub-header-text: ' . $cssEsc($theme['header_text_color'])  . ";\n";
                    $css .= '  --pub-footer-bg: '   . $cssEsc($theme['footer_bg'])          . ";\n";
                    $css .= '  --pub-footer-text: ' . $cssEsc($theme['footer_text_color'])  . ";\n";
                    // Sidebar resolved vars
                    $css .= '  --pub-sidebar-bg: '          . $cssEsc($theme['sidebar_bg'])           . ";\n";
                    $css .= '  --pub-sidebar-text: '        . $cssEsc($theme['sidebar_text'])         . ";\n";
                    $css .= '  --pub-sidebar-toggle-bg: '   . $cssEsc($theme['sidebar_toggle'])       . ";\n";
                    $css .= '  --pub-sidebar-hover: '       . $cssEsc($theme['sidebar_toggle_hover']) . ";\n";
                    $css .= '  --pub-sidebar-active: '      . $cssEsc($theme['primary'])              . ";\n";
                    $css .= '  --pub-sidebar-card-bg: '     . $cssEsc($theme['sidebar_card_bg'])      . ";\n";
                    $css .= '  --pub-sidebar-card-text: '   . $cssEsc($theme['sidebar_card_text'])    . ";\n";
                    // Status resolved vars
                    $css .= '  --pub-success: '     . $cssEsc($theme['success'])            . ";\n";
                    $css .= '  --pub-warning: '     . $cssEsc($theme['warning'])            . ";\n";
                    $css .= '  --pub-danger: '      . $cssEsc($theme['danger'])             . ";\n";
                    $css .= '  --pub-error: '       . $cssEsc($theme['error'])              . ";\n";
                    $css .= '  --pub-info: '        . $cssEsc($theme['info'])               . ";\n";
                    // Also set --color-* aliases so slider.php and other partials resolve correctly
                    $css .= '  --color-primary: '   . $cssEsc($theme['primary'])            . ";\n";
                    $css .= '  --color-primary-hover: '. $cssEsc($theme['primary_hover'])   . ";\n";
                    $css .= '  --color-secondary: ' . $cssEsc($theme['secondary'])          . ";\n";
                    $css .= '  --color-accent: '    . $cssEsc($theme['accent'])             . ";\n";
                    $css .= '  --color-bg: '        . $cssEsc($theme['background'])         . ";\n";
                    $css .= '  --color-surface: '   . $cssEsc($theme['surface'])            . ";\n";
                    $css .= '  --color-text: '      . $cssEsc($theme['text'])               . ";\n";
                    $css .= '  --color-border: '    . $cssEsc($theme['border'])             . ";\n";
                    // Sidebar --color-* aliases for variables.css bridge
                    $css .= '  --color-sidebar-bg: '           . $cssEsc($theme['sidebar_bg'])           . ";\n";
                    $css .= '  --color-sidebar-text: '         . $cssEsc($theme['sidebar_text'])         . ";\n";
                    $css .= '  --color-sidebar-toggle: '       . $cssEsc($theme['sidebar_toggle'])       . ";\n";
                    $css .= '  --color-sidebar-toggle-hover: ' . $cssEsc($theme['sidebar_toggle_hover']) . ";\n";
                    $css .= '  --color-sidebar-card-bg: '      . $cssEsc($theme['sidebar_card_bg'])      . ";\n";
                    $css .= '  --color-sidebar-card-text: '    . $cssEsc($theme['sidebar_card_text'])    . ";\n";
                    // Status --color-* aliases
                    $css .= '  --color-success: '   . $cssEsc($theme['success'])            . ";\n";
                    $css .= '  --color-warning: '   . $cssEsc($theme['warning'])            . ";\n";
                    $css .= '  --color-danger: '    . $cssEsc($theme['danger'])             . ";\n";
                    $css .= '  --color-error: '     . $cssEsc($theme['error'])              . ";\n";
                    $css .= '  --color-info: '      . $cssEsc($theme['info'])               . ";\n";
                    // --pub-card-bg: generic fallback used by CSS files for cards that have no
                    // card_styles DB entry. pub_card_inline_style() uses the DB background_color
                    // directly so per-card colours from the database are applied correctly.
                    $css .= "  --pub-card-bg: var(--pub-surface);\n";
                    // Card styles â†’ :root CSS variables (--card-{slug}-*) + POS card type aliases
                    $_pubPosTypes = ['product', 'category'];
                    $_pubPosSeen  = [];
                    foreach ($cards as $_pc) {
                        if (empty($_pc['slug'])) continue;
                        $_pSlug = preg_replace('/[^a-z0-9-]/', '-', strtolower((string)$_pc['slug']));
                        if (!empty($_pc['background_color'])) $css .= "  --card-{$_pSlug}-bg: "          . $cssEsc((string)$_pc['background_color']) . ";\n";
                        if (!empty($_pc['border_color']))     $css .= "  --card-{$_pSlug}-border: "       . $cssEsc((string)$_pc['border_color']) . ";\n";
                        if (!empty($_pc['border_radius']))    $css .= "  --card-{$_pSlug}-radius: "       . (int)$_pc['border_radius'] . "px;\n";
                        if (!empty($_pc['shadow_style']))     $css .= "  --card-{$_pSlug}-shadow: "       . $cssEsc((string)$_pc['shadow_style']) . ";\n";
                        if (!empty($_pc['padding']))          $css .= "  --card-{$_pSlug}-padding: "      . $cssEsc((string)$_pc['padding']) . ";\n";
                        if (!empty($_pc['text_color']))       $css .= "  --card-{$_pSlug}-text: "         . $cssEsc((string)$_pc['text_color']) . ";\n";
                        if (!empty($_pc['border_width']))     $css .= "  --card-{$_pSlug}-border-width: " . (int)$_pc['border_width'] . "px;\n";
                        $_pType = $_pc['card_type'] ?? '';
                        if (in_array($_pType, $_pubPosTypes, true) && !isset($_pubPosSeen[$_pType])) {
                            $_pubPosSeen[$_pType] = true;
                            $_tp = "--card-{$_pType}";
                            if (!empty($_pc['background_color'])) $css .= "  {$_tp}-bg: "          . $cssEsc((string)$_pc['background_color']) . ";\n";
                            if (!empty($_pc['text_color']))       $css .= "  {$_tp}-text: "         . $cssEsc((string)$_pc['text_color']) . ";\n";
                            if (!empty($_pc['border_color']))     $css .= "  {$_tp}-border: "       . $cssEsc((string)$_pc['border_color']) . ";\n";
                            if (!empty($_pc['border_width']))     $css .= "  {$_tp}-border-width: " . (int)$_pc['border_width'] . "px;\n";
                            if (!empty($_pc['border_radius']))    $css .= "  {$_tp}-radius: "       . (int)$_pc['border_radius'] . "px;\n";
                            if (!empty($_pc['shadow_style']))     $css .= "  {$_tp}-shadow: "       . $cssEsc((string)$_pc['shadow_style']) . ";\n";
                            if (!empty($_pc['padding']))          $css .= "  {$_tp}-padding: "      . $cssEsc((string)$_pc['padding']) . ";\n";
                        }
                    }
                    $css .= "}\n";
                    // Apply font_settings variables to relevant UI elements
                    $fontSelMap = [
                        'card_font'       => '.pub-card, .pub-entity-card, .pub-job-card, .pub-deal-card, .pub-cat-card',
                        'footer_font'     => '.pub-footer',
                        'form_font'       => '.pub-form input, .pub-form select, .pub-form textarea, .pub-search-input',
                        'promo_font'      => '.pub-deal-card, .pub-promo-card',
                        'small_text_font' => '.pub-muted, .pub-tag, small',
                        'code_font'       => 'code, pre',
                        'alert_font'      => '.pub-toast, .pub-notice, .pub-alert',
                    ];
                    foreach ($fonts as $f) {
                        if (empty($f['setting_key'])) continue;
                        $sk  = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($f['setting_key']));
                        $sel = $fontSelMap[$f['setting_key']] ?? null;
                        if (!$sel) continue;
                        $props = [];
                        if (!empty($f['font_family'])) $props[] = '  font-family: var(--' . $sk . '-family)';
                        if (!empty($f['font_size']))   $props[] = '  font-size: var(--'   . $sk . '-size)';
                        if (!empty($f['font_weight'])) $props[] = '  font-weight: var(--' . $sk . '-weight)';
                        if ($props) $css .= $sel . " {\n" . implode(";\n", $props) . ";\n}\n";
                    }
                    // Map button_type to .pub-btn-- class names used in HTML
                    $pubBtnTypeMap = ['transpa' => 'ghost', 'transparent' => 'ghost'];
                    // Emit --btn-{slug}-* CSS variables inside a :root block and generate
                    // .btn-{slug} classes that reference those vars (db-theme-bridge.css pattern)
                    $css .= ":root {\n";
                    foreach ($buttons as $b) {
                        if (empty($b['slug'])) continue;
                        $slugB    = preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']);
                        $btnType  = strtolower(trim((string)($b['button_type'] ?? '')));
                        $pubClass = $pubBtnTypeMap[$btnType] ?? $btnType;
                        $isDisabled = strpos($slugB, '-disabled') !== false;

                        // â”€â”€ :root CSS variables for this button â”€â”€
                        if (!empty($b['background_color']))       $css .= "  --btn-{$slugB}-bg: "           . $cssEsc((string)$b['background_color'])       . ";\n";
                        if (!empty($b['text_color']))             $css .= "  --btn-{$slugB}-color: "        . $cssEsc((string)$b['text_color'])             . ";\n";
                        if (!empty($b['border_color']))           $css .= "  --btn-{$slugB}-border: "       . $cssEsc((string)$b['border_color'])           . ";\n";
                        if (isset($b['border_width']))            $css .= "  --btn-{$slugB}-border-width: " . (int)$b['border_width']                       . "px;\n";
                        if (isset($b['border_radius']))           $css .= "  --btn-{$slugB}-radius: "       . (int)$b['border_radius']                      . "px;\n";
                        if (!empty($b['padding']))                $css .= "  --btn-{$slugB}-padding: "      . $cssEsc((string)$b['padding'])                . ";\n";
                        if (!empty($b['font_size']))              $css .= "  --btn-{$slugB}-font-size: "    . $cssEsc((string)$b['font_size']) . (is_numeric($b['font_size']) ? 'px' : '') . ";\n";
                        if (!empty($b['font_weight']))            $css .= "  --btn-{$slugB}-font-weight: "  . $cssEsc((string)$b['font_weight'])            . ";\n";
                        if (!empty($b['hover_background_color'])) $css .= "  --btn-{$slugB}-hover-bg: "     . $cssEsc((string)$b['hover_background_color']) . ";\n";
                        if (!empty($b['hover_text_color']))       $css .= "  --btn-{$slugB}-hover-color: "  . $cssEsc((string)$b['hover_text_color'])       . ";\n";
                        if (!empty($b['hover_border_color']))     $css .= "  --btn-{$slugB}-hover-border: " . $cssEsc((string)$b['hover_border_color'])     . ";\n";
                    }
                    $css .= "}\n";

                    // â”€â”€ .btn-{slug} classes using var() references â”€â”€
                    foreach ($buttons as $b) {
                        if (empty($b['slug'])) continue;
                        $slugB    = preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']);
                        $btnType  = strtolower(trim((string)($b['button_type'] ?? '')));
                        $pubClass = $pubBtnTypeMap[$btnType] ?? $btnType;
                        $isDisabled = strpos($slugB, '-disabled') !== false;
                        // Combine .btn-{slug} with .pub-btn--{slug} for non-disabled buttons
                        // Use slug (not button_type) to prevent type collisions
                        // e.g. ghost (type=primary) must NOT override .pub-btn--primary
                        $sel = ".btn-{$slugB}";
                        if (!$isDisabled && preg_match('/^[a-z][a-z0-9\-]*$/', $slugB)) {
                            $sel .= ", .pub-btn--{$slugB}";
                        }
                        $css .= "{$sel} {\n";
                        if (!empty($b['background_color'])) $css .= "  background-color: var(--btn-{$slugB}-bg);\n";
                        if (!empty($b['text_color']))       $css .= "  color:            var(--btn-{$slugB}-color);\n";
                        if (!empty($b['border_color']))     $css .= "  border:           var(--btn-{$slugB}-border-width, " . (int)$b['border_width'] . "px) solid var(--btn-{$slugB}-border);\n";
                        if (isset($b['border_radius']))     $css .= "  border-radius:    var(--btn-{$slugB}-radius);\n";
                        if (!empty($b['padding']))          $css .= "  padding:          var(--btn-{$slugB}-padding);\n";
                        if (!empty($b['font_size']))        $css .= "  font-size:        var(--btn-{$slugB}-font-size);\n";
                        if (!empty($b['font_weight']))      $css .= "  font-weight:      var(--btn-{$slugB}-font-weight);\n";
                        $css .= "  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;\n";
                        $css .= "}\n";
                        $hasHover = !empty($b['hover_background_color'])
                                 || !empty($b['hover_text_color'])
                                 || !empty($b['hover_border_color']);
                        if ($hasHover) {
                            $hoverSel = ".btn-{$slugB}:hover, .btn-{$slugB}:focus-visible";
                            if (!$isDisabled && preg_match('/^[a-z][a-z0-9\-]*$/', $slugB)) {
                                $hoverSel .= ", .pub-btn--{$slugB}:hover, .pub-btn--{$slugB}:focus-visible";
                            }
                            $css .= "{$hoverSel} {\n";
                            if (!empty($b['hover_background_color'])) $css .= "  background-color: var(--btn-{$slugB}-hover-bg);\n";
                            if (!empty($b['hover_text_color']))       $css .= "  color:            var(--btn-{$slugB}-hover-color);\n";
                            if (!empty($b['hover_border_color']))     $css .= "  border-color:     var(--btn-{$slugB}-hover-border);\n";
                            $css .= "}\n";
                        }
                    }
                    foreach ($cards as $c) {
                        if (empty($c['slug'])) continue;
                        $slugC = preg_replace('/[^a-z0-9_\-]/', '-', (string)$c['slug']);
                        $hoverEffect = strtolower(trim((string)($c['hover_effect'] ?? '')));
                        $css .= ".card-{$slugC} {\n";
                        if (!empty($c['background_color'])) $css .= "  background-color: var(--card-{$slugC}-bg);\n";
                        if (!empty($c['border_color']))     $css .= "  border:           var(--card-{$slugC}-border-width, " . (int)$c['border_width'] . "px) solid var(--card-{$slugC}-border);\n";
                        if (isset($c['border_radius']))     $css .= "  border-radius:    var(--card-{$slugC}-radius);\n";
                        if (!empty($c['shadow_style']))     $css .= "  box-shadow:       var(--card-{$slugC}-shadow);\n";
                        if (!empty($c['padding']))          $css .= "  padding:          var(--card-{$slugC}-padding);\n";
                        if (!empty($c['text_align']))       $css .= '  text-align: '       . $cssEsc((string)$c['text_align'])       . ";\n";
                        // Only add transition when a hover effect is configured
                        if ($hoverEffect && $hoverEffect !== 'none') {
                            $css .= "  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, filter 0.2s ease;\n";
                        }
                        $css .= "}\n";
                        // Image wrapper aspect ratio (e.g. "1:1" stored in DB â†’ CSS "1/1")
                        if (!empty($c['image_aspect_ratio'])) {
                            $ratio = preg_replace('/[^0-9:]/', '', (string)$c['image_aspect_ratio']);
                            $ratio = str_replace(':', '/', $ratio);
                            if ($ratio) $css .= ".card-{$slugC} .pub-cat-img-wrap { aspect-ratio: {$ratio}; }\n";
                        }
                        // Hover effect â€” matches AdminUiThemeLoader::generateCss() hoverEffectMap
                        if ($hoverEffect && $hoverEffect !== 'none') {
                            $css .= ".card-{$slugC}:hover {\n";
                            if ($hoverEffect === 'lift')       $css .= "  transform: translateY(-4px);\n  box-shadow: 0 8px 24px rgba(0,0,0,0.15);\n";
                            if ($hoverEffect === 'zoom')       $css .= "  transform: scale(1.03);\n";
                            if ($hoverEffect === 'shadow')     $css .= "  box-shadow: 0 8px 24px rgba(0,0,0,0.2);\n";
                            if ($hoverEffect === 'border')     $css .= "  border-color: var(--primary-color, #3B82F6);\n";
                            if ($hoverEffect === 'brightness') $css .= "  filter: brightness(1.08);\n";
                            $css .= "}\n";
                        }
                    }
                    $theme['generated_css'] = $css;
                    $theme['_debug'] = [
                        'source'             => 'db',
                        'theme_target'       => $themeTarget,
                        'theme_id'           => $themeDbId,
                        'settings_tenant_id' => $settingsTenantId,
                        'requested_tenant_id'=> $tenantId,
                        'script_name'        => $scriptName,
                        'has_generated_css'  => $css !== '',
                        'color_rows'         => count($colorRows),
                        'font_rows'          => count($fonts),
                        'design_rows'        => count($designs),
                        'button_rows'        => count($buttons),
                        'card_rows'          => count($cards),
                    ];

                    return $theme;
                }
            } catch (Throwable $_) {
                // Silently fall through to HTTP fallback
            }
        }

        // Fallback: try HTTP call to /api/public/ui
        $scriptName = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $themeTarget = pub_resolve_theme_target($identity, $tenantId);
        $query = ['theme_target' => $themeTarget];
        if ($themeTarget !== 'platform_home') {
            $query['tenant_id'] = $tenantId;
        }
        $url  = pub_api_url('public/ui') . '?' . http_build_query($query);
        $resp = pub_fetch($url, 3);
        if (!empty($resp['data']['generated_css'])) {
            $theme = $defaults;
            $theme['generated_css'] = $resp['data']['generated_css'];
            // Also apply color map from response
            $httpColorMap = [
                'primary_color'        => 'primary',
                'secondary_color'      => 'secondary',
                'accent_color'         => 'accent',
                'background_main'      => 'background',
                'background_secondary' => 'surface',
                'text_primary'         => 'text',
                'text_secondary'       => 'text_muted',
                'border_color'         => 'border',
                'header_bg_color'      => 'header_bg',
                'header_background'    => 'header_bg',
                'header_text'          => 'header_text_color',
                'footer_bg_color'      => 'footer_bg',
                'footer_background'    => 'footer_bg',
                'footer_text'          => 'footer_text_color',
            ];
            foreach ($resp['data']['colors'] ?? [] as $item) {
                $k = $item['key'] ?? '';
                $v = $item['value'] ?? '';
                if ($k && $v && isset($httpColorMap[$k])) $theme[$httpColorMap[$k]] = $v;
            }
            if (empty($theme['header_bg']) || $theme['header_bg'] === $defaults['header_bg']) {
                $theme['header_bg'] = $theme['primary'];
            }
            $theme['_debug'] = [
                'source'             => 'api_fallback',
                'theme_target'       => $themeTarget,
                'theme_id'           => $resp['data']['theme']['id'] ?? null,
                'settings_tenant_id' => $resp['data']['theme']['tenant_id'] ?? $tenantId,
                'requested_tenant_id'=> $tenantId,
                'script_name'        => $scriptName,
                'has_generated_css'  => !empty($theme['generated_css']),
                'color_rows'         => count($resp['data']['colors'] ?? []),
                'font_rows'          => count($resp['data']['fonts'] ?? []),
                'design_rows'        => count($resp['data']['design'] ?? []),
                'button_rows'        => count($resp['data']['buttons'] ?? []),
                'card_rows'          => count($resp['data']['cards'] ?? []),
            ];
            return $theme;
        }

        $defaults['_debug'] = [
            'source'             => 'defaults',
            'theme_target'       => $themeTarget ?? 'tenant_store',
            'theme_id'           => null,
            'settings_tenant_id' => $tenantId,
            'requested_tenant_id'=> $tenantId,
            'script_name'        => $scriptName ?? strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''))),
            'has_generated_css'  => false,
            'color_rows'         => 0,
            'font_rows'          => 0,
            'design_rows'        => 0,
            'button_rows'        => 0,
            'card_rows'          => 0,
        ];
        return $defaults;
    }
}

/* -------------------------------------------------------
 * 5a. Unified identity resolution
 * ----------------------------------------------------- */
if (!function_exists('pub_load_identity')) {
    function pub_load_identity(?PDO $pdo = null): array
    {
        $fallback = [
            'resolved_user_id'   => isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'resolved_tenant_id' => isset($_SESSION['tenant_id']) && is_numeric($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null,
            'identity_source'    => (string)($_SESSION['identity_debug']['identity_source'] ?? 'guest'),
            'session_id'         => session_id(),
            'request_id'         => (string)($_SESSION['identity_debug']['request_id'] ?? ''),
            'is_platform_admin'  => !empty($_SESSION['platform_admin']),
            'platform_role'      => $_SESSION['platform_role'] ?? null,
            'user'               => is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : null,
        ];

        $authBasePath = dirname(FRONTEND_BASE) . '/api/shared/application/Auth';
        $identityPath = $authBasePath . '/UserIdentity.php';
        $resolverPath = $authBasePath . '/UserIdentityResolver.php';
        if (!is_readable($identityPath) || !is_readable($resolverPath)) {
            return $fallback;
        }

        require_once $identityPath;
        require_once $resolverPath;

        if (!class_exists('\Shared\Application\Auth\UserIdentityResolver', false)) {
            return $fallback;
        }

        try {
            $identity = \Shared\Application\Auth\UserIdentityResolver::resolve($pdo, [
                'force' => true,
            ]);

            return [
                'resolved_user_id'   => $identity->id(),
                'resolved_tenant_id' => $identity->tenantId(),
                'identity_source'    => $identity->source(),
                'session_id'         => session_id(),
                'request_id'         => $identity->requestId(),
                'is_platform_admin'  => $identity->isPlatformAdmin(),
                'platform_role'      => $identity->platformRole(),
                'user'               => $identity->toArray(),
            ];
        } catch (Throwable $e) {
            error_log('[pub_load_identity] ' . $e->getMessage());
            return $fallback;
        }
    }
}

/* -------------------------------------------------------
 * 5b. Public theme target resolution
 * ----------------------------------------------------- */
if (!function_exists('pub_resolve_theme_target')) {
    function pub_resolve_theme_target(array $identity = [], ?int $requestedTenantId = null): string
    {
        $requestedThemeTarget = strtolower(trim((string)($_GET['theme_target'] ?? '')));
        if (in_array($requestedThemeTarget, ['tenant_store', 'platform_home'], true)) {
            return $requestedThemeTarget;
        }

        $scriptName = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        return $scriptName === 'entity.php' ? 'tenant_store' : 'platform_home';
    }
}

/* -------------------------------------------------------
 * 5c. Public tenant context resolution
 * ----------------------------------------------------- */
if (!function_exists('pub_resolve_context_tenant_id')) {
    function pub_resolve_context_tenant_id(array $identity = [], ?PDO $pdo = null): int
    {
        $requestedTenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
            ? (int)$_GET['tenant_id']
            : 0;
        $sessionTenantId = isset($_SESSION['pub_tenant_id']) && is_numeric($_SESSION['pub_tenant_id'])
            ? (int)$_SESSION['pub_tenant_id']
            : 0;
        $resolvedTenantId = isset($identity['resolved_tenant_id']) && is_numeric($identity['resolved_tenant_id'])
            ? (int)$identity['resolved_tenant_id']
            : 0;
        $scriptName = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));

        if ($scriptName === 'entity.php') {
            $entityId = isset($_GET['id']) && is_numeric($_GET['id'])
                ? (int)$_GET['id']
                : (isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0);
            $entitySlug = trim((string)($_GET['slug'] ?? ''));

            $pdo ??= pub_get_pdo();
            if ($pdo instanceof PDO) {
                try {
                    if ($entityId > 0) {
                        $stmt = $pdo->prepare('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1');
                        $stmt->execute([$entityId]);
                    } elseif ($entitySlug !== '') {
                        $stmt = $pdo->prepare('SELECT tenant_id FROM entities WHERE slug = ? LIMIT 1');
                        $stmt->execute([$entitySlug]);
                    } else {
                        $stmt = null;
                    }

                    $entityTenantId = $stmt ? (int)$stmt->fetchColumn() : 0;
                    if ($entityTenantId > 0) {
                        return $entityTenantId;
                    }
                } catch (Throwable $e) {
                    error_log('[pub_resolve_context_tenant_id] ' . $e->getMessage());
                }
            }

            if ($requestedTenantId > 0) {
                return $requestedTenantId;
            }

            return $sessionTenantId > 0 ? $sessionTenantId : 1;
        }

        if ($requestedTenantId > 0) {
            return $requestedTenantId;
        }
        if ($sessionTenantId > 0) {
            return $sessionTenantId;
        }
        if ($resolvedTenantId > 0) {
            return $resolvedTenantId;
        }

        return 1;
    }
}

/* -------------------------------------------------------
 * 7a. SEO meta helper â€” loads seo_meta + seo_meta_translations for any entity.
 * Returns array with SEO fields or [] if no row / DB error.
 * ----------------------------------------------------- */
if (!function_exists('pub_get_seo_meta')) {
    function pub_get_seo_meta(string $entityType, int $entityId, string $lang = 'en'): array {
        if (!$entityId || !$entityType) return [];
        $pdo = pub_get_pdo();
        if (!$pdo) return [];
        try {
            $st = $pdo->prepare(
                "SELECT sm.canonical_url, sm.robots, sm.schema_markup,
                        smt.meta_title, smt.meta_description, smt.meta_keywords,
                        smt.og_title, smt.og_description, smt.og_image
                   FROM seo_meta sm
              LEFT JOIN seo_meta_translations smt
                     ON smt.seo_meta_id = sm.id AND smt.language_code = ?
                  WHERE sm.entity_type = ? AND sm.entity_id = ?
                  LIMIT 1"
            );
            $st->execute([$lang, $entityType, $entityId]);
            $row = $st->fetch();
            if (!$row) return [];
            return [
                'title'          => $row['meta_title']        ?? '',
                'description'    => $row['meta_description']  ?? '',
                'keywords'       => $row['meta_keywords']     ?? '',
                'canonical_url'  => $row['canonical_url']     ?? '',
                'robots'         => $row['robots']            ?? '',
                'og_title'       => $row['og_title']          ?? '',
                'og_description' => $row['og_description']    ?? '',
                'og_image'       => $row['og_image']          ?? '',
                'schema_markup'  => $row['schema_markup']     ?? '',
            ];
        } catch (Throwable $_) { return []; }
    }
}

/* -------------------------------------------------------
 * 7b. Direct PDO helper â€” reuse the same DB connection as the API
 *    Returns PDO instance or null on failure.
 *    Used by product.php and other pages to avoid HTTP loopback
 *    self-referencing requests that may fail on shared hosting.
 *    Uses DOCUMENT_ROOT as primary path (same as admin_context.php).
 * ----------------------------------------------------- */
if (!function_exists('pub_get_pdo')) {
    function pub_get_pdo(): ?PDO {
        // Cache per request â€” avoid opening multiple connections (one from pub_load_theme + one from here)
        static $__pdo = false;
        if ($__pdo !== false) return $__pdo;

        // DOCUMENT_ROOT first (same as admin_context.php line 45), then relative path as fallback
        $candidates = [
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php',
            FRONTEND_BASE . '/../api/shared/config/db.php',
            realpath(FRONTEND_BASE . '/../api/shared/config/db.php') ?: '',
        ];
        $dbConf = null;
        foreach ($candidates as $f) {
            if ($f && is_readable($f)) {
                $dbConf = require $f;
                if (is_array($dbConf)) break;
                $dbConf = null;
            }
        }
        // Fallback: use already-defined DB_HOST constants (set by API bootstrap or db.php
        // loaded in the same request). This covers shared hosting where path resolution
        // fails but the constants are already in scope from admin/session bootstrap.
        if (!$dbConf && defined('DB_HOST') && defined('DB_NAME')) {
            $dbConf = [
                'host'    => DB_HOST,
                'user'    => defined('DB_USER')    ? DB_USER    : '',
                'pass'    => defined('DB_PASS')    ? DB_PASS    : '',
                'name'    => DB_NAME,
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'port'    => defined('DB_PORT')    ? (int)DB_PORT : 3306,
            ];
        }
        if (!$dbConf) { $__pdo = null; return null; }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConf['host'] ?? 'localhost',
                (int)($dbConf['port'] ?? 3306),
                $dbConf['name'],
                $dbConf['charset'] ?? 'utf8mb4'
            );
            $__pdo = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,  // ensures LIMIT/OFFSET bound params work on MySQL 5.x
            ]);
            return $__pdo;
        } catch (Throwable $_) {
            $__pdo = null;
            return null;
        }
    }
}

/* -------------------------------------------------------
 * 8. XSS escape helper
 * ----------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* -------------------------------------------------------
 * 9. Pagination helper
 * ----------------------------------------------------- */
if (!function_exists('pub_paginate')) {
    /**
     * Returns pagination info array.
     */
    function pub_paginate(int $total, int $page, int $perPage): array {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        return [
            'total'       => $total,
            'page'        => max(1, $page),
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }
}

/* -------------------------------------------------------
 * 9. Image URL helper
 *    Resolves uploaded image URLs for products, categories,
 *    entities, brands, etc.
 *    image_types reference:
 *      category / product / product_thumb / entity_logo /
 *      entity_cover / banner / gallery / brand / avatar â€¦
 * ----------------------------------------------------- */
if (!function_exists('pub_img')) {
    /**
     * Build an absolute-path image URL for items stored in /uploads.
     *
     * Handles all path formats from the DB:
     *   /admin/uploads/images/general/2026/02/02/img_xxx.webp  â†’ as-is (absolute)
     *   /uploads/images/img_xxx.webp                           â†’ as-is
     *   uploads/images/img_xxx.webp                            â†’ /uploads/images/img_xxx.webp
     *   img_xxx.webp                                           â†’ /uploads/images/img_xxx.webp
     *   https://cdn.example.com/x.jpg                          â†’ passthrough
     *
     * @param string|null $path   Raw path from DB
     * @param string      $type   image_types.code  (category / product / entity_logo â€¦)
     * @param string      $fallback Returned when no image available
     */
    function pub_img(?string $path, string $type = 'product', string $fallback = ''): string {
        if (empty($path)) {
            return $fallback;
        }

        // Already a full URL â†’ return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        // Absolute path starting with / â†’ return as-is (covers /admin/uploads/... and /uploads/...)
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Relative path already starting with uploads/
        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'uploads/')) {
            return '/' . $clean;
        }
        if (str_starts_with($clean, 'admin/uploads/')) {
            return '/' . $clean;
        }

        // Bare filename â€” place under /uploads/images/
        return '/uploads/images/' . $clean;
    }
}

/* -------------------------------------------------------
 * 10. Image HTML tag helper
 * ----------------------------------------------------- */
if (!function_exists('pub_img_tag')) {
    /**
     * Render an <img> tag or a placeholder div.
     *
     * @param string|null $path
     * @param string      $alt
     * @param string      $type  image_types.code
     * @param string      $cssClass
     * @param string      $placeholderIcon  Emoji used as placeholder
     */
    function pub_img_tag(?string $path, string $alt = '', string $type = 'product',
                          string $cssClass = '', string $placeholderIcon = 'ًں–¼ï¸ڈ'): string {
        $url = empty($path) ? '' : pub_img($path, $type);
        if ($url) {
            return '<img data-src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
                 . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
                 . ' loading="lazy"'
                 . ($cssClass ? ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"' : '')
                 . ' data-fallback-image>'
                 . '<span class="pub-img-placeholder" hidden aria-hidden="true">' . htmlspecialchars($placeholderIcon, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        return '<span class="pub-img-placeholder" aria-hidden="true">' . htmlspecialchars($placeholderIcon, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

/* -------------------------------------------------------
 * 11. Card helpers â€” DB-driven card_styles
 * ----------------------------------------------------- */

/** @internal Shared lookup for card_styles row by card_type or slug */
function _pub_card_row(string $cardType): ?array {
    $cards = $GLOBALS['PUB_CONTEXT']['theme']['cards'] ?? [];
    foreach ($cards as $c) {
        if (($c['card_type'] ?? '') === $cardType || ($c['slug'] ?? '') === $cardType) {
            return $c;
        }
    }
    // Fallback 1: match by the base of the slug (e.g. 'entities-default' base 'entities' matches 'entities').
    // Works for ALL cards regardless of card_type value (covers mis-typed DB rows).
    foreach ($cards as $c) {
        $slug     = $c['slug'] ?? '';
        $dashPos  = strpos($slug, '-');
        $slugBase = $dashPos !== false ? substr($slug, 0, $dashPos) : $slug;
        if ($slugBase === $cardType) {
            return $c;
        }
    }
    // Fallback 2: singular/plural alias using an explicit map of known card types.
    // Covers 'entities'â†”'entity', 'tenants'â†”'tenant', 'products'â†”'product', etc.
    static $aliasMap = [
        'entities'  => 'entity',   'entity'    => 'entities',
        'tenants'   => 'tenant',   'tenant'    => 'tenants',
        'products'  => 'product',  'product'   => 'products',
        'categories'=> 'category', 'category'  => 'categories',
        'auctions'  => 'auction',  'auction'   => 'auctions',
        'jobs'      => 'job',      'job'       => 'jobs',
        'blogs'     => 'blog',     'blog'      => 'blogs',
        'discounts' => 'discount', 'discount'  => 'discounts',
        'features'  => 'feature',  'feature'   => 'features',
        'banners'   => 'banner',   'banner'    => 'banners',
    ];
    if (isset($aliasMap[$cardType])) {
        $alias = $aliasMap[$cardType];
        foreach ($cards as $c) {
            if (($c['card_type'] ?? '') === $alias || ($c['slug'] ?? '') === $alias) {
                return $c;
            }
            $slug     = $c['slug'] ?? '';
            $dashPos  = strpos($slug, '-');
            $slugBase = $dashPos !== false ? substr($slug, 0, $dashPos) : $slug;
            if ($slugBase === $alias) {
                return $c;
            }
        }
    }
    return null;
}

if (!function_exists('pub_card_inline_style')) {
    /**
     * Return an inline CSS style string for a card element, sourced from the
     * DB card_styles table (already loaded into $GLOBALS['PUB_CONTEXT']['theme']['cards']).
     *
     * Matches by card_type first, then by slug. Returns '' when no matching row exists.
     *
     * @param string $cardType  e.g. 'entity', 'tenant', 'product'
     */
    function pub_card_inline_style(string $cardType): string {
        $row = _pub_card_row($cardType);
        if (!$row) return '';

        // Escape a CSS value: HTML-encode and also strip characters that could
        // break out of a style="..." attribute or inject extra CSS properties.
        $esc = function(string $v): string {
            $v = str_replace(['"', "'", ';', '{', '}', '\\'], '', $v);
            return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $parts = [];
        // Use the DB background_color directly so per-card colours stored in card_styles
        // are applied correctly. --pub-card-bg (aliased to --pub-surface) remains available
        // as a generic CSS fallback for cards that have no DB card_styles entry.
        if (!empty($row['background_color'])) $parts[] = 'background-color:' . $esc($row['background_color']);
        if (!empty($row['border_color'])) {
            $bw = max(0, (int)($row['border_width'] ?? 1));
            $parts[] = 'border:' . $bw . 'px solid ' . $esc($row['border_color']);
        }
        if (isset($row['border_radius']) && $row['border_radius'] !== '') $parts[] = 'border-radius:' . (int)$row['border_radius'] . 'px';
        if (!empty($row['shadow_style']))  $parts[] = 'box-shadow:' . $esc($row['shadow_style']);
        if (!empty($row['padding']))       $parts[] = 'padding:' . $esc($row['padding']);
        if (!empty($row['text_align']))    $parts[] = 'text-align:' . $esc($row['text_align']);
        return implode(';', $parts);
    }
}

if (!function_exists('pub_card_css_class')) {
    /**
     * Return the generated CSS class name for a card type, e.g. "card-product-default".
     * This class is emitted by pub_load_theme() as .card-{slug} in generated_css,
     * and includes hover effects. Returns '' when no matching row exists.
     */
    function pub_card_css_class(string $cardType): string {
        $row = _pub_card_row($cardType);
        if (empty($row['slug'])) return '';
        return 'card-' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$row['slug']));
    }
}

if (!function_exists('pub_card_img_style')) {
    /**
     * Return an inline style string for the image wrapper of a card, providing
     * the aspect-ratio from card_styles.image_aspect_ratio (e.g. "1:1" â†’ "aspect-ratio:1/1").
     * Falls back to the provided default ratio string if no DB row exists.
     */
    function pub_card_img_style(string $cardType, string $fallback = '1/1'): string {
        $row = _pub_card_row($cardType);
        $ratio = $fallback;
        if (!empty($row['image_aspect_ratio'])) {
            $r = preg_replace('/[^0-9:]/', '', (string)$row['image_aspect_ratio']);
            $r = str_replace(':', '/', $r);
            if ($r) $ratio = $r;
        }
        return 'aspect-ratio:' . htmlspecialchars($ratio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pub_entity_card_style')) {
    /**
     * Return an inline CSS style string for an entity card, applying the per-entity
     * card color from entity_settings.additional_settings JSON (card_color, card_text_color)
     * when set. When no per-entity color is set, returns the non-background parts of
     * $globalCardStyle (border, shadow, padding, etc.) so the CSS class can control
     * the background without being overridden by a redundant inline value.
     *
     * @param array  $entity         Entity data row (must include 'additional_settings' key if available)
     * @param string $globalCardStyle Fallback from pub_card_inline_style('entities')
     * @return string Inline CSS style string (without surrounding style="")
     */
    function pub_entity_card_style(array $entity, string $globalCardStyle = ''): string {
        if (!empty($entity['additional_settings'])) {
            $addSettings = json_decode((string)$entity['additional_settings'], true);
            if (is_array($addSettings) && !empty($addSettings['card_color'])) {
                $cc = preg_replace('/[^#a-zA-Z0-9(). ,%]/', '', (string)$addSettings['card_color']);
                $style = 'background-color:' . htmlspecialchars($cc, ENT_QUOTES, 'UTF-8');
                if (!empty($addSettings['card_text_color'])) {
                    $tc = preg_replace('/[^#a-zA-Z0-9(). ,%]/', '', (string)$addSettings['card_text_color']);
                    $style .= ';color:' . htmlspecialchars($tc, ENT_QUOTES, 'UTF-8');
                }
                return $style;
            }
        }
        // Strip the background-color declaration from the global fallback so the CSS class
        // (card-entities-default / .pub-entity-card) controls the background. This prevents
        // entities without a specific card color from having their background locked to the
        // same value as the page background via an inline style override.
        if ($globalCardStyle === '') return '';
        $parts = array_filter(array_map('trim', explode(';', $globalCardStyle)), function (string $p): bool {
            if ($p === '') return false;
            $prop = strtolower(strtok($p, ':'));
            return $prop !== 'background-color' && $prop !== 'background';
        });
        return implode(';', $parts);
    }
}


/* -------------------------------------------------------
 * 11b. Notifications loader â€” reads recent notifications for a tenant
 *      directly via the shared PDO connection. Returns array of rows
 *      sorted newest-first. Silently returns [] on error.
 *
 *      Columns used: id, tenant_id, title, message, sent_at,
 *                    notification_type_id, priority
 *      Compatible with the current `notifications` table schema.
 * ----------------------------------------------------- */
if (!function_exists('pub_load_notifications')) {
    function pub_load_notifications(int $tenantId, int $limit = 8): array {
        $pdo = pub_get_pdo();
        if (!$pdo) return [];
        // Resolve user from session (supports both session formats used across the app)
        $userId = (int)(
            $_SESSION['user_id'] ??
            ($_SESSION['user']['id'] ?? 0)
        );
        if (!$userId) return [];
        try {
            // Join notification_recipients so we only return notifications addressed
            // to this specific user, and include the is_read flag for the bell badge.
            $st = $pdo->prepare(
                "SELECT n.id, n.title, n.message, n.sent_at, n.priority,
                        nr.is_read,
                        nt.code AS type_code, nt.name AS type_name
                   FROM notification_recipients nr
                   JOIN notifications n          ON n.id  = nr.notification_id
              LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
                  WHERE nr.recipient_type = 'user'
                    AND nr.recipient_id   = ?
                    AND n.tenant_id       = ?
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())
                  ORDER BY n.sent_at DESC
                  LIMIT ?"
            );
            $st->execute([$userId, $tenantId, $limit]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $row['is_read'] = (bool)$row['is_read'];
            }
            unset($row);
            return $rows;
        } catch (Throwable $e) {
            error_log('[pub_load_notifications] ' . $e->getMessage());
            return [];
        }
    }
}

/* -------------------------------------------------------
 * 11c. Active entity context
 * ----------------------------------------------------- */
if (!function_exists('pub_haversine_km')) {
    function pub_haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }
}

if (!function_exists('pub_entity_hours_state')) {
    /**
     * Returns a compact open/closed state for an entity.
     * If no hours are configured, the entity is treated as available.
     */
    function pub_entity_hours_state(array $workingHours): array {
        if (empty($workingHours)) {
            return ['known' => false, 'is_open' => true];
        }

        $nowDow  = (int)date('w');
        $nowMins = (int)date('H') * 60 + (int)date('i');

        foreach ($workingHours as $h) {
            if ((int)($h['day_of_week'] ?? -1) !== $nowDow) {
                continue;
            }

            if (empty($h['is_open'])) {
                return ['known' => true, 'is_open' => false];
            }

            $openMin  = 0;
            $closeMin = 24 * 60;

            if (!empty($h['open_time'])) {
                [$oh, $om] = array_map('intval', explode(':', (string)$h['open_time']));
                $openMin = ($oh * 60) + $om;
            }
            if (!empty($h['close_time'])) {
                [$ch, $cm] = array_map('intval', explode(':', (string)$h['close_time']));
                $closeMin = ($ch * 60) + $cm;
            }

            $isOpen = $nowMins >= $openMin && ($closeMin === 0 || $nowMins < $closeMin);
            return ['known' => true, 'is_open' => $isOpen];
        }

        return ['known' => false, 'is_open' => true];
    }
}

if (!function_exists('pub_update_entity_location_cache')) {
    function pub_update_entity_location_cache(
        int $tenantId,
        ?float $lat,
        ?float $lng,
        string $source = 'unknown'
    ): void {
        if ($tenantId <= 0 || $lat === null || $lng === null) {
            return;
        }

        $_SESSION['pub_entity_location'] ??= [];
        $_SESSION['pub_entity_location'][$tenantId] = [
            'lat'         => round($lat, 7),
            'lng'         => round($lng, 7),
            'source'      => $source,
            'resolved_at' => date('c'),
        ];
    }
}

if (!function_exists('pub_list_entity_contexts')) {
    /**
     * Loads candidate entities with address coordinates and lightweight delivery metadata.
     */
    function pub_list_entity_contexts(
        int $tenantId,
        string $lang = 'en',
        ?float $lat = null,
        ?float $lng = null,
        int $limit = 8,
        array $entityIds = []
    ): array {
        $pdo = pub_get_pdo();
        if (!$pdo || $tenantId <= 0) {
            return [];
        }

        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        $params = [$lang, $tenantId];
        $entityFilterSql = '';

        if (!empty($entityIds)) {
            $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
            $entityFilterSql = " AND e.id IN ($placeholders)";
            $params = array_merge($params, $entityIds);
        }

        try {
            $sql = "
                SELECT e.id,
                       COALESCE(et.store_name, e.store_name) AS store_name,
                       e.slug,
                       e.status,
                       COALESCE(es.delivery_radius_km, 0) AS delivery_radius_km,
                       COALESCE(es.preparation_time_minutes, 0) AS preparation_time_minutes,
                       COALESCE(es.min_order_amount, 0) AS min_order_amount,
                       COALESCE(es.allow_cod, 0) AS allow_cod,
                       COALESCE(es.is_visible, 1) AS is_visible,
                       COALESCE(es.maintenance_mode, 0) AS maintenance_mode,
                       (
                           SELECT a.address_line1
                           FROM addresses a
                           WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                           ORDER BY a.is_primary DESC, a.id ASC
                           LIMIT 1
                       ) AS address_line1,
                       (
                           SELECT a.address_line2
                           FROM addresses a
                           WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                           ORDER BY a.is_primary DESC, a.id ASC
                           LIMIT 1
                       ) AS address_line2,
                       (
                           SELECT a.latitude
                           FROM addresses a
                           WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                           ORDER BY a.is_primary DESC, a.id ASC
                           LIMIT 1
                       ) AS latitude,
                       (
                           SELECT a.longitude
                           FROM addresses a
                           WHERE a.owner_type = 'entity' AND a.owner_id = e.id
                           ORDER BY a.is_primary DESC, a.id ASC
                           LIMIT 1
                       ) AS longitude,
                       (
                           SELECT COUNT(*)
                           FROM entity_pickup_points epp
                           WHERE epp.tenant_id = e.tenant_id
                             AND epp.entity_id = e.id
                             AND epp.is_active = 1
                       ) AS pickup_points_count
                  FROM entities e
             LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
             LEFT JOIN entity_settings es ON es.entity_id = e.id
                 WHERE e.tenant_id = ?
                   AND e.status NOT IN ('suspended', 'rejected')
                   $entityFilterSql
                 ORDER BY e.id ASC
                 LIMIT 100
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($rows)) {
                return [];
            }

            $ids = array_values(array_filter(array_map(
                static fn(array $row): int => (int)($row['id'] ?? 0),
                $rows
            )));

            $hoursMap = [];
            if (!empty($ids)) {
                try {
                    $hoursSql = implode(',', array_fill(0, count($ids), '?'));
                    $hoursStmt = $pdo->prepare(
                        "SELECT entity_id, day_of_week, open_time, close_time, is_open
                           FROM entities_working_hours
                          WHERE entity_id IN ($hoursSql)
                          ORDER BY entity_id ASC, day_of_week ASC"
                    );
                    $hoursStmt->execute($ids);
                    foreach ($hoursStmt->fetchAll(PDO::FETCH_ASSOC) as $hourRow) {
                        $hoursMap[(int)$hourRow['entity_id']][] = $hourRow;
                    }
                } catch (Throwable) {
                    $hoursMap = [];
                }
            }

            $items = [];
            foreach ($rows as $row) {
                $entityId = (int)($row['id'] ?? 0);
                if ($entityId <= 0) {
                    continue;
                }

                $hoursState = pub_entity_hours_state($hoursMap[$entityId] ?? []);
                $entityLat = isset($row['latitude']) && $row['latitude'] !== null ? (float)$row['latitude'] : null;
                $entityLng = isset($row['longitude']) && $row['longitude'] !== null ? (float)$row['longitude'] : null;
                $distanceKm = null;

                if ($lat !== null && $lng !== null && $entityLat !== null && $entityLng !== null) {
                    $distanceKm = round(pub_haversine_km($lat, $lng, $entityLat, $entityLng), 2);
                }

                $isVisible = (int)($row['is_visible'] ?? 1) !== 0;
                $inMaintenance = (int)($row['maintenance_mode'] ?? 0) !== 0;
                $isAvailable = $isVisible && !$inMaintenance && (!($hoursState['known'] ?? false) || !empty($hoursState['is_open']));
                $deliveryRadiusKm = (float)($row['delivery_radius_km'] ?? 0);

                $items[] = [
                    'id'                       => $entityId,
                    'name'                     => (string)($row['store_name'] ?? ''),
                    'slug'                     => (string)($row['slug'] ?? ''),
                    'status'                   => (string)($row['status'] ?? ''),
                    'address_line1'            => (string)($row['address_line1'] ?? ''),
                    'address_line2'            => (string)($row['address_line2'] ?? ''),
                    'latitude'                 => $entityLat,
                    'longitude'                => $entityLng,
                    'distance_km'              => $distanceKm,
                    'delivery_radius_km'       => $deliveryRadiusKm,
                    'preparation_time_minutes' => (int)($row['preparation_time_minutes'] ?? 0),
                    'min_order_amount'         => (float)($row['min_order_amount'] ?? 0),
                    'pickup_points_count'      => (int)($row['pickup_points_count'] ?? 0),
                    'allow_cod'                => (bool)($row['allow_cod'] ?? false),
                    'has_delivery_hint'        => $distanceKm !== null && $deliveryRadiusKm > 0
                        ? ($distanceKm <= $deliveryRadiusKm)
                        : ($deliveryRadiusKm > 0),
                    'is_visible'               => $isVisible,
                    'is_available'             => $isAvailable,
                    'is_open_now'              => (bool)($hoursState['is_open'] ?? true),
                    'hours_known'              => (bool)($hoursState['known'] ?? false),
                ];
            }

            usort($items, static function (array $a, array $b): int {
                if (($a['is_available'] ?? false) !== ($b['is_available'] ?? false)) {
                    return ($a['is_available'] ?? false) ? -1 : 1;
                }

                $aDist = $a['distance_km'] ?? null;
                $bDist = $b['distance_km'] ?? null;
                if ($aDist !== null && $bDist !== null && $aDist !== $bDist) {
                    return $aDist <=> $bDist;
                }
                if ($aDist !== null && $bDist === null) {
                    return -1;
                }
                if ($aDist === null && $bDist !== null) {
                    return 1;
                }
                return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            });

            return array_slice($items, 0, max(1, $limit));
        } catch (Throwable $e) {
            error_log('[pub_list_entity_contexts] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('pub_pick_active_entity_candidate')) {
    function pub_pick_active_entity_candidate(array $entities): ?array {
        if (empty($entities)) {
            return null;
        }

        foreach ($entities as $entity) {
            if (!empty($entity['is_available'])) {
                return $entity;
            }
        }

        return $entities[0] ?? null;
    }
}

if (!function_exists('pub_store_active_entity_context')) {
    function pub_store_active_entity_context(
        int $tenantId,
        array $entity,
        string $source = 'manual',
        ?float $lat = null,
        ?float $lng = null
    ): array {
        $_SESSION['pub_active_entity'] ??= [];

        $payload = [
            'id'                       => (int)($entity['id'] ?? 0),
            'name'                     => (string)($entity['name'] ?? $entity['store_name'] ?? ''),
            'slug'                     => (string)($entity['slug'] ?? ''),
            'status'                   => (string)($entity['status'] ?? ''),
            'latitude'                 => isset($entity['latitude']) && $entity['latitude'] !== null ? (float)$entity['latitude'] : null,
            'longitude'                => isset($entity['longitude']) && $entity['longitude'] !== null ? (float)$entity['longitude'] : null,
            'distance_km'              => isset($entity['distance_km']) && $entity['distance_km'] !== null ? (float)$entity['distance_km'] : null,
            'delivery_radius_km'       => (float)($entity['delivery_radius_km'] ?? 0),
            'pickup_points_count'      => (int)($entity['pickup_points_count'] ?? 0),
            'has_delivery_hint'        => !empty($entity['has_delivery_hint']),
            'is_available'             => !empty($entity['is_available']),
            'is_open_now'              => !empty($entity['is_open_now']),
            'source'                   => $source,
            'resolved_at'              => date('c'),
        ];

        $_SESSION['pub_active_entity'][$tenantId] = $payload;

        if ($lat !== null && $lng !== null) {
            pub_update_entity_location_cache($tenantId, $lat, $lng, $source);
        }

        return $payload;
    }
}

if (!function_exists('pub_resolve_active_entity_context')) {
    /**
     * Resolves the storefront's active entity and persists it in session.
     * Priority:
     * 1. Explicit request entity (entity page / query param)
     * 2. Valid session selection
     * 3. Session location â†’ nearest available entity
     * 4. First available entity for the tenant
     */
    function pub_resolve_active_entity_context(int $tenantId, string $lang = 'en'): array {
        if ($tenantId <= 0) {
            return ['id' => 0, 'name' => '', 'source' => 'none'];
        }

        $scriptName = strtolower((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $requestEntityId = 0;

        if (isset($_GET['active_entity_id'])) {
            $requestEntityId = (int)$_GET['active_entity_id'];
        } elseif (isset($_GET['entity_id'])) {
            $requestEntityId = (int)$_GET['entity_id'];
        } elseif (str_ends_with($scriptName, '/entity.php') && isset($_GET['id'])) {
            $requestEntityId = (int)$_GET['id'];
        }

        $locationState = $_SESSION['pub_entity_location'][$tenantId] ?? null;
        $locLat = isset($locationState['lat']) ? (float)$locationState['lat'] : null;
        $locLng = isset($locationState['lng']) ? (float)$locationState['lng'] : null;

        if ($requestEntityId > 0) {
            $requested = pub_list_entity_contexts(
                $tenantId,
                $lang,
                $locLat,
                $locLng,
                1,
                [$requestEntityId]
            );
            if (!empty($requested[0])) {
                return pub_store_active_entity_context(
                    $tenantId,
                    $requested[0],
                    'request',
                    $locLat,
                    $locLng
                );
            }
        }

        $sessionEntityId = (int)($_SESSION['pub_active_entity'][$tenantId]['id'] ?? 0);
        if ($sessionEntityId > 0) {
            $stored = pub_list_entity_contexts(
                $tenantId,
                $lang,
                $locLat,
                $locLng,
                1,
                [$sessionEntityId]
            );
            if (!empty($stored[0]) && !empty($stored[0]['is_available'])) {
                return pub_store_active_entity_context(
                    $tenantId,
                    $stored[0],
                    (string)($_SESSION['pub_active_entity'][$tenantId]['source'] ?? 'session'),
                    $locLat,
                    $locLng
                );
            }
        }

        if ($locLat !== null && $locLng !== null) {
            $nearest = pub_list_entity_contexts($tenantId, $lang, $locLat, $locLng, 8);
            $best = pub_pick_active_entity_candidate($nearest);
            if ($best) {
                return pub_store_active_entity_context($tenantId, $best, 'nearest', $locLat, $locLng);
            }
        }

        $fallback = pub_list_entity_contexts($tenantId, $lang, null, null, 8);
        $best = pub_pick_active_entity_candidate($fallback);
        if ($best) {
            return pub_store_active_entity_context($tenantId, $best, 'fallback', $locLat, $locLng);
        }

        return ['id' => 0, 'name' => '', 'source' => 'none'];
    }
}

/* -------------------------------------------------------
 * 11b. CSP-safe public theme stylesheet helpers
 * ----------------------------------------------------- */
if (!function_exists('pub_safe_theme_css_value')) {
    function pub_safe_theme_css_value(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $value = str_replace(['<', '>', '`', '\\'], '', $value);
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) return $value;
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/-]+\)$/i', $value)) return $value;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]{1,80}\)$/', $value)) return $value;
        if (preg_match('/^(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|ch|ex)?)(?:\s+\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|ch|ex)?){0,3}$/', $value)) return $value;
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9\s,"\'-]{0,120}$/', $value)) return $value;
        if (preg_match('/^\d+(?:px)?\s+\d+(?:px)?\s+\d+(?:px)?(?:\s+\d+(?:px)?)?\s+(?:rgba?\([\d\s%,.\/-]+\)|#[0-9a-fA-F]{3,8})$/', $value)) return $value;
        return '';
    }
}

if (!function_exists('pub_theme_stylesheet_css')) {
    function pub_theme_stylesheet_css(array $theme): string {
        $vars = [];
        $set = static function (string $name, $value) use (&$vars): void {
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
            if ($name === '') return;
            $safe = pub_safe_theme_css_value((string)$value);
            if ($safe !== '') $vars['--' . $name] = $safe;
        };

        $resolved = [
            'pub-primary' => $theme['primary'] ?? '',
            'pub-primary-hover' => $theme['primary_hover'] ?? '',
            'pub-secondary' => $theme['secondary'] ?? '',
            'pub-accent' => $theme['accent'] ?? '',
            'pub-bg' => $theme['background'] ?? '',
            'pub-surface' => $theme['surface'] ?? '',
            'pub-text' => $theme['text'] ?? '',
            'pub-muted' => $theme['text_muted'] ?? '',
            'pub-border' => $theme['border'] ?? '',
            'pub-header-bg' => $theme['header_bg'] ?? '',
            'pub-header-text' => $theme['header_text_color'] ?? '',
            'pub-sidebar-bg' => $theme['sidebar_bg'] ?? '',
            'pub-sidebar-text' => $theme['sidebar_text'] ?? '',
            'pub-sidebar-hover' => $theme['sidebar_toggle_hover'] ?? '',
            'pub-sidebar-active' => $theme['primary'] ?? '',
            'pub-footer-bg' => $theme['footer_bg'] ?? '',
            'pub-footer-text' => $theme['footer_text_color'] ?? '',
            'pub-success' => $theme['success'] ?? '',
            'pub-warning' => $theme['warning'] ?? '',
            'pub-danger' => $theme['danger'] ?? '',
            'pub-error' => $theme['error'] ?? '',
            'pub-info' => $theme['info'] ?? '',
        ];
        foreach ($resolved as $name => $value) $set($name, $value);

        foreach ($theme['color_settings'] ?? [] as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            $value = $row['color_value'] ?? ($row['setting_value'] ?? '');
            if ($key === '') continue;
            $set(str_replace('_', '-', $key), $value);
            $set(str_replace('-', '_', $key), $value);
        }

        foreach ($theme['font_settings'] ?? [] as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '') continue;
            if (!empty($row['font_family'])) $set($key . '-family', $row['font_family']);
            if (!empty($row['font_size'])) $set($key . '-size', is_numeric($row['font_size']) ? $row['font_size'] . 'px' : $row['font_size']);
            if (!empty($row['font_weight'])) $set($key . '-weight', $row['font_weight']);
        }

        foreach ($theme['design_settings'] ?? [] as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '' || $key === 'logo_url') continue;
            $value = (string)($row['setting_value'] ?? '');
            if (($row['setting_type'] ?? '') === 'number' && !preg_match('/[a-z%]$/i', $value)) {
                $value .= 'px';
            }
            $set($key, $value);
        }

        $css = ":root {\n";
        foreach ($vars as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }
        $css .= "  --pub-card-bg: var(--pub-surface);\n";
        $css .= "}\n";

        foreach ($theme['buttons'] ?? [] as $button) {
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)($button['slug'] ?? '')));
            if ($slug === '') continue;
            $decl = [];
            if (!empty($button['background_color']) && ($v = pub_safe_theme_css_value((string)$button['background_color']))) $decl[] = "background-color: {$v}";
            if (!empty($button['text_color']) && ($v = pub_safe_theme_css_value((string)$button['text_color']))) $decl[] = "color: {$v}";
            if (!empty($button['border_color']) && ($v = pub_safe_theme_css_value((string)$button['border_color']))) $decl[] = 'border: ' . max(0, (int)($button['border_width'] ?? 1)) . "px solid {$v}";
            if (isset($button['border_radius'])) $decl[] = 'border-radius: ' . max(0, (int)$button['border_radius']) . 'px';
            if (!empty($button['padding']) && ($v = pub_safe_theme_css_value((string)$button['padding']))) $decl[] = "padding: {$v}";
            if (!empty($decl)) $css .= ".btn-{$slug}, .pub-btn--{$slug} {" . implode(';', $decl) . ";}\n";
        }

        foreach ($theme['cards'] ?? [] as $card) {
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)($card['slug'] ?? '')));
            if ($slug === '') continue;
            $decl = [];
            if (!empty($card['background_color']) && ($v = pub_safe_theme_css_value((string)$card['background_color']))) $decl[] = "background-color: {$v}";
            if (!empty($card['text_color']) && ($v = pub_safe_theme_css_value((string)$card['text_color']))) $decl[] = "color: {$v}";
            if (!empty($card['border_color']) && ($v = pub_safe_theme_css_value((string)$card['border_color']))) $decl[] = 'border: ' . max(0, (int)($card['border_width'] ?? 1)) . "px solid {$v}";
            if (isset($card['border_radius'])) $decl[] = 'border-radius: ' . max(0, (int)$card['border_radius']) . 'px';
            if (!empty($card['shadow_style']) && ($v = pub_safe_theme_css_value((string)$card['shadow_style']))) $decl[] = "box-shadow: {$v}";
            if (!empty($card['padding']) && ($v = pub_safe_theme_css_value((string)$card['padding']))) $decl[] = "padding: {$v}";
            if (!empty($decl)) $css .= ".card-{$slug} {" . implode(';', $decl) . ";}\n";
        }

        return $css;
    }
}


/* -------------------------------------------------------
 * 12. Compose the shared context globals
 * ----------------------------------------------------- */
$_pubIdentity = pub_load_identity(pub_get_pdo());
$_pubIdentityUser = is_array($_pubIdentity['user'] ?? null) ? $_pubIdentity['user'] : null;
$_pubResolvedTenantId = isset($_pubIdentity['resolved_tenant_id']) && is_numeric($_pubIdentity['resolved_tenant_id'])
    ? (int)$_pubIdentity['resolved_tenant_id']
    : 0;
$_pubScriptName = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
$_pubRequestedTenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : 0;
$_pubSessionTenantId = isset($_SESSION['pub_tenant_id']) && is_numeric($_SESSION['pub_tenant_id'])
    ? (int)$_SESSION['pub_tenant_id']
    : 0;
$_pubRequestedThemeTarget = pub_resolve_theme_target($_pubIdentity, $_pubRequestedTenantId);
$_pubIsPlatformHomeRequest = $_pubRequestedThemeTarget === 'platform_home';

$tenantId = $_pubIsPlatformHomeRequest
    ? 1
    : pub_resolve_context_tenant_id($_pubIdentity, pub_get_pdo());

if ($_pubRequestedTenantId > 0 || !$_pubIsPlatformHomeRequest) {
    $_SESSION['pub_tenant_id'] = $tenantId;
}

$theme = pub_load_theme($tenantId, $_pubIdentity);
$_pubNotifications = pub_load_notifications($tenantId);
$_pubActiveEntity = pub_resolve_active_entity_context($tenantId, $lang);

// Resolve logged-in user from session.
// Supports two formats set by different auth paths:
//   - $_SESSION['user'] = [...] (full array, set by API auth)
//   - $_SESSION['user_id'] = 7  (scalar, load user from DB)
$_pubUser = $_pubIdentityUser ?? $_SESSION['user'] ?? $_SESSION['current_user'] ?? null;
if (empty($_pubUser['id']) && !empty($_SESSION['user_id'])) {
    // user_id is set but full user array is missing â€” load from DB
    $_pdo2 = pub_get_pdo();
    if ($_pdo2) {
        try {
            $__us = $_pdo2->prepare('SELECT id, name, username, email, preferred_language, is_active FROM users WHERE id = ? LIMIT 1');
            $__us->execute([(int)$_SESSION['user_id']]);
            $_pubUser = $__us->fetch() ?: null;
            if ($_pubUser) $_SESSION['user'] = $_pubUser; // cache for next request
        } catch (Throwable $_) {}
    }
    unset($_pdo2, $__us);
}

$GLOBALS['PUB_CONTEXT'] = [
    'lang'          => $lang,
    'dir'           => $dir,
    'tenant_id'     => $tenantId,
    'identity'      => $_pubIdentity,
    'active_entity' => $_pubActiveEntity,
    'theme'         => $theme,
    'app'           => $appConfig,
    'user'          => $_pubUser,
    'notifications' => $_pubNotifications,
];

// Export user and login state as global PHP variables so any PHP page can use
// them BEFORE including partials/header.php (which also sets these from PUB_CONTEXT).
// Without this, pages like wishlist.php that check $_isLoggedIn before header.php
// is included would always see the variable as undefined (= null = not logged in).
$_user       = $_pubUser;
$_isLoggedIn = !empty($_pubUser['id']);

unset(
    $_pubUser,
    $_pubNotifications,
    $_pubIdentity,
    $_pubIdentityUser,
    $_pubResolvedTenantId,
    $_pubRequestedTenantId,
    $_pubSessionTenantId,
    $_pubScriptName,
    $_pubRequestedThemeTarget,
    $_pubIsPlatformHomeRequest
);
