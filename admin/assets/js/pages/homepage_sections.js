/**
 * Homepage Sections – Two-Tab Module
 * Tab 1: Homepage Sections (homepage_sections + homepage_section_translations)
 * Tab 2: Store Page Sections (store_pages, store_sections, store_section_translations)
 * Each tab has its own data source and save action.
 */
(function () {
    'use strict';

    var API = {
        homepageSections: '/api/v1/homepage_sections',
        storePages:       '/api/v1/store_pages',
        languages:        '/api/v1/languages',
        entities:         '/api/entities'
    };

    var HOMEPAGE_SECTION_TYPES = [
        'ads', 'search', 'slider', 'categories', 'products', 'featured_products',
        'new_products', 'deals', 'brands', 'vendors', 'entities', 'banners',
        'testimonials', 'auctions', 'jobs', 'custom_html', 'other'
    ];

    var HOMEPAGE_COMPONENTS = [
        'ad_categories', 'ad_products', 'ad_deals', 'ad_entities', 'ad_jobs',
        'ad_tenants', 'ad_slider', 'ad_banner', 'ad_search', 'ad_stats',
        'ad_custom', 'ad_native', 'ad_ads'
    ];

    var STORE_SECTION_TYPES = [
        'header', 'contact', 'tabs', 'products', 'info',
        'hours', 'location', 'offers', 'reviews', 'policies'
    ];

    var LAYOUT_TYPES = ['grid', 'slider', 'list', 'carousel', 'masonry', 'full'];

    var TYPE_COLORS = {
        ads:               '#0ea5e9',
        search:            '#a855f7',
        slider:            '#3b82f6',
        categories:        '#8b5cf6',
        featured_products: '#f59e0b',
        new_products:      '#10b981',
        deals:             '#ef4444',
        brands:            '#6366f1',
        vendors:           '#ec4899',
        entities:          '#06b6d4',
        banners:           '#14b8a6',
        testimonials:      '#f97316',
        auctions:          '#d946ef',
        jobs:              '#84cc16',
        custom_html:       '#64748b',
        other:             '#94a3b8',
        header:            '#3b82f6',
        contact:           '#10b981',
        tabs:              '#8b5cf6',
        products:          '#f59e0b',
        info:              '#6366f1',
        hours:             '#14b8a6',
        location:          '#ef4444',
        offers:            '#f97316',
        reviews:           '#ec4899',
        policies:          '#64748b'
    };

    var state = {
        language:           'ar',
        tenantId:           0,
        userId:             0,
        isSuperAdmin:       false,
        canManage:          false,
        activeTab:          'homepage',
        homepageSections:   [],
        storePages:         [],
        currentStorePage:   null,
        storeSections:      [],
        modalMode:          null,
        modalTab:           null,
        editingSection:     null,
        dragSrcIndex:       null,
        availableLanguages: [],
        allEntities:        [],
        selectedEntityId:   null
    };

    var el = {};
    var translations = {};

    // ════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════
    function init() {
        cacheElements();
        state.language     = (el.lang && el.lang.value) || 'ar';
        state.tenantId     = parseInt(el.tenantId && el.tenantId.value) || 0;
        state.userId       = parseInt(el.userId && el.userId.value) || 0;
        state.isSuperAdmin = el.isSuperAdmin && el.isSuperAdmin.value === '1';
        state.canManage    = el.canManage && el.canManage.value === '1';

        loadTranslations(state.language).then(function () {
            applyTranslations();
            initEventListeners();
            // Load languages and entities first, then sections
            Promise.all([loadLanguages(), loadEntities()]).then(function () {
                loadHomepageSections();
                // Don't auto-load store pages; wait for entity selection
            });
        });
    }

    function cacheElements() {
        el = {
            container:           document.getElementById('homepage-sections-module'),
            lang:                document.getElementById('hsLang'),
            tenantId:            document.getElementById('hsTenantId'),
            userId:              document.getElementById('hsUserId'),
            canManage:           document.getElementById('hsCanManage'),
            isSuperAdmin:        document.getElementById('hsIsSuperAdmin'),
            csrf:                document.getElementById('hsCsrfToken'),

            tabHomepage:         document.querySelector('.hs-tab[data-tab="homepage"]'),
            tabStorePages:       document.querySelector('.hs-tab[data-tab="store_pages"]'),
            homepageContent:     document.querySelector('[data-tab-content="homepage"]'),
            storePagesContent:   document.querySelector('[data-tab-content="store_pages"]'),
            homepageCountBadge:  document.getElementById('homepage-count'),
            storePagesCountBadge:document.getElementById('store-pages-count'),

            homepageSectionsBody:document.getElementById('homepage-sections-body'),
            btnAddHomepage:      document.getElementById('btn-add-homepage-section'),
            btnSaveHomepage:     document.getElementById('btn-save-homepage'),

            storePageType:       document.getElementById('store-page-type'),
            storeEntitySelect:   document.getElementById('store-entity-select'),
            storeEntityInfo:     document.getElementById('store-entity-info'),
            storeSectionsBody:   document.getElementById('store-sections-body'),
            btnAddStore:         document.getElementById('btn-add-store-section'),
            btnSaveStore:        document.getElementById('btn-save-store-sections'),

            modal:               document.getElementById('section-modal'),
            modalTitle:          document.getElementById('modal-title'),
            modalClose:          document.getElementById('modal-close'),
            modalSectionType:    document.getElementById('modal-section-type'),
            modalComponent:      document.getElementById('modal-component'),
            modalComponentGroup: document.getElementById('modal-component-group'),
            modalLayoutType:     document.getElementById('modal-layout-type'),
            modalLayoutGroup:    document.getElementById('modal-layout-group'),
            modalItemsPerRow:    document.getElementById('modal-items-per-row'),
            modalItemsGroup:     document.getElementById('modal-items-group'),
            modalBgColor:        document.getElementById('modal-bg-color'),
            modalTextColor:      document.getElementById('modal-text-color'),
            modalTextColorGroup: document.getElementById('modal-text-color-group'),
            modalIsActive:       document.getElementById('modal-is-active'),
            modalSettings:       document.getElementById('modal-settings'),
            modalSettingsGroup:  document.getElementById('modal-settings-group'),
            modalLayoutConfig:   document.getElementById('modal-layout-config'),
            modalLayoutConfigGroup: document.getElementById('modal-layout-config-group'),
            modalPadding:        document.getElementById('modal-padding'),
            modalPaddingGroup:   document.getElementById('modal-padding-group'),
            modalCustomCss:      document.getElementById('modal-custom-css'),
            modalCustomCssGroup: document.getElementById('modal-custom-css-group'),
            modalCustomHtml:     document.getElementById('modal-custom-html'),
            modalCustomHtmlGroup:document.getElementById('modal-custom-html-group'),
            modalDataSource:     document.getElementById('modal-data-source'),
            modalDataSourceGroup:document.getElementById('modal-data-source-group'),
            modalTranslations:   document.getElementById('modal-translations'),
            modalCancel:         document.getElementById('modal-cancel'),
            modalSave:           document.getElementById('modal-save'),

            toast:               document.getElementById('hs-toast')
        };
    }

    // ════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════
    function loadTranslations(lang) {
        return fetch('/languages/HomepageSections/' + encodeURIComponent(lang) + '.json')
            .then(function (res) { return res.ok ? res.json() : {}; })
            .then(function (json) { translations = (json && json.strings) || json || {}; })
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

    function applyTranslations() {
        if (!el.container) return;
        var elems = el.container.querySelectorAll('[data-i18n]');
        for (var i = 0; i < elems.length; i++) {
            var key = elems[i].getAttribute('data-i18n');
            var translated = t(key);
            if (translated && translated !== key) {
                elems[i].textContent = translated;
            }
        }
    }

    // ════════════════════════════════════════
    // LANGUAGES & ENTITIES
    // ════════════════════════════════════════
    function loadLanguages() {
        return apiCall(API.languages)
            .then(function (res) {
                var items = (res && res.data && res.data.items) || (res && res.data) || [];
                state.availableLanguages = Array.isArray(items) ? items : [];
            })
            .catch(function (e) {
                console.warn('Failed to load languages, falling back to AR/EN:', e);
                state.availableLanguages = [
                    { code: 'ar', name: 'Arabic', direction: 'rtl' },
                    { code: 'en', name: 'English', direction: 'ltr' }
                ];
            });
    }

    function loadEntities() {
        if (!state.tenantId) return Promise.resolve();
        var url = API.entities + '?limit=500&tenant_id=' + state.tenantId + '&lang=' + encodeURIComponent(state.language);
        return apiCall(url)
            .then(function (res) {
                var items = (res && res.data && res.data.items) || (res && res.data) || [];
                state.allEntities = Array.isArray(items) ? items : [];
                populateEntityDropdown();
            })
            .catch(function (e) {
                console.error('Failed to load entities:', e);
                state.allEntities = [];
                populateEntityDropdown();
            });
    }

    function populateEntityDropdown() {
        if (!el.storeEntitySelect) return;
        var html = '<option value="">' + escHtml(t('store_pages.select_entity_placeholder', '-- Select Store --')) + '</option>';
        for (var i = 0; i < state.allEntities.length; i++) {
            var entity = state.allEntities[i];
            var entityName = entity.name || entity.title || ('Entity #' + entity.id);
            html += '<option value="' + escHtml(String(entity.id)) + '">' + escHtml(entityName) + '</option>';
        }
        el.storeEntitySelect.innerHTML = html;
        // Restore selection if any
        if (state.selectedEntityId) {
            el.storeEntitySelect.value = String(state.selectedEntityId);
        }
        updateEntityInfo();
    }

    function updateEntityInfo() {
        if (!el.storeEntityInfo) return;
        if (!state.selectedEntityId) {
            el.storeEntityInfo.style.display = '';
            el.storeEntityInfo.textContent = t('store_pages.no_entity_selected', 'Please select a store first');
        } else {
            el.storeEntityInfo.style.display = 'none';
        }
    }

    function renderTranslationRows(tab) {
        if (!el.modalTranslations) return;
        var langs = state.availableLanguages;
        if (!langs || langs.length === 0) {
            langs = [
                { code: 'ar', name: 'Arabic', direction: 'rtl' },
                { code: 'en', name: 'English', direction: 'ltr' }
            ];
        }
        var isStore = (tab === 'store_pages');
        var html = '';
        for (var i = 0; i < langs.length; i++) {
            var lang = langs[i];
            var dirIndicator = (lang.direction === 'rtl') ? 'RTL' : 'LTR';
            var inputDir = lang.direction || 'ltr';
            html += '<div class="hs-translation-row" data-lang-code="' + escHtml(lang.code) + '">';
            html += '<span class="lang-label">' + escHtml(lang.code.toUpperCase());
            html += ' <span class="lang-dir-indicator">(' + escHtml(dirIndicator) + ')</span>';
            html += '</span>';
            html += '<div class="hs-translation-fields">';
            html += '<input type="text" class="modal-trans-title hs-input" data-lang="' + escHtml(lang.code) + '" dir="' + escHtml(inputDir) + '"';
            html += ' placeholder="' + escHtml(t('modal.title_placeholder', 'Title')) + ' (' + escHtml(lang.name || lang.code) + ')">';
            if (isStore) {
                html += '<textarea class="modal-trans-content hs-translation-content" data-lang="' + escHtml(lang.code) + '" dir="' + escHtml(inputDir) + '"';
                html += ' placeholder="' + escHtml(t('modal.content_placeholder', 'Content')) + ' (' + escHtml(lang.name || lang.code) + ')" rows="3"></textarea>';
            } else {
                html += '<input type="text" class="modal-trans-subtitle hs-input" data-lang="' + escHtml(lang.code) + '" dir="' + escHtml(inputDir) + '"';
                html += ' placeholder="' + escHtml(t('modal.subtitle_placeholder', 'Subtitle')) + ' (' + escHtml(lang.name || lang.code) + ')">';
            }
            html += '</div>';
            html += '</div>';
        }
        el.modalTranslations.innerHTML = html;
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
        return fetch(url, options).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) {
                    var errMsg = (json && json.message) || (json && json.error) || ('HTTP ' + res.status);
                    throw new Error(errMsg);
                }
                return json;
            });
        });
    }

    function showToast(message, type) {
        type = type || 'success';
        if (el.toast) {
            el.toast.textContent = message;
            el.toast.className = 'hs-toast hs-toast-' + type;
            el.toast.style.display = '';
            clearTimeout(el.toast._timer);
            el.toast._timer = setTimeout(function () { el.toast.style.display = 'none'; }, 3500);
            return;
        }
        var toast = document.createElement('div');
        toast.className = 'hs-toast hs-toast-' + type;
        toast.textContent = message;
        if (el.container) el.container.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3500);
    }

    // ════════════════════════════════════════
    // TAB MANAGEMENT
    // ════════════════════════════════════════
    function switchTab(tabName) {
        state.activeTab = tabName;
        var tabs = el.container ? el.container.querySelectorAll('.hs-tab') : [];
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === tabName);
        }
        var contents = el.container ? el.container.querySelectorAll('.hs-tab-content') : [];
        for (var j = 0; j < contents.length; j++) {
            contents[j].classList.toggle('active', contents[j].getAttribute('data-tab-content') === tabName);
        }
    }

    // ════════════════════════════════════════
    // EVENT LISTENERS
    // ════════════════════════════════════════
    function initEventListeners() {
        if (el.tabHomepage)   el.tabHomepage.addEventListener('click', function () { switchTab('homepage'); });
        if (el.tabStorePages) el.tabStorePages.addEventListener('click', function () { switchTab('store_pages'); });

        if (el.btnAddHomepage) el.btnAddHomepage.addEventListener('click', function () { openModal('add', 'homepage'); });
        if (el.btnAddStore)    el.btnAddStore.addEventListener('click', function () { openModal('add', 'store_pages'); });

        if (el.btnSaveHomepage) el.btnSaveHomepage.addEventListener('click', function () { saveHomepageSectionsOrder(); });
        if (el.btnSaveStore)    el.btnSaveStore.addEventListener('click', function () { saveStoreSectionsOrder(); });

        if (el.modalClose)  el.modalClose.addEventListener('click', closeModal);
        if (el.modalCancel) el.modalCancel.addEventListener('click', closeModal);
        if (el.modalSave)   el.modalSave.addEventListener('click', handleModalSave);

        if (el.modal) {
            el.modal.addEventListener('click', function (e) {
                if (e.target === el.modal) closeModal();
            });
        }

        if (el.storePageType) {
            el.storePageType.addEventListener('change', function () {
                var pageType = this.value;
                if (pageType && state.selectedEntityId) {
                    ensureStorePage(pageType).then(function () {
                        if (state.currentStorePage) {
                            loadStoreSections(state.currentStorePage.id);
                        }
                    });
                }
            });
        }

        if (el.storeEntitySelect) {
            el.storeEntitySelect.addEventListener('change', function () {
                var entityId = this.value ? parseInt(this.value) : null;
                state.selectedEntityId = entityId;
                state.currentStorePage = null;
                state.storePages = [];
                state.storeSections = [];
                updateEntityInfo();
                updateBadgeCounts();
                renderStoreSections();
                if (entityId) {
                    loadStorePages();
                }
            });
        }
    }

    // ════════════════════════════════════════
    // TAB 1: HOMEPAGE SECTIONS
    // ════════════════════════════════════════
    function loadHomepageSections() {
        if (!state.tenantId) return Promise.resolve();
        var url = API.homepageSections + '?tenant_id=' + state.tenantId + '&lang=' + encodeURIComponent(state.language);
        return apiCall(url)
            .then(function (res) {
                var items = (res && res.data && res.data.items) || (res && res.data) || [];
                state.homepageSections = Array.isArray(items) ? items : [];
                state.homepageSections.sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); });
                updateBadgeCounts();
                renderHomepageSections();
            })
            .catch(function (e) {
                console.error('Failed to load homepage sections:', e);
                state.homepageSections = [];
                updateBadgeCounts();
                renderHomepageSections();
            });
    }

    function renderHomepageSections() {
        if (!el.homepageSectionsBody) return;
        if (state.homepageSections.length === 0) {
            el.homepageSectionsBody.innerHTML = '<tr><td colspan="13" class="hs-table-empty">' + t('homepage.no_sections', 'No sections yet') + '</td></tr>';
            return;
        }
        var html = '';
        for (var i = 0; i < state.homepageSections.length; i++) {
            html += renderHomepageSectionRow(state.homepageSections[i], i);
        }
        el.homepageSectionsBody.innerHTML = html;
        attachHomepageCardListeners();
    }

    function renderHomepageSectionRow(section, index) {
        var sectionId = parseInt(section.id);
        var sType = section.section_type || section.type || 'other';
        var title = section.title || section.translated_title || '';
        if (!title && section.translations) {
            var trLang = section.translations[state.language] || section.translations['ar'] || section.translations['en'];
            if (trLang) title = trLang.title || '';
        }
        var subtitle = section.subtitle || '';
        if (!subtitle && section.translations) {
            var trL2 = section.translations[state.language] || section.translations['ar'] || section.translations['en'];
            if (trL2) subtitle = trL2.subtitle || '';
        }
        var isActive = section.is_active == 1 || section.is_active === true;
        var sortOrder = section.sort_order || (index + 1);
        var color = TYPE_COLORS[sType] || '#94a3b8';
        var component = section.component || '';
        var layout = section.layout_type || '';
        var bgColor = section.background_color || '';
        var textColor = section.text_color || '';
        var dataSource = section.data_source || '';
        var itemsPerRow = section.items_per_row || '';

        var html = '<tr data-id="' + sectionId + '" data-index="' + index + '">';
        html += '<td>' + escHtml(String(sectionId)) + '</td>';
        html += '<td><span class="hs-type-badge" style="background:' + color + '">' + escHtml(sType) + '</span></td>';
        html += '<td>' + escHtml(component) + '</td>';
        html += '<td>' + escHtml(title) + '</td>';
        html += '<td>' + escHtml(subtitle) + '</td>';
        html += '<td>' + escHtml(layout) + '</td>';
        html += '<td>' + escHtml(String(itemsPerRow)) + '</td>';
        // Background color with swatch
        html += '<td>';
        if (bgColor) {
            var safeBg = /^(#[a-fA-F0-9]{3,8}|var\(--[\w-]+\))$/.test(bgColor) ? bgColor : '';
            if (safeBg) html += '<span class="hs-color-swatch" style="background:' + safeBg + '"></span>';
            html += escHtml(bgColor);
        }
        html += '</td>';
        // Text color with swatch
        html += '<td>';
        if (textColor) {
            var safeTxt = /^(#[a-fA-F0-9]{3,8}|var\(--[\w-]+\))$/.test(textColor) ? textColor : '';
            if (safeTxt) html += '<span class="hs-color-swatch" style="background:' + safeTxt + '"></span>';
            html += escHtml(textColor);
        }
        html += '</td>';
        html += '<td>' + escHtml(dataSource) + '</td>';
        // Active toggle
        html += '<td>';
        html += '<label class="hs-toggle">';
        html += '<input type="checkbox"' + (isActive ? ' checked' : '') + ' data-action="toggle-active" data-tab="homepage" data-id="' + sectionId + '" data-index="' + index + '">';
        html += '<span class="hs-toggle-slider"></span>';
        html += '</label>';
        html += '</td>';
        html += '<td>' + escHtml(String(sortOrder)) + '</td>';
        // Actions
        html += '<td class="hs-table-actions"><div class="hs-table-actions-inner">';
        if (state.canManage) {
            html += '<button class="hs-btn hs-btn-sm hs-btn-move" data-action="move-up" data-tab="homepage" data-index="' + index + '" title="&#9650;"><i class="fas fa-arrow-up"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-move" data-action="move-down" data-tab="homepage" data-index="' + index + '" title="&#9660;"><i class="fas fa-arrow-down"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-edit" data-action="edit" data-tab="homepage" data-id="' + sectionId + '" data-index="' + index + '" title="' + t('common.edit', 'Edit') + '"><i class="fas fa-edit"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-delete" data-action="delete" data-tab="homepage" data-id="' + sectionId + '" data-index="' + index + '" title="' + t('common.delete', 'Delete') + '"><i class="fas fa-trash"></i></button>';
        }
        html += '</div></td>';
        html += '</tr>';
        return html;
    }

    function attachHomepageCardListeners() {
        if (!el.homepageSectionsBody) return;
        var toggles = el.homepageSectionsBody.querySelectorAll('[data-action="toggle-active"][data-tab="homepage"]');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('change', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (state.homepageSections[idx]) {
                    state.homepageSections[idx].is_active = this.checked ? 1 : 0;
                }
            });
        }
        var moveUpBtns = el.homepageSectionsBody.querySelectorAll('[data-action="move-up"][data-tab="homepage"]');
        for (var u = 0; u < moveUpBtns.length; u++) {
            moveUpBtns[u].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (idx > 0) {
                    arrayMove(state.homepageSections, idx, idx - 1);
                    for (var s = 0; s < state.homepageSections.length; s++) {
                        state.homepageSections[s].sort_order = s + 1;
                    }
                    renderHomepageSections();
                }
            });
        }
        var moveDownBtns = el.homepageSectionsBody.querySelectorAll('[data-action="move-down"][data-tab="homepage"]');
        for (var dn = 0; dn < moveDownBtns.length; dn++) {
            moveDownBtns[dn].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (idx < state.homepageSections.length - 1) {
                    arrayMove(state.homepageSections, idx, idx + 1);
                    for (var s = 0; s < state.homepageSections.length; s++) {
                        state.homepageSections[s].sort_order = s + 1;
                    }
                    renderHomepageSections();
                }
            });
        }
        var editBtns = el.homepageSectionsBody.querySelectorAll('[data-action="edit"][data-tab="homepage"]');
        for (var e = 0; e < editBtns.length; e++) {
            editBtns[e].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                var sectionId = parseInt(this.getAttribute('data-id'));
                editHomepageSection(sectionId, idx);
            });
        }
        var delBtns = el.homepageSectionsBody.querySelectorAll('[data-action="delete"][data-tab="homepage"]');
        for (var d = 0; d < delBtns.length; d++) {
            delBtns[d].addEventListener('click', function () {
                var sectionId = parseInt(this.getAttribute('data-id'));
                deleteHomepageSection(sectionId);
            });
        }
    }

    function editHomepageSection(sectionId, index) {
        var url = API.homepageSections + '?tenant_id=' + state.tenantId + '&id=' + sectionId + '&all_translations=1';
        apiCall(url)
            .then(function (res) {
                var section = (res && res.data) || state.homepageSections[index] || {};
                openModal('edit', 'homepage', section);
            })
            .catch(function () {
                var section = state.homepageSections[index] || {};
                openModal('edit', 'homepage', section);
            });
    }

    // ════════════════════════════════════════
    // TAB 2: STORE PAGE SECTIONS
    // ════════════════════════════════════════
    function loadStorePages() {
        if (!state.tenantId) return Promise.resolve();
        var url = API.storePages + '?tenant_id=' + state.tenantId;
        if (state.selectedEntityId) {
            url += '&entity_id=' + state.selectedEntityId;
        }
        return apiCall(url)
            .then(function (res) {
                var items = (res && res.data && res.data.items) || (res && res.data) || [];
                state.storePages = Array.isArray(items) ? items : [];
                var pageType = (el.storePageType && el.storePageType.value) || 'store';
                return ensureStorePage(pageType);
            })
            .then(function () {
                if (state.currentStorePage) {
                    return loadStoreSections(state.currentStorePage.id);
                }
            })
            .catch(function (e) {
                console.error('Failed to load store pages:', e);
                state.storePages = [];
            });
    }

    function ensureStorePage(pageType) {
        pageType = pageType || 'store';
        for (var i = 0; i < state.storePages.length; i++) {
            if (state.storePages[i].type === pageType &&
                (!state.selectedEntityId || String(state.storePages[i].entity_id) === String(state.selectedEntityId))) {
                state.currentStorePage = state.storePages[i];
                return Promise.resolve(state.currentStorePage);
            }
        }
        var url = API.storePages + '?tenant_id=' + state.tenantId + '&type=' + encodeURIComponent(pageType);
        if (state.selectedEntityId) {
            url += '&entity_id=' + state.selectedEntityId;
        }
        return apiCall(url)
            .then(function (res) {
                var page = (res && res.data) || null;
                if (page && page.id) {
                    state.currentStorePage = page;
                    if (state.storePages.indexOf(page) === -1) state.storePages.push(page);
                    return page;
                }
                return createStorePage(pageType);
            })
            .catch(function () {
                return createStorePage(pageType);
            });
    }

    function createStorePage(pageType) {
        var body = {
            tenant_id: state.tenantId,
            type: pageType,
            name: pageType.charAt(0).toUpperCase() + pageType.slice(1) + ' Page',
            is_active: 1
        };
        if (state.selectedEntityId) {
            body.entity_id = state.selectedEntityId;
        }
        return apiCall(API.storePages + '?target=page', {
            method: 'POST',
            body: body
        }).then(function (res) {
            var page = (res && res.data) || null;
            if (page && page.id) {
                state.currentStorePage = page;
                state.storePages.push(page);
            }
            return page;
        }).catch(function (e) {
            console.error('Failed to create store page:', e);
            return null;
        });
    }

    function loadStoreSections(pageId) {
        if (!pageId) return Promise.resolve();
        var url = API.storePages + '?page_id=' + pageId + '&sections=1&lang=' + encodeURIComponent(state.language);
        return apiCall(url)
            .then(function (res) {
                var items = (res && res.data && res.data.items) || (res && res.data) || [];
                state.storeSections = Array.isArray(items) ? items : [];
                state.storeSections.sort(function (a, b) { return (a.position || 0) - (b.position || 0); });
                updateBadgeCounts();
                renderStoreSections();
            })
            .catch(function (e) {
                console.error('Failed to load store sections:', e);
                state.storeSections = [];
                updateBadgeCounts();
                renderStoreSections();
            });
    }

    function renderStoreSections() {
        if (!el.storeSectionsBody) return;
        if (state.storeSections.length === 0) {
            el.storeSectionsBody.innerHTML = '<tr><td colspan="7" class="hs-table-empty">' + t('store_pages.no_sections', 'No sections yet') + '</td></tr>';
            return;
        }
        var html = '';
        for (var i = 0; i < state.storeSections.length; i++) {
            html += renderStoreSectionRow(state.storeSections[i], i);
        }
        el.storeSectionsBody.innerHTML = html;
        attachStoreCardListeners();
    }

    function renderStoreSectionRow(section, index) {
        var sectionId = parseInt(section.id);
        var sType = section.type || 'info';
        var title = section.title || section.translated_title || '';
        if (!title && section.translations) {
            var trLang = section.translations[state.language] || section.translations['ar'] || section.translations['en'];
            if (trLang) title = trLang.title || '';
        }
        if (!title) title = t('section_types.' + sType, sType);
        var isActive = section.is_active == 1 || section.is_active === true;
        var position = section.position || (index + 1);
        var color = TYPE_COLORS[sType] || '#94a3b8';
        var settings = section.settings || {};
        var settingsStr = '';
        if (typeof settings === 'object' && Object.keys(settings).length > 0) {
            settingsStr = JSON.stringify(settings);
            if (settingsStr.length > 50) settingsStr = settingsStr.substring(0, 50) + '...';
        }

        var html = '<tr data-id="' + sectionId + '" data-index="' + index + '">';
        html += '<td>' + escHtml(String(sectionId)) + '</td>';
        html += '<td><span class="hs-type-badge" style="background:' + color + '">' + escHtml(sType) + '</span></td>';
        html += '<td>' + escHtml(title) + '</td>';
        html += '<td>' + escHtml(String(position)) + '</td>';
        html += '<td class="hs-settings-cell">' + escHtml(settingsStr) + '</td>';
        // Active toggle
        html += '<td>';
        html += '<label class="hs-toggle">';
        html += '<input type="checkbox"' + (isActive ? ' checked' : '') + ' data-action="toggle-active" data-tab="store_pages" data-id="' + sectionId + '" data-index="' + index + '">';
        html += '<span class="hs-toggle-slider"></span>';
        html += '</label>';
        html += '</td>';
        // Actions
        html += '<td class="hs-table-actions"><div class="hs-table-actions-inner">';
        if (state.canManage) {
            html += '<button class="hs-btn hs-btn-sm hs-btn-move" data-action="move-up" data-tab="store_pages" data-index="' + index + '" title="&#9650;"><i class="fas fa-arrow-up"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-move" data-action="move-down" data-tab="store_pages" data-index="' + index + '" title="&#9660;"><i class="fas fa-arrow-down"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-edit" data-action="edit" data-tab="store_pages" data-id="' + sectionId + '" data-index="' + index + '" title="' + t('common.edit', 'Edit') + '"><i class="fas fa-edit"></i></button>';
            html += '<button class="hs-btn hs-btn-sm hs-btn-delete" data-action="delete" data-tab="store_pages" data-id="' + sectionId + '" data-index="' + index + '" title="' + t('common.delete', 'Delete') + '"><i class="fas fa-trash"></i></button>';
        }
        html += '</div></td>';
        html += '</tr>';
        return html;
    }

    function attachStoreCardListeners() {
        if (!el.storeSectionsBody) return;
        var toggles = el.storeSectionsBody.querySelectorAll('[data-action="toggle-active"][data-tab="store_pages"]');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('change', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (state.storeSections[idx]) {
                    state.storeSections[idx].is_active = this.checked ? 1 : 0;
                }
            });
        }
        var moveUpBtns = el.storeSectionsBody.querySelectorAll('[data-action="move-up"][data-tab="store_pages"]');
        for (var u = 0; u < moveUpBtns.length; u++) {
            moveUpBtns[u].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (idx > 0) {
                    arrayMove(state.storeSections, idx, idx - 1);
                    for (var s = 0; s < state.storeSections.length; s++) {
                        state.storeSections[s].position = s + 1;
                    }
                    renderStoreSections();
                }
            });
        }
        var moveDownBtns = el.storeSectionsBody.querySelectorAll('[data-action="move-down"][data-tab="store_pages"]');
        for (var dn = 0; dn < moveDownBtns.length; dn++) {
            moveDownBtns[dn].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                if (idx < state.storeSections.length - 1) {
                    arrayMove(state.storeSections, idx, idx + 1);
                    for (var s = 0; s < state.storeSections.length; s++) {
                        state.storeSections[s].position = s + 1;
                    }
                    renderStoreSections();
                }
            });
        }
        var editBtns = el.storeSectionsBody.querySelectorAll('[data-action="edit"][data-tab="store_pages"]');
        for (var e = 0; e < editBtns.length; e++) {
            editBtns[e].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'));
                var sectionId = parseInt(this.getAttribute('data-id'));
                editStoreSection(sectionId, idx);
            });
        }
        var delBtns = el.storeSectionsBody.querySelectorAll('[data-action="delete"][data-tab="store_pages"]');
        for (var d = 0; d < delBtns.length; d++) {
            delBtns[d].addEventListener('click', function () {
                var sectionId = parseInt(this.getAttribute('data-id'));
                deleteStoreSection(sectionId);
            });
        }
    }

    function editStoreSection(sectionId, index) {
        var url = API.storePages + '?section_id=' + sectionId + '&translations=1';
        apiCall(url)
            .then(function (res) {
                var section = (res && res.data) || state.storeSections[index] || {};
                openModal('edit', 'store_pages', section);
            })
            .catch(function () {
                var section = state.storeSections[index] || {};
                openModal('edit', 'store_pages', section);
            });
    }

    // ════════════════════════════════════════
    // MODAL
    // ════════════════════════════════════════
    function openModal(mode, tab, section) {
        state.modalMode = mode;
        state.modalTab = tab;
        state.editingSection = section || null;

        if (el.modalTitle) {
            if (mode === 'edit') {
                el.modalTitle.textContent = t('modal.edit_section', 'Edit Section');
            } else {
                el.modalTitle.textContent = t('modal.add_section', 'Add Section');
            }
        }

        populateModalTypeOptions(tab);
        configureModalFields(tab);
        renderTranslationRows(tab);

        if (mode === 'edit' && section) {
            populateModalFromSection(section, tab);
        } else {
            resetModalFields();
        }

        if (el.modal) el.modal.style.display = '';
    }

    function closeModal() {
        if (el.modal) el.modal.style.display = 'none';
        state.modalMode = null;
        state.modalTab = null;
        state.editingSection = null;
    }

    function populateModalTypeOptions(tab) {
        if (!el.modalSectionType) return;
        var types = (tab === 'homepage') ? HOMEPAGE_SECTION_TYPES : STORE_SECTION_TYPES;
        var html = '';
        for (var i = 0; i < types.length; i++) {
            html += '<option value="' + types[i] + '">' + t('section_types.' + types[i], types[i]) + '</option>';
        }
        el.modalSectionType.innerHTML = html;

        if (tab === 'homepage' && el.modalComponent) {
            html = '<option value="">' + t('modal.select_component', '-- Select Component --') + '</option>';
            for (var c = 0; c < HOMEPAGE_COMPONENTS.length; c++) {
                html += '<option value="' + HOMEPAGE_COMPONENTS[c] + '">' + t('components.' + HOMEPAGE_COMPONENTS[c], HOMEPAGE_COMPONENTS[c]) + '</option>';
            }
            el.modalComponent.innerHTML = html;
        }
    }

    function configureModalFields(tab) {
        var isHomepage = (tab === 'homepage');
        if (el.modalComponentGroup)    el.modalComponentGroup.style.display    = isHomepage ? '' : 'none';
        if (el.modalLayoutGroup)       el.modalLayoutGroup.style.display       = isHomepage ? '' : 'none';
        if (el.modalItemsGroup)        el.modalItemsGroup.style.display        = isHomepage ? '' : 'none';
        if (el.modalTextColorGroup)    el.modalTextColorGroup.style.display    = isHomepage ? '' : 'none';
        if (el.modalLayoutConfigGroup) el.modalLayoutConfigGroup.style.display = isHomepage ? '' : 'none';
        if (el.modalPaddingGroup)      el.modalPaddingGroup.style.display      = isHomepage ? '' : 'none';
        if (el.modalCustomCssGroup)    el.modalCustomCssGroup.style.display    = isHomepage ? '' : 'none';
        if (el.modalCustomHtmlGroup)   el.modalCustomHtmlGroup.style.display   = isHomepage ? '' : 'none';
        if (el.modalDataSourceGroup)   el.modalDataSourceGroup.style.display   = isHomepage ? '' : 'none';
        if (el.modalSettingsGroup)     el.modalSettingsGroup.style.display     = isHomepage ? 'none' : '';
    }

    function resetModalFields() {
        if (el.modalSectionType)  el.modalSectionType.selectedIndex = 0;
        if (el.modalComponent)    el.modalComponent.selectedIndex = 0;
        if (el.modalLayoutType)   el.modalLayoutType.value = 'grid';
        if (el.modalItemsPerRow)  el.modalItemsPerRow.value = '4';
        if (el.modalBgColor)      el.modalBgColor.value = '#ffffff';
        if (el.modalTextColor)    el.modalTextColor.value = '#000000';
        if (el.modalIsActive)     el.modalIsActive.checked = true;
        if (el.modalSettings)     el.modalSettings.value = '';
        if (el.modalLayoutConfig) el.modalLayoutConfig.value = '';
        if (el.modalPadding)      el.modalPadding.value = '';
        if (el.modalCustomCss)    el.modalCustomCss.value = '';
        if (el.modalCustomHtml)   el.modalCustomHtml.value = '';
        if (el.modalDataSource)   el.modalDataSource.value = '';
        clearTranslationFields();
    }

    function populateModalFromSection(section, tab) {
        if (tab === 'homepage') {
            if (el.modalSectionType)  el.modalSectionType.value = section.section_type || section.type || '';
            if (el.modalComponent)    el.modalComponent.value   = section.component || '';
            if (el.modalLayoutType)   el.modalLayoutType.value  = section.layout_type || 'grid';
            if (el.modalItemsPerRow)  el.modalItemsPerRow.value = section.items_per_row || '4';
            if (el.modalBgColor)      el.modalBgColor.value     = section.background_color || '#ffffff';
            if (el.modalTextColor)    el.modalTextColor.value   = section.text_color || '#000000';
            if (el.modalLayoutConfig) {
                var lcVal = section.layout_config;
                if (lcVal && typeof lcVal !== 'string') {
                    lcVal = JSON.stringify(lcVal, null, 2);
                }
                el.modalLayoutConfig.value = lcVal || '';
            }
            if (el.modalPadding)      el.modalPadding.value      = section.padding || '';
            if (el.modalCustomCss)    el.modalCustomCss.value    = section.custom_css || '';
            if (el.modalCustomHtml)   el.modalCustomHtml.value   = section.custom_html || '';
            if (el.modalDataSource)   el.modalDataSource.value   = section.data_source || '';
        } else {
            if (el.modalSectionType)  el.modalSectionType.value = section.type || '';
            if (el.modalSettings)     el.modalSettings.value    = section.settings ? (typeof section.settings === 'string' ? section.settings : JSON.stringify(section.settings, null, 2)) : '';
            if (el.modalBgColor)      el.modalBgColor.value     = (section.settings && section.settings.background_color) || '#ffffff';
        }
        if (el.modalIsActive) el.modalIsActive.checked = (section.is_active == 1 || section.is_active === true);

        clearTranslationFields();
        if (section.translations && typeof section.translations === 'object') {
            var titleInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-title') : [];
            var subtitleInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-subtitle') : [];
            var contentInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-content') : [];
            for (var ti = 0; ti < titleInputs.length; ti++) {
                var lang = titleInputs[ti].getAttribute('data-lang');
                if (lang && section.translations[lang]) {
                    titleInputs[ti].value = section.translations[lang].title || '';
                }
            }
            for (var si = 0; si < subtitleInputs.length; si++) {
                var sLang = subtitleInputs[si].getAttribute('data-lang');
                if (sLang && section.translations[sLang]) {
                    subtitleInputs[si].value = section.translations[sLang].subtitle || '';
                }
            }
            for (var ci = 0; ci < contentInputs.length; ci++) {
                var cLang = contentInputs[ci].getAttribute('data-lang');
                if (cLang && section.translations[cLang]) {
                    contentInputs[ci].value = section.translations[cLang].content || '';
                }
            }
        }
    }

    function clearTranslationFields() {
        if (!el.modalTranslations) return;
        var inputs = el.modalTranslations.querySelectorAll('input[type="text"]');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].value = '';
        }
        var textareas = el.modalTranslations.querySelectorAll('textarea');
        for (var j = 0; j < textareas.length; j++) {
            textareas[j].value = '';
        }
    }

    function collectModalData(tab) {
        var data = {};
        var titleInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-title') : [];
        var subtitleInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-subtitle') : [];
        var contentInputs = el.modalTranslations ? el.modalTranslations.querySelectorAll('.modal-trans-content') : [];
        var trs = {};
        for (var ti = 0; ti < titleInputs.length; ti++) {
            var lang = titleInputs[ti].getAttribute('data-lang');
            if (lang) {
                if (!trs[lang]) trs[lang] = {};
                trs[lang].title = titleInputs[ti].value.trim();
            }
        }
        for (var si = 0; si < subtitleInputs.length; si++) {
            var sLang = subtitleInputs[si].getAttribute('data-lang');
            if (sLang) {
                if (!trs[sLang]) trs[sLang] = {};
                trs[sLang].subtitle = subtitleInputs[si].value.trim();
            }
        }
        for (var ci = 0; ci < contentInputs.length; ci++) {
            var cLang = contentInputs[ci].getAttribute('data-lang');
            if (cLang) {
                if (!trs[cLang]) trs[cLang] = {};
                trs[cLang].content = contentInputs[ci].value.trim();
            }
        }

        data.is_active = el.modalIsActive && el.modalIsActive.checked ? 1 : 0;
        data.translations = trs;

        if (tab === 'homepage') {
            data.section_type      = el.modalSectionType ? el.modalSectionType.value : '';
            data.component         = el.modalComponent ? el.modalComponent.value : '';
            data.layout_type       = el.modalLayoutType ? el.modalLayoutType.value : 'grid';
            data.items_per_row     = el.modalItemsPerRow ? parseInt(el.modalItemsPerRow.value) || 4 : 4;
            data.background_color  = el.modalBgColor ? el.modalBgColor.value : '#ffffff';
            data.text_color        = el.modalTextColor ? el.modalTextColor.value : '#000000';
            data.tenant_id         = state.tenantId;
            // New homepage fields
            var layoutConfigStr = el.modalLayoutConfig ? el.modalLayoutConfig.value.trim() : '';
            if (layoutConfigStr) {
                try { data.layout_config = JSON.parse(layoutConfigStr); } catch (e) { showToast(t('messages.invalid_json', 'Invalid JSON in Layout Config'), 'error'); return null; }
            }
            data.padding     = el.modalPadding ? el.modalPadding.value.trim() : '';
            data.custom_css  = el.modalCustomCss ? el.modalCustomCss.value.trim() : '';
            data.custom_html = el.modalCustomHtml ? el.modalCustomHtml.value.trim() : '';
            data.data_source = el.modalDataSource ? el.modalDataSource.value.trim() : '';
        } else {
            data.type = el.modalSectionType ? el.modalSectionType.value : '';
            var settingsStr = el.modalSettings ? el.modalSettings.value.trim() : '';
            if (settingsStr) {
                try {
                    data.settings = JSON.parse(settingsStr);
                } catch (e) {
                    data.settings = {};
                }
            } else {
                data.settings = {};
            }
            if (el.modalBgColor) {
                data.settings.background_color = el.modalBgColor.value;
            }
            if (state.currentStorePage) {
                data.page_id = state.currentStorePage.id;
            }
            data.tenant_id = state.tenantId;
        }

        if (state.editingSection && state.editingSection.id) {
            data.id = state.editingSection.id;
        }

        return data;
    }

    function handleModalSave() {
        var tab = state.modalTab;
        var data = collectModalData(tab);
        if (!data) return; // Validation failed

        if (tab === 'homepage') {
            saveHomepageSection(data);
        } else {
            saveStoreSection(data);
        }
    }

    // ════════════════════════════════════════
    // HOMEPAGE SECTION CRUD
    // ════════════════════════════════════════
    function saveHomepageSection(data) {
        var isEdit = !!(data.id);
        var method = isEdit ? 'PUT' : 'POST';

        if (!isEdit) {
            data.sort_order = state.homepageSections.length + 1;
        }

        apiCall(API.homepageSections, { method: method, body: data })
            .then(function () {
                showToast(t(isEdit ? 'messages.section_updated' : 'messages.section_created', isEdit ? 'Section updated' : 'Section created'), 'success');
                closeModal();
                loadHomepageSections();
            })
            .catch(function (e) {
                console.error('Save homepage section failed:', e);
                showToast(e.message || t('messages.save_failed', 'Save failed'), 'error');
            });
    }

    function deleteHomepageSection(sectionId) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this section?'))) return;
        var url = API.homepageSections + '?tenant_id=' + state.tenantId + '&id=' + sectionId;
        apiCall(url, { method: 'DELETE' })
            .then(function () {
                showToast(t('messages.section_deleted', 'Section deleted'), 'success');
                loadHomepageSections();
            })
            .catch(function (e) {
                console.error('Delete homepage section failed:', e);
                showToast(e.message || t('messages.delete_failed', 'Delete failed'), 'error');
            });
    }

    function saveHomepageSectionsOrder() {
        if (!state.tenantId) return;
        var promises = [];
        for (var i = 0; i < state.homepageSections.length; i++) {
            var section = state.homepageSections[i];
            var payload = {
                id: section.id,
                sort_order: i + 1,
                is_active: section.is_active == 1 ? 1 : 0,
                tenant_id: state.tenantId
            };
            promises.push(apiCall(API.homepageSections, { method: 'PUT', body: payload }));
        }
        Promise.all(promises)
            .then(function () {
                showToast(t('messages.order_saved', 'Order saved'), 'success');
                loadHomepageSections();
            })
            .catch(function (e) {
                console.error('Save homepage order failed:', e);
                showToast(e.message || t('messages.save_failed', 'Save failed'), 'error');
            });
    }

    // ════════════════════════════════════════
    // STORE SECTION CRUD
    // ════════════════════════════════════════
    function saveStoreSection(data) {
        var isEdit = !!(data.id);

        if (!isEdit && state.currentStorePage) {
            data.page_id = state.currentStorePage.id;
            data.position = state.storeSections.length + 1;
        }

        var trs = data.translations || {};
        delete data.translations;

        var sectionPromise;
        if (isEdit) {
            sectionPromise = apiCall(API.storePages + '?target=section', { method: 'PUT', body: data });
        } else {
            data.translations = trs;
            sectionPromise = apiCall(API.storePages + '?target=section', { method: 'POST', body: data });
        }

        sectionPromise
            .then(function (res) {
                var savedSection = (res && res.data) || {};
                var savedId = savedSection.id || data.id;
                if (isEdit && savedId && Object.keys(trs).length > 0) {
                    return apiCall(API.storePages + '?target=translations', {
                        method: 'POST',
                        body: { section_id: savedId, translations: trs }
                    });
                }
            })
            .then(function () {
                showToast(t(isEdit ? 'messages.section_updated' : 'messages.section_created', isEdit ? 'Section updated' : 'Section created'), 'success');
                closeModal();
                if (state.currentStorePage) {
                    loadStoreSections(state.currentStorePage.id);
                }
            })
            .catch(function (e) {
                console.error('Save store section failed:', e);
                showToast(e.message || t('messages.save_failed', 'Save failed'), 'error');
            });
    }

    function deleteStoreSection(sectionId) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this section?'))) return;
        if (!state.currentStorePage) return;
        var url = API.storePages + '?target=section&page_id=' + state.currentStorePage.id + '&section_id=' + sectionId;
        apiCall(url, { method: 'DELETE' })
            .then(function () {
                showToast(t('messages.section_deleted', 'Section deleted'), 'success');
                loadStoreSections(state.currentStorePage.id);
            })
            .catch(function (e) {
                console.error('Delete store section failed:', e);
                showToast(e.message || t('messages.delete_failed', 'Delete failed'), 'error');
            });
    }

    function saveStoreSectionsOrder() {
        if (!state.currentStorePage) return;
        var positions = [];
        for (var i = 0; i < state.storeSections.length; i++) {
            positions.push({
                id: state.storeSections[i].id,
                position: i + 1
            });
        }
        apiCall(API.storePages + '?target=reorder', {
            method: 'POST',
            body: {
                page_id: state.currentStorePage.id,
                positions: positions
            }
        })
        .then(function () {
            showToast(t('messages.order_saved', 'Order saved'), 'success');
            loadStoreSections(state.currentStorePage.id);
        })
        .catch(function (e) {
            console.error('Save store order failed:', e);
            showToast(e.message || t('messages.save_failed', 'Save failed'), 'error');
        });
    }

    // ════════════════════════════════════════
    // DRAG & DROP REORDER
    // ════════════════════════════════════════
    function initSortable(listElement, callback) {
        if (!listElement) return;
        var cards = listElement.querySelectorAll('.hs-section-card');
        for (var i = 0; i < cards.length; i++) {
            (function (card) {
                card.addEventListener('dragstart', function (e) {
                    state.dragSrcIndex = parseInt(card.getAttribute('data-index'));
                    card.classList.add('hs-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', String(state.dragSrcIndex));
                });

                card.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    card.classList.add('hs-drag-over');
                });

                card.addEventListener('dragleave', function () {
                    card.classList.remove('hs-drag-over');
                });

                card.addEventListener('drop', function (e) {
                    e.preventDefault();
                    card.classList.remove('hs-drag-over');
                    var targetIndex = parseInt(card.getAttribute('data-index'));
                    if (state.dragSrcIndex !== null && state.dragSrcIndex !== targetIndex) {
                        callback(state.dragSrcIndex, targetIndex);
                    }
                    state.dragSrcIndex = null;
                });

                card.addEventListener('dragend', function () {
                    card.classList.remove('hs-dragging');
                    var allCards = listElement.querySelectorAll('.hs-section-card');
                    for (var c = 0; c < allCards.length; c++) {
                        allCards[c].classList.remove('hs-drag-over');
                    }
                    state.dragSrcIndex = null;
                });
            })(cards[i]);
        }
    }

    // ════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════
    function updateBadgeCounts() {
        if (el.homepageCountBadge)   el.homepageCountBadge.textContent = state.homepageSections.length;
        if (el.storePagesCountBadge) el.storePagesCountBadge.textContent = state.storeSections.length;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function arrayMove(arr, fromIndex, toIndex) {
        if (fromIndex < 0 || fromIndex >= arr.length) return;
        if (toIndex < 0 || toIndex >= arr.length) return;
        var item = arr.splice(fromIndex, 1)[0];
        arr.splice(toIndex, 0, item);
    }

    // ════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════
    window.HomepageSectionsModule = {
        init: init
    };

    window.page = { run: init };

    if (window.Admin && window.Admin.page && window.Admin.page.register) {
        window.Admin.page.register('homepage_sections', init);
    }

})();