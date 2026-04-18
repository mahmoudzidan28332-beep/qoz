<?php
/**
 * frontend/partials/store_sections/location.php
 * Store Location Section — Addresses with map links
 */

require_once __DIR__ . '/icons.php';

$showOsm    = ($sectionSettings['show_osm']    ?? true);
$showGoogle = ($sectionSettings['show_google'] ?? true);
?>

<style>
/* ── Location section ──────────────────────────────── */
.loc-grid { display: grid; gap: 14px; }

.loc-card {
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
}
.loc-card:hover {
    border-color: var(--pub-primary);
    box-shadow: 0 4px 16px rgba(3,135,78,.08);
}

.loc-card-head {
    display: flex; align-items: center; gap: 9px;
    padding: 13px 16px;
    border-bottom: 1px solid var(--pub-border);
}
.loc-card-head svg { flex-shrink: 0; color: var(--pub-primary); }
.loc-card-title { font-size: 0.88rem; font-weight: 700; color: var(--pub-text); flex: 1; }
.loc-primary-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.7rem; font-weight: 700;
    padding: 2px 9px;
    background: var(--pub-primary);
    color: #fff;
    border-radius: 999px;
}

.loc-addr {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 12px 16px;
    font-size: 0.87rem;
    color: var(--pub-text);
    line-height: 1.5;
}
.loc-addr svg { margin-top: 2px; flex-shrink: 0; opacity: .5; }

.loc-actions {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 0 16px 16px;
}
.loc-map-btn {
    display: inline-flex; align-items: center; gap: 7px;
    flex: 1;
    justify-content: center;
    padding: 9px 14px;
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    background: var(--pub-bg);
    color: var(--pub-text);
    font-size: 0.82rem; font-weight: 600;
    text-decoration: none;
    transition: border-color .15s, color .15s, background .15s;
    white-space: nowrap;
}
.loc-map-btn:hover {
    border-color: var(--pub-primary);
    color: var(--pub-primary);
    background: rgba(3,135,78,.04);
}
.loc-map-btn svg { flex-shrink: 0; }

/* Empty state */
.loc-empty { text-align: center; padding: 52px 20px; color: var(--pub-muted); }
.loc-empty-icon { margin: 0 auto 14px; opacity: .25; }
.loc-empty-msg { font-size: 0.9rem; margin: 0; }
</style>

<div class="pub-entity-section-content" id="sectionLocation">
    <?php if (!empty($entity['addresses'])): ?>
    <div class="loc-grid">
        <?php foreach ($entity['addresses'] as $addr): ?>
        <div class="loc-card">
            <div class="loc-card-head">
                <?= icon('pin', 17, 'var(--pub-primary)') ?>
                <span class="loc-card-title"><?= e($addr['label'] ?? t('entity.address', 'Address')) ?></span>
                <?php if (!empty($addr['is_primary'])): ?>
                <span class="loc-primary-badge">
                    <?= icon('star', 11, '#fff') ?>
                    <?= e(t('entity.primary', 'Primary')) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php $hasAddr = !empty($addr['address_line1']); ?>
            <?php if ($hasAddr): ?>
            <div class="loc-addr">
                <?= icon('map', 15, 'var(--pub-muted)') ?>
                <span>
                    <?= e($addr['address_line1']) ?>
                    <?php if (!empty($addr['address_line2'])): ?>, <?= e($addr['address_line2']) ?><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
            <div class="loc-actions">
                <?php if ($showOsm): ?>
                <a href="https://www.openstreetmap.org/?mlat=<?= e($addr['latitude']) ?>&mlon=<?= e($addr['longitude']) ?>#map=16/<?= e($addr['latitude']) ?>/<?= e($addr['longitude']) ?>"
                   target="_blank" rel="noopener" class="loc-map-btn">
                    <?= icon('map', 15, 'currentColor') ?>
                    <?= e(t('entity.view_on_map')) ?>
                </a>
                <?php endif; ?>
                <?php if ($showGoogle): ?>
                <a href="https://maps.google.com/?q=<?= e($addr['latitude']) ?>,<?= e($addr['longitude']) ?>"
                   target="_blank" rel="noopener" class="loc-map-btn">
                    <?= icon('navigation', 15, 'currentColor') ?>
                    Google Maps
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="loc-empty">
        <div class="loc-empty-icon">
            <?= icon('pin', 44, 'var(--pub-muted)') ?>
        </div>
        <p class="loc-empty-msg"><?= e(t('entity.no_addresses')) ?></p>
    </div>
    <?php endif; ?>
</div>