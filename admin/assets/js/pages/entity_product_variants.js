/**
 * Entity Product Variants – Two-Tab Module
 * Tab 1: Entity Products (entity_products + product_pricing)
 * Tab 2: Entity Product Variants (entity_product_variants)
 * Each tab has its own data source and save action.
 */
(function () {
    'use strict';

    var API = {
        entities:              '/api/entities',
        entityProducts:        '/api/entity_products',
        entityProductVariants: '/api/entity_product_variants',
        products:              '/api/products',
        productVariants:       '/api/product_variants',
        currencies:            '/api/currencies'
    };

    var state = {
        language:       'en',
        tenantId:       0,
        entityId:       0,
        isSuperAdmin:   false,
        canManage:      false,
        activeTab:      'products',
        entityProducts: [],
        entityVariants: [],
        allEntities:    [],
        currencies:     []
    };

    var el = {};
    var translations = {};

    // ════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════
    function init() {
        cacheElements();
        state.language     = (el.lang && el.lang.value) || 'en';
        state.tenantId     = parseInt(el.tenantId && el.tenantId.value) || 0;
        state.entityId     = parseInt(el.entityId && el.entityId.value) || 0;
        state.isSuperAdmin = el.isSuperAdmin && el.isSuperAdmin.value === '1';
        state.canManage    = el.canManage && el.canManage.value === '1';

        loadTranslations(state.language).then(function () {
            initEventListeners();
            loadCurrencies();

            // Auto-detect tenant: if tenantId is already set from session, use it directly
            if (state.tenantId > 0) {
                // For super admin, update the tenant input display
                if (state.isSuperAdmin && el.tenantIdInput) {
                    el.tenantIdInput.value = state.tenantId;
                    showTenantDisplay(state.tenantId);
                }
                // Load entities immediately
                loadEntities().then(function () {
                    if (state.entityId > 0) {
                        if (el.entityFilter) el.entityFilter.value = state.entityId;
                        showTabs();
                        loadEntityProducts();
                        loadEntityVariants();
                    }
                });
            } else if (state.isSuperAdmin) {
                // Super admin without tenant - wait for manual input
                // Do nothing, user will enter tenant ID
            }
        });
    }

    function cacheElements() {
        el = {
            container:            document.getElementById('epvPageContainer'),
            lang:                 document.getElementById('epvLang'),
            tenantId:             document.getElementById('epvTenantId'),
            entityId:             document.getElementById('epvEntityId'),
            canManage:            document.getElementById('epvCanManage'),
            isSuperAdmin:         document.getElementById('epvIsSuperAdmin'),
            csrf:                 document.getElementById('epvCsrfToken'),

            entityFilter:         document.getElementById('epvEntityFilter'),
            tenantIdInput:        document.getElementById('epvTenantIdInput'),
            btnVerifyTenant:      document.getElementById('epvBtnVerifyTenant'),
            tenantNameDisplay:    document.getElementById('epvTenantNameDisplay'),

            tabsContainer:        document.getElementById('epvTabsContainer'),
            tabProducts:          document.getElementById('epvTabProducts'),
            tabVariants:          document.getElementById('epvTabVariants'),
            productsContent:      document.getElementById('epvProductsContent'),
            variantsContent:      document.getElementById('epvVariantsContent'),
            productsCountBadge:   document.getElementById('epvProductsCount'),
            variantsCountBadge:   document.getElementById('epvVariantsCount'),

            productSearch:        document.getElementById('epvProductSearch'),
            btnAddProduct:        document.getElementById('epvBtnAddProduct'),
            productsList:         document.getElementById('epvProductsList'),
            productsEmpty:        document.getElementById('epvProductsEmpty'),
            productsFooter:       document.getElementById('epvProductsFooter'),
            btnSaveProducts:      document.getElementById('epvBtnSaveProducts'),

            variantSearch:        document.getElementById('epvVariantSearch'),
            btnAddVariant:        document.getElementById('epvBtnAddVariant'),
            variantsList:         document.getElementById('epvVariantsList'),
            variantsEmpty:        document.getElementById('epvVariantsEmpty'),
            variantsFooter:       document.getElementById('epvVariantsFooter'),
            btnSaveVariants:      document.getElementById('epvBtnSaveVariants'),

            productsModal:         document.getElementById('epvProductsModal'),
            closeProductsModal:    document.getElementById('epvCloseProductsModal'),
            modalProductSearch:    document.getElementById('epvModalProductSearch'),
            selectAllProducts:     document.getElementById('epvSelectAllProducts'),
            deselectAllProducts:   document.getElementById('epvDeselectAllProducts'),
            productSelectedCount:  document.getElementById('epvProductSelectedCount'),
            modalProductsList:     document.getElementById('epvModalProductsList'),
            confirmProductSel:     document.getElementById('epvConfirmProductSelection'),
            cancelProductSel:      document.getElementById('epvCancelProductSelection'),

            variantsModal:          document.getElementById('epvVariantsModal'),
            closeVariantsModal:     document.getElementById('epvCloseVariantsModal'),
            modalVarProductFilter:  document.getElementById('epvModalVariantProductFilter'),
            selectAllVariants:      document.getElementById('epvSelectAllVariants'),
            deselectAllVariants:    document.getElementById('epvDeselectAllVariants'),
            variantSelectedCount:   document.getElementById('epvVariantSelectedCount'),
            modalVariantsList:      document.getElementById('epvModalVariantsList'),
            confirmVariantSel:      document.getElementById('epvConfirmVariantSelection'),
            cancelVariantSel:       document.getElementById('epvCancelVariantSelection')
        };
    }

    // ════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════
    function loadTranslations(lang) {
        return fetch('/languages/EntityProductVariants/' + encodeURIComponent(lang) + '.json')
            .then(function (res) { return res.ok ? res.json() : {}; })
            .then(function (json) { translations = (json && json.strings) || {}; })
            .catch(function () { translations = {}; });
    }

    function t(key, fallback) {
        var keys = key.split('.');
        var val = translations;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    // ════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════
    function apiCall(url, options) {
        options = options || {};
        var csrf = el.csrf ? el.csrf.value : '';
        var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) headers['X-CSRF-Token'] = csrf;
        options.headers = Object.assign({}, headers, options.headers || {});
        if (options.body && typeof options.body === 'object') {
            options.body = JSON.stringify(options.body);
        }
        return fetch(url, options).then(function (res) { return res.json(); });
    }

    function showTenantDisplay(tenantId, entityCount) {
        if (!el.tenantNameDisplay) return;
        el.tenantNameDisplay.style.display = 'block';
        if (entityCount !== undefined) {
            el.tenantNameDisplay.textContent = t('filter.tenant_verified', 'Tenant') + ' #' + tenantId + ' (' + entityCount + ' ' + t('filter.entities_found', 'entities') + ')';
        } else {
            el.tenantNameDisplay.textContent = t('filter.tenant_verified', 'Tenant') + ' #' + tenantId;
        }
        el.tenantNameDisplay.className = 'epv-tenant-verified';
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'epv-toast epv-toast-' + (type || 'success');
        toast.textContent = message;
        if (el.container) el.container.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3500);
    }

    // ════════════════════════════════════════
    // TAB MANAGEMENT
    // ════════════════════════════════════════
    function showTabs() {
        if (el.tabsContainer) el.tabsContainer.style.display = '';
    }

    function hideTabs() {
        if (el.tabsContainer) el.tabsContainer.style.display = 'none';
    }

    function switchTab(tabName) {
        state.activeTab = tabName;
        var tabs = el.container ? el.container.querySelectorAll('.epv-tab') : [];
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === tabName);
        }
        var contents = el.container ? el.container.querySelectorAll('.epv-tab-content') : [];
        for (var j = 0; j < contents.length; j++) {
            contents[j].classList.toggle('active', contents[j].getAttribute('data-tab') === tabName);
        }
    }

    // ════════════════════════════════════════
    // EVENT LISTENERS
    // ════════════════════════════════════════
    function initEventListeners() {
        if (el.tabProducts) el.tabProducts.addEventListener('click', function () { switchTab('products'); });
        if (el.tabVariants) el.tabVariants.addEventListener('click', function () { switchTab('variants'); });

        if (el.entityFilter) {
            el.entityFilter.addEventListener('change', function () {
                state.entityId = parseInt(this.value) || 0;
                if (state.entityId > 0) {
                    showTabs();
                    loadEntityProducts();
                    loadEntityVariants();
                } else {
                    hideTabs();
                    state.entityProducts = [];
                    state.entityVariants = [];
                }
            });
        }

        if (el.btnVerifyTenant) el.btnVerifyTenant.addEventListener('click', verifyTenant);

        if (el.btnAddProduct) el.btnAddProduct.addEventListener('click', openProductsModal);
        if (el.btnSaveProducts) el.btnSaveProducts.addEventListener('click', saveProducts);
        if (el.productSearch) el.productSearch.addEventListener('input', renderProductsList);

        if (el.btnAddVariant) el.btnAddVariant.addEventListener('click', openVariantsModal);
        if (el.btnSaveVariants) el.btnSaveVariants.addEventListener('click', saveVariants);
        if (el.variantSearch) el.variantSearch.addEventListener('input', renderVariantsList);

        if (el.closeProductsModal) el.closeProductsModal.addEventListener('click', closeProductsModal);
        if (el.cancelProductSel) el.cancelProductSel.addEventListener('click', closeProductsModal);
        if (el.confirmProductSel) el.confirmProductSel.addEventListener('click', confirmProductSelection);
        if (el.selectAllProducts) el.selectAllProducts.addEventListener('click', function () { toggleAllModalProducts(true); });
        if (el.deselectAllProducts) el.deselectAllProducts.addEventListener('click', function () { toggleAllModalProducts(false); });
        if (el.modalProductSearch) el.modalProductSearch.addEventListener('input', filterModalProducts);

        if (el.closeVariantsModal) el.closeVariantsModal.addEventListener('click', closeVariantsModal);
        if (el.cancelVariantSel) el.cancelVariantSel.addEventListener('click', closeVariantsModal);
        if (el.confirmVariantSel) el.confirmVariantSel.addEventListener('click', confirmVariantSelection);
        if (el.selectAllVariants) el.selectAllVariants.addEventListener('click', function () { toggleAllModalVariants(true); });
        if (el.deselectAllVariants) el.deselectAllVariants.addEventListener('click', function () { toggleAllModalVariants(false); });
        if (el.modalVarProductFilter) el.modalVarProductFilter.addEventListener('change', loadModalVariants);
    }

    // ════════════════════════════════════════
    // ENTITY LOADING
    // ════════════════════════════════════════
    function loadEntities() {
        var url = API.entities + '?limit=500&lang=' + encodeURIComponent(state.language);
        if (state.tenantId > 0) url += '&tenant_id=' + state.tenantId;
        return apiCall(url).then(function (res) {
            var items = (res && res.data && res.data.items) || (res && res.data) || [];
            items = Array.isArray(items) ? items : [];
            state.allEntities = items;
            populateEntityDropdown(items);
        }).catch(function (e) {
            console.error('Failed to load entities:', e);
            state.allEntities = [];
            populateEntityDropdown([]);
        });
    }

    function populateEntityDropdown(entities) {
        if (!el.entityFilter) return;
        el.entityFilter.innerHTML = '<option value="">' + t('filter.select_entity', 'Select Entity...') + '</option>';
        for (var i = 0; i < entities.length; i++) {
            var ent = entities[i];
            var name = ent.store_name || ent.name || ('Entity #' + ent.id);
            var opt = document.createElement('option');
            opt.value = ent.id;
            opt.textContent = name + (ent.branch_code ? ' (' + ent.branch_code + ')' : '');
            el.entityFilter.appendChild(opt);
        }
        if (state.entityId > 0) {
            el.entityFilter.value = state.entityId;
        }
    }

    // ════════════════════════════════════════
    // CURRENCIES
    // ════════════════════════════════════════
    function loadCurrencies() {
        return apiCall(API.currencies).then(function (res) {
            var items = (res && res.data && res.data.items) || (res && res.data) || [];
            state.currencies = Array.isArray(items) ? items : [];
        }).catch(function (e) {
            console.error('Failed to load currencies:', e);
            state.currencies = [];
        });
    }

    function buildCurrencySelect(selectedCode, onchangeAttr) {
        var html = '<select ' + onchangeAttr + '>';
        html += '<option value="">' + t('products.select_currency', '-- Currency --') + '</option>';
        for (var i = 0; i < state.currencies.length; i++) {
            var c = state.currencies[i];
            var code = c.code || c.currency_code || '';
            var name = c.name || c.currency_name || code;
            var sel = (code === selectedCode) ? ' selected' : '';
            html += '<option value="' + escHtml(code) + '"' + sel + '>' + escHtml(code) + ' - ' + escHtml(name) + '</option>';
        }
        html += '</select>';
        return html;
    }

    function verifyTenant() {
        var tid = parseInt(el.tenantIdInput ? el.tenantIdInput.value : 0) || 0;
        if (tid <= 0) {
            showToast(t('messages.invalid_tenant', 'Please enter a valid Tenant ID'), 'error');
            return;
        }
        state.tenantId = tid;
        // Update hidden field so save operations use the correct tenant
        if (el.tenantId) el.tenantId.value = tid;

        if (el.tenantNameDisplay) {
            el.tenantNameDisplay.style.display = 'block';
            el.tenantNameDisplay.textContent = t('filter.loading', 'Loading...');
            el.tenantNameDisplay.className = '';
        }
        state.entityId = 0;
        hideTabs();

        // Load entities for this tenant to verify it exists
        loadEntities().then(function () {
            if (state.allEntities.length > 0) {
                showTenantDisplay(tid, state.allEntities.length);
            } else {
                if (el.tenantNameDisplay) {
                    el.tenantNameDisplay.textContent = t('messages.no_entities_for_tenant', 'No entities found for this tenant');
                    el.tenantNameDisplay.className = 'epv-tenant-error';
                }
            }
        }).catch(function () {
            if (el.tenantNameDisplay) {
                el.tenantNameDisplay.textContent = t('messages.tenant_verify_failed', 'Failed to verify tenant');
                el.tenantNameDisplay.className = 'epv-tenant-error';
            }
        });
    }

    // ════════════════════════════════════════
    // TAB 1: ENTITY PRODUCTS
    // ════════════════════════════════════════
    function loadEntityProducts() {
        if (!state.entityId) return;
        return apiCall(API.entityProducts + '?action=entity&entity_id=' + state.entityId)
            .then(function (res) {
                state.entityProducts = (res && res.data) || [];
                updateProductsCount();
                renderProductsList();
            })
            .catch(function (e) {
                console.error('Failed to load entity products:', e);
                showToast(t('messages.load_failed', 'Failed to load data'), 'error');
            });
    }

    function updateProductsCount() {
        if (el.productsCountBadge) el.productsCountBadge.textContent = state.entityProducts.length;
    }

    function renderProductsList() {
        if (!el.productsList) return;
        var searchVal = (el.productSearch ? el.productSearch.value : '').toLowerCase();
        var items = state.entityProducts;

        if (searchVal) {
            items = items.filter(function (p) {
                return (p.product_name || '').toLowerCase().indexOf(searchVal) >= 0 ||
                       (p.product_sku || '').toLowerCase().indexOf(searchVal) >= 0;
            });
        }

        if (items.length === 0) {
            el.productsList.innerHTML = '';
            if (el.productsEmpty) el.productsEmpty.style.display = '';
            if (el.productsFooter) el.productsFooter.style.display = 'none';
            return;
        }

        if (el.productsEmpty) el.productsEmpty.style.display = 'none';
        if (el.productsFooter) el.productsFooter.style.display = '';

        var html = '';
        for (var i = 0; i < items.length; i++) {
            var p = items[i];
            var name = p.product_name || ('Product #' + p.product_id);
            var pid = parseInt(p.product_id);
            var safeIdx = parseInt(i);

            html += '<div class="epv-item-card" data-product-id="' + pid + '">';
            html += '<div class="epv-item-header">' +
                '<div class="epv-item-title">' +
                    '<div class="epv-item-name">' + escHtml(name) + '</div>' +
                    '<div class="epv-item-meta">' +
                        (p.product_sku ? '<span>SKU: ' + escHtml(p.product_sku) + '</span>' : '') +
                        '<span class="epv-badge ' + (p.is_active == 1 ? 'epv-badge-success' : 'epv-badge-danger') + '">' +
                            (p.is_active == 1 ? t('filter.active', 'Active') : t('filter.inactive', 'Inactive')) +
                        '</span>' +
                    '</div>' +
                '</div>' +
                (state.canManage ? '<button class="epv-btn-remove" onclick="EntityProductVariants._removeProduct(' + safeIdx + ')">' +
                    t('products.remove', 'Remove') + '</button>' : '') +
            '</div>';

            html += '<div class="epv-item-fields">' +
                '<div class="epv-field"><label>' + t('products.stock_quantity', 'Stock') + '</label>' +
                    '<input type="number" value="' + (p.stock_quantity != null ? p.stock_quantity : 0) + '" min="0" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'stock_quantity\',this.value)"></div>' +
                '<div class="epv-field"><label>' + t('products.low_stock_threshold', 'Low Stock') + '</label>' +
                    '<input type="number" value="' + (p.low_stock_threshold != null ? p.low_stock_threshold : 5) + '" min="0" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'low_stock_threshold\',this.value)"></div>' +
                '<div class="epv-field"><label>' + t('products.is_active', 'Active') + '</label>' +
                    '<input type="checkbox"' + (p.is_active == 1 ? ' checked' : '') + ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'is_active\',this.checked?1:0)"></div>' +
                '<div class="epv-field"><label>' + t('products.is_featured', 'Featured') + '</label>' +
                    '<input type="checkbox"' + (p.is_featured == 1 ? ' checked' : '') + ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'is_featured\',this.checked?1:0)"></div>' +
            '</div>';

            var price = p.price || '';
            var comparePrice = p.compare_at_price || '';
            var costPrice = p.cost_price || '';
            var currencyCode = p.currency_code || '';
            var taxRate = p.tax_rate || '';

            html += '<div class="epv-pricing"><div class="epv-pricing-label">' + t('products.pricing', 'Pricing') + '</div>' +
                '<div class="epv-pricing-fields">' +
                    '<div class="epv-field"><label>' + t('products.price', 'Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(price) + '" min="0" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'price\',this.value)"></div>' +
                    '<div class="epv-field"><label>' + t('products.compare_at_price', 'Compare Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(comparePrice) + '" min="0" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'compare_at_price\',this.value)"></div>' +
                    '<div class="epv-field"><label>' + t('products.cost_price', 'Cost Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(costPrice) + '" min="0" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'cost_price\',this.value)"></div>' +
                    '<div class="epv-field"><label>' + t('products.currency_code', 'Currency') + '</label>' +
                        buildCurrencySelect(currencyCode, 'onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'currency_code\',this.value)"') + '</div>' +
                    '<div class="epv-field"><label>' + t('products.tax_rate', 'Tax %') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(taxRate) + '" min="0" max="100" onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'tax_rate\',this.value)"></div>' +
                '</div></div>';

            html += '</div>';
        }

        el.productsList.innerHTML = html;
    }

    // ════════════════════════════════════════
    // TAB 2: ENTITY VARIANTS
    // ════════════════════════════════════════
    function loadEntityVariants() {
        if (!state.entityId) return;
        return apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId)
            .then(function (res) {
                state.entityVariants = (res && res.data) || [];
                updateVariantsCount();
                renderVariantsList();
            })
            .catch(function (e) {
                console.error('Failed to load entity variants:', e);
                showToast(t('messages.load_failed', 'Failed to load data'), 'error');
            });
    }

    function updateVariantsCount() {
        if (el.variantsCountBadge) el.variantsCountBadge.textContent = state.entityVariants.length;
    }

    function renderVariantsList() {
        if (!el.variantsList) return;
        var searchVal = (el.variantSearch ? el.variantSearch.value : '').toLowerCase();
        var items = state.entityVariants;

        if (searchVal) {
            items = items.filter(function (v) {
                return (v.product_name || '').toLowerCase().indexOf(searchVal) >= 0 ||
                       (v.variant_sku || '').toLowerCase().indexOf(searchVal) >= 0;
            });
        }

        if (items.length === 0) {
            el.variantsList.innerHTML = '';
            if (el.variantsEmpty) el.variantsEmpty.style.display = '';
            if (el.variantsFooter) el.variantsFooter.style.display = 'none';
            return;
        }

        if (el.variantsEmpty) el.variantsEmpty.style.display = 'none';
        if (el.variantsFooter) el.variantsFooter.style.display = '';

        // Group by product_id
        var groups = {};
        var groupOrder = [];
        for (var i = 0; i < items.length; i++) {
            var v = items[i];
            var pid = parseInt(v.product_id);
            if (!groups[pid]) {
                groups[pid] = { name: v.product_name || ('Product #' + pid), items: [] };
                groupOrder.push(pid);
            }
            groups[pid].items.push(v);
        }

        var html = '';
        for (var g = 0; g < groupOrder.length; g++) {
            var gpid = groupOrder[g];
            var group = groups[gpid];

            html += '<div class="epv-variant-group">';
            html += '<div class="epv-variant-group-header">' + escHtml(group.name) + '</div>';
            html += '<div class="epv-variant-group-items">';

            for (var vi = 0; vi < group.items.length; vi++) {
                var vItem = group.items[vi];
                var realIdx = state.entityVariants.indexOf(vItem);
                var safeVIdx = parseInt(realIdx);
                var stockStatusOpts = ['in_stock', 'out_of_stock', 'unlimited'];
                var varLabel = vItem.variant_sku || ('Variant #' + vItem.variant_id);

                html += '<div class="epv-item-card" data-variant-id="' + parseInt(vItem.variant_id) + '">';
                html += '<div class="epv-item-header">' +
                    '<div class="epv-item-title">' +
                        '<div class="epv-item-name">' + escHtml(varLabel) + '</div>' +
                        '<div class="epv-item-meta">' +
                            (vItem.variant_price ? '<span>' + t('products.price', 'Price') + ': ' + escHtml(String(vItem.variant_price)) + '</span>' : '') +
                            '<span class="epv-badge ' + (vItem.is_active == 1 ? 'epv-badge-success' : 'epv-badge-danger') + '">' +
                                (vItem.is_active == 1 ? t('filter.active', 'Active') : t('filter.inactive', 'Inactive')) +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    (state.canManage ? '<button class="epv-btn-remove" onclick="EntityProductVariants._removeVariant(' + safeVIdx + ')">&times;</button>' : '') +
                '</div>';

                html += '<div class="epv-item-fields">';
                html += '<div class="epv-field"><label>' + t('variants.stock_quantity', 'Stock') + '</label>' +
                    '<input type="number" value="' + (vItem.stock_quantity != null ? vItem.stock_quantity : 0) + '" min="0" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'stock_quantity\',this.value)"></div>';
                html += '<div class="epv-field"><label>' + t('variants.low_stock_threshold', 'Low Stock') + '</label>' +
                    '<input type="number" value="' + (vItem.low_stock_threshold != null ? vItem.low_stock_threshold : 5) + '" min="0" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'low_stock_threshold\',this.value)"></div>';
                html += '<div class="epv-field"><label>' + t('variants.stock_status', 'Status') + '</label><select onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'stock_status\',this.value)">';
                for (var si = 0; si < stockStatusOpts.length; si++) {
                    html += '<option value="' + stockStatusOpts[si] + '"' + (vItem.stock_status === stockStatusOpts[si] ? ' selected' : '') + '>' + t('variants.' + stockStatusOpts[si], stockStatusOpts[si]) + '</option>';
                }
                html += '</select></div>';
                html += '<div class="epv-field"><label>' + t('variants.manage_stock', 'Manage') + '</label>' +
                    '<input type="checkbox"' + (vItem.manage_stock == 1 ? ' checked' : '') + ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'manage_stock\',this.checked?1:0)"></div>';
                html += '<div class="epv-field"><label>' + t('variants.is_active', 'Active') + '</label>' +
                    '<input type="checkbox"' + (vItem.is_active == 1 ? ' checked' : '') + ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'is_active\',this.checked?1:0)"></div>';
                html += '<div class="epv-field"><label>' + t('variants.is_featured', 'Featured') + '</label>' +
                    '<input type="checkbox"' + (vItem.is_featured == 1 ? ' checked' : '') + ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'is_featured\',this.checked?1:0)"></div>';
                html += '</div>';

                // Variant pricing section
                var vPrice = vItem.price || '';
                var vComparePrice = vItem.compare_at_price || '';
                var vCostPrice = vItem.cost_price || '';
                var vCurrencyCode = vItem.currency_code || '';
                var vTaxRate = vItem.tax_rate || '';

                html += '<div class="epv-pricing"><div class="epv-pricing-label">' + t('products.pricing', 'Pricing') + '</div>' +
                    '<div class="epv-pricing-fields">' +
                        '<div class="epv-field"><label>' + t('products.price', 'Price') + '</label>' +
                            '<input type="number" step="0.01" value="' + escHtml(vPrice) + '" min="0" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'price\',this.value)"></div>' +
                        '<div class="epv-field"><label>' + t('products.compare_at_price', 'Compare Price') + '</label>' +
                            '<input type="number" step="0.01" value="' + escHtml(vComparePrice) + '" min="0" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'compare_at_price\',this.value)"></div>' +
                        '<div class="epv-field"><label>' + t('products.cost_price', 'Cost Price') + '</label>' +
                            '<input type="number" step="0.01" value="' + escHtml(vCostPrice) + '" min="0" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'cost_price\',this.value)"></div>' +
                        '<div class="epv-field"><label>' + t('products.currency_code', 'Currency') + '</label>' +
                            buildCurrencySelect(vCurrencyCode, 'onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'currency_code\',this.value)"') + '</div>' +
                        '<div class="epv-field"><label>' + t('products.tax_rate', 'Tax %') + '</label>' +
                            '<input type="number" step="0.01" value="' + escHtml(vTaxRate) + '" min="0" max="100" onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'tax_rate\',this.value)"></div>' +
                    '</div></div>';

                html += '</div>';
            }

            html += '</div></div>';
        }

        el.variantsList.innerHTML = html;
    }

    // ════════════════════════════════════════
    // PRODUCT CRUD
    // ════════════════════════════════════════
    function updateProduct(index, field, value) {
        if (state.entityProducts[index]) {
            state.entityProducts[index][field] = value;
        }
    }

    function removeProduct(index) {
        if (!confirm(t('products.confirm_remove', 'Remove this product?'))) return;
        var product = state.entityProducts[index];
        if (product && product.id) {
            apiCall(API.entityProducts + '?id=' + parseInt(product.id), { method: 'DELETE' })
                .then(function () { showToast(t('messages.product_deleted', 'Product removed'), 'success'); })
                .catch(function () { showToast(t('messages.delete_failed', 'Delete failed'), 'error'); });
        }
        state.entityProducts.splice(index, 1);
        updateProductsCount();
        renderProductsList();
    }

    // ════════════════════════════════════════
    // VARIANT CRUD
    // ════════════════════════════════════════
    function updateVariant(index, field, value) {
        if (state.entityVariants[index]) {
            state.entityVariants[index][field] = value;
        }
    }

    function removeVariant(index) {
        if (!confirm(t('variants.confirm_remove', 'Remove this variant?'))) return;
        var variant = state.entityVariants[index];
        if (variant && variant.id) {
            apiCall(API.entityProductVariants + '?id=' + parseInt(variant.id), { method: 'DELETE' })
                .then(function () { showToast(t('messages.variant_deleted', 'Variant removed'), 'success'); })
                .catch(function () { showToast(t('messages.delete_failed', 'Delete failed'), 'error'); });
        }
        state.entityVariants.splice(index, 1);
        updateVariantsCount();
        renderVariantsList();
    }

    // ════════════════════════════════════════
    // SAVE PRODUCTS
    // ════════════════════════════════════════
    function saveProducts() {
        if (!state.entityId || !state.tenantId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }

        var payload = state.entityProducts.map(function (p) {
            return {
                product_id:          parseInt(p.product_id),
                stock_quantity:      parseInt(p.stock_quantity) || 0,
                low_stock_threshold: parseInt(p.low_stock_threshold) || 5,
                is_active:           p.is_active == 1 ? 1 : 0,
                is_featured:         p.is_featured == 1 ? 1 : 0,
                price:               p.price !== undefined && p.price !== '' ? p.price : null,
                compare_at_price:    p.compare_at_price !== undefined && p.compare_at_price !== '' ? p.compare_at_price : null,
                cost_price:          p.cost_price !== undefined && p.cost_price !== '' ? p.cost_price : null,
                currency_code:       p.currency_code !== undefined && p.currency_code !== '' ? p.currency_code : null,
                tax_rate:            p.tax_rate !== undefined && p.tax_rate !== '' ? p.tax_rate : null
            };
        });

        var url = API.entityProducts + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
        apiCall(url, { method: 'POST', body: payload })
            .then(function () {
                showToast(t('messages.products_saved', 'Products saved'), 'success');
                loadEntityProducts();
            })
            .catch(function (e) {
                console.error('Save products failed:', e);
                showToast(t('messages.save_failed', 'Save failed'), 'error');
            });
    }

    // ════════════════════════════════════════
    // SAVE VARIANTS
    // ════════════════════════════════════════
    function saveVariants() {
        if (!state.entityId || !state.tenantId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }

        apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId, { method: 'DELETE' })
            .then(function () {
                if (state.entityVariants.length === 0) {
                    showToast(t('messages.variants_saved', 'Variants saved'), 'success');
                    return;
                }

                var payload = state.entityVariants.map(function (v) {
                    return {
                        product_id:          parseInt(v.product_id),
                        variant_id:          parseInt(v.variant_id),
                        stock_quantity:      parseInt(v.stock_quantity) || 0,
                        low_stock_threshold: parseInt(v.low_stock_threshold) || 5,
                        manage_stock:        v.manage_stock == 1 ? 1 : 0,
                        stock_status:        v.stock_status || 'in_stock',
                        is_active:           v.is_active == 1 ? 1 : 0,
                        is_featured:         v.is_featured == 1 ? 1 : 0,
                        price:               v.price !== undefined && v.price !== '' ? v.price : null,
                        compare_at_price:    v.compare_at_price !== undefined && v.compare_at_price !== '' ? v.compare_at_price : null,
                        cost_price:          v.cost_price !== undefined && v.cost_price !== '' ? v.cost_price : null,
                        currency_code:       v.currency_code !== undefined && v.currency_code !== '' ? v.currency_code : null,
                        tax_rate:            v.tax_rate !== undefined && v.tax_rate !== '' ? v.tax_rate : null
                    };
                });

                var url = API.entityProductVariants + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
                return apiCall(url, { method: 'POST', body: payload });
            })
            .then(function () {
                showToast(t('messages.variants_saved', 'Variants saved'), 'success');
                loadEntityVariants();
            })
            .catch(function (e) {
                console.error('Save variants failed:', e);
                showToast(t('messages.save_failed', 'Save failed'), 'error');
            });
    }

    // ════════════════════════════════════════
    // PRODUCTS MODAL
    // ════════════════════════════════════════
    function openProductsModal() {
        if (!state.entityId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        if (el.productsModal) el.productsModal.style.display = '';
        if (el.modalProductSearch) el.modalProductSearch.value = '';
        if (el.modalProductsList) el.modalProductsList.innerHTML = '<div class="epv-loading">' + t('products.loading_products', 'Loading...') + '</div>';

        var url = API.products + '?limit=1000';
        if (state.tenantId) url += '&tenant_id=' + state.tenantId;
        apiCall(url).then(function (res) {
            var products = (res && res.data && res.data.items) || (res && res.data) || [];
            var existingIds = state.entityProducts.map(function (p) { return parseInt(p.product_id); });

            if (products.length === 0) {
                el.modalProductsList.innerHTML = '<div class="epv-loading">' + t('products.no_products_found', 'No products found') + '</div>';
                return;
            }

            el.modalProductsList.innerHTML = products.map(function (p) {
                var pid = parseInt(p.id);
                var isAdded = existingIds.indexOf(pid) >= 0;
                var name = p.name || p.product_name || ('Product #' + pid);
                return '<div class="epv-modal-item' + (isAdded ? ' disabled' : '') + '" data-id="' + pid + '">' +
                    '<input type="checkbox"' + (isAdded ? ' disabled checked' : '') + '>' +
                    '<div class="epv-modal-item-info">' +
                        '<div class="epv-modal-item-name">' + escHtml(name) + '</div>' +
                        '<div class="epv-modal-item-meta">' + (p.sku ? 'SKU: ' + escHtml(p.sku) : '') + '</div>' +
                    '</div>' +
                    (isAdded ? '<span class="epv-modal-item-badge">' + t('products.already_added', 'Added') + '</span>' : '') +
                '</div>';
            }).join('');

            attachModalItemListeners(el.modalProductsList, updateProductSelectedCount);
        }).catch(function () {
            el.modalProductsList.innerHTML = '<div class="epv-loading">' + t('messages.load_failed', 'Failed to load') + '</div>';
        });
    }

    function closeProductsModal() {
        if (el.productsModal) el.productsModal.style.display = 'none';
    }

    function filterModalProducts() {
        var q = (el.modalProductSearch ? el.modalProductSearch.value : '').toLowerCase();
        var items = el.modalProductsList ? el.modalProductsList.querySelectorAll('.epv-modal-item') : [];
        for (var i = 0; i < items.length; i++) {
            var nameEl = items[i].querySelector('.epv-modal-item-name');
            var metaEl = items[i].querySelector('.epv-modal-item-meta');
            var name = nameEl ? nameEl.textContent.toLowerCase() : '';
            var meta = metaEl ? metaEl.textContent.toLowerCase() : '';
            items[i].style.display = (name.indexOf(q) >= 0 || meta.indexOf(q) >= 0) ? '' : 'none';
        }
    }

    function toggleAllModalProducts(checked) {
        var cbs = el.modalProductsList ? el.modalProductsList.querySelectorAll('.epv-modal-item:not(.disabled) input[type="checkbox"]') : [];
        for (var i = 0; i < cbs.length; i++) {
            cbs[i].checked = checked;
            cbs[i].closest('.epv-modal-item').classList.toggle('selected', checked);
        }
        updateProductSelectedCount();
    }

    function updateProductSelectedCount() {
        var count = el.modalProductsList ? el.modalProductsList.querySelectorAll('.epv-modal-item:not(.disabled) input:checked').length : 0;
        if (el.productSelectedCount) el.productSelectedCount.textContent = t('products.selected_count', count + ' selected').replace('{count}', count);
    }

    function confirmProductSelection() {
        var selected = [];
        var checkedItems = el.modalProductsList ? el.modalProductsList.querySelectorAll('.epv-modal-item:not(.disabled) input:checked') : [];
        for (var i = 0; i < checkedItems.length; i++) {
            var item = checkedItems[i].closest('.epv-modal-item');
            var pid = parseInt(item.getAttribute('data-id'));
            var nameEl = item.querySelector('.epv-modal-item-name');
            var metaEl = item.querySelector('.epv-modal-item-meta');
            selected.push({
                product_id: pid,
                product_name: nameEl ? nameEl.textContent : '',
                product_sku: (metaEl ? metaEl.textContent : '').replace('SKU: ', ''),
                stock_quantity: 0,
                low_stock_threshold: 5,
                is_active: 1,
                is_featured: 0,
                price: '',
                compare_at_price: '',
                cost_price: '',
                currency_code: '',
                tax_rate: ''
            });
        }
        state.entityProducts = state.entityProducts.concat(selected);
        updateProductsCount();
        closeProductsModal();
        renderProductsList();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('products.add_selected', 'products added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // VARIANTS MODAL
    // ════════════════════════════════════════
    function openVariantsModal() {
        if (!state.entityId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        if (state.entityProducts.length === 0) {
            showToast(t('variants.select_product_first', 'Add products first'), 'error');
            return;
        }
        if (el.variantsModal) el.variantsModal.style.display = '';
        populateVariantProductFilter();
        if (el.modalVarProductFilter) el.modalVarProductFilter.value = '';
        if (el.modalVariantsList) {
            el.modalVariantsList.innerHTML = '<div class="epv-loading">' + t('variants.select_product_to_see_variants', 'Select a product to see variants') + '</div>';
        }
    }

    function closeVariantsModal() {
        if (el.variantsModal) el.variantsModal.style.display = 'none';
    }

    function populateVariantProductFilter() {
        var dd = el.modalVarProductFilter;
        if (!dd) return;
        dd.innerHTML = '<option value="">' + t('variants.select_product_first', 'Select product...') + '</option>';
        for (var i = 0; i < state.entityProducts.length; i++) {
            var p = state.entityProducts[i];
            var name = p.product_name || ('Product #' + p.product_id);
            var opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = name;
            dd.appendChild(opt);
        }
    }

    function loadModalVariants() {
        var productId = parseInt(el.modalVarProductFilter ? el.modalVarProductFilter.value : 0) || 0;
        if (!productId) {
            if (el.modalVariantsList) {
                el.modalVariantsList.innerHTML = '<div class="epv-loading">' + t('variants.select_product_to_see_variants', 'Select a product') + '</div>';
            }
            return;
        }

        if (el.modalVariantsList) {
            el.modalVariantsList.innerHTML = '<div class="epv-loading">' + t('variants.loading_variants', 'Loading...') + '</div>';
        }

        apiCall(API.productVariants + '?product_id=' + productId + '&limit=500')
            .then(function (res) {
                var variants = (res && res.data && res.data.items) || (res && res.data) || [];
                var existingIds = state.entityVariants.map(function (v) { return parseInt(v.variant_id); });

                if (variants.length === 0) {
                    el.modalVariantsList.innerHTML = '<div class="epv-loading">' + t('variants.no_variants_found', 'No variants found') + '</div>';
                    return;
                }

                el.modalVariantsList.innerHTML = variants.map(function (v) {
                    var vid = parseInt(v.id);
                    var isAdded = existingIds.indexOf(vid) >= 0;
                    var label = v.sku || v.barcode || ('Variant #' + vid);
                    return '<div class="epv-modal-item' + (isAdded ? ' disabled' : '') + '" data-id="' + vid + '" data-product-id="' + productId + '">' +
                        '<input type="checkbox"' + (isAdded ? ' disabled checked' : '') + '>' +
                        '<div class="epv-modal-item-info">' +
                            '<div class="epv-modal-item-name">' + escHtml(label) + '</div>' +
                            '<div class="epv-modal-item-meta">' +
                                (v.barcode ? 'Barcode: ' + escHtml(v.barcode) : '') +
                                (v.is_default == 1 ? ' (Default)' : '') +
                            '</div>' +
                        '</div>' +
                        (isAdded ? '<span class="epv-modal-item-badge">' + t('variants.already_added', 'Added') + '</span>' : '') +
                    '</div>';
                }).join('');

                attachModalItemListeners(el.modalVariantsList, updateVariantSelectedCount);
                updateVariantSelectedCount();
            })
            .catch(function () {
                if (el.modalVariantsList) {
                    el.modalVariantsList.innerHTML = '<div class="epv-loading">' + t('messages.load_failed', 'Failed to load') + '</div>';
                }
            });
    }

    function toggleAllModalVariants(checked) {
        var cbs = el.modalVariantsList ? el.modalVariantsList.querySelectorAll('.epv-modal-item:not(.disabled) input[type="checkbox"]') : [];
        for (var i = 0; i < cbs.length; i++) {
            cbs[i].checked = checked;
            cbs[i].closest('.epv-modal-item').classList.toggle('selected', checked);
        }
        updateVariantSelectedCount();
    }

    function updateVariantSelectedCount() {
        var count = el.modalVariantsList ? el.modalVariantsList.querySelectorAll('.epv-modal-item:not(.disabled) input:checked').length : 0;
        if (el.variantSelectedCount) el.variantSelectedCount.textContent = t('variants.selected_count', count + ' selected').replace('{count}', count);
    }

    function confirmVariantSelection() {
        var productId = parseInt(el.modalVarProductFilter ? el.modalVarProductFilter.value : 0) || 0;
        if (!productId) return;

        var product = null;
        for (var p = 0; p < state.entityProducts.length; p++) {
            if (parseInt(state.entityProducts[p].product_id) === productId) {
                product = state.entityProducts[p];
                break;
            }
        }
        var productName = product ? (product.product_name || '') : '';

        var selected = [];
        var checkedItems = el.modalVariantsList ? el.modalVariantsList.querySelectorAll('.epv-modal-item:not(.disabled) input:checked') : [];
        for (var i = 0; i < checkedItems.length; i++) {
            var item = checkedItems[i].closest('.epv-modal-item');
            var vid = parseInt(item.getAttribute('data-id'));
            var nameEl = item.querySelector('.epv-modal-item-name');
            selected.push({
                product_id:          productId,
                product_name:        productName,
                variant_id:          vid,
                variant_sku:         nameEl ? nameEl.textContent : '',
                stock_quantity:      0,
                low_stock_threshold: 5,
                manage_stock:        1,
                stock_status:        'in_stock',
                is_active:           1,
                is_featured:         0
            });
        }

        state.entityVariants = state.entityVariants.concat(selected);
        updateVariantsCount();
        closeVariantsModal();
        renderVariantsList();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('variants.add_selected', 'variants added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // SHARED MODAL HELPERS
    // ════════════════════════════════════════
    function attachModalItemListeners(listEl, countFn) {
        if (!listEl) return;
        var items = listEl.querySelectorAll('.epv-modal-item:not(.disabled)');
        for (var i = 0; i < items.length; i++) {
            (function (item) {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') return;
                    var cb = item.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = !cb.checked;
                    item.classList.toggle('selected', cb && cb.checked);
                    if (countFn) countFn();
                });
                var cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.addEventListener('change', function () {
                    item.classList.toggle('selected', this.checked);
                    if (countFn) countFn();
                });
            })(items[i]);
        }
    }

    // ════════════════════════════════════════
    // UTILS
    // ════════════════════════════════════════
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // ════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════
    window.EntityProductVariants = {
        init:            init,
        _updateProduct:  updateProduct,
        _removeProduct:  removeProduct,
        _updateVariant:  updateVariant,
        _removeVariant:  removeVariant
    };

    // REGISTER — supports fragment navigation & direct load
    window.page = { run: init };

    if (window.Admin && window.Admin.page && window.Admin.page.register) {
        window.Admin.page.register('entity_product_variants', init);
    }

})();