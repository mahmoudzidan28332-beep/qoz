(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;

    function reloadConfig() {
        CFG        = window.RETURNS_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    const API = {
        get returns()  { return (window.RETURNS_CONFIG && window.RETURNS_CONFIG.apiUrl)     || '/api/returns'; },
        get items()    { return (window.RETURNS_CONFIG && window.RETURNS_CONFIG.itemsApi)   || '/api/return_items'; },
        get history()  { return (window.RETURNS_CONFIG && window.RETURNS_CONFIG.historyApi) || '/api/return_status_history'; },
        get orders()   { return (window.RETURNS_CONFIG && window.RETURNS_CONFIG.ordersApi)  || '/api/orders'; },
        get users()    { return (window.RETURNS_CONFIG && window.RETURNS_CONFIG.usersApi)   || '/api/users'; }
    };

    const state = {
        page: 1,
        perPage: 20,
        total: 0,
        returns: [],
        currentReturn: null,
        returnItems: [],
        returnHistory: [],
        filters: {}
    };

    let el = {};

    // ════════════════════════════════════════════════════════════
    // 2. HELPERS
    // ════════════════════════════════════════════════════════════

    /** Translation helper */
    function t(key, fallback) {
        // 1) inline strings from PAGE_CONFIG
        if (STRINGS && STRINGS[key]) return STRINGS[key];
        // 2) global admin i18n
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }
        return fallback !== undefined ? fallback : key;
    }

    /** XSS escape */
    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    /** Toast notification */
    function notify(msg, type) {
        // Prefer global admin notify
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type || 'info');
            return;
        }
        // Fallback: simple toast
        let container = document.querySelector('.ret-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ret-notifications';
            document.body.appendChild(container);
        }
        const iconMap = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = 'ret-toast ret-toast--' + (type || 'info');
        toast.innerHTML =
            '<i class="fas ' + (iconMap[type] || 'fa-info-circle') + ' ret-toast-icon" aria-hidden="true"></i>' +
            '<div class="ret-toast-body">' + esc(msg) + '</div>' +
            '<button class="ret-toast-close" aria-label="Close"><i class="fas fa-times" aria-hidden="true"></i></button>';
        toast.querySelector('.ret-toast-close').addEventListener('click', function () { removeToast(toast); });
        container.appendChild(toast);
        setTimeout(function () { removeToast(toast); }, 5000);
    }

    function removeToast(toast) {
        toast.classList.add('removing');
        setTimeout(function () { toast.remove(); }, 300);
    }

    // ════════════════════════════════════════════════════════════
    // 3. SHOW STATE  (loading / empty / error / table)
    // ════════════════════════════════════════════════════════════
    function showState(state_name, errorMsg) {
        const loading   = document.getElementById('ret-tableLoading');
        const empty     = document.getElementById('ret-emptyState');
        const error     = document.getElementById('ret-errorState');
        const container = document.getElementById('ret-tableContainer');

        [loading, empty, error, container].forEach(function (el) {
            if (el) el.style.display = 'none';
        });

        switch (state_name) {
            case 'loading':
                if (loading) loading.style.display = 'flex';
                break;
            case 'empty':
                if (empty) empty.style.display = 'flex';
                break;
            case 'error':
                if (error) error.style.display = 'flex';
                if (errorMsg) {
                    const p = document.getElementById('ret-errorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default:
                if (container) container.style.display = 'block';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 4. API CALL
    // ════════════════════════════════════════════════════════════
    async function apiCall(url, opts) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (opts && opts.method && opts.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = CSRF;
        }
        const config = Object.assign({}, defaults, opts || {});
        if (opts && opts.headers) {
            config.headers = Object.assign({}, defaults.headers, opts.headers);
        }
        const res  = await fetch(url, config);
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
        return data;
    }

    // ════════════════════════════════════════════════════════════
    // 5. DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function loadReturns(page) {
        try {
            showState('loading');
            state.page = page || 1;
            const cfg    = window.RETURNS_CONFIG || {};
            const tenant = cfg.tenantId || 1;
            const lang   = cfg.lang || 'en';
            const params = new URLSearchParams({
                page:      state.page,
                limit:     cfg.itemsPerPage || state.perPage,
                tenant_id: tenant,
                lang:      lang
            });
            Object.keys(state.filters).forEach(function (k) {
                if (state.filters[k]) params.set(k, state.filters[k]);
            });
            const result = await apiCall(API.returns + '?' + params);
            if (result.success) {
                state.returns = result.data.items || result.data || [];
                state.total   = (result.data.meta && result.data.meta.total) || state.returns.length;
                renderTable(state.returns);
                updatePagination(state.total);
                showState(state.returns.length ? 'table' : 'empty');
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            showState('error', err.message);
        }
    }

    function updatePagination(total) {
        if (!el.pagination) return;
        const perPage = (window.RETURNS_CONFIG && window.RETURNS_CONFIG.itemsPerPage) || state.perPage;
        const pages   = Math.ceil(total / perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += '<button class="pagination-btn ' + (i === state.page ? 'active' : '') +
                    '" onclick="Returns.load(' + i + ')">' + i + '</button>';
        }
        el.pagination.innerHTML = html;
        if (el.paginationInfo) {
            const start = ((state.page - 1) * perPage) + 1;
            const end   = Math.min(state.page * perPage, total);
            el.paginationInfo.textContent = start + '–' + end + ' / ' + total;
        }
    }

    // ════════════════════════════════════════════════════════════
    // 6. RENDER TABLE
    // ════════════════════════════════════════════════════════════
    function statusBadge(status) {
        return '<span class="badge badge-' + esc(status) + '">' + esc(t('status.' + status, status)) + '</span>';
    }

    function renderTable(items) {
        if (!el.tbody) return;
        el.tbody.innerHTML = items.map(function (r) {
            return '<tr data-id="' + r.id + '">' +
                '<td>#' + r.id + '</td>' +
                '<td><strong>' + esc(r.return_number || '-') + '</strong></td>' +
                '<td>' + esc(r.order_number || '-') + '</td>' +
                '<td>' + esc(r.user_email   || '-') + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
                    esc(r.reason || '-') +
                '</td>' +
                '<td>' + (r.requested_at ? new Date(r.requested_at).toLocaleDateString() : '-') + '</td>' +
                '<td>' +
                    '<div class="table-actions">' +
                        '<button class="btn btn-sm btn-primary edit-btn" ' +
                                'onclick="Returns.edit(' + r.id + ')" ' +
                                'title="' + esc(t('form.edit_title', 'Edit')) + '" ' +
                                'aria-label="' + esc(t('form.edit_title', 'Edit')) + '">' +
                            '<i class="fas fa-edit" aria-hidden="true"></i>' +
                        '</button>' +
                        (CAN_DELETE
                            ? '<button class="btn btn-sm btn-danger delete-btn" ' +
                                      'onclick="Returns.remove(' + r.id + ')" ' +
                                      'title="' + esc(t('form.buttons.delete', 'Delete')) + '" ' +
                                      'aria-label="' + esc(t('form.buttons.delete', 'Delete')) + '">' +
                                  '<i class="fas fa-trash" aria-hidden="true"></i>' +
                              '</button>'
                            : '') +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    // ════════════════════════════════════════════════════════════
    // 7. MODAL / FORM SYSTEM
    // ════════════════════════════════════════════════════════════
    function openForm() {
        if (!el.formContainer) return;
        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth' });
        // Focus on first interactive element
        const first = el.formContainer.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (first) setTimeout(function () { first.focus(); }, 50);
    }

    function closeForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentReturn = null;
    }

    async function showForm(data) {
        state.currentReturn = data || null;
        state.returnItems   = [];
        state.returnHistory = [];

        if (el.form) el.form.reset();

        // Reset tabs
        if (el.formContainer) {
            el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            el.formContainer.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
        }
        const firstTab  = el.formContainer && el.formContainer.querySelector('.tab-btn[data-tab="details"]');
        const detailPane = document.getElementById('ret-tab-details');
        if (firstTab)   firstTab.classList.add('active');
        if (detailPane) detailPane.style.display = 'block';

        if (data) {
            if (el.formTitle)    el.formTitle.textContent = t('form.edit_title', 'Edit Return') + ' #' + data.id;
            if (el.formId)       el.formId.value          = data.id;
            if (el.returnNumber) el.returnNumber.value    = data.return_number || '';
            if (el.status)       el.status.value          = data.status;
            if (el.reason)       el.reason.value          = data.reason       || '';
            if (el.adminNotes)   el.adminNotes.value      = data.admin_notes  || '';
            if (el.btnDelete)    el.btnDelete.style.display = 'inline-flex';
            await loadReturnDetails(data.id);
        } else {
            if (el.formTitle) el.formTitle.textContent = t('form.add_title', 'New Return Request');
            if (el.formId)    el.formId.value          = '';
            if (el.btnDelete) el.btnDelete.style.display = 'none';
        }

        openForm();
    }

    async function loadReturnDetails(returnId) {
        const cfg    = window.RETURNS_CONFIG || {};
        const tenant = cfg.tenantId || 1;

        // Items
        try {
            const res = await apiCall(API.items + '?return_id=' + returnId + '&tenant_id=' + tenant);
            if (res.success) {
                state.returnItems = res.data.items || res.data || [];
                renderItems();
            }
        } catch (e) { /* silent */ }

        // History
        try {
            const res = await apiCall(API.history + '?return_id=' + returnId + '&tenant_id=' + tenant + '&order_by=id&order_dir=ASC');
            if (res.success) {
                state.returnHistory = res.data.items || res.data || [];
                renderHistory();
            }
        } catch (e) { /* silent */ }
    }

    function renderItems() {
        if (!el.itemsList) return;
        if (!state.returnItems.length) {
            el.itemsList.innerHTML =
                '<p style="color:var(--text-secondary);text-align:center;padding:20px">' +
                t('items.empty', 'No items in this return') +
                '</p>';
            return;
        }
        el.itemsList.innerHTML =
            '<div class="items-table-wrapper"><table class="items-table">' +
            '<thead><tr>' +
            '<th>' + t('items.headers.product',       'Product') + '</th>' +
            '<th>' + t('items.headers.quantity',      'Qty')     + '</th>' +
            '<th>' + t('items.headers.reason',        'Reason')  + '</th>' +
            '<th>' + t('items.headers.refund_amount', 'Refund')  + '</th>' +
            '</tr></thead><tbody>' +
            state.returnItems.map(function (item) {
                return '<tr>' +
                    '<td>#' + esc(item.product_id) + '</td>' +
                    '<td>' + esc(String(item.quantity)) + '</td>' +
                    '<td>' + esc(item.reason || '-') + '</td>' +
                    '<td>' + (item.refund_amount != null ? parseFloat(item.refund_amount).toFixed(2) : '-') + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    function renderHistory() {
        if (!el.historyList) return;
        if (!state.returnHistory.length) {
            el.historyList.innerHTML =
                '<p style="color:var(--text-secondary);text-align:center;padding:20px">' +
                t('history.empty', 'No status history yet') +
                '</p>';
            return;
        }
        el.historyList.innerHTML = '<div class="history-list">' +
            state.returnHistory.map(function (h) {
                return '<div class="history-item">' +
                    '<div class="history-item-content">' +
                        '<div>' + statusBadge(h.status) +
                        (h.changed_by
                            ? ' &nbsp;<small style="color:var(--text-secondary)">by #' + esc(String(h.changed_by)) + '</small>'
                            : '') +
                        '</div>' +
                        (h.notes
                            ? '<div style="margin-top:4px;font-size:0.85rem;color:var(--text-secondary)">' + esc(h.notes) + '</div>'
                            : '') +
                    '</div>' +
                    '<div class="history-item-date">' +
                        (h.created_at ? new Date(h.created_at).toLocaleString() : '') +
                    '</div>' +
                '</div>';
            }).join('') +
            '</div>';
    }

    // ════════════════════════════════════════════════════════════
    // 8. CRUD
    // ════════════════════════════════════════════════════════════
    async function saveReturn(e) {
        e.preventDefault();
        const cfg    = window.RETURNS_CONFIG || {};
        const tenant = cfg.tenantId || 1;
        const formData = new FormData(el.form);
        const id = formData.get('id');
        const data = {
            tenant_id:   tenant,
            status:      formData.get('status'),
            reason:      formData.get('reason')      || null,
            admin_notes: formData.get('admin_notes') || null
        };
        if (id) data.id = id;

        try {
            const method = id ? 'PUT' : 'POST';
            const res = await apiCall(API.returns, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (res.success) {
                notify(id ? t('messages.updated', 'Updated successfully') : t('messages.created', 'Created successfully'), 'success');
                closeForm();
                loadReturns(state.page);
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            notify(err.message, 'error');
        }
    }

    async function deleteReturn(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this return?'))) return;
        const cfg    = window.RETURNS_CONFIG || {};
        const tenant = cfg.tenantId || 1;
        try {
            const res = await apiCall(API.returns, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, tenant_id: tenant })
            });
            if (res.success) {
                notify(t('messages.deleted', 'Deleted successfully'), 'success');
                closeForm();
                loadReturns(state.page);
            }
        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // 9. FILTERS
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {
            search: (document.getElementById('ret-searchInput')  || {}).value || '',
            status: (document.getElementById('ret-statusFilter') || {}).value || ''
        };
        loadReturns(1);
    }

    function resetFilters() {
        state.filters = {};
        const si = document.getElementById('ret-searchInput');
        const sf = document.getElementById('ret-statusFilter');
        if (si) si.value = '';
        if (sf) sf.value = '';
        loadReturns(1);
    }

    // ════════════════════════════════════════════════════════════
    // 10. INIT
    // ════════════════════════════════════════════════════════════
    function init() {
        reloadConfig();

        el = {
            container:      document.getElementById('ret-tableContainer'),
            tbody:          document.getElementById('ret-tableBody'),
            pagination:     document.getElementById('ret-pagination'),
            paginationInfo: document.getElementById('ret-paginationInfo'),
            formContainer:  document.getElementById('ret-formContainer'),
            form:           document.getElementById('ret-form'),
            formTitle:      document.getElementById('ret-formTitle'),
            formId:         document.getElementById('ret-formId'),
            returnNumber:   document.getElementById('ret-returnNumber'),
            status:         document.getElementById('ret-status'),
            reason:         document.getElementById('ret-reason'),
            adminNotes:     document.getElementById('ret-adminNotes'),
            btnDelete:      document.getElementById('ret-btnDelete'),
            itemsList:      document.getElementById('ret-itemsList'),
            historyList:    document.getElementById('ret-historyList')
        };

        // ── Event Bindings ─────────────────────────────────────
        document.getElementById('ret-btnAdd')?.addEventListener('click', function () { showForm(); });
        document.getElementById('ret-btnCloseForm')?.addEventListener('click', closeForm);
        document.getElementById('ret-btnCancelForm')?.addEventListener('click', closeForm);
        document.getElementById('ret-btnAddFirst')?.addEventListener('click', function () { showForm(); });

        if (el.form) el.form.addEventListener('submit', saveReturn);

        if (el.btnDelete) {
            el.btnDelete.addEventListener('click', function () {
                const id = el.formId && el.formId.value ? parseInt(el.formId.value, 10) : null;
                if (id) deleteReturn(id);
            });
        }

        document.getElementById('ret-btnApplyFilters')?.addEventListener('click', applyFilters);
        document.getElementById('ret-btnResetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('ret-btnRetry')?.addEventListener('click', function () { loadReturns(state.page); });

        // Tabs
        if (el.formContainer) {
            el.formContainer.querySelectorAll('.tab-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                    el.formContainer.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
                    btn.classList.add('active');
                    const pane = document.getElementById('ret-tab-' + btn.dataset.tab);
                    if (pane) pane.style.display = 'block';
                });
            });
        }

        // ESC closes form
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (el.formContainer && el.formContainer.style.display !== 'none') {
                closeForm();
            }
        });

        loadReturns(1);
    }

    // ════════════════════════════════════════════════════════════
    // 11. REGISTER
    // ════════════════════════════════════════════════════════════
    window.Returns = {
        init:   init,
        load:   loadReturns,
        edit:   async function (id) {
            try {
                const cfg    = window.RETURNS_CONFIG || {};
                const tenant = cfg.tenantId || 1;
                const res    = await apiCall(API.returns + '?id=' + id + '&tenant_id=' + tenant);
                if (res.success) await showForm(res.data);
            } catch (e) { console.error('[Returns] edit error:', e); }
        },
        remove: deleteReturn
    };

    window.page = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('returns', init);
    }

    // Initialization is driven by the fragment's inline <script> which waits
    // for admin:i18n:applied — do NOT self-invoke init() here.

}());