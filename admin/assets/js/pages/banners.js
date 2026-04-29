/**
 * admin/assets/js/pages/banners.js
 * Banners management — guide-compliant
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // 1. CONFIG
    // ════════════════════════════════════════════════════════════
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;

    function reloadConfig() {
        CFG        = window.BANNERS_CONFIG || {};
        CSRF       = CFG.csrfToken || window.CSRF_TOKEN || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    const API_URL           = () => (window.BANNERS_CONFIG || {}).apiUrl          || '/api/banners';
    const IMAGES_API        = () => (window.BANNERS_CONFIG || {}).imagesApi       || '/api/images';
    const LANGUAGES_API     = () => (window.BANNERS_CONFIG || {}).languagesApi    || '/api/languages';
    const BUTTON_STYLES_API = () => (window.BANNERS_CONFIG || {}).buttonStylesApi || '/api/button_styles';
    const IMAGE_TYPE_ID     = () => (window.BANNERS_CONFIG || {}).imageTypeId     || 9;
    const LANG              = () => (window.BANNERS_CONFIG || {}).lang            || window.USER_LANGUAGE || 'en';

    // ════════════════════════════════════════════════════════════
    // 2. STATE
    // ════════════════════════════════════════════════════════════
    let state = {
        items:            [],
        editingId:        null,
        selectedImageId:  null,
        languages:        [],
        filters:          { search: '', position: '', status: '' }
    };

    // ════════════════════════════════════════════════════════════
    // 3. i18n
    // ════════════════════════════════════════════════════════════
    function t(key, fallback) {
        // دائماً اقرأ من المصدر الحي — BANNERS_CONFIG.strings يُحدَّث بعد تحميل ملف الترجمة
        const live = (window.BANNERS_CONFIG && window.BANNERS_CONFIG.strings) || {};
        if (live[key] !== undefined && live[key] !== '') return String(live[key]);
        // Nested traversal على BANNERS_TRANSLATIONS (الـ JSON الخام)
        const tr  = window.BANNERS_TRANSLATIONS || {};
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr);
        if (val !== null && val !== undefined && typeof val !== 'object') return String(val);
        // Fallback
        return fallback !== undefined ? fallback : key.split('.').pop().replace(/_/g, ' ');
    }

    // طبّق الترجمات على كل [data-i18n] و [data-i18n-placeholder] في الصفحة
    function applyI18n() {
        const container = document.getElementById('bannersPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const val = t(key, '');
            if (!val) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = val;
            } else {
                el.textContent = val;
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            const val = t(key, '');
            if (val) el.placeholder = val;
        });
    }

    // ════════════════════════════════════════════════════════════
    // 4. HELPERS
    // ════════════════════════════════════════════════════════════
    const $  = id => document.getElementById(id);
    const esc = s => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    // ════════════════════════════════════════════════════════════
    // 5. SHOW STATE
    // ════════════════════════════════════════════════════════════
    function showState(stateName, errorMsg) {
        const loading   = $('bannersLoading');
        const empty     = $('bannersEmpty');
        const error     = $('bannersError');
        const container = $('bannersTableContainer');

        [loading, empty, error, container].forEach(el => { if (el) el.style.display = 'none'; });

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
                    const p = $('bannersErrorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default:
                if (container) container.style.display = 'block';
        }
    }

    // ════════════════════════════════════════════════════════════
    // 6. TOAST (prefix: bnn-)
    // ════════════════════════════════════════════════════════════
    function notify(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type || 'info');
            return;
        }
        let container = document.querySelector('.bnn-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'bnn-notifications';
            document.body.appendChild(container);
        }
        const iconMap = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = `bnn-toast bnn-toast--${type || 'info'}`;
        toast.innerHTML =
            `<i class="fas ${iconMap[type] || 'fa-info-circle'} bnn-toast-icon" aria-hidden="true"></i>` +
            `<div class="bnn-toast-body">${esc(msg)}</div>` +
            `<button class="bnn-toast-close" aria-label="Close"><i class="fas fa-times" aria-hidden="true"></i></button>`;
        toast.querySelector('.bnn-toast-close').addEventListener('click', () => removeToast(toast));
        container.appendChild(toast);
        setTimeout(() => removeToast(toast), 4000);
    }

    function removeToast(toast) {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }

    // ════════════════════════════════════════════════════════════
    // 7. FETCH HELPERS
    // ════════════════════════════════════════════════════════════
    async function apiFetch(url, opts = {}) {
        opts.credentials = 'same-origin';
        if (!opts.headers) opts.headers = {};
        opts.headers['Accept'] = 'application/json';
        if (opts.method && opts.method !== 'GET') {
            opts.headers['X-CSRF-Token'] = CSRF;
        }
        const res  = await fetch(url, opts);
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + text.slice(0, 200)); }
        if (!res.ok) throw Object.assign(new Error((json && (json.message || json.error)) || `HTTP ${res.status}`), { status: res.status, data: json });
        return json;
    }

    const apiGet    = url        => apiFetch(url);
    const apiPost   = (url, b)   => apiFetch(url, { method: 'POST',   headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
    const apiPut    = (url, b)   => apiFetch(url, { method: 'PUT',    headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
    const apiDelete = (url, b)   => apiFetch(url, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });

    // ════════════════════════════════════════════════════════════
    // 8. LOAD BANNERS
    // ════════════════════════════════════════════════════════════
    async function loadBanners() {
        try {
            showState('loading');
            const params = new URLSearchParams({ lang: LANG(), all_translations: '1' });
            if (state.filters.position) params.set('position', state.filters.position);
            if (state.filters.status !== '') params.set('is_active', state.filters.status);

            const data  = await apiGet(`${API_URL()}?${params}`);
            const items = Array.isArray(data) ? data : (data.data || data.items || []);
            state.items = items;
            renderTable();
        } catch (err) {
            console.error('[Banners] loadBanners failed:', err);
            showState('error', err.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 9. RENDER TABLE
    // ════════════════════════════════════════════════════════════
    async function renderTable() {
        const tbody = $('bannersTbody');
        if (!tbody) return;

        let items = state.items;
        const search = state.filters.search.trim().toLowerCase();
        if (search) {
            items = items.filter(b =>
                (b.title    || '').toLowerCase().includes(search) ||
                (b.subtitle || '').toLowerCase().includes(search)
            );
        }

        if (!items.length) { showState('empty'); return; }

        const rows = await Promise.all(items.map(async banner => {
            let imgHtml = `<span style="color:var(--text-secondary);">—</span>`;
            try {
                const imgData = await apiGet(`${IMAGES_API()}/by_owner?owner_id=${banner.id}&image_type_id=${IMAGE_TYPE_ID()}`);
                const images  = Array.isArray(imgData) ? imgData : (imgData.data || []);
                if (images.length) {
                    const url = images[0].url || images[0].thumb_url || '';
                    if (url) imgHtml = `<img src="${esc(url)}" alt="" style="width:80px;height:32px;object-fit:cover;border-radius:4px;">`;
                }
            } catch (_) { /* no image */ }

            const activeClass = banner.is_active ? 'badge-success' : 'badge-warning';
            const activeText  = banner.is_active ? t('table.status.active', 'Active') : t('table.status.inactive', 'Inactive');
            const dateStr     = [banner.start_date, banner.end_date].filter(Boolean).map(d => d.slice(0, 10)).join(' → ') || '—';

            const editBtn   = CAN_EDIT   ? `<button class="btn btn-sm btn-primary btn-edit"  data-id="${banner.id}" title="${esc(t('table.actions.edit',   'Edit'))}"   aria-label="${esc(t('table.actions.edit',   'Edit'))}"><i class="fas fa-edit"  aria-hidden="true"></i></button>` : '';
            const deleteBtn = CAN_DELETE ? `<button class="btn btn-sm btn-danger  btn-delete" data-id="${banner.id}" title="${esc(t('table.actions.delete', 'Delete'))}" aria-label="${esc(t('table.actions.delete', 'Delete'))}"><i class="fas fa-trash" aria-hidden="true"></i></button>` : '';

            return `<tr data-id="${banner.id}">
                <td>${esc(banner.id)}</td>
                <td>${imgHtml}</td>
                <td>
                    <strong>${esc(banner.title || '')}</strong>
                    ${banner.subtitle ? `<br><small style="color:var(--text-secondary);">${esc(banner.subtitle)}</small>` : ''}
                </td>
                <td>${esc(banner.position || '')}</td>
                <td>${esc(banner.sort_order ?? 0)}</td>
                <td><span class="badge ${activeClass}">${esc(activeText)}</span></td>
                <td style="font-size:0.82rem;">${esc(dateStr)}</td>
                <td><div class="table-actions">${editBtn}${deleteBtn}</div></td>
            </tr>`;
        }));

        tbody.innerHTML = rows.join('');
        showState('table');
        bindTableActions();
    }

    function bindTableActions() {
        document.querySelectorAll('#bannersTable .btn-edit').forEach(btn => {
            btn.addEventListener('click', () => openEditForm(parseInt(btn.dataset.id, 10)));
        });
        document.querySelectorAll('#bannersTable .btn-delete').forEach(btn => {
            btn.addEventListener('click', () => confirmDelete(parseInt(btn.dataset.id, 10)));
        });
    }

    // ════════════════════════════════════════════════════════════
    // 10. MODAL — form open / close / focus
    // ════════════════════════════════════════════════════════════
    function openForm() {
        const fc = $('bannerFormContainer');
        if (!fc) return;
        fc.style.display = 'block';
        fc.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const first = fc.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (first) setTimeout(() => first.focus(), 50);
    }

    function closeForm() {
        const fc = $('bannerFormContainer');
        if (fc) fc.style.display = 'none';
        resetForm();
    }

    function openAddForm() {
        resetForm();
        state.editingId       = null;
        state.selectedImageId = null;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.add_title', 'Add Banner');
        openForm();
    }

    async function openEditForm(id) {
        resetForm();
        state.editingId = id;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.edit_title', 'Edit Banner');
        openForm();
        try {
            const data   = await apiGet(`${API_URL()}/${id}?all_translations=1`);
            const banner = data.data || data;
            populateForm(banner);
        } catch (err) {
            notify(t('messages.error.load_failed') + ': ' + err.message, 'error');
            closeForm();
        }
    }

    function resetForm() {
        const form = $('bannerForm');
        if (form) form.reset();
        ['formId', 'bannerImageId'].forEach(id => { const el = $(id); if (el) el.value = ''; });
        state.selectedImageId = null;
        state.editingId       = null;
        const preview = $('bannerImagePreview');
        if (preview) { preview.src = ''; preview.style.display = 'none'; }
        const links = $('bannerImageLinks');
        if (links) links.innerHTML = '';
        const bg = $('bannerBgColor');   if (bg) bg.value = '#FFFFFF';
        const tc = $('bannerTextColor'); if (tc) tc.value = '#000000';
        ['title', 'subtitle', 'link_text'].forEach(f => { const el = $(`trans_en_${f}`); if (el) el.value = ''; });
        const dynPanels = $('bannerTranslations');
        if (dynPanels) dynPanels.innerHTML = '';
        document.querySelectorAll('#bannerForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function populateForm(banner) {
        const set = (id, val) => { const el = $(id); if (el) el.value = val == null ? '' : val; };
        set('formId',          banner.id);
        set('bannerTitle',     banner.title     || '');
        set('bannerSubtitle',  banner.subtitle  || '');
        set('bannerLinkUrl',   banner.link_url  || '');
        set('bannerLinkText',  banner.link_text || '');
        set('bannerSortOrder', banner.sort_order ?? 0);
        set('bannerBgColor',   banner.background_color || '#FFFFFF');
        set('bannerTextColor', banner.text_color       || '#000000');
        set('bannerButtonStyle', banner.button_style   || '');
        const posEl = $('bannerPosition'); if (posEl) posEl.value = banner.position || 'homepage_main';
        const stEl  = $('bannerIsActive'); if (stEl)  stEl.value  = String(banner.is_active ?? 1);
        if (banner.start_date) { const el = $('bannerStartDate'); if (el) el.value = banner.start_date.slice(0, 16); }
        if (banner.end_date)   { const el = $('bannerEndDate');   if (el) el.value = banner.end_date.slice(0, 16);   }

        const translations = banner.translations || {};
        const enTrans = translations['en'] || {};
        const setE = (f, fb) => { const el = $(`trans_en_${f}`); if (el) el.value = enTrans[f] || fb || ''; };
        setE('title',     banner.title);
        setE('subtitle',  banner.subtitle);
        setE('link_text', banner.link_text);

        const dynPanels = $('bannerTranslations');
        if (dynPanels) dynPanels.innerHTML = '';
        Object.entries(translations).forEach(([code, lt]) => {
            if (code === 'en') return;
            const langName = (state.languages.find(l => l.code === code) || {}).name || code.toUpperCase();
            if (dynPanels) dynPanels.appendChild(createTranslationPanel(code, langName, lt));
        });

        loadBannerImage(banner.id);
    }

    async function loadBannerImage(bannerId) {
        try {
            const data   = await apiGet(`${IMAGES_API()}/by_owner?owner_id=${encodeURIComponent(bannerId)}&image_type_id=${IMAGE_TYPE_ID()}`);
            const images = Array.isArray(data) ? data : (data.data || []);
            if (images.length) {
                const img = images[0];
                state.selectedImageId = img.id;
                const imgIdEl = $('bannerImageId'); if (imgIdEl) imgIdEl.value = img.id;
                const url = img.url || img.thumb_url || '';
                const preview = $('bannerImagePreview');
                if (preview && url) { preview.src = url; preview.style.display = 'block'; }
                const links = $('bannerImageLinks');
                if (links && url) links.innerHTML = `<a href="${esc(url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color);">View</a>`;
            }
        } catch (_) { /* no image */ }
    }

    // ════════════════════════════════════════════════════════════
    // 11. SAVE
    // ════════════════════════════════════════════════════════════
    async function handleFormSubmit(e) {
        e.preventDefault();
        const enTitleEl = $('trans_en_title');
        if (!enTitleEl || !enTitleEl.value.trim()) {
            if (enTitleEl) enTitleEl.classList.add('is-invalid');
            notify(t('messages.error.en_required', 'English title is required'), 'error');
            if (enTitleEl) enTitleEl.focus();
            return;
        }
        if (enTitleEl) enTitleEl.classList.remove('is-invalid');

        const saveBtn = $('bannerSaveBtn');
        const saveTxt = $('bannerSaveBtnText');
        if (saveBtn) saveBtn.disabled = true;
        if (saveTxt) saveTxt.textContent = t('form.buttons.saving', 'Saving...');

        try {
            const payload = buildPayload();
            const result  = state.editingId
                ? await apiPut(`${API_URL()}/${state.editingId}`, payload)
                : await apiPost(API_URL(), payload);

            notify(state.editingId ? t('messages.success.updated') : t('messages.success.created'), 'success');
            closeForm();
            await loadBanners();
        } catch (err) {
            console.error('[Banners] save failed:', err);
            notify(t('messages.error.save_failed') + ': ' + err.message, 'error');
        } finally {
            if (saveBtn) saveBtn.disabled = false;
            if (saveTxt) saveTxt.textContent = t('form.buttons.save', 'Save');
        }
    }

    function buildPayload() {
        const get = id => { const el = $(id); return el ? el.value.trim() : ''; };
        const translations = {};

        const enTitle    = get('trans_en_title')    || get('bannerTitle');
        const enSubtitle = get('trans_en_subtitle') || get('bannerSubtitle');
        const enLinkText = get('trans_en_link_text') || get('bannerLinkText');
        if (enTitle || enSubtitle || enLinkText) {
            translations['en'] = { title: enTitle, subtitle: enSubtitle, link_text: enLinkText };
        }

        document.querySelectorAll('#bannerTranslations .banner-translation-panel').forEach(panel => {
            const code    = panel.dataset.lang;
            if (!code || code === 'en') return;
            const title    = (panel.querySelector('.btrans-title')     || {}).value || '';
            const subtitle = (panel.querySelector('.btrans-subtitle')  || {}).value || '';
            const linkText = (panel.querySelector('.btrans-link-text') || {}).value || '';
            if (title || subtitle || linkText) translations[code] = { title, subtitle, link_text: linkText };
        });

        return {
            title:            (translations.en && translations.en.title)    || get('bannerTitle'),
            subtitle:         (translations.en && translations.en.subtitle) || get('bannerSubtitle'),
            link_url:         get('bannerLinkUrl'),
            link_text:        (translations.en && translations.en.link_text) || get('bannerLinkText'),
            position:         get('bannerPosition') || 'homepage_main',
            background_color: get('bannerBgColor')  || '#FFFFFF',
            text_color:       get('bannerTextColor') || '#000000',
            button_style:     get('bannerButtonStyle'),
            sort_order:       parseInt(get('bannerSortOrder'), 10) || 0,
            is_active:        parseInt(get('bannerIsActive'), 10) ?? 1,
            start_date:       get('bannerStartDate') || null,
            end_date:         get('bannerEndDate')   || null,
            image_id:         state.selectedImageId  || null,
            translations
        };
    }

    // ════════════════════════════════════════════════════════════
    // 12. DELETE
    // ════════════════════════════════════════════════════════════
    async function confirmDelete(id) {
        if (!confirm(t('table.actions.confirm_delete', 'Are you sure you want to delete this banner?'))) return;
        try {
            await apiDelete(`${API_URL()}/${id}`);
            notify(t('messages.success.deleted'), 'success');
            await loadBanners();
        } catch (err) {
            notify(t('messages.error.delete_failed') + ': ' + err.message, 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // 13. MEDIA STUDIO
    // ════════════════════════════════════════════════════════════
    function openMediaStudio() {
        const tenantId = (window.BANNERS_CONFIG || {}).tenantId || 1;
        const ownerId  = state.editingId || 0;
        const src      = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${encodeURIComponent(tenantId)}&owner_id=${encodeURIComponent(ownerId)}&image_type_id=${IMAGE_TYPE_ID()}&lang=${encodeURIComponent(LANG())}&mode=select&limit=1`;
        const overlay = $('bannerMediaStudioOverlay');
        const frame   = $('bannerMediaStudioFrame');
        if (!overlay || !frame) return;
        frame.src = src;
        overlay.style.display = 'flex';
    }

    function closeMediaStudio() {
        const overlay = $('bannerMediaStudioOverlay');
        const frame   = $('bannerMediaStudioFrame');
        if (overlay) overlay.style.display = 'none';
        if (frame)   frame.src = 'about:blank';
    }

    function removeImage() {
        state.selectedImageId = null;
        const imgIdEl = $('bannerImageId'); if (imgIdEl) imgIdEl.value = '';
        const preview = $('bannerImagePreview'); if (preview) { preview.src = ''; preview.style.display = 'none'; }
        const links   = $('bannerImageLinks');   if (links)   links.innerHTML = '';
    }

    window.addEventListener('ImageStudio:selected', function (ev) {
        const img      = ev.detail;
        const selected = Array.isArray(img) ? img[0] : img;
        if (!selected) return;
        state.selectedImageId = selected.id;
        const imgIdEl = $('bannerImageId'); if (imgIdEl) imgIdEl.value = selected.id;
        const url     = selected.url || selected.thumb_url || '';
        const preview = $('bannerImagePreview');
        if (preview && url) { preview.src = url; preview.style.display = 'block'; }
        const links = $('bannerImageLinks');
        if (links && url) links.innerHTML = `<a href="${esc(url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color);">View</a>`;
        closeMediaStudio();
    });

    window.addEventListener('ImageStudio:close', closeMediaStudio);

    // ════════════════════════════════════════════════════════════
    // 14. TRANSLATIONS (languages + dynamic panels)
    // ════════════════════════════════════════════════════════════
    async function loadLanguages() {
        try {
            const res   = await apiGet(`${LANGUAGES_API()}?format=json`);
            const items = res?.data?.items || res?.data || res || [];
            state.languages = Array.isArray(items) ? items : [];
            const langSelect = $('bannerLangSelect');
            if (!langSelect) return;
            langSelect.innerHTML = `<option value="">${t('form.translations.choose_language', 'Choose language')}</option>`;
            state.languages.forEach(lang => {
                if (lang.code === 'en') return;
                const opt = document.createElement('option');
                opt.value = lang.code;
                opt.textContent = lang.name || lang.code;
                langSelect.appendChild(opt);
            });
        } catch (err) { console.warn('[Banners] Failed to load languages:', err); }
    }

    async function loadButtonStyles() {
        const select = $('bannerButtonStyle');
        if (!select || select.options.length > 1) return;
        try {
            const res   = await apiGet(`${BUTTON_STYLES_API()}?format=json&is_active=1`);
            const items = res?.data?.items || res?.data || (Array.isArray(res) ? res : []);
            if (!Array.isArray(items) || !items.length) return;
            items.forEach(bs => {
                const slug = bs.slug || bs.id || '';
                if (!slug) return;
                const opt = document.createElement('option');
                opt.value = slug;
                opt.textContent = bs.name || slug;
                select.appendChild(opt);
            });
        } catch (err) { console.warn('[Banners] Failed to load button styles:', err); }
    }

    function createTranslationPanel(langCode, langName, data) {
        const panel = document.createElement('div');
        panel.className = 'translation-panel banner-translation-panel';
        panel.dataset.lang = langCode;
        const dir = (state.languages.find(l => l.code === langCode) || {}).direction || 'ltr';
        panel.innerHTML = `
            <div class="translation-panel-header">
                <span class="lang-badge">${esc(langCode.toUpperCase())}</span>
                <span>${esc(langName)}</span>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.banner-translation-panel').remove()" aria-label="Remove">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="translation-panel-body">
                <div class="form-group">
                    <label class="filter-label">${t('form.translations.title_label', 'Title')}</label>
                    <input type="text" class="form-control btrans-title"     dir="${esc(dir)}" value="${esc(data.title    || '')}">
                </div>
                <div class="form-group">
                    <label class="filter-label">${t('form.translations.subtitle_label', 'Subtitle')}</label>
                    <input type="text" class="form-control btrans-subtitle"  dir="${esc(dir)}" value="${esc(data.subtitle  || '')}">
                </div>
                <div class="form-group">
                    <label class="filter-label">${t('form.translations.link_text_label', 'Button Text')}</label>
                    <input type="text" class="form-control btrans-link-text" dir="${esc(dir)}" value="${esc(data.link_text || '')}">
                </div>
            </div>`;
        return panel;
    }

    function addBannerTranslation() {
        const langSelect = $('bannerLangSelect');
        if (!langSelect || !langSelect.value) return;
        const langCode = langSelect.value;
        const langName = langSelect.options[langSelect.selectedIndex].textContent;
        if (document.querySelector(`#bannerTranslations [data-lang="${langCode}"]`)) {
            notify(t('messages.translation_exists', 'Translation already added'), 'warning');
            return;
        }
        const container = $('bannerTranslations');
        if (container) container.appendChild(createTranslationPanel(langCode, langName, {}));
        langSelect.value = '';
    }

    // ════════════════════════════════════════════════════════════
    // 15. FILTERS
    // ════════════════════════════════════════════════════════════
    function bindFilters() {
        $('bannerSearch')?.addEventListener('input', function () {
            state.filters.search = this.value;
            renderTable();
        });
        $('bannerFilterPosition')?.addEventListener('change', function () {
            state.filters.position = this.value;
            loadBanners();
        });
        $('bannerFilterStatus')?.addEventListener('change', function () {
            state.filters.status = this.value;
            loadBanners();
        });
        $('btnRefresh')?.addEventListener('click', loadBanners);
    }

    // ════════════════════════════════════════════════════════════
    // 16. INIT
    // ════════════════════════════════════════════════════════════
    async function init() {
        reloadConfig();
        applyI18n(); // طبّق الترجمات على كل [data-i18n] في الصفحة

        // ── Bind UI Events ──────────────────────────────────────
        $('btnAddBanner')?.addEventListener('click', openAddForm);
        $('btnAddBannerEmpty')?.addEventListener('click', openAddForm);
        $('btnCloseForm')?.addEventListener('click', closeForm);
        $('btnCancelForm')?.addEventListener('click', closeForm);
        $('bannerForm')?.addEventListener('submit', handleFormSubmit);
        $('bannerSelectImageBtn')?.addEventListener('click', openMediaStudio);
        $('bannerRemoveImageBtn')?.addEventListener('click', removeImage);
        $('bannerAddLangBtn')?.addEventListener('click', addBannerTranslation);
        $('bannerMediaStudioClose')?.addEventListener('click', closeMediaStudio);
        $('btnRetry')?.addEventListener('click', loadBanners);

        // Media studio backdrop click
        $('bannerMediaStudioOverlay')?.addEventListener('click', function (e) {
            if (e.target === this) closeMediaStudio();
        });

        // ESC closes form AND media studio
        document.addEventListener('keydown', function handleEsc(e) {
            if (e.key !== 'Escape') return;
            const overlay = $('bannerMediaStudioOverlay');
            if (overlay && overlay.style.display !== 'none') { closeMediaStudio(); return; }
            const fc = $('bannerFormContainer');
            if (fc && fc.style.display !== 'none') closeForm();
        });

        bindFilters();

        await Promise.all([loadBanners(), loadLanguages(), loadButtonStyles()]);
        console.log('[Banners] ✓ Initialized');
    }

    // ════════════════════════════════════════════════════════════
    // 17. REGISTER
    // ════════════════════════════════════════════════════════════
    window.Banners = { init };
    window.page    = { run: init };

    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('banners', init);
    }

    // Initialization driven by fragment's inline <script> (admin:i18n:applied)
    // Do NOT self-invoke init() here.

}());