(function(){
    'use strict';

    const CONFIG = window.TENANT_CATEGORIES_CONFIG || {};
    const API = CONFIG.apiUrl || '/api/categories-tenants';
    const TENANTS_API = CONFIG.tenantsUrl || '/api/tenants';
    const CATEGORIES_API = CONFIG.categoriesUrl || '/api/categories';
    const TRANSLATIONS_URL = CONFIG.translationsUrl || '/languages/Tenant_categories/en.json';

    const state = {
        page: 1,
        perPage: 25,
        total: 0,
        filters: {},
        items: [],
        tenants: [],
        categories: [],
        categoriesTree: [],
        permissions: CONFIG.permissions || {},
        isSuperAdmin: CONFIG.isSuperAdmin || false
    };

    let el = {};
    let translations = CONFIG.strings || {};

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    async function loadTranslations() {
        if (Object.keys(translations).length > 0) {
            applyTranslations();
            return;
        }
        
        try {
            const response = await fetch(TRANSLATIONS_URL);
            if (response.ok) {
                const data = await response.json();
                translations = data.strings || data;
            }
        } catch (error) {
            console.error('[TenantCategories] Error loading translations:', error);
        }
        applyTranslations();
    }

    function t(key, placeholders = {}) {
        let text = translations[key] || key;
        Object.keys(placeholders).forEach(p => {
            text = text.replace(new RegExp(`{${p}}`, 'g'), placeholders[p]);
        });
        return text;
    }

    function applyTranslations() {
        const container = document.getElementById('tenantCategoriesPage');
        if (!container) return;

        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            if (elem.tagName === 'INPUT' && elem.hasAttribute('placeholder')) {
                elem.setAttribute('placeholder', t(key));
            } else if (elem.tagName !== 'INPUT' && elem.tagName !== 'TEXTAREA') {
                elem.textContent = t(key);
            }
        });
    }

    function showNotification(message, type = 'success') {
        if (window.AdminFramework && window.AdminFramework.notify) {
            window.AdminFramework.notify(message, type);
            return;
        }
        alert(message);
    }

    // ════════════════════════════════════════════════════════════
    // CATEGORY TREE BUILDING
    // ════════════════════════════════════════════════════════════
    
    function buildCategoryTree(categories, parentId = null) {
        const tree = [];
        
        const children = categories.filter(cat => {
            const catParent = cat.parent_id;
            if (parentId === null || parentId === 0 || parentId === '0') {
                return !catParent || catParent === 0 || catParent === '0';
            }
            return String(catParent) === String(parentId);
        });
        
        for (const child of children) {
            const grandchildren = buildCategoryTree(categories, child.id);
            tree.push({
                ...child,
                children: grandchildren,
                hasChildren: grandchildren.length > 0
            });
        }
        
        return tree;
    }
    
    function renderCategoriesTree(selectedCategoryId = null) {
        if (!el.categoriesTreeContainer) return;
        
        if (!state.categories || state.categories.length === 0) {
            el.categoriesTreeContainer.innerHTML = '<div class="text-muted">No categories found</div>';
            return;
        }
        
        state.categoriesTree = buildCategoryTree(state.categories, null);
        
        const renderNode = (node, level = 0) => {
            const isSelected = selectedCategoryId && parseInt(selectedCategoryId) === node.id;
            const indent = level * 24;
            
            return `
                <li class="category-tree-node ${node.hasChildren ? 'has-children' : ''}" data-id="${node.id}">
                    <div class="category-tree-row" style="padding-left: ${indent}px;">
                        ${node.hasChildren ? `
                            <button type="button" class="category-tree-toggle">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        ` : '<span class="category-tree-toggle-placeholder"></span>'}
                        <label class="category-tree-label">
                            <input type="radio" 
                                   name="selectedCategory" 
                                   class="category-tree-radio" 
                                   data-id="${node.id}"
                                   data-name="${escapeHtml(node.name)}"
                                   ${isSelected ? 'checked' : ''}>
                            <span class="category-tree-name">${escapeHtml(node.name)}</span>
                        </label>
                    </div>
                    ${node.hasChildren ? `
                        <ul class="category-tree-children" style="display: none;">
                            ${node.children.map(child => renderNode(child, level + 1)).join('')}
                        </ul>
                    ` : ''}
                </li>
            `;
        };
        
        const html = `<ul class="category-tree-root">${state.categoriesTree.map(node => renderNode(node, 0)).join('')}</ul>`;
        el.categoriesTreeContainer.innerHTML = html;
        
        // Attach events
        el.categoriesTreeContainer.querySelectorAll('.category-tree-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    if (el.categoryHidden) el.categoryHidden.value = this.dataset.id;
                    if (el.categoryDisplay) el.categoryDisplay.value = this.dataset.name;
                    if (el.categorySelect) el.categorySelect.value = this.dataset.id;
                }
            });
        });
        
        el.categoriesTreeContainer.querySelectorAll('.category-tree-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const li = this.closest('.category-tree-node');
                const childrenUl = li.querySelector(':scope > .category-tree-children');
                if (childrenUl) {
                    const isHidden = childrenUl.style.display === 'none';
                    childrenUl.style.display = isHidden ? 'block' : 'none';
                    this.querySelector('i').className = isHidden ? 'fas fa-chevron-down' : 'fas fa-chevron-right';
                }
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // API CALLS
    // ════════════════════════════════════════════════════════════
    
    async function apiFetch(url, options = {}) {
        const defaultOptions = {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (options.method && options.method !== 'GET') {
            defaultOptions.headers['Content-Type'] = 'application/json';
            defaultOptions.headers['X-CSRF-Token'] = CONFIG.csrfToken || '';
        }
        
        const finalOptions = { ...defaultOptions, ...options };
        if (options.body && typeof options.body === 'object') {
            finalOptions.body = JSON.stringify(options.body);
        }
        
        console.log('[TenantCategories] API Request:', url, finalOptions.method || 'GET');
        
        const response = await fetch(url, finalOptions);
        const data = await response.json();
        
        console.log('[TenantCategories] API Response:', data);
        return data;
    }

    // ════════════════════════════════════════════════════════════
    // LOAD DROPDOWNS
    // ════════════════════════════════════════════════════════════
    
    async function loadDropdowns() {
        console.log('[TenantCategories] Loading dropdowns...');
        
        try {
            // Load categories
            const catParams = new URLSearchParams({
                format: 'json',
                limit: 1000,
                lang: CONFIG.lang || 'ar',
                tenant_id: CONFIG.tenantId
            });
            
            const categoriesResult = await apiFetch(`${CATEGORIES_API}?${catParams}`);
            console.log('[TenantCategories] Categories loaded:', categoriesResult);
            
            if (categoriesResult.success && categoriesResult.data) {
                let items = categoriesResult.data;
                if (categoriesResult.data.items) items = categoriesResult.data.items;
                if (Array.isArray(items)) {
                    state.categories = items;
                    renderCategoriesTree();
                    populateSelect('tenantCategoryCategoryId', state.categories);
                    populateDatalist('categoriesList', state.categories);
                    populateDatalist('filterCategoriesList', state.categories);
                }
            }
            
            // Load tenants (super admin only)
            if (state.isSuperAdmin) {
                const tenantsResult = await apiFetch(`${TENANTS_API}?format=json&limit=1000`);
                console.log('[TenantCategories] Tenants loaded:', tenantsResult);
                
                if (tenantsResult.success && tenantsResult.data) {
                    let items = tenantsResult.data;
                    if (tenantsResult.data.items) items = tenantsResult.data.items;
                    if (Array.isArray(items)) {
                        state.tenants = items;
                        populateDatalist('tenantsList', state.tenants);
                        populateDatalist('filterTenantsList', state.tenants);
                    }
                }
            }
            
            // Load main data after dropdowns
            await loadData(1);
            
        } catch (error) {
            console.error('[TenantCategories] Load dropdowns error:', error);
            showError('Failed to load data');
        }
    }

    function populateSelect(selectId, data) {
        const select = document.getElementById(selectId);
        if (!select || !data) return;
        
        const currentVal = select.value;
        select.innerHTML = '<option value="">-- Select Category --</option>';
        
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.name} (#${item.id})`;
            select.appendChild(option);
        });
        
        if (currentVal) select.value = currentVal;
    }

    function populateDatalist(datalistId, data) {
        const datalist = document.getElementById(datalistId);
        if (!datalist || !data) return;
        
        datalist.innerHTML = '';
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.name;
            option.setAttribute('data-id', item.id);
            datalist.appendChild(option);
        });
    }

    function getIdFromDatalist(datalistId, displayValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return null;
        
        const trimmed = (displayValue || '').trim();
        if (!trimmed) return null;
        
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.value === trimmed) {
                return option.getAttribute('data-id');
            }
        }
        
        if (/^\d+$/.test(trimmed)) return trimmed;
        return null;
    }

    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist || !idValue) return;
        
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.getAttribute('data-id') === idValue.toString()) {
                const displayEl = document.getElementById(displayId);
                const hiddenEl = document.getElementById(hiddenId);
                if (displayEl) displayEl.value = option.value;
                if (hiddenEl) hiddenEl.value = idValue;
                return;
            }
        }
    }

    // ════════════════════════════════════════════════════════════
    // MAIN DATA LOADING
    // ════════════════════════════════════════════════════════════
    
    async function loadData(page = 1) {
        console.log('[TenantCategories] Loading data page:', page);
        
        try {
            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                format: 'json'
            });
            if (CONFIG.tenantId && !state.isSuperAdmin) {
                params.set('tenant_id', CONFIG.tenantId);
            }

            // Add filters
            Object.keys(state.filters).forEach(key => {
                const val = state.filters[key];
                if (val !== undefined && val !== null && val !== '') {
                    params.set(key, val);
                }
            });

            const url = `${API}?${params}`;
            console.log('[TenantCategories] Fetching:', url);
            
            const result = await apiFetch(url);
            console.log('[TenantCategories] Result:', result);

            if (result.success && result.data) {
                // Handle different response structures
                let items = [];
                let total = 0;
                
                if (Array.isArray(result.data)) {
                    items = result.data;
                    total = result.total || items.length;
                } else if (result.data.items) {
                    items = result.data.items;
                    total = result.data.meta?.total || result.data.total || items.length;
                } else if (result.data.data) {
                    items = result.data.data;
                    total = result.data.meta?.total || items.length;
                } else {
                    items = [];
                    total = 0;
                }
                
                state.items = items;
                state.total = total;
                
                console.log('[TenantCategories] Loaded', items.length, 'items, total:', total);
                
                renderTable();
                updatePagination(page, total);
                updateResultsCount(total);
                
                if (items.length > 0) {
                    showTable();
                } else {
                    showEmpty();
                }
            } else {
                console.warn('[TenantCategories] API returned unsuccessful:', result);
                showEmpty();
            }
        } catch (error) {
            console.error('[TenantCategories] Load error:', error);
            showError(error.message || 'Error loading data');
        }
    }

    // ════════════════════════════════════════════════════════════
    // TABLE RENDERING
    // ════════════════════════════════════════════════════════════
    
    function renderTable() {
        if (!el.tableBody) {
            console.error('[TenantCategories] tableBody not found');
            return;
        }
        
        if (!state.items || state.items.length === 0) {
            el.tableBody.innerHTML = '';
            return;
        }

        let html = '';
        state.items.forEach(item => {
            const statusText = item.is_active ? 'Active' : 'Inactive';
            const statusClass = item.is_active ? 'btn-success' : 'btn-danger';
            const createdDate = item.created_at ? new Date(item.created_at).toLocaleDateString() : '-';
            const tenantName = item.tenant_name || (item.tenant?.name) || '-';
            const categoryName = item.category_name || (item.category?.name) || '-';

            html += `
                <tr>
                    <td>${escapeHtml(item.id)}</td>
                    ${state.isSuperAdmin ? `<td>${escapeHtml(item.tenant_id)}</td>` : ''}
                    <td><strong>${escapeHtml(tenantName)}</strong></td>
                    <td>${escapeHtml(item.category_id)}</td>
                    <td><strong>${escapeHtml(categoryName)}</strong></td>
                    <td>${item.sort_order ?? 0}</td>
                    ${state.isSuperAdmin ? `<td>
                        <button class="btn btn-sm ${statusClass}" onclick="TenantCategories.toggleStatus(${item.id}, ${item.is_active ? 0 : 1})">
                            ${statusText}
                        </button>
                    </td>` : ''}
                    <td>${createdDate}</td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit ? `
                                <button class="btn btn-sm btn-outline" onclick="TenantCategories.edit(${item.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${state.permissions.canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="TenantCategories.remove(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        el.tableBody.innerHTML = html;
        console.log('[TenantCategories] Table rendered with', state.items.length, 'rows');
    }

    // ════════════════════════════════════════════════════════════
    // FORM MANAGEMENT
    // ════════════════════════════════════════════════════════════
    
    function showForm(isEdit = false, data = null) {
        if (!el.formContainer) return;

        el.formContainer.style.display = 'block';
        if (el.form) el.form.reset();
        if (el.formId) el.formId.value = '';

        if (el.formTitle) {
            el.formTitle.textContent = isEdit ? 'Edit Tenant Category' : 'Add Tenant Category';
        }

        renderCategoriesTree(data ? data.category_id : null);

        if (isEdit && data) {
            if (el.formId) el.formId.value = data.id;
            if (state.isSuperAdmin && el.tenantDisplay) {
                setDisplayFromId('tenantCategoryTenantIdHidden', 'tenantCategoryTenantId', 'tenantsList', data.tenant_id);
            }
            if (el.categoryHidden) el.categoryHidden.value = data.category_id;
            if (el.sortOrder) el.sortOrder.value = data.sort_order ?? 0;
            if (state.isSuperAdmin && el.isActive) el.isActive.value = data.is_active ?? 1;
            if (el.btnDelete) el.btnDelete.style.display = 'inline-block';
        } else {
            if (el.btnDelete) el.btnDelete.style.display = 'none';
            if (el.categoryHidden) el.categoryHidden.value = '';
            if (el.categoryDisplay) el.categoryDisplay.value = '';
        }

        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.form) el.form.reset();
    }

    // ════════════════════════════════════════════════════════════
    // SAVE DATA
    // ════════════════════════════════════════════════════════════
    
    async function saveData(e) {
        if (e) e.preventDefault();

        const id = el.formId ? el.formId.value.trim() : '';
        const isEdit = !!id;

        // Get tenant ID
        let tenantId = CONFIG.tenantId;
        if (state.isSuperAdmin && el.tenantDisplay) {
            const tenantDisplay = el.tenantDisplay.value.trim();
            tenantId = getIdFromDatalist('tenantsList', tenantDisplay);
            if (!tenantId) {
                showNotification('Please select a tenant', 'error');
                return;
            }
        }

        // Get category ID
        let categoryId = null;
        if (el.categoryHidden && el.categoryHidden.value) {
            categoryId = el.categoryHidden.value;
        } else if (el.categorySelect && el.categorySelect.value) {
            categoryId = el.categorySelect.value;
        }

        if (!categoryId) {
            showNotification('Please select a category', 'error');
            return;
        }

        const data = {
            tenant_id: parseInt(tenantId),
            category_id: parseInt(categoryId),
            sort_order: el.sortOrder ? (parseInt(el.sortOrder.value) || 0) : 0,
            is_active: (state.isSuperAdmin && el.isActive) ? (parseInt(el.isActive.value) || 1) : 1
        };

        if (isEdit) data.id = parseInt(id);

        try {
            if (el.btnSave) {
                el.btnSave.disabled = true;
                el.btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const url = isEdit ? `${API}/${data.id}` : API;
            const method = isEdit ? 'PUT' : 'POST';
            
            const result = await apiFetch(url, {
                method: method,
                body: data
            });

            if (el.btnSave) {
                el.btnSave.disabled = false;
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> Save';
            }

            if (result.success) {
                showNotification(isEdit ? 'Updated successfully' : 'Added successfully', 'success');
                hideForm();
                await loadData(state.page);
            } else {
                showNotification(result.message || 'Error occurred', 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Save error:', error);
            if (el.btnSave) {
                el.btnSave.disabled = false;
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> Save';
            }
            showNotification('Error occurred', 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // CRUD OPERATIONS
    // ════════════════════════════════════════════════════════════
    
    async function deleteData(id) {
        if (!confirm('Are you sure you want to delete this item?')) return;

        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'DELETE',
                body: { id: id }
            });

            if (result.success) {
                showNotification('Deleted successfully', 'success');
                await loadData(state.page);
            } else {
                showNotification(result.message || 'Error occurred', 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Delete error:', error);
            showNotification('Error occurred', 'error');
        }
    }

    async function toggleStatus(id, newStatus) {
        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'PUT',
                body: { is_active: newStatus }
            });

            if (result.success) {
                showNotification('Status updated', 'success');
                await loadData(state.page);
            } else {
                showNotification(result.message || 'Error occurred', 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Toggle status error:', error);
            showNotification('Error occurred', 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════════
    
    function applyFilters() {
        state.filters = {};

        // For Platform Admins, allow filtering by tenant
        if (state.isSuperAdmin && el.filterTenantHidden && el.filterTenantHidden.value !== '') {
            state.filters.tenant_id = el.filterTenantHidden.value;
        }

        if (el.filterCategoryHidden && el.filterCategoryHidden.value) {
            state.filters.category_id = el.filterCategoryHidden.value;
        }

        if (state.isSuperAdmin && el.filterStatus && el.filterStatus.value !== '') {
            state.filters.is_active = el.filterStatus.value;
        }

        loadData(1);
    }

    function resetFilters() {
        if (state.isSuperAdmin && el.filterTenant) el.filterTenant.value = '';
        if (state.isSuperAdmin && el.filterTenantHidden) el.filterTenantHidden.value = '';
        if (el.filterCategory) el.filterCategory.value = '';
        if (el.filterCategoryHidden) el.filterCategoryHidden.value = '';
        if (state.isSuperAdmin && el.filterStatus) el.filterStatus.value = '';

        state.filters = {};
        loadData(1);
    }

    // ════════════════════════════════════════════════════════════
    // UI HELPERS
    // ════════════════════════════════════════════════════════════
    
    function showLoading() {
        const loading = document.getElementById('tcLoading');
        const tableContainer = document.getElementById('tcTableContainer');
        const empty = document.getElementById('tcEmpty');
        const error = document.getElementById('tcError');
        
        if (loading) loading.style.display = 'flex';
        if (tableContainer) tableContainer.style.display = 'none';
        if (empty) empty.style.display = 'none';
        if (error) error.style.display = 'none';
    }

    function showTable() {
        const loading = document.getElementById('tcLoading');
        const tableContainer = document.getElementById('tcTableContainer');
        const empty = document.getElementById('tcEmpty');
        const error = document.getElementById('tcError');
        
        if (loading) loading.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'block';
        if (empty) empty.style.display = 'none';
        if (error) error.style.display = 'none';
    }

    function showEmpty() {
        const loading = document.getElementById('tcLoading');
        const tableContainer = document.getElementById('tcTableContainer');
        const empty = document.getElementById('tcEmpty');
        const error = document.getElementById('tcError');
        
        if (loading) loading.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'none';
        if (empty) empty.style.display = 'flex';
        if (error) error.style.display = 'none';
        
        if (el.tableBody) el.tableBody.innerHTML = '';
    }

    function showError(message) {
        const loading = document.getElementById('tcLoading');
        const tableContainer = document.getElementById('tcTableContainer');
        const empty = document.getElementById('tcEmpty');
        const error = document.getElementById('tcError');
        const errorMessage = document.getElementById('tcErrorMessage');
        
        if (loading) loading.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'none';
        if (empty) empty.style.display = 'none';
        if (error) error.style.display = 'flex';
        if (errorMessage) errorMessage.textContent = message;
    }

    function updatePagination(page, total) {
        const paginationContainer = document.getElementById('tcPagination');
        const paginationInfo = document.getElementById('tcPaginationInfo');
        
        if (!paginationContainer || !paginationInfo) return;

        const totalPages = Math.ceil(total / state.perPage);
        const from = total > 0 ? ((page - 1) * state.perPage) + 1 : 0;
        const to = Math.min(page * state.perPage, total);

        paginationInfo.textContent = `Showing ${from}-${to} of ${total}`;

        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        html += `<button class="pagination-btn" ${page <= 1 ? 'disabled' : ''} onclick="TenantCategories.load(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="TenantCategories.load(${i})">${i}</button>`;
            } else if (i === page - 3 || i === page + 3) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }
        
        html += `<button class="pagination-btn" ${page >= totalPages ? 'disabled' : ''} onclick="TenantCategories.load(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
        
        paginationContainer.innerHTML = html;
    }

    function updateResultsCount(total) {
        const resultsCount = document.getElementById('tcResultsCount');
        if (resultsCount) {
            if (total > 0) {
                resultsCount.textContent = `${total} results found`;
                resultsCount.style.display = 'block';
            } else {
                resultsCount.style.display = 'none';
            }
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // ════════════════════════════════════════════════════════════
    // EDIT FUNCTION
    // ════════════════════════════════════════════════════════════
    
    async function editItem(id) {
        try {
            const result = await apiFetch(`${API}/${id}?format=json`);
            if (result.success && result.data) {
                let item = result.data;
                if (result.data.data) item = result.data.data;
                if (result.data.items) item = result.data.items[0];
                showForm(true, item);
            } else {
                showNotification('Error loading item', 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Edit error:', error);
            showNotification('Error loading item', 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    
    function init() {
        console.log('[TenantCategories] Initializing...');
        
        // Cache DOM elements
        el = {
            formContainer: document.getElementById('tenantCategoryFormContainer'),
            form: document.getElementById('tenantCategoryForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('tenantCategoryId'),
            tenantDisplay: document.getElementById('tenantCategoryTenantId'),
            tenantHidden: document.getElementById('tenantCategoryTenantIdHidden'),
            categorySelect: document.getElementById('tenantCategoryCategoryId'),
            categoryDisplay: document.getElementById('tenantCategoryCategoryIdText'),
            categoryHidden: document.getElementById('tenantCategoryCategoryIdHidden'),
            categorySearch: document.getElementById('tenantCategoryCategorySearch'),
            categoriesTreeContainer: document.getElementById('tenantCategoriesTreeContainer'),
            sortOrder: document.getElementById('tenantCategorySortOrder'),
            isActive: document.getElementById('tenantCategoryIsActive'),
            btnSave: document.getElementById('btnSaveTenantCategory'),
            btnCancel: document.getElementById('btnCancelTenantCategoryForm'),
            btnDelete: document.getElementById('btnDeleteTenantCategory'),
            btnClose: document.getElementById('btnCloseTenantCategoryForm'),
            tableBody: document.getElementById('tenantCategoryTableBody'),
            filterTenant: document.getElementById('tenantCategoryFilterTenant'),
            filterTenantHidden: document.getElementById('tenantCategoryFilterTenantHidden'),
            filterCategory: document.getElementById('tenantCategoryFilterCategory'),
            filterCategoryHidden: document.getElementById('tenantCategoryFilterCategoryHidden'),
            filterStatus: document.getElementById('tenantCategoryFilterStatus'),
            btnApply: document.getElementById('btnApplyTenantCategoryFilters'),
            btnReset: document.getElementById('btnResetTenantCategoryFilters'),
            btnAdd: document.getElementById('btnAddTenantCategory'),
            btnRetry: document.getElementById('btnRetryTenantCategories')
        };

        // Add empty state button
        const emptyBtn = document.getElementById('btnAddTenantCategoryEmpty');
        if (emptyBtn) {
            emptyBtn.onclick = () => showForm(false);
        }

        // Event handlers
        if (el.form) el.form.onsubmit = saveData;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadData(1);
        if (el.btnAdd) el.btnAdd.onclick = () => showForm(false);
        if (el.btnDelete) el.btnDelete.onclick = () => {
            if (el.formId && el.formId.value) {
                deleteData(parseInt(el.formId.value));
            }
        };

        // Tenant autocomplete
        if (el.tenantDisplay) {
            el.tenantDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('tenantsList', this.value);
                if (el.tenantHidden) el.tenantHidden.value = id || '';
            });
        }

        // Filter autocomplete
        if (el.filterTenant) {
            el.filterTenant.addEventListener('input', function() {
                const id = getIdFromDatalist('filterTenantsList', this.value);
                if (el.filterTenantHidden) el.filterTenantHidden.value = id || '';
            });
        }

        if (el.filterCategory) {
            el.filterCategory.addEventListener('input', function() {
                const id = getIdFromDatalist('filterCategoriesList', this.value);
                if (el.filterCategoryHidden) el.filterCategoryHidden.value = id || '';
            });
        }

        // Start loading
        loadTranslations().then(() => {
            loadDropdowns();
        });
    }

    // Public API
    window.TenantCategories = {
        init: init,
        load: loadData,
        edit: editItem,
        remove: deleteData,
        toggleStatus: toggleStatus,
        add: () => showForm(false)
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();