<?php
/**
 * frontend/partials/store_sections/jobs.php
 * Entity Jobs Section — Lists open job postings for the current entity
 *
 * Expected variables: $entity, $entityId, $pdo, $lang, $sectionSettings
 */

require_once __DIR__ . '/icons.php';

$jobLimit = isset($sectionSettings['limit']) ? (int)$sectionSettings['limit'] : 6;

// Fetch jobs for this entity via PDO
$entityJobs = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $jStmt = $pdo->prepare(
            "SELECT j.id, j.title, j.slug, j.employment_type, j.experience_level,
                    j.salary_min, j.salary_max, j.salary_currency,
                    j.is_remote, j.is_featured, j.is_urgent,
                    j.application_deadline, j.positions_available,
                    j.city_name, j.status, j.created_at,
                    COALESCE(jt.title, j.title) AS display_title,
                    jt.description AS translated_desc
               FROM jobs j
          LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
              WHERE j.entity_id = ?
                AND j.status = 'active'
              ORDER BY j.is_featured DESC, j.is_urgent DESC, j.created_at DESC
              LIMIT ?"
        );
        $jStmt->execute([$lang, $entityId, $jobLimit]);
        $entityJobs = $jStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\RuntimeException $_) {
        // jobs table may not exist — silently skip
        $entityJobs = [];
    }
}

// Fallback: try API
if (empty($entityJobs)) {
    $jResp = pub_fetch(pub_api_url('public/jobs') . '?entity_id=' . $entityId . '&lang=' . urlencode($lang) . '&limit=' . $jobLimit);
    $entityJobs = isset($jResp['data']['data']) ? $jResp['data']['data'] : (isset($jResp['data']['items']) ? $jResp['data']['items'] : []);
}

if (empty($entityJobs)) return;

$empLabels = [
    'full_time'  => t('jobs.type_full_time', 'Full Time'),
    'part_time'  => t('jobs.type_part_time', 'Part Time'),
    'contract'   => t('jobs.type_contract', 'Contract'),
    'freelance'  => t('jobs.type_freelance', 'Freelance'),
    'internship' => t('jobs.type_internship', 'Internship'),
];
?>

<style>
/* -- Entity Jobs Section -- */
.ej-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
}
.ej-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px 20px;
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius, 12px);
    text-decoration: none;
    color: var(--pub-text);
    transition: border-color .2s, box-shadow .2s;
}
.ej-card:hover {
    border-color: var(--pub-primary);
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}
.ej-title {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--pub-text);
    line-height: 1.3;
}
.ej-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.82rem;
    color: var(--pub-muted);
}
.ej-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.ej-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.ej-tag {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .02em;
}
.ej-tag--featured { background: rgba(245,158,11,.12); color: #92400e; }
.ej-tag--urgent   { background: rgba(239,68,68,.1);   color: #991b1b; }
.ej-tag--remote   { background: rgba(59,130,246,.1);  color: #1e40af; }
.ej-more {
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
.ej-more:hover { text-decoration: underline; }
@media(max-width: 600px) {
    .ej-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pub-entity-section-content" id="sectionJobs">
    <div class="ej-grid">
        <?php foreach ($entityJobs as $j):
            $jTitle = isset($j['display_title']) ? $j['display_title'] : (isset($j['title']) ? $j['title'] : '');
            $jEmpTypeArr = isset($j['employment_type']) ? explode(',', $j['employment_type']) : [];
            $jCity = isset($j['city_name']) ? $j['city_name'] : '';
            $jDeadline = isset($j['application_deadline']) ? $j['application_deadline'] : '';
        ?>
        <a href="/frontend/public/job.php?id=<?= (int)($j['id']) ?>" class="ej-card">
            <h3 class="ej-title"><?= e($jTitle) ?></h3>
            <div class="ej-meta">
                <?php if (!empty($jEmpTypeArr[0]) && isset($empLabels[$jEmpTypeArr[0]])): ?>
                    <span class="ej-meta-item"><?= icon('clock', 14, 'var(--pub-muted)') ?> <?= e($empLabels[$jEmpTypeArr[0]]) ?></span>
                <?php endif; ?>
                <?php if ($jCity): ?>
                    <span class="ej-meta-item"><?= icon('pin', 14, 'var(--pub-muted)') ?> <?= e($jCity) ?></span>
                <?php endif; ?>
                <?php if ($jDeadline): ?>
                    <span class="ej-meta-item"><?= icon('calendar', 14, 'var(--pub-muted)') ?> <?= e(substr((string)$jDeadline, 0, 10)) ?></span>
                <?php endif; ?>
            </div>
            <div class="ej-tags">
                <?php if (!empty($j['is_featured'])): ?>
                    <span class="ej-tag ej-tag--featured">
                        <?= icon('star', 11, '#f59e0b') ?>
                        <?= e($sectionContentJson['featured'] ?? t('jobs.featured', 'Featured')) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($j['is_urgent'])): ?>
                    <span class="ej-tag ej-tag--urgent">
                        <?= icon('alert', 11, '#ef4444') ?>
                        <?= e($sectionContentJson['urgent'] ?? t('jobs.urgent', 'Urgent')) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($j['is_remote'])): ?>
                    <span class="ej-tag ej-tag--remote">
                        <?= icon('globe', 11, '#3b82f6') ?>
                        <?= e($sectionContentJson['remote'] ?? t('jobs.remote', 'Remote')) ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <a href="/frontend/public/jobs.php?entity_id=<?= $entityId ?>" class="ej-more">
        <?= e($sectionContentJson['view_all'] ?? t('entity.view_all_jobs', 'View all jobs')) ?>
        <?= icon('chevron-right', 16, 'var(--pub-primary)') ?>
    </a>
</div>
