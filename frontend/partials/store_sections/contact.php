<?php
/**
 * frontend/partials/store_sections/contact.php
 * Store Contact Section — Phone, email, website, social, share button
 *
 * Expected variables: $entity, $entityShowContactInfo, $sectionSettings
 */

require_once __DIR__ . '/icons.php';

if (!$entityShowContactInfo) return;
?>

<style>
/* ── Contact section ───────────────────────────────── */
.ct-bar {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 14px 0 2px;
}
.ct-bar::-webkit-scrollbar { display: none; }

.ct-item {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px;
    border: 1px solid var(--pub-border);
    border-radius: 999px;
    background: var(--pub-surface);
    font-size: 0.82rem; font-weight: 600;
    color: var(--pub-primary);
    white-space: nowrap;
    flex-shrink: 0;
    text-decoration: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.ct-item:hover {
    border-color: var(--pub-primary);
    background: rgba(3,135,78,.05);
    box-shadow: 0 2px 8px rgba(3,135,78,.1);
}
.ct-item svg { flex-shrink: 0; }

/* Share button */
.ct-share {
    color: var(--pub-text);
    border-style: dashed;
}
.ct-share:hover { color: var(--pub-primary); }

/* Social row */
.ct-social {
    display: flex; gap: 6px;
    flex-wrap: nowrap;
    overflow-x: auto;
    scrollbar-width: none;
    padding: 6px 0 14px;
}
.ct-social::-webkit-scrollbar { display: none; }

.ct-social-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 13px;
    border: 1px solid var(--pub-border);
    border-radius: 999px;
    background: var(--pub-surface);
    font-size: 0.8rem; font-weight: 600;
    color: var(--pub-text);
    white-space: nowrap;
    flex-shrink: 0;
    text-decoration: none;
    transition: border-color .15s, background .15s;
}
.ct-social-btn:hover { border-color: var(--pub-primary); color: var(--pub-primary); }

/* Share dropdown panel */
.ct-share-panel {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    z-index: 50;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    padding: 10px;
    min-width: 180px;
    box-shadow: 0 8px 24px rgba(0,0,0,.1);
}
.ct-share-wrap { position: relative; display: inline-flex; }
.ct-share-link {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px;
    border-radius: var(--pub-radius-sm);
    font-size: 0.83rem; font-weight: 600;
    color: var(--pub-text);
    text-decoration: none;
    transition: background .12s;
}
.ct-share-link:hover { background: rgba(0,0,0,.04); }
</style>

<div class="pub-container">

    <!-- Contact items -->
    <div class="ct-bar">
        <?php if (!empty($entity['phone'])): ?>
        <a href="tel:<?= e($entity['phone']) ?>" class="ct-item">
            <?= icon('phone', 14, 'var(--pub-primary)') ?>
            <?= e($entity['phone']) ?>
        </a>
        <?php endif; ?>

        <?php if (!empty($entity['mobile']) && $entity['mobile'] !== $entity['phone']): ?>
        <a href="tel:<?= e($entity['mobile']) ?>" class="ct-item">
            <?= icon('phone', 14, 'var(--pub-primary)') ?>
            <?= e($entity['mobile']) ?>
        </a>
        <?php endif; ?>

        <?php if (!empty($entity['email'])): ?>
        <a href="mailto:<?= e($entity['email']) ?>" class="ct-item">
            <?= icon('mail', 14, 'var(--pub-primary)') ?>
            <?= e($entity['email']) ?>
        </a>
        <?php endif; ?>

        <?php if (!empty($entity['website'])): ?>
        <a href="<?= e($entity['website']) ?>" target="_blank" rel="noopener" class="ct-item">
            <?= icon('globe', 14, 'var(--pub-primary)') ?>
            <?= e(t('entity.website', 'Website')) ?>
        </a>
        <?php endif; ?>

        <!-- Share -->
        <div class="ct-share-wrap">
            <button type="button" class="ct-item ct-share" onclick="pubShareEntity()">
                <?= icon('share', 14, 'currentColor') ?>
                <?= e(t('entity.share', 'Share')) ?>
            </button>
            <div class="ct-share-panel" id="pubSharePanel">
                <a href="#" id="pubShareWA" target="_blank" rel="noopener" class="ct-share-link">
                    <?= icon('message', 14, '#25d366') ?>
                    WhatsApp
                </a>
                <a href="#" id="pubShareTW" target="_blank" rel="noopener" class="ct-share-link">
                    <?= icon('globe', 14, 'var(--pub-muted)') ?>
                    Twitter / X
                </a>
                <a href="#" id="pubShareFB" target="_blank" rel="noopener" class="ct-share-link">
                    <?= icon('globe', 14, '#1877f2') ?>
                    Facebook
                </a>
                <a href="#" id="pubShareTG" target="_blank" rel="noopener" class="ct-share-link">
                    <?= icon('send', 14, '#2aabee') ?>
                    Telegram
                </a>
                <button type="button" onclick="pubCopyLink()" class="ct-share-link" style="width:100%;border:none;background:none;cursor:pointer;text-align:start;font-family:inherit;">
                    <?= icon('link', 14, 'var(--pub-muted)') ?>
                    <?= e(t('entity.copy_link', 'Copy link')) ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Social links -->
    <?php
    $socials = [
        'instagram_url' => ['Instagram', 'globe'],
        'twitter_url'   => ['Twitter',   'globe'],
        'facebook_url'  => ['Facebook',  'globe'],
        'tiktok_url'    => ['TikTok',    'globe'],
        'snapchat_url'  => ['Snapchat',  'globe'],
        'youtube_url'   => ['YouTube',   'globe'],
        'linkedin_url'  => ['LinkedIn',  'globe'],
    ];
    $hasSocials = false;
    foreach ($socials as $k => [$label, $ico]) {
        if (!empty($entity[$k])) { $hasSocials = true; break; }
    }
    if ($hasSocials): ?>
    <div class="ct-social">
        <?php foreach ($socials as $k => [$label, $ico]): ?>
            <?php if (empty($entity[$k])) continue; ?>
            <a href="<?= e($entity[$k]) ?>" target="_blank" rel="noopener" class="ct-social-btn">
                <?= icon($ico, 13, 'currentColor') ?>
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>