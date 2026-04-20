(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    let CFG, CSRF, CAN_CREATE, CAN_EDIT, CAN_DELETE;

    function reloadConfig() {
        CFG        = window.TICKETS_CONFIG || {};
        CSRF       = CFG.csrfToken || window.APP_CONFIG?.CSRF_TOKEN || '';
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    const API = {
        get tickets()    { return (window.TICKETS_CONFIG || {}).apiUrl         || '/api/support_tickets'; },
        get categories() { return (window.TICKETS_CONFIG || {}).categoriesApi  || '/api/ticket_categories'; },
        get messages()   { return (window.TICKETS_CONFIG || {}).messagesApi    || '/api/ticket_messages'; },
        get history()    { return (window.TICKETS_CONFIG || {}).historyApi     || '/api/ticket_status_history'; },
        get users()      { return (window.TICKETS_CONFIG || {}).usersApi       || '/api/users'; },
        get orders()     { return (window.TICKETS_CONFIG || {}).ordersApi      || '/api/orders'; },
        get entities()   { return (window.TICKETS_CONFIG || {}).entitiesApi    || '/api/entities'; }
    };

    const state = {
        page:          1,
        perPage:       20,
        total:         0,
        tickets:       [],
        categories:    [],
        users:         [],
        currentTicket: null,
        messages:      [],
        history:       [],
        filters:       {}
    };

    let el = {};

    // ════════════════════════════════════════════════════════════
    // 2. i18n — reads live from TICKETS_CONFIG.strings (flat)
    // ════════════════════════════════════════════════════════════
    function t(key, fb) {
        const live = (window.TICKETS_CONFIG && window.TICKETS_CONFIG.strings) || {};
        if (live[key] !== undefined && live[key] !== '') return String(live[key]);
        // Nested traversal on TICKETS_TRANSLATIONS
        const tr  = window.TICKETS_TRANSLATIONS || {};
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr);
        if (val !== null && val !== undefined && typeof val !== 'object') return String(val);
        return fb !== undefined ? fb : key.split('.').pop().replace(/_/g, ' ');
    }

    function applyI18n() {
        const container = document.getElementById('ticketsPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const val = t(key, '');
            if (!val) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = val;
            } else if (el.tagName === 'OPTION') {
                el.textContent = val;
            } else {
                el.textContent = val;
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const val = t(el.getAttribute('data-i18n-placeholder'), '');
            if (val) el.placeholder = val;
        });
    }

    // ════════════════════════════════════════════════════════════
    // 3. HELPERS
    // ════════════════════════════════════════════════════════════
    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    function lang() { return (window.TICKETS_CONFIG || {}).lang || window.USER_LANGUAGE || 'en'; }
    function tenantId() { return (window.TICKETS_CONFIG || {}).tenantId || window.APP_CONFIG?.TENANT_ID || 1; }

    // ════════════════════════════════════════════════════════════
    // 4. SHOW STATE
    // ════════════════════════════════════════════════════════════
    function showState(stateName, errorMsg) {
        const loading   = document.getElementById('tableLoading');
        const empty     = document.getElementById('emptyState');
        const error     = document.getElementById('errorState');
        const container = document.getElementById('tableContainer');

        [loading, empty, error, container].forEach(el => { if (el) el.style.display = 'none'; });

        switch (stateName) {
            case 'loading':
                if (loading)   loading.style.display   = 'flex';   break;
            case 'empty':
                if (empty)     empty.style.display     = 'flex';   break;
            case 'error':
                if (error)     error.style.display     = 'flex';
                if (errorMsg) {
                    const p = document.getElementById('errorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default:
                if (container) container.style.display = 'block';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 5. NOTIFICATION
    // ════════════════════════════════════════════════════════════
    function notify(msg, type = 'info') {
        if (window._admin && typeof window._admin.notify === 'function') {
            return window._admin.notify(msg, type);
        }
        // Fallback toast using CSS vars only
        let container = document.querySelector('.tkt-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'tkt-notifications';
            document.body.appendChild(container);
        }
        const iconMap = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = `tkt-toast tkt-toast--${type}`;
        toast.innerHTML =
            `<i class="fas ${iconMap[type] || 'fa-info-circle'} tkt-toast-icon" aria-hidden="true"></i>` +
            `<span class="tkt-toast-body">${esc(msg)}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // ════════════════════════════════════════════════════════════
    // 6. API CALL
    // ════════════════════════════════════════════════════════════
    async function apiCall(url, opts = {}) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (opts.method && opts.method !== 'GET') headers['X-CSRF-Token'] = CSRF;
        if (opts.headers) Object.assign(headers, opts.headers);
        const res  = await fetch(url, { credentials: 'same-origin', ...opts, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // ════════════════════════════════════════════════════════════
    // 7. LOAD TICKETS
    // ════════════════════════════════════════════════════════════
    async function loadTickets(page = 1) {
        try {
            showState('loading');
            state.page = page;
            const params = new URLSearchParams({
                page, limit: state.perPage, tenant_id: tenantId(), lang: lang()
            });
            Object.entries(state.filters).forEach(([k, v]) => { if (v) params.set(k, v); });
            const result = await apiCall(`${API.tickets}?${params}`);
            if (!result.success) throw new Error(result.message);
            state.tickets = result.data.items || result.data || [];
            state.total   = result.data.meta?.total ?? state.tickets.length;
            renderTable(state.tickets);
            updatePagination(state.total);
            showState(state.tickets.length ? 'table' : 'empty');
        } catch (err) {
            showState('error', err.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 8. LOAD DROPDOWNS
    // ════════════════════════════════════════════════════════════
    async function loadDropdowns() {
        try {
            const res = await apiCall(`${API.categories}?tenant_id=${tenantId()}&lang=${lang()}`);
            if (res.success) {
                state.categories = res.data.items || res.data || [];
                populateSelect(el.category, state.categories, 'id', 'name', t('form.fields.category.select', 'Select category'));
            }
        } catch (e) { console.warn('[Tickets] categories:', e); }

        try {
            const res = await apiCall(`${API.users}?limit=200&tenant_id=${tenantId()}`);
            if (res.success) {
                state.users = res.data.items || res.data || [];
                populateSelect(el.user,     state.users, 'id', 'email', t('form.fields.user.select',        'Select customer'));
                populateSelect(el.assigned, state.users, 'id', 'email', t('form.fields.assigned_to.select', 'Select agent'), true);
            }
        } catch (e) { console.warn('[Tickets] users:', e); }

        populateSelect(el.order,  [], 'id', 'order_number', t('form.fields.order.select',  'Select order (optional)'));
        populateSelect(el.entity, [], 'id', 'store_name',   t('form.fields.entity.select', 'Select entity'));
    }

    async function loadUserOrdersAndEntities(userId) {
        if (!userId) {
            populateSelect(el.order,  [], 'id', 'order_number', t('form.fields.order.select',  'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name',   t('form.fields.entity.select', 'Select entity'));
            return;
        }
        try {
            const res = await apiCall(`${API.orders}?user_id=${userId}&tenant_id=${tenantId()}&limit=100`);
            if (res.success) populateSelect(el.order, res.data.items || res.data || [], 'id', 'order_number', t('form.fields.order.select', 'Select order (optional)'));
        } catch (e) { console.warn('[Tickets] orders:', e); }

        try {
            const res = await apiCall(`${API.entities}?user_id=${userId}&tenant_id=${tenantId()}&limit=100`);
            if (res.success) {
                const entities = res.data.items || res.data || [];
                populateSelect(el.entity, entities, 'id', 'store_name', t('form.fields.entity.select', 'Select entity'));
                if (entities.length === 1 && el.entity) el.entity.value = entities[0].id;
            }
        } catch (e) { console.warn('[Tickets] entities:', e); }
    }

    function populateSelect(sel, items, valKey, txtKey, placeholder, includeUnassigned = false) {
        if (!sel) return;
        sel.innerHTML = '';
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = ''; opt.textContent = placeholder;
            sel.appendChild(opt);
        }
        if (includeUnassigned) {
            const opt = document.createElement('option');
            opt.value = '0'; opt.textContent = t('form.fields.assigned_to.unassigned', 'Unassigned');
            sel.appendChild(opt);
        }
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valKey];
            opt.textContent = item[txtKey] || `ID: ${item[valKey]}`;
            sel.appendChild(opt);
        });
    }

    // ════════════════════════════════════════════════════════════
    // 9. RENDER TABLE
    // ════════════════════════════════════════════════════════════
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showState('empty'); return; }

        el.tbody.innerHTML = items.map(ticket => {
            const statusClass    = ticket.status === 'open' ? 'badge-success' : ticket.status === 'closed' ? 'badge-secondary' : 'badge-primary';
            const priorityClass  = ticket.priority === 'urgent' ? 'badge-danger' : ticket.priority === 'high' ? 'badge-warning' : 'badge-secondary';
            const updatedDate    = ticket.updated_at ? new Date(ticket.updated_at).toLocaleDateString(lang()) : '—';
            const statusLabel    = t('status.'   + ticket.status,   ticket.status);
            const priorityLabel  = t('priority.' + ticket.priority, ticket.priority);

            return `<tr data-id="${ticket.id}">
                <td>#${esc(ticket.id)}</td>
                <td>
                    <strong>${esc(ticket.subject)}</strong>
                    ${ticket.ticket_number ? `<br><small style="color:var(--text-secondary)">${esc(ticket.ticket_number)}</small>` : ''}
                </td>
                <td>${esc(ticket.user_email || t('table.guest', 'Guest'))}</td>
                <td>${esc(ticket.category_name || '—')}</td>
                <td><span class="badge ${priorityClass}">${esc(priorityLabel)}</span></td>
                <td><span class="badge ${statusClass}">${esc(statusLabel)}</span></td>
                <td>${esc(updatedDate)}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-primary"
                                onclick="Tickets.edit(${ticket.id})"
                                title="${esc(t('table.actions.edit', 'Edit'))}"
                                aria-label="${esc(t('table.actions.edit', 'Edit'))}">
                            <i class="fas fa-edit" aria-hidden="true"></i>
                        </button>
                        ${CAN_DELETE ? `<button class="btn btn-sm btn-danger"
                                onclick="Tickets.remove(${ticket.id})"
                                title="${esc(t('table.actions.delete', 'Delete'))}"
                                aria-label="${esc(t('table.actions.delete', 'Delete'))}">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ════════════════════════════════════════════════════════════
    // 10. FORM — open with focus, close
    // ════════════════════════════════════════════════════════════
    function openForm() {
        if (!el.formContainer) return;
        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth' });
        const first = el.formContainer.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (first) setTimeout(() => first.focus(), 50);
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentTicket = null;
    }

    async function showForm(data = null) {
        state.currentTicket = data;
        state.messages      = [];
        state.history       = [];

        el.form?.reset();

        // Reset tabs
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        document.querySelector('.tab-btn[data-tab="details"]')?.classList.add('active');
        const detailsPane = document.getElementById('tab-details');
        if (detailsPane) detailsPane.style.display = 'block';

        if (data) {
            if (el.formTitle) el.formTitle.textContent = `${t('form.edit_title', 'Edit Ticket')} #${data.id}`;
            if (el.formId)    el.formId.value          = data.id;
            if (el.subject)   el.subject.value         = data.subject     || '';
            if (el.description) el.description.value   = data.description || '';
            if (el.status)    el.status.value           = data.status      || 'open';
            if (el.priority)  el.priority.value         = data.priority    || 'normal';
            if (data.category_id && el.category) el.category.value = data.category_id;
            if (data.assigned_to  && el.assigned) el.assigned.value = data.assigned_to;
            if (data.user_id && el.user) {
                el.user.value = data.user_id;
                await loadUserOrdersAndEntities(data.user_id);
                if (data.order_id  && el.order)  el.order.value  = data.order_id;
                if (data.entity_id && el.entity) el.entity.value = data.entity_id;
            }
            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';
            loadTicketData(data.id);
        } else {
            if (el.formTitle) el.formTitle.textContent = t('form.add_title', 'New Support Ticket');
            if (el.formId)    el.formId.value          = '';
            if (el.btnDelete) el.btnDelete.style.display = 'none';
            populateSelect(el.order,  [], 'id', 'order_number', t('form.fields.order.select',  'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name',   t('form.fields.entity.select', 'Select entity'));
        }

        openForm();
    }

    // ════════════════════════════════════════════════════════════
    // 11. LOAD MESSAGES + HISTORY
    // ════════════════════════════════════════════════════════════
    async function loadTicketData(id) {
        try {
            const res = await apiCall(`${API.messages}?ticket_id=${id}&tenant_id=${tenantId()}`);
            if (res.success) { state.messages = res.data.items || res.data || []; renderMessages(); }
        } catch (e) { console.warn('[Tickets] messages:', e); }

        try {
            const res = await apiCall(`${API.history}?ticket_id=${id}&tenant_id=${tenantId()}`);
            if (res.success) { state.history = res.data.items || res.data || []; renderHistory(); }
        } catch (e) { console.warn('[Tickets] history:', e); }
    }

    function renderMessages() {
        if (!el.messagesList) return;
        if (!state.messages.length) {
            el.messagesList.innerHTML = `<p class="tkt-empty-msg">${t('messages.empty', 'No messages yet.')}</p>`;
            return;
        }
        el.messagesList.innerHTML = state.messages.map(msg => `
            <div class="message-item${msg.is_internal ? ' is-internal' : ''}">
                <div class="message-header">
                    <strong>${esc(msg.sender_email || t('messages.system', 'System'))}</strong>
                    <span class="message-meta">
                        ${msg.is_internal ? `<em class="internal-label">${t('messages.internal_note', 'Internal Note')}</em>` : ''}
                        ${new Date(msg.created_at).toLocaleString(lang())}
                    </span>
                </div>
                <div class="message-body">${esc(msg.message)}</div>
            </div>
        `).join('');
    }

    function renderHistory() {
        if (!el.historyList) return;
        if (!state.history.length) {
            el.historyList.innerHTML = `<p class="tkt-empty-msg">${t('history.empty', 'No history yet.')}</p>`;
            return;
        }
        el.historyList.innerHTML = state.history.map(h => `
            <div class="history-item">
                <span class="badge badge-secondary">${esc(t('status.' + (h.old_status || 'new'), h.old_status || 'New'))}</span>
                <i class="fas fa-arrow-right history-arrow" aria-hidden="true"></i>
                <span class="badge badge-success">${esc(t('status.' + h.new_status, h.new_status))}</span>
                <span class="history-date">${new Date(h.created_at).toLocaleString(lang())}</span>
            </div>
        `).join('');
    }

    // ════════════════════════════════════════════════════════════
    // 12. SAVE / DELETE / REPLY
    // ════════════════════════════════════════════════════════════
    async function saveTicket(e) {
        e.preventDefault();
        const fd = new FormData(el.form);
        const id = fd.get('id');
        const payload = {
            tenant_id:   tenantId(),
            subject:     fd.get('subject'),
            description: fd.get('description'),
            category_id: fd.get('category_id')  || null,
            user_id:     fd.get('user_id')       || null,
            order_id:    fd.get('order_id')      || null,
            entity_id:   fd.get('entity_id')     || null,
            status:      fd.get('status'),
            priority:    fd.get('priority'),
            assigned_to: fd.get('assigned_to')   || null
        };
        if (id) payload.id = id;

        try {
            const res = await apiCall(API.tickets, {
                method:  id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            });
            if (!res.success) throw new Error(res.message);
            notify(id ? t('notifications.updated', 'Ticket updated successfully') : t('notifications.created', 'Ticket created successfully'), 'success');
            hideForm();
            loadTickets(state.page);
        } catch (err) {
            notify(err.message || t('errors.save_failed', 'Failed to save ticket'), 'error');
        }
    }

    async function sendReply() {
        if (!state.currentTicket) return;
        const message = el.replyText?.value?.trim();
        if (!message) return;
        try {
            const res = await apiCall(API.messages, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    tenant_id:      tenantId(),
                    ticket_id:      state.currentTicket.id,
                    sender_user_id: (window.TICKETS_CONFIG || {}).userId || null,
                    message,
                    is_internal:    el.replyInternal?.checked ? 1 : 0
                })
            });
            if (!res.success) throw new Error(res.message);
            if (el.replyText)     el.replyText.value       = '';
            if (el.replyInternal) el.replyInternal.checked = false;
            notify(t('notifications.reply_sent', 'Reply sent successfully'), 'success');
            await loadTicketData(state.currentTicket.id);
        } catch (err) { notify(err.message, 'error'); }
    }

    async function deleteTicket(id) {
        if (!id) return;
        if (!confirm(t('confirm.delete', 'Are you sure you want to delete this ticket?'))) return;
        try {
            const res = await apiCall(API.tickets, {
                method:  'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id, tenant_id: tenantId() })
            });
            if (!res.success) throw new Error(res.message);
            notify(t('notifications.deleted', 'Ticket deleted'), 'success');
            hideForm();
            loadTickets(state.page);
        } catch (err) { notify(err.message, 'error'); }
    }

    // ════════════════════════════════════════════════════════════
    // 13. PAGINATION
    // ════════════════════════════════════════════════════════════
    function updatePagination(total) {
        if (!el.pagination) return;
        const pages = Math.ceil(total / state.perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += `<button class="pagination-btn${i === state.page ? ' active' : ''}" onclick="Tickets.load(${i})">${i}</button>`;
        }
        el.pagination.innerHTML = html;
        if (el.paginationInfo) {
            const from = total ? ((state.page - 1) * state.perPage) + 1 : 0;
            const to   = Math.min(state.page * state.perPage, total);
            el.paginationInfo.textContent = `${from}–${to} ${t('pagination.of', 'of')} ${total}`;
        }
    }

    // ════════════════════════════════════════════════════════════
    // 14. FILTERS
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {
            search:   document.getElementById('searchInput')?.value    || '',
            status:   document.getElementById('statusFilter')?.value   || '',
            priority: document.getElementById('priorityFilter')?.value || ''
        };
        loadTickets(1);
    }

    function resetFilters() {
        state.filters = {};
        ['searchInput','statusFilter','priorityFilter'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        loadTickets(1);
    }

    // ════════════════════════════════════════════════════════════
    // 15. INIT
    // ════════════════════════════════════════════════════════════
    async function init() {
        reloadConfig();
        applyI18n();

        el = {
            container:     document.getElementById('tableContainer'),
            loading:       document.getElementById('tableLoading'),
            empty:         document.getElementById('emptyState'),
            error:         document.getElementById('errorState'),
            tbody:         document.getElementById('tableBody'),
            pagination:    document.getElementById('pagination'),
            paginationInfo:document.getElementById('paginationInfo'),
            formContainer: document.getElementById('ticketFormContainer'),
            form:          document.getElementById('ticketForm'),
            formTitle:     document.getElementById('formTitle'),
            formId:        document.getElementById('formId'),
            subject:       document.getElementById('ticketSubject'),
            description:   document.getElementById('ticketDescription'),
            status:        document.getElementById('ticketStatus'),
            priority:      document.getElementById('ticketPriority'),
            category:      document.getElementById('ticketCategory'),
            user:          document.getElementById('ticketUser'),
            order:         document.getElementById('ticketOrder'),
            entity:        document.getElementById('ticketEntity'),
            assigned:      document.getElementById('ticketAssigned'),
            messagesList:  document.getElementById('ticketMessagesList'),
            historyList:   document.getElementById('ticketHistoryList'),
            replyText:     document.getElementById('ticketReply'),
            replyInternal: document.getElementById('replyInternal'),
            btnDelete:     document.getElementById('btnDeleteTicket')
        };

        // ── Bindings ─────────────────────────────────────────────
        document.getElementById('btnAddTicket')?.addEventListener('click',    () => showForm());
        document.getElementById('btnAddTicketEmpty')?.addEventListener('click', () => showForm());
        document.getElementById('btnCloseForm')?.addEventListener('click',    hideForm);
        document.getElementById('btnCancelForm')?.addEventListener('click',   hideForm);
        el.form?.addEventListener('submit', saveTicket);
        el.btnDelete?.addEventListener('click', () => deleteTicket(state.currentTicket?.id));
        document.getElementById('btnSendReply')?.addEventListener('click', sendReply);
        el.user?.addEventListener('change', () => loadUserOrdersAndEntities(el.user.value));

        document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
        document.getElementById('btnResetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('btnRetry')?.addEventListener('click', () => loadTickets(state.page));
        document.getElementById('searchInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                const pane = document.getElementById(`tab-${btn.dataset.tab}`);
                if (pane) pane.style.display = 'block';
            });
        });

        // ESC closes form
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el.formContainer && el.formContainer.style.display !== 'none') hideForm();
        });

        await loadDropdowns();
        await loadTickets(1);
        console.log('[Tickets] ✓ Initialized');
    }

    // ════════════════════════════════════════════════════════════
    // 16. REGISTER
    // ════════════════════════════════════════════════════════════
    window.Tickets = {
        init,
        load:   loadTickets,
        edit:   async (id) => {
            try {
                const res = await apiCall(`${API.tickets}?id=${id}&tenant_id=${tenantId()}`);
                if (res.success) await showForm(res.data);
                else throw new Error(res.message);
            } catch (e) { console.error('[Tickets] edit:', e); notify(e.message, 'error'); }
        },
        remove: deleteTicket
    };

    window.page = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('tickets', init);
    }

    // Initialization driven by fragment's inline script — do NOT self-invoke here.
}());
