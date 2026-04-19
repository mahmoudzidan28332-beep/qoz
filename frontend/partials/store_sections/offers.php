<?php
/**
 * frontend/partials/store_sections/offers.php — QOOQZ Public Store Sections
 * Grid of active discounts (offers) for the entity
 */

require_once __DIR__ . '/icons.php';

?>

<style>
/* ── Offers Section ───────────────────────────────── */
.of-grid { display: grid; gap: 14px; }

.of-card {
    position: relative;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    overflow: hidden;
    transition: border-color .18s, box-shadow .18s;
}
.of-card:hover {
    border-color: var(--pub-primary);
    box-shadow: 0 4px 18px rgba(3,135,78,.08);
}

/* Left accent bar */
.of-card::before {
    content: '';
    position: absolute;
    inset-block: 0;
    left: 0;
    width: 4px;
    background: var(--pub-primary);
    border-radius: 4px 0 0 4px;
}

.of-badge-top {
    position: absolute;
    top: 12px;
    right: 14px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(245,158,11,.12);
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid rgba(245,158,11,.25);
    letter-spacing: .02em;
}

.of-inner {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 18px 18px 22px;
}

.of-icon-wrap {
    flex-shrink: 0;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(3,135,78,.09);
    display: flex; align-items: center; justify-content: center;
    color: var(--pub-primary);
    margin-top: 2px;
}

.of-body { flex: 1; min-width: 0; }

.of-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--pub-text);
    margin: 0 0 4px;
    padding-right: 80px; /* badge clearance */
}

.of-desc {
    font-size: 0.82rem;
    color: var(--pub-muted);
    margin: 0 0 10px;
    line-height: 1.5;
}

.of-code-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.of-code {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: monospace;
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: .08em;
    color: var(--pub-primary);
    background: rgba(3,135,78,.07);
    border: 1.5px dashed rgba(3,135,78,.3);
    border-radius: 6px;
    padding: 5px 12px;
}

.of-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 5px 12px;
    border: 1.5px solid var(--pub-border);
    border-radius: 6px;
    background: var(--pub-bg);
    color: var(--pub-muted);
    cursor: pointer;
    font-family: inherit;
    transition: border-color .15s, color .15s, background .15s;
}
.of-copy-btn:hover {
    border-color: var(--pub-primary);
    color: var(--pub-primary);
    background: rgba(3,135,78,.04);
}
.of-copy-btn.copied {
    border-color: #059669;
    color: #059669;
    background: rgba(5,150,105,.06);
}

.of-expires {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    color: var(--pub-muted);
    margin-top: 8px;
}

/* Terms */
.of-terms {
    border-top: 1px solid var(--pub-border);
    margin: 0 0 0 4px; /* skip accent bar width */
}
.of-terms summary {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--pub-muted);
    cursor: pointer;
    list-style: none;
    transition: color .15s;
}
.of-terms summary::-webkit-details-marker { display: none; }
.of-terms summary:hover { color: var(--pub-text); }
.of-terms-icon { transition: transform .2s; }
details[open] .of-terms-icon { transform: rotate(180deg); }
.of-terms p {
    margin: 0;
    padding: 0 18px 14px;
    font-size: 0.8rem;
    color: var(--pub-muted);
    line-height: 1.6;
}

/* Empty state */
.of-empty { text-align: center; padding: 52px 20px; color: var(--pub-muted); }
.of-empty-icon { margin: 0 auto 14px; opacity: .28; }
.of-empty-msg { font-size: 0.9rem; margin: 0; }
</style>

<div class="pub-entity-section-content" id="sectionOffers">
    <?php if (!empty($discounts)): ?>
    <div class="of-grid">
        <?php foreach ($discounts as $d): ?>
        <div class="of-card<?= $_entityDiscountCardClass ? ' ' . $_entityDiscountCardClass : '' ?>"<?= $_entityDiscountCardStyle ? ' style="' . e($_entityDiscountCardStyle) . '"' : '' ?>>

            <?php if (!empty($d['marketing_badge'])): ?>
                <span class="of-badge-top">
                    <?= icon('gift', 11, '#b45309') ?>
                    <?= e($d['marketing_badge']) ?>
                </span>
            <?php endif; ?>

            <div class="of-inner">
                <div class="of-icon-wrap">
                    <?= icon('tag', 20, 'var(--pub-primary)') ?>
                </div>

                <div class="of-body">
                    <p class="of-title"><?= e($d['title'] ?? $d['code'] ?? '') ?></p>

                    <?php if (!empty($d['description'])): ?>
                        <p class="of-desc"><?= e($d['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($d['code'])): ?>
                        <div class="of-code-row">
                            <span class="of-code">
                                <?= icon('tag', 13, 'var(--pub-primary)') ?>
                                <?= e($d['code']) ?>
                            </span>
                            <button type="button" class="of-copy-btn"
                                    onclick="ofCopyCode('<?= e(addslashes($d['code'])) ?>', this)">
                                <span class="of-copy-icon"><?= icon('copy', 13, 'currentColor') ?></span>
                                <span class="of-copy-text"><?= e(t('discounts.copy_code')) ?></span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($d['ends_at'])): ?>
                        <div class="of-expires">
                            <?= icon('clock', 12, 'var(--pub-muted)') ?>
                            <?= e(t('discounts.expires')) ?>: <?= e(substr($d['ends_at'], 0, 10)) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($d['terms_conditions'])): ?>
                <details class="of-terms">
                    <summary>
                        <?= icon('info', 13, 'currentColor') ?>
                        <?= e(t('discounts.terms')) ?>
                        <span class="of-terms-icon" style="margin-inline-start:auto;">
                            <?= icon('chevron-down', 14, 'currentColor') ?>
                        </span>
                    </summary>
                    <p><?= e($d['terms_conditions']) ?></p>
                </details>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="of-empty">
        <div class="of-empty-icon">
            <?= icon('tag', 44, 'var(--pub-muted)') ?>
        </div>
        <p class="of-empty-msg"><?= e(t('discounts.none')) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
function ofCopyCode(code, btn) {
    navigator.clipboard.writeText(code).then(function(){
        btn.classList.add('copied');
        var iconEl = btn.querySelector('.of-copy-icon');
        var textEl = btn.querySelector('.of-copy-text');
        if (iconEl) iconEl.innerHTML = '<?= addslashes(icon('check', 13, 'currentColor')) ?>';
        if (textEl) textEl.textContent = '<?= e(t('discounts.copied') ?: 'Copied!') ?>';
        setTimeout(function(){
            btn.classList.remove('copied');
            if (iconEl) iconEl.innerHTML = '<?= addslashes(icon('copy', 13, 'currentColor')) ?>';
            if (textEl) textEl.textContent = '<?= e(t('discounts.copy_code')) ?>';
        }, 2000);
    }).catch(function(){
        var t = btn.querySelector('.of-copy-text');
        if (t) { var prev = t.textContent; t.textContent = code; setTimeout(function(){ t.textContent = prev; }, 2000); }
    });
}
</script>