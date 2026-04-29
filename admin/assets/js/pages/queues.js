/**
 * /admin/assets/js/pages/queues.js
 * Queue Management – guide-compliant v4.0
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    var CFG, CSRF, CAN_DELETE;
    var PER_PAGE        = 25;
    var currentPage     = 1;
    var currentFilters  = {};
    var LOOKUP_CACHE    = {};

    var STATUS_MAP = { 0: 'pending', 1: 'working', 2: 'done', 3: 'failed' };

    function reloadConfig() {
        CFG        = window.QUEUES_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        CAN_DELETE = !!CFG.canDelete;
    }

    // ════════════════════════════════════════════════════════════
    // 2. i18n — reads live from QUEUES_CONFIG.strings (flat)
    // ════════════════════════════════════════════════════════════
    function t(key, fallback) {
        var strings = (window.QUEUES_CONFIG && window.QUEUES_CONFIG.strings) || {};
        if (strings[key] !== undefined && strings[key] !== '') return String(strings[key]);
        return fallback !== undefined ? fallback : key.split('.').pop().replace(/_/g, ' ');
    }

    function applyI18n() {
        var container = document.getElementById('queuesPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var val = t(key, '');
            if (!val) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = val;
            } else if (el.tagName === 'OPTION') {
                el.textContent = val;
            } else {
                el.textContent = val;
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            var val = t(el.getAttribute('data-i18n-placeholder'), '');
            if (val) el.placeholder = val;
        });
    }

    // ════════════════════════════════════════════════════════════
    // 3. HELPERS
    // ════════════════════════════════════════════════════════════
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function authHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF };
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val != null ? val : 0;
    }

    // ════════════════════════════════════════════════════════════
    // 4. SHOW STATE
    // ════════════════════════════════════════════════════════════
    function showState(state, errorMsg) {
        var loading    = document.getElementById('qmLoading');
        var empty      = document.getElementById('qmEmpty');
        var error      = document.getElementById('qmError');
        var container  = document.getElementById('qmTableContainer');

        [loading, empty, error, container].forEach(function (el) {
            if (el) el.style.display = 'none';
        });

        switch (state) {
            case 'loading':
                if (loading)   loading.style.display   = 'flex'; break;
            case 'empty':
                if (empty)     empty.style.display     = 'flex'; break;
            case 'error':
                if (error)     error.style.display     = 'flex';
                if (errorMsg) {
                    var p = document.getElementById('qmErrorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default:
                if (container) container.style.display = 'block';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 5. NOTIFICATIONS (qm-toast-*)
    // ════════════════════════════════════════════════════════════
    function showNotification(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type || 'info');
            return;
        }
        var container = document.querySelector('.qm-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'qm-notifications';
            document.body.appendChild(container);
        }
        var iconMap = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
        var el = document.createElement('div');
        el.className = 'qm-toast qm-toast--' + (type || 'info');
        el.innerHTML = '<i class="fas ' + (iconMap[type] || 'fa-info-circle') + ' qm-toast-icon" aria-hidden="true"></i>' +
                       '<span class="qm-toast-body">' + esc(msg) + '</span>';
        container.appendChild(el);
        setTimeout(function () {
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 3500);
    }

    // ════════════════════════════════════════════════════════════
    // 6. MODAL — open with focus, close
    // ════════════════════════════════════════════════════════════
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'flex';
        var first = el.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (first) setTimeout(function () { first.focus(); }, 50);
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // ════════════════════════════════════════════════════════════
    // 7. STATS
    // ════════════════════════════════════════════════════════════
    function loadStats() {
        fetch('/api/queues/stats', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    var s = d.data;
                    setText('statTotal',   s.total);
                    setText('statPending', s.pending);
                    setText('statWorking', s.working);
                    setText('statDone',    s.done);
                    setText('statFailed',  s.failed);
                }
            })
            .catch(function (err) { console.warn('[Queues] stats failed:', err.message); });
    }

    // ════════════════════════════════════════════════════════════
    // 8. QUEUE NAMES
    // ════════════════════════════════════════════════════════════
    function loadQueueNames() {
        fetch('/api/queues/names', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var sel   = document.getElementById('filterQueue');
                if (!sel) return;
                var names = [];
                if (d.success && Array.isArray(d.data))              names = d.data;
                else if (d.success && Array.isArray(d.data && d.data.items)) names = d.data.items;
                names.forEach(function (n) {
                    var opt = document.createElement('option');
                    opt.value = n; opt.textContent = n;
                    sel.appendChild(opt);
                });
            })
            .catch(function (err) { console.warn('[Queues] queue names failed:', err.message); });
    }

    // ════════════════════════════════════════════════════════════
    // 9. LOAD JOBS
    // ════════════════════════════════════════════════════════════
    function loadJobs(page) {
        page        = page || 1;
        currentPage = page;
        var offset  = (page - 1) * PER_PAGE;
        var params  = 'limit=' + PER_PAGE + '&offset=' + offset;
        if (currentFilters.queue)    params += '&queue='    + encodeURIComponent(currentFilters.queue);
        if (currentFilters.status !== undefined && currentFilters.status !== '')
                                     params += '&status='   + encodeURIComponent(currentFilters.status);
        if (currentFilters.priority) params += '&priority=' + encodeURIComponent(currentFilters.priority);
        if (currentFilters.search)   params += '&search='   + encodeURIComponent(currentFilters.search);

        showState('loading');

        fetch('/api/queues?' + params, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('queuesBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                var items = [];
                var total = 0;
                if (d.success && d.data) {
                    items = Array.isArray(d.data.items) ? d.data.items : (Array.isArray(d.data) ? d.data : []);
                    total = (d.data.meta && d.data.meta.total) || items.length;
                }

                if (!items.length) {
                    showState('empty');
                    setText('paginationInfo', '');
                    document.getElementById('pagination').innerHTML = '';
                    return;
                }

                items.forEach(function (job) {
                    var statusKey   = STATUS_MAP[job.status] || 'unknown';
                    var priorityKey = job.priority || 'normal';
                    var errText     = job.error ? (job.error.length > 60 ? job.error.substring(0, 60) + '…' : job.error) : '';
                    var entityDisp  = job.entity_id
                        ? '<span class="entity-lookup" data-type="' + esc(job.entity_type) + '" data-id="' + esc(job.entity_id) + '">' + esc(job.entity_id) + '</span>'
                        : '—';

                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(job.id) + '</td>' +
                        '<td>' + esc(job.queue) + '</td>' +
                        '<td>' + esc(job.job_type || '—') + '</td>' +
                        '<td>' + entityDisp + '</td>' +
                        '<td><span class="priority-badge priority-' + esc(priorityKey) + '">' + esc(t('status.priority.' + priorityKey, priorityKey)) + '</span></td>' +
                        '<td><span class="badge badge-' + esc(statusKey) + '">' + esc(t('status.' + statusKey, statusKey)) + '</span></td>' +
                        '<td>' + esc(job.attempts) + '</td>' +
                        '<td class="error-cell" title="' + esc(job.error) + '">' + esc(errText) + '</td>' +
                        '<td>' + esc(job.created_at) + '</td>' +
                        '<td class="actions-cell"></td>';

                    var cell = tr.querySelector('.actions-cell');

                    // View
                    var bView = document.createElement('button');
                    bView.className = 'btn btn-sm btn-primary';
                    bView.setAttribute('aria-label', t('actions.view', 'View'));
                    bView.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
                    bView.addEventListener('click', (function (id) { return function () { viewJob(id); }; })(job.id));
                    cell.appendChild(bView);

                    // Retry (failed only)
                    if (parseInt(job.status) === 3) {
                        var bRetry = document.createElement('button');
                        bRetry.className = 'btn btn-sm btn-secondary';
                        bRetry.setAttribute('aria-label', t('actions.retry', 'Retry'));
                        bRetry.innerHTML = '<i class="fas fa-redo" aria-hidden="true"></i>';
                        bRetry.addEventListener('click', (function (id) { return function () { retryJob(id); }; })(job.id));
                        cell.appendChild(bRetry);
                    }

                    // Delete
                    if (CAN_DELETE) {
                        var bDel = document.createElement('button');
                        bDel.className = 'btn btn-sm btn-danger';
                        bDel.setAttribute('aria-label', t('actions.delete', 'Delete'));
                        bDel.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
                        bDel.addEventListener('click', (function (id) { return function () { deleteJob(id); }; })(job.id));
                        cell.appendChild(bDel);
                    }

                    tbody.appendChild(tr);
                });

                renderPagination(total, page);
                showState('table');
                triggerLookups();

                if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
                    Admin.buttons.applyHoverEffects(tbody);
                }
            })
            .catch(function (err) {
                showState('error', err.message || t('messages.load_failed', 'Error loading jobs'));
            });
    }

    // ════════════════════════════════════════════════════════════
    // 10. PAGINATION
    // ════════════════════════════════════════════════════════════
    function renderPagination(total, page) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var info = document.getElementById('paginationInfo');
        var pag  = document.getElementById('pagination');
        if (!pag) return;

        var start = total > 0 ? ((page - 1) * PER_PAGE + 1) : 0;
        var end   = Math.min(page * PER_PAGE, total);
        if (info) info.textContent = start + '–' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

        pag.innerHTML = '';
        if (totalPages <= 1) return;

        function mkBtn(label, pageNum, disabled, active) {
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (active ? ' btn-primary active' : '') + (disabled ? ' disabled' : '');
            btn.innerHTML = label;
            btn.disabled  = !!disabled;
            if (!disabled) btn.addEventListener('click', function () { loadJobs(pageNum); });
            return btn;
        }

        pag.appendChild(mkBtn('‹', page - 1, page <= 1, false));

        for (var i = 1; i <= totalPages; i++) {
            if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
                if (i === 3 || i === totalPages - 2) {
                    var sp = document.createElement('span');
                    sp.className = 'pagination-ellipsis';
                    sp.textContent = '…';
                    pag.appendChild(sp);
                }
                continue;
            }
            pag.appendChild(mkBtn(String(i), i, false, i === page));
        }

        pag.appendChild(mkBtn('›', page + 1, page >= totalPages, false));
    }

    // ════════════════════════════════════════════════════════════
    // 11. VIEW JOB
    // ════════════════════════════════════════════════════════════
    function viewJob(id) {
        fetch('/api/queues?id=' + id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var container = document.getElementById('viewJobContent');
                if (!container) return;
                if (!d.success || !d.data) {
                    container.innerHTML = '<p>' + t('messages.not_found', 'Not found') + '</p>';
                    openModal('viewJobModal');
                    return;
                }
                var job       = d.data;
                var statusKey = STATUS_MAP[job.status] || 'unknown';
                var payload   = '';
                try { payload = JSON.stringify(JSON.parse(job.payload), null, 2); }
                catch (e) { payload = job.payload || ''; }

                container.innerHTML =
                    '<div class="detail-row"><strong>' + t('table.id',           'ID')        + ':</strong> ' + esc(job.id) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.queue',        'Queue')     + ':</strong> ' + esc(job.queue) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.job_type',     'Job Type')  + ':</strong> ' + esc(job.job_type || '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.entity',       'Entity')    + ':</strong> ' + (job.entity_id ? esc(job.entity_type) + ' (' + esc(job.entity_id) + ')' : '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.priority',     'Priority')  + ':</strong> <span class="priority-badge priority-' + esc(job.priority || 'normal') + '">' + esc(t('status.priority.' + (job.priority || 'normal'), job.priority || 'normal')) + '</span></div>' +
                    '<div class="detail-row"><strong>' + t('table.status',       'Status')    + ':</strong> <span class="badge badge-' + esc(statusKey) + '">' + esc(t('status.' + statusKey, statusKey)) + '</span></div>' +
                    '<div class="detail-row"><strong>' + t('table.attempts',     'Attempts')  + ':</strong> ' + esc(job.attempts) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.created_at',   'Created')   + ':</strong> ' + esc(job.created_at) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.processed_at', 'Processed') + ':</strong> ' + esc(job.processed_at || '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.updated_at',   'Updated')   + ':</strong> ' + esc(job.updated_at) + '</div>' +
                    (job.error ? '<div class="detail-row"><strong>' + t('table.error', 'Error') + ':</strong><pre class="error-pre">' + esc(job.error) + '</pre></div>' : '') +
                    '<div class="detail-row"><strong>' + t('table.payload', 'Payload') + ':</strong><pre class="payload-pre">' + esc(payload) + '</pre></div>';

                openModal('viewJobModal');
            })
            .catch(function (err) { showNotification(err.message, 'error'); });
    }

    // ════════════════════════════════════════════════════════════
    // 12. RETRY / DELETE / ARCHIVE / PURGE
    // ════════════════════════════════════════════════════════════
    function retryJob(id) {
        fetch('/api/queues/retry', {
            method: 'POST',
            headers: authHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.retry_success', 'Job queued for retry'), 'success');
                    loadJobs(currentPage); loadStats();
                } else {
                    showNotification(d.message || t('messages.retry_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.retry_failed', 'Failed'), 'error'); });
    }

    function deleteJob(id) {
        if (!confirm(t('messages.confirm_delete', 'Delete this job?'))) return;
        fetch('/api/queues?id=' + id, {
            method: 'DELETE',
            headers: authHeaders(),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.delete_success', 'Job deleted'), 'success');
                    loadJobs(currentPage); loadStats();
                } else {
                    showNotification(d.message || t('messages.delete_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.delete_failed', 'Failed'), 'error'); });
    }

    function archiveDone() {
        if (!confirm(t('messages.confirm_archive', 'Archive all completed jobs?'))) return;
        fetch('/api/queues/archive', {
            method: 'POST',
            headers: authHeaders(),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.archive_success', 'Archived') + ' (' + ((d.data && d.data.archived) || 0) + ')', 'success');
                    loadJobs(1); loadStats();
                } else {
                    showNotification(d.message || t('messages.archive_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.archive_failed', 'Failed'), 'error'); });
    }

    function confirmPurge() {
        var status = document.getElementById('purgeStatus').value;
        var days   = parseInt(document.getElementById('purgeDays').value, 10) || 30;
        fetch('/api/queues/purge', {
            method: 'POST',
            headers: authHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ status: status, days: days })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                closeModal('purgeModal');
                if (d.success) {
                    showNotification(t('messages.purge_success', 'Purged') + ' (' + ((d.data && d.data.purged) || 0) + ')', 'success');
                    loadJobs(1); loadStats();
                } else {
                    showNotification(d.message || t('messages.purge_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.purge_failed', 'Failed'), 'error'); });
    }

    // ════════════════════════════════════════════════════════════
    // 13. FILTERS
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        currentFilters = {
            search:   (document.getElementById('filterSearch')   || {}).value || '',
            queue:    (document.getElementById('filterQueue')    || {}).value || '',
            priority: (document.getElementById('filterPriority') || {}).value || '',
            status:   (document.getElementById('filterStatus')   || {}).value
        };
        loadJobs(1);
    }

    function clearFilters() {
        ['filterSearch','filterQueue','filterPriority','filterStatus'].forEach(function (id) {
            var el = document.getElementById(id); if (el) el.value = '';
        });
        currentFilters = {};
        loadJobs(1);
    }

    // ════════════════════════════════════════════════════════════
    // 14. ENTITY LOOKUPS
    // ════════════════════════════════════════════════════════════
    function triggerLookups() {
        document.querySelectorAll('.entity-lookup').forEach(function (el) {
            var type = el.getAttribute('data-type');
            var id   = el.getAttribute('data-id');
            if (!type || !id) return;

            var cacheKey = type + ':' + id;
            if (LOOKUP_CACHE[cacheKey]) {
                el.innerHTML = esc(LOOKUP_CACHE[cacheKey]) + ' <small>(' + esc(id) + ')</small>';
                return;
            }

            var url = type === 'tenant' ? '/api/tenants/' + id
                    : type === 'entity' ? '/api/entities?id=' + id
                    : type === 'user'   ? '/api/user?id=' + id
                    : '';
            if (!url) return;

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var name = '';
                    if (d.success && d.data) {
                        name = type === 'tenant' ? d.data.name
                             : type === 'entity' ? (d.data.store_name || d.data.name)
                             : type === 'user'   ? (d.data.username || d.data.name)
                             : '';
                    }
                    if (name) {
                        LOOKUP_CACHE[cacheKey] = name;
                        el.innerHTML = esc(name) + ' <small>(' + esc(id) + ')</small>';
                    }
                })
                .catch(function () {});
        });
    }

    // ════════════════════════════════════════════════════════════
    // 15. INIT
    // ════════════════════════════════════════════════════════════
    function init() {
        reloadConfig();
        applyI18n();

        loadStats();
        loadQueueNames();
        loadJobs(1);

        if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
            Admin.buttons.applyHoverEffects(document.getElementById('queuesPageContainer'));
        }

        // Header actions
        document.getElementById('btnRefresh')?.addEventListener('click', function () { loadStats(); loadJobs(currentPage); });
        document.getElementById('btnArchiveDone')?.addEventListener('click', archiveDone);
        document.getElementById('btnOpenPurge')?.addEventListener('click', function () { openModal('purgeModal'); });

        // Filters
        document.getElementById('btnFilter')?.addEventListener('click', applyFilters);
        document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);
        document.getElementById('filterSearch')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        // Purge
        document.getElementById('btnConfirmPurge')?.addEventListener('click', confirmPurge);

        // Retry button (in table)
        document.getElementById('btnRetry')?.addEventListener('click', function () { loadJobs(currentPage); });

        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-modal');
                if (modalId) closeModal(modalId);
            });
        });

        // ESC closes any open modal
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ['viewJobModal', 'purgeModal'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el && el.style.display !== 'none') closeModal(id);
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // 16. REGISTER
    // ════════════════════════════════════════════════════════════
    window.Queues = { init: init };
    window.page   = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('queues', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());