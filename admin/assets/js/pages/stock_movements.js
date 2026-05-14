(function () {
    'use strict';

    var CFG, CSRF;
    var currentTab = 'global-products';
    var currentPage = 1;
    var perPage = 25;
    var tenantDebounce = null;
    var cameraStream = null;
    var cameraActive = false;

    function t(key, fallback) {
        if (window.TRANSLATIONS && window.TRANSLATIONS[key]) return window.TRANSLATIONS[key];
        // Handle nested keys like "table.id"
        if (window.TRANSLATIONS) {
            var parts = key.split('.');
            var current = window.TRANSLATIONS;
            for (var i = 0; i < parts.length; i++) {
                if (current[parts[i]] !== undefined) {
                    current = current[parts[i]];
                } else {
                    return fallback || key;
                }
            }
            return current || fallback || key;
        }
        return fallback || key;
    }

    function init() {
        CFG = window.STOCK_MOVEMENTS_CONFIG || {};
        CSRF = CFG.csrfToken;

        // Apply Translations
        if (window.Admin && Admin.i18n) {
            Admin.i18n.applyTranslations(document.getElementById('stockMovementsContainer'));
        }

        bindEvents();
        loadData();
        if (CFG.isPlatformAdmin) {
            setupTenantSearch();
        } else {
            loadEntities(CFG.tenantId);
        }
    }

    function bindEvents() {
        // Tab switching
        document.querySelectorAll('#stockTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#stockTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentTab = this.getAttribute('data-tab');
                currentPage = 1;
                loadData();
            });
        });

        // Entity change
        document.getElementById('entitySelect').addEventListener('change', function () {
            currentPage = 1;
            loadData();
        });

        // Search in table
        var searchInput = document.getElementById('tableSearchInput');
        var searchDebounce = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () {
                currentPage = 1;
                loadData();
            }, 400);
        });

        // Modal close
        document.getElementById('btnCloseModal').addEventListener('click', function () { closeModal(); });
        document.getElementById('btnCancelModal').addEventListener('click', function () { closeModal(); });
        document.getElementById('btnSaveMovement').addEventListener('click', submitMovement);

        // Barcode scan
        document.getElementById('btnScan').addEventListener('click', handleBarcode);
        document.getElementById('barcodeInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') handleBarcode();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('tableSearchInput').focus();
            }
            if (e.key === 'Escape') closeModal();
        });

        // Refresh
        document.getElementById('btnRefresh').addEventListener('click', function () { loadData(); });

        // Camera Scanner
        var btnToggle = document.getElementById('btnToggleCamera');
        if (btnToggle) btnToggle.addEventListener('click', toggleCamera);
        
        var btnClose = document.getElementById('btnCloseCamera');
        if (btnClose) btnClose.addEventListener('click', stopCamera);
    }

    // ════════════════════════════════════════════════════════════
    // TENANT SEARCH (Step 1)
    // ════════════════════════════════════════════════════════════
    function setupTenantSearch() {
        var input = document.getElementById('tenantSearchInput');
        var results = document.getElementById('tenantSearchResults');

        input.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(tenantDebounce);
            if (q.length < 1) { results.innerHTML = ''; return; }

            tenantDebounce = setTimeout(function () {
                var param = isNaN(q) ? 'search=' : 'id=';
                fetch('/api/tenants?' + param + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        results.innerHTML = '';
                        if (d.success && d.data && d.data.items) {
                            d.data.items.forEach(function (t) {
                                var div = document.createElement('div');
                                div.className = 'search-item';
                                div.textContent = '[' + t.id + '] ' + (t.name || t.store_name || 'Tenant');
                                div.addEventListener('click', function () {
                                    document.getElementById('selectedTenantId').value = t.id;
                                    input.value = t.name || t.store_name;
                                    results.innerHTML = '';
                                    loadEntities(t.id);
                                    loadData();
                                });
                                results.appendChild(div);
                            });
                        }
                    });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#tenantSearchContainer')) results.innerHTML = '';
        });
    }

    // ════════════════════════════════════════════════════════════
    // ENTITY CASCADE (Step 2)
    // ════════════════════════════════════════════════════════════
    function loadEntities(tenantId) {
        var select = document.getElementById('entitySelect');
        select.innerHTML = '<option value="">-- All Entities --</option>';
        if (!tenantId) return;

        fetch('/api/entities?tenant_id=' + tenantId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data && d.data.items) {
                    d.data.items.forEach(function (en) {
                        var opt = document.createElement('option');
                        opt.value = en.id;
                        opt.textContent = en.store_name || ('Entity ' + en.id);
                        select.appendChild(opt);
                    });
                }
            });
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING (Step 3 & 4)
    // ════════════════════════════════════════════════════════════
    function loadData() {
        var tenantId = document.getElementById('selectedTenantId').value;
        var entityId = document.getElementById('entitySelect').value;
        var q = document.getElementById('tableSearchInput').value;
        var offset = (currentPage - 1) * perPage;

        updateTableHeaders();
        var tbody = document.getElementById('stockTableBody');
        tbody.innerHTML = '<tr><td colspan="10" class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>' + t('messages.loading', 'Loading...') + '</td></tr>';

        var url = getApiUrl(currentTab, tenantId, entityId, q, offset);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                tbody.innerHTML = '';
                if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                    d.data.items.forEach(function (row) {
                        tbody.appendChild(renderRow(row));
                    });
                    renderPagination(d.data.meta || {});
                } else {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center p-5">No items found.</td></tr>';
                }
            })
            .catch(function (err) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center p-5 text-danger">Error: ' + err.message + '</td></tr>';
            });
    }

    function getApiUrl(tab, tenantId, entityId, q, offset) {
        var base = '';
        var params = '?limit=' + perPage + '&offset=' + offset;
        if (tenantId) params += '&tenant_id=' + tenantId;
        if (q) params += '&search=' + encodeURIComponent(q);

        switch (tab) {
            case 'global-products':
                base = '/api/products';
                break;
            case 'global-variants':
                base = '/api/product_variants';
                break;
            case 'entity-products':
                base = '/api/entity_products';
                if (entityId) params += '&entity_id=' + entityId;
                break;
            case 'entity-variants':
                base = '/api/entity_product_variants';
                if (entityId) params += '&entity_id=' + entityId;
                break;
        }
        return base + params;
    }

    function updateTableHeaders() {
        var head = document.getElementById('tableHeaderRow');
        var html = '<th>' + t('table.id', 'ID') + '</th>' +
                   '<th>' + t('table.image', 'Image') + '</th>' +
                   '<th>' + t('table.product', 'Product Info') + '</th>' +
                   '<th>' + t('table.sku', 'SKU') + '</th>' +
                   '<th>' + t('table.barcode', 'Barcode') + '</th>';

        if (currentTab === 'global-variants' || currentTab === 'entity-variants') {
            html += '<th>' + t('table.variant', 'Variant Info') + '</th>';
        }

        html += '<th>' + t('table.stock', 'Current Stock') + '</th>' +
                '<th>' + t('table.actions', 'Actions') + '</th>';
        head.innerHTML = html;
    }

    function renderRow(item) {
        var tr = document.createElement('tr');
        var name = item.name || item.product_name || '—';
        var sku = item.sku || '—';
        var barcode = item.barcode || '—';
        var stock = item.stock_quantity || 0;
        var img = item.image_url || '/assets/images/no-image.png';

        var html = '<td>' + item.id + '</td>';
        html += '<td><img src="' + img + '" class="table-img" style="width:40px;height:40px;object-fit:cover;border-radius:4px"></td>';
        html += '<td><strong>' + name + '</strong></td>';
        html += '<td><code class="sku-badge">' + sku + '</code></td>';
        html += '<td>' + barcode + '</td>';

        if (currentTab === 'global-variants' || currentTab === 'entity-variants') {
            var vInfo = [];
            if (item.color_name) vInfo.push(item.color_name);
            if (item.size_name) vInfo.push(item.size_name);
            if (vInfo.length === 0 && item.variant_name) vInfo.push(item.variant_name);
            html += '<td>' + (vInfo.join(' / ') || 'Default') + '</td>';
        }

        html += '<td><span class="stock-value ' + (stock <= 0 ? 'text-danger' : '') + '">' + stock + '</span></td>';
        html += '<td class="text-end"><button class="btn btn-sm btn-primary btn-add-move" data-id="' + item.id + '"><i class="fas fa-plus"></i> Add Movement</button></td>';

        tr.innerHTML = html;
        tr.querySelector('.btn-add-move').addEventListener('click', function () {
            openMovementModal(item);
        });
        return tr;
    }

    function renderPagination(meta) {
        var pag = document.getElementById('pagination');
        var info = document.getElementById('paginationInfo');
        var total = meta.total || 0;
        var totalPages = Math.ceil(total / perPage);

        info.textContent = 'Showing ' + (total > 0 ? (currentPage - 1) * perPage + 1 : 0) + ' to ' + Math.min(currentPage * perPage, total) + ' of ' + total;

        pag.innerHTML = '';
        if (totalPages <= 1) return;

        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);

        if (currentPage > 1) pag.appendChild(createPageBtn('<', currentPage - 1));
        for (var i = start; i <= end; i++) {
            pag.appendChild(createPageBtn(i, i, i === currentPage));
        }
        if (currentPage < totalPages) pag.appendChild(createPageBtn('>', currentPage + 1));
    }

    function createPageBtn(label, page, active) {
        var btn = document.createElement('button');
        btn.className = 'btn btn-sm btn-outline-secondary' + (active ? ' active' : '');
        btn.textContent = label;
        btn.addEventListener('click', function () {
            currentPage = page;
            loadData();
        });
        return btn;
    }

    // ════════════════════════════════════════════════════════════
    // MOVEMENT MODAL (Step 5)
    // ════════════════════════════════════════════════════════════
    function openMovementModal(item) {
        var modal = document.getElementById('movementModal');
        var form = document.getElementById('movementForm');
        form.reset();

        document.getElementById('formTenantId').value = document.getElementById('selectedTenantId').value;
        document.getElementById('formEntityId').value = document.getElementById('entitySelect').value;
        
        // Reset IDs
        document.getElementById('formProductId').value = '';
        document.getElementById('formVariantId').value = '';
        document.getElementById('formEntityProductId').value = '';
        document.getElementById('formEntityVariantId').value = '';

        // Map IDs based on Tab
        switch (currentTab) {
            case 'global-products':
                document.getElementById('formProductId').value = item.id;
                break;
            case 'global-variants':
                document.getElementById('formProductId').value = item.product_id;
                document.getElementById('formVariantId').value = item.id;
                break;
            case 'entity-products':
                document.getElementById('formProductId').value = item.product_id;
                document.getElementById('formEntityProductId').value = item.id;
                break;
            case 'entity-variants':
                document.getElementById('formProductId').value = item.product_id;
                document.getElementById('formVariantId').value = item.variant_id;
                document.getElementById('formEntityVariantId').value = item.id;
                break;
        }

        document.getElementById('summaryName').textContent = item.name || item.product_name || 'Item #' + item.id;
        document.getElementById('summarySku').textContent = 'SKU: ' + (item.sku || '—');

        modal.style.display = 'flex';
        document.getElementById('changeQuantity').focus();
    }

    function closeModal() {
        document.getElementById('movementModal').style.display = 'none';
    }

    function submitMovement() {
        var btn = document.getElementById('btnSaveMovement');
        var form = document.getElementById('movementForm');
        var data = {};
        new FormData(form).forEach(function (v, k) { if (v) data[k] = v; });

        if (!data.change_quantity || !data.type) {
            alert('Please fill all required fields');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch('/api/product_stock_movements', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                closeModal();
                loadData();
                if (window.Admin && Admin.notify) Admin.notify('Stock movement saved!', 'success');
                else alert('Stock movement saved!');
            } else {
                alert('Error: ' + (d.message || d.error || 'Unknown error'));
            }
        })
        .catch(function (err) { alert('Request failed: ' + err.message); })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = 'Confirm Movement';
        });
    }

    // ════════════════════════════════════════════════════════════
    // BARCODE HANDLER
    // ════════════════════════════════════════════════════════════
    function handleBarcode() {
        var code = document.getElementById('barcodeInput').value.trim();
        if (!code) return;

        var tenantId = document.getElementById('selectedTenantId').value;
        var entityId = document.getElementById('entitySelect').value;
        var url = '/api/product_stock_movements?barcode=' + encodeURIComponent(code);
        if (tenantId) url += '&tenant_id=' + tenantId;
        if (entityId) url += '&entity_id=' + entityId;

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    openMovementModal(d.data);
                    document.getElementById('barcodeInput').value = '';
                } else {
                    if (window.Admin && Admin.notify) Admin.notify('Barcode not found in current scope.', 'warning');
                    else alert('Barcode not found in current scope.');
                }
            });
    }

    // ════════════════════════════════════════════════════════════
    // CAMERA SCANNER
    // ════════════════════════════════════════════════════════════
    async function toggleCamera() {
        if (cameraActive) {
            stopCamera();
        } else {
            await startCamera();
        }
    }

    async function startCamera() {
        var container = document.getElementById('cameraPreviewContainer');
        var preview = document.getElementById('cameraPreview');
        if (!container || !preview) return;

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
            });
            
            var video = document.createElement('video');
            video.srcObject = cameraStream;
            video.setAttribute('playsinline', true); // Required for iOS
            video.style.width = '100%';
            preview.innerHTML = '';
            preview.appendChild(video);
            await video.play();

            container.style.display = 'block';
            cameraActive = true;

            if ('BarcodeDetector' in window) {
                var detector = new window.BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e']
                });

                var scan = async function () {
                    if (!cameraActive) return;
                    try {
                        var barcodes = await detector.detect(video);
                        if (barcodes.length > 0) {
                            var code = barcodes[0].rawValue;
                            document.getElementById('barcodeInput').value = code;
                            stopCamera();
                            handleBarcode();
                            return;
                        }
                    } catch (e) { /* ignore */ }
                    requestAnimationFrame(scan);
                };
                requestAnimationFrame(scan);
            } else {
                if (window.Admin && Admin.notify) Admin.notify('BarcodeDetector API not supported. Using manual scan.', 'warning');
            }
        } catch (err) {
            alert('Camera Error: ' + err.message);
        }
    }

    function stopCamera() {
        cameraActive = false;
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
        var container = document.getElementById('cameraPreviewContainer');
        if (container) container.style.display = 'none';
        var preview = document.getElementById('cameraPreview');
        if (preview) preview.innerHTML = '';
    }

    // Run
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();