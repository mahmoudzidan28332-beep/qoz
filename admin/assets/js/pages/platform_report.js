/**
 * Platform Report & Analytics Module
 * admin/assets/js/pages/platform_report.js
 *
 * Handles:
 *  - Dashboard summary cards
 *  - Report generation with filters
 *  - Metric cards rendering
 *  - Chart.js time-series charts
 *  - Data tables for detailed metrics
 *  - Export functionality
 */
(function () {
    'use strict';

    // ═══════════════════════════════════════════
    // CONFIG & STATE
    // ═══════════════════════════════════════════
    const CFG = window.__PR_CONFIG || {};
    const API_BASE = (CFG.apiBase || '/api');
    const API = API_BASE + '/platform_report';
    const T   = CFG.strings || {};
    const CHARTJS_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
    let mainChart = null;
    let currentReportData = null;
    let _chartJsPromise = null;

    // ═══════════════════════════════════════════
    // PLATFORM ADMIN MODULE
    // ═══════════════════════════════════════════
    const platformAdmin = {
        activeTenantId: 0,

        /** Returns the effective tenant_id for all API calls. */
        getTenantId: function () {
            if (CFG.isPlatformAdmin) {
                return this.activeTenantId || 0;
            }
            return this.activeTenantId !== 0 ? this.activeTenantId : (CFG.tenantId ? parseInt(CFG.tenantId, 10) : 0);
        },

        /** Returns 'tenant_id=N' query string parameter. */
        tenantParam: function () {
            const tid = this.getTenantId();
            return tid ? ('tenant_id=' + tid) : '';
        },

        init: function () {
            if (!CFG.isPlatformAdmin) return;

            var self = this;
            var paTenantSelect = document.getElementById('paTenantSelect');
            var paTenantInput  = document.getElementById('paTenantIdInput');
            var paLookupBtn    = document.getElementById('paLookupTenantBtn');
            var paApplyBtn     = document.getElementById('paApplyTenantBtn');
            var paClearBtn     = document.getElementById('paClearTenantBtn');
            var paBanner       = document.getElementById('paActiveTenantBanner');
            var paLabel        = document.getElementById('paActiveTenantLabel');
            var hiddenTenant   = document.getElementById('prTenantId');
            var cfgTenantId    = CFG.tenantId ? parseInt(CFG.tenantId, 10) : 0;

            function applyTenantContext(tid, labelText) {
                self.activeTenantId = (!isNaN(tid) && tid > 0) ? tid : 0;
                if (paTenantInput && self.activeTenantId) paTenantInput.value = self.activeTenantId;
                if (hiddenTenant) hiddenTenant.value = self.activeTenantId || '';
                if (paClearBtn) paClearBtn.style.display = self.activeTenantId ? '' : 'none';
                if (paBanner)  paBanner.style.display   = self.activeTenantId ? '' : 'none';
                if (paLabel && self.activeTenantId) {
                    if (labelText) {
                        paLabel.textContent = 'Active tenant: ' + labelText;
                    } else {
                        var opt = paTenantSelect && paTenantSelect.options[paTenantSelect.selectedIndex];
                        paLabel.textContent = 'Active tenant: ' + (opt && opt.value ? opt.textContent : '#' + self.activeTenantId);
                    }
                }
                loadEntities(self.activeTenantId || undefined);
            }

            function upsertTenantOption(tid, tenantName) {
                if (!paTenantSelect || !tid) return null;
                var existing = null;
                Array.prototype.forEach.call(paTenantSelect.options, function (opt) {
                    if (parseInt(opt.value, 10) === tid) existing = opt;
                });
                if (existing) {
                    if (tenantName) existing.textContent = tenantName + ' (#' + tid + ')';
                    return existing;
                }
                var opt = document.createElement('option');
                opt.value = tid;
                opt.textContent = tenantName ? (tenantName + ' (#' + tid + ')') : ('Tenant #' + tid);
                paTenantSelect.appendChild(opt);
                return opt;
            }

            function lookupTenantById(tid, autoApply) {
                if (!tid || tid <= 0) return Promise.resolve();
                return fetch(API_BASE + '/tenants?id=' + encodeURIComponent(tid), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        var tenant = null;
                        if (res && res.data) {
                            if (Array.isArray(res.data)) {
                                tenant = res.data[0] || null;
                            } else if (Array.isArray(res.data.items)) {
                                tenant = res.data.items[0] || null;
                            } else if (typeof res.data === 'object') {
                                tenant = res.data;
                            }
                        }
                        var tenantName = tenant ? (tenant.tenant_name || tenant.name || '') : '';
                        var opt = upsertTenantOption(tid, tenantName);
                        if (paTenantSelect) paTenantSelect.value = String(tid);
                        if (autoApply) {
                            var label = (opt && opt.textContent) ? opt.textContent : ('#' + tid);
                            applyTenantContext(tid, label);
                        }
                    })
                    .catch(function () {
                        upsertTenantOption(tid, '');
                        if (paTenantSelect) paTenantSelect.value = String(tid);
                        if (autoApply) applyTenantContext(tid);
                    });
            }

            // Load tenants into select
            fetch(API_BASE + '/tenants?limit=500&order_by=id&order_dir=ASC', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    var items = (res.data && res.data.items) ? res.data.items : (Array.isArray(res.data) ? res.data : []);
                    items.forEach(function (tn) {
                        var opt = document.createElement('option');
                        opt.value       = tn.tenant_id || tn.id;
                        opt.textContent = (tn.tenant_name || tn.name || '') + ' (#' + (tn.tenant_id || tn.id) + ')';
                        if (paTenantSelect) paTenantSelect.appendChild(opt);
                    });
                    if (cfgTenantId > 0 && !self.activeTenantId) {
                        if (paTenantSelect) paTenantSelect.value = String(cfgTenantId);
                        applyTenantContext(cfgTenantId);
                    }
                })
                .catch(function () {
                    if (cfgTenantId > 0) {
                        lookupTenantById(cfgTenantId, true);
                    }
                });

            if (cfgTenantId > 0) {
                if (paTenantInput) paTenantInput.value = cfgTenantId;
                lookupTenantById(cfgTenantId, true);
            }

            if (paApplyBtn) {
                paApplyBtn.onclick = function () {
                    var inputTid  = paTenantInput  ? parseInt(paTenantInput.value,  10) : 0;
                    var selectTid = paTenantSelect ? parseInt(paTenantSelect.value, 10) : 0;
                    var tid = (!isNaN(inputTid) && inputTid > 0) ? inputTid : selectTid;
                    applyTenantContext(tid);
                };
            }

            if (paLookupBtn) {
                paLookupBtn.onclick = function () {
                    var tid = paTenantInput ? parseInt(paTenantInput.value, 10) : 0;
                    if (!tid || tid <= 0) return;
                    lookupTenantById(tid, true);
                };
            }

            if (paClearBtn) {
                paClearBtn.onclick = function () {
                    self.activeTenantId = 0;
                    if (paTenantSelect) paTenantSelect.value = '';
                    if (paTenantInput)  paTenantInput.value  = '';
                    if (hiddenTenant)   hiddenTenant.value   = '';
                    paClearBtn.style.display = 'none';
                    if (paBanner) paBanner.style.display = 'none';
                    loadEntities(undefined);
                };
            }
        }
    };

    function t(key, fallback) {
        return T[key] || fallback || key;
    }

    function fmt(num) {
        if (num === null || num === undefined || num === '') return '-';
        const n = parseFloat(num);
        if (isNaN(n)) return num;
        var isArabic = (CFG.lang || '').indexOf('ar') === 0;
        return n.toLocaleString(isArabic ? 'ar-SA' : 'en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function fmtCurrency(num) {
        if (num === null || num === undefined || num === '') return '-';
        const n = parseFloat(num);
        if (isNaN(n)) return num;
        var isArabic = (CFG.lang || '').indexOf('ar') === 0;
        return n.toLocaleString(isArabic ? 'ar-SA' : 'en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function h(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }

    // ═══════════════════════════════════════════
    // DOM REFERENCES
    // ═══════════════════════════════════════════
    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return document.querySelectorAll(sel); }

    // ═══════════════════════════════════════════
    // API HELPER
    // ═══════════════════════════════════════════
    async function apiGet(action, params) {
        const url = new URL(API, window.location.origin);
        url.searchParams.set('action', action);
        if (params) {
            Object.entries(params).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    url.searchParams.set(k, v);
                }
            });
        }
        const resp = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return resp.json();
    }

    async function apiPost(action, body) {
        const url = new URL(API, window.location.origin);
        url.searchParams.set('action', action);
        const resp = await fetch(url.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        });
        return resp.json();
    }

    // ═══════════════════════════════════════════
    // DIRECTION (RTL/LTR)
    // ═══════════════════════════════════════════
    function applyDirection() {
        var dir = CFG.dir || 'ltr';

        // Apply dir globally
        document.documentElement.dir = dir;
        document.documentElement.setAttribute('dir', dir);
        document.body.dir = dir;
        document.body.setAttribute('dir', dir);

        // Apply to main container
        var app = $('#platformReportApp');
        if (app) {
            app.dir = dir;
            app.setAttribute('dir', dir);
            app.dataset.dir = dir;
        }

        // Chart.js canvas must stay LTR for correct rendering
        var canvas = $('#prMainChart');
        if (canvas) {
            canvas.dir = 'ltr';
            canvas.setAttribute('dir', 'ltr');
            if (canvas.parentElement) {
                canvas.parentElement.dir = 'ltr';
                canvas.parentElement.setAttribute('dir', 'ltr');
            }
        }
    }

    // ═══════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════
    function init() {
        // Apply direction first (RTL/LTR)
        applyDirection();

        // Set default dates (last 30 days)
        const endDateEl = $('#prEndDate');
        const startDateEl = $('#prStartDate');
        if (endDateEl && startDateEl) {
            const now = new Date();
            endDateEl.value = now.toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(now);
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            startDateEl.value = thirtyDaysAgo.toISOString().split('T')[0];
        }

        // Default report type
        const reportTypeEl = $('#prReportType');
        if (reportTypeEl) {
            reportTypeEl.value = 'sales_overview';
        }

        // Bind generate button
        const genBtn = $('#prGenerateBtn');
        if (genBtn) {
            genBtn.addEventListener('click', generateReport);
        }

        // Bind export buttons
        $$('.pr-btn-export').forEach(btn => {
            btn.addEventListener('click', function () {
                requestExport(this.dataset.format);
            });
        });

        // Init platform admin panel (must be before loadEntities)
        platformAdmin.init();

        // Load entities for filter (tenant admin loads immediately; PA waits for tenant selection)
        loadEntities();

        // Debounced window resize handler for chart
        var _resizeTimer = null;
        window.addEventListener('resize', function () {
            if (_resizeTimer) clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(function () {
                if (mainChart) {
                    mainChart.resize();
                }
            }, 250);
        });

        // Auto-generate default report after a short delay for initial render
        loadDashboardAndAutoReport();
    }

    /**
     * Load dashboard summary first, then auto-generate report.
     */
    async function loadDashboardAndAutoReport() {
        await loadDashboardSummary();
        generateReport();
    }

    // ═══════════════════════════════════════════
    // DASHBOARD SUMMARY
    // ═══════════════════════════════════════════
    async function loadDashboardSummary() {
        try {
            const params = {};
            const tid = platformAdmin.getTenantId();
            if (tid) params.tenant_id = tid;
            const resp = await apiGet('dashboard', params);
            if (resp.success && resp.data) {
                const d = resp.data;
                const todayOrders = $('#todayOrders');
                const todayRevenue = $('#todayRevenue');
                const todayCustomers = $('#todayCustomers');
                const monthOrders = $('#monthOrders');
                const monthRevenue = $('#monthRevenue');
                const monthCustomers = $('#monthCustomers');
                const monthAvgOrder = $('#monthAvgOrder');

                if (todayOrders) todayOrders.textContent = fmt(d.today?.orders);
                if (todayRevenue) todayRevenue.textContent = fmtCurrency(d.today?.revenue);
                if (todayCustomers) todayCustomers.textContent = fmt(d.today?.customers);
                if (monthOrders) monthOrders.textContent = fmt(d.month?.orders);
                if (monthRevenue) monthRevenue.textContent = fmtCurrency(d.month?.revenue);
                if (monthCustomers) monthCustomers.textContent = fmt(d.month?.customers);
                if (monthAvgOrder) monthAvgOrder.textContent = fmtCurrency(d.month?.avg_order);
            }
        } catch (e) {
            console.error('Failed to load dashboard summary:', e);
        }
    }

    // ═══════════════════════════════════════════
    // LOAD ENTITIES (for entity filter)
    // ═══════════════════════════════════════════
    async function loadEntities(tenantIdOverride) {
        try {
            const sel = $('#prEntityId');
            if (!sel) return;

            // Determine tenant ID: explicit override > platformAdmin active tenant > config fallback
            let tid = tenantIdOverride !== undefined ? tenantIdOverride : platformAdmin.getTenantId();

            // Clear existing options
            sel.innerHTML = '<option value="">' + t('all_entities', 'All Entities') + '</option>';

            // Platform admin must explicitly select a tenant first
            if (!tid) return;

            const url = new URL(API_BASE + '/entities', window.location.origin);
            url.searchParams.set('limit', '200');
            url.searchParams.set('tenant_id', tid);

            const resp = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            const items = data?.data?.items || data?.data || [];
            items.forEach(function (e) {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.store_name || e.name || ('Entity #' + e.id);
                sel.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load entities:', e);
        }
    }

    // ═══════════════════════════════════════════
    // GENERATE REPORT
    // ═══════════════════════════════════════════
    async function generateReport() {
        const reportType = $('#prReportType')?.value;
        const startDate = $('#prStartDate')?.value;
        const endDate = $('#prEndDate')?.value;
        const groupBy = $('#prGroupBy')?.value || 'day';
        const tenantId = platformAdmin.getTenantId();
        const entityIdEl = $('#prEntityId');
        const entityId = entityIdEl ? entityIdEl.value : '';

        if (!reportType) {
            alert(t('select_report', 'Please select a report type'));
            return;
        }
        if (!startDate || !endDate) {
            alert(t('start_date', 'Please select start and end dates'));
            return;
        }

        showLoading(true);
        hideResults();

        try {
            const resp = await apiGet('report', {
                report_type: reportType,
                start_date: startDate,
                end_date: endDate,
                group_by: groupBy,
                tenant_id: tenantId,
                entity_id: entityId
            });

            if (resp.success && resp.data?.success) {
                currentReportData = resp.data;
                // Show results container BEFORE rendering so chart container is visible
                showResults(true);
                await renderReport(resp.data);
            } else {
                const msg = resp.message || resp.data?.errors?.join('; ') || t('error_loading');
                showNoData(msg);
            }
        } catch (e) {
            console.error('Failed to generate report:', e);
            showNoData(t('error_loading'));
        } finally {
            showLoading(false);
        }
    }

    // ═══════════════════════════════════════════
    // RENDER REPORT
    // ═══════════════════════════════════════════
    async function renderReport(data) {
        renderMetrics(data.report_type, data.metrics || {});
        await renderChart(data.report_type, data.time_series || []);
        renderTable(data.report_type, data.metrics || {});
    }

    // ═══════════════════════════════════════════
    // RENDER METRICS CARDS
    // ═══════════════════════════════════════════
    function renderMetrics(type, metrics) {
        const grid = $('#prMetricsGrid');
        if (!grid) return;

        const cards = getMetricCards(type, metrics);
        grid.innerHTML = cards.map(c =>
            `<div class="pr-metric-card ${c.color || ''}">
                <div class="pr-metric-icon">${c.icon || '📊'}</div>
                <div class="pr-metric-info">
                    <div class="pr-metric-value">${h(c.value)}</div>
                    <div class="pr-metric-label">${h(c.label)}</div>
                </div>
            </div>`
        ).join('');
    }

    function getMetricCards(type, m) {
        switch (type) {
            case 'sales_overview':
                return [
                    { icon: '🛒', label: t('total_orders'), value: fmt(m.total_orders), color: 'pr-blue' },
                    { icon: '💰', label: t('total_revenue'), value: fmtCurrency(m.total_revenue), color: 'pr-green' },
                    { icon: '👥', label: t('unique_customers'), value: fmt(m.unique_customers), color: 'pr-purple' },
                    { icon: '📦', label: t('avg_order_value'), value: fmtCurrency(m.avg_order_value), color: 'pr-orange' },
                    { icon: '✅', label: t('completed_orders'), value: fmt(m.completed_orders), color: 'pr-green' },
                    { icon: '❌', label: t('cancelled_orders'), value: fmt(m.cancelled_orders), color: 'pr-red' },
                    { icon: '💸', label: t('total_discounts'), value: fmtCurrency(m.total_discounts), color: 'pr-yellow' },
                    { icon: '💳', label: t('paid_orders'), value: fmt(m.paid_orders), color: 'pr-blue' },
                ];

            case 'revenue_profit':
                return [
                    { icon: '💰', label: t('gross_revenue'), value: fmtCurrency(m.gross_revenue), color: 'pr-green' },
                    { icon: '💵', label: t('net_revenue'), value: fmtCurrency(m.net_revenue), color: 'pr-blue' },
                    { icon: '🏷️', label: t('total_discounts'), value: fmtCurrency(m.total_discounts), color: 'pr-yellow' },
                    { icon: '🏦', label: t('total_tax'), value: fmtCurrency(m.total_tax), color: 'pr-orange' },
                    { icon: '🚚', label: t('total_shipping'), value: fmtCurrency(m.total_shipping), color: 'pr-purple' },
                    { icon: '💹', label: t('total_commissions'), value: fmtCurrency(m.total_commissions), color: 'pr-red' },
                ];

            case 'orders_performance':
                return [
                    { icon: '📦', label: t('total_orders'), value: fmt(m.total_orders), color: 'pr-blue' },
                    { icon: '⏳', label: t('pending_orders'), value: fmt(m.pending_orders), color: 'pr-yellow' },
                    { icon: '✅', label: t('delivered_orders'), value: fmt(m.delivered_orders), color: 'pr-green' },
                    { icon: '❌', label: t('cancelled_orders'), value: fmt(m.cancelled_orders), color: 'pr-red' },
                    { icon: '🌐', label: t('online_orders'), value: fmt(m.online_orders), color: 'pr-blue' },
                    { icon: '🧾', label: t('pos_orders'), value: fmt(m.pos_orders), color: 'pr-purple' },
                    { icon: '⏱️', label: t('avg_delivery_hours'), value: fmt(m.avg_delivery_hours), color: 'pr-orange' },
                    { icon: '🔄', label: t('refunded_orders'), value: fmt(m.refunded_orders), color: 'pr-red' },
                    { icon: '🚚', label: t('total_deliveries', 'Total Deliveries'), value: fmt(m.total_deliveries), color: 'pr-blue' },
                    { icon: '📍', label: t('pending_deliveries', 'Pending Deliveries'), value: fmt(m.pending_deliveries), color: 'pr-yellow' },
                    { icon: '🚛', label: t('in_transit_deliveries', 'In Transit'), value: fmt(m.in_transit_deliveries), color: 'pr-purple' },
                    { icon: '✅', label: t('completed_deliveries', 'Completed Deliveries'), value: fmt(m.completed_deliveries), color: 'pr-green' },
                    { icon: '💰', label: t('total_delivery_fees', 'Delivery Fees'), value: fmtCurrency(m.total_delivery_fees), color: 'pr-orange' },
                    { icon: '⏱️', label: t('avg_delivery_minutes', 'Avg Delivery (min)'), value: fmt(m.avg_delivery_minutes), color: 'pr-purple' },
                ];

            case 'products_performance':
                return [
                    { icon: '📦', label: t('total_products'), value: fmt(m.total_products), color: 'pr-blue' },
                    { icon: '✅', label: t('active_products'), value: fmt(m.active_products), color: 'pr-green' },
                    { icon: '⚠️', label: t('out_of_stock'), value: fmt(m.out_of_stock), color: 'pr-red' },
                    { icon: '📉', label: t('low_stock'), value: fmt(m.low_stock), color: 'pr-yellow' },
                    { icon: '🛍️', label: t('products_sold'), value: fmt(m.products_sold_count), color: 'pr-purple' },
                    { icon: '📊', label: t('units_sold'), value: fmt(m.total_units_sold), color: 'pr-orange' },
                    { icon: '👁️', label: t('product_views'), value: fmt(m.product_views), color: 'pr-blue' },
                    { icon: '👆', label: t('product_clicks'), value: fmt(m.product_clicks), color: 'pr-green' },
                    { icon: '🛒', label: t('add_to_cart_events'), value: fmt(m.add_to_cart_events), color: 'pr-purple' },
                    { icon: '❤️', label: t('product_favorites'), value: fmt(m.product_favorites), color: 'pr-red' },
                ];

            case 'ads_performance':
                return [
                    { icon: '📺', label: t('active_campaigns'), value: fmt(m.active_campaigns), color: 'pr-blue' },
                    { icon: '👁️', label: t('total_impressions'), value: fmt(m.total_impressions), color: 'pr-purple' },
                    { icon: '👆', label: t('total_clicks'), value: fmt(m.total_clicks), color: 'pr-green' },
                    { icon: '📈', label: t('ctr'), value: fmt(m.ctr) + '%', color: 'pr-orange' },
                    { icon: '🔗', label: t('total_interactions'), value: fmt(m.total_interactions), color: 'pr-red' },
                ];

            case 'returns_complaints':
                return [
                    { icon: '↩️', label: t('total_returns'), value: fmt(m.total_returns), color: 'pr-blue' },
                    { icon: '⏳', label: t('pending_returns'), value: fmt(m.pending_returns), color: 'pr-yellow' },
                    { icon: '✅', label: t('approved_returns'), value: fmt(m.approved_returns), color: 'pr-green' },
                    { icon: '❌', label: t('rejected_returns'), value: fmt(m.rejected_returns), color: 'pr-red' },
                    { icon: '🎫', label: t('total_tickets'), value: fmt(m.total_tickets), color: 'pr-purple' },
                    { icon: '📂', label: t('open_tickets'), value: fmt(m.open_tickets), color: 'pr-orange' },
                    { icon: '✔️', label: t('resolved_tickets'), value: fmt(m.resolved_tickets), color: 'pr-green' },
                ];

            case 'entities_performance':
                return [
                    { icon: '🏪', label: t('total_entities'), value: fmt(m.total_entities), color: 'pr-blue' },
                    { icon: '✅', label: t('active_entities'), value: fmt(m.active_entities), color: 'pr-green' },
                    { icon: '⏳', label: t('pending_entities'), value: fmt(m.pending_entities), color: 'pr-yellow' },
                    { icon: '🚫', label: t('suspended_entities'), value: fmt(m.suspended_entities), color: 'pr-red' },
                ];

            case 'customer_behavior':
                return [
                    { icon: '👤', label: t('new_users'), value: fmt(m.new_users), color: 'pr-blue' },
                    { icon: '🛒', label: t('total_carts'), value: fmt(m.total_carts), color: 'pr-purple' },
                    { icon: '🚫', label: t('abandoned_carts'), value: fmt(m.abandoned_carts), color: 'pr-red' },
                    { icon: '✅', label: t('converted_carts'), value: fmt(m.converted_carts), color: 'pr-green' },
                    { icon: '📈', label: t('cart_conversion_rate'), value: fmt(m.cart_conversion_rate) + '%', color: 'pr-orange' },
                    { icon: '🔄', label: t('repeat_customers'), value: fmt(m.repeat_customers), color: 'pr-blue' },
                    { icon: '❤️', label: t('wishlist_items'), value: fmt(m.wishlist_items), color: 'pr-yellow' },
                ];

            case 'delivery_performance':
                return [
                    { icon: '🚚', label: t('total_deliveries', 'Total Deliveries'), value: fmt(m.total_deliveries), color: 'pr-blue' },
                    { icon: '📍', label: t('pending_deliveries', 'Pending'), value: fmt(m.pending_deliveries), color: 'pr-yellow' },
                    { icon: '🚛', label: t('in_transit_deliveries', 'In Transit'), value: fmt(m.in_transit_deliveries), color: 'pr-purple' },
                    { icon: '✅', label: t('completed_deliveries', 'Completed'), value: fmt(m.completed_deliveries), color: 'pr-green' },
                    { icon: '❌', label: t('failed_deliveries', 'Failed'), value: fmt(m.failed_deliveries), color: 'pr-red' },
                    { icon: '💰', label: t('total_delivery_fees', 'Delivery Fees'), value: fmtCurrency(m.total_delivery_fees), color: 'pr-orange' },
                    { icon: '⏱️', label: t('avg_delivery_minutes', 'Avg Time (min)'), value: fmt(m.avg_delivery_minutes), color: 'pr-purple' },
                    { icon: '💵', label: t('total_delivery_revenue', 'Delivery Revenue'), value: fmtCurrency(m.total_delivery_revenue), color: 'pr-green' },
                ];

            case 'platform_health':
                return [
                    { icon: '👥', label: t('total_users'), value: fmt(m.total_users), color: 'pr-blue' },
                    { icon: '✅', label: t('active_users'), value: fmt(m.active_users), color: 'pr-green' },
                    { icon: '🏢', label: t('total_tenants'), value: fmt(m.total_tenants), color: 'pr-purple' },
                    { icon: '🏪', label: t('total_entities'), value: fmt(m.total_entities), color: 'pr-orange' },
                    { icon: '📦', label: t('total_products'), value: fmt(m.total_products), color: 'pr-blue' },
                    { icon: '🛒', label: t('period_orders'), value: fmt(m.period_orders), color: 'pr-green' },
                    { icon: '💰', label: t('period_revenue'), value: fmtCurrency(m.period_revenue), color: 'pr-green' },
                    { icon: '🔄', label: t('active_subscriptions'), value: fmt(m.active_subscriptions), color: 'pr-purple' },
                ];

            default:
                return Object.entries(m)
                    .filter(([k, v]) => typeof v !== 'object')
                    .map(([k, v]) => ({
                        icon: '📊',
                        label: t(k, k.replace(/_/g, ' ')),
                        value: typeof v === 'number' ? fmt(v) : String(v),
                        color: 'pr-blue'
                    }));
        }
    }

    // ═══════════════════════════════════════════
    // RENDER CHART
    // ═══════════════════════════════════════════
    function ensureChartJs() {
        if (typeof Chart !== 'undefined') return Promise.resolve();
        if (_chartJsPromise) return _chartJsPromise;

        _chartJsPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = CHARTJS_CDN;
            script.onload = function () { resolve(); };
            script.onerror = function () {
                _chartJsPromise = null;
                reject(new Error('Chart.js CDN failed to load'));
            };
            document.head.appendChild(script);
        });
        return _chartJsPromise;
    }

    async function renderChart(type, timeSeries) {
        const canvas = $('#prMainChart');
        if (!canvas) return;

        const wrapper = canvas.parentElement;

        // Destroy previous chart instance before creating new one
        if (mainChart) {
            mainChart.destroy();
            mainChart = null;
        }

        if (!timeSeries || timeSeries.length === 0) {
            if (wrapper) wrapper.style.display = 'none';
            return;
        }
        if (wrapper) wrapper.style.display = 'block';

        // Ensure Chart.js is loaded
        try {
            await ensureChartJs();
        } catch (e) {
            console.error('Chart.js failed to load:', e);
            if (wrapper) wrapper.style.display = 'none';
            return;
        }

        // Set explicit height on wrapper and canvas for desktop rendering
        if (wrapper) {
            wrapper.style.height = '400px';
            wrapper.style.position = 'relative';
        }
        canvas.style.width = '100%';
        canvas.style.height = '100%';

        // Force canvas LTR even in RTL mode
        canvas.dir = 'ltr';
        canvas.setAttribute('dir', 'ltr');
        if (wrapper) {
            wrapper.dir = 'ltr';
            wrapper.setAttribute('dir', 'ltr');
        }

        // Force reflow before rendering to ensure correct layout dimensions
        void canvas.offsetHeight;
        if (wrapper) void wrapper.offsetHeight;

        const labels = timeSeries.map(d => d.period);

        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--primary-color').trim() || '#4F46E5';
        const successColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--success-color').trim() || '#10B981';

        // Build datasets based on report type
        const chartConfig = getChartConfig(type, timeSeries, labels, primaryColor, successColor);

        mainChart = new Chart(canvas.getContext('2d'), chartConfig);

        // Multi-stage resize to ensure correct rendering on desktop
        // Stage 1: immediate resize after render
        if (mainChart) mainChart.resize();

        // Stage 2: delayed resize (100ms)
        setTimeout(function () {
            if (mainChart) mainChart.resize();
        }, 100);

        // Stage 3: further delayed resize (300ms) for slow layout recalc
        setTimeout(function () {
            if (mainChart) mainChart.resize();
        }, 300);
    }

    function getChartConfig(type, timeSeries, labels, primaryColor, successColor) {
        switch (type) {
            case 'ads_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_impressions', 'Views'),
                                data: timeSeries.map(d => parseFloat(d.views) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('total_clicks', 'Clicks'),
                                data: timeSeries.map(d => parseFloat(d.clicks) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('total_impressions', 'Views'), t('total_clicks', 'Clicks'), false)
                };

            case 'products_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('units_sold', 'Units Sold'),
                                data: timeSeries.map(d => parseFloat(d.units_sold) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('revenue', 'Revenue'),
                                data: timeSeries.map(d => parseFloat(d.revenue) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('units_sold', 'Units Sold'), t('revenue', 'Revenue'), true)
                };

            case 'returns_complaints':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_returns', 'Returns'),
                                data: timeSeries.map(d => parseFloat(d.return_count) || 0),
                                backgroundColor: '#EF4444' + '80',
                                borderColor: '#EF4444',
                                borderWidth: 1,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, position: CFG.dir === 'rtl' ? 'right' : 'left' }
                        }
                    }
                };

            case 'customer_behavior':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('new_users', 'New Users'),
                                data: timeSeries.map(d => parseFloat(d.new_users) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, position: CFG.dir === 'rtl' ? 'right' : 'left' }
                        }
                    }
                };

            case 'delivery_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_deliveries', 'Deliveries'),
                                data: timeSeries.map(d => parseFloat(d.delivery_count) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('delivery_fees', 'Delivery Fees'),
                                data: timeSeries.map(d => parseFloat(d.delivery_fees) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('total_deliveries', 'Deliveries'), t('delivery_fees', 'Delivery Fees'), true)
                };

            default:
                // Default: orders + revenue (for sales, orders, entities, platform)
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('orders', 'Orders'),
                                data: timeSeries.map(d => parseFloat(d.order_count) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('revenue', 'Revenue'),
                                data: timeSeries.map(d => parseFloat(d.revenue) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('orders', 'Orders'), t('revenue', 'Revenue'), true)
                };
        }
    }

    function buildChartOptions(leftLabel, rightLabel, rightIsCurrency) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.datasetIndex === 1 && rightIsCurrency) {
                                return ctx.dataset.label + ': ' + fmtCurrency(ctx.raw);
                            }
                            return ctx.dataset.label + ': ' + fmt(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: CFG.dir === 'rtl' ? 'right' : 'left',
                    title: { display: true, text: leftLabel },
                    beginAtZero: true,
                },
                y1: {
                    type: 'linear',
                    position: CFG.dir === 'rtl' ? 'left' : 'right',
                    title: { display: true, text: rightLabel },
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                },
            }
        };
    }

    // ═══════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════
    function renderTable(type, metrics) {
        const thead = $('#prDataTableHead');
        const tbody = $('#prDataTableBody');
        if (!thead || !tbody) return;

        // Build table from metrics
        if (type === 'products_performance' && metrics.top_products) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('product_name'))}</th>
                <th>${h(t('quantity'))}</th>
                <th>${h(t('revenue'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_products.map((p, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(p.product_name || '-')}</td>
                    <td>${fmt(p.total_quantity)}</td>
                    <td>${fmtCurrency(p.total_revenue)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="4">${h(t('no_data'))}</td></tr>`;
            return;
        }

        if (type === 'entities_performance' && metrics.top_entities) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('entity_name'))}</th>
                <th>${h(t('order_count'))}</th>
                <th>${h(t('revenue'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_entities.map((e, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(e.store_name || '-')}</td>
                    <td>${fmt(e.order_count)}</td>
                    <td>${fmtCurrency(e.total_revenue)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="4">${h(t('no_data'))}</td></tr>`;
            return;
        }

        if (type === 'ads_performance' && metrics.top_ads) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('ad_type', 'Ad Type'))}</th>
                <th>${h(t('ad_target', 'Target'))}</th>
                <th>${h(t('total_impressions', 'Views'))}</th>
                <th>${h(t('total_clicks', 'Clicks'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_ads.map((a, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(a.ad_type || '-')}</td>
                    <td>${h(a.ad_target || '-')}</td>
                    <td>${fmt(a.total_views)}</td>
                    <td>${fmt(a.total_clicks)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="5">${h(t('no_data'))}</td></tr>`;
            return;
        }

        // Default: show all metrics as key-value table
        const entries = Object.entries(metrics).filter(([k, v]) => typeof v !== 'object');
        thead.innerHTML = `<tr>
            <th>${h(t('metrics'))}</th>
            <th>${h(t('value'))}</th>
        </tr>`;
        tbody.innerHTML = entries.map(([k, v]) =>
            `<tr>
                <td>${h(t(k, k.replace(/_/g, ' ')))}</td>
                <td>${typeof v === 'number' ? fmt(v) : h(String(v))}</td>
            </tr>`
        ).join('') || `<tr><td colspan="2">${h(t('no_data'))}</td></tr>`;
    }

    // ═══════════════════════════════════════════
    // EXPORT
    // ═══════════════════════════════════════════
    async function requestExport(format) {
        if (!currentReportData) {
            alert(t('no_data', 'No data available for the selected period'));
            return;
        }

        // Also log export to backend for tracking
        try {
            apiPost('export', {
                report_type: currentReportData.report_type,
                start_date: currentReportData.period?.start,
                end_date: currentReportData.period?.end,
                tenant_id: currentReportData.tenant_id || '',
                export_format: format
            }).catch(function (err) { console.error('Export audit log failed:', err); });
        } catch (err) { console.error('Export audit log failed:', err); }

        // Generate actual downloadable file client-side
        try {
            if (format === 'csv') {
                exportCSV(currentReportData);
            } else if (format === 'excel') {
                exportExcel(currentReportData);
            } else if (format === 'pdf') {
                exportPDF(currentReportData);
            }
        } catch (e) {
            console.error('Export failed:', e);
            alert(t('export_failed', 'Export failed'));
        }
    }

    /**
     * Build export rows from report data (shared by CSV/Excel)
     */
    function buildExportRows(data) {
        var headers = [];
        var rows = [];
        var type = data.report_type;
        var metrics = data.metrics || {};

        // If there's a top list table, export that
        if (type === 'products_performance' && metrics.top_products) {
            headers = ['#', t('product_name', 'Product Name'), t('quantity', 'Quantity'), t('revenue', 'Revenue')];
            rows = metrics.top_products.map(function (p, i) {
                return [i + 1, p.product_name || '-', p.total_quantity || 0, p.total_revenue || 0];
            });
        } else if (type === 'entities_performance' && metrics.top_entities) {
            headers = ['#', t('entity_name', 'Entity Name'), t('order_count', 'Order Count'), t('revenue', 'Revenue')];
            rows = metrics.top_entities.map(function (e, i) {
                return [i + 1, e.store_name || '-', e.order_count || 0, e.total_revenue || 0];
            });
        } else if (type === 'ads_performance' && metrics.top_ads) {
            headers = ['#', t('ad_type', 'Ad Type'), t('ad_target', 'Target'), t('total_impressions', 'Views'), t('total_clicks', 'Clicks')];
            rows = metrics.top_ads.map(function (a, i) {
                return [i + 1, a.ad_type || '-', a.ad_target || '-', a.total_views || 0, a.total_clicks || 0];
            });
        } else {
            // Default: metrics key-value pairs
            headers = [t('metric', 'Metric'), t('value', 'Value')];
            Object.entries(metrics).forEach(function (entry) {
                var k = entry[0], v = entry[1];
                if (typeof v !== 'object') {
                    rows.push([t(k, k.replace(/_/g, ' ')), v]);
                }
            });
        }

        // If there's time series, add it as a separate section
        var timeSeries = data.time_series || [];
        if (timeSeries.length > 0) {
            rows.push([]); // blank row separator
            var tsKeys = Object.keys(timeSeries[0]);
            rows.push(tsKeys.map(function (k) { return t(k, k.replace(/_/g, ' ')); }));
            timeSeries.forEach(function (row) {
                rows.push(tsKeys.map(function (k) { return row[k] != null ? row[k] : ''; }));
            });
        }

        return { headers: headers, rows: rows };
    }

    /**
     * Export as CSV with proper encoding for Arabic/RTL
     */
    function exportCSV(data) {
        var result = buildExportRows(data);
        var csvContent = '\uFEFF'; // BOM for Excel UTF-8 recognition

        // Add report info header
        csvContent += t('report_type', 'Report Type') + ',' + t(data.report_type, data.report_type) + '\n';
        if (data.period) {
            csvContent += t('period', 'Period') + ',' + (data.period.start || '') + ' - ' + (data.period.end || '') + '\n';
        }
        csvContent += '\n';

        // Add headers
        csvContent += result.headers.map(escapeCsvCell).join(',') + '\n';

        // Add rows
        result.rows.forEach(function (row) {
            csvContent += row.map(escapeCsvCell).join(',') + '\n';
        });

        downloadFile(csvContent, 'report_' + data.report_type + '.csv', 'text/csv;charset=utf-8;');
    }

    /**
     * Export as Excel (HTML table format that Excel can open)
     */
    function exportExcel(data) {
        var result = buildExportRows(data);
        var isRtl = CFG.dir === 'rtl';
        var dirAttr = isRtl ? ' dir="rtl"' : '';

        var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        html += '<head><meta charset="utf-8">';
        html += '<style>';
        html += 'table { border-collapse: collapse; width: 100%; direction: ' + (isRtl ? 'rtl' : 'ltr') + '; }';
        html += 'th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: ' + (isRtl ? 'right' : 'left') + '; }';
        html += 'th { background-color: #4F46E5; color: #fff; font-weight: bold; }';
        html += '.info { font-weight: bold; color: #374151; }';
        html += '</style></head>';
        html += '<body' + dirAttr + '>';

        // Report info
        html += '<table><tr><td class="info">' + h(t('report_type', 'Report Type')) + '</td>';
        html += '<td>' + h(t(data.report_type, data.report_type)) + '</td></tr>';
        if (data.period) {
            html += '<tr><td class="info">' + h(t('period', 'Period')) + '</td>';
            html += '<td>' + h((data.period.start || '') + ' - ' + (data.period.end || '')) + '</td></tr>';
        }
        html += '</table><br>';

        // Data table
        html += '<table><thead><tr>';
        result.headers.forEach(function (hdr) {
            html += '<th>' + h(String(hdr)) + '</th>';
        });
        html += '</tr></thead><tbody>';
        result.rows.forEach(function (row) {
            if (row.length === 0) {
                html += '<tr><td colspan="' + result.headers.length + '">&nbsp;</td></tr>';
                return;
            }
            html += '<tr>';
            row.forEach(function (cell) {
                html += '<td>' + h(String(cell != null ? cell : '')) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></body></html>';

        var blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'report_' + data.report_type + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /**
     * Export as PDF via browser print dialog
     */
    function exportPDF(data) {
        var result = buildExportRows(data);
        var isRtl = CFG.dir === 'rtl';
        var dirAttr = isRtl ? ' dir="rtl"' : '';

        var html = '<!DOCTYPE html><html' + dirAttr + '><head><meta charset="utf-8">';
        html += '<title>' + h(t('report_type', 'Report')) + ': ' + h(t(data.report_type, data.report_type)) + '</title>';
        html += '<style>';
        html += '* { margin: 0; padding: 0; box-sizing: border-box; }';
        html += 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; padding: 24px; direction: ' + (isRtl ? 'rtl' : 'ltr') + '; text-align: ' + (isRtl ? 'right' : 'left') + '; }';
        html += 'h1 { font-size: 18px; margin-bottom: 8px; color: #1f2937; text-align: ' + (isRtl ? 'right' : 'left') + '; }';
        html += 'p { font-size: 13px; color: #6b7280; margin-bottom: 16px; text-align: ' + (isRtl ? 'right' : 'left') + '; }';
        html += 'table { border-collapse: collapse; width: 100%; margin-top: 12px; direction: ' + (isRtl ? 'rtl' : 'ltr') + '; }';
        html += 'th, td { border: 1px solid #d1d5db; padding: 8px 12px; text-align: ' + (isRtl ? 'right' : 'left') + '; font-size: 13px; }';
        html += 'th { background-color: #4F46E5; color: #fff; font-weight: 600; }';
        html += 'tr:nth-child(even) { background-color: #f9fafb; }';
        html += '@media print { body { padding: 0; } }';
        html += '</style></head><body>';

        html += '<h1>' + h(t(data.report_type, data.report_type)) + '</h1>';
        if (data.period) {
            html += '<p>' + h(t('period', 'Period')) + ': ' + h((data.period.start || '') + ' - ' + (data.period.end || '')) + '</p>';
        }

        html += '<table><thead><tr>';
        result.headers.forEach(function (hdr) {
            html += '<th>' + h(String(hdr)) + '</th>';
        });
        html += '</tr></thead><tbody>';
        result.rows.forEach(function (row) {
            if (row.length === 0) return;
            html += '<tr>';
            row.forEach(function (cell) {
                html += '<td>' + h(String(cell != null ? cell : '')) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></body></html>';

        var printWin = window.open('', '_blank');
        if (printWin) {
            printWin.document.write(html);
            printWin.document.close();
            printWin.focus();
            // Allow time for content to render before triggering print dialog
            setTimeout(function () { printWin.print(); }, 500);
        } else {
            alert(t('popup_blocked', 'Please allow popups to export PDF'));
        }
    }

    function escapeCsvCell(val) {
        var str = String(val != null ? val : '');
        if (str.indexOf(',') !== -1 || str.indexOf('"') !== -1 || str.indexOf('\n') !== -1) {
            return '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    }

    function downloadFile(content, filename, mimeType) {
        var blob = new Blob([content], { type: mimeType });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // ═══════════════════════════════════════════
    // UI HELPERS
    // ═══════════════════════════════════════════
    function showLoading(show) {
        const el = $('#prLoading');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    function showResults(show) {
        const el = $('#prReportResults');
        const exp = $('#prExportSection');
        const noData = $('#prNoData');
        if (el) {
            el.style.display = show ? 'block' : 'none';
            // Deliberate reflow trigger: reading offsetHeight forces the browser
            // to recalculate layout, fixing render issues on desktop after display change
            if (show) {
                void el.offsetHeight;
                // Re-trigger chart resize when results become visible
                if (mainChart) {
                    setTimeout(function () {
                        if (mainChart) mainChart.resize();
                    }, 150);
                }
            }
        }
        if (exp) exp.style.display = show ? 'flex' : 'none';
        if (noData) noData.style.display = 'none';
    }

    function hideResults() {
        showResults(false);
        const noData = $('#prNoData');
        if (noData) noData.style.display = 'none';
    }

    function showNoData(msg) {
        const el = $('#prNoData');
        if (el) {
            el.style.display = 'block';
            if (msg) el.querySelector('p').textContent = msg;
        }
        showResults(false);
    }

    // ═══════════════════════════════════════════
    // BOOTSTRAP (fragment + standalone + SPA)
    // ═══════════════════════════════════════════
    function bootstrap() {
        if ($('#platformReportApp')) {
            init();
        }
    }

    // For admin SPA navigation
    window.page = { run: bootstrap };

    // For fragment mode (AJAX load)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        // Already loaded (fragment)
        const checkReady = setInterval(function () {
            if ($('#platformReportApp')) {
                clearInterval(checkReady);
                bootstrap();
            }
        }, 100);
        // Cleanup after 10s
        setTimeout(function () { clearInterval(checkReady); }, 10000);
    }

})();
