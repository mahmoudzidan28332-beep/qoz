/**
 * /admin/assets/js/pages/auctions.js — Production v2.0
 *
 * ─ الإصلاحات الجوهرية ─────────────────────────────────────
 * ✅ AF (AdminFramework) كان غير معرّف → notify() مستقلة
 * ✅ tab IDs موحّدة مع PHP: auc-tab-* بدل tab-*
 * ✅ showState() موحّدة (aucLoading/aucEmpty/aucError/aucTableContainer)
 * ✅ btn-primary للتعديل (موحَّد)
 * ✅ credentials: 'same-origin' على كل fetch
 * ✅ ESC يُغلق form card
 * ✅ Admin.page.register + window.page
 * ✅ لا AF.Form.validate() — فحص صريح
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════
    // CONFIG & STATE
    // ════════════════════════════════════════════════════════
    let CFG = {}, STRINGS = {}, API = {};

    function reloadConfig() {
        CFG     = window.AUCTIONS_CONFIG || {};
        STRINGS = CFG.strings || {};
        const u = CFG.urls || {};
        API = {
            auctions:     u.auctions     || '/api/auctions',
            bids:         u.bids         || '/api/auction_bids',
            translations: u.translations || '/api/auction_translations',
            products:     u.products     || '/api/products',
            currencies:   u.currencies   || '/api/currencies',
            languages:    u.languages    || '/api/languages',
            entities:     u.entities     || '/api/entities',
        };
    }

    // ════════════════════════════════════════════════════════
    // PLATFORM ADMIN — Tenant Context
    // ════════════════════════════════════════════════════════
    const platformAdmin = {
        activeTenantId: 0,

        /** Returns the effective tenant_id for all API calls. */
        getTenantId: function () {
            return this.activeTenantId !== 0 ? this.activeTenantId : (CFG.tenantId || 0);
        },

        /** Returns 'tenant_id=N' query string parameter. */
        tenantParam: function () {
            return 'tenant_id=' + this.getTenantId();
        },

        /** Wires up the Platform Admin panel controls. */
        bind: function () {
            if (!CFG.isPlatformAdmin) return;
            const self          = this;
            const searchInput   = document.getElementById('paUserSearch');
            const searchBtn     = document.getElementById('paUserSearchBtn');
            const searchResults = document.getElementById('paUserSearchResults');
            const tenantSelect  = document.getElementById('paTenantSelect');
            const applyBtn      = document.getElementById('paApplyTenantBtn');
            const banner        = document.getElementById('paActiveTenantBanner');
            const bannerLabel   = document.getElementById('paActiveTenantLabel');
            const clearBtn      = document.getElementById('paClearTenantBtn');

            if (!searchBtn) return;

            // Search users by ID or name
            searchBtn.addEventListener('click', function () {
                const q    = searchInput ? searchInput.value.trim() : '';
                if (!q) return;
                const isId = /^\d+$/.test(q);
                const url  = isId
                    ? (CFG.usersApi || '/api/users') + '/' + encodeURIComponent(q)
                    : (CFG.usersApi || '/api/users') + '?search=' + encodeURIComponent(q) + '&limit=20';
                fetch(url, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(json => {
                        const users = isId
                            ? (json.data ? [json.data] : (json.id ? [json] : []))
                            : (json.data && Array.isArray(json.data) ? json.data : (Array.isArray(json.items) ? json.items : []));
                        if (!searchResults) return;
                        searchResults.innerHTML = '';
                        searchResults.style.display = users.length ? 'block' : 'none';
                        users.forEach(u => {
                            const item = document.createElement('div');
                            item.className   = 'pa-user-item';
                            item.textContent = (u.name || u.username || '') + ' (#' + u.id + ')';
                            item.addEventListener('click', () => {
                                if (searchResults) searchResults.style.display = 'none';
                                if (searchInput) searchInput.value = item.textContent;
                                self.loadTenantsForUser(u.id, tenantSelect, applyBtn);
                            });
                            searchResults.appendChild(item);
                        });
                    })
                    .catch(() => {});
            });

            // Load all tenants for the dropdown on load
            self.loadAllTenants(tenantSelect, applyBtn);

            // Apply selected tenant
            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    const tid = parseInt(tenantSelect ? tenantSelect.value : '', 10) || 0;
                    if (!tid) return;
                    self.activeTenantId = tid;
                    if (banner) banner.style.display = 'flex';
                    if (bannerLabel) {
                        const opt = tenantSelect ? tenantSelect.options[tenantSelect.selectedIndex] : null;
                        bannerLabel.textContent = t('platform_admin.acting_on_behalf', 'Acting on behalf of') + ': ' + (opt ? opt.text : 'Tenant #' + tid);
                    }
                    if (el.auctionTenantId) el.auctionTenantId.value = tid;
                    loadAuctions(1);
                });
            }

            // Clear selected tenant
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    self.activeTenantId = 0;
                    if (banner) banner.style.display = 'none';
                    if (tenantSelect) tenantSelect.value = '';
                    if (applyBtn) applyBtn.disabled = true;
                    if (el.auctionTenantId) el.auctionTenantId.value = CFG.tenantId || 0;
                    loadAuctions(1);
                });
            }

            // Enable apply button when a tenant is selected
            if (tenantSelect) {
                tenantSelect.addEventListener('change', function () {
                    if (applyBtn) applyBtn.disabled = !tenantSelect.value;
                });
            }
        },

        /** Populate tenant dropdown with all tenants. */
        loadAllTenants: function (selectEl, applyBtn) {
            if (!selectEl) return;
            const url = (CFG.tenantsApi || '/api/tenants') + '?limit=500';
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    const list = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                    list.forEach(tn => {
                        const opt       = document.createElement('option');
                        opt.value       = tn.id;
                        opt.textContent = (tn.name || tn.tenant_name || '') + ' (#' + tn.id + ')';
                        selectEl.appendChild(opt);
                    });
                    if (applyBtn) applyBtn.disabled = !selectEl.value;
                })
                .catch(() => {});
        },

        /** Populate tenant dropdown filtered by user. */
        loadTenantsForUser: function (userId, selectEl, applyBtn) {
            if (!selectEl) return;
            const url = (CFG.usersApi || '/api/users') + '/' + encodeURIComponent(userId) + '/tenants';
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    const list = json.data || json.items || [];
                    while (selectEl.options.length > 1) selectEl.remove(1);
                    list.forEach(tn => {
                        const opt       = document.createElement('option');
                        opt.value       = tn.tenant_id || tn.id;
                        opt.textContent = (tn.tenant_name || tn.name || '') + ' (#' + (tn.tenant_id || tn.id) + ')';
                        selectEl.appendChild(opt);
                    });
                    if (applyBtn) applyBtn.disabled = !selectEl.value;
                })
                .catch(() => {});
        },
    };

    const state = {
        page:           1,
        perPage:        25,
        auctions:       [],
        languages:      [],
        currencies:     [],
        currencyMap:    {},
        filters:        {},
        currentAuction: null,
    };

    let el = {};

    // ════════════════════════════════════════════════════════
    // i18n — من CONFIG.strings فقط
    // ════════════════════════════════════════════════════════
    function t(key, fallback) {
        const parts = key.split('.');
        let val = STRINGS;
        for (const k of parts) {
            if (val && typeof val === 'object' && k in val) val = val[k];
            else return fallback || key;
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (auc- prefix)
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        const AF = window.AdminFramework;
        if (AF) {
            if (type === 'success' && AF.success) return AF.success(message);
            if (type === 'error'   && AF.error)   return AF.error(message);
            if (type === 'warning' && AF.warning)  return AF.warning(message);
            if (AF.notify) return AF.notify(message, type);
        }
        let container = document.getElementById('aucNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'aucNotifications';
            container.className = 'auc-notifications';
            const page = document.getElementById('auctionsPageContainer');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }
        const toast = document.createElement('div');
        toast.className = `auc-toast auc-toast-${type}`;
        toast.setAttribute('role', 'alert');
        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);
        const close = document.createElement('button');
        close.className = 'auc-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', () => toast.remove());
        toast.appendChild(close);
        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4500);
    }

    // ════════════════════════════════════════════════════════
    // FETCH HELPER
    // ════════════════════════════════════════════════════════
    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CFG.csrfToken || '',
            },
        };
        if (options.body && typeof options.body === 'string') {
            defaults.headers['Content-Type'] = 'application/json';
        }
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };
        const res  = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
        return data;
    }

    function esc(txt) {
        if (txt == null) return '';
        const d = document.createElement('div');
        d.textContent = String(txt);
        return d.innerHTML;
    }

    function formatPrice(amount, currencyCode) {
        if (amount == null || amount === '') return '—';
        const cur      = state.currencyMap[currencyCode] || null;
        const decimals = cur ? (parseInt(cur.decimal_places, 10) || 2) : 2;
        const num      = Number(amount).toFixed(decimals);
        if (!cur) return `${num} ${currencyCode || ''}`.trim();
        const sym = cur.symbol || currencyCode;
        return cur.symbol_position === 'after' ? `${num} ${sym}` : `${sym}${num}`;
    }

    function toDatetimeLocal(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            const p = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
        } catch { return ''; }
    }

    function generateSlug(name) {
        const suffix = '-' + Date.now().toString(36);
        return name.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 255 - suffix.length) + suffix;
    }

    // ════════════════════════════════════════════════════════
    // TABLE STATE
    // ════════════════════════════════════════════════════════
    function showState(which, msg = '') {
        const loading   = document.getElementById('aucLoading');
        const empty     = document.getElementById('aucEmpty');
        const error     = document.getElementById('aucError');
        const container = document.getElementById('aucTableContainer');
        const errMsg    = document.getElementById('aucErrorMessage');

        [loading, empty, error, container].forEach(e => { if (e) e.style.display = 'none'; });

        switch (which) {
            case 'loading': if (loading)   loading.style.display   = 'flex';  break;
            case 'empty':   if (empty)     empty.style.display     = 'flex';  break;
            case 'error':
                if (error)  error.style.display = 'flex';
                if (errMsg && msg) errMsg.textContent = msg;
                break;
            default:        if (container) container.style.display = 'block'; break;
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD DROPDOWN DATA
    // ════════════════════════════════════════════════════════
    async function loadDropdownData() {
        // Currencies
        try {
            const res = await apiFetch(`${API.currencies}?format=json`);
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data : (res.data?.items || res.data?.data || []);
                applyCurrencies(data);
            }
        } catch (_) {
            applyCurrencies([
                { id: 1, code: 'SAR', name: 'Saudi Riyal',  symbol: '﷼', symbol_position: 'after',  decimal_places: 2 },
                { id: 2, code: 'USD', name: 'US Dollar',    symbol: '$',  symbol_position: 'before', decimal_places: 2 },
                { id: 3, code: 'EUR', name: 'Euro',          symbol: '€',  symbol_position: 'before', decimal_places: 2 },
                { id: 4, code: 'AED', name: 'UAE Dirham',   symbol: 'د.إ',symbol_position: 'after',  decimal_places: 2 },
            ]);
        }

        // Languages
        try {
            const res = await apiFetch(`${API.languages}?format=json`);
            if (res.success) {
                state.languages = Array.isArray(res.data) ? res.data : (res.data?.items || []);
                populateSelect(el.auctionLangSelect, state.languages, 'code', 'name',
                    t('translations.choose','Choose language'));
            }
        } catch (_) {}

        // Products
        try {
            const res = await apiFetch(`${API.products}?format=json&${platformAdmin.tenantParam()}&lang=${CFG.lang}&limit=500`);
            if (res.success && el.auctionProduct) {
                const data = res.data?.items || (Array.isArray(res.data) ? res.data : []);
                populateSelect(el.auctionProduct, data, 'id', 'name',
                    t('form.fields.product_id.select','Select product (optional)'));
            }
        } catch (_) {}

        // Entities
        try {
            const res = await apiFetch(`${API.entities}?format=json&${platformAdmin.tenantParam()}&lang=${CFG.lang}&limit=500`);
            if (res.success && el.auctionEntity) {
                const data = res.data?.items || (Array.isArray(res.data) ? res.data : []);
                populateSelect(el.auctionEntity, data, 'id', 'store_name',
                    t('form.fields.entity_id.select','Select entity'));
            }
        } catch (_) {}
    }

    function applyCurrencies(data) {
        state.currencies = data;
        state.currencyMap = Object.fromEntries(data.map(c => [c.code, c]));
        if (!el.auctionCurrency) return;
        el.auctionCurrency.innerHTML = `<option value="">${t('form.fields.currency_id.select','Select currency')}</option>`;
        data.forEach(cur => {
            const o = document.createElement('option');
            o.value = cur.id;
            o.textContent = `${cur.code} – ${cur.name}${cur.symbol ? ` (${cur.symbol})` : ''}`;
            el.auctionCurrency.appendChild(o);
        });
    }

    function populateSelect(sel, data, valKey, txtKey, placeholder = '') {
        if (!sel) return;
        sel.innerHTML = '';
        if (placeholder) {
            const o = document.createElement('option');
            o.value = '';
            o.textContent = placeholder;
            sel.appendChild(o);
        }
        data.forEach(item => {
            const o = document.createElement('option');
            o.value = item[valKey];
            o.textContent = item[txtKey];
            sel.appendChild(o);
        });
    }

    // ════════════════════════════════════════════════════════
    // LOAD AUCTIONS
    // ════════════════════════════════════════════════════════
    async function loadAuctions(page = 1) {
        showState('loading');
        state.page = page;

        const params = new URLSearchParams({
            page:      page,
            limit:     state.perPage,
            tenant_id: platformAdmin.getTenantId() || 0,
            lang:      CFG.lang || 'en',
            format:    'json',
            ...state.filters,
        });

        try {
            const result = await apiFetch(`${API.auctions}?${params}`);

            if (result.success && result.data) {
                const items = result.data.items || (Array.isArray(result.data) ? result.data : []);
                const meta  = result.data.meta  || result.meta || {};
                state.auctions = items;
                const total    = meta.total || items.length;

                if (!items.length) {
                    showState('empty');
                } else {
                    showState('table');
                    renderTable(items);
                    renderPagination(page, total, meta.per_page || state.perPage);
                }
                const infoEl = document.getElementById('aucPaginationInfo');
                if (infoEl) {
                    const start = total > 0 ? (page - 1) * state.perPage + 1 : 0;
                    const end   = Math.min(page * state.perPage, total);
                    infoEl.textContent = total > 0 ? `${start}–${end} / ${total}` : '';
                }
            } else {
                showState('error', result.message || t('messages.error.load_failed','Failed to load'));
            }
        } catch (e) {
            console.error('[Auctions] loadAuctions:', e);
            showState('error', e.message || t('messages.error.load_failed','Failed to load'));
        }
    }

    // ════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════
    function renderTable(items) {
        const tbody = document.getElementById('auctionTableBody');
        if (!tbody) return;

        tbody.innerHTML = items.map(a => {
            const featured = a.is_featured == 1
                ? '<i class="fas fa-star auc-featured-star" aria-hidden="true"></i>' : '';
            const tenantCol = (CFG.isSuperAdmin || CFG.isPlatformAdmin)
                ? `<td>${esc(a.tenant_name || `#${a.tenant_id}`)}</td>` : '';
            const price = a.current_price
                ? `<span class="auc-price-current">${formatPrice(a.current_price, a.currency_code)}</span>`
                : '—';
            const endDate = a.end_date
                ? `<small>${esc(new Date(a.end_date).toLocaleString())}</small>` : '—';

            // ✅ btn-primary للتعديل
            const editBtn = CFG.canEdit
                ? `<button class="btn btn-sm btn-primary auc-edit-btn" data-id="${esc(a.id)}" aria-label="${t('table.headers.actions','Edit')}">
                       <i class="fas fa-edit" aria-hidden="true"></i>
                   </button>`
                : '';
            const delBtn = CFG.canDelete
                ? `<button class="btn btn-sm btn-danger auc-del-btn" data-id="${esc(a.id)}" aria-label="${t('form.buttons.delete','Delete')}">
                       <i class="fas fa-trash" aria-hidden="true"></i>
                   </button>`
                : '';

            return `
                <tr data-id="${esc(a.id)}">
                    <td>${esc(a.id)}</td>
                    ${tenantCol}
                    <td>${esc(a.entity_name || `#${a.entity_id || ''}`)}</td>
                    <td>
                        <strong>${esc(a.translated_title || a.title || `Auction #${a.id}`)}${featured}</strong>
                        <span class="auc-slug">${esc(a.slug || '')}</span>
                    </td>
                    <td><span class="badge badge-${esc(a.auction_type)}">${esc(a.auction_type)}</span></td>
                    <td><span class="badge badge-${esc(a.status)}">${esc(a.status)}</span></td>
                    <td>${price}</td>
                    <td>${esc(a.total_bids || 0)}</td>
                    <td>${endDate}</td>
                    <td>
                        <div class="table-actions">
                            ${editBtn}
                            ${delBtn}
                        </div>
                    </td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('.auc-edit-btn').forEach(b =>
            b.addEventListener('click', () => editAuction(b.dataset.id)));
        tbody.querySelectorAll('.auc-del-btn').forEach(b =>
            b.addEventListener('click', () => deleteAuction(b.dataset.id)));
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(page, total, perPage) {
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        const pagEl = document.getElementById('aucPagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const makeBtn = (label, target, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => loadAuctions(target));
            return btn;
        };

        pagEl.appendChild(makeBtn('&laquo;', page - 1, false, page <= 1));
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === page, i === page));
            } else if (i === page - 3 || i === page + 3) {
                const sp = document.createElement('span');
                sp.className = 'pagination-dots';
                sp.textContent = '\u2026';
                pagEl.appendChild(sp);
            }
        }
        pagEl.appendChild(makeBtn('&raquo;', page + 1, false, page >= totalPages));
    }

    // ════════════════════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════════════════════
    function initTabs() {
        document.querySelectorAll('#auctionForm .auc-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                document.querySelectorAll('#auctionForm .auc-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('#auctionForm .auc-tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                const content = document.getElementById(`auc-tab-${target}`);
                if (content) content.style.display = 'block';
                if (target === 'bids' && state.currentAuction?.id) {
                    loadBids(state.currentAuction.id);
                }
            });
        });
    }

    function resetTabs() {
        document.querySelectorAll('#auctionForm .auc-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('#auctionForm .auc-tab-content').forEach(c => c.style.display = 'none');
        const firstBtn = document.querySelector('#auctionForm .auc-tab-btn[data-tab="general"]');
        const firstContent = document.getElementById('auc-tab-general');
        if (firstBtn) firstBtn.classList.add('active');
        if (firstContent) firstContent.style.display = 'block';
    }

    // ════════════════════════════════════════════════════════
    // FORM
    // ════════════════════════════════════════════════════════
    function showForm(auction = null) {
        if (!el.formContainer || !el.form) return;
        state.currentAuction = auction;
        el.form.reset();
        resetTabs();

        // Clear bids + translations
        if (el.bidsTableBody) el.bidsTableBody.innerHTML = '';
        document.getElementById('bidsLoading')?.style && (document.getElementById('bidsLoading').style.display = 'none');
        document.getElementById('bidsEmpty')?.style   && (document.getElementById('bidsEmpty').style.display = 'none');
        document.getElementById('bidsTableWrapper')?.style && (document.getElementById('bidsTableWrapper').style.display = 'none');
        if (el.auctionTranslations) el.auctionTranslations.innerHTML = '';

        const titleEl = document.getElementById('auctionFormTitle');

        if (auction) {
            if (titleEl) titleEl.textContent = t('form.edit_title','Edit Auction');
            if (el.auctionFormId)    el.auctionFormId.value  = auction.id   || '';
            if (el.auctionTitle)     el.auctionTitle.value   = auction.title || '';
            if (el.auctionSlug)      el.auctionSlug.value    = auction.slug  || '';
            if (el.auctionProduct)   el.auctionProduct.value = auction.product_id || '';
            if (el.auctionEntity)    el.auctionEntity.value  = auction.entity_id  || '';
            if (el.auctionType)      el.auctionType.value    = auction.auction_type    || 'normal';
            if (el.auctionStatus)    el.auctionStatus.value  = auction.status          || 'draft';
            if (el.auctionCondition) el.auctionCondition.value = auction.condition_type || 'new';
            if (el.auctionQuantity)  el.auctionQuantity.value  = auction.quantity        || 1;
            if (el.auctionIsFeatured)el.auctionIsFeatured.value = auction.is_featured    || '0';
            if (el.auctionAutoBid)   el.auctionAutoBid.value    = auction.auto_bid_enabled ?? '1';
            if (el.auctionNotes)     el.auctionNotes.value      = auction.notes || '';
            // Pricing
            if (el.auctionStartingPrice)   el.auctionStartingPrice.value   = auction.starting_price  || '';
            if (el.auctionReservePrice)    el.auctionReservePrice.value    = auction.reserve_price   || '';
            if (el.auctionBuyNowPrice)     el.auctionBuyNowPrice.value     = auction.buy_now_price   || '';
            if (el.auctionBidIncrement)    el.auctionBidIncrement.value    = auction.bid_increment   || '5.00';
            if (el.auctionCurrency)        el.auctionCurrency.value        = auction.currency_id     || '';
            if (el.auctionShipping)        el.auctionShipping.value        = auction.shipping_cost   || '0.00';
            if (el.auctionPaymentDeadline) el.auctionPaymentDeadline.value = auction.payment_deadline_hours || '48';
            // Schedule
            if (el.auctionStartDate && auction.start_date) el.auctionStartDate.value = toDatetimeLocal(auction.start_date);
            if (el.auctionEndDate   && auction.end_date)   el.auctionEndDate.value   = toDatetimeLocal(auction.end_date);
            if (el.auctionAutoExtend)       el.auctionAutoExtend.value       = auction.auto_extend        ?? '1';
            if (el.auctionExtendMinutes)    el.auctionExtendMinutes.value    = auction.extend_minutes      || '5';
            if (el.auctionMinExtendBidTime) el.auctionMinExtendBidTime.value = auction.min_extend_bid_time || '5';
            // Bid stats
            updateBidStats(auction);
            // Load translations
            if (auction.id) loadAuctionTranslations(auction.id);
            if (el.btnDeleteAuction) el.btnDeleteAuction.style.display = CFG.canDelete ? 'inline-flex' : 'none';
        } else {
            if (titleEl) titleEl.textContent = t('form.add_title','Add Auction');
            if (el.auctionFormId) el.auctionFormId.value = '';
            if (el.auctionTenantId) el.auctionTenantId.value = platformAdmin.getTenantId() || CFG.tenantId || 0;
            if (el.btnDeleteAuction) el.btnDeleteAuction.style.display = 'none';
        }

        el.formContainer.style.display = 'block';
        setTimeout(() => el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.form) el.form.reset();
        state.currentAuction = null;
    }

    // ════════════════════════════════════════════════════════
    // SAVE — فحص صريح للحقول المطلوبة
    // ════════════════════════════════════════════════════════
    async function saveAuction(e) {
        e.preventDefault();

        // Explicit validation
        const title    = el.auctionTitle?.value?.trim()          || '';
        const price    = el.auctionStartingPrice?.value?.trim()  || '';
        const currency = el.auctionCurrency?.value               || '';

        if (!title) {
            notify(t('form.fields.title.required','Title is required'), 'error');
            el.auctionTitle?.focus();
            // Switch to General tab
            document.querySelector('#auctionForm .auc-tab-btn[data-tab="general"]')?.click();
            return;
        }
        if (!price) {
            notify(t('form.fields.starting_price.required','Starting price is required'), 'error');
            document.querySelector('#auctionForm .auc-tab-btn[data-tab="pricing"]')?.click();
            el.auctionStartingPrice?.focus();
            return;
        }
        if (!currency) {
            notify(t('form.fields.currency_id.select','Please select a currency'), 'warning');
            document.querySelector('#auctionForm .auc-tab-btn[data-tab="pricing"]')?.click();
            return;
        }

        const auctionId = el.auctionFormId?.value?.trim() || '';
        const isEdit    = !!auctionId;
        const fd        = new FormData(el.form);

        const body = {
            tenant_id:              platformAdmin.getTenantId() || CFG.tenantId || 0,
            entity_id:              parseInt(fd.get('entity_id') || 0) || null,
            title,
            slug:                   fd.get('slug')  || generateSlug(title),
            product_id:             fd.get('product_id')   || null,
            auction_type:           fd.get('auction_type') || 'normal',
            status:                 fd.get('status')       || 'draft',
            starting_price:         price,
            reserve_price:          fd.get('reserve_price')  || null,
            current_price:          price,
            buy_now_price:          fd.get('buy_now_price')  || null,
            bid_increment:          fd.get('bid_increment')  || '5.00',
            currency_id:            parseInt(currency) || null,
            auto_bid_enabled:       fd.get('auto_bid_enabled') ?? '1',
            start_date:             fd.get('start_date')     || '',
            end_date:               fd.get('end_date')       || '',
            auto_extend:            fd.get('auto_extend')    ?? '1',
            extend_minutes:         fd.get('extend_minutes') || '5',
            min_extend_bid_time:    fd.get('min_extend_bid_time') || '5',
            is_featured:            fd.get('is_featured')    || '0',
            condition_type:         fd.get('condition_type') || 'new',
            quantity:               fd.get('quantity')       || '1',
            shipping_cost:          fd.get('shipping_cost')  || '0.00',
            payment_deadline_hours: fd.get('payment_deadline_hours') || '48',
            notes:                  fd.get('notes')          || null,
            created_by:             CFG.userId               || null,
        };
        if (isEdit) body.id = auctionId;

        if (el.btnSubmit) {
            el.btnSubmit.disabled = true;
            el.btnSubmit.innerHTML = `<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>`;
        }

        try {
            const result = await apiFetch(API.auctions, {
                method: isEdit ? 'PUT' : 'POST',
                body:   JSON.stringify(body),
            });

            if (result.success) {
                const savedId = isEdit ? auctionId : (result.data?.id || result.data?.items?.[0]?.id);
                // Save translations
                const trans = collectTranslations();
                if (Object.keys(trans).length > 0 && savedId) {
                    await saveAuctionTranslations(savedId, trans);
                }
                notify(isEdit
                    ? t('messages.updated','Auction updated successfully')
                    : t('messages.created','Auction created successfully'),
                    'success'
                );
                hideForm();
                await loadAuctions(state.page);
            } else {
                notify(result.message || t('messages.error.save_failed','Save failed'), 'error');
            }
        } catch (err) {
            console.error('[Auctions] saveAuction:', err);
            notify(err.message || t('messages.error.save_failed','Save failed'), 'error');
        } finally {
            if (el.btnSubmit) {
                el.btnSubmit.disabled = false;
                el.btnSubmit.innerHTML = `<i class="fas fa-save" aria-hidden="true"></i> ${t('form.buttons.save','Save')}`;
            }
        }
    }

    // ════════════════════════════════════════════════════════
    // EDIT
    // ════════════════════════════════════════════════════════
    async function editAuction(id) {
        try {
            const result = await apiFetch(`${API.auctions}?id=${encodeURIComponent(id)}&lang=${CFG.lang}&${platformAdmin.tenantParam()}&format=json`);
            if (result.success && result.data) {
                showForm(Array.isArray(result.data) ? result.data[0] : result.data);
            } else {
                notify(t('messages.error.load_failed','Failed to load'), 'error');
            }
        } catch (e) {
            console.error('[Auctions] editAuction:', e);
            notify(t('messages.error.load_failed','Failed to load'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════
    async function deleteAuction(id) {
        if (!confirm(t('messages.confirm_delete','Delete this auction?'))) return;
        try {
            const result = await apiFetch(API.auctions, {
                method: 'DELETE',
                body:   JSON.stringify({ id }),
            });
            if (result.success) {
                notify(t('messages.deleted','Auction deleted'), 'success');
                hideForm();
                await loadAuctions(state.page);
            } else {
                notify(result.message || t('messages.error.delete_failed','Delete failed'), 'error');
            }
        } catch (e) {
            console.error('[Auctions] deleteAuction:', e);
            notify(e.message || t('messages.error.delete_failed','Delete failed'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // BIDS
    // ════════════════════════════════════════════════════════
    function updateBidStats(auction) {
        const set = (id, val) => { const el2 = document.getElementById(id); if (el2) el2.textContent = val; };
        set('statTotalBids',     auction.total_bids    || 0);
        set('statTotalBidders',  auction.total_bidders || 0);
        set('statCurrentPrice',  auction.current_price  ? formatPrice(auction.current_price,  auction.currency_code) : '—');
        set('statWinningAmount', auction.winning_amount ? formatPrice(auction.winning_amount, auction.currency_code) : '—');
    }

    async function loadBids(auctionId) {
        const loading = document.getElementById('bidsLoading');
        const empty   = document.getElementById('bidsEmpty');
        const wrapper = document.getElementById('bidsTableWrapper');
        const tbody   = document.getElementById('bidsTableBody');
        if (!loading || !empty || !wrapper || !tbody) return;

        loading.style.display = 'flex';
        empty.style.display   = 'none';
        wrapper.style.display = 'none';

        try {
            const res = await apiFetch(`${API.bids}?auction_id=${auctionId}&limit=100&order_by=id&order_dir=DESC&format=json`);
            loading.style.display = 'none';

            if (!res.success) { empty.style.display = 'flex'; return; }
            const items = res.data?.items || (Array.isArray(res.data) ? res.data : []);
            if (!items.length) { empty.style.display = 'flex'; return; }

            wrapper.style.display = 'block';
            tbody.innerHTML = items.map(bid => {
                const isWin = bid.is_winning == 1;
                const statusBadge = isWin
                    ? `<span class="auc-winner-badge"><i class="fas fa-trophy" aria-hidden="true"></i> Winning</span>`
                    : (bid.is_auto_outbid == 1 ? '<span class="badge badge-ended">Outbid</span>' : '—');
                return `
                    <tr class="${isWin ? 'auc-winner-row' : ''}">
                        <td>${esc(bid.id)}</td>
                        <td>${esc(bid.user_id)}</td>
                        <td><strong>${formatPrice(bid.bid_amount, state.currentAuction?.currency_code)}</strong></td>
                        <td><span class="badge badge-${esc(bid.bid_type || 'manual')}">${esc(bid.bid_type || 'manual')}</span></td>
                        <td>${statusBadge}</td>
                        <td><small>${esc(bid.created_at ? new Date(bid.created_at).toLocaleString() : '—')}</small></td>
                    </tr>`;
            }).join('');
        } catch (e) {
            loading.style.display = 'none';
            empty.style.display   = 'flex';
            console.warn('[Auctions] loadBids:', e);
        }
    }

    // ════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════
    function createTranslationPanel(langCode, langName, data = {}) {
        const div = document.createElement('div');
        div.className = 'auc-trans-panel';
        div.dataset.lang = langCode;
        div.innerHTML = `
            <div class="auc-trans-header">
                <h5><i class="fas fa-language" aria-hidden="true"></i> ${esc(langName)} (${esc(langCode)})</h5>
                <button type="button" class="btn btn-sm btn-danger auc-trans-remove" aria-label="Remove">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="auc-trans-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="form-control auc-trans-title" data-lang="${esc(langCode)}" value="${esc(data.title||'')}">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control auc-trans-description" rows="4" data-lang="${esc(langCode)}">${esc(data.description||'')}</textarea>
                </div>
                <div class="form-group">
                    <label>Terms &amp; Conditions</label>
                    <textarea class="form-control auc-trans-terms" rows="3" data-lang="${esc(langCode)}">${esc(data.terms_conditions||'')}</textarea>
                </div>
            </div>`;
        div.querySelector('.auc-trans-remove')?.addEventListener('click', () => div.remove());
        return div;
    }

    function addTranslationPanel() {
        const sel  = el.auctionLangSelect;
        if (!sel?.value) return;
        const code = sel.value;
        const name = sel.options[sel.selectedIndex].textContent;
        if (document.querySelector(`#auctionTranslations [data-lang="${code}"]`)) {
            notify(t('messages.translation_exists','Translation already added'), 'warning');
            return;
        }
        el.auctionTranslations?.appendChild(createTranslationPanel(code, name, {}));
        sel.value = '';
    }

    function collectTranslations() {
        const result = {};
        document.querySelectorAll('#auctionTranslations .auc-trans-panel').forEach(panel => {
            const code  = panel.dataset.lang;
            const title = panel.querySelector('.auc-trans-title')?.value       || '';
            const desc  = panel.querySelector('.auc-trans-description')?.value  || '';
            const terms = panel.querySelector('.auc-trans-terms')?.value        || '';
            if (title || desc || terms) {
                result[code] = { title, description: desc, terms_conditions: terms };
            }
        });
        return result;
    }

    async function saveAuctionTranslations(auctionId, translations) {
        for (const [langCode, data] of Object.entries(translations)) {
            try {
                await apiFetch(API.translations, {
                    method: 'POST',
                    body: JSON.stringify({
                        auction_id:       parseInt(auctionId),
                        language_code:    langCode,
                        title:            data.title            || '',
                        description:      data.description      || null,
                        terms_conditions: data.terms_conditions || null,
                    }),
                });
            } catch (e) {
                console.warn('[Auctions] saveAuctionTranslations lang:', langCode, e);
            }
        }
    }

    async function loadAuctionTranslations(auctionId) {
        try {
            const res = await apiFetch(`${API.translations}?auction_id=${auctionId}&format=json`);
            if (!res.success) return;
            const items = Array.isArray(res.data) ? res.data : (res.data?.items || []);
            if (el.auctionTranslations) el.auctionTranslations.innerHTML = '';
            items.forEach(trans => {
                const langName = state.languages.find(l => l.code === trans.language_code)?.name || trans.language_code;
                el.auctionTranslations?.appendChild(
                    createTranslationPanel(trans.language_code, langName, {
                        title:            trans.title            || '',
                        description:      trans.description      || '',
                        terms_conditions: trans.terms_conditions || '',
                    })
                );
            });
        } catch (e) {
            console.warn('[Auctions] loadAuctionTranslations:', e);
        }
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        const search    = el.auctionSearch?.value?.trim();
        const tenant    = el.auctionTenantFilter?.value;
        const status    = el.auctionStatusFilter?.value;
        const type      = el.auctionTypeFilter?.value;
        const featured  = el.auctionFeaturedFilter?.value;
        if (search)           state.filters.search       = search;
        if (tenant)           state.filters.tenant_id    = tenant;
        if (status)           state.filters.status       = status;
        if (type)             state.filters.auction_type = type;
        if (featured !== '')  state.filters.is_featured  = featured;
        loadAuctions(1);
    }

    function resetFilters() {
        state.filters = {};
        if (el.auctionSearch)        el.auctionSearch.value        = '';
        if (el.auctionTenantFilter)  el.auctionTenantFilter.value  = platformAdmin.getTenantId() || CFG.tenantId || 0;
        if (el.auctionStatusFilter)  el.auctionStatusFilter.value  = '';
        if (el.auctionTypeFilter)    el.auctionTypeFilter.value    = '';
        if (el.auctionFeaturedFilter)el.auctionFeaturedFilter.value = '';
        loadAuctions(1);
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    async function init() {
        reloadConfig();

        const $ = id => document.getElementById(id);

        el = {
            formContainer:          $('auctionFormContainer'),
            form:                   $('auctionForm'),
            auctionFormId:          $('auctionFormId'),
            auctionTenantId:        $('auctionTenantId'),
            auctionTitle:           $('auctionTitle'),
            auctionSlug:            $('auctionSlug'),
            auctionProduct:         $('auctionProduct'),
            auctionEntity:          $('auctionEntity'),
            auctionType:            $('auctionType'),
            auctionStatus:          $('auctionStatus'),
            auctionCondition:       $('auctionCondition'),
            auctionQuantity:        $('auctionQuantity'),
            auctionIsFeatured:      $('auctionIsFeatured'),
            auctionAutoBid:         $('auctionAutoBid'),
            auctionNotes:           $('auctionNotes'),
            auctionStartingPrice:   $('auctionStartingPrice'),
            auctionReservePrice:    $('auctionReservePrice'),
            auctionBuyNowPrice:     $('auctionBuyNowPrice'),
            auctionBidIncrement:    $('auctionBidIncrement'),
            auctionCurrency:        $('auctionCurrency'),
            auctionShipping:        $('auctionShipping'),
            auctionPaymentDeadline: $('auctionPaymentDeadline'),
            auctionStartDate:       $('auctionStartDate'),
            auctionEndDate:         $('auctionEndDate'),
            auctionAutoExtend:      $('auctionAutoExtend'),
            auctionExtendMinutes:   $('auctionExtendMinutes'),
            auctionMinExtendBidTime:$('auctionMinExtendBidTime'),
            auctionTranslations:    $('auctionTranslations'),
            auctionLangSelect:      $('auctionLangSelect'),
            bidsTableBody:          $('bidsTableBody'),
            btnSubmit:              $('btnSubmitAuctionForm'),
            btnDeleteAuction:       $('btnDeleteAuction'),
            auctionSearch:          $('auctionSearch'),
            auctionTenantFilter:    $('auctionTenantFilter'),
            auctionStatusFilter:    $('auctionStatusFilter'),
            auctionTypeFilter:      $('auctionTypeFilter'),
            auctionFeaturedFilter:  $('auctionFeaturedFilter'),
        };

        // ESC closes form
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el.formContainer && el.formContainer.style.display !== 'none') hideForm();
        });

        if (el.form) el.form.addEventListener('submit', saveAuction);

        $('btnAddAuction')?.addEventListener('click', () => showForm());
        $('btnAddAuctionEmpty')?.addEventListener('click', () => showForm());
        $('btnCloseAuctionForm')?.addEventListener('click', hideForm);
        $('btnCancelAuctionForm')?.addEventListener('click', hideForm);
        $('btnApplyAuctionFilters')?.addEventListener('click', applyFilters);
        $('btnResetAuctionFilters')?.addEventListener('click', resetFilters);
        $('btnAuctionRetry')?.addEventListener('click', () => loadAuctions(state.page));
        $('btnRefreshBids')?.addEventListener('click', () => {
            if (state.currentAuction?.id) loadBids(state.currentAuction.id);
        });
        $('auctionAddLangBtn')?.addEventListener('click', addTranslationPanel);
        if (el.btnDeleteAuction) {
            el.btnDeleteAuction.addEventListener('click', () => {
                if (state.currentAuction?.id) deleteAuction(state.currentAuction.id);
            });
        }
        if (el.auctionSearch) {
            el.auctionSearch.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
            });
        }

        initTabs();
        platformAdmin.bind();
        await loadDropdownData();
        await loadAuctions(1);
        console.log('[Auctions] ✓ Initialized');
    }

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.Auctions = { init, load: loadAuctions, add: () => showForm(), edit: editAuction, remove: deleteAuction };
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('auctions', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());