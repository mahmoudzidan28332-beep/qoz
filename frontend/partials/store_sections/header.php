<?php
/**
 * frontend/partials/store_sections/header.php
 * Store Header Section — Cover image, logo, name, rating, verification, open/closed
 */

require_once __DIR__ . '/icons.php';

$showCover    = ($sectionSettings['show_cover']    ?? true);
$showRating   = ($sectionSettings['show_rating']   ?? true);
$showVerified = ($sectionSettings['show_verified'] ?? true);
$showStatus   = ($sectionSettings['show_status']   ?? true);
?>

<style>
/* ── Header section ────────────────────────────────── */
.sh-banner {
    width: 100%; height: 220px;
    overflow: hidden;
    background: var(--pub-surface);
    position: relative;
}
@media(min-width: 900px) { .sh-banner { height: 320px; } }
.sh-banner-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sh-banner-placeholder {
    width: 100%; height: 100%;
    background: linear-gradient(135deg, var(--pub-primary) 0%, var(--pub-accent, #f59e0b) 100%);
}

/* ── Profile card ──────────────────────────────────── */
.sh-profile {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    margin-top: -48px;
    position: relative;
    z-index: 2;
    flex-wrap: wrap;
}
.sh-logo {
    width: 96px; height: 96px;
    border-radius: 16px;
    overflow: hidden;
    background: var(--pub-surface);
    border: 3px solid var(--pub-bg);
    flex-shrink: 0;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    display: flex; align-items: center; justify-content: center;
    color: var(--pub-muted);
}
.sh-logo img { width: 100%; height: 100%; object-fit: cover; }

.sh-info { flex: 1; min-width: 0; padding-top: 52px; }
.sh-info-inner { display: flex; flex-direction: column; gap: 7px; }

.sh-name {
    font-size: 1.4rem; font-weight: 800;
    margin: 0; color: var(--pub-text);
    line-height: 1.2;
}
.sh-desc { font-size: 0.91rem; color: var(--pub-muted); margin: 2px 0 0; line-height: 1.5; }

/* ── Pills ─────────────────────────────────────────── */
.sh-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 0.78rem; font-weight: 700;
    width: fit-content;
}
.sh-pill--rating {
    background: rgba(245,158,11,.12);
    color: #92400e;
}
.sh-pill--verified {
    background: rgba(34,197,94,.12);
    color: #065f46;
}
.sh-pill--open {
    background: rgba(34,197,94,.12);
    color: #065f46;
}
.sh-pill--closed {
    background: rgba(239,68,68,.1);
    color: #991b1b;
}
.sh-pill--type {
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    color: var(--pub-text);
    font-weight: 600;
}

/* ── Mobile ────────────────────────────────────────── */
@media(max-width: 600px) {
    .sh-profile { flex-direction: column; align-items: center; text-align: center; gap: 10px; margin-top: -36px; }
    .sh-logo { width: 72px; height: 72px; margin: 0 auto; }
    .sh-info { padding-top: 0; width: 100%; }
    .sh-info-inner { align-items: center; }
    .sh-name { font-size: 1.15rem; }
}
</style>

<!-- Banner -->
<div class="sh-banner">
    <?php if ($showCover && !empty($entity['cover_url'])): ?>
        <img src="<?= e(pub_img($entity['cover_url'], 'entity_cover')) ?>"
             alt="<?= e($entity['store_name']) ?>"
             class="sh-banner-img"
             loading="eager"
             onerror="this.style.display='none'">
    <?php else: ?>
        <div class="sh-banner-placeholder"></div>
    <?php endif; ?>
</div>

<!-- Profile -->
<div class="pub-container">
    <div class="sh-profile">

        <!-- Logo -->
        <div class="sh-logo">
            <?php if (!empty($entity['logo_url'])): ?>
                <img src="<?= e(pub_img($entity['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($entity['store_name']) ?>"
                     loading="eager"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;align-items:center;justify-content:center;">
                    <?= icon('building', 36, 'var(--pub-muted)') ?>
                </span>
            <?php else: ?>
                <?= icon('building', 36, 'var(--pub-muted)') ?>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="sh-info">
            <div class="sh-info-inner">
                <h1 class="sh-name"><?= e($entity['store_name'] ?? '') ?></h1>

                <?php if ($showRating && $entityRatingAvg !== null): ?>
                <span class="sh-pill sh-pill--rating">
                    <?= icon('star', 13, '#f59e0b') ?>
                    <?= number_format((float)$entityRatingAvg, 1) ?>
                    <span style="opacity:.65;font-weight:500;">(<?= (int)$entityRatingTotal ?>)</span>
                </span>
                <?php endif; ?>

                <?php if ($showVerified && !empty($entity['is_verified'])): ?>
                <span class="sh-pill sh-pill--verified">
                    <?= icon('verified', 14, '#22c55e') ?>
                    <?= e(t('entities.verified')) ?>
                </span>
                <?php endif; ?>

                <?php if ($showStatus && $entityIsOpen !== null): ?>
                <span class="sh-pill <?= $entityIsOpen ? 'sh-pill--open' : 'sh-pill--closed' ?>">
                    <?= $entityIsOpen ? icon('dot-pulse', 14) : icon('dot-closed', 14) ?>
                    <?= e($entityOpenLabel) ?>
                </span>
                <?php endif; ?>

                <?php if (!empty($entity['type_name'] ?? $entity['vendor_type'])): ?>
                <span class="sh-pill sh-pill--type">
                    <?= icon('store', 14, 'var(--pub-muted)') ?>
                    <?= e($entity['type_name'] ?? $entity['vendor_type']) ?>
                </span>
                <?php endif; ?>

                <?php if (!empty($entity['description'])): ?>
                <p class="sh-desc"><?= e($entity['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>