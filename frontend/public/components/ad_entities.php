<?php
declare(strict_types=1);
/**
 * Component: ad_entities
 * Renders entity/vendor/brand cards with avatars and verification badges.
 */
if (empty($sectionData)) {
    return;
}
$_clsEntity = $_cardStyles['entities']['class'] ?? '';
?>
<div class="pub-grid-md">
    <?php foreach ($sectionData as $ent):
        $entId   = (int)($ent['id'] ?? 0);
        $entName = $ent['store_name'] ?? $ent['name'] ?? '';
    ?>
    <a href="/frontend/public/entity.php?id=<?= $entId ?>"
       class="pub-entity-card<?= $_clsEntity ? ' ' . e($_clsEntity) : '' ?>"
       data-track-type="entity"
       data-track-id="<?= $entId ?>">

        <div class="pub-entity-avatar">
            <?php if (!empty($ent['logo_url'])): ?>
                <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($entName) ?>"
                     loading="lazy"
                     data-fallback-image>
                <span class="pub-img-placeholder" hidden aria-hidden="true">&#127970;</span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true">&#127970;</span>
            <?php endif; ?>
        </div>

        <div class="pub-entity-info">
            <p class="pub-entity-name"><?= e($entName) ?></p>
            <?php if (!empty($ent['vendor_type'])): ?>
                <p class="pub-entity-desc"><?= e($ent['vendor_type']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ent['is_verified'])): ?>
                <span class="pub-entity-verified">&#9989; <?= e(t('entities.verified')) ?></span>
            <?php endif; ?>
        </div>

    </a>
    <?php endforeach; ?>
</div>