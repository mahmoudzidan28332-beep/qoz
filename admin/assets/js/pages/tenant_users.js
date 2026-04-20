/**
 * Tenant Users Management
 * Version: 4.1.0 — Guide-compliant, full i18n support
 */
(function () {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/tenant_users';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;

    function reloadConfig() {
        CFG        = window.TENANT_USERS_CONFIG || {};
        CSRF       = CFG.csrfToken || window.APP_CONFIG?.CSRF_TOKEN || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    const state = {
        page: 1,
        perPage: 10,
        filters: {},
        permissions: {},
        translations: {},
        meta: null,
        language: ''
    };

    let el = {};

    // ════════════════════════════════════════════════════════════
    // 2. HELPERS — t(), esc(), notify()
    // ════════════════════════════════════════════════════════════

    function t(key, fallback) {
        // 1) inline strings from PAGE_CONFIG
        if (STRINGS && STRINGS[key]) return STRINGS[key];
        // 2) module translations
        const keys  = key.split('.');
        let   value = state.translations;
        for (const k of keys) value = value && value[k];
        if (value) return value;
        // 3) global admin i18n
        if (window._admin && typeof window._admin.t === 'function') {
            const v = window._admin.t(key);
            if (v && v !== key) return v;
        }
        return fallback !== undefined ? fallback : key;
    }

    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    function notify(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type || 'info');
            return;
        }
        if (AF && typeof AF.success === 'function') {
            const fnMap = { success: AF.success, error: AF.error, warning: AF.warning, info: AF.info };
            (fnMap[type] || AF.info).call(AF, msg);
            return;
        }
        alert(msg);
    }

    // ════════════════════════════════════════════════════════════
    // 3. SHOW STATE  (loading / empty / error / table)
    // ════════════════════════════════════════════════════════════
    function showState(stateName, errorMsg) {
        const loading   = document.getElementById('tableLoading');
        const empty     = document.getElementById('emptyState');
        const error     = document.getElementById('errorState');
        const container = document.getElementById('tableContainer');

        [loading, empty, error, container].forEach(function (el) {
            if (el) el.style.display = 'none';
        });

        switch (stateName) {
            case 'loading':
                if (loading) loading.style.display = 'flex';
                break;
            case 'empty':
                if (empty) empty.style.display = 'flex';
                break;
            case 'error':
                if (error) error.style.display = 'flex';
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
    // 4. DIRECTION
    // ════════════════════════════════════════════════════════════
    function setDirectionForLang(lang) {
        if (!lang) return;
        const rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
        const isRtl    = rtlLangs.includes(String(lang).toLowerCase().substring(0, 2));
        const dir      = isRtl ? 'rtl' : 'ltr';
        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }
        if (document.body) {
            document.body.classList.toggle('rtl', isRtl);
            document.body.classList.toggle('ltr', !isRtl);
        }
        const container = document.getElementById('tenantUsersPageContainer') || document.querySelector('.page-container');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 5. TRANSLATION SYSTEM
    // ════════════════════════════════════════════════════════════
    async function loadTranslations(lang) {
        lang = lang || (CFG && CFG.lang) || window.USER_LANGUAGE || 'en';
        try {
            const response = await fetch(`/languages/TenantUsers/${encodeURIComponent(lang)}.json`, {
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            state.translations = await response.json();
            state.language     = lang;
            return true;
        } catch (error) {
            console.error('[TenantUsers] Translation load failed:', error);
            if (lang !== 'en') return loadTranslations('en');
            state.translations = getFallbackTranslations();
            return true;
        }
    }

    function getFallbackTranslations() {
        return {
            tenant_users: { title: 'Tenant Users Management', subtitle: 'Manage users assigned to tenants', add_new: 'Add New User', loading: 'Loading...', retry: 'Retry' },
            table: {
                headers: { id: 'ID', username: 'Username', email: 'Email', tenant: 'Tenant', entity: 'Entity', role: 'Role', joined_at: 'Joined At', status: 'Status', actions: 'Actions' },
                actions: { edit: 'Edit', delete: 'Delete', confirm_delete: 'Are you sure you want to delete this user?' },
                status:  { active: 'Active', inactive: 'Inactive' },
                empty:   { title: 'No Tenant Users Found', message: 'Start by adding users to tenants', add_first: 'Add First User', no_entity: 'N/A' }
            },
            filters: { search: 'Search', apply: 'Apply', reset: 'Reset', status_options: { all: 'All Status', active: 'Active', inactive: 'Inactive' } },
            form: {
                add_title: 'Add New Tenant User', edit_title: 'Edit Tenant User',
                fields: {
                    role_id:   { enter_tenant_first: 'Enter tenant ID first', loading: 'Loading...', no_roles: 'No roles available', placeholder: 'Select role' },
                    entity_id: { enter_tenant_first: 'Enter tenant ID first', no_entities: 'No entities available', placeholder: 'Select entity (optional)', not_found: 'Entity not found' },
                    tenant_id: { not_found: 'Tenant not found' },
                    status:    { active: 'Active', inactive: 'Inactive' }
                },
                tenant_info: { domain: 'Domain:' },
                buttons: { save: 'Save', cancel: 'Cancel', saving: 'Saving...', updating: 'Updating...' }
            },
            messages: {
                success: { created: 'User created successfully', updated: 'User updated successfully', deleted: 'User deleted successfully' },
                error:   { load_failed: 'Failed to load data', save_failed: 'Failed to save data', delete_failed: 'Failed to delete data', not_found: 'Item not found' }
            },
            pagination: { showing: 'Showing', to: 'to', of: 'of', results: 'results' },
            validation: { required: 'Required' },
            export: { no_filters: 'Please apply filters before exporting', exporting: 'Exporting...', export_success: 'Exported successfully', export_error: 'Export failed' }
        };
    }

    // ════════════════════════════════════════════════════════════
    // 6. API NORMALIZER & HELPERS
    // ════════════════════════════════════════════════════════════
    function normalizeApiResponse(response) {
        let payload = response;
        let meta    = null;
        if (response && typeof response === 'object' && response.data !== undefined) {
            payload = response.data;
        }
        if (payload && typeof payload === 'object' && payload.meta) {
            meta = payload.meta;
        }
        return { payload, meta };
    }

    function afGet(url)       { return AF ? AF.get(url)           : fetch(url, { credentials: 'same-origin' }).then(r => r.json()); }
    function afPost(url, data) { return AF ? AF.post(url, data)   : fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(data) }).then(r => r.json()); }
    function afApi(url, opts)  { return AF ? AF.api(url, opts)    : fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } }, opts)).then(r => r.json()); }
    function afDel(url, data)  { return AF ? AF.delete(url, data) : fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(data) }).then(r => r.json()); }

    async function getUser(id) {
        const key    = `user_${id}`;
        const cached = AF ? AF.Cache.get(key) : null;
        if (cached) return cached;
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/users_account/${id}`));
            const user = Array.isArray(payload) ? payload[0] : payload;
            if (user && AF) AF.Cache.set(key, user);
            return user || null;
        } catch (e) { return null; }
    }

    async function getTenant(id) {
        const key    = `tenant_${id}`;
        const cached = AF ? AF.Cache.get(key) : null;
        if (cached) return cached;
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/tenants/${id}`));
            const tenant = Array.isArray(payload) ? payload[0] : payload;
            if (tenant && AF) AF.Cache.set(key, tenant);
            return tenant || null;
        } catch (e) { return null; }
    }

    async function getRoles(tenantId) {
        const key    = `roles_${tenantId}`;
        const cached = AF ? AF.Cache.get(key) : null;
        if (cached) return cached;
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/roles?tenant_id=${tenantId}`));
            const roles = Array.isArray(payload) ? payload : (payload?.items || []);
            if (roles.length && AF) AF.Cache.set(key, roles);
            return roles;
        } catch (e) { return []; }
    }

    async function getEntities(tenantId) {
        const key    = `entities_${tenantId}`;
        const cached = AF ? AF.Cache.get(key) : null;
        if (cached) return cached;
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/entities?tenant_id=${tenantId}`));
            const entities = Array.isArray(payload) ? payload : (payload?.items || []);
            if (entities.length && AF) AF.Cache.set(key, entities);
            return entities;
        } catch (e) { return []; }
    }

    async function getEntity(id) {
        const key    = `entity_${id}`;
        const cached = AF ? AF.Cache.get(key) : null;
        if (cached) return cached;
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/entities/${id}`));
            const entity = Array.isArray(payload) ? payload[0] : payload;
            if (entity && AF) AF.Cache.set(key, entity);
            return entity || null;
        } catch (e) { return null; }
    }

    // ════════════════════════════════════════════════════════════
    // 7. PAGINATION
    // ════════════════════════════════════════════════════════════
    function updatePaginationInfo() {
        if (!state.meta) return;
        const { total, page, per_page } = state.meta;
        const start = total === 0 ? 0 : (page - 1) * per_page + 1;
        const end   = Math.min(page * per_page, total);
        const elInfo = document.getElementById('paginationInfo');
        if (elInfo) elInfo.textContent = `${start}-${end} ${t('pagination.of', 'of')} ${total} ${t('pagination.results', 'results')}`;
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (!container || !state.meta) return;
        const { page, pages } = state.meta;
        if (pages <= 1) { container.innerHTML = ''; return; }

        let html = '';
        html += `<button class="pagination-btn" ${page <= 1 ? 'disabled' : ''} ${page > 1 ? `onclick="TenantUsers.load(${page - 1})"` : ''}><i class="fas fa-chevron-left"></i></button>`;

        let startPage = Math.max(1, page - 2);
        let endPage   = Math.min(pages, page + 2);
        if (endPage - startPage < 4) {
            if (startPage === 1) endPage = Math.min(pages, 5);
            else if (endPage === pages) startPage = Math.max(1, pages - 4);
        }

        if (startPage > 1) {
            html += `<button class="pagination-btn" onclick="TenantUsers.load(1)">1</button>`;
            if (startPage > 2) html += `<span class="pagination-ellipsis">...</span>`;
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="TenantUsers.load(${i})">${i}</button>`;
        }
        if (endPage < pages) {
            if (endPage < pages - 1) html += `<span class="pagination-ellipsis">...</span>`;
            html += `<button class="pagination-btn" onclick="TenantUsers.load(${pages})">${pages}</button>`;
        }
        html += `<button class="pagination-btn" ${page >= pages ? 'disabled' : ''} ${page < pages ? `onclick="TenantUsers.load(${page + 1})"` : ''}><i class="fas fa-chevron-right"></i></button>`;

        container.innerHTML = html;
    }

    // ════════════════════════════════════════════════════════════
    // 8. RENDER TABLE
    // ════════════════════════════════════════════════════════════
    function renderTable(items) {
        if (!el.tbody) return;

        if (!items || !items.length) { showState('empty'); return; }

        let html = '';
        for (const item of items) {
            const username   = item.username    || t('validation.required', 'Unknown');
            const email      = item.email       || 'N/A';
            const tenantName = item.tenant_name || t('validation.required', 'Unknown');
            const entityName = item.entity_name || t('table.empty.no_entity', 'N/A');
            const roleName   = item.role_name   || 'N/A';
            const joinedAt   = item.joined_at   || '-';
            const statusText  = item.is_active  ? t('table.status.active', 'Active') : t('table.status.inactive', 'Inactive');
            const statusClass = item.is_active  ? 'badge-success' : 'badge-danger';

            html += `
                <tr>
                    <td>${item.id}</td>
                    <td>
                        <strong>${esc(username)}</strong>
                        <small>${t('table.headers.id', 'ID')}: ${item.user_id}</small>
                    </td>
                    <td>${esc(email)}</td>
                    <td>
                        <strong>${esc(tenantName)}</strong>
                        <small>${t('table.headers.id', 'ID')}: ${item.tenant_id}</small>
                    </td>
                    <td>
                        ${item.entity_id
                            ? `<strong>${esc(entityName)}</strong><small>${t('table.headers.id', 'ID')}: ${item.entity_id}</small>`
                            : `<span class="text-muted">${t('table.empty.no_entity', 'N/A')}</span>`}
                    </td>
                    <td>
                        <span class="badge badge-primary">
                            ${esc(roleName)}
                        </span>
                    </td>
                    <td>${esc(joinedAt)}</td>
                    <td><span class="badge ${statusClass}">${esc(statusText)}</span></td>
                    <td>
                        <div class="table-actions">
                            ${CAN_EDIT   ? `<button class="btn btn-sm btn-primary" onclick="TenantUsers.edit(${item.id})" title="${esc(t('table.actions.edit', 'Edit'))}" aria-label="${esc(t('table.actions.edit', 'Edit'))}"><i class="fas fa-edit" aria-hidden="true"></i></button>` : ''}
                            ${CAN_DELETE ? `<button class="btn btn-sm btn-danger"  onclick="TenantUsers.remove(${item.id})" title="${esc(t('table.actions.delete', 'Delete'))}" aria-label="${esc(t('table.actions.delete', 'Delete'))}"><i class="fas fa-trash" aria-hidden="true"></i></button>` : ''}
                        </div>
                    </td>
                </tr>`;
        }

        el.tbody.innerHTML = html;
        showState('table');
    }

    // ════════════════════════════════════════════════════════════
    // 9. FORM — open / close / focus
    // ════════════════════════════════════════════════════════════
    function openForm() {
        if (!el.formContainer) return;
        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const first = el.formContainer.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (first) setTimeout(function () { first.focus(); }, 50);
    }

    function closeForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
    }

    // ════════════════════════════════════════════════════════════
    // 10. TENANT / USER / ENTITY VERIFICATION (debounced)
    // ════════════════════════════════════════════════════════════
    const verifyTenant = AF && AF.debounce ? AF.debounce(_verifyTenant, 300) : debounce(_verifyTenant, 300);
    const verifyUser   = AF && AF.debounce ? AF.debounce(_verifyUser,   300) : debounce(_verifyUser,   300);
    const verifyEntity = AF && AF.debounce ? AF.debounce(_verifyEntity, 300) : debounce(_verifyEntity, 300);

    function debounce(fn, ms) {
        let timer;
        return function () { clearTimeout(timer); timer = setTimeout(fn, ms); };
    }

    async function _verifyTenant() {
        const id = el.formTenantId && el.formTenantId.value.trim();
        if (!id || isNaN(id)) {
            if (el.tenantInfo) el.tenantInfo.style.display = 'none';
            if (el.formRoleId) { el.formRoleId.disabled = true; el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.enter_tenant_first')}</option>`; }
            return;
        }
        try {
            if (el.formRoleId) { el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.loading', 'Loading...')}</option>`; el.formRoleId.disabled = true; }
            const tenant = await getTenant(id);
            if (tenant) {
                if (el.tenantName)   el.tenantName.textContent   = tenant.name || '';
                if (el.tenantDomain) el.tenantDomain.textContent = tenant.domain ? `${t('form.tenant_info.domain', 'Domain:')} ${tenant.domain}` : '';
                if (el.tenantStatus) { el.tenantStatus.textContent = tenant.status || ''; el.tenantStatus.className = `badge ${tenant.status === 'active' ? 'badge-success' : 'badge-warning'}`; }
                if (el.tenantInfo)   el.tenantInfo.style.display  = 'block';

                const roles = await getRoles(id);
                if (el.formRoleId) {
                    if (roles.length) {
                        el.formRoleId.innerHTML = [`<option value="">${t('form.fields.role_id.placeholder', 'Select role')}</option>`,
                            ...roles.map(r => `<option value="${r.id}">${esc(r.display_name || r.key_name || r.name || `Role ${r.id}`)}</option>`)
                        ].join('');
                        el.formRoleId.disabled = false;
                    } else {
                        el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.no_roles', 'No roles available')}</option>`;
                        el.formRoleId.disabled = true;
                    }
                }

                const entities = await getEntities(id);
                if (el.formEntityId) {
                    el.formEntityId.innerHTML = [`<option value="">${t('form.fields.entity_id.placeholder', 'Select entity (optional)')}</option>`,
                        ...entities.map(e => `<option value="${e.id}">${esc(e.store_name || e.name || `Entity ${e.id}`)}</option>`)
                    ].join('');
                    el.formEntityId.disabled = false;
                }
            } else {
                if (el.tenantInfo) el.tenantInfo.style.display = 'none';
                if (el.formRoleId) { el.formRoleId.innerHTML = `<option value="">${t('form.fields.tenant_id.not_found', 'Tenant not found')}</option>`; el.formRoleId.disabled = true; }
            }
        } catch (error) {
            console.error('[TenantUsers] verifyTenant error:', error);
            if (el.tenantInfo) el.tenantInfo.style.display = 'none';
        }
    }

    async function _verifyUser() {
        const id = el.formUserId && el.formUserId.value.trim();
        if (!id || isNaN(id)) { if (el.userInfo) el.userInfo.style.display = 'none'; return; }
        const user = await getUser(id);
        if (user && el.userInfo) {
            if (el.userName)   el.userName.textContent   = user.username || '';
            if (el.userEmail)  el.userEmail.textContent  = user.email    || '';
            if (el.userStatus) { el.userStatus.textContent = user.is_active ? t('form.fields.status.active', 'Active') : t('form.fields.status.inactive', 'Inactive'); el.userStatus.className = `badge ${user.is_active ? 'badge-success' : 'badge-danger'}`; }
            el.userInfo.style.display = 'block';
        } else if (el.userInfo) {
            el.userInfo.style.display = 'none';
        }
    }

    async function _verifyEntity() {
        const id = el.formEntityId && el.formEntityId.value.trim();
        if (!id || isNaN(id)) { if (el.entityInfo) el.entityInfo.style.display = 'none'; return; }
        const entity = await getEntity(id);
        if (entity && el.entityInfo) {
            if (el.entityName)   el.entityName.textContent   = entity.store_name || '';
            if (el.entitySlug)   el.entitySlug.textContent   = entity.slug       || '';
            if (el.entityStatus) { el.entityStatus.textContent = entity.status || ''; el.entityStatus.className = `badge ${entity.status === 'approved' ? 'badge-success' : 'badge-warning'}`; }
            el.entityInfo.style.display = 'block';
        } else if (el.entityInfo) {
            el.entityInfo.style.display = 'none';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 11. CRUD — save, edit, remove, add
    // ════════════════════════════════════════════════════════════
    async function save(e) {
        e.preventDefault();
        const formEl   = document.getElementById('tenantUserForm');
        const formData = AF && AF.Form ? AF.Form.getData('tenantUserForm') : Object.fromEntries(new FormData(formEl));
        const id       = el.formId && el.formId.value.trim();
        const isEdit   = !!id;
        const data     = {
            tenant_id:  parseInt(formData.tenant_id),
            user_id:    parseInt(formData.user_id),
            role_id:    formData.role_id   === '' ? null : parseInt(formData.role_id),
            entity_id:  formData.entity_id === '' ? null : parseInt(formData.entity_id),
            is_active:  formData.is_active === '1' ? 1 : 0
        };
        if (isEdit) data.id = parseInt(id);

        try {
            const btnLabel = isEdit ? t('form.buttons.updating', 'Updating...') : t('form.buttons.saving', 'Saving...');
            if (AF && AF.Loading) AF.Loading.show(el.btnSubmit, btnLabel);

            let response;
            if (isEdit) {
                response = await afApi(`${API}/${data.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            } else {
                response = await afPost(API, data);
            }

            const { payload } = normalizeApiResponse(response);
            const serverOk = (response?.success === true) || (payload && (payload.id || payload.items));

            if (serverOk || (payload && payload.id)) {
                notify(isEdit ? t('messages.success.updated') : t('messages.success.created'), 'success');
                closeForm();
                await load(state.page);
                return;
            }

            notify((response && response.message) || t('messages.error.save_failed'), 'error');
        } catch (err) {
            console.error('[TenantUsers] Save error:', err);
            notify((err && err.message) || t('messages.error.save_failed'), 'error');
        } finally {
            if (AF && AF.Loading) AF.Loading.hide(el.btnSubmit);
        }
    }

    async function edit(id) {
        try {
            const response = await afGet(`${API}/${id}`);
            const { payload } = normalizeApiResponse(response);
            let item = Array.isArray(payload) ? payload.find(i => i.id == id) || payload[0]
                     : (payload?.items ? payload.items.find(i => i.id == id)  : payload);
            if (!item) throw new Error(t('messages.error.not_found', 'Item not found'));

            const formEl = document.getElementById('tenantUserForm');
            if (formEl) { formEl.reset(); formEl.classList.remove('was-validated'); }
            if (el.tenantInfo) el.tenantInfo.style.display = 'none';
            if (el.userInfo)   el.userInfo.style.display   = 'none';
            if (el.entityInfo) el.entityInfo.style.display = 'none';

            const formTitle = document.getElementById('formTitle');
            if (formTitle) formTitle.textContent = t('form.edit_title', 'Edit Tenant User');
            if (el.formId)       el.formId.value       = String(item.id    || '');
            if (el.formTenantId) el.formTenantId.value = String(item.tenant_id || '');
            if (el.formUserId)   el.formUserId.value   = String(item.user_id   || '');
            if (el.formIsActive) el.formIsActive.value = item.is_active ? '1' : '0';

            const btnDel = document.getElementById('btnDeleteTenantUser');
            if (btnDel) btnDel.style.display = '';

            if (item.tenant_id) {
                const tenant = await getTenant(item.tenant_id);
                if (tenant && el.tenantInfo) {
                    if (el.tenantName)   el.tenantName.textContent   = tenant.name || '';
                    if (el.tenantDomain) el.tenantDomain.textContent = tenant.domain ? `${t('form.tenant_info.domain', 'Domain:')} ${tenant.domain}` : '';
                    if (el.tenantStatus) { el.tenantStatus.textContent = tenant.status || ''; el.tenantStatus.className = `badge ${tenant.status === 'active' ? 'badge-success' : 'badge-warning'}`; }
                    el.tenantInfo.style.display = 'block';
                    const roles = await getRoles(item.tenant_id);
                    if (el.formRoleId) {
                        el.formRoleId.innerHTML = [`<option value="">${t('form.fields.role_id.placeholder', 'Select role')}</option>`,
                            ...roles.map(r => `<option value="${r.id}"${r.id == item.role_id ? ' selected' : ''}>${esc(r.display_name || r.key_name || r.name || `Role ${r.id}`)}</option>`)
                        ].join('');
                        el.formRoleId.disabled = false;
                    }
                    const entities = await getEntities(item.tenant_id);
                    if (el.formEntityId) {
                        el.formEntityId.innerHTML = [`<option value="">${t('form.fields.entity_id.placeholder', 'Select entity (optional)')}</option>`,
                            ...entities.map(e => `<option value="${e.id}"${e.id == item.entity_id ? ' selected' : ''}>${esc(e.store_name || e.name || `Entity ${e.id}`)}</option>`)
                        ].join('');
                        el.formEntityId.disabled = false;
                    }
                }
            }

            if (item.user_id) {
                const user = await getUser(item.user_id);
                if (user && el.userInfo) {
                    if (el.userName)   el.userName.textContent   = user.username || '';
                    if (el.userEmail)  el.userEmail.textContent  = user.email    || '';
                    if (el.userStatus) { el.userStatus.textContent = user.is_active ? t('form.fields.status.active', 'Active') : t('form.fields.status.inactive', 'Inactive'); el.userStatus.className = `badge ${user.is_active ? 'badge-success' : 'badge-danger'}`; }
                    el.userInfo.style.display = 'block';
                }
            }

            if (item.entity_id) {
                const entity = await getEntity(item.entity_id);
                if (entity && el.entityInfo) {
                    if (el.entityName)   el.entityName.textContent   = entity.store_name || '';
                    if (el.entitySlug)   el.entitySlug.textContent   = entity.slug       || '';
                    if (el.entityStatus) { el.entityStatus.textContent = entity.status || ''; el.entityStatus.className = `badge ${entity.status === 'approved' ? 'badge-success' : 'badge-warning'}`; }
                    el.entityInfo.style.display = 'block';
                }
            }

            openForm();
        } catch (err) {
            console.error('[TenantUsers] Edit error:', err);
            notify(t('messages.error.load_failed'), 'error');
        }
    }

    async function remove(id) {
        const confirmFn = AF?.Modal?.confirm
            ? (msg, cb) => AF.Modal.confirm(msg, cb)
            : (msg, cb) => { if (confirm(msg)) cb(); };

        confirmFn(t('table.actions.confirm_delete', 'Are you sure?'), async function () {
            try {
                await afDel(`${API}/${id}`, { id: id });
                notify(t('messages.success.deleted'), 'success');
                closeForm();
                load(state.page);
            } catch (err) {
                notify(t('messages.error.delete_failed'), 'error');
            }
        });
    }

    function add() {
        const formEl = document.getElementById('tenantUserForm');
        if (formEl) { formEl.reset(); formEl.classList.remove('was-validated'); }
        if (el.formId) el.formId.value = '';
        if (el.tenantInfo) el.tenantInfo.style.display = 'none';
        if (el.userInfo)   el.userInfo.style.display   = 'none';
        if (el.entityInfo) el.entityInfo.style.display = 'none';
        if (el.formRoleId)   { el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.enter_tenant_first')}</option>`;   el.formRoleId.disabled   = true; }
        if (el.formEntityId) { el.formEntityId.innerHTML = `<option value="">${t('form.fields.entity_id.enter_tenant_first')}</option>`; el.formEntityId.disabled = true; }
        const btnDel = document.getElementById('btnDeleteTenantUser');
        if (btnDel) btnDel.style.display = 'none';
        const formTitle = document.getElementById('formTitle');
        if (formTitle) formTitle.textContent = t('form.add_title', 'Add New Tenant User');
        openForm();
    }

    // ════════════════════════════════════════════════════════════
    // 12. EXPORT
    // ════════════════════════════════════════════════════════════
    async function exportToExcel() {
        const hasFilters = Object.keys(state.filters).length > 0 &&
            Object.values(state.filters).some(v => v !== null && v !== undefined && String(v).trim() !== '');
        if (!hasFilters) { notify(t('export.no_filters', 'Please apply filters before exporting'), 'warning'); return; }

        try {
            notify(t('export.exporting', 'Exporting...'), 'info');
            const params = new URLSearchParams({ ...state.filters, per_page: 10000 });
            const { payload } = normalizeApiResponse(await afGet(`/api/tenant_users?${params}`));
            const items = Array.isArray(payload) ? payload : (payload?.items || []);
            if (!items.length) { notify(t('messages.info.no_data', 'No data to export'), 'warning'); return; }

            const headers = ['ID', 'Username', 'Email', 'Tenant', 'Tenant ID', 'Entity', 'Entity ID', 'Role', 'Joined At', 'Status'];
            const rows    = items.map(item => [
                item.id, item.username || '', item.email || '',
                item.tenant_name || '', item.tenant_id || '',
                item.entity_name || 'N/A', item.entity_id || '',
                item.role_name   || '', item.joined_at   || '',
                item.is_active ? 'Active' : 'Inactive'
            ]);
            const csv  = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href          = URL.createObjectURL(blob);
            link.download      = `tenant_users_${new Date().toISOString().split('T')[0]}.csv`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            notify(t('export.export_success', 'Exported successfully'), 'success');
        } catch (error) {
            notify(t('export.export_error', 'Export failed'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // 13. DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function load(page) {
        page = page || 1;
        try {
            showState('loading');
            state.page = page;
            const params = new URLSearchParams({ page, per_page: state.perPage, ...state.filters });
            const response = await afGet(`${API}?${params}`);
            const { payload, meta } = normalizeApiResponse(response);

            let items = [];
            if (Array.isArray(payload))           items = payload;
            else if (payload?.items)              items = payload.items;
            else if (payload?.data && Array.isArray(payload.data)) items = payload.data;
            else if (payload && typeof payload === 'object' && Object.keys(payload).length) items = [payload];

            const finalMeta = meta || { total: items.length, page, per_page: state.perPage, pages: Math.ceil(items.length / state.perPage) };
            state.meta = finalMeta;

            updatePaginationInfo();
            renderPagination();
            renderTable(items);
        } catch (err) {
            console.error('[TenantUsers] Load error:', err);
            showState('error', err.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 14. FILTERS
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        const s = el.searchInput && el.searchInput.value.trim();    if (s) state.filters.search    = s;
        const te = el.tenantFilter && el.tenantFilter.value.trim(); if (te) state.filters.tenant_id = te;
        const u = el.userFilter   && el.userFilter.value.trim();    if (u) state.filters.user_id   = u;
        const en = el.entityFilter && el.entityFilter.value.trim(); if (en) state.filters.entity_id = en;
        const st = el.statusFilter && el.statusFilter.value;        if (st !== '') state.filters.is_active = st;
        load(1);
    }

    function resetFilters() {
        ['searchInput', 'tenantFilter', 'userFilter', 'entityFilter', 'statusFilter'].forEach(function (k) {
            if (el[k]) el[k].value = '';
        });
        state.filters = {};
        load(1);
    }

    // ════════════════════════════════════════════════════════════
    // 15. INIT
    // ════════════════════════════════════════════════════════════
    async function init() {
        reloadConfig();
        await loadTranslations((CFG && CFG.lang) || window.USER_LANGUAGE || 'en');
        setDirectionForLang(state.language);

        const $ = id => document.getElementById(id);

        el = {
            formContainer: $('tenantUserFormContainer'),
            form:          $('tenantUserForm'),
            formId:        $('formId'),
            formTenantId:  $('formTenantId'),
            formUserId:    $('formUserId'),
            formRoleId:    $('formRoleId'),
            formEntityId:  $('formEntityId'),
            formIsActive:  $('formIsActive'),
            tenantInfo:    $('tenantInfo'),
            tenantName:    $('tenantName'),
            tenantDomain:  $('tenantDomain'),
            tenantStatus:  $('tenantStatus'),
            entityInfo:    $('entityInfo'),
            entityName:    $('entityName'),
            entitySlug:    $('entitySlug'),
            entityStatus:  $('entityStatus'),
            userInfo:      $('userInfo'),
            userName:      $('userName'),
            userEmail:     $('userEmail'),
            userStatus:    $('userStatus'),
            tbody:         $('tableBody'),
            pagination:    $('pagination'),
            paginationInfo:$('paginationInfo'),
            searchInput:   $('searchInput'),
            tenantFilter:  $('tenantFilter'),
            userFilter:    $('userFilter'),
            entityFilter:  $('entityFilter'),
            statusFilter:  $('statusFilter'),
            btnSubmit:     $('btnSubmitForm')
        };

        // Load permissions
        try {
            const permsScript = $('pagePermissions');
            if (permsScript) state.permissions = JSON.parse(permsScript.textContent);
        } catch (e) { state.permissions = { canCreate: false, canEdit: false, canDelete: false }; }

        // ── Event Bindings ─────────────────────────────────────
        const formEl = document.getElementById('tenantUserForm');
        if (formEl) formEl.addEventListener('submit', save);

        if (el.formTenantId) el.formTenantId.addEventListener('input',  verifyTenant);
        if (el.formUserId)   el.formUserId.addEventListener('input',    verifyUser);
        if (el.formEntityId) el.formEntityId.addEventListener('change', verifyEntity);

        $('btnAddTenantUser')?.addEventListener('click', add);
        $('btnAddFirst')?.addEventListener('click', add);
        $('btnCloseForm')?.addEventListener('click',  closeForm);
        $('btnCancelForm')?.addEventListener('click', closeForm);

        const btnDel = $('btnDeleteTenantUser');
        if (btnDel) btnDel.addEventListener('click', function () {
            const id = el.formId && el.formId.value ? parseInt(el.formId.value, 10) : null;
            if (id) remove(id);
        });

        $('btnApplyFilters')?.addEventListener('click', applyFilters);
        $('btnResetFilters')?.addEventListener('click', resetFilters);
        $('btnExportExcel')?.addEventListener('click',  exportToExcel);
        $('btnRetry')?.addEventListener('click', function () { load(state.page); });

        // ESC closes form
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (el.formContainer && el.formContainer.style.display !== 'none') closeForm();
        });

        load(1);
        console.log('[TenantUsers] Initialized successfully!');
    }

    // ════════════════════════════════════════════════════════════
    // 16. REGISTER
    // ════════════════════════════════════════════════════════════
    window.TenantUsers = {
        init,
        load,
        edit,
        remove,
        add,
        verifyTenant,
        verifyUser,
        verifyEntity,
        setLanguage: async function (lang) {
            await loadTranslations(lang);
            setDirectionForLang(lang);
            load(state.page);
        }
    };

    window.page = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('tenant_users', init);
    }

    // Initialization is driven by the fragment's inline <script> which waits
    // for admin:i18n:applied — do NOT self-invoke init() here.

}());