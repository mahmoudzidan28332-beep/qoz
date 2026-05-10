<?php
declare(strict_types=1);
/**
 * Component: ad_tenants
 * Renders tenant/store cards.
 */

if (empty($sectionData)) {
    return;
}

$_clsTenant = $_cardStyles['tenants']['class'] ?? '';
?>
<div class="pub-grid-md">
    <?php foreach ($sectionData as $ten): ?>
    <a href="/frontend/public/tenant.php?id=<?= (int)($ten['id'] ?? 0) ?>"
       class="pub-entity-card<?= $_clsTenant ? ' ' . $_clsTenant : '' ?>" 
       >
        <div class="pub-entity-avatar">
            <?php if (!empty($ten['logo_url'])): ?>
                <img src="<?= e(pub_img($ten['logo_url'], 'tenant_logo')) ?>"
                     alt="<?= e($ten['name'] ?? '') ?>" 
                     loading="lazy"
                     data-fallback-image>
                <span class="pub-img-placeholder" hidden aria-hidden="true">ًںڈھ</span>
            <?php else: ?>
                <span aria-hidden="true">ًںڈھ</span>
            <?php endif; ?>
        </div>
        <div class="pub-entity-info">
            <p class="pub-entity-name"><?= e($ten['name'] ?? '') ?></p>
            <?php if (($ten['status'] ?? '') === 'active'): ?>
                <span class="pub-entity-verified">ًںں¢ <?= e(t('tenants.active')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
