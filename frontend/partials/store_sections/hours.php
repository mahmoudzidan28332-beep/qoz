<?php
/**
 * frontend/partials/store_sections/hours.php
 * Store Working Hours Section
 *
 * Expected variables: $entity, $workingHoursArr, $dayNames, $entityIsOpen, $sectionSettings
 */

require_once __DIR__ . '/icons.php';

$dayNames = $dayNames ?? [
    0 => t('entity.day_sunday'),    1 => t('entity.day_monday'),
    2 => t('entity.day_tuesday'),   3 => t('entity.day_wednesday'),
    4 => t('entity.day_thursday'),  5 => t('entity.day_friday'),
    6 => t('entity.day_saturday'),
];
?>

<style>
/* ── Hours section ─────────────────────────────────── */
.hrs-card {
    background: var(--pub-surface);
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius);
    overflow: hidden;
}

/* Status banner */
.hrs-status {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--pub-border);
    font-size: 0.85rem; font-weight: 700;
}
.hrs-status--open  { background: rgba(34,197,94,.08); color: #065f46; }
.hrs-status--closed{ background: rgba(239,68,68,.07); color: #991b1b; }

/* Day rows */
.hrs-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 16px;
    border-bottom: 1px solid rgba(0,0,0,.04);
    font-size: 0.86rem;
    transition: background .1s;
}
.hrs-row:last-child { border-bottom: none; }
.hrs-row:hover { background: rgba(0,0,0,.02); }
.hrs-row--today {
    background: rgba(3,135,78,.05);
    border-left: 3px solid var(--pub-primary);
}
.hrs-row--closed-day { opacity: .45; }

.hrs-day {
    display: flex; align-items: center; gap: 7px;
    font-weight: 600; color: var(--pub-text);
}
.hrs-day svg { opacity: .5; }
.hrs-today-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--pub-primary);
    flex-shrink: 0;
}
.hrs-time { color: var(--pub-muted); font-size: 0.82rem; }
.hrs-time--open { color: #059669; font-weight: 600; }
.hrs-closed-label { color: var(--pub-muted); font-size: 0.79rem; font-style: italic; }

/* Empty state */
.hrs-empty { text-align: center; padding: 40px 20px; color: var(--pub-muted); }
</style>

<div class="pub-entity-section-content" id="sectionHours">
    <?php if (!empty($workingHoursArr)): ?>
    <div class="hrs-card">

        <?php if ($entityIsOpen !== null): ?>
        <div class="hrs-status <?= $entityIsOpen ? 'hrs-status--open' : 'hrs-status--closed' ?>">
            <?= $entityIsOpen ? icon('dot-pulse', 16) : icon('dot-closed', 16) ?>
            <?= e($entityIsOpen ? t('entity.open_now') : t('entity.closed')) ?>
        </div>
        <?php endif; ?>

        <?php
        /* Index hours by day */
        $hoursByDay = [];
        foreach ($workingHoursArr as $h) {
            $hoursByDay[(int)$h['day_of_week']] = $h;
        }
        $todayDow = (int)date('w');
        ?>

        <?php for ($dow = 0; $dow <= 6; $dow++):
            $h       = $hoursByDay[$dow] ?? null;
            $isToday = ($dow === $todayDow);
            $isOpen  = !empty($h) && !empty($h['is_open']);
            $rowCls  = $isToday ? 'hrs-row--today' : (!$isOpen ? 'hrs-row--closed-day' : '');
        ?>
        <div class="hrs-row <?= $rowCls ?>">
            <span class="hrs-day">
                <?php if ($isToday): ?>
                    <span class="hrs-today-dot"></span>
                <?php else: ?>
                    <?= icon('clock', 13, 'var(--pub-muted)') ?>
                <?php endif; ?>
                <?= e($dayNames[$dow] ?? $dow) ?>
            </span>
            <?php if ($h && $isOpen): ?>
                <span class="hrs-time <?= $isToday ? 'hrs-time--open' : '' ?>">
                    <?= e(substr($h['open_time'] ?? '', 0, 5)) ?> – <?= e(substr($h['close_time'] ?? '', 0, 5)) ?>
                </span>
            <?php else: ?>
                <span class="hrs-closed-label"><?= e(t('entity.day_closed', 'Closed')) ?></span>
            <?php endif; ?>
        </div>
        <?php endfor; ?>

    </div>

    <?php else: ?>
    <div class="hrs-empty">
        <?= icon('clock', 40, 'var(--pub-muted)') ?>
        <p style="margin:12px 0 0;font-size:.88rem;opacity:.5;"><?= e(t('entity.no_hours', 'No working hours available')) ?></p>
    </div>
    <?php endif; ?>
</div>