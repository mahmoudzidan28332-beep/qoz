<?php
/**
 * frontend/partials/store_sections/reviews.php — QOOQZ Public Store Sections
 * Rating summary, review cards, submit form
 */

require_once __DIR__ . '/icons.php';

$showForm = ($sectionSettings['show_form'] ?? true);
$limit    = (int)($sectionSettings['limit'] ?? 5);

if (!$entityShowReviews) return;

?>

<style>
/* ── Reviews Section ───────────────────────────────── */
.rv-wrap { padding: 4px 0; }
.rv-summary {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    padding: 20px 22px;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
}
.rv-big-score {
    font-size: 3rem;
    font-weight: 900;
    line-height: 1;
    color: var(--pub-text);
    letter-spacing: -0.03em;
}
.rv-stars-row { display: flex; gap: 3px; align-items: center; margin-bottom: 4px; }
.rv-count { font-size: 0.78rem; color: var(--pub-muted); margin-top: 2px; }

/* ── Review card ───────────────────────────────────── */
.rv-card {
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    padding: 16px 18px;
    margin-bottom: 10px;
    transition: border-color .15s;
}
.rv-card:last-child { margin-bottom: 0; }
.rv-card:hover { border-color: var(--pub-primary); }
.rv-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.rv-author-row { display: flex; align-items: center; gap: 8px; }
.rv-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: rgba(3,135,78,.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: var(--pub-primary);
}
.rv-author-name { font-weight: 700; font-size: 0.88rem; color: var(--pub-text); }
.rv-meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rv-stars-sm { display: flex; gap: 2px; align-items: center; }
.rv-date {
    display: flex; align-items: center; gap: 4px;
    font-size: 0.74rem; color: var(--pub-muted);
}
.rv-text { font-size: 0.87rem; color: var(--pub-text); line-height: 1.6; margin: 0; }

/* ── Empty state ───────────────────────────────────── */
.rv-empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--pub-muted);
}
.rv-empty-icon {
    width: 52px; height: 52px;
    margin: 0 auto 12px;
    opacity: .3;
}
.rv-empty-msg { font-size: 0.9rem; margin: 0; }

/* ── Rate form ─────────────────────────────────────── */
.rv-form-card {
    margin-top: 24px;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    padding: 20px 22px;
}
.rv-form-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.96rem; font-weight: 700; color: var(--pub-text);
    margin: 0 0 16px;
}
.rv-star-label { font-size: 0.78rem; font-weight: 600; color: var(--pub-muted); text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 8px; }
.rv-star-picker { display: flex; gap: 5px; cursor: pointer; margin-bottom: 14px; }
.rv-star-pick {
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px;
    transition: background .12s, transform .1s;
    color: var(--pub-border);
}
.rv-star-pick:hover,
.rv-star-pick.active { color: #F59E0B; transform: scale(1.12); }
.rv-textarea {
    width: 100%; box-sizing: border-box;
    padding: 10px 13px;
    border: 1.5px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    background: var(--pub-bg);
    color: var(--pub-text);
    font-size: 0.88rem;
    font-family: inherit;
    resize: vertical;
    transition: border-color .15s, box-shadow .15s;
    margin-bottom: 12px;
}
.rv-textarea:focus {
    outline: none;
    border-color: var(--pub-primary);
    box-shadow: 0 0 0 3px rgba(3,135,78,.1);
}
.rv-submit-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 20px;
    background: var(--pub-primary);
    color: #fff;
    border: none; border-radius: var(--pub-radius-sm);
    font-size: 0.88rem; font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: opacity .15s;
}
.rv-submit-btn:hover { opacity: .88; }
.rv-login-note {
    text-align: center; margin-top: 20px;
    font-size: 0.86rem; color: var(--pub-muted);
}
</style>

<div class="pub-entity-section-content rv-wrap" id="sectionReviews">

    <?php if ($entityRatingAvg !== null): ?>
    <div class="rv-summary">
        <div class="rv-big-score"><?= number_format((float)$entityRatingAvg, 1) ?></div>
        <div>
            <div class="rv-stars-row">
                <?php for ($si = 1; $si <= 5; $si++):
                    $full = $si <= $entityRatingAvg;
                    $half = !$full && ($si - 0.5 <= $entityRatingAvg);
                    $starColor = ($full || $half) ? '#F59E0B' : 'var(--pub-border)';
                ?>
                    <?php if ($full): ?>
                        <?= icon('star', 18, '#F59E0B') ?>
                    <?php elseif ($half): ?>
                        <span style="opacity:.55"><?= icon('star', 18, '#F59E0B') ?></span>
                    <?php else: ?>
                        <?= icon('star-outline', 18, 'var(--pub-border)') ?>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <div class="rv-count"><?= (int)$entityRatingTotal ?> <?= e(t('entity.ratings_count')) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($entityRatings)): ?>
    <div>
        <?php foreach ($entityRatings as $r): ?>
        <div class="rv-card">
            <div class="rv-card-head">
                <div class="rv-author-row">
                    <div class="rv-avatar">
                        <?= icon('user', 16, 'var(--pub-primary)') ?>
                    </div>
                    <span class="rv-author-name"><?= e($r['reviewer_name']) ?></span>
                </div>
                <div class="rv-meta-row">
                    <div class="rv-stars-sm">
                        <?php for ($si = 1; $si <= 5; $si++): ?>
                            <?= icon($si <= (float)$r['rating'] ? 'star' : 'star-outline', 13, '#F59E0B') ?>
                        <?php endfor; ?>
                    </div>
                    <div class="rv-date">
                        <?= icon('calendar', 12, 'var(--pub-muted)') ?>
                        <?= e(substr($r['created_at'] ?? '', 0, 10)) ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($r['review'])): ?>
                <p class="rv-text"><?= e($r['review']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="rv-empty">
        <div class="rv-empty-icon">
            <?= icon('star-outline', 52, 'var(--pub-muted)') ?>
        </div>
        <p class="rv-empty-msg"><?= e(t('entity.no_ratings')) ?></p>
    </div>
    <?php endif; ?>


    <?php if ($showForm && $_isLoggedIn): ?>
    <div class="rv-form-card">
        <h3 class="rv-form-title">
            <?= icon('edit', 16, 'var(--pub-primary)') ?>
            <?= e(t('entity.rate_title')) ?>
        </h3>
        <form id="pubEntityRateForm" onsubmit="pubSubmitEntityRating(event)">
            <span class="rv-star-label"><?= e(t('entity.your_rating')) ?></span>
            <div id="pubEntityStarPicker" class="rv-star-picker" role="group" aria-label="<?= e(t('entity.your_rating')) ?>">
                <?php for ($si = 1; $si <= 5; $si++): ?>
                    <div class="rv-star-pick" data-val="<?= $si ?>" onclick="pubPickEntityStar(<?= $si ?>)"
                         role="radio" aria-label="<?= $si ?> stars" tabindex="0">
                        <?= icon('star', 20, 'currentColor') ?>
                    </div>
                <?php endfor; ?>
            </div>
            <input type="hidden" id="pubEntityRating" name="rating" value="0">

            <textarea id="pubEntityReview" name="review" rows="3"
                      placeholder="<?= e(t('entity.write_review')) ?>"
                      class="rv-textarea"></textarea>

            <button type="submit" class="rv-submit-btn">
                <?= icon('send', 14, '#fff') ?>
                <?= e(t('entity.submit_rating')) ?>
            </button>
            <p id="pubEntityRateMsg" style="margin:10px 0 0;font-size:0.84rem;display:none;"></p>
        </form>
    </div>

    <?php elseif ($showForm): ?>
    <p class="rv-login-note">
        <?= icon('lock', 14, 'var(--pub-muted)') ?>
        <a href="/frontend/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="pub-link" style="margin-left:4px;"><?= e(t('common.login')) ?></a>
        <?= e(t('entity.login_to_rate')) ?>
    </p>
    <?php endif; ?>

</div>

<script>
(function(){
    window.pubPickEntityStar = function(val){
        var picks = document.querySelectorAll('.rv-star-pick');
        picks.forEach(function(p){
            p.classList.toggle('active', parseInt(p.dataset.val) <= val);
        });
        var inp = document.getElementById('pubEntityRating');
        if (inp) inp.value = val;
    };
})();
</script>