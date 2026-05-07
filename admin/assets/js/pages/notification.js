/**
 * /admin/assets/js/pages/notification.js
 * Notification System – 5-tab admin module
 * Tabs: types | list | channels | counters | deliveries
 */
(function (window) {
    'use strict';

    /* ──────────────────────────────────────────
       HELPERS
    ────────────────────────────────────────── */
    const cfg = () => window.NOTIFICATIONS_CONFIG || {};
    const perm = () => window.PAGE_PERMISSIONS || {};
    const t = (key) => {
        const tr = window.NOTIF_TRANSLATIONS || {};
        return key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr) ?? key;
    };

    let _csrfToken = () => window.APP_CONFIG?.CSRF_TOKEN || window.CSRF_TOKEN || '';

    async function apiFetch(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': _csrfToken(),
            },
            credentials: 'same-origin',
        };
        const res = await fetch(url, Object.assign({}, defaults, options, {
            headers: Object.assign({}, defaults.headers, options.headers || {}),
        }));
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || json.error || `HTTP ${res.status}`);
        return json;
    }

    function showToast(msg, type = 'success') {
        if (window.AdminFramework?.toast) {
            window.AdminFramework.toast(msg, type);
            return;
        }
        if (window.showToast) {
            window.showToast(msg, type);
            return;
        }
        // fallback
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = `
            position:fixed; top:20px; right:20px; z-index:99999;
            padding:12px 20px; border-radius:8px; color:#fff; font-size:0.9rem;
            background:${type === 'success' ? 'var(--success, #22c55e)' : 'var(--danger, #ef4444)'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease;
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    function confirmDialog(msg) {
        return window.confirm(msg);
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function truncate(str, len = 60) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '…' : str;
    }

    function dateFmt(val) {
        if (!val) return '—';
        try {
            return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch { return val; }
    }

    /* ──────────────────────────────────────────
       TAB DEFINITIONS
    ────────────────────────────────────────── */
    const TABS = {
        types: {
            apiKey: 'types',
            titleKey: 'types.title',
            addKey: 'types.add_new',
            emptyKey: 'types.empty_title',
            emptyMsgKey: 'types.empty_message',
            addFirstKey: 'types.add_first',
            showStatus: true,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: false,
            showDeviceType: false,
        },
        list: {
            apiKey: 'list',
            titleKey: 'list.title',
            addKey: 'list.add_new',
            emptyKey: 'list.empty_title',
            emptyMsgKey: 'list.empty_message',
            addFirstKey: 'list.add_first',
            showStatus: false,
            showPriority: true,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: true,
            showDeviceType: false,
        },
        channels: {
            apiKey: 'channels',
            titleKey: 'channels.title',
            addKey: 'channels.add_new',
            emptyKey: 'channels.empty_title',
            emptyMsgKey: 'channels.empty_message',
            addFirstKey: 'channels.add_first',
            showStatus: true,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: false,
            showDeviceType: false,
        },
        counters: {
            apiKey: 'counters',
            titleKey: 'counters.title',
            addKey: 'counters.add_new',
            emptyKey: 'counters.empty_title',
            emptyMsgKey: 'counters.empty_message',
            addFirstKey: 'counters.add_first',
            showStatus: false,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: true,
            showTenant: true,
            showDeviceType: false,
        },
        deliveries: {
            apiKey: 'deliveries',
            titleKey: 'deliveries.title',
            addKey: 'deliveries.add_new',
            emptyKey: 'deliveries.empty_title',
            emptyMsgKey: 'deliveries.empty_message',
            addFirstKey: 'deliveries.add_first',
            showStatus: false,
            showPriority: false,
            showDeliveryStatus: true,
            showRecipientType: false,
            showTenant: false,
            showDeviceType: false,
        },
        devices: {
            apiKey: 'devices',
            titleKey: 'devices.title',
            addKey: 'devices.add_new',
            emptyKey: 'devices.empty_title',
            emptyMsgKey: 'devices.empty_message',
            addFirstKey: 'devices.add_first',
            showStatus: true,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: false,
            showDeviceType: true,
        },
        recipients: {
            apiKey: 'recipients',
            titleKey: 'recipients.title',
            addKey: null,
            emptyKey: 'recipients.empty_title',
            emptyMsgKey: 'recipients.empty_message',
            addFirstKey: 'recipients.empty_title',
            showStatus: false,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: true,
            showTenant: true,
            showDeviceType: false,
            readOnly: true,
        },
    };

    /* ──────────────────────────────────────────
       STATE
    ────────────────────────────────────────── */
    let state = {
        currentTab: 'types',
        currentPage: 1,
        filters: {},
        editingId: null,
        notifTypes: [],     // cache
        channels: [],       // cache
    };

    /* ──────────────────────────────────────────
       DOM REFERENCES
    ────────────────────────────────────────── */
    let $;
    function getEl(id) { return document.getElementById(id); }
    function getEls(sel, ctx) { return (ctx || document).querySelectorAll(sel); }

    /* ──────────────────────────────────────────
       TABLE HEADERS PER TAB
    ────────────────────────────────────────── */
    function getHeaders(tab) {
        const H = tab => ({ id: t('table.headers.id'), ...tab });
        const act = t('table.headers.actions');
        switch (tab) {
            case 'types':
                return [
                    t('table.headers.id'), t('table.headers.code'), t('table.headers.name'),
                    t('table.headers.description'), t('table.headers.status'),
                    t('table.headers.owner_scope') || 'Scope',
                    t('table.headers.default_template'), t('table.headers.actions')
                ];
            case 'list':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.sender'),
                    t('table.headers.entity'), t('table.headers.title'),
                    t('table.headers.message'), t('table.headers.priority'), t('table.headers.type'),
                    t('table.headers.sent_at'), t('table.headers.expires_at'), act
                ];
            case 'channels':
                return [
                    t('table.headers.id'), t('table.headers.code'), t('table.headers.name'),
                    t('table.headers.status'), t('table.headers.created_at'), act
                ];
            case 'counters':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.recipient_type'),
                    t('table.headers.recipient_id'), t('table.headers.unread_count'),
                    t('table.headers.updated_at'), act
                ];
            case 'deliveries':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.notification'),
                    t('table.headers.channel'), t('table.headers.delivery_status'), t('table.headers.attempts'),
                    t('table.headers.sent_at'), t('table.headers.error'), act
                ];
            case 'devices':
                return [
                    t('table.headers.id'), t('table.headers.user_id'), t('table.headers.device_type'),
                    t('table.headers.device_name'), t('table.headers.fcm_token'),
                    t('table.headers.ip'), t('table.headers.last_seen'),
                    t('table.headers.status'), act
                ];
            case 'recipients':
                return [
                    t('table.headers.id'),
                    t('table.headers.notification') || 'Notification',
                    t('table.headers.tenant') || 'Tenant',
                    t('table.headers.recipient_type') || 'Type',
                    t('table.headers.recipient_id') || 'Recipient',
                    t('table.headers.is_read') || 'Read',
                    t('table.headers.read_at') || 'Read At',
                    t('table.headers.created_at') || 'Created',
                    act
                ];
        }
        return [];
    }

    /* ──────────────────────────────────────────
       RENDER ROW PER TAB
    ────────────────────────────────────────── */
    function renderRow(tab, item) {
        const editBtn = perm().canEdit ?
            `<button class="btn btn-sm btn-primary notif-edit-btn" data-id="${item.id}" title="${t('table.actions.edit')}">
                <i class="fas fa-edit"></i>
             </button>` : '';
        const deleteBtn = perm().canDelete ?
            `<button class="btn btn-sm btn-danger notif-delete-btn" data-id="${item.id}" title="${t('table.actions.delete')}">
                <i class="fas fa-trash"></i>
             </button>` : '';
        const actions = `<div class="action-btns">${editBtn}${deleteBtn}</div>`;

        switch (tab) {
            case 'types': {
                const status = (item.is_active == 1)
                    ? `<span class="badge badge-success">${t('table.status.active')}</span>`
                    : `<span class="badge badge-danger">${t('table.status.inactive')}</span>`;
                const scopeColors = { platform: 'warning', tenant: 'info', shared: 'secondary' };
                const scope = item.owner_scope || 'shared';
                const scopeBadge = `<span class="badge badge-${scopeColors[scope] || 'secondary'}">${esc(scope)}</span>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td><code style="background:var(--primary-light, rgba(59,130,246,0.1));padding:2px 6px;border-radius:4px;font-size:0.8rem;">${esc(item.code)}</code></td>
                    <td>${esc(item.name)}</td>
                    <td><span class="text-truncate" title="${esc(item.description)}">${esc(truncate(item.description))}</span></td>
                    <td>${status}</td>
                    <td>${scopeBadge}</td>
                    <td><span style="color:var(--text-secondary,#94a3b8);font-size:0.8rem;">${item.default_template ? '✓' : '—'}</span></td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'list': {
                const p = item.priority || 'normal';
                const priorityBadge = `<span class="badge badge-${p}">${t('priority_labels.' + p) || p}</span>`;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                const sName = item.sender_name ? `${esc(item.sender_name)} <small>(${esc(item.sender_entity_id)})</small>` : (item.sender_entity_id || '—');
                const rName = item.recipient_name ? `${esc(item.recipient_name)} <small>(${esc(item.entity_id)})</small>` : (item.entity_id || '—');

                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td>${sName}</td>
                    <td>${rName}</td>
                    <td><span class="text-truncate" title="${esc(item.title)}">${esc(truncate(item.title, 40))}</span></td>
                    <td><span class="text-truncate" title="${esc(item.message)}">${esc(truncate(item.message, 50))}</span></td>
                    <td>${priorityBadge}</td>
                    <td>${esc(item.type_name || item.notification_type_id || '—')}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.sent_at)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.expires_at)}</td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'channels': {
                const status = (item.is_active == 1)
                    ? `<span class="badge badge-success">${t('table.status.active')}</span>`
                    : `<span class="badge badge-danger">${t('table.status.inactive')}</span>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td><code style="background:var(--primary-light, rgba(59,130,246,0.1));padding:2px 6px;border-radius:4px;font-size:0.8rem;">${esc(item.code)}</code></td>
                    <td>${esc(item.name)}</td>
                    <td>${status}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.created_at)}</td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'counters': {
                const rt = item.recipient_type || '';
                const rtLabel = t('recipient_type_labels.' + rt) || rt;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                const rName = item.recipient_name ? `${esc(item.recipient_name)} <small>(${esc(item.recipient_id)})</small>` : esc(item.recipient_id);
                const extraBtns = `
                    <button class="btn btn-sm btn-primary notif-increment-btn" data-id="${item.id}" title="${t('table.actions.increment')}">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary notif-reset-btn" data-id="${item.id}" title="${t('table.actions.reset')}">
                        <i class="fas fa-redo"></i>
                    </button>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td><span class="badge badge-info">${esc(rtLabel)}</span></td>
                    <td>${rName}</td>
                    <td><strong style="color:${item.unread_count > 0 ? 'var(--warning, #f59e0b)' : 'var(--text-secondary, #94a3b8)'};">${esc(item.unread_count ?? 0)}</strong></td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.updated_at)}</td>
                    <td><div class="action-btns">${editBtn}${extraBtns}${deleteBtn}</div></td>
                </tr>`;
            }
            case 'deliveries': {
                const ds = item.delivery_status || 'pending';
                const dsBadge = `<span class="badge badge-${ds}">${t('delivery_status_labels.' + ds) || ds}</span>`;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td><span class="text-truncate" title="${esc(item.notification_title)}">${esc(truncate(item.notification_title, 35))}</span></td>
                    <td>${esc(item.channel_name || item.channel_id)}</td>
                    <td>${dsBadge}</td>
                    <td>${esc(item.attempts ?? 0)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.sent_at)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">
                        ${item.error_message ? `<span title="${esc(item.error_message)}" style="color:var(--danger, #ef4444);">${esc(truncate(item.error_message, 35))}</span>` : '—'}
                    </td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'devices': {
                const status = (item.is_active == 1)
                    ? `<span class="badge badge-success">${t('table.status.active')}</span>`
                    : `<span class="badge badge-danger">${t('table.status.inactive')}</span>`;
                const dtLabel = t('device_type_labels.' + (item.device_type || 'web')) || item.device_type || 'web';
                const tokenShort = item.fcm_token ? truncate(item.fcm_token, 25) : '—';
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${esc(item.user_id)}</td>
                    <td><span class="badge badge-info">${esc(dtLabel)}</span></td>
                    <td>${esc(item.device_name || '—')}</td>
                    <td><code style="font-size:0.75rem;color:var(--text-secondary,#94a3b8);" title="${esc(item.fcm_token)}">${esc(tokenShort)}</code></td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${esc(item.ip || '—')}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.last_seen_at)}</td>
                    <td>${status}</td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'recipients': {
                const readBadge = (item.is_read == 1)
                    ? `<span class="badge badge-success">${t('table.status.read') || 'Read'}</span>`
                    : `<span class="badge badge-warning">${t('table.status.unread') || 'Unread'}</span>`;
                const tName = item.tenant_name
                    ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>`
                    : (item.tenant_id ? esc(item.tenant_id) : '—');
                const rtLabel = t('recipient_type_labels.' + (item.recipient_type || '')) || item.recipient_type || '—';
                const rName = item.recipient_name
                    ? `${esc(item.recipient_name)} <small>(${esc(item.recipient_id)})</small>`
                    : esc(item.recipient_id);
                const notifTitle = item.notification_title ? truncate(item.notification_title, 40) : `#${esc(item.notification_id)}`;
                const markReadBtn = (item.is_read != 1)
                    ? `<button class="btn btn-sm btn-success notif-mark-read-btn" data-id="${item.id}" title="${t('table.actions.mark_read') || 'Mark Read'}"><i class="fas fa-check"></i></button>`
                    : '';
                const deleteBtn2 = `<button class="btn btn-sm btn-danger notif-delete-btn" data-id="${item.id}" title="${t('table.actions.delete')}"><i class="fas fa-trash"></i></button>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td><span class="text-truncate" title="${esc(item.notification_title)}">${esc(notifTitle)}</span></td>
                    <td>${tName}</td>
                    <td><span class="badge badge-info">${esc(rtLabel)}</span></td>
                    <td>${rName}</td>
                    <td>${readBadge}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${item.read_at ? dateFmt(item.read_at) : '—'}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.created_at)}</td>
                    <td><div class="action-btns">${markReadBtn}${deleteBtn2}</div></td>
                </tr>`;
            }
        }
        return '';
    }

    /* ──────────────────────────────────────────
       LOAD DATA
    ────────────────────────────────────────── */
    async function loadData() {
        const tab = state.currentTab;
        const tabDef = TABS[tab];
        const apiUrl = cfg().api[tabDef.apiKey];
        const page = state.currentPage;
        const limit = cfg().itemsPerPage || 25;
        const offset = (page - 1) * limit;

        // Show loading
        setStates({ loading: true, table: false, empty: false, error: false });

        // Build query params
        const params = new URLSearchParams({
            page, limit,
            order_by: 'id', order_dir: 'DESC',
        });

        // Common filters
        const f = state.filters;
        if (f.search) params.set('search', f.search);
        if (f.is_active !== undefined && f.is_active !== '') params.set('is_active', f.is_active);
        if (f.priority) params.set('priority', f.priority);
        if (f.delivery_status) params.set('delivery_status', f.delivery_status);
        if (f.recipient_type) params.set('recipient_type', f.recipient_type);
        if (f.tenant_id) params.set('tenant_id', f.tenant_id);
        if (f.device_type) params.set('device_type', f.device_type);
        if (f.owner_scope) params.set('owner_scope', f.owner_scope);

        // Platform admin tenant override (always wins over filter value)
        const paTid = platformAdmin.getActiveTenantId();
        if (paTid) params.set('tenant_id', paTid);

        try {
            const json = await apiFetch(`${apiUrl}?${params}`);
            const items = json.data?.items || json.items || [];
            const meta = json.data?.meta || json.meta || {};
            const total = meta.total ?? items.length;

            if (items.length === 0) {
                setStates({ loading: false, table: false, empty: true, error: false });
                updateEmptyState(tab);
                getEl('resultsCount').style.display = 'none';
                return;
            }

            // Render headers
            const thead = getEl('tableHead');
            thead.innerHTML = getHeaders(tab).map(h => `<th>${h}</th>`).join('');

            // Render rows
            const tbody = getEl('tableBody');
            tbody.innerHTML = items.map(item => renderRow(tab, item)).join('');

            // Results count
            const countEl = getEl('resultsCount');
            countEl.style.display = 'block';
            getEl('resultsCountText').textContent = `${meta.from ?? 1}–${meta.to ?? items.length} / ${total}`;

            // Pagination
            renderPagination(meta);

            setStates({ loading: false, table: true, empty: false, error: false });

            // Bind row actions
            bindRowActions();

        } catch (err) {
            console.error('[Notifications] Load error:', err);
            setStates({ loading: false, table: false, empty: false, error: true });
            const em = getEl('errorMessage');
            if (em) em.textContent = err.message;
        }
    }

    function updateEmptyState(tab) {
        const tabDef = TABS[tab];
        const et = getEl('emptyTitle');
        const em = getEl('emptyMessage');
        const ea = getEl('emptyAddLabel');
        if (et) et.textContent = t(tabDef.emptyKey);
        if (em) em.textContent = t(tabDef.emptyMsgKey);
        if (ea) ea.textContent = t(tabDef.addFirstKey);
    }

    function setStates({ loading, table, empty, error }) {
        const show = (id, v) => { const el = getEl(id); if (el) el.style.display = v ? '' : 'none'; };
        show('tableLoading', loading);
        show('tableContainer', table);
        show('emptyState', empty);
        show('errorState', error);
    }

    /* ──────────────────────────────────────────
       PAGINATION
    ────────────────────────────────────────── */
    function renderPagination(meta) {
        const pag = getEl('pagination');
        const inf = getEl('paginationInfo');
        if (!pag) return;

        const total = meta.total ?? 0;
        const totalPages = meta.total_pages ?? 1;
        const from = meta.from ?? 0;
        const to = meta.to ?? 0;

        if (inf) inf.textContent = `${from}–${to} / ${total}`;

        if (totalPages <= 1) { pag.innerHTML = ''; return; }

        let html = '';
        if (state.currentPage > 1) {
            html += `<button data-page="${state.currentPage - 1}">‹</button>`;
        }

        const range = 2;
        let start = Math.max(1, state.currentPage - range);
        let end = Math.min(totalPages, state.currentPage + range);
        if (start > 1) html += `<button data-page="1">1</button>${start > 2 ? '<button disabled>…</button>' : ''}`;
        for (let i = start; i <= end; i++) {
            html += `<button data-page="${i}" class="${i === state.currentPage ? 'active' : ''}">${i}</button>`;
        }
        if (end < totalPages) {
            html += `${end < totalPages - 1 ? '<button disabled>…</button>' : ''}<button data-page="${totalPages}">${totalPages}</button>`;
        }
        if (state.currentPage < totalPages) {
            html += `<button data-page="${state.currentPage + 1}">›</button>`;
        }

        pag.innerHTML = html;
        pag.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.currentPage = parseInt(btn.dataset.page, 10);
                loadData();
            });
        });
    }

    /* ──────────────────────────────────────────
       FORM
    ────────────────────────────────────────── */
    function showForm(editData = null) {
        const tab = state.currentTab;
        state.editingId = editData ? editData.id : null;

        const formContainer = getEl('notifFormContainer');
        const formTitle = getEl('notifFormTitle');
        const formId = getEl('formId');
        const formTabInput = getEl('formTab');
        const deleteBtn = getEl('btnDeleteRecord');
        const sendBtn = getEl('btnSendNotification');

        // Show correct form section
        getEls('.notif-form-section').forEach(sec => {
            sec.style.display = sec.dataset.formTab === tab ? '' : 'none';
        });

        if (formTitle) formTitle.textContent = editData ? t('form.edit_title') : t('form.add_title');
        if (formId) formId.value = editData ? editData.id : '';
        if (formTabInput) formTabInput.value = tab;
        if (deleteBtn) deleteBtn.style.display = editData && perm().canDelete ? '' : 'none';

        // Show Send button only for list tab when creating (not editing)
        if (sendBtn) {
            sendBtn.style.display = (tab === 'list' && !editData && perm().canCreate) ? '' : 'none';
        }

        // Show/hide channels and recipient groups
        const channelsGroup = document.querySelector('.notif-channels-group');
        const recipientGroup = document.querySelector('.notif-recipient-group');
        const devicePickerGroup = getEl('devicePickerGroup');
        if (channelsGroup) channelsGroup.style.display = (tab === 'list' && !editData) ? '' : 'none';
        if (recipientGroup) recipientGroup.style.display = (tab === 'list' && !editData) ? '' : 'none';
        if (devicePickerGroup) {
            devicePickerGroup.style.display = 'none'; // hidden by default, shown when push is checked
            const pickerList = getEl('devicePickerList');
            if (pickerList) pickerList.innerHTML = '<p class="device-picker-empty">' + esc(t('send_notification.enter_recipient_first')) + '</p>';
        }

        // Reset form
        const form = getEl('notifForm');
        if (form) {
            // Collect all inputs in current section
            const section = form.querySelector(`.notif-form-section[data-form-tab="${tab}"]`);
            if (section) {
                section.querySelectorAll('input, textarea, select').forEach(el => {
                    if (el.name === 'csrf_token' || el.name === 'id' || el.name === '_tab') return;
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        el.checked = el.defaultChecked;
                    } else {
                        el.value = '';
                    }
                });
            }
        }

        // Populate edit data
        if (editData) {
            Object.keys(editData).forEach(key => {
                const el = form.querySelector(`[name="${key}"]`);
                if (el && editData[key] !== null && editData[key] !== undefined) {
                    if (key === 'expires_at' || key === 'sent_at') {
                        // datetime-local format
                        try {
                            const d = new Date(editData[key]);
                            if (!isNaN(d)) {
                                el.value = d.toISOString().slice(0, 16);
                            }
                        } catch { el.value = editData[key]; }
                    } else {
                        el.value = editData[key];
                    }
                }
            });
        }

        formContainer.style.display = '';
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Trigger lookup hints for any prefilled IDs (edit mode)
        setTimeout(triggerLookupForPrefilled, 50);
    }

    function hideForm() {
        const fc = getEl('notifFormContainer');
        if (fc) fc.style.display = 'none';
        state.editingId = null;
        const form = getEl('notifForm');
        if (form) form.reset();
        // Clear all lookup hints
        document.querySelectorAll('.id-lookup-hint').forEach(h => {
            h.className = 'id-lookup-hint';
            h.textContent = '';
        });
    }

    /* ──────────────────────────────────────────
       COLLECT FORM DATA
    ────────────────────────────────────────── */
    function collectFormData() {
        const tab = state.currentTab;
        const form = getEl('notifForm');
        const section = form.querySelector(`.notif-form-section[data-form-tab="${tab}"]`);
        const data = {};

        if (state.editingId) {
            data.id = state.editingId;
        }

        if (section) {
            section.querySelectorAll('input, textarea, select').forEach(el => {
                if (!el.name || el.name === 'csrf_token' || el.name === '_tab') return;
                // Skip channel checkboxes and send_ prefixed fields (handled separately)
                if (el.name === 'channels[]' || el.name.startsWith('send_')) return;
                const v = el.value.trim();
                data[el.name] = v === '' ? null : v;
            });
        }

        return data;
    }

    /* ──────────────────────────────────────────
       DEVICE PICKER (for targeted push)
    ────────────────────────────────────────── */
    async function loadDevicesForRecipient() {
        const riEl = getEl('fSendRecipientId');
        const pickerList = getEl('devicePickerList');
        if (!riEl || !pickerList) return;

        const recipientId = parseInt(riEl.value, 10);
        if (!recipientId || recipientId <= 0) {
            pickerList.innerHTML = '<p class="device-picker-empty">' + esc(t('send_notification.enter_recipient_first')) + '</p>';
            return;
        }

        pickerList.innerHTML = '<p class="device-picker-loading"><i class="fas fa-spinner fa-spin"></i> ' + esc(t('send_notification.loading_devices')) + '</p>';

        try {
            const result = await apiFetch(cfg().api.devices + '?user_id=' + recipientId);
            const items = result.data?.items || result.items || [];
            if (items.length === 0) {
                pickerList.innerHTML = '<p class="device-picker-empty">' + esc(t('send_notification.no_devices_found')) + '</p>';
                return;
            }

            let html = '';
            items.forEach(dev => {
                const hasFcm = dev.fcm_token && dev.fcm_token !== 'NULL';
                const isActive = String(dev.is_active) === '1';
                const disabledAttr = (!hasFcm || !isActive) ? ' disabled' : '';
                const statusIcon = isActive ? (hasFcm ? '🟢' : '🟡') : '🔴';
                const tokenHint = hasFcm ? '' : ' — ' + esc(t('send_notification.no_fcm_token'));
                const inactiveHint = !isActive ? ' — ' + esc(t('send_notification.device_inactive')) : '';
                html += '<label class="device-picker-item' + (disabledAttr ? ' disabled' : '') + '">'
                    + '<input type="checkbox" name="device_ids[]" value="' + esc(dev.id) + '"' + disabledAttr + '>'
                    + '<span class="device-info">'
                    + statusIcon + ' '
                    + '<strong>' + esc(dev.device_name || dev.device_type || 'Unknown') + '</strong>'
                    + ' <span class="device-type-badge">' + esc(dev.device_type || '') + '</span>'
                    + tokenHint + inactiveHint
                    + (dev.last_seen_at ? ' <span class="device-last-seen">' + dateFmt(dev.last_seen_at) + '</span>' : '')
                    + '</span>'
                    + '</label>';
            });
            pickerList.innerHTML = html;
        } catch (err) {
            pickerList.innerHTML = '<p class="device-picker-empty" style="color:var(--danger)">' + esc(err.message || t('send_notification.load_devices_error')) + '</p>';
        }
    }

    function toggleDevicePicker() {
        const pushCb = getEl('chkPushChannel');
        const group = getEl('devicePickerGroup');
        if (!pushCb || !group) return;
        group.style.display = pushCb.checked ? '' : 'none';
        if (pushCb.checked) {
            loadDevicesForRecipient();
        }
    }

    /* Collect send notification data (channels + recipient from list tab) */
    function collectSendData() {
        const form = getEl('notifForm');
        const section = form.querySelector(`.notif-form-section[data-form-tab="list"]`);
        const data = {};

        // Collect standard fields
        if (section) {
            section.querySelectorAll('input, textarea, select').forEach(el => {
                if (!el.name || el.name === 'csrf_token' || el.name === '_tab'
                    || el.name === 'channels[]' || el.name === 'device_ids[]'
                    || el.name.startsWith('send_')) return;
                const v = el.value.trim();
                data[el.name] = v === '' ? null : v;
            });
        }

        // Collect channels
        const channels = [];
        form.querySelectorAll('input[name="channels[]"]:checked').forEach(cb => {
            channels.push(cb.value);
        });
        data.channels = channels.length > 0 ? channels : ['database'];

        // Collect selected device IDs for targeted push
        if (channels.includes('push')) {
            const deviceIds = [];
            form.querySelectorAll('input[name="device_ids[]"]:checked').forEach(cb => {
                deviceIds.push(parseInt(cb.value, 10));
            });
            if (deviceIds.length > 0) {
                data.device_ids = deviceIds;
            }
        }

        // Collect recipient
        const rtEl = getEl('fSendRecipientType');
        const riEl = getEl('fSendRecipientId');
        data.recipient_type = rtEl ? rtEl.value : 'user';
        data.recipient_id = riEl ? parseInt(riEl.value, 10) || null : null;

        // Map notification_type_id to type_code if we have it cached
        if (data.notification_type_id) {
            const typeObj = state.notifTypes.find(t => String(t.id) === String(data.notification_type_id));
            if (typeObj) data.type_code = typeObj.code;
        }

        return data;
    }

    /* ──────────────────────────────────────────
       SAVE
    ────────────────────────────────────────── */
    async function saveRecord(data) {
        const tab = state.currentTab;
        const apiUrl = cfg().api[TABS[tab].apiKey];
        const isEdit = !!data.id;
        const method = isEdit ? 'PUT' : 'POST';

        const submitBtn = getEl('btnSubmitForm');
        if (submitBtn) {
            submitBtn.disabled = true;
            const span = submitBtn.querySelector('span');
            if (span) span.textContent = t(isEdit ? 'form.buttons.updating' : 'form.buttons.saving');
        }

        try {
            await apiFetch(apiUrl, {
                method,
                body: JSON.stringify(data),
            });
            showToast(t(isEdit ? 'messages.success.updated' : 'messages.success.created'), 'success');
            hideForm();
            await loadData();
        } catch (err) {
            showToast(err.message || t('messages.error.save_failed'), 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                const span = submitBtn.querySelector('span');
                if (span) span.textContent = t('form.buttons.save');
            }
        }
    }

    /* ──────────────────────────────────────────
       DELETE
    ────────────────────────────────────────── */
    async function deleteRecord(id) {
        if (!confirmDialog(t('table.actions.confirm_delete'))) return;
        const tab = state.currentTab;
        const apiUrl = cfg().api[TABS[tab].apiKey];
        try {
            await apiFetch(apiUrl, {
                method: 'DELETE',
                body: JSON.stringify({ id }),
            });
            showToast(t('messages.success.deleted'), 'success');
            await loadData();
        } catch (err) {
            showToast(err.message || t('messages.error.delete_failed'), 'error');
        }
    }

    /* ──────────────────────────────────────────
       COUNTER SPECIAL ACTIONS
    ────────────────────────────────────────── */
    async function incrementCounter(id) {
        try {
            await apiFetch(cfg().api.counters + '/increment', {
                method: 'POST',
                body: JSON.stringify({ id, amount: 1 }),
            });
            showToast('Counter incremented', 'success');
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function resetCounter(id) {
        if (!confirmDialog('Reset unread count to 0?')) return;
        try {
            await apiFetch(cfg().api.counters + '/reset', {
                method: 'POST',
                body: JSON.stringify({ id }),
            });
            showToast('Counter reset', 'success');
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function markRecipientRead(id) {
        try {
            await apiFetch(cfg().api.recipients + '/mark-read', {
                method: 'POST',
                body: JSON.stringify({ id }),
            });
            showToast(t('recipients.marked_read') || 'Marked as read', 'success');
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    /* ──────────────────────────────────────────
       SEND NOTIFICATION (multi-channel via helper)
    ────────────────────────────────────────── */
    async function sendNotification() {
        const data = collectSendData();

        if (!data.recipient_id || data.recipient_id <= 0) {
            showToast(t('send_notification.recipient_id') + ' is required', 'error');
            return;
        }
        if (!data.title) {
            showToast(t('form.fields.title.label') + ' is required', 'error');
            return;
        }
        if (!data.message) {
            showToast(t('form.fields.message.label') + ' is required', 'error');
            return;
        }

        if (!confirmDialog(t('send_notification.confirm'))) return;

        const sendBtn = getEl('btnSendNotification');
        if (sendBtn) {
            sendBtn.disabled = true;
            const span = sendBtn.querySelector('span');
            if (span) span.textContent = t('form.buttons.sending');
        }

        try {
            const result = await apiFetch(cfg().api.send, {
                method: 'POST',
                body: JSON.stringify(data),
            });
            showToast(t('messages.success.sent'), 'success');
            hideForm();
            await loadData();
        } catch (err) {
            showToast(err.message || t('messages.error.send_failed'), 'error');
        } finally {
            if (sendBtn) {
                sendBtn.disabled = false;
                const span = sendBtn.querySelector('span');
                if (span) span.textContent = t('form.buttons.send');
            }
        }
    }

    /* ──────────────────────────────────────────
       ROW ACTIONS
    ────────────────────────────────────────── */
    function bindRowActions() {
        getEls('.notif-edit-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const tab = state.currentTab;
                const apiUrl = cfg().api[TABS[tab].apiKey];
                try {
                    const json = await apiFetch(`${apiUrl}?id=${id}`);
                    const item = json.data || json;
                    await populateDropdowns();
                    showForm(item);
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        getEls('.notif-delete-btn').forEach(btn => {
            btn.addEventListener('click', () => deleteRecord(parseInt(btn.dataset.id, 10)));
        });

        getEls('.notif-mark-read-btn').forEach(btn => {
            btn.addEventListener('click', () => markRecipientRead(parseInt(btn.dataset.id, 10)));
        });

        getEls('.notif-increment-btn').forEach(btn => {
            btn.addEventListener('click', () => incrementCounter(parseInt(btn.dataset.id, 10)));
        });

        getEls('.notif-reset-btn').forEach(btn => {
            btn.addEventListener('click', () => resetCounter(parseInt(btn.dataset.id, 10)));
        });
    }

    /* ──────────────────────────────────────────
       POPULATE DROPDOWNS (types select, channel select)
    ────────────────────────────────────────── */
    async function populateDropdowns() {
        // Notification types
        if (!state.notifTypes.length) {
            try {
                const json = await apiFetch(cfg().api.types + '?limit=1000&is_active=1');
                state.notifTypes = json.data?.items || json.items || [];
            } catch { state.notifTypes = []; }
        }
        const tSelect = getEl('fNotifTypeId');
        if (tSelect) {
            const current = tSelect.value;
            tSelect.innerHTML = `<option value="">-- ${t('form.fields.notification_type_id.label')} --</option>`;
            state.notifTypes.forEach(ty => {
                const opt = document.createElement('option');
                opt.value = ty.id;
                opt.textContent = `${ty.name} (${ty.code})`;
                if (String(ty.id) === String(current)) opt.selected = true;
                tSelect.appendChild(opt);
            });
        }

        // Channels
        if (!state.channels.length) {
            try {
                const json = await apiFetch(cfg().api.channels + '?limit=1000&is_active=1');
                state.channels = json.data?.items || json.items || [];
            } catch { state.channels = []; }
        }
        const chSelect = getEl('fChannelId');
        if (chSelect) {
            const current = chSelect.value;
            chSelect.innerHTML = `<option value="">-- ${t('form.fields.channel_id.label')} --</option>`;
            state.channels.forEach(ch => {
                const opt = document.createElement('option');
                opt.value = ch.id;
                opt.textContent = `${ch.name} (${ch.code})`;
                if (String(ch.id) === String(current)) opt.selected = true;
                chSelect.appendChild(opt);
            });
        }
    }

    /* ──────────────────────────────────────────
       ID LOOKUP HINTS
    ────────────────────────────────────────── */
    const _lookupTimers = {};

    function setHint(inputEl, state, text) {
        const hint = document.querySelector(`.id-lookup-hint[data-for="${inputEl.id}"]`);
        if (!hint) return;
        hint.className = 'id-lookup-hint' + (state ? ' ' + state : '');
        hint.textContent = text || '';
    }

    async function doLookup(inputEl, lookupType) {
        const id = parseInt(inputEl.value, 10);
        if (!id || id <= 0) {
            setHint(inputEl, '', '');
            return;
        }

        setHint(inputEl, 'loading', '');

        const apiBase = cfg().api;
        const tenantId = cfg().tenantId || window.APP_CONFIG?.TENANT_ID || 1;

        try {
            let name = null;

            if (lookupType === 'tenant') {
                // GET /api/tenants/{id}  – path-segment style
                const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/tenants/${id}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.name || null;
            }

            else if (lookupType === 'entity') {
                // GET /api/entities?id=X&tenant_id=X
                const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/entities?id=${id}&tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.store_name || null;
            }

            else if (lookupType === 'notification') {
                // Use our own notification API
                const json = await fetch(`${apiBase.list}?id=${id}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.title || null;
            }

            else if (lookupType === 'recipient') {
                // Depends on the recipient_type select value
                const rtSelect = document.getElementById('fRecipientType');
                const rType = rtSelect?.value || 'user';

                if (rType === 'user') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/user?id=${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.username || row?.email || null;
                } else if (rType === 'entity') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/entities?id=${id}&tenant_id=${tenantId}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.store_name || null;
                } else if (rType === 'tenant') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/tenants/${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.name || null;
                }
            }

            else if (lookupType === 'recipient_send') {
                // Same as recipient but uses fSendRecipientType
                const rtSelect = document.getElementById('fSendRecipientType');
                const rType = rtSelect?.value || 'user';

                if (rType === 'user') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/user?id=${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.username || row?.email || null;
                } else if (rType === 'entity') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/entities?id=${id}&tenant_id=${tenantId}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.store_name || null;
                } else if (rType === 'tenant') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/tenants/${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.name || null;
                }
            }

            else if (lookupType === 'device_user') {
                const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/user?id=${id}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.username || row?.email || null;
            }

            if (name) {
                setHint(inputEl, 'found', name);
            } else {
                setHint(inputEl, 'not-found', 'Not found');
            }
        } catch (e) {
            setHint(inputEl, 'not-found', 'Lookup error');
        }
    }

    function initLookupHints() {
        const form = document.getElementById('notifForm');
        if (!form) return;

        form.querySelectorAll('[data-lookup]').forEach(input => {
            const lookupType = input.dataset.lookup;

            // Fire on input with 600ms debounce
            input.addEventListener('input', () => {
                clearTimeout(_lookupTimers[input.id]);
                setHint(input, '', '');
                if (!input.value) return;
                _lookupTimers[input.id] = setTimeout(() => doLookup(input, lookupType), 600);
            });

            // Also fire on blur immediately
            input.addEventListener('blur', () => {
                clearTimeout(_lookupTimers[input.id]);
                if (input.value) doLookup(input, lookupType);
            });
        });

        // Re-trigger recipient lookup when recipient_type changes
        const rtSelect = document.getElementById('fRecipientType');
        if (rtSelect) {
            rtSelect.addEventListener('change', () => {
                const recipientInput = document.getElementById('fRecipientId');
                if (recipientInput?.value) doLookup(recipientInput, 'recipient');
            });
        }

        // Re-trigger send recipient lookup when send_recipient_type changes
        const srtSelect = document.getElementById('fSendRecipientType');
        if (srtSelect) {
            srtSelect.addEventListener('change', () => {
                const recipientInput = document.getElementById('fSendRecipientId');
                if (recipientInput?.value) doLookup(recipientInput, 'recipient_send');
            });
        }
    }

    /* Helper to fire lookup hints for all prefilled ID inputs (edit mode) */
    function triggerLookupForPrefilled() {
        const form = document.getElementById('notifForm');
        if (!form) return;
        form.querySelectorAll('[data-lookup]').forEach(input => {
            if (input.value && parseInt(input.value, 10) > 0) {
                doLookup(input, input.dataset.lookup);
            }
        });
    }

    function switchTab(tab) {
        state.currentTab = tab;
        state.currentPage = 1;
        state.filters = {};

        // Update tab buttons
        getEls('.notif-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        // Update Add button label
        const addLabel = getEl('btnAddLabel');
        const addBtn = getEl('btnAddRecord');
        if (tab === 'bulk_send' || TABS[tab]?.readOnly) {
            if (addBtn) addBtn.style.display = 'none';
        } else {
            if (addBtn) addBtn.style.display = '';
            if (addLabel) addLabel.textContent = t(TABS[tab]?.addKey || 'types.add_new');
        }

        // Show/hide bulk send panel vs regular content
        const bulkPanel = getEl('bulkSendPanel');
        const filterCard = document.querySelector('.filter-card');
        const tableCard = document.querySelector('.table-card');
        const resultsCount = getEl('resultsCount');

        if (tab === 'bulk_send') {
            if (bulkPanel) bulkPanel.style.display = '';
            if (filterCard) filterCard.style.display = 'none';
            if (tableCard) tableCard.style.display = 'none';
            if (resultsCount) resultsCount.style.display = 'none';
            hideForm();
            initBulkSendTab();
            return;
        } else {
            if (bulkPanel) bulkPanel.style.display = 'none';
            if (filterCard) filterCard.style.display = '';
            if (tableCard) tableCard.style.display = '';
        }

        // Update filters visibility
        const tabDef = TABS[tab];
        if (!tabDef) return;
        const show = (id, v) => { const el = getEl(id); if (el) el.style.display = v ? '' : 'none'; };
        show('filterStatusGroup', tabDef.showStatus);
        show('filterPriorityGroup', tabDef.showPriority);
        show('filterDeliveryStatusGroup', tabDef.showDeliveryStatus);
        show('filterRecipientTypeGroup', tabDef.showRecipientType);
        show('filterTenantGroup', tabDef.showTenant && perm().isSuperAdmin);
        show('filterDeviceTypeGroup', tabDef.showDeviceType);
        show('filterOwnerScopeGroup', tab === 'types' && perm().isPlatformAdmin);

        // Reset filter inputs
        const si = getEl('searchInput');
        if (si) si.value = '';
        const st = getEl('statusFilter');
        if (st) st.value = '';
        const pr = getEl('priorityFilter');
        if (pr) pr.value = '';
        const ds = getEl('deliveryStatusFilter');
        if (ds) ds.value = '';
        const rt = getEl('recipientTypeFilter');
        if (rt) rt.value = '';
        const dt = getEl('deviceTypeFilter');
        if (dt) dt.value = '';

        hideForm();
        loadData();
    }

    /* ──────────────────────────────────────────
       EVENTS
    ────────────────────────────────────────── */
    function bindEvents() {
        // Tab clicks
        getEls('.notif-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Add button
        const addBtn = getEl('btnAddRecord');
        if (addBtn) {
            addBtn.addEventListener('click', async () => {
                await populateDropdowns();
                showForm(null);
            });
        }

        // Close / cancel form
        const closeBtn = getEl('btnCloseForm');
        const cancelBtn = getEl('btnCancelForm');
        if (closeBtn) closeBtn.addEventListener('click', hideForm);
        if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

        // Delete (from form)
        const deleteBtn = getEl('btnDeleteRecord');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                if (state.editingId) deleteRecord(state.editingId);
            });
        }

        // Send Notification button
        const sendNotifBtn = getEl('btnSendNotification');
        if (sendNotifBtn) {
            sendNotifBtn.addEventListener('click', () => sendNotification());
        }

        // Push channel checkbox — toggle device picker
        const pushCb = getEl('chkPushChannel');
        if (pushCb) {
            pushCb.addEventListener('change', toggleDevicePicker);
        }

        // Load devices button
        const loadDevBtn = getEl('btnLoadDevices');
        if (loadDevBtn) {
            loadDevBtn.addEventListener('click', () => loadDevicesForRecipient());
        }

        // Auto-load devices when recipient ID changes (debounced)
        const recipientIdEl = getEl('fSendRecipientId');
        if (recipientIdEl) {
            let devTimeout;
            recipientIdEl.addEventListener('input', () => {
                clearTimeout(devTimeout);
                devTimeout = setTimeout(() => {
                    const pushEl = getEl('chkPushChannel');
                    if (pushEl && pushEl.checked) loadDevicesForRecipient();
                }, 800);
            });
        }

        // Form submit
        const form = getEl('notifForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = collectFormData();
                await saveRecord(data);
            });
        }

        // Filters
        const applyBtn = getEl('btnApplyFilters');
        const resetBtn = getEl('btnResetFilters');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                state.filters = {};
                state.currentPage = 1;
                const si = getEl('searchInput');
                if (si?.value) state.filters.search = si.value.trim();
                const sf = getEl('statusFilter');
                if (sf?.value !== '') state.filters.is_active = sf.value;
                const pf = getEl('priorityFilter');
                if (pf?.value) state.filters.priority = pf.value;
                const df = getEl('deliveryStatusFilter');
                if (df?.value) state.filters.delivery_status = df.value;
                const rf = getEl('recipientTypeFilter');
                if (rf?.value) state.filters.recipient_type = rf.value;
                const tf = getEl('tenantFilter');
                if (tf?.value) state.filters.tenant_id = tf.value;
                const dtf = getEl('deviceTypeFilter');
                if (dtf?.value) state.filters.device_type = dtf.value;
                const osf = getEl('ownerScopeFilter');
                if (osf?.value) state.filters.owner_scope = osf.value;
                loadData();
            });
        }
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                state.filters = {};
                state.currentPage = 1;
                ['searchInput', 'statusFilter', 'priorityFilter', 'deliveryStatusFilter', 'recipientTypeFilter', 'deviceTypeFilter', 'ownerScopeFilter'].forEach(id => {
                    const el = getEl(id);
                    if (el) el.value = '';
                });
                loadData();
            });
        }

        // Retry button
        const retryBtn = getEl('btnRetry');
        if (retryBtn) retryBtn.addEventListener('click', loadData);

        // Enter key in search
        const si = getEl('searchInput');
        if (si) {
            si.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') applyBtn?.click();
            });
        }
    }

    /* ──────────────────────────────────────────
       BULK SEND
    ────────────────────────────────────────── */
    let _bulkState = {
        selectedUserIds: new Set(),
        usersPage: 1,
        usersData: [],
        usersMeta: {},
    };

    function initBulkSendTab() {
        // Populate type code dropdown
        const typeSelect = getEl('bsTypeCode');
        if (typeSelect && state.notifTypes.length) {
            typeSelect.innerHTML = '<option value="general">General</option>';
            state.notifTypes.forEach(ty => {
                const opt = document.createElement('option');
                opt.value = ty.code;
                opt.textContent = `${ty.name} (${ty.code})`;
                typeSelect.appendChild(opt);
            });
        } else if (typeSelect && !state.notifTypes.length) {
            // Load types first
            populateDropdowns().then(() => {
                typeSelect.innerHTML = '<option value="general">General</option>';
                state.notifTypes.forEach(ty => {
                    const opt = document.createElement('option');
                    opt.value = ty.code;
                    opt.textContent = `${ty.name} (${ty.code})`;
                    typeSelect.appendChild(opt);
                });
            });
        }
    }

    function updateBulkSendCount() {
        const countEl = getEl('bsSelectedCount');
        const sendCountEl = getEl('bsSendCount');
        const count = _bulkState.selectedUserIds.size;
        if (countEl) countEl.textContent = count + ' ' + (t('bulk_send.selected') || 'selected');
        if (sendCountEl) sendCountEl.textContent = count;
    }

    async function loadBulkUsers(page = 1) {
        _bulkState.usersPage = page;
        const listEl = getEl('bsUserList');
        const pagEl = getEl('bsUserPagination');
        if (!listEl) return;

        listEl.innerHTML = '<p class="bulk-send-loading"><i class="fas fa-spinner fa-spin"></i> ' + (t('bulk_send.loading_users') || 'Loading users...') + '</p>';

        const params = new URLSearchParams({ page, per_page: 50 });
        const search = getEl('bsFilterSearch')?.value?.trim();
        if (search) params.set('search', search);
        const roleId = getEl('bsFilterRole')?.value;
        if (roleId) params.set('role_id', roleId);
        const isActive = getEl('bsFilterActive')?.value;
        if (isActive !== '' && isActive !== undefined) params.set('is_active', isActive);
        const deviceType = getEl('bsFilterDeviceType')?.value;
        if (deviceType) params.set('device_type', deviceType);

        try {
            const apiBase = cfg().api.types.replace('/notification_types', '');
            const json = await apiFetch(`${apiBase}/users?${params}`);
            const items = json.data?.items || json.items || [];
            const meta = json.data?.meta || json.meta || {};
            _bulkState.usersData = items;
            _bulkState.usersMeta = meta;

            if (items.length === 0) {
                listEl.innerHTML = '<p class="bulk-send-empty">' + (t('bulk_send.no_users_found') || 'No users found with the current filters.') + '</p>';
                if (pagEl) pagEl.style.display = 'none';
                return;
            }

            let html = '<table class="data-table bulk-send-table"><thead><tr>'
                + '<th><input type="checkbox" id="bsCheckAll"></th>'
                + '<th>ID</th><th>' + (t('table.headers.username') || 'Username') + '</th>'
                + '<th>' + (t('table.headers.email') || 'Email') + '</th>'
                + '<th>' + (t('table.headers.status') || 'Status') + '</th>'
                + '</tr></thead><tbody>';

            items.forEach(u => {
                const checked = _bulkState.selectedUserIds.has(u.id) ? ' checked' : '';
                const statusBadge = String(u.is_active) === '1'
                    ? '<span class="badge badge-success">' + (t('table.status.active') || 'Active') + '</span>'
                    : '<span class="badge badge-danger">' + (t('table.status.inactive') || 'Inactive') + '</span>';
                html += '<tr>'
                    + '<td><input type="checkbox" class="bs-user-check" value="' + esc(u.id) + '"' + checked + '></td>'
                    + '<td>' + esc(u.id) + '</td>'
                    + '<td>' + esc(u.username || '—') + '</td>'
                    + '<td>' + esc(u.email || '—') + '</td>'
                    + '<td>' + statusBadge + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            listEl.innerHTML = html;

            // Bind checkboxes
            listEl.querySelectorAll('.bs-user-check').forEach(cb => {
                cb.addEventListener('change', () => {
                    const uid = parseInt(cb.value, 10);
                    if (cb.checked) {
                        _bulkState.selectedUserIds.add(uid);
                    } else {
                        _bulkState.selectedUserIds.delete(uid);
                    }
                    updateBulkSendCount();
                });
            });

            // Check All checkbox
            const checkAll = getEl('bsCheckAll');
            if (checkAll) {
                checkAll.checked = items.every(u => _bulkState.selectedUserIds.has(u.id));
                checkAll.addEventListener('change', () => {
                    items.forEach(u => {
                        if (checkAll.checked) {
                            _bulkState.selectedUserIds.add(u.id);
                        } else {
                            _bulkState.selectedUserIds.delete(u.id);
                        }
                    });
                    listEl.querySelectorAll('.bs-user-check').forEach(cb => {
                        cb.checked = checkAll.checked;
                    });
                    updateBulkSendCount();
                });
            }

            // Render pagination
            if (pagEl) {
                const totalPages = meta.total_pages || Math.ceil((meta.total || items.length) / 50);
                if (totalPages <= 1) {
                    pagEl.style.display = 'none';
                } else {
                    pagEl.style.display = '';
                    let pagHtml = '';
                    if (page > 1) pagHtml += '<button class="btn btn-sm btn-secondary bs-page-btn" data-page="' + (page - 1) + '">‹</button> ';
                    const start = Math.max(1, page - 2);
                    const end = Math.min(totalPages, page + 2);
                    for (let i = start; i <= end; i++) {
                        pagHtml += '<button class="btn btn-sm ' + (i === page ? 'btn-primary' : 'btn-secondary') + ' bs-page-btn" data-page="' + i + '">' + i + '</button> ';
                    }
                    if (page < totalPages) pagHtml += '<button class="btn btn-sm btn-secondary bs-page-btn" data-page="' + (page + 1) + '">›</button>';
                    pagHtml += ' <span style="color:var(--text-secondary, #94a3b8);font-size:0.85rem;margin-inline-start:8px;">' + (meta.total || items.length) + ' ' + (t('bulk_send.total_users') || 'total') + '</span>';
                    pagEl.innerHTML = pagHtml;
                    pagEl.querySelectorAll('.bs-page-btn').forEach(btn => {
                        btn.addEventListener('click', () => loadBulkUsers(parseInt(btn.dataset.page, 10)));
                    });
                }
            }

            updateBulkSendCount();
        } catch (err) {
            listEl.innerHTML = '<p class="bulk-send-empty" style="color:var(--danger, #ef4444);">' + esc(err.message || 'Failed to load users') + '</p>';
        }
    }

    async function executeBulkSend() {
        const userIds = Array.from(_bulkState.selectedUserIds);
        if (userIds.length === 0) {
            showToast(t('bulk_send.no_recipients_selected') || 'Please select at least one recipient', 'error');
            return;
        }

        const title = getEl('bsTitle')?.value?.trim();
        const message = getEl('bsMessage')?.value?.trim();
        if (!title) { showToast((t('form.fields.title.label') || 'Title') + ' is required', 'error'); return; }
        if (!message) { showToast((t('form.fields.message.label') || 'Message') + ' is required', 'error'); return; }

        const channels = [];
        document.querySelectorAll('input[name="bs_channels[]"]:checked').forEach(cb => channels.push(cb.value));
        if (channels.length === 0) channels.push('database');

        const confirmMsg = (t('bulk_send.confirm_send') || 'Send notification to {count} recipients?').replace('{count}', userIds.length);
        if (!confirmDialog(confirmMsg)) return;

        const sendBtn = getEl('btnBulkSend');
        let originalBtnHtml = '';
        if (sendBtn) {
            originalBtnHtml = sendBtn.innerHTML;
            sendBtn.disabled = true;
            const span = sendBtn.querySelector('span');
            if (span) span.textContent = t('form.buttons.sending') || 'Sending...';
        }

        try {
            const payload = {
                user_ids: userIds,
                tenant_id: platformAdmin.getActiveTenantId() || parseInt(getEl('bsTenantId')?.value, 10) || 1,
                type_code: getEl('bsTypeCode')?.value || 'general',
                title: title,
                message: message,
                channels: channels,
                priority: getEl('bsPriority')?.value || 'normal',
            };

            const result = await apiFetch(cfg().api.sendBulk, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            const data = result.data || result;
            const successCount = data.success_count ?? 0;
            const failCount = data.fail_count ?? 0;
            const total = data.total ?? userIds.length;

            showToast(
                (t('bulk_send.send_complete') || 'Sent: {success}/{total}, Failed: {fail}')
                    .replace('{success}', successCount)
                    .replace('{total}', total)
                    .replace('{fail}', failCount),
                failCount > 0 ? 'error' : 'success'
            );

            // Clear selections
            _bulkState.selectedUserIds.clear();
            updateBulkSendCount();
            if (_bulkState.usersData.length) loadBulkUsers(_bulkState.usersPage);
        } catch (err) {
            showToast(err.message || (t('bulk_send.send_failed') || 'Bulk send failed'), 'error');
        } finally {
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalBtnHtml;
                const countSpan = getEl('bsSendCount');
                if (countSpan) countSpan.textContent = _bulkState.selectedUserIds.size;
            }
        }
    }

    function bindBulkSendEvents() {
        const loadBtn = getEl('btnBsLoadUsers');
        if (loadBtn) loadBtn.addEventListener('click', () => {
            _bulkState.usersPage = 1;
            loadBulkUsers(1);
        });

        const selectAllBtn = getEl('btnBsSelectAll');
        if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
            // Select all on current page
            document.querySelectorAll('.bs-user-check').forEach(cb => {
                cb.checked = true;
                _bulkState.selectedUserIds.add(parseInt(cb.value, 10));
            });
            const checkAll = getEl('bsCheckAll');
            if (checkAll) checkAll.checked = true;
            updateBulkSendCount();
        });

        const deselectAllBtn = getEl('btnBsDeselectAll');
        if (deselectAllBtn) deselectAllBtn.addEventListener('click', () => {
            _bulkState.selectedUserIds.clear();
            document.querySelectorAll('.bs-user-check').forEach(cb => { cb.checked = false; });
            const checkAll = getEl('bsCheckAll');
            if (checkAll) checkAll.checked = false;
            updateBulkSendCount();
        });

        const bulkSendBtn = getEl('btnBulkSend');
        if (bulkSendBtn) bulkSendBtn.addEventListener('click', executeBulkSend);

        // Enter key on search
        const bsSearch = getEl('bsFilterSearch');
        if (bsSearch) bsSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') loadBtn?.click();
        });
    }

    /* ──────────────────────────────────────────
       PLATFORM ADMIN MODULE
    ────────────────────────────────────────── */
    const platformAdmin = (function () {
        let _activeTenantId   = null;
        let _activeTenantName = '';

        function isActive() {
            return cfg().isPlatformAdmin === true;
        }

        /** Returns query-string fragment `&tenant_id=X` when a tenant is active, otherwise `''`. */
        function tenantParam() {
            return _activeTenantId ? `&tenant_id=${_activeTenantId}` : '';
        }

        function getActiveTenantId() {
            return _activeTenantId;
        }

        function applyTenant(id, name) {
            _activeTenantId   = id;
            _activeTenantName = name || `Tenant #${id}`;

            const banner = getEl('notifPaActiveTenantBanner');
            const label  = getEl('notifPaActiveTenantLabel');
            if (banner) banner.style.display = '';
            if (label)  label.textContent    = `⚠️ Acting on behalf of: ${_activeTenantName} (ID: ${_activeTenantId})`;

            // Sync tenant_id fields inside forms
            ['fTenantId', 'fCtrTenantId', 'bsTenantId'].forEach(id => {
                const el = getEl(id);
                if (el) el.value = _activeTenantId;
            });

            // Reload data with the new tenant scope
            state.currentPage = 1;
            state.filters = {};
            if (state.currentTab !== 'bulk_send') loadData();
        }

        function clearTenant() {
            _activeTenantId   = null;
            _activeTenantName = '';

            const banner = getEl('notifPaActiveTenantBanner');
            if (banner) banner.style.display = 'none';

            // Restore tenant_id fields to original config
            const originalTid = cfg().tenantId || window.APP_CONFIG?.TENANT_ID || '';
            ['fTenantId', 'fCtrTenantId', 'bsTenantId'].forEach(id => {
                const el = getEl(id);
                if (el) el.value = originalTid;
            });

            state.currentPage = 1;
            state.filters = {};
            if (state.currentTab !== 'bulk_send') loadData();
        }

        async function loadTenants() {
            const select = getEl('notifPaTenantSelect');
            if (!select) return;
            try {
                const apiBase = cfg().api.types.replace('/notification_types', '');
                const json = await apiFetch(`${apiBase}/tenants?limit=500&is_active=1`);
                const items = json.data?.items || json.items || [];
                select.innerHTML = `<option value="">-- Select tenant --</option>`;
                items.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = `${t.name} (#${t.id})`;
                    select.appendChild(opt);
                });
            } catch (e) {
                console.warn('[Notifications PA] Could not load tenants:', e.message);
            }
        }

        async function searchUsers(query) {
            const resultsEl = getEl('notifPaUserSearchResults');
            if (!resultsEl) return;

            if (!query || query.trim() === '') {
                resultsEl.style.display = 'none';
                return;
            }

            resultsEl.innerHTML = '<div style="padding:6px;color:var(--text-secondary,#94a3b8)"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
            resultsEl.style.display = '';

            try {
                const apiBase = cfg().api.types.replace('/notification_types', '');
                const params = new URLSearchParams({ limit: 10 });
                if (/^\d+$/.test(query.trim())) {
                    params.set('id', query.trim());
                } else {
                    params.set('search', query.trim());
                }
                const json = await apiFetch(`${apiBase}/users?${params}`);
                const items = json.data?.items || json.items || [];

                if (!items.length) {
                    resultsEl.innerHTML = '<div style="padding:6px;color:var(--text-secondary,#94a3b8)">No users found</div>';
                    return;
                }

                resultsEl.innerHTML = items.map(u => `
                    <div class="pa-search-result-item" data-user-id="${esc(u.id)}" style="padding:6px 8px;cursor:pointer;border-bottom:1px solid var(--border,#e2e8f0)">
                        <strong>${esc(u.username || u.email || u.id)}</strong>
                        <small style="color:var(--text-secondary,#94a3b8)"> #${esc(u.id)} — ${esc(u.email || '')}</small>
                    </div>`).join('');

                resultsEl.querySelectorAll('.pa-search-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const userId = parseInt(item.dataset.userId, 10);
                        resultsEl.style.display = 'none';
                        showToast(`User #${userId} selected`, 'success');
                    });
                });
            } catch (e) {
                resultsEl.innerHTML = `<div style="padding:6px;color:var(--danger,#ef4444)">${esc(e.message)}</div>`;
            }
        }

        function bindEvents() {
            if (!isActive()) return;

            // Load tenants on init
            loadTenants();

            // Enable Apply button when a tenant is selected
            const tenantSelect = getEl('notifPaTenantSelect');
            const applyBtn     = getEl('notifPaApplyTenantBtn');
            if (tenantSelect && applyBtn) {
                tenantSelect.addEventListener('change', () => {
                    applyBtn.disabled = !tenantSelect.value;
                });
            }

            // Apply button
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    const sel    = getEl('notifPaTenantSelect');
                    const tid    = sel ? parseInt(sel.value, 10) : 0;
                    const tname  = sel?.selectedOptions?.[0]?.textContent || '';
                    if (!tid) return;
                    applyTenant(tid, tname);
                });
            }

            // Clear button
            const clearBtn = getEl('notifPaClearTenantBtn');
            if (clearBtn) clearBtn.addEventListener('click', clearTenant);

            // User search
            const searchInput = getEl('notifPaUserSearch');
            const searchBtn   = getEl('notifPaUserSearchBtn');
            if (searchInput) {
                let searchTimer;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => searchUsers(searchInput.value), 500);
                });
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') { clearTimeout(searchTimer); searchUsers(searchInput.value); }
                });
            }
            if (searchBtn) {
                searchBtn.addEventListener('click', () => {
                    const q = getEl('notifPaUserSearch')?.value || '';
                    searchUsers(q);
                });
            }
        }

        return { isActive, tenantParam, getActiveTenantId, applyTenant, clearTenant, bindEvents };
    })();

    /* ──────────────────────────────────────────
       PUBLIC API
    ────────────────────────────────────────── */
    const Notifications = {
        async init() {
            console.log('[Notifications] Initializing...');
            bindEvents();
            bindBulkSendEvents();
            initLookupHints();
            platformAdmin.bindEvents();
            switchTab('types');
        },
        add() {
            populateDropdowns().then(() => showForm(null));
        },
        reload() {
            loadData();
        },
    };

    window.Notifications = Notifications;
    console.log('[Notifications] Module registered.');

})(window);