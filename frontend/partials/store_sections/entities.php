<?php
/**
 * frontend/partials/store_sections/entities.php
 * Related Entities Section — Shows sibling entities under the same tenant
 *
 * Expected variables: $entity, $entityId, $pdo, $lang, $tenantId, $sectionSettings
 */

require_once __DIR__ . '/icons.php';

$relLimit = isset($sectionSettings['limit']) ? (int)$sectionSettings['limit'] : 8;

// The current entity's tenant_id
$entityTid = 0;
if (isset($entity['tenant_id'])) {
    $entityTid = (int)$entity['tenant_id'];
} elseif (isset($tenantId)) {
    $entityTid = (int)$tenantId;
}

$relatedEntities = [];
if (isset($pdo) && $pdo instanceof PDO && $entityTid > 0) {
    try {
        $reStmt = $pdo->prepare(
            "SELECT id, store_name, slug, vendor_type, is_verified, status, parent_id
               FROM entities
              WHERE tenant_id = ?
                AND id != ?
                AND status = 'approved'
              ORDER BY is_verified DESC, store_name ASC
              LIMIT ?"
        );
        $reStmt->execute([$entityTid, $entityId, $relLimit]);
        $relatedEntities = $reStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\RuntimeException $e) {
        error_log('[entities-section] query error: ' . $e->getMessage());
        $relatedEntities = [];
    }
}

// API fallback
if (empty($relatedEntities) && $entityTid > 0) {
    $reResp = pub_fetch(pub_api_url('public/entities') . '?tenant_id=' . $entityTid . '&exclude_id=' . $entityId . '&limit=' . $relLimit . '&lang=' . urlencode($lang));
    $relatedEntities = isset($reResp['data']['data']) ? $reResp['data']['data'] : (isset($reResp['data']) && is_array($reResp['data']) ? $reResp['data'] : []);
}

if (empty($relatedEntities)) return;

// FIX: Load logos using owner_id + owner_type = 'entity' (not the non-existent i.entity_id column)
$_reLogos = [];
if (isset($pdo) && $pdo instanceof PDO && !empty($relatedEntities)) {
    try {
        $_reIds  = array_map(function ($r) { return (int)$r['id']; }, $relatedEntities);
        $_phList = implode(',', $_reIds);
        $_logoStmt = $pdo->query(
            "SELECT i.owner_id AS entity_id, i.url
               FROM images i
          LEFT JOIN image_types it ON it.id = i.image_type_id
              WHERE i.owner_type = 'entity'
                AND i.owner_id IN ({$_phList})
                AND (it.code = 'entity_logo' OR it.code = 'logo' OR it.code = 'tenant_logo')
              ORDER BY i.is_main DESC, i.id ASC"
        );
        foreach ($_logoStmt->fetchAll(PDO::FETCH_ASSOC) as $_lr) {
            $_eid = (int)$_lr['entity_id'];
            // Keep only the first (most relevant) logo per entity
            if (!isset($_reLogos[$_eid])) {
                $_reLogos[$_eid] = $_lr['url'];
            }
        }
    } catch (\RuntimeException $_) {
        // images table structure mismatch — skip logos gracefully
        $_reLogos = [];
    }
}
?>

<style>
/* -- Related Entities Section -- */
.re-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
}
.re-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 22px 16px;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius, 12px);
    text-decoration: none;
    color: var(--pub-text);
    text-align: center;
    transition: border-color .2s, box-shadow .2s, transform .15s;
}
.re-card:hover {
    border-color: var(--pub-primary);
    box-shadow: 0 6px 24px rgba(0,0,0,.1);
    transform: translateY(-2px);
}
.re-logo {
    width: 64px; height: 64px;
    border-radius: 14px;
    overflow: hidden;
    background: var(--pub-bg);
    border: 2px solid var(--pub-border);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--pub-muted);
}
.re-logo img {
    width: 100%; height: 100%;
    object-fit: cover;
}
.re-name {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0;
    color: var(--pub-text);
    line-height: 1.3;
}
.re-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}
.re-badge--verified {
    background: rgba(34,197,94,.12);
    color: #065f46;
}
.re-badge--type {
    background: var(--pub-bg);
    border: 1px solid var(--pub-border);
    color: var(--pub-muted);
    font-weight: 500;
}
.re-more {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--pub-primary);
    text-decoration: none;
}
.re-more:hover { text-decoration: underline; }
@media(max-width: 600px) {
    .re-grid { grid-template-columns: repeat(2, 1fr); }
    .re-logo { width: 52px; height: 52px; }
    .re-name { font-size: 0.84rem; }
}
</style>

<div class="pub-entity-section-content" id="sectionEntities">
    <div class="re-grid">
        <?php foreach ($relatedEntities as $re):
            $reName       = isset($re['store_name']) ? $re['store_name'] : '';
            $reId         = (int)($re['id']);
            $reLogo       = $_reLogos[$reId] ?? '';
            $reLink       = '/frontend/public/entity.php?id=' . $reId;
            $reVendorType = isset($re['vendor_type']) ? $re['vendor_type'] : '';
        ?>
        <a href="<?= e($reLink) ?>" class="re-card">
            <div class="re-logo">
                <?php if ($reLogo): ?>
                    <img src="<?= e($reLogo) ?>"
                         alt="<?= e($reName) ?>"
                         loading="lazy"
                         onerror="this.style.display='none';this.parentElement.innerHTML='<?= addslashes(icon('building', 28, 'var(--pub-muted)')) ?>'">
                <?php else: ?>
                    <?= icon('building', 28, 'var(--pub-muted)') ?>
                <?php endif; ?>
            </div>
            <h3 class="re-name"><?= e($reName) ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:5px;justify-content:center;">
                <?php if (!empty($re['is_verified'])): ?>
                    <span class="re-badge re-badge--verified">
                        <?= icon('verified', 12, '#22c55e') ?>
                        <?= e($sectionContentJson['verified'] ?? t('entities.verified', 'Verified')) ?>
                    </span>
                <?php endif; ?>
                <?php if ($reVendorType): ?>
                    <span class="re-badge re-badge--type">
                        <?= icon('store', 12, 'var(--pub-muted)') ?>
                        <?= e(str_replace('_', ' ', $reVendorType)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <a href="/frontend/public/entities.php" class="re-more">
        <?= e($sectionContentJson['view_all'] ?? t('entity.view_all_entities', 'Browse all entities')) ?>
        <?= icon('chevron-right', 16, 'var(--pub-primary)') ?>
    </a>
</div>