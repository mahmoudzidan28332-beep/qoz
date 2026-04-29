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

    function reloadConfig() {
        CFG  = window.STOCK_MOVEMENTS_CONFIG || {};
        CSRF = CFG.csrfToken || '';
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
        fetch('/api/product_stock_movements?stats=1', { credentials: 'same-origin' })
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
        var url     = '/api/product_stock_movements?limit=' + PER_PAGE + '&offset=' + offset;
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

        var url    = '/api/product_stock_movements';
        var method = 'POST';
        if (isEdit) {
            payload.id = parseInt(editId, 10);
            url += '?id=' + editId;
            method = 'PUT';
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
        fetch('/api/product_stock_movements?id=' + id, {
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
        fetch('/api/product_stock_movements?id=' + id, { credentials: 'same-origin' })
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

        loadStats();
        loadMovements(1);

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

        // ESC closes modal
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var modal = document.getElementById('movementModal');
            if (modal && modal.style.display !== 'none') closeModal('movementModal');
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