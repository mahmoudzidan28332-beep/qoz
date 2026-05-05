/*!
 * /admin/assets/js/pages/stock_movements.js  v4.0
 * Guide-compliant:
 * • reloadConfig() + live t() from STOCK_MOVEMENTS_CONFIG.strings
 * • showState() unified (loading/empty/error/table)
 * • openModal() with focus
 * • ESC closes modal
 * • credentials: 'same-origin' on every fetch
 * • window.page + Admin.page.register
 * • applyI18n() translates [data-i18n] elements on init
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    var CFG, CSRF;
    var PER_PAGE       = 25;
    var currentPage    = 1;
    var currentFilters = {};

    // Tab state
    var activeTab = 'movements';

    // Secondary table state
    var epPage = 1;
    var pvPage = 1;
    var vaPage = 1;

    function reloadConfig() {
        CFG  = window.STOCK_MOVEMENTS_CONFIG || {};
        CSRF = CFG.csrfToken || '';
    }

    // ════════════════════════════════════════════════════════════
    // 1b. PLATFORM ADMIN — Tenant / Entity Context
    // ════════════════════════════════════════════════════════════
    var platformAdmin = {
        activeTenantId: 0,
        activeEntityId: 0,

        getTenantId: function () {
            return this.activeTenantId !== 0 ? this.activeTenantId : (CFG.tenantId || 0);
        },
        getEntityId: function () {
            return this.activeEntityId !== 0 ? this.activeEntityId : (CFG.entityId || 0);
        },
        tenantParam: function () {
            return 'tenant_id=' + this.getTenantId();
        },
        entityParam: function () {
            var eid = this.getEntityId();
            return eid ? '&entity_id=' + eid : '';
        },

        bind: function () {
            if (!CFG.isPlatformAdmin) return;
            var self         = this;
            var tenantSel    = document.getElementById('paTenantSelect');
            var entityGroup  = document.getElementById('paEntityGroup');
            var entitySel    = document.getElementById('paEntitySelect');
            var applyBtn     = document.getElementById('paApplyBtn');
            var clearBtn     = document.getElementById('paClearBtn');
            var banner       = document.getElementById('paActiveBanner');
            var bannerLabel  = document.getElementById('paActiveBannerLabel');

            if (!tenantSel) return;

            // Load all tenants on open
            self.loadAllTenants(tenantSel, applyBtn);

            // When tenant changes, enable apply and load entities
            tenantSel.addEventListener('change', function () {
                var tid = parseInt(tenantSel.value, 10) || 0;
                if (applyBtn) applyBtn.disabled = !tid;
                if (entitySel) {
                    while (entitySel.options.length > 1) entitySel.remove(1);
                }
                self.activeEntityId = 0;
                if (tid && entityGroup) {
                    entityGroup.style.display = 'block';
                    self.loadEntitiesForTenant(tid, entitySel);
                } else if (entityGroup) {
                    entityGroup.style.display = 'none';
                }
            });

            // Apply
            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    var tid = parseInt(tenantSel.value, 10) || 0;
                    if (!tid) return;
                    self.activeTenantId = tid;
                    self.activeEntityId = entitySel ? (parseInt(entitySel.value, 10) || 0) : 0;
                    var tenantLabel = (tenantSel.options[tenantSel.selectedIndex] || {}).text || ('Tenant #' + tid);
                    var entityLabel = self.activeEntityId
                        ? ((entitySel.options[entitySel.selectedIndex] || {}).text || ('Entity #' + self.activeEntityId))
                        : '';
                    if (banner) banner.style.display = 'block';
                    if (bannerLabel) {
                        bannerLabel.textContent = 'Acting on behalf of: ' + tenantLabel + (entityLabel ? ' / ' + entityLabel : '');
                    }
                    if (clearBtn) clearBtn.style.display = 'inline-flex';
                    // Sync hidden fields in modals
                    var htid = document.getElementById('movementTenantId');
                    var heid = document.getElementById('movementEntityId');
                    if (htid) htid.value = self.activeTenantId;
                    if (heid) heid.value = self.activeEntityId;
                    var epTid = document.getElementById('epTenantId');
                    if (epTid) epTid.value = self.activeTenantId;
                    var pvTid = document.getElementById('pvTenantId');
                    if (pvTid) pvTid.value = self.activeTenantId;
                    // Reload only active tab + stats
                    loadStats();
                    if (activeTab === 'movements')          loadMovements(1);
                    else if (activeTab === 'entity-products')   loadEntityProducts(1);
                    else if (activeTab === 'product-variants')  loadProductVariants(1);
                    else if (activeTab === 'variant-attributes') loadVariantAttributes(1);
                });
            }

            // Clear
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    self.activeTenantId = 0;
                    self.activeEntityId = 0;
                    if (tenantSel) tenantSel.value = '';
                    if (entitySel) { while (entitySel.options.length > 1) entitySel.remove(1); }
                    if (entityGroup) entityGroup.style.display = 'none';
                    if (applyBtn) applyBtn.disabled = true;
                    if (banner) banner.style.display = 'none';
                    if (clearBtn) clearBtn.style.display = 'none';
                    var htid = document.getElementById('movementTenantId');
                    var heid = document.getElementById('movementEntityId');
                    if (htid) htid.value = '';
                    if (heid) heid.value = '';
                    var epTid = document.getElementById('epTenantId');
                    if (epTid) epTid.value = '';
                    var pvTid = document.getElementById('pvTenantId');
                    if (pvTid) pvTid.value = '';
                    loadStats();
                    if (activeTab === 'movements')           loadMovements(1);
                    else if (activeTab === 'entity-products')    loadEntityProducts(1);
                    else if (activeTab === 'product-variants')   loadProductVariants(1);
                    else if (activeTab === 'variant-attributes') loadVariantAttributes(1);
                });
            }
        },

        loadAllTenants: function (sel, applyBtn) {
            if (!sel) return;
            var url = (CFG.tenantsApi || '/api/tenants') + '?limit=500';
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    var list = (json.data && json.data.items) ? json.data.items
                             : (Array.isArray(json.data) ? json.data : []);
                    list.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value       = item.id;
                        opt.textContent = (item.name || item.tenant_name || '') + ' (#' + item.id + ')';
                        sel.appendChild(opt);
                    });
                    if (applyBtn) applyBtn.disabled = !sel.value;
                })
                .catch(function () {});
        },

        loadEntitiesForTenant: function (tenantId, sel) {
            if (!sel) return;
            var url = (CFG.entitiesApi || '/api/entities') + '?tenant_id=' + tenantId + '&limit=500';
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    var list = (json.data && json.data.items) ? json.data.items
                             : (Array.isArray(json.data) ? json.data : []);
                    list.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value       = item.id;
                        opt.textContent = (item.name || item.entity_name || '') + ' (#' + item.id + ')';
                        sel.appendChild(opt);
                    });
                })
                .catch(function () {});
        }
    };

    // ════════════════════════════════════════════════════════════
    // 1c. TAB SWITCHING
    // ════════════════════════════════════════════════════════════
    function switchTab(tab) {
        activeTab = tab;
        var panels = ['movements', 'entity-products', 'product-variants', 'variant-attributes'];
        panels.forEach(function (p) {
            var panelId = 'tab' + p.split('-').map(function (w) { return w.charAt(0).toUpperCase() + w.slice(1); }).join('');
            var el = document.getElementById(panelId);
            if (el) el.style.display = (p === tab) ? 'block' : 'none';
        });
        document.querySelectorAll('#smTabNav .sm-tab-btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-tab') === tab;
            btn.style.borderBottomColor = isActive ? 'var(--color-primary,#3b82f6)' : 'transparent';
            btn.style.color = isActive ? 'var(--color-primary,#3b82f6)' : 'var(--text-secondary,#6b7280)';
            btn.style.fontWeight = isActive ? '600' : '400';
        });
        // Lazy-load tab on first visit
        if (tab === 'entity-products')    loadEntityProducts(1);
        if (tab === 'product-variants')   loadProductVariants(1);
        if (tab === 'variant-attributes') loadVariantAttributes(1);
    }

    // ════════════════════════════════════════════════════════════
    // 2. i18n — reads live from CFG.strings (flat dot-notation)
    // ════════════════════════════════════════════════════════════
    function t(key, fb) {
        var strings = (window.STOCK_MOVEMENTS_CONFIG && window.STOCK_MOVEMENTS_CONFIG.strings) || {};
        if (strings[key] !== undefined && strings[key] !== '') return String(strings[key]);
        return fb !== undefined ? fb : key.split('.').pop().replace(/_/g, ' ');
    }

    function applyI18n() {
        var container = document.getElementById('stockMovementsContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var val = t(key, '');
            if (!val) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = val;
            } else {
                el.textContent = val;
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            var key = el.getAttribute('data-i18n-placeholder');
            var val = t(key, '');
            if (val) el.placeholder = val;
        });
    }

    // ════════════════════════════════════════════════════════════
    // 3. HELPERS
    // ════════════════════════════════════════════════════════════
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function authHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF };
    }

    // ════════════════════════════════════════════════════════════
    // 4. SHOW STATE
    // ════════════════════════════════════════════════════════════
    function showState(state, errorMsg) {
        var stateCard  = document.getElementById('smStateCard');
        var tableCard  = document.getElementById('smTableCard');
        var loading    = document.getElementById('smLoading');
        var empty      = document.getElementById('smEmpty');
        var error      = document.getElementById('smError');

        [loading, empty, error].forEach(function (el) { if (el) el.style.display = 'none'; });

        switch (state) {
            case 'loading':
                if (stateCard) stateCard.style.display = 'block';
                if (tableCard) tableCard.style.display = 'none';
                if (loading)   loading.style.display   = 'flex';
                break;
            case 'empty':
                if (stateCard) stateCard.style.display = 'block';
                if (tableCard) tableCard.style.display = 'none';
                if (empty)     empty.style.display     = 'flex';
                break;
            case 'error':
                if (stateCard) stateCard.style.display = 'block';
                if (tableCard) tableCard.style.display = 'none';
                if (error)     error.style.display     = 'flex';
                if (errorMsg) {
                    var p = document.getElementById('smErrorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default: // 'table'
                if (stateCard) stateCard.style.display = 'none';
                if (tableCard) tableCard.style.display = 'block';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 5. TOAST NOTIFICATIONS (sm-toast-*)
    // ════════════════════════════════════════════════════════════
    function showNotification(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type || 'info');
            return;
        }
        var container = document.querySelector('.sm-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'sm-notifications';
            document.body.appendChild(container);
        }
        var n = document.createElement('div');
        n.className = 'sm-toast sm-toast-' + (type || 'info');
        n.textContent = msg;
        container.appendChild(n);
        setTimeout(function () {
            n.style.opacity = '0';
            setTimeout(function () { n.remove(); }, 300);
        }, 3000);
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
        var url = '/api/product_stock_movements?stats=1&' + platformAdmin.tenantParam() + platformAdmin.entityParam();
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    document.getElementById('statTotal').textContent     = d.data.total_movements || 0;
                    document.getElementById('statRestocked').textContent = d.data.total_restocked || 0;
                    document.getElementById('statSold').textContent      = d.data.total_sold      || 0;
                    document.getElementById('statReturned').textContent  = d.data.total_returned  || 0;
                }
            })
            .catch(function () {});
    }

    // ════════════════════════════════════════════════════════════
    // 8. LOAD LIST
    // ════════════════════════════════════════════════════════════
    function loadMovements(page) {
        currentPage = page || 1;
        var offset  = (currentPage - 1) * PER_PAGE;
        var url     = '/api/product_stock_movements?' + platformAdmin.tenantParam() + platformAdmin.entityParam() +
                      '&limit=' + PER_PAGE + '&offset=' + offset;
        if (currentFilters.search)    url += '&search='    + encodeURIComponent(currentFilters.search);
        if (currentFilters.type)      url += '&type='      + encodeURIComponent(currentFilters.type);
        if (currentFilters.date_from) url += '&date_from=' + encodeURIComponent(currentFilters.date_from);
        if (currentFilters.date_to)   url += '&date_to='   + encodeURIComponent(currentFilters.date_to);

        showState('loading');

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('movementsBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                    d.data.items.forEach(function (item) {
                        var typeClass = item.type === 'restock'    ? 'badge-success' :
                                        item.type === 'sale'       ? 'badge-danger'  :
                                        item.type === 'return'     ? 'badge-warning' :
                                                                     'badge-primary';
                        var qtyPrefix = parseInt(item.change_quantity, 10) > 0 ? '+' : '';
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(String(item.id)) + '</td>' +
                            '<td>' + esc(item.product_name || '') + ' <small>(#' + esc(String(item.product_id)) + ')</small></td>' +
                            '<td>' + (item.variant_id ? esc(String(item.variant_id)) : '—') + '</td>' +
                            '<td><span class="badge ' + typeClass + '">' + t('types.' + item.type, item.type) + '</span></td>' +
                            '<td><strong>' + qtyPrefix + esc(String(item.change_quantity)) + '</strong></td>' +
                            '<td>' + (item.reference_id ? esc(String(item.reference_id)) : '—') + '</td>' +
                            '<td>' + esc(item.notes || '—') + '</td>' +
                            '<td>' + esc(item.created_at || '—') + '</td>' +
                            '<td class="actions-cell">' +
                                (CFG.canEdit   ? '<button class="btn btn-sm btn-primary btn-edit" data-id="' + item.id + '" aria-label="' + t('form.edit', 'Edit') + '"><i class="fas fa-edit" aria-hidden="true"></i></button> ' : '') +
                                (CFG.canDelete ? '<button class="btn btn-sm btn-danger btn-delete" data-id="' + item.id + '" aria-label="' + t('form.delete', 'Delete') + '"><i class="fas fa-trash" aria-hidden="true"></i></button>' : '') +
                            '</td>';
                        tbody.appendChild(tr);
                    });

                    renderPagination(d.data);
                    showState('table');

                    if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
                        Admin.buttons.applyHoverEffects(tbody);
                    }
                } else {
                    showState('empty');
                    document.getElementById('paginationInfo').textContent = '';
                    document.getElementById('pagination').innerHTML = '';
                }
            })
            .catch(function (err) {
                showState('error', err.message || t('messages.error', 'Error loading data'));
            });
    }

    // ════════════════════════════════════════════════════════════
    // 9. PAGINATION
    // ════════════════════════════════════════════════════════════
    function renderPagination(meta) {
        var total      = meta.total || 0;
        var totalPages = meta.total_pages || Math.ceil(total / PER_PAGE) || 1;
        var start      = ((currentPage - 1) * PER_PAGE) + 1;
        var end        = Math.min(currentPage * PER_PAGE, total);

        document.getElementById('paginationInfo').textContent =
            t('pagination.showing', 'Showing') + ' ' + start + '–' + end + ' ' +
            t('pagination.of', 'of') + ' ' + total;

        var pag = document.getElementById('pagination');
        pag.innerHTML = '';
        if (totalPages <= 1) return;

        function mkBtn(label, page, disabled, active) {
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (active ? ' btn-primary active' : '') + (disabled ? ' disabled' : '');
            btn.textContent = label;
            btn.disabled = !!disabled;
            if (!disabled) btn.addEventListener('click', function () { loadMovements(page); });
            return btn;
        }

        pag.appendChild(mkBtn(t('pagination.prev', '‹'), currentPage - 1, currentPage <= 1, false));

        for (var i = 1; i <= totalPages; i++) {
            if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
                if (i === 3 || i === totalPages - 2) {
                    var sp = document.createElement('span');
                    sp.className = 'pagination-ellipsis';
                    sp.textContent = '…';
                    pag.appendChild(sp);
                }
                continue;
            }
            pag.appendChild(mkBtn(String(i), i, false, i === currentPage));
        }

        pag.appendChild(mkBtn(t('pagination.next', '›'), currentPage + 1, currentPage >= totalPages, false));
    }

    // ════════════════════════════════════════════════════════════
    // 10. BARCODE / SKU / PRODUCT LOOKUP
    // ════════════════════════════════════════════════════════════
    function scanBarcode() {
        var barcode = document.getElementById('barcodeInput').value.trim();
        if (!barcode) return;
        fetch('/api/product_stock_movements?barcode=' + encodeURIComponent(barcode), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var resultEl = document.getElementById('barcodeResult');
                if (d.success && d.data) {
                    resultEl.style.display = 'block';
                    resultEl.style.color   = 'var(--success-color, #10b981)';
                    resultEl.textContent   = t('messages.product_found', 'Product found') + ': ' + (d.data.product_name || '') + ' (#' + d.data.id + ')';
                    document.getElementById('productIdInput').value = d.data.id;
                    if (d.data.variant_id) document.getElementById('variantIdInput').value = d.data.variant_id;
                    lookupProduct();
                } else {
                    resultEl.style.display = 'block';
                    resultEl.style.color   = 'var(--danger-color, #ef4444)';
                    resultEl.textContent   = t('messages.barcode_not_found', 'Barcode not found');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'An error occurred'), 'error'); });
    }

    function lookupProduct() {
        var productId = document.getElementById('productIdInput').value;
        var nameEl    = document.getElementById('productName');
        if (!productId) { nameEl.textContent = ''; return; }
        fetch('/api/products?id=' + encodeURIComponent(productId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    var name = d.data.name || d.data.product_name || '';
                    nameEl.textContent = name ? t('messages.product_found', 'Product found') + ': ' + name : '';
                    nameEl.className   = 'sm-lookup-name';
                } else {
                    nameEl.textContent = t('messages.product_not_found', 'Product not found');
                    nameEl.className   = 'sm-lookup-name error';
                }
            })
            .catch(function () {
                nameEl.textContent = t('messages.product_not_found', 'Product not found');
                nameEl.className   = 'sm-lookup-name error';
            });
    }

    function skuLookup() {
        var sku = document.getElementById('skuInput').value.trim();
        if (!sku) return;
        fetch('/api/product_stock_movements?sku=' + encodeURIComponent(sku), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var resultEl = document.getElementById('barcodeResult');
                if (d.success && d.data) {
                    resultEl.style.display = 'block';
                    resultEl.style.color   = 'var(--success-color, #10b981)';
                    resultEl.textContent   = t('messages.product_found', 'Product found') + ': ' + (d.data.product_name || '') + ' (#' + d.data.id + ')';
                    document.getElementById('productIdInput').value = d.data.id;
                    if (d.data.variant_id) document.getElementById('variantIdInput').value = d.data.variant_id;
                    lookupProduct();
                    openModal('movementModal');
                } else {
                    resultEl.style.display = 'block';
                    resultEl.style.color   = 'var(--danger-color, #ef4444)';
                    resultEl.textContent   = t('messages.sku_not_found', 'SKU not found');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'An error occurred'), 'error'); });
    }

    // ════════════════════════════════════════════════════════════
    // 11. SAVE / DELETE / EDIT
    // ════════════════════════════════════════════════════════════
    function saveMovement() {
        var editId  = document.getElementById('movementId').value;
        var isEdit  = editId && parseInt(editId, 10) > 0;
        var payload = {
            product_id:      parseInt(document.getElementById('productIdInput').value, 10) || 0,
            change_quantity: parseInt(document.getElementById('changeQuantity').value, 10) || 0,
            type:            document.getElementById('movementType').value
        };

        var variantId   = document.getElementById('variantIdInput').value;
        if (variantId)   payload.variant_id   = parseInt(variantId, 10);
        var referenceId = document.getElementById('referenceId').value;
        if (referenceId) payload.reference_id = parseInt(referenceId, 10);
        var notes = document.getElementById('movementNotes').value;
        if (notes)       payload.notes = notes;

        var url    = '/api/product_stock_movements?' + platformAdmin.tenantParam() + platformAdmin.entityParam();
        var method = 'POST';
        if (isEdit) {
            payload.id = parseInt(editId, 10);
            url += '&id=' + editId;
            method = 'PUT';
        }
        // inject tenant/entity for platform admin
        if (CFG.isPlatformAdmin) {
            var htid = document.getElementById('movementTenantId');
            var heid = document.getElementById('movementEntityId');
            if (htid && htid.value) payload.tenant_id = parseInt(htid.value, 10);
            if (heid && heid.value) payload.entity_id = parseInt(heid.value, 10);
        }

        var saveBtn  = document.getElementById('btnSaveMovement');
        var saveTxt  = document.getElementById('btnSaveMovementText');
        if (saveBtn) saveBtn.disabled = true;
        if (saveTxt) saveTxt.textContent = t('form.saving', 'Saving...');

        fetch(url, { method: method, headers: authHeaders(), body: JSON.stringify(payload), credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    closeModal('movementModal');
                    showNotification(t('messages.saved', 'Movement saved successfully'), 'success');
                    document.getElementById('movementForm').reset();
                    document.getElementById('movementId').value = '';
                    document.getElementById('productName').textContent = '';
                    loadMovements(currentPage);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.error', 'Error'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
                if (saveTxt) saveTxt.textContent = t('form.save', 'Save');
            });
    }

    function deleteMovement(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete?'))) return;
        fetch('/api/product_stock_movements?' + platformAdmin.tenantParam() + platformAdmin.entityParam() + '&id=' + id, {
            method: 'DELETE',
            headers: authHeaders(),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.deleted', 'Movement deleted'), 'success');
                    loadMovements(currentPage);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.error', 'Error'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); });
    }

    function editMovement(id) {
        fetch('/api/product_stock_movements?' + platformAdmin.tenantParam() + '&id=' + id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    var item = d.data;
                    document.getElementById('movementId').value       = item.id;
                    document.getElementById('productIdInput').value   = item.product_id   || '';
                    document.getElementById('variantIdInput').value   = item.variant_id   || '';
                    document.getElementById('movementType').value     = item.type         || 'restock';
                    document.getElementById('changeQuantity').value   = item.change_quantity || 0;
                    document.getElementById('referenceId').value      = item.reference_id || '';
                    document.getElementById('movementNotes').value    = item.notes        || '';
                    document.getElementById('modalTitle').textContent = t('form.edit', 'Edit') + ' #' + id;
                    lookupProduct();
                    openModal('movementModal');
                } else {
                    showNotification(d.message || t('messages.error', 'Error'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); });
    }

    // ════════════════════════════════════════════════════════════
    // 12. CAMERA SCANNER
    // ════════════════════════════════════════════════════════════
    var cameraStream   = null;
    var cameraInterval = null;

    function startCameraScanner() {
        var container = document.getElementById('cameraContainer');
        var video     = document.getElementById('cameraVideo');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showNotification(t('messages.camera_not_supported', 'Camera not supported'), 'error');
            return;
        }
        container.style.display = 'block';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                cameraStream = stream;
                video.srcObject = stream;
                var detector = window.BarcodeDetector
                    ? new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e'] })
                    : null;
                cameraInterval = setInterval(function () {
                    var canvas = document.getElementById('cameraCanvas');
                    var ctx    = canvas.getContext('2d');
                    canvas.width  = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0);
                    if (detector) {
                        detector.detect(canvas).then(function (barcodes) {
                            if (barcodes.length > 0) {
                                var code = barcodes[0].rawValue;
                                stopCameraScanner();
                                document.getElementById('barcodeInput').value = code;
                                scanBarcode();
                            }
                        }).catch(function () {});
                    }
                }, 800);
            })
            .catch(function (err) {
                showNotification(t('messages.camera_error', 'Cannot access camera: ') + err.message, 'error');
                container.style.display = 'none';
            });
    }

    function stopCameraScanner() {
        if (cameraInterval) { clearInterval(cameraInterval); cameraInterval = null; }
        if (cameraStream)   { cameraStream.getTracks().forEach(function (tr) { tr.stop(); }); cameraStream = null; }
        var video = document.getElementById('cameraVideo');
        if (video) video.srcObject = null;
        document.getElementById('cameraContainer').style.display = 'none';
    }

    // ════════════════════════════════════════════════════════════
    // 11b. ENTITY PRODUCTS
    // ════════════════════════════════════════════════════════════
    function renderSimplePagination(meta, pagId, infoId, PER, loadFn) {
        var total      = meta.total || 0;
        var totalPages = meta.total_pages || Math.ceil(total / PER) || 1;
        var page       = meta.page || 1;
        var start      = ((page - 1) * PER) + 1;
        var end        = Math.min(page * PER, total);
        var infoEl     = document.getElementById(infoId);
        var pagEl      = document.getElementById(pagId);
        if (infoEl) infoEl.textContent = start + '–' + end + ' / ' + total;
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;
        function mkBtn2(label, p, disabled, active) {
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (active ? ' btn-primary active' : '') + (disabled ? ' disabled' : '');
            btn.textContent = label;
            btn.disabled = !!disabled;
            if (!disabled) btn.addEventListener('click', function () { loadFn(p); });
            return btn;
        }
        pagEl.appendChild(mkBtn2('‹', page - 1, page <= 1, false));
        for (var i = 1; i <= Math.min(totalPages, 7); i++) {
            pagEl.appendChild(mkBtn2(String(i), i, false, i === page));
        }
        pagEl.appendChild(mkBtn2('›', page + 1, page >= totalPages, false));
    }

    function loadEntityProducts(page) {
        epPage = page || 1;
        var url = '/api/entity_products?' + platformAdmin.tenantParam() + platformAdmin.entityParam() +
                  '&limit=' + PER_PAGE + '&offset=' + ((epPage - 1) * PER_PAGE);
        var tbody = document.getElementById('epBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.loading', 'Loading...') + '</td></tr>';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!tbody) return;
                tbody.innerHTML = '';
                var items = (d.data && d.data.items) ? d.data.items : (Array.isArray(d.data) ? d.data : []);
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
                    return;
                }
                items.forEach(function (item) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(String(item.tenant_id || '')) + '</td>' +
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td>' + esc(String(item.product_id || '')) + '</td>' +
                        '<td><strong>' + esc(String(item.stock_quantity ?? '')) + '</strong></td>' +
                        '<td>' + esc(String(item.low_stock_threshold ?? '')) + '</td>' +
                        '<td><span class="badge ' + (item.is_active ? 'badge-success' : 'badge-secondary') + '">' + (item.is_active ? '✓' : '✗') + '</span></td>' +
                        '<td><span class="badge ' + (item.is_featured ? 'badge-warning' : 'badge-secondary') + '">' + (item.is_featured ? '★' : '—') + '</span></td>' +
                        '<td>' + esc(item.created_at || '—') + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-warning ep-btn-adjust" ' +
                                'data-product-id="' + esc(String(item.product_id)) + '" ' +
                                'data-entity-id="' + esc(String(item.entity_id)) + '" ' +
                                'data-tenant-id="' + esc(String(item.tenant_id)) + '" ' +
                                'title="' + t('ep.adjust_stock', 'Adjust Stock') + '" ' +
                                'aria-label="' + t('ep.adjust_stock', 'Adjust Stock') + '">' +
                                '<i class="fas fa-sliders-h" aria-hidden="true"></i>' +
                            '</button>' +
                            (CFG.canEdit   ? ' <button class="btn btn-sm btn-primary ep-btn-edit" data-id="' + item.id + '" aria-label="' + t('form.edit', 'Edit') + '"><i class="fas fa-edit" aria-hidden="true"></i></button>' : '') +
                            (CFG.canDelete ? ' <button class="btn btn-sm btn-danger ep-btn-delete" data-id="' + item.id + '" aria-label="' + t('form.delete', 'Delete') + '"><i class="fas fa-trash" aria-hidden="true"></i></button>' : '') +
                        '</td>';
                    tbody.appendChild(tr);
                });
                if (d.data && d.data.total !== undefined) {
                    renderSimplePagination({total: d.data.total, total_pages: d.data.total_pages, page: epPage}, 'epPagination', 'epPaginationInfo', PER_PAGE, loadEntityProducts);
                }
            })
            .catch(function (err) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">' + esc(err.message) + '</td></tr>';
            });
    }

    function saveEntityProduct() {
        var editId  = document.getElementById('epId').value;
        var isEdit  = editId && parseInt(editId, 10) > 0;
        var payload = {
            entity_id:          parseInt(document.getElementById('epEntityId').value, 10)    || 0,
            product_id:         parseInt(document.getElementById('epProductId').value, 10)   || 0,
            stock_quantity:     parseInt(document.getElementById('epStockQty').value, 10)    || 0,
            low_stock_threshold:parseInt(document.getElementById('epLowThreshold').value, 10)|| 0,
            is_active:          parseInt(document.getElementById('epIsActive').value, 10),
            is_featured:        parseInt(document.getElementById('epIsFeatured').value, 10)
        };
        if (CFG.isPlatformAdmin) {
            var htid = document.getElementById('epTenantId');
            if (htid && htid.value) payload.tenant_id = parseInt(htid.value, 10);
        }
        var url    = '/api/entity_products?' + platformAdmin.tenantParam();
        var method = 'POST';
        if (isEdit) { payload.id = parseInt(editId, 10); url += '&id=' + editId; method = 'PUT'; }
        var btn = document.getElementById('btnSaveEntityProduct');
        var txt = document.getElementById('btnSaveEpText');
        if (btn) btn.disabled = true;
        if (txt) txt.textContent = t('form.saving', 'Saving...');
        fetch(url, { method: method, headers: authHeaders(), body: JSON.stringify(payload), credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    closeModal('entityProductModal');
                    showNotification(t('messages.saved', 'Saved successfully'), 'success');
                    loadEntityProducts(epPage);
                } else {
                    showNotification(d.message || t('messages.error', 'Error'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); })
            .finally(function () {
                if (btn) btn.disabled = false;
                if (txt) txt.textContent = t('form.save', 'Save');
            });
    }

    function editEntityProduct(id) {
        fetch('/api/entity_products?' + platformAdmin.tenantParam() + '&id=' + id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var item = d.data || d;
                if (!item || !item.id) { showNotification(t('messages.error', 'Error'), 'error'); return; }
                document.getElementById('epId').value           = item.id;
                document.getElementById('epEntityId').value     = item.entity_id        || '';
                document.getElementById('epProductId').value    = item.product_id       || '';
                document.getElementById('epStockQty').value     = item.stock_quantity   ?? 0;
                document.getElementById('epLowThreshold').value = item.low_stock_threshold ?? 0;
                document.getElementById('epIsActive').value     = item.is_active    ? '1' : '0';
                document.getElementById('epIsFeatured').value   = item.is_featured  ? '1' : '0';
                document.getElementById('epModalTitle').textContent = t('form.edit', 'Edit') + ' #' + id;
                openModal('entityProductModal');
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); });
    }

    function deleteEntityProduct(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
        fetch('/api/entity_products?' + platformAdmin.tenantParam() + '&id=' + id, {
            method: 'DELETE', headers: authHeaders(), credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) { showNotification(t('messages.deleted', 'Deleted'), 'success'); loadEntityProducts(epPage); }
                else showNotification(d.message || t('messages.error', 'Error'), 'error');
            })
            .catch(function () { showNotification(t('messages.error', 'Error'), 'error'); });
    }

    // ════════════════════════════════════════════════════════════
    // 11c. PRODUCT VARIANTS (stock-adjust only)
    // ════════════════════════════════════════════════════════════
    function loadProductVariants(page) {
        pvPage = page || 1;
        var url = '/api/product_variants?' + platformAdmin.tenantParam() +
                  '&limit=' + PER_PAGE + '&offset=' + ((pvPage - 1) * PER_PAGE);
        var tbody = document.getElementById('pvBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.loading', 'Loading...') + '</td></tr>';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!tbody) return;
                tbody.innerHTML = '';
                var items = (d.data && d.data.items) ? d.data.items : (Array.isArray(d.data) ? d.data : []);
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
                    return;
                }
                items.forEach(function (item) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(String(item.product_id || '')) + '</td>' +
                        '<td>' + esc(item.sku || '—') + '</td>' +
                        '<td>' + esc(item.barcode || '—') + '</td>' +
                        '<td><strong>' + esc(String(item.stock_quantity ?? '')) + '</strong></td>' +
                        '<td>' + esc(String(item.low_stock_threshold ?? '')) + '</td>' +
                        '<td><span class="badge ' + (item.is_active ? 'badge-success' : 'badge-secondary') + '">' + (item.is_active ? '✓' : '✗') + '</span></td>' +
                        '<td><span class="badge ' + (item.is_default ? 'badge-primary' : 'badge-secondary') + '">' + (item.is_default ? '★' : '—') + '</span></td>' +
                        '<td>' + esc(item.created_at || '—') + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-warning pv-btn-adjust" ' +
                                'data-product-id="' + esc(String(item.product_id)) + '" ' +
                                'data-variant-id="' + esc(String(item.id)) + '" ' +
                                'title="' + t('pv.adjust_stock', 'Adjust Stock') + '" ' +
                                'aria-label="' + t('pv.adjust_stock', 'Adjust Stock') + '">' +
                                '<i class="fas fa-sliders-h" aria-hidden="true"></i>' +
                            '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                if (d.data && d.data.total !== undefined) {
                    renderSimplePagination({total: d.data.total, total_pages: d.data.total_pages, page: pvPage}, 'pvPagination', 'pvPaginationInfo', PER_PAGE, loadProductVariants);
                }
            })
            .catch(function (err) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">' + esc(err.message) + '</td></tr>';
            });
    }

    // ════════════════════════════════════════════════════════════
    // 11d. ENTITY VARIANT STOCK (stock-adjust only, was "Variant Attributes")
    // ════════════════════════════════════════════════════════════
    function loadVariantAttributes(page) {
        vaPage = page || 1;
        var url = '/api/entity_product_variants?' + platformAdmin.tenantParam() + platformAdmin.entityParam() +
                  '&limit=' + PER_PAGE + '&offset=' + ((vaPage - 1) * PER_PAGE);
        var tbody = document.getElementById('vaBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="text-center">' + t('table.loading', 'Loading...') + '</td></tr>';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!tbody) return;
                tbody.innerHTML = '';
                var items = (d.data && d.data.items) ? d.data.items : (Array.isArray(d.data) ? d.data : []);
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="11" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
                    return;
                }
                items.forEach(function (item) {
                    var statusBadge = item.stock_status === 'in_stock'
                        ? '<span class="badge badge-success">' + esc(item.stock_status) + '</span>'
                        : '<span class="badge badge-danger">' + esc(item.stock_status || '—') + '</span>';
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(String(item.tenant_id || '')) + '</td>' +
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td>' + esc(item.product_name || String(item.product_id || '')) + '</td>' +
                        '<td>' + esc(String(item.variant_id || '')) + '</td>' +
                        '<td>' + esc(item.variant_sku || '—') + '</td>' +
                        '<td><strong>' + esc(String(item.stock_quantity ?? '')) + '</strong></td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td><span class="badge ' + (item.is_active ? 'badge-success' : 'badge-secondary') + '">' + (item.is_active ? '✓' : '✗') + '</span></td>' +
                        '<td>' + esc(item.created_at || '—') + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-warning va-btn-adjust" ' +
                                'data-product-id="' + esc(String(item.product_id)) + '" ' +
                                'data-variant-id="' + esc(String(item.variant_id)) + '" ' +
                                'data-entity-id="' + esc(String(item.entity_id)) + '" ' +
                                'data-tenant-id="' + esc(String(item.tenant_id)) + '" ' +
                                'title="' + t('epv.adjust_stock', 'Adjust Stock') + '" ' +
                                'aria-label="' + t('epv.adjust_stock', 'Adjust Stock') + '">' +
                                '<i class="fas fa-sliders-h" aria-hidden="true"></i>' +
                            '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                if (d.data && d.data.total !== undefined) {
                    renderSimplePagination({total: d.data.total, total_pages: d.data.total_pages, page: vaPage}, 'vaPagination', 'vaPaginationInfo', PER_PAGE, loadVariantAttributes);
                }
            })
            .catch(function (err) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger">' + esc(err.message) + '</td></tr>';
            });
    }

    /**
     * Open the stock movement modal pre-filled for a specific product/variant/entity.
     * Used by "Adjust Stock" buttons in tabs 2, 3, and 4.
     */
    function openAdjustStockModal(productId, variantId, entityId, tenantId) {
        document.getElementById('movementForm').reset();
        document.getElementById('movementId').value      = '';
        document.getElementById('productIdInput').value  = productId  || '';
        document.getElementById('variantIdInput').value  = variantId  || '';
        document.getElementById('productName').textContent = '';
        document.getElementById('modalTitle').textContent = t('form.adjust_stock_title', 'Adjust Stock');
        if (CFG.isPlatformAdmin) {
            var htid = document.getElementById('movementTenantId');
            var heid = document.getElementById('movementEntityId');
            if (htid) htid.value = tenantId || '';
            if (heid) heid.value = entityId || '';
            if (tenantId) platformAdmin.activeTenantId = parseInt(tenantId, 10) || 0;
            if (entityId) platformAdmin.activeEntityId = parseInt(entityId, 10) || 0;
        }
        if (productId) {
            // Auto-lookup product name via the existing lookupProduct helper
            lookupProduct();
        }
        openModal('movementModal');
    }

    // ════════════════════════════════════════════════════════════
    // 13. FILTERS
    // ════════════════════════════════════════════════════════════
    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value  = '';
        document.getElementById('dateFrom').value    = '';
        document.getElementById('dateTo').value      = '';
        currentFilters = {};
        loadMovements(1);
    }

    // ════════════════════════════════════════════════════════════
    // 14. INIT
    // ════════════════════════════════════════════════════════════
    function init() {
        reloadConfig();
        applyI18n();

        platformAdmin.bind();

        loadStats();
        loadMovements(1);

        // Wire up tabs
        document.querySelectorAll('#smTabNav .sm-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });

        if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
            Admin.buttons.applyHoverEffects(document.getElementById('stockMovementsContainer'));
        }

        // ── Filters ─────────────────────────────────────────────
        document.getElementById('btnFilter').addEventListener('click', function () {
            currentFilters = {
                search:    document.getElementById('searchInput').value,
                type:      document.getElementById('typeFilter').value,
                date_from: document.getElementById('dateFrom').value,
                date_to:   document.getElementById('dateTo').value
            };
            loadMovements(1);
        });

        document.getElementById('btnClearFilter').addEventListener('click', clearFilters);

        document.getElementById('searchInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnFilter').click(); }
        });

        // ── Modal open/close ─────────────────────────────────────
        document.getElementById('btnAddMovement').addEventListener('click', function () {
            document.getElementById('movementForm').reset();
            document.getElementById('movementId').value = '';
            document.getElementById('productName').textContent = '';
            document.getElementById('modalTitle').textContent = t('add_movement', 'Add Movement');
            openModal('movementModal');
        });

        document.getElementById('btnCloseModal').addEventListener('click', function () { closeModal('movementModal'); });
        document.getElementById('btnCancelModal').addEventListener('click', function () { closeModal('movementModal'); });

        document.getElementById('movementModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal('movementModal');
        });

        // ── Save ─────────────────────────────────────────────────
        document.getElementById('btnSaveMovement').addEventListener('click', saveMovement);

        // ── Product lookup ───────────────────────────────────────
        document.getElementById('btnLookupProduct').addEventListener('click', lookupProduct);

        // ── Barcode ──────────────────────────────────────────────
        document.getElementById('btnScanBarcode').addEventListener('click', scanBarcode);
        document.getElementById('barcodeInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); scanBarcode(); }
        });

        // ── SKU ──────────────────────────────────────────────────
        var skuBtn   = document.getElementById('btnSearchSku');
        var skuInput = document.getElementById('skuInput');
        if (skuBtn)   skuBtn.addEventListener('click', skuLookup);
        if (skuInput) skuInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); skuLookup(); }
        });

        // ── Camera ───────────────────────────────────────────────
        var camBtn  = document.getElementById('btnCameraScanner');
        var stopBtn = document.getElementById('btnStopCamera');
        if (camBtn)  camBtn.addEventListener('click', startCameraScanner);
        if (stopBtn) stopBtn.addEventListener('click', stopCameraScanner);

        // ── Retry ────────────────────────────────────────────────
        var retryBtn = document.getElementById('btnRetry');
        if (retryBtn) retryBtn.addEventListener('click', function () { loadMovements(currentPage); });

        // ── Table row delegation (edit / delete) ─────────────────
        document.getElementById('movementsBody').addEventListener('click', function (e) {
            var btnDel  = e.target.closest('.btn-delete');
            if (btnDel)  deleteMovement(parseInt(btnDel.getAttribute('data-id'), 10));
            var btnEdit = e.target.closest('.btn-edit');
            if (btnEdit) editMovement(parseInt(btnEdit.getAttribute('data-id'), 10));
        });

        // ── Entity Products modal ─────────────────────────────────
        var btnAddEp = document.getElementById('btnAddEntityProduct');
        if (btnAddEp) btnAddEp.addEventListener('click', function () {
            document.getElementById('entityProductForm').reset();
            document.getElementById('epId').value = '';
            document.getElementById('epModalTitle').textContent = t('ep.add', 'Add Entity Product');
            if (CFG.isPlatformAdmin) {
                var htid = document.getElementById('epTenantId');
                if (htid) htid.value = platformAdmin.getTenantId() || '';
            }
            openModal('entityProductModal');
        });
        var btnCloseEp  = document.getElementById('btnCloseEpModal');
        var btnCancelEp = document.getElementById('btnCancelEpModal');
        if (btnCloseEp)  btnCloseEp.addEventListener('click',  function () { closeModal('entityProductModal'); });
        if (btnCancelEp) btnCancelEp.addEventListener('click', function () { closeModal('entityProductModal'); });
        var epModal = document.getElementById('entityProductModal');
        if (epModal) epModal.addEventListener('click', function (e) { if (e.target === this) closeModal('entityProductModal'); });
        var btnSaveEp = document.getElementById('btnSaveEntityProduct');
        if (btnSaveEp) btnSaveEp.addEventListener('click', saveEntityProduct);

        var epBody = document.getElementById('epBody');
        if (epBody) epBody.addEventListener('click', function (e) {
            var btnAdj  = e.target.closest('.ep-btn-adjust');
            if (btnAdj) {
                openAdjustStockModal(
                    parseInt(btnAdj.getAttribute('data-product-id'), 10) || 0,
                    0,
                    parseInt(btnAdj.getAttribute('data-entity-id'), 10)  || 0,
                    parseInt(btnAdj.getAttribute('data-tenant-id'), 10)  || 0
                );
            }
            var btnEdit = e.target.closest('.ep-btn-edit');
            if (btnEdit) editEntityProduct(parseInt(btnEdit.getAttribute('data-id'), 10));
            var btnDel  = e.target.closest('.ep-btn-delete');
            if (btnDel)  deleteEntityProduct(parseInt(btnDel.getAttribute('data-id'), 10));
        });

        // ── Product Variants — adjust-stock delegation ────────────
        var pvBody = document.getElementById('pvBody');
        if (pvBody) pvBody.addEventListener('click', function (e) {
            var btnAdj = e.target.closest('.pv-btn-adjust');
            if (btnAdj) {
                openAdjustStockModal(
                    parseInt(btnAdj.getAttribute('data-product-id'), 10) || 0,
                    parseInt(btnAdj.getAttribute('data-variant-id'), 10) || 0,
                    0,
                    0
                );
            }
        });

        // ── Entity Variant Stock — adjust-stock delegation ────────
        var vaBody = document.getElementById('vaBody');
        if (vaBody) vaBody.addEventListener('click', function (e) {
            var btnAdj = e.target.closest('.va-btn-adjust');
            if (btnAdj) {
                openAdjustStockModal(
                    parseInt(btnAdj.getAttribute('data-product-id'), 10) || 0,
                    parseInt(btnAdj.getAttribute('data-variant-id'), 10) || 0,
                    parseInt(btnAdj.getAttribute('data-entity-id'),  10) || 0,
                    parseInt(btnAdj.getAttribute('data-tenant-id'),  10) || 0
                );
            }
        });

        // ESC closes all modals
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ['movementModal', 'entityProductModal'].forEach(function (mid) {
                var m = document.getElementById(mid);
                if (m && m.style.display !== 'none') closeModal(mid);
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // 15. REGISTER
    // ════════════════════════════════════════════════════════════
    window.StockMovements = { init: init };
    window.page           = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('stock_movements', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());