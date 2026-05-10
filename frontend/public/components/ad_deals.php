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

$_csDeal = $_cardStyles['promo']['class']  ?? '';
?>
<div class="pub-deals-strip pub-home-slider pub-home-slider--deals">
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
        if ($aType && strpos($aType, 'fixed') !== false)  $badgeCls .= ' pub-deal-val-badge--fixed';
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
    <div class="pub-deal-card<?= $_csDeal ? ' ' . $_csDeal : '' ?>">

        <!-- Coloured top strip -->
        <div class="pub-deal-strip-top<?= $aType && strpos($aType, 'fixed') !== false ? ' pub-deal-strip-top--fixed' : ($aType==='free_shipping' ? ' pub-deal-strip-top--ship' : '') ?>"></div>

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
                      role="button"
                      tabindex="0"
                      data-copy-code="<?= e($dCode) ?>"
                      data-copy-target="hdc-<?= $dId ?>"
                      title="<?= e(t('discounts.copy_code','Copy Code')) ?>">
                    <?= e($dCode) ?>
                </span>
                <button class="pub-btn pub-btn--primary pub-btn--sm pub-deal-copy-btn"
                        type="button"
                        data-copy-code="<?= e($dCode) ?>"
                        data-copy-target="hdc-<?= $dId ?>"
                        title="<?= e(t('discounts.copy_code','Copy Code')) ?>">
                    <?= e(t('discounts.copy_code','Copy')) ?>
                    <svg class="pub-icon pub-icon--sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
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
