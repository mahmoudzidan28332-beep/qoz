<?php
declare(strict_types=1);
/**
 * frontend/public/discounts.php
 * QOOQZ — Public Discounts & Offers Page
 *
 * Features:
 *  - All active discounts across all merchants (or per-merchant via ?entity_id=X)
 *  - Discount value badge (% / fixed / free shipping) from discount_actions
 *  - Merchant filter tabs (all + per-entity)
 *  - Full pagination
 *  - RTL-aware, database-driven styling
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = (int)$ctx['tenant_id'];

$GLOBALS['PUB_PAGE_TITLE'] = t('discounts.page_title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'discounts';

// Resolve card styles from DB
$_discountCardStyle = pub_card_inline_style('discount');
$_discountCardClass = pub_card_css_class('discount');

/* -------------------------------------------------------
 * Filters from URL
 * ----------------------------------------------------- */
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 24;
$entityId = (int)($_GET['entity_id'] ?? 0);
$dType    = trim($_GET['type'] ?? '');

/* -------------------------------------------------------
 * Fetch discounts via PDO (direct — avoids HTTP overhead)
 * ----------------------------------------------------- */
$discounts = [];
$total     = 0;
$pages     = 1;

$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $where  = ["d.status = 'active'", '(d.starts_at IS NULL OR d.starts_at <= NOW())', '(d.ends_at IS NULL OR d.ends_at >= NOW())'];
        $params = [];

        if ($tenantId) {
            $where[]  = "(d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?) 
                         OR EXISTS (SELECT 1 FROM discount_scopes ds_t 
                                    LEFT JOIN categories c_t ON ds_t.scope_type = 'category' AND c_t.id = ds_t.scope_id
                                    LEFT JOIN products p_t ON ds_t.scope_type = 'product' AND p_t.id = ds_t.scope_id
                                    WHERE ds_t.discount_id = d.id 
                                      AND (c_t.tenant_id = ? OR p_t.tenant_id = ?)))";
            $params[] = $tenantId;
            $params[] = $tenantId;
            $params[] = $tenantId;
        }
        if ($entityId) {
            $where[]  = 'd.entity_id = ?';
            $params[] = $entityId;
        }
        if ($dType !== '') {
            $where[]  = 'd.type = ?';
            $params[] = $dType;
        }
        if (($_GET['expires_today'] ?? '0') === '1') {
            $where[] = 'd.ends_at IS NOT NULL AND d.ends_at >= NOW() AND d.ends_at <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM discounts d $whereSQL");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();
        $pages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        $stmt = $pdo->prepare(
            "SELECT d.id, d.entity_id, d.code, d.type, d.auto_apply, d.priority,
                    d.currency_code, d.status,
                    d.max_redemptions, d.current_redemptions,
                    d.starts_at, d.ends_at, d.updated_at,
                    COALESCE(dt.name, d.code, d.type) AS title,
                    dt.description, dt.terms_conditions, dt.marketing_badge,
                    COALESCE(et.store_name, e.store_name, e.slug) AS merchant_name
             FROM discounts d
             LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
             LEFT JOIN entities e ON e.id = d.entity_id
             LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
             $whereSQL
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute(array_merge([$lang, $lang], $params));
        $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich with discount_actions (value badge)
        if ($discounts) {
            $ids  = array_column($discounts, 'id');
            $phs  = implode(',', array_fill(0, count($ids), '?'));
            $aStmt = $pdo->prepare(
                "SELECT discount_id, action_type, action_value
                 FROM discount_actions WHERE discount_id IN ($phs) ORDER BY id ASC"
            );
            $aStmt->execute($ids);
            $actions = [];
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if (!isset($actions[$a['discount_id']])) $actions[$a['discount_id']] = $a;
            }

            // Human-readable type names (fallback when no translation exists)
            $typeLabels = [
                'percentage'             => t('discounts.type_percentage', 'Percentage Discount'),
                'percentage_discount'    => t('discounts.type_percentage', 'Percentage Discount'),
                'percent_discount'       => t('discounts.type_percentage', 'Percentage Discount'),
                'fixed'                  => t('discounts.type_fixed',      'Fixed Amount Discount'),
                'fixed_discount'         => t('discounts.type_fixed',      'Fixed Amount Discount'),
                'fixed_amount'           => t('discounts.type_fixed',      'Fixed Amount Discount'),
                'free_shipping'          => t('discounts.type_free_shipping', 'Free Shipping'),
                'buy_x_get_y'            => t('discounts.type_buy_x_get_y', 'Buy X Get Y'),
                'bundle'                 => t('discounts.type_bundle',     'Bundle Deal'),
            ];

            foreach ($discounts as &$d) {
                $act = $actions[$d['id']] ?? null;
                $atype = $act['action_type'] ?? ($d['type'] ?? '');
                $d['action_type']  = $atype;
                $d['action_value'] = $act['action_value'] ?? null;
                $d['discount_label'] = null;
                $d['type_label'] = $typeLabels[$atype] ?? ($typeLabels[$d['type'] ?? ''] ?? null);
                if ($act) {
                    $v = $act['action_value'] ?? '';
                    $d['discount_label'] = match(true) {
                        in_array($atype, ['percentage_discount','percent_discount','percentage'], true)
                            => number_format((float)$v, 0) . '%',
                        in_array($atype, ['fixed_discount','fixed_amount','fixed'], true)
                            => number_format((float)$v, 2) . ' ' . trim($d['currency_code'] ?? ''),
                        $atype === 'free_shipping'
                            => t('discounts.type_free_shipping', 'Free Shipping'),
                        $atype === 'buy_x_get_y'
                            => t('discounts.type_buy_x_get_y', 'Buy X Get Y'),
                        default => ((string)$v !== '' ? (string)$v : null),
                    };
                }
            }
            unset($d);

            // ── Fetch discount_scopes → resolve product / category names ──
            try {
                $sStmt = $pdo->prepare(
                    "SELECT discount_id, scope_type, scope_id
                     FROM discount_scopes WHERE discount_id IN ($phs)
                     ORDER BY discount_id ASC"
                );
                $sStmt->execute($ids);
                $rawScopes = $sStmt->fetchAll(PDO::FETCH_ASSOC);

                // Group by discount_id; collect scope_ids for resolution
                $scopeMap  = [];
                $pScopeIds = [];
                $cScopeIds = [];
                foreach ($rawScopes as $sc) {
                    $did = (int)$sc['discount_id'];
                    $scopeMap[$did][] = $sc;
                    if ($sc['scope_type'] === 'product'  && $sc['scope_id']) $pScopeIds[] = (int)$sc['scope_id'];
                    if ($sc['scope_type'] === 'category' && $sc['scope_id']) $cScopeIds[] = (int)$sc['scope_id'];
                }

                // Resolve product names
                $productNames = [];
                if ($pScopeIds) {
                    $pphs = implode(',', array_fill(0, count($pScopeIds), '?'));
                    $pnStmt = $pdo->prepare(
                        "SELECT p.id, COALESCE(pt.name, p.slug) AS name
                         FROM products p
                         LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                         WHERE p.id IN ($pphs)"
                    );
                    $pnStmt->execute(array_merge([$lang], $pScopeIds));
                    foreach ($pnStmt->fetchAll(PDO::FETCH_ASSOC) as $pn) {
                        $productNames[(int)$pn['id']] = $pn['name'];
                    }
                }

                // Resolve category names
                $categoryNames = [];
                if ($cScopeIds) {
                    $cphs = implode(',', array_fill(0, count($cScopeIds), '?'));
                    $cnStmt = $pdo->prepare(
                        "SELECT c.id, COALESCE(ct.name, c.slug) AS name
                         FROM categories c
                         LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
                         WHERE c.id IN ($cphs)"
                    );
                    $cnStmt->execute(array_merge([$lang], $cScopeIds));
                    foreach ($cnStmt->fetchAll(PDO::FETCH_ASSOC) as $cn) {
                        $categoryNames[(int)$cn['id']] = $cn['name'];
                    }
                }

                // Attach resolved scope labels to each discount
                foreach ($discounts as &$d) {
                    $d['scope_items']     = [];
                    $d['scope_type_label'] = null;
                    $scopes = $scopeMap[$d['id']] ?? [];
                    if (empty($scopes)) {
                        $d['scope_type_label'] = t('discounts.scope_all', 'All Products');
                    } else {
                        $labels    = [];
                        $firstType = $scopes[0]['scope_type'] ?? '';
                        foreach ($scopes as $sc) {
                            $sid = (int)($sc['scope_id'] ?? 0);
                            $labels[] = match($sc['scope_type']) {
                                'product'  => $productNames[$sid]  ?? ('#' . $sid),
                                'category' => $categoryNames[$sid] ?? ('#' . $sid),
                                'entity'   => $d['merchant_name']  ?? ('#' . $sid),
                                default    => $sc['scope_type'],
                            };
                        }
                        $d['scope_items'] = array_unique($labels);
                        $d['scope_type_label'] = match($firstType) {
                            'product'  => t('discounts.scope_products',   'Selected Products'),
                            'category' => t('discounts.scope_categories', 'Selected Categories'),
                            'entity'   => t('discounts.scope_entity',     'All Store Products'),
                            default    => t('discounts.scope_all',        'All Products'),
                        };
                    }
                }
                unset($d);

            } catch (Throwable $se) {
                error_log('[discounts.php] scopes: ' . $se->getMessage());
            }
        }

        // Merchant list for filter tabs
        $merchantsRaw = [];
        if ($tenantId) {
            $mStmt = $pdo->prepare(
                "SELECT DISTINCT e.id, COALESCE(et.store_name, e.store_name, e.slug) AS name
                 FROM discounts d
                 JOIN entities e ON e.id = d.entity_id
                 LEFT JOIN entity_translations et ON et.entity_id = e.id AND et.language_code = ?
                 WHERE d.status = 'active'
                   AND (d.starts_at IS NULL OR d.starts_at <= NOW())
                   AND (d.ends_at IS NULL OR d.ends_at >= NOW())
                   AND e.tenant_id = ?
                 ORDER BY name ASC"
            );
            $mStmt->execute([$lang, $tenantId]);
            $merchantsRaw = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Throwable $e) {
        error_log('[discounts.php] PDO error: ' . $e->getMessage());
    }
} else {
    // HTTP fallback
    $qs   = http_build_query(array_filter([
        'lang'        => $lang,
        'tenant_id'   => $tenantId ?: null,
        'entity_id'   => $entityId ?: null,
        'type'        => $dType ?: null,
        'page'        => $page,
        'per'         => $perPage,
    ]));
    $resp      = pub_fetch(pub_api_url('public/discounts') . '?' . $qs);
    $discounts = $resp['data']['data'] ?? ($resp['data'] ?? []);
    $total     = (int)($resp['data']['meta']['total'] ?? count($discounts));
    $pages     = (int)($resp['data']['meta']['pages'] ?? 1);
    if (!is_array($discounts)) $discounts = [];
    $merchantsRaw = [];
}

include dirname(__DIR__) . '/partials/header.php';

/* -------------------------------------------------------
 * Build pagination query string helper
 * ----------------------------------------------------- */
$baseQs = array_filter(['entity_id' => $entityId ?: null, 'type' => $dType ?: null]);
$pgUrl  = fn(int $pg) => '?' . http_build_query(array_merge($baseQs, ['page' => $pg]));
?>

<style>
/* ── Discounts page ─────────────────────────────────── */
.disc-hero {
    background: linear-gradient(135deg, var(--pub-primary,#03874e) 0%, var(--pub-accent,#F59E0B) 100%);
    padding: 40px 0 32px;
    text-align: center;
}
.disc-hero h1 {
    font-size: clamp(1.4rem, 3vw, 2.2rem);
    font-weight: 900;
    color: #fff;
    margin: 0 0 8px;
    text-shadow: 0 2px 8px rgba(0,0,0,.25);
}
.disc-hero p {
    font-size: 1rem;
    color: rgba(255,255,255,.88);
    margin: 0;
}
.disc-badge-count {
    display: inline-block;
    background: rgba(255,255,255,.2);
    color: #fff;
    font-size: .8rem;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 999px;
    margin-top: 12px;
    backdrop-filter: blur(6px);
}

/* ── Merchant filter tabs ───────────────────────────── */
.disc-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding: 20px 0 8px;
    overflow-x: auto;
}
.disc-tab {
    padding: 6px 18px;
    border-radius: 999px;
    font-size: .84rem;
    font-weight: 600;
    border: 2px solid var(--pub-border, #333);
    color: var(--pub-text, #fff);
    background: transparent;
    text-decoration: none;
    white-space: nowrap;
    transition: all .2s;
}
.disc-tab:hover,
.disc-tab.active {
    background: var(--pub-primary, #03874e);
    border-color: var(--pub-primary, #03874e);
    color: #fff;
    transform: translateY(-1px);
}

/* ── Cards grid ─────────────────────────────────────── */
.disc-grid {
    display: grid;
    gap: 18px;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    padding: 24px 0 32px;
}

/* Card */
.disc-card {
    background: var(--pub-surface, #1e1e1e);
    border: 1px solid var(--pub-border, #333);
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    transition: transform .22s ease, box-shadow .22s ease;
}
.disc-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.28);
}

/* Gradient top strip based on type */
.disc-card-strip {
    height: 5px;
    background: linear-gradient(90deg, var(--pub-primary,#03874e), var(--pub-accent,#F59E0B));
}
.disc-card--fixed .disc-card-strip {
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
}
.disc-card--free_shipping .disc-card-strip {
    background: linear-gradient(90deg, #0ea5e9, #38bdf8);
}

/* Big value badge */
.disc-value-badge {
    position: absolute;
    top: 12px;
    inset-inline-end: 14px;
    background: var(--pub-primary, #03874e);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 900;
    padding: 5px 14px;
    border-radius: 999px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(0,0,0,.35);
    letter-spacing: .5px;
}
.disc-value-badge--fixed {
    background: #6366f1;
}
.disc-value-badge--ship {
    background: #0ea5e9;
    font-size: .85rem;
}
.disc-value-badge--marketing {
    background: var(--pub-accent, #F59E0B);
    color: #000;
    font-size: .8rem;
    font-weight: 800;
}

/* Card body */
.disc-card-body {
    padding: 52px 16px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.disc-card-merchant {
    font-size: .75rem;
    font-weight: 600;
    color: var(--pub-primary, #03874e);
    text-transform: uppercase;
    letter-spacing: .5px;
}
.disc-card-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--pub-text, #fff);
    margin: 0;
    line-height: 1.35;
}
.disc-card-desc {
    font-size: .83rem;
    color: var(--pub-muted, #aaa);
    margin: 0;
    line-height: 1.5;
}
.disc-card-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.disc-meta-tag {
    font-size: .74rem;
    color: var(--pub-muted, #888);
    background: var(--pub-bg, #0d0d0d);
    padding: 2px 10px;
    border-radius: 999px;
    border: 1px solid var(--pub-border, #333);
}

/* Code row */
.disc-code-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.disc-code {
    font-family: monospace;
    font-size: .9rem;
    font-weight: 800;
    letter-spacing: 2.5px;
    background: var(--pub-bg, #000);
    border: 2px dashed var(--pub-primary, #03874e);
    padding: 4px 14px;
    border-radius: 8px;
    color: var(--pub-primary, #03874e);
    cursor: pointer;
    transition: background .18s;
}
.disc-code:hover {
    background: var(--pub-primary, #03874e);
    color: #fff;
}
.btn-copy-disc {
    font-size: .78rem;
    padding: 5px 14px;
}

/* Card footer */
.disc-card-footer {
    padding: 10px 16px;
    border-top: 1px solid var(--pub-border, #333);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.disc-expires {
    font-size: .75rem;
    color: var(--pub-muted, #888);
}
.disc-expires-soon {
    color: var(--pub-warning, #f59e0b);
    font-weight: 700;
}

/* Terms toggle */
.disc-terms {
    padding: 10px 16px 14px;
    border-top: 1px solid var(--pub-border, #333);
}
.disc-terms summary {
    font-size: .8rem;
    color: var(--pub-muted, #888);
    cursor: pointer;
}
.disc-terms p {
    font-size: .8rem;
    color: var(--pub-muted, #888);
    margin: 6px 0 0;
    line-height: 1.5;
}

/* Scope row: what this discount applies to */
.disc-scope-row {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    flex-wrap: wrap;
    font-size: .77rem;
    color: var(--pub-muted, #888);
    background: var(--pub-bg, #0d0d0d);
    border: 1px solid var(--pub-border, #333);
    border-radius: 7px;
    padding: 5px 10px;
    margin-top: 4px;
    line-height: 1.5;
}
.disc-scope-label {
    font-weight: 700;
    color: var(--pub-primary, #03874e);
    white-space: nowrap;
}
.disc-scope-items {
    color: var(--pub-text, #ddd);
}

/* Empty state */
.disc-empty {
    text-align: center;
    padding: 90px 0;
}
.disc-empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: .5;
}
.disc-empty-msg {
    font-size: 1.1rem;
    color: var(--pub-muted, #888);
}
</style>

<!-- Hero banner -->
<div class="disc-hero">
    <div class="pub-container">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.3em;height:1.3em;display:inline-block;vertical-align:middle;margin-inline-end:6px"><path fill-rule="evenodd" d="M1.5 6.375c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v3.026a.75.75 0 01-.375.65 2.249 2.249 0 000 3.898.75.75 0 01.375.65v3.026c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 17.625v-3.026a.75.75 0 01.374-.65 2.249 2.249 0 000-3.898.75.75 0 01-.374-.65V6.375zm15-1.125a.75.75 0 01.75.75v.75a.75.75 0 01-1.5 0V6a.75.75 0 01.75-.75zm.75 4.5a.75.75 0 00-1.5 0v.75a.75.75 0 001.5 0v-.75zm-.75 3a.75.75 0 01.75.75v.75a.75.75 0 01-1.5 0v-.75a.75.75 0 01.75-.75zm.75 4.5a.75.75 0 00-1.5 0V18a.75.75 0 001.5 0v-.75zM6 12a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5A.75.75 0 016 12zm.75 2.25a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5z" clip-rule="evenodd"/></svg>
            <?= e(t('discounts.page_title', 'Offers & Discounts')) ?>
        </h1>
        <p><?= e(t('discounts.page_subtitle', 'Discover the best deals and discounts from our vendors')) ?></p>
        <?php if ($total > 0): ?>
        <div class="disc-badge-count"><?= number_format($total) ?> <?= e(t('discounts.offers_count', 'offer(s) available')) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="pub-container">

    <!-- Breadcrumb -->
    <nav style="font-size:.84rem;color:var(--pub-muted);margin:16px 0 0;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home', 'Home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('discounts.page_title', 'Offers & Discounts')) ?></span>
    </nav>

    <?php if (!empty($merchantsRaw)): ?>
    <!-- Merchant filter tabs -->
    <div class="disc-tabs" role="tablist">
        <a href="?<?= http_build_query(array_filter(['type' => $dType ?: null])) ?>"
           class="disc-tab<?= !$entityId ? ' active' : '' ?>"
           role="tab"><?= e(t('discounts.all_merchants', 'All')) ?></a>
        <?php foreach ($merchantsRaw as $m): ?>
        <a href="?<?= http_build_query(array_filter(['entity_id' => (int)$m['id'], 'type' => $dType ?: null])) ?>"
           class="disc-tab<?= (int)$m['id'] === $entityId ? ' active' : '' ?>"
           role="tab"><?= e($m['name'] ?? '') ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($discounts)): ?>
    <!-- Empty state -->
    <div class="disc-empty">
        <div class="disc-empty-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:64px;height:64px;opacity:.35"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L9.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
        </div>
        <p class="disc-empty-msg"><?= e(t('discounts.none', 'No active offers at this time')) ?></p>
        <a href="/frontend/public/products.php<?= $tenantId ? '?tenant_id=' . $tenantId : '' ?>"
           class="pub-btn pub-btn--primary" style="margin-top:16px;text-decoration:none;">
            <?= e(t('nav.products', 'Products')) ?>
        </a>
    </div>

    <?php else: ?>
    <!-- Grid -->
    <div class="disc-grid" role="list">
        <?php foreach ($discounts as $d):
            $dId        = (int)($d['id'] ?? 0);
            $dCode      = $d['code'] ?? '';
            $dLabel     = $d['discount_label'] ?? null;
            $dTypeLabel = $d['type_label'] ?? null;
            $dTitle     = $d['title'] ?? $dCode;
            // If title equals the raw code, show the type label as display name
            if ($dTitle === $dCode && $dTypeLabel) $dTitle = $dTypeLabel;
            $dDesc      = $d['description'] ?? '';
            $dMerchant  = $d['merchant_name'] ?? '';
            $dBadge     = $d['marketing_badge'] ?? '';
            $dEnds      = $d['ends_at'] ?? null;
            $dTerms     = $d['terms_conditions'] ?? '';
            $dMax       = (int)($d['max_redemptions'] ?? 0);
            $dCurrent   = (int)($d['current_redemptions'] ?? 0);
            $dRemain    = $dMax > 0 ? max(0, $dMax - $dCurrent) : null;
            $aType      = $d['action_type'] ?? '';
            $scopeLabel = $d['scope_type_label'] ?? null;
            $scopeItems = $d['scope_items'] ?? [];

            // Expiry urgency (trust SQL — just compute display label)
            $expiresMsg  = '';
            $expiresClass = '';
            if ($dEnds) {
                $diff = strtotime($dEnds) - time();
                if ($diff > 0) {
                    if ($diff < 86400) {
                        $hrs = ceil($diff / 3600);
                        $expiresMsg  = t('discounts.expires_in_hours', 'Expires in') . ' ' . $hrs . ' ' . t('discounts.hours', 'hours');
                        $expiresClass = 'disc-expires-soon';
                    } elseif ($diff < 86400 * 3) {
                        $days = ceil($diff / 86400);
                        $expiresMsg  = t('discounts.expires_in_days', 'Expires in') . ' ' . $days . ' ' . t('discounts.days', 'days');
                        $expiresClass = 'disc-expires-soon';
                    } else {
                        $expiresMsg = t('discounts.expires', 'Expires') . ': ' . substr($dEnds, 0, 10);
                    }
                }
            }

            // Card CSS type class
            $typeClass = '';
            if ($aType && str_contains($aType, 'fixed')) $typeClass = 'disc-card--fixed';
            elseif ($aType === 'free_shipping')           $typeClass = 'disc-card--free_shipping';

            // Value badge variant
            $badgeClass = 'disc-value-badge';
            if ($aType && str_contains($aType, 'fixed')) $badgeClass .= ' disc-value-badge--fixed';
            elseif ($aType === 'free_shipping')           $badgeClass .= ' disc-value-badge--ship';
        ?>
        <div class="disc-card <?= $typeClass ?><?= $_discountCardClass ? ' ' . $_discountCardClass : '' ?>"
             <?= $_discountCardStyle ? 'style="' . e($_discountCardStyle) . '"' : '' ?>
             role="listitem">

            <!-- Top colour strip -->
            <div class="disc-card-strip"></div>

            <!-- Discount value badge -->
            <?php if ($dBadge): ?>
                <span class="<?= $badgeClass ?> disc-value-badge--marketing"><?= e($dBadge) ?></span>
            <?php elseif ($dLabel): ?>
                <span class="<?= $badgeClass ?>"><?= e($dLabel) ?></span>
            <?php endif; ?>

            <!-- Body -->
            <div class="disc-card-body">
                <?php if ($dMerchant): ?>
                <p class="disc-card-merchant"><?= e($dMerchant) ?></p>
                <?php endif; ?>
                <p class="disc-card-title"><?= e($dTitle) ?></p>
                <?php if ($dDesc): ?>
                <p class="disc-card-desc"><?= e($dDesc) ?></p>
                <?php endif; ?>

                <!-- Meta tags -->
                <div class="disc-card-meta">
                    <?php if ($dRemain !== null && $dRemain <= 20): ?>
                        <span class="disc-meta-tag" style="color:var(--pub-warning,#f59e0b);border-color:var(--pub-warning,#f59e0b);">
                            <?= $dRemain ?> <?= e(t('discounts.remaining', 'left')) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($d['auto_apply'] ?? 0): ?>
                        <span class="disc-meta-tag"><?= e(t('discounts.auto_apply', 'Auto Apply')) ?></span>
                    <?php endif; ?>
                    <?php if ($dTypeLabel && $dTitle !== $dTypeLabel): ?>
                        <span class="disc-meta-tag"><?= e($dTypeLabel) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Scope: what does this discount apply to? -->
                <?php if ($scopeLabel || !empty($scopeItems)): ?>
                <div class="disc-scope-row">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.9em;height:.9em;flex-shrink:0;opacity:.6"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    <span class="disc-scope-label"><?= e($scopeLabel ?? '') ?><?php if (!empty($scopeItems)): ?>:<?php endif; ?></span>
                    <?php if (!empty($scopeItems)): ?>
                    <span class="disc-scope-items"><?= e(implode(', ', array_slice($scopeItems, 0, 3))) ?>
                        <?php if (count($scopeItems) > 3): ?><em style="opacity:.6"> +<?= count($scopeItems) - 3 ?></em><?php endif; ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Promo code -->
                <?php if ($dCode): ?>
                <div class="disc-code-row">
                    <span class="disc-code"
                          id="dc-<?= $dId ?>"
                          title="<?= e(t('discounts.copy_code', 'Copy Code')) ?>"
                          onclick="pubCopyDiscountCode('<?= e(addslashes($dCode)) ?>', 'dc-<?= $dId ?>', event)">
                        <?= e($dCode) ?>
                    </span>
                    <button class="pub-btn pub-btn--ghost pub-btn--sm btn-copy-disc"
                            type="button"
                            onclick="pubCopyDiscountCode('<?= e(addslashes($dCode)) ?>', 'dc-<?= $dId ?>', event)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1em;height:1em;display:inline-block;vertical-align:middle"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
                        <?= e(t('discounts.copy_code', 'Copy')) ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer: expiry + products link -->
            <?php if ($expiresMsg || $dMerchant): ?>
            <div class="disc-card-footer">
                <?php if ($expiresMsg): ?>
                <span class="disc-expires <?= $expiresClass ?>"><?= e($expiresMsg) ?></span>
                <?php else: ?>
                <span></span>
                <?php endif; ?>
                <?php if ((int)($d['entity_id'] ?? 0) > 0): ?>
                <a href="/frontend/public/products.php?entity_id=<?= (int)$d['entity_id'] ?>&<?= $tenantId ? 'tenant_id=' . $tenantId : '' ?>"
                   class="pub-btn pub-btn--ghost pub-btn--sm"
                   style="font-size:.78rem;text-decoration:none;">
                    <?= e(t('discounts.shop_now', 'Shop Now')) ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Terms & conditions (collapsible) -->
            <?php if ($dTerms): ?>
            <details class="disc-terms">
                <summary><?= e(t('discounts.terms', 'Terms & Conditions')) ?></summary>
                <p><?= e($dTerms) ?></p>
            </details>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <nav class="pub-pagination" aria-label="Pagination">
        <a href="<?= $pgUrl(max(1, $page - 1)) ?>"
           class="pub-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
            <?= e(t('pagination.prev', 'Prev')) ?>
        </a>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <a href="<?= $pgUrl($i) ?>"
           class="pub-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pgUrl(min($pages, $page + 1)) ?>"
           class="pub-page-btn <?= $page >= $pages ? 'disabled' : '' ?>">
            <?= e(t('pagination.next', 'Next')) ?>
        </a>
    </nav>
    <?php endif; ?>

    <?php endif; // end: has discounts ?>
</div><!-- /.pub-container -->

<script>
function pubCopyDiscountCode(code, elId, e) {
    if (e) { e.stopPropagation(); }
    var el = document.getElementById(elId);
    var orig = el ? el.textContent : code;

    function flash() {
        if (el) { el.textContent = '✅'; setTimeout(function(){ el.textContent = orig; }, 1800); }
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(flash).catch(function() {
            _fallbackCopy(code); flash();
        });
    } else {
        _fallbackCopy(code); flash();
    }
}
function _fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>