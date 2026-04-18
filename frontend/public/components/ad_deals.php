<?php
declare(strict_types=1);
/**
 * Component: ad_deals
 * Renders discount/offer cards on the homepage with:
 *  - Discount value badge (% / fixed / free shipping) from discount_actions
 *  - Merchant name
 *  - Expiry & remaining uses
 *  - Code copy button
 *  - "View all" link → discounts.php
 */

if (empty($sectionData)) {
    return;
}

$_cDeal  = $_cardStyles['promo']['inline'] ?? '';
$_csDeal = $_cardStyles['promo']['class']  ?? '';
?>
<div class="pub-deals-strip">
    <?php foreach (array_slice($sectionData, 0, 20) as $deal):
        $dId    = (int)($deal['id'] ?? 0);
        $dCode  = $deal['code'] ?? '';
        $dLabel = $deal['discount_label'] ?? null;
        $dTitle = $deal['title'] ?? $dCode ?? '';
        $dMerch = $deal['merchant_name'] ?? '';
        $dBadge = $deal['marketing_badge'] ?? '';
        $dEnds  = $deal['ends_at'] ?? null;
        $aType  = $deal['action_type'] ?? ($deal['type'] ?? '');

        // Human-readable type fallback for title
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
        $dTypeLabel = $typeLabels[$aType] ?? ($typeLabels[$deal['type'] ?? ''] ?? null);
        if ($dTitle === $dCode && $dTypeLabel) {
            $dTitle = $dTypeLabel;
        }

        // Badge colour class
        $badgeCls = 'pub-deal-val-badge';
        if ($aType && str_contains($aType, 'fixed'))  $badgeCls .= ' pub-deal-val-badge--fixed';
        elseif ($aType === 'free_shipping')            $badgeCls .= ' pub-deal-val-badge--ship';

        // Expiry label
        $expiresMsg   = '';
        $expiresCls   = '';
        if ($dEnds) {
            $diff = strtotime($dEnds) - time();
            if ($diff > 0) {
                if ($diff < 86400) {
                    $expiresMsg = t('discounts.expires_in_hours', 'Expires in') . ' ' . ceil($diff/3600) . 'h';
                    $expiresCls = 'pub-deal-exp--soon';
                } else {
                    $expiresMsg = t('discounts.expires', 'Expires') . ': ' . substr($dEnds, 0, 10);
                }
            }
        }
    ?>
    <div class="pub-deal-card<?= $_csDeal ? ' ' . $_csDeal : '' ?>"
         <?= $_cDeal ? 'style="' . e($_cDeal) . '"' : '' ?>>

        <!-- Coloured top strip -->
        <div class="pub-deal-strip-top<?= $aType && str_contains($aType, 'fixed') ? ' pub-deal-strip-top--fixed' : ($aType==='free_shipping' ? ' pub-deal-strip-top--ship' : '') ?>"></div>

        <!-- Value badge (top-right) -->
        <?php if ($dBadge): ?>
            <span class="pub-deal-val-badge pub-deal-val-badge--promo"><?= e($dBadge) ?></span>
        <?php elseif ($dLabel): ?>
            <span class="<?= $badgeCls ?>"><?= e($dLabel) ?></span>
        <?php endif; ?>

        <div class="pub-deal-body">
            <?php if ($dMerch): ?>
                <p class="pub-deal-merchant"><?= e($dMerch) ?></p>
            <?php endif; ?>
            <p class="pub-deal-title"><?= e($dTitle) ?></p>

            <?php if (!empty($deal['scope_summary'])): ?>
                <p class="pub-deal-scopes">
                    <span class="pub-deal-scopes-label"><?= e(t('discounts.applies_to', 'Applies to')) ?>:</span>
                    <?= e($deal['scope_summary']) ?>
                </p>
            <?php endif; ?>

            <?php if ($dCode): ?>
            <div class="pub-deal-code-row">
                <span class="pub-deal-code"
                      id="hdc-<?= $dId ?>"
                      onclick="pubCopyDeal('<?= e(addslashes($dCode)) ?>','hdc-<?= $dId ?>')"
                      title="<?= e(t('discounts.copy_code','Copy Code')) ?>">
                    <?= e($dCode) ?>
                </span>
                <button class="pub-btn pub-btn--ghost pub-btn--sm"
                        type="button"
                        onclick="pubCopyDeal('<?= e(addslashes($dCode)) ?>','hdc-<?= $dId ?>')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.9em;height:.9em;vertical-align:middle"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
                </button>
            </div>
            <?php endif; ?>

            <?php if ($expiresMsg): ?>
                <p class="pub-deal-exp <?= $expiresCls ?>"><?= e($expiresMsg) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
.pub-deals-strip {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}
.pub-deal-card {
    position: relative;
    background: var(--pub-surface, #1e1e1e);
    border: 1px solid var(--pub-border, #333);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform .2s ease, box-shadow .2s ease;
}
.pub-deal-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(0,0,0,.25);
}
.pub-deal-strip-top {
    height: 4px;
    background: linear-gradient(90deg, var(--pub-primary,#03874e), var(--pub-accent,#F59E0B));
}
.pub-deal-strip-top--fixed { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.pub-deal-strip-top--ship  { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }

/* Value badge */
.pub-deal-val-badge {
    position: absolute;
    top: 10px;
    inset-inline-end: 12px;
    background: var(--pub-primary, #03874e);
    color: #fff;
    font-size: .95rem;
    font-weight: 900;
    padding: 4px 12px;
    border-radius: 999px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.pub-deal-val-badge--fixed { background: #6366f1; }
.pub-deal-val-badge--ship  { background: #0ea5e9; font-size: .75rem; }
.pub-deal-val-badge--promo { background: var(--pub-accent,#F59E0B); color: #000; font-size: .78rem; }

/* Body */
.pub-deal-body {
    padding: 42px 14px 14px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex: 1;
}
.pub-deal-merchant {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--pub-primary, #03874e);
    letter-spacing: .4px;
    margin: 0;
}
.pub-deal-title {
    font-size: .92rem;
    font-weight: 700;
    color: var(--pub-text, #fff);
    margin: 0;
    line-height: 1.35;
}
.pub-deal-scopes {
    font-size: .72rem;
    color: var(--pub-muted, #888);
    margin: 2px 0 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.pub-deal-scopes-label {
    font-weight: 700;
    color: var(--pub-text, #fff);
    font-size: .68rem;
    text-transform: uppercase;
    margin-inline-end: 4px;
}
.pub-deal-code-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.pub-deal-code {
    font-family: monospace;
    font-size: .84rem;
    font-weight: 800;
    letter-spacing: 2px;
    border: 2px dashed var(--pub-primary, #03874e);
    padding: 3px 10px;
    border-radius: 6px;
    color: var(--pub-primary, #03874e);
    cursor: pointer;
    background: var(--pub-bg, #000);
    transition: background .15s, color .15s;
}
.pub-deal-code:hover {
    background: var(--pub-primary, #03874e);
    color: #fff;
}
.pub-deal-exp {
    font-size: .73rem;
    color: var(--pub-muted, #888);
    margin: 2px 0 0;
}
.pub-deal-exp--soon {
    color: var(--pub-warning, #f59e0b);
    font-weight: 700;
}
</style>

<script>
if (typeof pubCopyDeal === 'undefined') {
    function pubCopyDeal(code, elId) {
        var el   = document.getElementById(elId);
        var orig = el ? el.textContent.trim() : code;
        function flash() { if (el) { el.textContent = '✅'; setTimeout(function(){ el.textContent = orig; }, 1800); } }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(flash).catch(function(){
                var ta = document.createElement('textarea');
                ta.value = code; ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch(e) {}
                document.body.removeChild(ta); flash();
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = code; ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta); flash();
        }
    }
}
</script>