/**
 * Addresses Management - Production Version
 * Full CRUD + Countries/Cities + Multilingual + Owner-aware
 */
(function () {
    'use strict';

    const AF = window.AdminFramework || {};
    const CFG = window.ADDRESSES_CONFIG || {};

    const API = CFG.apiUrl || '/api/addresses';
    const COUNTRIES_API = CFG.countriesApi || '/api/countries';
    const CITIES_API = CFG.citiesApi || '/api/cities';
    const ENTITIES_API = CFG.entitiesApi || '/api/entities';

    const S = CFG.strings || {};
    function t(key, fallback) { return S[key] || fallback || key; }

    const PER_PAGE = 10;
    let currentPage = 1;

    const state = {
        language: CFG.lang || 'ar',
        items: [],
        countries: [],
        cities: [],
        entities: []
    };

    let el = {};

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════
    
    function esc(txt) {
        if (!txt) return '';
        const d = document.createElement('div');
        d.textContent = txt;
        return d.innerHTML;
    }

    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        };
        const res = await fetch(url, { ...defaults, ...options });
        return await res.json();
    }

    function showMessage(msg, type = 'success') {
        if (AF.success && type === 'success') return AF.success(msg);
        if (AF.error && type === 'error') return AF.error(msg);
        alert(msg);
    }

    // ═══════════════════════════════════════════════════════════
    // GET USER LOCATION
    // ═══════════════════════════════════════════════════════════
    
    function getUserLocation() {
        if (!navigator.geolocation) {
            showMessage(t('location_not_supported', 'Geolocation is not supported by your browser'), 'error');
            return;
        }

        const btnGetLocation = document.getElementById('btnGetLocation');
        if (btnGetLocation) {
            btnGetLocation.disabled = true;
            btnGetLocation.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + t('getting_location', 'Getting location...');
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (el.latitude) el.latitude.value = lat.toFixed(7);
                if (el.longitude) el.longitude.value = lng.toFixed(7);

                showMessage(t('location_success', 'Location retrieved successfully!'), 'success');
                
                if (btnGetLocation) {
                    btnGetLocation.disabled = false;
                    btnGetLocation.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location');
                }
            },
            (error) => {
                let errorMsg = t('location_error', 'Unable to retrieve your location');
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg = t('location_denied', 'Location access denied. Please enable location permissions.');
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg = t('location_unavailable', 'Location information is unavailable.');
                        break;
                    case error.TIMEOUT:
                        errorMsg = t('location_timeout', 'Location request timed out.');
                        break;
                }

                showMessage(errorMsg, 'error');
                
                if (btnGetLocation) {
                    btnGetLocation.disabled = false;
                    btnGetLocation.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location');
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD COUNTRIES
    // ═══════════════════════════════════════════════════════════
    
    async function loadCountries(selectedId = null) {
        try {
            const url = `${COUNTRIES_API}?language=${encodeURIComponent(state.language)}`;
            console.log('📡 Loading countries from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 Countries response:', result);
            
            // Handle different response formats
            if (result.data) {
                if (Array.isArray(result.data.data)) {
                    state.countries = result.data.data;
                } else if (Array.isArray(result.data)) {
                    state.countries = result.data;
                }
            } else if (Array.isArray(result)) {
                state.countries = result;
            } else {
                state.countries = [];
            }

            if (el.country) {
                el.country.innerHTML = '<option value="">' + t('select_country', 'Select Country') + '</option>';
                state.countries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    if (selectedId && String(selectedId) === String(country.id)) {
                        option.selected = true;
                    }
                    el.country.appendChild(option);
                });

                // Trigger city load if country selected
                if (selectedId) {
                    await loadCities(selectedId);
                }
            }

            console.log('✓ Countries loaded:', state.countries.length);
        } catch (e) {
            console.error('❌ loadCountries error:', e);
            showMessage(t('failed_load_countries', 'Failed to load countries'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD CITIES
    // ═══════════════════════════════════════════════════════════
    
    async function loadCities(countryId, selectedId = null) {
        if (!el.city) return;

        el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>';
        el.city.disabled = true;

        if (!countryId) {
            return;
        }

        try {
            const url = `${CITIES_API}?country_id=${encodeURIComponent(countryId)}&language=${encodeURIComponent(state.language)}`;
            console.log('📡 Loading cities from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 Cities response:', result);
            
            // Handle different response formats
            if (result.data) {
                if (Array.isArray(result.data.data)) {
                    state.cities = result.data.data;
                } else if (Array.isArray(result.data)) {
                    state.cities = result.data;
                }
            } else if (Array.isArray(result)) {
                state.cities = result;
            } else {
                state.cities = [];
            }

            el.city.disabled = false;
            state.cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                if (selectedId && String(selectedId) === String(city.id)) {
                    option.selected = true;
                }
                el.city.appendChild(option);
            });

            console.log('✓ Cities loaded:', state.cities.length);
        } catch (e) {
            console.error('❌ loadCities error:', e);
            showMessage(t('failed_load_cities', 'Failed to load cities'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD ENTITIES (tenant-scoped)
    // ═══════════════════════════════════════════════════════════

    async function loadEntities(selectedId = null) {
        if (!el.ownerEntitySelect) return;

        el.ownerEntitySelect.innerHTML = '<option value="">' + t('select_entity', 'Select entity...') + '</option>';

        try {
            const tenantId = CFG.tenantId || 0;
            const params = new URLSearchParams({ limit: 1000, language: state.language });
            if (tenantId) params.append('tenant_id', tenantId);

            const result = await apiFetch(`${ENTITIES_API}?${params}`);
            const items = (result.data && (result.data.data || result.data)) || (Array.isArray(result) ? result : []);
            state.entities = Array.isArray(items) ? items : [];

            state.entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.id;
                option.textContent = entity.store_name || entity.name || `#${entity.id}`;
                if (selectedId && String(selectedId) === String(entity.id)) {
                    option.selected = true;
                }
                el.ownerEntitySelect.appendChild(option);
            });
        } catch (e) {
            console.error('❌ loadEntities error:', e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // TOGGLE OWNER FIELDS (user ↔ entity)
    // ═══════════════════════════════════════════════════════════

    async function toggleOwnerFields(ownerType, selectedEntityId = null) {
        if (!CFG.canEditAllFields) return;

        const isEntity = ownerType === 'entity';

        if (el.ownerIdInput) {
            el.ownerIdInput.style.display  = isEntity ? 'none' : '';
            el.ownerIdInput.disabled       = isEntity;
            el.ownerIdInput.required       = !isEntity;
            el.ownerIdInput.name           = isEntity ? '' : 'owner_id';
        }
        if (el.ownerEntitySelect) {
            el.ownerEntitySelect.style.display = isEntity ? '' : 'none';
            el.ownerEntitySelect.disabled      = !isEntity;
            el.ownerEntitySelect.required      = isEntity;
            el.ownerEntitySelect.name          = isEntity ? 'owner_id' : '';
        }

        if (isEntity) {
            await loadEntities(selectedEntityId);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD ADDRESSES
    // ═══════════════════════════════════════════════════════════
    
    async function loadAddresses() {
        if (!el.tbody) return;

        el.tbody.innerHTML = '<tr><td colspan="' + (CFG.isPlatformAdmin ? 8 : 7) + '" style="text-align:center">' + t('loading', 'Loading...') + '</td></tr>';

        try {
            const params = new URLSearchParams({
                page: currentPage,
                limit: PER_PAGE,
                language: state.language
            });

            // Global filter for Platform Admin
            const globalFilter = document.getElementById('globalTenantFilter');
            if (globalFilter && globalFilter.value !== '') {
                params.append('tenant_id', globalFilter.value);
            } else if (!CFG.isPlatformAdmin) {
                params.append('tenant_id', CFG.tenantId);
            }

            const url = `${API}?${params}`;
            console.log('📡 Loading addresses from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 API Response:', result);
            
            if (result.success && result.data) {
                state.items = result.data.data || [];
                const meta = result.data.meta || {};
                renderTable(state.items);
                renderPagination(meta.total || 0, meta.total_pages || 1);
            } else {
                throw new Error(result.message || 'Failed to load addresses');
            }
        } catch (e) {
            console.error('❌ loadAddresses error:', e);
            el.tbody.innerHTML = '<tr><td colspan="' + (CFG.isPlatformAdmin ? 8 : 7) + '" style="text-align:center;color:red">' + t('error_loading', 'Error loading addresses') + '</td></tr>';
            showMessage(t('failed_load_list', 'Failed to load addresses'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PAGINATION
    // ═══════════════════════════════════════════════════════════

    function renderPagination(total, totalPages) {
        const infoEl = document.getElementById('paginationInfo');
        const pagEl = document.getElementById('pagination');
        if (!infoEl || !pagEl) return;

        if (total === 0) {
            infoEl.textContent = '';
            pagEl.innerHTML = '';
            return;
        }

        const start = (currentPage - 1) * PER_PAGE + 1;
        const end = Math.min(currentPage * PER_PAGE, total);
        infoEl.textContent = t('pagination_showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination_of', 'of') + ' ' + total;

        let html = '';
        html += '<button class="page-btn" data-page="' + (currentPage - 1) + '"' + (currentPage <= 1 ? ' disabled' : '') + '>&laquo;</button>';

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += '<button class="page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="page-ellipsis">...</span>';
            }
        }

        html += '<button class="page-btn" data-page="' + (currentPage + 1) + '"' + (currentPage >= totalPages ? ' disabled' : '') + '>&raquo;</button>';

        pagEl.innerHTML = html;

        pagEl.querySelectorAll('.page-btn').forEach(function(btn) {
            btn.onclick = function() {
                const page = parseInt(this.getAttribute('data-page'));
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    loadAddresses();
                }
            };
        });
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════════════════════
    
    function renderTable(items) {
        if (!el.tbody) return;

        const colSpan = CFG.isPlatformAdmin ? 8 : 7;
        if (!items || items.length === 0) {
            el.tbody.innerHTML = '<tr><td colspan="' + colSpan + '" style="text-align:center;color:#888">' + t('no_addresses', 'No addresses found') + '</td></tr>';
            return;
        }

        el.tbody.innerHTML = items.map(addr => {
            const countryName = addr.country_name || addr.country || '';
            const cityName = addr.city_name || addr.city || '';
            const addressLine = addr.address_line1 || addr.address_line || '';
            const isPrimary = addr.is_primary || addr.is_default || false;
            const tenantId = addr.tenant_id || '—';

            const editBtn = CFG.permissions.canEdit 
                ? `<button class="btn btn-sm btn-secondary btnEdit" data-id="${addr.id}">${t('edit', 'Edit')}</button>` 
                : '';
            const deleteBtn = CFG.permissions.canDelete 
                ? `<button class="btn btn-sm btn-danger btnDelete" data-id="${addr.id}">${t('delete', 'Delete')}</button>` 
                : '';

            const tenantCol = CFG.isPlatformAdmin ? `<td>${esc(String(tenantId))}</td>` : '';
            const ownerCol = `<td><span class="badge badge-outline">${esc(addr.owner_type || '')}</span> ${esc(String(addr.owner_id || ''))}</td>`;

            return `
                <tr>
                    <td>${addr.id}</td>
                    ${tenantCol}
                    ${ownerCol}
                    <td>${esc(countryName)}</td>
                    <td>${esc(cityName)}</td>
                    <td>${esc(addressLine)}</td>
                    <td>${isPrimary ? t('primary_yes', '✔') : ''}</td>
                    <td>${editBtn} ${deleteBtn}</td>
                </tr>
            `;
        }).join('');

        el.tbody.querySelectorAll('.btnEdit').forEach(btn => {
            btn.onclick = () => editAddress(btn.dataset.id);
        });

        el.tbody.querySelectorAll('.btnDelete').forEach(btn => {
            btn.onclick = () => deleteAddress(btn.dataset.id);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // ADD ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    function addAddress() {
        if (el.form) el.form.reset();
        if (el.formCard) el.formCard.style.display = 'block';
        if (el.formTitle) el.formTitle.textContent = t('add_address', 'Add Address');
        if (el.btnDelete) el.btnDelete.style.display = 'none';

        if (CFG.isPlatformAdmin) {
            const formTenantId = document.getElementById('formTenantId');
            if (formTenantId) formTenantId.value = document.getElementById('globalTenantFilter')?.value || CFG.tenantId;
        }

        // Reset owner-type fields to "user" view.
        if (CFG.canEditAllFields) {
            toggleOwnerFields('user');
        }

        loadCountries();
        if (el.city) {
            el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>';
            el.city.disabled = true;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // EDIT ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function editAddress(id) {
        try {
            const result = await apiFetch(`${API}/${id}?language=${state.language}`);
            const addr = result.data || result;

            if (el.formCard) el.formCard.style.display = 'block';
            if (el.formTitle) el.formTitle.textContent = t('edit_address', 'Edit Address');
            if (el.btnDelete) el.btnDelete.style.display = 'block';

            if (el.form) {
                el.form.id.value = addr.id || '';
                el.form.address_line1.value = addr.address_line1 || '';
                el.form.address_line2.value = addr.address_line2 || '';
                el.form.postal_code.value = addr.postal_code || '';
                el.form.is_primary.value = addr.is_primary || '0';
                
                if (CFG.isPlatformAdmin) {
                    const formTenantId = document.getElementById('formTenantId');
                    if (formTenantId) formTenantId.value = addr.tenant_id || '';
                }

                if (CFG.canEditAllFields) {
                    const ownerTypeSelect = document.getElementById('ownerTypeSelect');
                    if (ownerTypeSelect) ownerTypeSelect.value = addr.owner_type || 'user';
                    // Toggle and populate entity dropdown / user-id input accordingly.
                    await toggleOwnerFields(
                        addr.owner_type || 'user',
                        addr.owner_type === 'entity' ? addr.owner_id : null
                    );
                    // For user-type, restore the numeric input value after toggleOwnerFields reset it.
                    if ((addr.owner_type || 'user') === 'user' && el.ownerIdInput) {
                        el.ownerIdInput.value = addr.owner_id || '';
                    }
                }
            }

            await loadCountries(addr.country_id);
            await loadCities(addr.country_id, addr.city_id);

        } catch (e) {
            console.error('❌ editAddress error:', e);
            showMessage(t('failed_load', 'Failed to load address'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // SAVE ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function saveAddress(e) {
        e.preventDefault();

        const formData = new FormData(el.form);
        const data = Object.fromEntries(formData.entries());

        const id = data.id;
        if (id) delete data.id;

        try {
            const url = id ? `${API}/${id}` : API;
            const method = id ? 'PUT' : 'POST';

            const result = await apiFetch(url, {
                method,
                body: JSON.stringify(data)
            });

            if (result.success) {
                showMessage(id ? t('address_updated', 'Address updated successfully') : t('address_created', 'Address created successfully'), 'success');
                if (el.formCard) el.formCard.style.display = 'none';
                loadAddresses();
            } else {
                showMessage(result.message || t('save_failed', 'Save failed'), 'error');
            }
        } catch (e) {
            console.error('❌ saveAddress error:', e);
            showMessage(t('save_failed', 'Failed to save address'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function deleteAddress(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this address?'))) {
            return;
        }

        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'DELETE',
                body: JSON.stringify({ csrf_token: CFG.csrf })
            });

            if (result.success) {
                showMessage(t('address_deleted', 'Address deleted successfully'), 'success');
                loadAddresses();
            } else {
                showMessage(result.message || t('delete_failed', 'Delete failed'), 'error');
            }
        } catch (e) {
            console.error('❌ deleteAddress error:', e);
            showMessage(t('delete_failed', 'Failed to delete address'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════
    
    async function init() {
        el = {
            tbody: document.querySelector('#addressesTable tbody'),
            form: document.getElementById('addressForm'),
            formCard: document.getElementById('addressFormCard'),
            formTitle: document.getElementById('addressFormTitle'),
            country: document.getElementById('countrySelect'),
            city: document.getElementById('citySelect'),
            ownerIdInput: document.getElementById('ownerIdInput'),
            ownerEntitySelect: document.getElementById('ownerEntitySelect'),
            ownerTypeSelect: document.getElementById('ownerTypeSelect'),
            btnAdd: document.getElementById('btnAddAddress'),
            btnClose: document.getElementById('btnCloseForm'),
            btnDelete: document.getElementById('btnDeleteAddress'),
            btnGetLocation: document.getElementById('btnGetLocation'),
            globalFilter: document.getElementById('globalTenantFilter')
        };

        if (el.form) el.form.onsubmit = saveAddress;
        if (el.btnAdd) el.btnAdd.onclick = addAddress;
        if (el.btnClose) el.btnClose.onclick = () => { if (el.formCard) el.formCard.style.display = 'none'; };
        if (el.btnDelete) el.btnDelete.onclick = () => { const id = el.form?.id?.value; if (id) deleteAddress(id); };
        if (el.btnGetLocation) el.btnGetLocation.onclick = getUserLocation;
        if (el.country) el.country.onchange = () => loadCities(el.country.value);
        if (el.globalFilter) el.globalFilter.onchange = () => { currentPage = 1; loadAddresses(); };

        // Wire up owner-type selector → swap user-id input ↔ entity dropdown.
        if (el.ownerTypeSelect) {
            el.ownerTypeSelect.onchange = () => toggleOwnerFields(el.ownerTypeSelect.value);
        }

        await loadCountries();
        await loadAddresses();
    }

    window.Addresses = {
        init,
        load: loadAddresses,
        add: addAddress,
        edit: editAddress,
        delete: deleteAddress
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();