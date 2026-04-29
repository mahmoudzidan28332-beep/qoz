<?php
/**
 * frontend/partials/store_sections/attributes.php — QOOQZ Public Store Sections
 * Merchant Attributes (e.g. WiFi, Parking, etc.)
 */
require_once __DIR__ . '/icons.php';

$_eid  = (int)($entity['id'] ?? $entityId ?? 0);
$attrs = $entity['attributes'] ?? [];

if (empty($attrs) && $pdo && $_eid) {
    try {
        $atStmt = $pdo->prepare(
            "SELECT COALESCE(eat.name, ea.slug) AS attribute_name,
                    eat.description AS attribute_description,
                    ea.attribute_type,
                    eav.value
               FROM entities_attribute_values eav
          LEFT JOIN entities_attributes ea ON ea.id = eav.attribute_id
          LEFT JOIN entities_attribute_translations eat ON eat.attribute_id = ea.id AND eat.language_code = ?
              WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != ''
              ORDER BY ea.sort_order ASC, ea.id ASC
              LIMIT 50"
        );
        $atStmt->execute([$lang, $_eid]);
        $attrs = $atStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {
        $attrs = [];
    }
}


if (empty($attrs)): ?>
<!-- No attributes configured for this entity -->
<?php else: ?>

<style>
/* ── Attributes ─────────────────────────────────── */
.at-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 10px;
}
.at-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    transition: border-color .15s;
}
.at-item:hover { border-color: rgba(3,135,78,.35); }

.at-name-wrap { display: flex; align-items: center; gap: 7px; flex-shrink: 0; }
.at-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--pub-primary);
    opacity: .5;
    flex-shrink: 0;
}
.at-name { font-weight: 600; font-size: 0.88rem; color: var(--pub-text); }
.at-value { font-size: 0.87rem; color: var(--pub-muted); text-align: end; word-break: break-word; }

.at-bool-yes {
    display: inline-flex; align-items: center; gap: 4px;
    font-weight: 700; font-size: 0.82rem;
    color: #059669;
    background: rgba(5,150,105,.08);
    border: 1px solid rgba(5,150,105,.2);
    padding: 3px 9px; border-radius: 999px;
}
.at-bool-no {
    display: inline-flex; align-items: center; gap: 4px;
    font-weight: 700; font-size: 0.82rem;
    color: #dc2626;
    background: rgba(220,38,38,.06);
    border: 1px solid rgba(220,38,38,.15);
    padding: 3px 9px; border-radius: 999px;
}

/* Empty */
.at-empty { text-align: center; padding: 40px 20px; color: var(--pub-muted); }
.at-empty-icon { margin: 0 auto 12px; opacity: .25; }

@media (max-width: 600px) { .at-grid { grid-template-columns: 1fr; } }
</style>

<div class="pub-entity-section-content" id="sectionAttributes">
    <?php
    $rendered = 0;
    ob_start();
    foreach ($attrs as $attr):
        $attrName  = trim($attr['attribute_name'] ?? '');
        $attrValue = trim($attr['value'] ?? '');
        $attrType  = $attr['attribute_type'] ?? 'text';
        if ($attrName === '' || $attrValue === '') continue;
        $rendered++;

        /* Boolean pill */
        $isTrue  = ($attrValue === '1' || strtolower($attrValue) === 'true');
        $isBool  = ($attrType === 'boolean');
    ?>
    <div class="at-item">
        <div class="at-name-wrap">
            <span class="at-dot"></span>
            <span class="at-name"><?= e($attrName) ?></span>
        </div>
        <?php if ($isBool): ?>
            <?php if ($isTrue): ?>
                <span class="at-bool-yes">
                    <?= icon('check-circle', 13, '#059669') ?>
                    <?= e(t('common.yes', 'Yes')) ?>
                </span>
            <?php else: ?>
                <span class="at-bool-no">
                    <?= icon('x-circle', 13, '#dc2626') ?>
                    <?= e(t('common.no', 'No')) ?>
                </span>
            <?php endif; ?>
        <?php else: ?>
            <span class="at-value"><?= e($attrValue) ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach;
    $html = ob_get_clean();

    if ($rendered > 0): ?>
    <div class="at-grid"><?= $html ?></div>
    <?php else: ?>
    <div class="at-empty">
        <div class="at-empty-icon"><?= icon('sparkle', 32, 'var(--pub-muted)') ?></div>
        <p style="margin:0;font-size:.88rem;"><?= e(t('entity.no_attributes', 'No information available')) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>