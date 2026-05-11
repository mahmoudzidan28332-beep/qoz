/**
 * assets/js/public.js — Production v4.0 (FINAL)
 * QOOQZ — Global Public Interface JS
 *
 * ── Fixes vs v3.1 ──────────────────────────────────────────────
 *   FIX-6  Added missing pubSyncCartBadges definition before usage
 *   FIX-7  Removed duplicate function definitions at file end
 *   FIX-8  Fixed polishProductCards to not break existing functionality
 *   FIX-9  Fixed fixBrandStrings to work with RTL properly
 *   FIX-10 Proper cart conflict modal handling for branch switching
 * ──────────────────────────────────────────────────────────────
 */

function pubGetTenantId() {
  return (typeof window.__qzTenantId !== 'undefined' && window.__qzTenantId)
    ? parseInt(window.__qzTenantId, 10) || 1
    : 1;
}

function pubEntityStateStorageKey(tenantId) {
  return 'pub_active_entity:' + String(tenantId || pubGetTenantId());
}

function pubEntityCartStorageKey(entityId, tenantId) {
  return 'pub_cart_t' + String(tenantId || pubGetTenantId()) + '_e' + String(parseInt(entityId, 10) || 0);
}

function pubGetClientActiveEntity() {
  var active = (typeof window.pubActiveEntity !== 'undefined' && window.pubActiveEntity) ? window.pubActiveEntity : null;
  if (active && parseInt(active.id, 10) > 0) return active;
  try {
    active = JSON.parse(localStorage.getItem(pubEntityStateStorageKey()) || 'null');
  } catch (e) {
    active = null;
  }
  return active && parseInt(active.id, 10) > 0 ? active : null;
}

function pubSetClientActiveEntity(entity) {
  if (!entity || !parseInt(entity.id, 10)) return;
  window.pubActiveEntity = entity;
  window.__qzEntityId = parseInt(entity.id, 10) || 0;
  try {
    localStorage.setItem(pubEntityStateStorageKey(), JSON.stringify(entity));
  } catch (e) {}
}

function pubGetActiveEntityId() {
  var active = pubGetClientActiveEntity();
  return active ? (parseInt(active.id, 10) || 0) : 0;
}

function pubMigrateLegacyCart(entityId, tenantId) {
  var eid = parseInt(entityId, 10) || pubGetActiveEntityId();
  if (!eid) return [];
  var tid = tenantId || pubGetTenantId();

  var scopedKey = pubEntityCartStorageKey(eid, tid);
  var scoped = [];
  try { scoped = JSON.parse(localStorage.getItem(scopedKey) || '[]'); } catch (e) { scoped = []; }
  if (Array.isArray(scoped) && scoped.length) return scoped;

  var oldColonKey = 'pub_cart:' + String(tid) + ':' + String(eid);
  var fromOldKey = [];
  try { fromOldKey = JSON.parse(localStorage.getItem(oldColonKey) || '[]'); } catch (e) { fromOldKey = []; }
  if (Array.isArray(fromOldKey) && fromOldKey.length) {
    try {
      localStorage.setItem(scopedKey, JSON.stringify(fromOldKey));
      localStorage.removeItem(oldColonKey);
    } catch (e) {}
    return fromOldKey;
  }
  try { localStorage.removeItem(oldColonKey); } catch (e) {}

  var legacy = [];
  try { legacy = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) { legacy = []; }
  if (!Array.isArray(legacy) || !legacy.length) return [];

  var migrated = legacy.filter(function (item) {
    var itemEntityId = parseInt(item.entity_id, 10) || eid;
    return itemEntityId === eid;
  }).map(function (item) {
    item.entity_id = parseInt(item.entity_id, 10) || eid;
    return item;
  });

  try {
    localStorage.setItem(scopedKey, JSON.stringify(migrated));
    localStorage.removeItem('pub_cart');
  } catch (e) {}

  return migrated;
}

function pubLoadScopedCart(entityId, tenantId) {
  var tid = tenantId || pubGetTenantId();
  var eid = parseInt(entityId, 10) || pubGetActiveEntityId();
  if (!eid) return [];
  var key = pubEntityCartStorageKey(eid, tid);
  var cart = [];
  try { cart = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { cart = []; }
  if (!Array.isArray(cart) || !cart.length) {
    cart = pubMigrateLegacyCart(eid, tid);
  }
  return Array.isArray(cart) ? cart : [];
}

function pubSaveScopedCart(cart, entityId, tenantId) {
  var eid = parseInt(entityId, 10) || pubGetActiveEntityId();
  if (!eid) return;
  try {
    localStorage.setItem(pubEntityCartStorageKey(eid, tenantId), JSON.stringify(Array.isArray(cart) ? cart : []));
  } catch (e) {}
  pubSyncCartBadges();
}

function pubClearScopedCart(entityId, tenantId) {
  var eid = parseInt(entityId, 10) || pubGetActiveEntityId();
  if (!eid) return;
  try {
    localStorage.removeItem(pubEntityCartStorageKey(eid, tenantId));
  } catch (e) {}
  pubSyncCartBadges();
}

function pubScopedCartCount(entityId, tenantId) {
  return pubLoadScopedCart(entityId, tenantId).reduce(function (sum, item) {
    return sum + Math.max(1, parseInt(item.qty, 10) || 1);
  }, 0);
}

// CRITICAL FIX: Define pubSyncCartBadges BEFORE any usage
function pubSyncCartBadges() {
  var total = pubScopedCartCount();
  ['pubCartCount', 'pubCartCountSidebar', 'pubCartCountFooter'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = total;
    el.style.display = total ? 'inline-flex' : 'none';
  });
}

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Sidebar toggle
   *    Desktop : 3-state cycle:
   *              Full (0) → Collapsed/icons-only (1) → Hidden (2)
   *              state persisted in localStorage (pub_sidebar_state)
   *    Mobile  : slide-out overlay (sidebar.open + backdrop.open)
   * ----------------------------------------------------- */
  function initSidebar() {
    var toggle   = document.getElementById('pubHamburger');
    var sidebar  = document.getElementById('pubSidebar');
    var backdrop = document.getElementById('pubSidebarOverlay');
    var closeBtn = document.getElementById('pubSidebarClose');

    if (!toggle || !sidebar) return;

    if (toggle.dataset.bound) {
      var clean = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(clean, toggle);
      toggle = clean;
    }
    toggle.dataset.bound = 'js';

    var STORAGE_KEY = 'pub_sidebar_state';
    var MOBILE_BP   = 768;

    function isMobile() {
      return window.innerWidth <= MOBILE_BP;
    }

    function restoreDesktopState() {
      if (isMobile()) return;
      try {
        var state = localStorage.getItem(STORAGE_KEY);
        if (!state) state = '2';
        if (state === '1') {
          document.body.classList.add('pub-sidebar-collapsed');
          document.body.classList.remove('pub-sidebar-hidden');
        } else if (state === '2') {
          document.body.classList.add('pub-sidebar-hidden');
          document.body.classList.remove('pub-sidebar-collapsed');
        } else {
          document.body.classList.remove('pub-sidebar-collapsed');
          document.body.classList.remove('pub-sidebar-hidden');
        }
      } catch (e) {}
    }

    function toggleDesktop() {
      var body = document.body;
      if (body.classList.contains('pub-sidebar-hidden')) {
        body.classList.remove('pub-sidebar-hidden');
        body.classList.remove('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '0'); } catch (e) {}
      } else if (body.classList.contains('pub-sidebar-collapsed')) {
        body.classList.remove('pub-sidebar-collapsed');
        body.classList.add('pub-sidebar-hidden');
        try { localStorage.setItem(STORAGE_KEY, '2'); } catch (e) {}
      } else {
        body.classList.add('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
      }
    }

    function openMobile() {
      sidebar.classList.add('open');
      if (backdrop) backdrop.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function closeMobile() {
      sidebar.classList.remove('open');
      if (backdrop) backdrop.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function () {
      if (isMobile()) {
        sidebar.classList.contains('open') ? closeMobile() : openMobile();
      } else {
        toggleDesktop();
      }
    });

    if (backdrop) {
      backdrop.addEventListener('click', closeMobile);
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', closeMobile);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeMobile();
      }
    });

    window.addEventListener('resize', function () {
      if (!isMobile() && sidebar.classList.contains('open')) {
        closeMobile();
      }
    }, { passive: true });

    restoreDesktopState();

    var currentPath = window.location.pathname;
    var links = sidebar.querySelectorAll('.pub-sidebar-link');
    for (var i = 0; i < links.length; i++) {
      if (links[i].getAttribute('href') === currentPath) {
        links[i].classList.add('active');
        break;
      }
    }
  }

  /* -------------------------------------------------------
   * 2. Apply dynamic theme colors from #pubThemeData element
   * ----------------------------------------------------- */
  function applyTheme() {
    var themeEl = document.getElementById('pubThemeData');
    if (!themeEl) return;

    var raw = themeEl.textContent || themeEl.innerText || '';
    if (!raw.trim()) return;

    var theme;
    try { theme = JSON.parse(raw); } catch (e) { return; }

    var root = document.documentElement;
    var map = {
      primary:            '--pub-primary',
      secondary:          '--pub-secondary',
      accent:             '--pub-accent',
      background:         '--pub-bg',
      surface:            '--pub-surface',
      text:               '--pub-text',
      header_bg:          '--pub-header-bg',
      header_text_color:  '--pub-header-text',
      footer_bg:          '--pub-footer-bg',
      footer_text_color:  '--pub-footer-text',
    };

    Object.keys(map).forEach(function (key) {
      if (theme[key]) root.style.setProperty(map[key], theme[key]);
    });
  }

  /* -------------------------------------------------------
   * 3. Mark active nav link based on current path
   * ----------------------------------------------------- */
  function markActiveNav() {
    var path = window.location.pathname;
    document.querySelectorAll('.pub-sidebar-link').forEach(function (a) {
      if (a.getAttribute('href') && path.indexOf(a.getAttribute('href')) !== -1) {
        a.classList.add('active');
      }
    });
  }

  /* -------------------------------------------------------
   * 4. Lazy-load images with data-src attribute
   * ----------------------------------------------------- */
  function lazyLoadImages() {
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('img[data-src]').forEach(function (img) {
        img.src = img.dataset.src;
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });

    document.querySelectorAll('img[data-src]').forEach(function (img) {
      observer.observe(img);
    });
  }

  /* -------------------------------------------------------
   * 5. Dynamic Backgrounds
   * ----------------------------------------------------- */

  function initDynamicBackgrounds() {
    document.querySelectorAll('[data-banner-bg]').forEach(function (el) {
      var color = el.dataset.bannerBg || '';
      if (/^(#[0-9a-f]{3,8}|rgba?\([\d\s%,.\/-]+\)|hsla?\([\d\s%,.\/-]+\))$/i.test(color)) {
        el.style.background = color;
      }
    });
  }

  /* -------------------------------------------------------
   * 6. Banner / Slider carousel auto-advance
   * ----------------------------------------------------- */
  function initSliders() {
    var sliders = document.querySelectorAll('.pub-banner-slider');
    sliders.forEach(function (slider) {
      var slides = slider.querySelectorAll('.pub-banner-slide');
      if (slides.length <= 1) return;

      var current = 0;
      var isRtl   = document.documentElement.dir === 'rtl';

      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[0].classList.add('active');

      var prevBtn = document.createElement('button');
      prevBtn.className = 'pub-slider-btn pub-slider-btn--prev';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.textContent = isRtl ? '\u203a' : '\u2039';
      slider.appendChild(prevBtn);

      var nextBtn = document.createElement('button');
      nextBtn.className = 'pub-slider-btn pub-slider-btn--next';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.textContent = isRtl ? '\u2039' : '\u203a';
      slider.appendChild(nextBtn);

      var dotsWrap = document.createElement('div');
      dotsWrap.className = 'pub-slider-dots';
      slides.forEach(function (_, i) {
        var dot = document.createElement('button');
        dot.className = 'pub-slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        dot.addEventListener('click', function () { goTo(i); resetTimer(); });
        dotsWrap.appendChild(dot);
      });
      slider.appendChild(dotsWrap);

      function goTo(idx) {
        slides[current].classList.remove('active');
        dotsWrap.children[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dotsWrap.children[current].classList.add('active');
      }

      var timer;
      function resetTimer() {
        clearInterval(timer);
        timer = setInterval(function () { goTo(current + 1); }, 5000);
      }

      prevBtn.addEventListener('click', function () { goTo(current - 1); resetTimer(); });
      nextBtn.addEventListener('click', function () { goTo(current + 1); resetTimer(); });

      resetTimer();

      slider.addEventListener('mouseenter', function () { clearInterval(timer); });
      slider.addEventListener('mouseleave', resetTimer);
    });
  }

  /* -------------------------------------------------------
   * 7. Animate stat counters (.pub-stat-value[data-target])
   * ----------------------------------------------------- */
  function animateCounters() {
    var counters = document.querySelectorAll('.pub-stat-value[data-target]');
    if (!counters.length || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el     = entry.target;
        var target = parseInt(el.dataset.target, 10);
        var start  = performance.now();
        observer.unobserve(el);

        function step(now) {
          var progress = Math.min((now - start) / 800, 1);
          el.textContent = Math.floor(progress * target).toLocaleString();
          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            el.textContent = target.toLocaleString() + '+';
          }
        }
        requestAnimationFrame(step);
      });
    });

    counters.forEach(function (c) { observer.observe(c); });
  }

  /* -------------------------------------------------------
   * 8. Filter selects - auto-submit on change
   * ----------------------------------------------------- */
  function initFilterSelects() {
    document.querySelectorAll('.pub-filter-select[data-auto-submit]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var form = sel.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* -------------------------------------------------------
   * 9. Back-to-top button
   * ----------------------------------------------------- */
  function initBackToTop() {
    var btn = document.getElementById('pubBackToTop');
    if (!btn) return;

    // Use a 400px sentinel at the top to track scroll depth without scroll events
    var sentinel = document.createElement('div');
    sentinel.style.position = 'absolute';
    sentinel.style.top = '0';
    sentinel.style.width = '1px';
    sentinel.style.height = '400px';
    sentinel.style.pointerEvents = 'none';
    sentinel.style.visibility = 'hidden';
    document.body.prepend(sentinel);

    var observer = new IntersectionObserver(function(entries) {
      if (!entries[0].isIntersecting) {
        btn.style.display = 'flex';
      } else {
        btn.style.display = 'none';
      }
    });
    
    observer.observe(sentinel);

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* -------------------------------------------------------
   * 10. Cart badge - update sidebar badge from localStorage
   * ----------------------------------------------------- */
  function initCartBadge() {
    pubSyncCartBadges();
  }

  /* -------------------------------------------------------
   * 11. Entity context
   * ----------------------------------------------------- */
  function initEntityContext() {
    var strip      = document.getElementById('pubEntityStrip');
    var nameEl     = document.getElementById('pubEntityStripName');
    var metaEl     = document.getElementById('pubEntityStripMeta');
    var modal      = document.getElementById('pubEntityModal');
    var modalClose = document.getElementById('pubEntityModalClose');
    var modalStatus = document.getElementById('pubEntityModalStatus');
    var list       = document.getElementById('pubEntityList');
    var strings    = window.pubEntityStrings || {};
    var tenantId   = pubGetTenantId();
    var geoKey     = 'pub_entity_geo_ts:' + String(tenantId);

    if (!strip || !nameEl || !modal || !list) return;

    function text(key, fallback) {
      return strings[key] || fallback;
    }

    function entityMeta(entity) {
      if (!entity) return '';
      var bits = [];
      if (entity.distance_km !== null && entity.distance_km !== undefined && entity.distance_km !== '') {
        bits.push(parseFloat(entity.distance_km).toFixed(1) + ' km');
      }
      if (entity.hours_known) {
        bits.push(entity.is_open_now ? text('branch_open', 'Open now') : text('branch_closed', 'Closed now'));
      }
      if (entity.has_delivery_hint) {
        bits.push(text('delivery_hint', 'Delivery available'));
      } else if ((parseInt(entity.pickup_points_count, 10) || 0) > 0) {
        bits.push(text('pickup_only', 'Pickup available'));
      }
      return bits.join(' | ');
    }

    function setModalStatus(message) {
      if (!modalStatus) return;
      modalStatus.textContent = message || text('switching_notice', 'Switching branch shows that branch cart and delivery options.');
    }

    function renderStrip(entity) {
      if (!entity || !parseInt(entity.id, 10)) return;
      pubSetClientActiveEntity(entity);
      nameEl.textContent = entity.name || text('select_branch', 'Select branch');
      if (metaEl) metaEl.textContent = entityMeta(entity);
      pubSyncCartBadges();
    }

    function requestJson(url, options) {
      var opts = options || {};
      opts.credentials = 'include';
      opts.headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
      return fetch(url, opts).then(function (response) {
        return response.ok ? response.json() : null;
      });
    }

    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    function renderCandidates(candidates) {
      var activeId = pubGetActiveEntityId();
      list.textContent = '';

      if (!Array.isArray(candidates) || !candidates.length) {
        setModalStatus(text('location_required', 'We could not detect a nearby branch. Choose one manually.'));
        var empty = document.createElement('div');
        empty.className = 'pub-entity-option is-unavailable';
        var emptyBody = document.createElement('div');
        emptyBody.className = 'pub-entity-option__body';
        var emptyName = document.createElement('div');
        emptyName.className = 'pub-entity-option__name';
        emptyName.textContent = text('location_required', 'We could not detect a nearby branch. Choose one manually.');
        emptyBody.appendChild(emptyName);
        empty.appendChild(emptyBody);
        list.appendChild(empty);
        return;
      }

      candidates.forEach(function (entity) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pub-entity-option'
          + ((parseInt(entity.id, 10) === activeId) ? ' is-active' : '')
          + (!entity.is_available ? ' is-unavailable' : '');

        var body = document.createElement('div');
        body.className = 'pub-entity-option__body';
        var name = document.createElement('div');
        name.className = 'pub-entity-option__name';
        name.textContent = entity.name || text('select_branch', 'Select branch');
        var addr = document.createElement('p');
        addr.className = 'pub-entity-option__addr';
        addr.textContent = [entity.address_line1 || '', entity.address_line2 || ''].filter(Boolean).join(' | ');
        var meta = document.createElement('div');
        meta.className = 'pub-entity-option__meta';

        function addChip(label) {
          var chip = document.createElement('span');
          chip.className = 'pub-entity-chip';
          chip.textContent = label;
          meta.appendChild(chip);
        }

        if (entity.hours_known) {
          addChip(entity.is_open_now ? text('branch_open', 'Open now') : text('branch_closed', 'Closed now'));
        }
        if (entity.has_delivery_hint) {
          addChip(text('delivery_hint', 'Delivery available'));
        } else if ((parseInt(entity.pickup_points_count, 10) || 0) > 0) {
          addChip(text('pickup_only', 'Pickup available'));
        }
        if (parseInt(entity.id, 10) === activeId) {
          addChip(text('selected', 'Selected'));
        }

        body.appendChild(name);
        body.appendChild(addr);
        body.appendChild(meta);

        var distance = document.createElement('div');
        distance.className = 'pub-entity-option__distance';
        distance.textContent = entity.distance_km != null ? parseFloat(entity.distance_km).toFixed(1) + ' km' : '';
        button.appendChild(body);
        button.appendChild(distance);

        button.addEventListener('click', function () {
          setModalStatus(text('switching_notice', 'Switching branch shows that branch cart and delivery options.'));
          requestJson('/api/public/entity_context/select?tenant_id=' + tenantId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ entity_id: parseInt(entity.id, 10) || 0 })
          }).then(function (resp) {
            if (!resp || !resp.success || !resp.data || !resp.data.active_entity) return;
            renderStrip(resp.data.active_entity);
            closeModal();
            if (resp.data.changed) {
              window.location.reload();
            }
          }).catch(function () {});
        });

        list.appendChild(button);
      });
    }

    function fetchOptions(lat, lng) {
      setModalStatus(text('nearest_first', 'Nearest branches first'));
      var url = '/api/public/entity_context/options?tenant_id=' + tenantId;
      if (lat != null && lng != null) {
        url += '&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
      }
      requestJson(url).then(function (resp) {
        if (!resp || !resp.success || !resp.data) return;
        renderCandidates(resp.data.candidates || []);
      }).catch(function () {});
    }

    function openModal() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      fetchOptions();
    }

    function refreshCurrent() {
      requestJson('/api/public/entity_context/current?tenant_id=' + tenantId).then(function (resp) {
        if (!resp || !resp.success || !resp.data) return;
        if (resp.data.active_entity) renderStrip(resp.data.active_entity);
      }).catch(function () {});
    }

    function maybeAutoResolve() {
      var active = pubGetClientActiveEntity();
      var lastTs = 0;
      try { lastTs = parseInt(localStorage.getItem(geoKey) || '0', 10) || 0; } catch (e) {}

      if (!navigator.geolocation) return;
      if (active && parseInt(active.id, 10) > 0 && active.source === 'manual') return;
      if (lastTs && (Date.now() - lastTs) < (30 * 60 * 1000)) return;

      navigator.geolocation.getCurrentPosition(function (position) {
        var previousEntityId = pubGetActiveEntityId();
        try { localStorage.setItem(geoKey, String(Date.now())); } catch (e) {}
        requestJson('/api/public/entity_context/resolve?tenant_id=' + tenantId, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            lat: position.coords.latitude,
            lng: position.coords.longitude
          })
        }).then(function (resp) {
          if (!resp || !resp.success || !resp.data) return;
          if (resp.data.active_entity) {
            renderStrip(resp.data.active_entity);
            if (resp.data.changed && previousEntityId !== (parseInt(resp.data.active_entity.id, 10) || 0)) {
              window.location.reload();
              return;
            }
          }
          if (resp.data.requires_manual_selection) {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            setModalStatus(text('location_required', 'We could not detect a nearby branch. Choose one manually.'));
            renderCandidates(resp.data.candidates || []);
          }
        }).catch(function () {});
      }, function () {}, {
        enableHighAccuracy: true,
        timeout: 4500,
        maximumAge: 15 * 60 * 1000
      });
    }

    document.querySelectorAll('[data-entity-close="1"]').forEach(function (node) {
      node.addEventListener('click', closeModal);
    });
    if (modalClose) modalClose.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    window.pubOpenEntityModal = openModal;
    window.pubRefreshEntityContext = refreshCurrent;

    if (strip.dataset.mode !== 'discovery') {
      renderStrip(pubGetClientActiveEntity() || window.pubActiveEntity || null);
      refreshCurrent();
      maybeAutoResolve();
    }
  }

  function updateUserDisplay() {
    try {
      var u = null;
      var raw = localStorage.getItem('pubUser');
      if (raw) { try { u = JSON.parse(raw); } catch (e) {} }

      if (!u || !u.id) {
        u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser) ? window.pubSessionUser : null;
        if (u && u.id) {
          try { localStorage.setItem('pubUser', JSON.stringify(u)); } catch (e) {}
        }
      }

      if (!u || !u.id) return;

      var displayName = u.name || u.username || 'User';

      document.querySelectorAll('a.pub-login-btn').forEach(function (el) {
        if (el.href && el.href.indexOf('login.php') !== -1) {
          el.textContent = displayName;
          el.href = '/frontend/profile.php';
        }
      });
    } catch (e) {}
  }

  /* -------------------------------------------------------
   * 12. Notification bell
   * ----------------------------------------------------- */
  function initNotifBell() {
    var btn      = document.getElementById('pubNotifBtn');
    var dropdown = document.getElementById('pubNotifDropdown');
    var badge    = document.getElementById('pubNotifBadge');
    var list     = document.getElementById('pubNotifList');
    var markAll  = document.getElementById('pubNotifMarkAll');

    if (!btn || !dropdown) return;

    var notifications = [];
    var dataEl = document.getElementById('pubNotifData');
    if (dataEl) {
      try { notifications = JSON.parse(dataEl.textContent || '[]'); } catch (e) {}
    }
    if (!Array.isArray(notifications)) notifications = [];

    var seenKey = 'pub_notif_seen';
    var seenIds = [];
    try { seenIds = JSON.parse(localStorage.getItem(seenKey) || '[]'); } catch (e) {}
    if (!Array.isArray(seenIds)) seenIds = [];

    var unread = notifications.filter(function (n) {
      return !n.is_read && seenIds.indexOf(String(n.id)) === -1;
    }).length;

    function updateBadge(count) {
      if (!badge) return;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.toggle('visible', count > 0);
    }
    updateBadge(unread);

    function typeIcon(code) {
      var icons = {
        order: '📦', payment: '💳', shipment: '🚚', 'return': '↩️',
        review: '⭐', promotion: '🎉', system: '⚙️', entities: '🏢',
        support: '🆘', wallet: '💰', loyalty: '🏆',
        audit_completed: '✅', audit_rejected: '❌',
      };
      return icons[code] || '🔔';
    }

    function renderList() {
      if (!list) return;
      list.textContent = '';
      if (!notifications.length) {
        var empty = document.createElement('div');
        empty.className = 'pub-notif-empty';
        empty.textContent = window.pubTranslations && window.pubTranslations.common && window.pubTranslations.common.no_notifications
          ? window.pubTranslations.common.no_notifications
          : 'No notifications';
        list.appendChild(empty);
        return;
      }
      notifications.forEach(function (n) {
        var isSeen = n.is_read || seenIds.indexOf(String(n.id)) !== -1;
        var icon   = typeIcon(n.type_code || '');
        var time   = n.sent_at ? n.sent_at.replace('T', ' ').substring(0, 16) : '';
        var item = document.createElement('div');
        item.className = 'pub-notif-item' + (isSeen ? '' : ' unread');
        item.dataset.id = String(n.id || '');
        var iconEl = document.createElement('span');
        iconEl.className = 'pub-notif-icon';
        iconEl.textContent = icon;
        var body = document.createElement('div');
        body.className = 'pub-notif-body';
        var title = document.createElement('p');
        title.className = 'pub-notif-title';
        title.textContent = n.title || '';
        body.appendChild(title);
        if (n.message) {
          var msg = document.createElement('p');
          msg.className = 'pub-notif-msg';
          msg.textContent = n.message;
          body.appendChild(msg);
        }
        if (time) {
          var timeEl = document.createElement('div');
          timeEl.className = 'pub-notif-time';
          timeEl.textContent = time;
          body.appendChild(timeEl);
        }
        item.appendChild(iconEl);
        item.appendChild(body);
        list.appendChild(item);
      });

      list.querySelectorAll('.pub-notif-item').forEach(function (item) {
        item.addEventListener('click', function () {
          var id = String(item.dataset.id);
          if (seenIds.indexOf(id) === -1) {
            seenIds.push(id);
            try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
            item.classList.remove('unread');
            unread = Math.max(0, unread - 1);
            updateBadge(unread);
            fetch('/api/public/notifications/mark-read', {
              method: 'POST', credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ids: [parseInt(id, 10)] })
            }).catch(function () {});
          }
        });
      });
    }
    renderList();

    if (markAll) {
      markAll.addEventListener('click', function () {
        seenIds = notifications.map(function (n) { return String(n.id); });
        try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
        unread = 0;
        updateBadge(0);
        renderList();
        fetch('/api/public/notifications/mark-all-read', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' }
        }).catch(function () {});
      });
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // FIX: Polish Product Cards - safe version that doesn't break existing functionality
  function polishProductCards() {
    document.querySelectorAll('.pub-product-card').forEach(function (card) {
      // Add discount badge if price reflects it
      var priceEl = card.querySelector('.pub-product-price');
      if (priceEl && !card.querySelector('.pub-product-badge--discount')) {
        var oldPrice = card.querySelector('.pub-product-price-old');
        if (oldPrice) {
          var current = parseFloat(priceEl.textContent.replace(/[^\d.]/g, ''));
          var old = parseFloat(oldPrice.textContent.replace(/[^\d.]/g, ''));
          if (old > current && !isNaN(current) && !isNaN(old)) {
            var pct = Math.round(((old - current) / old) * 100);
            var badge = document.createElement('div');
            badge.className = 'pub-product-badge--discount';
            badge.textContent = '-' + pct + '%';
            var imgWrap = card.querySelector('.pub-card-img-wrap');
            if (imgWrap) imgWrap.appendChild(badge);
          }
        }
      }
      
      // Add cart icon if missing from button
      var btn = card.querySelector('.pub-btn--add-cart, .pub-card-action-cart');
      if (btn && !btn.querySelector('i')) {
        var icon = document.createElement('i');
        icon.className = 'bi bi-cart-plus pub-icon-cart';
        icon.setAttribute('aria-hidden', 'true');
        btn.prepend(icon);
      }
    });
  }

  // FIX: Fix Brand Strings - safe version that works correctly
  function fixBrandStrings() {
    var isRtl = document.body.dataset.dir === 'rtl';
    document.querySelectorAll('*').forEach(function(el) {
      if (el.children.length === 0 && el.textContent.includes('brands.featured')) {
        el.textContent = isRtl ? 'أبرز العلامات التجارية' : 'Featured Brands';
      }
    });
  }

  /* -------------------------------------------------------
   * 13. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initSidebar();
    markActiveNav();
    lazyLoadImages();
    initDynamicBackgrounds();
    initSliders();
    animateCounters();
    initFilterSelects();
    initBackToTop();
    initEntityContext();
    initCartBadge();
    updateUserDisplay();
    initNotifBell();
    polishProductCards();
    fixBrandStrings();

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/frontend/sw.js').catch(function () {});
    }
  });

})();


/* -------------------------------------------------------
 * Global comparison helpers
 * ----------------------------------------------------- */

function pubToggleCompare(btn) {
  var u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) ? window.pubSessionUser : null;
  if (!u || !u.id) {
    try { localStorage.removeItem('pubUser'); } catch (e) {}
    window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
  }

  var pid = btn.dataset.productId;
  if (!pid) return;

  var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
  var idx = inList.indexOf(String(pid));
  var action = (idx >= 0) ? 'remove' : 'add';

  if (action === 'add' && inList.length >= 4) {
    alert(window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_max
      ? window.pubTranslations.products.compare_max
      : 'Max 4 products can be compared.');
    return;
  }

  btn.disabled = true;

  var fd = new FormData();
  fd.append('product_id', pid);

  fetch('/api/public/compare/' + action, { method: 'POST', credentials: 'include', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success || data.ok) {
        if (action === 'remove') {
          inList.splice(idx, 1);
          btn.classList.remove('active');
          btn.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare)
            ? window.pubTranslations.products.compare : 'Compare';
        } else {
          inList.push(String(pid));
          btn.classList.add('active');
          btn.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_added)
            ? window.pubTranslations.products.compare_added : 'In Comparison';
        }
        localStorage.setItem('pub_compare', inList.join(','));
        pubUpdateCompareBadge();
      }
    })
    .catch(function () {})
    .finally(function () { btn.disabled = false; });
}

function pubUpdateCompareBadge() {
  var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
  var n = inList.length;

  var b1 = document.getElementById('pubCompareBadge');
  var b2 = document.getElementById('pubCompareBadgeSidebar');
  if (b1) { b1.textContent = n; b1.style.display = n > 0 ? 'inline-flex' : 'none'; }
  if (b2) { b2.textContent = n; b2.style.display = n > 0 ? 'inline-flex' : 'none'; }

  document.querySelectorAll('.pub-compare-toggle').forEach(function (el) {
    var pid = String(el.dataset.productId);
    if (inList.indexOf(pid) >= 0) {
      el.classList.add('active');
      el.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_added)
        ? window.pubTranslations.products.compare_added : 'In Comparison';
    } else {
      el.classList.remove('active');
      el.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare)
        ? window.pubTranslations.products.compare : 'Compare';
    }
  });
}

document.addEventListener('DOMContentLoaded', function () {
  pubUpdateCompareBadge();
});


/* -------------------------------------------------------
 * Global cart helpers
 * ----------------------------------------------------- */

function pubQtyChange(delta) {
  var inp = document.getElementById('pubQtyInput');
  if (!inp) return;
  var v = parseInt(inp.value, 10) || 1;
  v = Math.max(1, Math.min(parseInt(inp.max, 10) || 999, v + delta));
  inp.value = v;
}

function pubCartButtonLabel(btn, fallback) {
  var value = btn ? (btn.dataset.addedText || '') : '';
  value = value.replace(/^[^A-Za-z0-9\u0600-\u06FF]+/, '').trim();
  return value || fallback;
}

function pubSetCartButtonState(btn, state) {
  if (!btn) return;
  var label = state === 'added'
    ? pubCartButtonLabel(btn, (window.pubTranslations && window.pubTranslations.cart && window.pubTranslations.cart.added) ? window.pubTranslations.cart.added : 'Added!')
    : (btn.dataset.defaultText || ((window.pubTranslations && window.pubTranslations.cart && window.pubTranslations.cart.add) ? window.pubTranslations.cart.add : 'Add to Cart'));

  btn.textContent = '';
  var icon = document.createElement('i');
  icon.className = state === 'added' ? 'bi bi-check2-circle pub-cart-feedback-icon' : 'bi bi-cart-plus pub-cart-feedback-icon';
  icon.setAttribute('aria-hidden', 'true');
  var text = document.createElement('span');
  text.className = 'pub-cart-text';
  text.textContent = label;
  btn.appendChild(icon);
  btn.appendChild(text);
  btn.classList.toggle('pub-card-action-cart--added', state === 'added');
}

function pubFlyCartIcon(btn) {
  if (!btn || !btn.getBoundingClientRect) return;
  var rect = btn.getBoundingClientRect();
  var fly = document.createElement('span');
  fly.className = 'pub-cart-fly';
  fly.innerHTML = '<i class="bi bi-cart-plus" aria-hidden="true"></i>';
  fly.style.left = (rect.left + rect.width / 2) + 'px';
  fly.style.top = (rect.top + rect.height / 2) + 'px';
  document.body.appendChild(fly);
  window.setTimeout(function () {
    if (fly.parentNode) fly.parentNode.removeChild(fly);
  }, 900);
}

function pubAddToCart(btn) {
  var pubU = null;

  if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
    pubU = window.pubSessionUser;
    try { localStorage.setItem('pubUser', JSON.stringify(pubU)); } catch (e) {}
  }

  if (!pubU || !pubU.id) {
    try { localStorage.removeItem('pubUser'); } catch (e) {}
    window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
  }

  var qtyInput = document.getElementById('pubQtyInput');
  var qty   = Math.max(1, qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1);
  var id    = parseInt(btn.dataset.productId, 10);
  var name  = btn.dataset.productName  || '';
  var price = parseFloat(btn.dataset.productPrice) || 0;
  var sale  = parseFloat(btn.dataset.salePrice) || null;
  var img   = btn.dataset.productImage || '';
  var cur   = btn.dataset.currency     || '';
  var sku   = btn.dataset.productSku   || '';
  var eid   = parseInt(btn.dataset.entityId, 10) || pubGetActiveEntityId() || 0;

  if (!id) return;
  if (!eid) {
    eid = 1;
  }

  var tenantIdForCheck = pubGetTenantId();
  var cartKeyPrefix    = 'pub_cart_t' + String(tenantIdForCheck) + '_e';
  var conflictingEid   = 0;

  try {
    for (var i = 0; i < localStorage.length; i++) {
      var lsKey = localStorage.key(i);
      if (!lsKey || lsKey.indexOf(cartKeyPrefix) !== 0) continue;
      var otherEid = parseInt(lsKey.substring(cartKeyPrefix.length), 10) || 0;
      if (otherEid === eid || otherEid <= 0) continue;
      var otherCart = JSON.parse(localStorage.getItem(lsKey) || '[]');
      if (Array.isArray(otherCart) && otherCart.length > 0) {
        conflictingEid = otherEid;
        break;
      }
    }
  } catch (e) {}

  if (conflictingEid > 0) {
    var conflictModal = document.getElementById('pubCartConflictModal');
    if (conflictModal) {
      var previousFocus = document.activeElement;
      conflictModal.hidden = false;
      conflictModal.setAttribute('aria-hidden', 'false');
      window._pubPendingAdd = { btn: btn, eid: eid, otherEid: conflictingEid };

      var switchBtn = document.getElementById('pubCartConflictSwitch');
      var cancelBtn = document.getElementById('pubCartConflictCancel');
      var closeBtn  = document.getElementById('pubCartConflictCloseBtn');
      var backdrop  = document.getElementById('pubCartConflictCloseBackdrop');

      var _cleanup = function () {
        conflictModal.hidden = true;
        conflictModal.setAttribute('aria-hidden', 'true');
        delete window._pubPendingAdd;
        document.removeEventListener('keydown', _trapConflictModal);
        if (previousFocus && previousFocus.focus) previousFocus.focus();
      };

      var _trapConflictModal = function (event) {
        if (event.key === 'Escape') {
          _cleanup();
          return;
        }
        if (event.key !== 'Tab') return;
        var focusable = conflictModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) return;
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      };

      if (switchBtn) {
        switchBtn.onclick = null;
        switchBtn.addEventListener('click', function () {
          var p = window._pubPendingAdd;
          if (p) {
            pubClearScopedCart(p.otherEid, tenantIdForCheck);
            _cleanup();
            pubAddToCart(p.btn);
          }
        }, { once: true });
      }
      if (cancelBtn) cancelBtn.addEventListener('click', _cleanup, { once: true });
      if (closeBtn)  closeBtn.addEventListener('click', _cleanup, { once: true });
      if (backdrop)  backdrop.addEventListener('click', _cleanup, { once: true });
      document.addEventListener('keydown', _trapConflictModal);
      if (switchBtn) switchBtn.focus();

      return;
    }
  }

  var cart = pubLoadScopedCart(eid);
  if (!Array.isArray(cart)) cart = [];

  var found = false;
  cart.forEach(function (item) {
    if (parseInt(item.id, 10) === id) {
      item.qty        = (parseInt(item.qty, 10) || 0) + qty;
      item.name       = name  || item.name;
      item.image      = img   || item.image;
      item.price      = price;
      item.sale_price = (sale !== null && sale < price) ? sale : null;
      item.sku        = sku   || item.sku;
      found = true;
    }
  });

  if (!found) {
    cart.push({
      id:         id,
      name:       name,
      price:      price,
      sale_price: (sale !== null && sale < price ? sale : null),
      qty:        qty,
      image:      img,
      currency:   cur,
      sku:        sku,
      entity_id:  eid
    });
  }
  pubSaveScopedCart(cart, eid);

  if (typeof window.pubTrackEvent === 'function') {
    window.pubTrackEvent('product', id, 'add_to_cart', price || null);
  }

  var tenantId = pubGetTenantId();
  if (typeof fetch !== 'undefined') {
    fetch('/api/public/cart/add?tenant_id=' + tenantId + '&_t=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        product_id:    id,
        product_name:  name,
        sku:           sku,
        unit_price:    price,
        qty:           qty,
        entity_id:     eid,
        currency_code: cur
      })
    })
    .then(function (r) {
      if (r.status === 401 || r.status === 403) {
        try { localStorage.removeItem('pubUser'); } catch (e) {}
        window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
        throw new Error('AUTH_REDIRECT');
      }
      if (!r.ok) throw new Error('Server error: ' + r.status);
      return r.json();
    })
    .then(function (resp) {
      if (!resp || !resp.success) {
        console.error('Cart add failed:', resp);
      }
      pubSyncCartBadges();
      pubSetCartButtonState(btn, 'added');
      pubFlyCartIcon(btn);
      btn.disabled = true;
      setTimeout(function () {
        window.location.href = '/frontend/public/cart.php';
      }, 800);
    })
    .catch(function (err) {
      if (err && err.message === 'AUTH_REDIRECT') return;
      console.error('Cart sync failed:', err);
      setTimeout(function () { window.location.href = '/frontend/public/cart.php'; }, 1000);
    });
    return;
  }

  pubSyncCartBadges();
  pubSetCartButtonState(btn, 'added');
  pubFlyCartIcon(btn);
  btn.disabled = true;
  setTimeout(function () {
    window.location.href = '/frontend/public/cart.php';
  }, 1200);
}


/* -------------------------------------------------------
 * Wishlist helpers
 * ----------------------------------------------------- */

function pubToggleWishlist(btn) {
  var u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) ? window.pubSessionUser : null;
  if (!u || !u.id) {
    try { localStorage.removeItem('pubUser'); } catch (e) {}
    window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
  }
  var productId = btn.dataset.productId;
  if (!productId) return;

  var active = btn.classList.contains('pub-wishlist-active');
  var action = active ? 'remove' : 'add';
  btn.disabled = true;

  var fd = new FormData();
  fd.append('product_id', productId);
  fd.append('entity_id',  btn.dataset.entityId || '1');

  fetch('/api/public/wishlist/' + action, { method: 'POST', credentials: 'include', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success || data.ok) {
        if (active) {
          btn.classList.remove('pub-wishlist-active');
          btn.title       = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.add)
            ? window.pubTranslations.wishlist.add : 'Add to wishlist';
          btn.textContent = '\u2661';
        } else {
          btn.classList.add('pub-wishlist-active');
          btn.title       = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.added)
            ? window.pubTranslations.wishlist.added : 'In wishlist';
          btn.textContent = '\u2665';
          if (typeof window.pubTrackEvent === 'function') {
            window.pubTrackEvent('product', parseInt(productId, 10), 'favorite');
          }
        }
        pubRefreshWishlistBadge();
      }
    })
    .catch(function () {})
    .finally(function () { btn.disabled = false; });
}

function pubRefreshWishlistBadge() {
  fetch('/api/public/wishlist/ids', { credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var ids   = (data.data && data.data.ids) ? data.data.ids : [];
      var count = ids.length;

      var b1 = document.getElementById('pubWishlistCount');
      var b2 = document.getElementById('pubWishlistCountSidebar');
      if (b1) { b1.textContent = count; b1.style.display = count ? 'inline-flex' : 'none'; }
      if (b2) { b2.textContent = count; b2.style.display = count ? 'inline-flex' : 'none'; }

      document.querySelectorAll('.pub-wishlist-btn').forEach(function (btn) {
        if (ids.map(String).indexOf(String(btn.dataset.productId)) !== -1) {
          btn.classList.add('pub-wishlist-active');
          btn.textContent = '\u2665';
          btn.title = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.added)
            ? window.pubTranslations.wishlist.added : 'In wishlist';
        } else {
          btn.classList.remove('pub-wishlist-active');
          btn.textContent = '\u2661';
          btn.title = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.add)
            ? window.pubTranslations.wishlist.add : 'Add to wishlist';
        }
      });
    })
    .catch(function () {});
}

function pubCopyDeal(code, elId) {
  var el = elId ? document.getElementById(elId) : null;
  var original = el ? el.textContent.trim() : code;

  function flash() {
    if (!el) return;
    el.textContent = '\u2713';
    window.setTimeout(function () { el.textContent = original; }, 1800);
  }

  function fallbackCopy() {
    var ta = document.createElement('textarea');
    ta.value = code;
    ta.className = 'pub-copy-buffer';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    flash();
  }

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(code).then(flash).catch(fallbackCopy);
  } else {
    fallbackCopy();
  }
}

(function bindDelegatedPublicActions() {
  document.addEventListener('error', function (event) {
    var img = event.target;
    if (!img || !img.matches || !img.matches('img[data-fallback-image]')) return;
    img.hidden = true;
    var placeholder = img.nextElementSibling;
    if (placeholder && placeholder.classList.contains('pub-img-placeholder')) {
      placeholder.hidden = false;
    }
  }, true);

  document.addEventListener('click', function (event) {
    var copy = event.target.closest('[data-copy-code]');
    if (copy) {
      event.preventDefault();
      pubCopyDeal(copy.dataset.copyCode || '', copy.dataset.copyTarget || '');
      return;
    }

    var action = event.target.closest('[data-pub-action]');
    if (!action) return;
    if (action.dataset.pubAction === 'wishlist') {
      event.preventDefault();
      event.stopPropagation();
      pubToggleWishlist(action);
    } else if (action.dataset.pubAction === 'add-cart') {
      event.preventDefault();
      event.stopPropagation();
      
      action.classList.add('pub-cart-pop');
      setTimeout(function() { action.classList.remove('pub-cart-pop'); }, 400);

      pubAddToCart(action);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    var copy = event.target.closest('[data-copy-code]');
    if (!copy) return;
    event.preventDefault();
    pubCopyDeal(copy.dataset.copyCode || '', copy.dataset.copyTarget || '');
  });
}());

(function () {
  var u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) ? window.pubSessionUser : null;
  if (u && u.id && document.querySelector('.pub-wishlist-btn')) {
    pubRefreshWishlistBadge();
  }
}());