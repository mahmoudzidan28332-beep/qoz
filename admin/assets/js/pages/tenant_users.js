/**
 * Tenant Users Management
 * Version: 4.1.2 — Fixed Edit API call and form data extraction
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
        if (STRINGS && STRINGS[key]) return STRINGS[key];
        const keys  = key.split('.');
        let   value = state.translations;
        for (const k of keys) value = value && value[k];
        if (value) return value;
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
    // 3. SHOW STATE
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
    // 4. TRANSLATIONS
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
                    role_id:   { enter_tenant_first: 'Enter tenant ID first', loading: 'Loading...', no_roles: 'No roles available', placeholder: 'Select role', error: 'Error loading data' },
                    entity_id: { enter_tenant_first: 'Enter tenant ID first', no_entities: 'No entities available', placeholder: 'Select entity (optional)', not_found: 'Entity not found' },
                    tenant_id: { not_found: 'Tenant not found' },
                    status:    { active: 'Active', inactive: 'Inactive' }
                },
                tenant_info: { domain: 'Domain:' },
                buttons: { save: 'Save', cancel: 'Cancel', saving: 'Saving...', updating: 'Updating...' }
            },
            messages: {
                success: { created: 'User created successfully', updated: 'User updated successfully', deleted: 'User deleted successfully' },
                error:   { load_failed: 'Failed to load data', save_failed: 'Failed to save data', delete_failed: 'Failed to delete data', not_found: 'Item not found' },
                error_loading_tenant_data: 'Error loading tenant data: '
            },
            pagination: { showing: 'Showing', to: 'to', of: 'of', results: 'results' },
            validation: { required: 'Required' },
            export: { no_filters: 'Please apply filters before exporting', exporting: 'Exporting...', export_success: 'Exported successfully', export_error: 'Export failed' }
        };
    }

    // ════════════════════════════════════════════════════════════
    // 5. API HELPERS
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

    function parsePositiveInt(value) {
        if (value === null || value === undefined) return null;
        const normalized = String(value).trim();
        if (normalized === '') return null;
        const parsed = Number.parseInt(normalized, 10);
        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    function getItemId(item, keys) {
        if (!item || typeof item !== 'object') return null;
        for (const key of keys) {
            const parsed = parsePositiveInt(item[key]);
            if (parsed !== null) return parsed;
        }
        return null;
    }

    function afGet(url)       { return AF ? AF.get(url)           : fetch(url, { credentials: 'same-origin' }).then(r => r.json()); }
    function afPost(url, data) { return AF ? AF.post(url, data)   : fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(data) }).then(r => r.json()); }
    function afApi(url, opts)  { return AF ? AF.api(url, opts)    : fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } }, opts)).then(r => r.json()); }
    function afDel(url, data)  { return AF ? AF.delete(url, data) : fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(data) }).then(r => r.json()); }

    async function getUser(id) {
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/users_account/${id}`));
            const user = Array.isArray(payload) ? payload[0] : payload;
            return user || null;
        } catch (e) { return null; }
    }

    async function getTenant(id) {
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/tenants/${id}`));
            const tenant = Array.isArray(payload) ? payload[0] : payload;
            return tenant || null;
        } catch (e) { return null; }
    }

    async function getRoles(tenantId) {
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/roles?tenant_id=${tenantId}`));
            const roles = Array.isArray(payload) ? payload : (payload?.items || []);
            return roles;
        } catch (e) { return []; }
    }

    async function getEntities(tenantId) {
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/entities?tenant_id=${tenantId}`));
            const entities = Array.isArray(payload) ? payload : (payload?.items || []);
            return entities;
        } catch (e) { return []; }
    }

    async function getEntity(id) {
        try {
            const { payload } = normalizeApiResponse(await afGet(`/api/entities/${id}`));
            const entity = Array.isArray(payload) ? payload[0] : payload;
            return entity || null;
        } catch (e) { return null; }
    }

    // ════════════════════════════════════════════════════════════
    // 6. VERIFICATION
    // ════════════════════════════════════════════════════════════
    const verifyTenant = AF && AF.debounce ? AF.debounce(_verifyTenant, 300) : debounce(_verifyTenant, 300);
    const verifyUser   = AF && AF.debounce ? AF.debounce(_verifyUser,   300) : debounce(_verifyUser,   300);
    const verifyEntity = AF && AF.debounce ? AF.debounce(_verifyEntity, 300) : debounce(_verifyEntity, 300);

    function debounce(fn, ms) {
        let timer;
        return function () { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, arguments), ms); };
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
                    const roleOptions = roles
                        .map(r => {
                            const roleId = getItemId(r, ['id', 'role_id']);
                            if (roleId === null) return '';
                            const roleName = esc(r.display_name || r.key_name || r.name || `Role ${roleId}`);
                            return `<option value="${roleId}" data-role-id="${roleId}">${roleName}</option>`;
                        })
                        .filter(Boolean);

                    if (roleOptions.length) {
                        el.formRoleId.innerHTML = [`<option value="">${t('form.fields.role_id.placeholder', 'Select role')}</option>`,
                            ...roleOptions
                        ].join('');
                        el.formRoleId.disabled = false;
                    } else {
                        el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.no_roles', 'No roles available')}</option>`;
                        el.formRoleId.disabled = true;
                    }
                }

                const entities = await getEntities(id);
                if (el.formEntityId) {
                    const entityOptions = entities
                        .map(e => {
                            const entityId = getItemId(e, ['id', 'entity_id']);
                            if (entityId === null) return '';
                            const entityName = esc(e.store_name || e.name || `Entity ${entityId}`);
                            return `<option value="${entityId}" data-entity-id="${entityId}">${entityName}</option>`;
                        })
                        .filter(Boolean);

                    el.formEntityId.innerHTML = [`<option value="">${t('form.fields.entity_id.placeholder', 'Select entity (optional)')}</option>`,
                        ...entityOptions
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
            if (el.formRoleId) { 
                el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.error', 'Error loading data')}</option>`; 
                el.formRoleId.disabled = true;
            }
            if (AF && AF.error) AF.error(t('messages.error_loading_tenant_data', 'Error loading tenant data: ') + error.message);
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
    // 7. CRUD
    // ════════════════════════════════════════════════════════════
    async function save(e) {
        e.preventDefault();
        const formEl = el.form;
        if (!formEl) return;

        // Manual extraction to avoid issues with AF.Form.getData
        const id = el.formId && el.formId.value.trim();
        const tenant_id = parsePositiveInt(el.formTenantId && el.formTenantId.value.trim());
        const user_id = parsePositiveInt(el.formUserId && el.formUserId.value.trim());
        const role_id = parsePositiveInt(
            (el.formRoleId && el.formRoleId.value) ||
            (el.formRoleId && el.formRoleId.selectedOptions && el.formRoleId.selectedOptions[0] && el.formRoleId.selectedOptions[0].dataset.roleId) ||
            ''
        );
        const entity_id = parsePositiveInt(
            (el.formEntityId && el.formEntityId.value) ||
            (el.formEntityId && el.formEntityId.selectedOptions && el.formEntityId.selectedOptions[0] && el.formEntityId.selectedOptions[0].dataset.entityId) ||
            ''
        );
        const is_active = el.formIsActive && el.formIsActive.value;

        const isEdit = !!id;

        const data = {
            tenant_id: tenant_id,
            user_id:   user_id,
            role_id:   role_id,
            entity_id: entity_id,
            is_active: is_active === '1' ? 1 : 0
        };
        if (isEdit) data.id = parsePositiveInt(id);

        console.log('[TenantUsers] Saving data:', data);

        if (data.tenant_id === null || data.user_id === null) {
            notify('Tenant ID and User ID are required', 'error');
            return;
        }
        if (!isEdit && data.role_id === null) {
            notify(t('form.fields.role_id.required', 'Role is required'), 'error');
            return;
        }

        try {
            if (AF && AF.Loading) AF.Loading.show(el.btnSubmit, isEdit ? t('form.buttons.updating') : t('form.buttons.saving'));
            let response;
            if (isEdit) {
                response = await afApi(API, { method: 'PUT', body: JSON.stringify(data) });
            } else {
                response = await afPost(API, data);
            }

            if (response && response.success) {
                notify(isEdit ? t('messages.success.updated') : t('messages.success.created'), 'success');
                closeForm();
                load(state.page);
            } else {
                notify(response?.message || t('messages.error.save_failed'), 'error');
            }
        } catch (err) {
            notify(err.message || t('messages.error.save_failed'), 'error');
        } finally {
            if (AF && AF.Loading) AF.Loading.hide(el.btnSubmit);
        }
    }

    async function edit(id) {
        try {
            // FIX: Use ?id= query param as expected by the PHP route
            const response = await afGet(`${API}?id=${id}`);
            const { payload } = normalizeApiResponse(response);
            const item = Array.isArray(payload) ? payload[0] : payload;
            if (!item) throw new Error(t('messages.error.not_found'));

            if (el.formId)       el.formId.value       = item.id;
            if (el.formTenantId) el.formTenantId.value = item.tenant_id;
            if (el.formUserId)   el.formUserId.value   = item.user_id;
            if (el.formIsActive) el.formIsActive.value = item.is_active ? '1' : '0';

            // Wait for verifications to finish so selects are populated
            await _verifyTenant();
            await _verifyUser();

            if (item.entity_id && el.formEntityId) {
                el.formEntityId.value = item.entity_id;
                await _verifyEntity();
            }
            if (item.role_id && el.formRoleId) {
                el.formRoleId.value = item.role_id;
            }

            const formTitle = document.getElementById('formTitle');
            if (formTitle) formTitle.textContent = t('form.edit_title');
            openForm();
        } catch (err) {
            console.error('[TenantUsers] Edit error:', err);
            notify(err.message || t('messages.error.load_failed'), 'error');
        }
    }

    async function remove(id) {
        if (!confirm(t('table.actions.confirm_delete'))) return;
        try {
            const response = await afDel(`${API}?id=${id}`, { id });
            if (response && response.success) {
                notify(t('messages.success.deleted'), 'success');
                load(state.page);
            } else {
                notify(response?.message || t('messages.error.delete_failed'), 'error');
            }
        } catch (err) {
            notify(err.message || t('messages.error.delete_failed'), 'error');
        }
    }

    function add() {
        if (el.form) { el.form.reset(); el.formId.value = ''; }
        if (el.tenantInfo) el.tenantInfo.style.display = 'none';
        if (el.userInfo)   el.userInfo.style.display   = 'none';
        if (el.entityInfo) el.entityInfo.style.display = 'none';
        
        const formTitle = document.getElementById('formTitle');
        if (formTitle) formTitle.textContent = t('form.add_title');
        
        if (el.formTenantId && CFG.tenantId > 0 && !CFG.isPlatformAdmin) {
            el.formTenantId.value = CFG.tenantId;
            _verifyTenant();
        }
        openForm();
    }

    function openForm() { if (el.formContainer) el.formContainer.style.display = 'block'; el.formContainer?.scrollIntoView({ behavior: 'smooth' }); }
    function closeForm() { if (el.formContainer) el.formContainer.style.display = 'none'; }

    // ════════════════════════════════════════════════════════════
    // 8. DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function load(page) {
        state.page = page || 1;
        showState('loading');
        try {
            const params = new URLSearchParams({
                page: state.page,
                limit: state.perPage,
                ...state.filters
            });
            const response = await afGet(`${API}?${params}`);
            const { payload, meta } = normalizeApiResponse(response);
            state.meta = meta;
            renderTable(payload?.items || payload || []);
            renderPagination();
        } catch (err) {
            showState('error', err.message);
        }
    }

    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showState('empty'); return; }

        el.tbody.innerHTML = items.map(item => `
            <tr>
                <td>${item.id}</td>
                <td><strong>${esc(item.username)}</strong><br><small>ID: ${item.user_id}</small></td>
                <td>${esc(item.email)}</td>
                <td><strong>${esc(item.tenant_name)}</strong><br><small>ID: ${item.tenant_id}</small></td>
                <td>${item.entity_name ? esc(item.entity_name) : 'N/A'}</td>
                <td><span class="badge badge-info">${esc(item.role_name)}</span></td>
                <td><span class="badge ${item.is_active ? 'badge-success' : 'badge-danger'}">${item.is_active ? t('table.status.active') : t('table.status.inactive')}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="TenantUsers.edit(${item.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="TenantUsers.remove(${item.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
        showState('table');
    }

    function renderPagination() {
        const container = el.pagination;
        if (!container || !state.meta || state.meta.pages <= 1) { if (container) container.innerHTML = ''; return; }
        let html = '';
        for (let i = 1; i <= state.meta.pages; i++) {
            html += `<button class="pagination-btn ${i === state.page ? 'active' : ''}" onclick="TenantUsers.load(${i})">${i}</button>`;
        }
        container.innerHTML = html;
    }

    // ════════════════════════════════════════════════════════════
    // 9. INIT
    // ════════════════════════════════════════════════════════════
    async function init() {
        reloadConfig();
        await loadTranslations();
        
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
            btnSubmit:     $('btnSubmitForm')
        };

        if (el.form) el.form.addEventListener('submit', save);
        if (el.formTenantId) el.formTenantId.addEventListener('input', verifyTenant);
        if (el.formUserId)   el.formUserId.addEventListener('input', verifyUser);
        
        $('btnAddTenantUser')?.addEventListener('click', add);
        $('btnCloseForm')?.addEventListener('click', closeForm);
        $('btnCancelForm')?.addEventListener('click', closeForm);

        load(1);
    }

    window.TenantUsers = { init, load, edit, remove };
    document.addEventListener('DOMContentLoaded', init);

})();
