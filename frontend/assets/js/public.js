/**
 * assets/js/public.js â€” Production v3.1
 * QOOQZ â€” Global Public Interface JS
 *
 * â”€ Fixes vs v3.0 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 *   FIX-3  Sidebar double-binding eliminated:
 *          cloneNode() replaces inline-script listener when
 *          button carries data-bound="1" (set by header.php).
 *          public.js then sets data-bound="js" as its own mark.
 *   FIX-4  Desktop collapse state correctly restored on load.
 *   FIX-5  Resize handler prevents stale mobile-open state.
 * â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 *
 * No external dependencies.
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
  // Respect server-injected context first
  var active = (typeof window.pubActiveEntity !== 'undefined' && window.pubActiveEntity) ? window.pubActiveEntity : null;

  // If server provided a valid entity ID (even in discovery mode), use it
  if (active && parseInt(active.id, 10) > 0) return active;

  // Fall back to localStorage for returning visitors
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

  /* Try old colon-format key (pub_cart:T:E) first */
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
  /* Remove stale colon-key even if empty */
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
   *              Full (0) â†’ Collapsed/icons-only (1) â†’ Hidden (2)
   *              state persisted in localStorage (pub_sidebar_state)
   *    Mobile  : slide-out overlay (sidebar.open + backdrop.open)
   * ----------------------------------------------------- */
  function initSidebar() {
    var toggle   = document.getElementById('pubHamburger');
    var sidebar  = document.getElementById('pubSidebar');
    var backdrop = document.getElementById('pubSidebarOverlay');
    var closeBtn = document.getElementById('pubSidebarClose');

    if (!toggle || !sidebar) return;

    // â”€â”€ FIX-3: Remove the inline fallback listener from header.php â”€â”€
    // header.php marks the button with data-bound="1".
    // We replace the node with a clean clone so the old addEventListener
    // (captured in the header.php inline <script>) is discarded entirely.
    // Then we add our own listener and mark the button as ours.
    if (toggle.dataset.bound) {
      var clean = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(clean, toggle);
      toggle = clean;
    }
    toggle.dataset.bound = 'js'; // mark as handled by this file

    var STORAGE_KEY = 'pub_sidebar_state'; // 0=full, 1=collapsed, 2=hidden
    var MOBILE_BP   = 768; // must match CSS @media breakpoint

    function isMobile() {
      return window.innerWidth <= MOBILE_BP;
    }

    // â”€â”€ Desktop: persist 3-state sidebar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function restoreDesktopState() {
      if (isMobile()) return;
      try {
        var state = localStorage.getItem(STORAGE_KEY);
        // Default to state 2 (hidden) if not set
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
        // Hidden â†’ Full
        body.classList.remove('pub-sidebar-hidden');
        body.classList.remove('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '0'); } catch (e) {}
      } else if (body.classList.contains('pub-sidebar-collapsed')) {
        // Collapsed â†’ Hidden
        body.classList.remove('pub-sidebar-collapsed');
        body.classList.add('pub-sidebar-hidden');
        try { localStorage.setItem(STORAGE_KEY, '2'); } catch (e) {}
      } else {
        // Full â†’ Collapsed
        body.classList.add('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
      }
    }

    // â”€â”€ Mobile: slide-out overlay â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Main toggle click â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    toggle.addEventListener('click', function () {
      if (isMobile()) {
        sidebar.classList.contains('open') ? closeMobile() : openMobile();
      } else {
        toggleDesktop();
      }
    });

    // Close on backdrop click (mobile)
    if (backdrop) {
      backdrop.addEventListener('click', closeMobile);
    }

    // Close button inside sidebar (mobile)
    if (closeBtn) {
      closeBtn.addEventListener('click', closeMobile);
    }

    // Escape key closes mobile sidebar
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeMobile();
      }
    });

    // â”€â”€ FIX-5: Resize â€” clean up stale mobile-open state â”€â”€
    window.addEventListener('resize', function () {
      if (!isMobile() && sidebar.classList.contains('open')) {
        closeMobile();
      }
    }, { passive: true });

    // Restore desktop collapsed state
    restoreDesktopState();

    // Highlight active sidebar link by current URL path
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
   * 5. Search form â€” auto-focus on desktop
   * ----------------------------------------------------- */
  function initSearch() {
    var form = document.getElementById('pubSearchForm');
    if (!form) return;
    var input = form.querySelector('.pub-search-input');
    if (!input) return;
    if (window.innerWidth >= 768) input.focus();
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

      // Activate first slide
      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[0].classList.add('active');

      // Prev button
      var prevBtn = document.createElement('button');
      prevBtn.className = 'pub-slider-btn pub-slider-btn--prev';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.innerHTML = isRtl ? '&#8250;' : '&#8249;';
      slider.appendChild(prevBtn);

      // Next button
      var nextBtn = document.createElement('button');
      nextBtn.className = 'pub-slider-btn pub-slider-btn--next';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.innerHTML = isRtl ? '&#8249;' : '&#8250;';
      slider.appendChild(nextBtn);

      // Dot indicators
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
   * 8. Filter selects â€” auto-submit on change
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

    window.addEventListener('scroll', function () {
      btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* -------------------------------------------------------
   * 10. Cart badge â€” update sidebar badge from localStorage
   * ----------------------------------------------------- */
  function initCartBadge() {
    var cart = (typeof pubLoadScopedCart === 'function') ? pubLoadScopedCart() : [];
    if (!Array.isArray(cart)) cart = [];
    var total = cart.reduce(function (s, i) {
      return s + (Math.max(1, parseInt(i.qty, 10) || 1));
    }, 0);
    
    var badge1 = document.getElementById('pubCartCountSidebar');
    if (badge1) { badge1.textContent = total; badge1.style.display = total ? 'inline-flex' : 'none'; }
    
    var badge2 = document.getElementById('pubCartCountFooter');
    if (badge2) { badge2.textContent = total; badge2.style.display = total ? 'inline-flex' : 'none'; }
  }

  /* -------------------------------------------------------
   * 11. User display â€” sync localStorage / PHP session user
   * ----------------------------------------------------- */
  function initEntityContext() {
    var strip = document.getElementById('pubEntityStrip');
    var nameEl = document.getElementById('pubEntityStripName');
    var metaEl = document.getElementById('pubEntityStripMeta');
    var modal = document.getElementById('pubEntityModal');
    var modalClose = document.getElementById('pubEntityModalClose');
    var modalStatus = document.getElementById('pubEntityModalStatus');
    var list = document.getElementById('pubEntityList');
    var strings = window.pubEntityStrings || {};
    var tenantId = pubGetTenantId();
    var geoKey = 'pub_entity_geo_ts:' + String(tenantId);

    if (!strip || !nameEl || !modal || !list) return;

    function text(key, fallback) {
      return strings[key] || fallback;
    }

    function esc(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
      list.innerHTML = '';

      if (!Array.isArray(candidates) || !candidates.length) {
        setModalStatus(text('location_required', 'We could not detect a nearby branch. Choose one manually.'));
        list.innerHTML = '<div class="pub-entity-option is-unavailable"><div class="pub-entity-option__body"><div class="pub-entity-option__name">'
          + esc(text('location_required', 'We could not detect a nearby branch. Choose one manually.'))
          + '</div></div></div>';
        return;
      }

      candidates.forEach(function (entity) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pub-entity-option'
          + ((parseInt(entity.id, 10) === activeId) ? ' is-active' : '')
          + (!entity.is_available ? ' is-unavailable' : '');

        var chips = [];
        if (entity.hours_known) {
          chips.push('<span class="pub-entity-chip">' + esc(entity.is_open_now ? text('branch_open', 'Open now') : text('branch_closed', 'Closed now')) + '</span>');
        }
        if (entity.has_delivery_hint) {
          chips.push('<span class="pub-entity-chip">' + esc(text('delivery_hint', 'Delivery available')) + '</span>');
        } else if ((parseInt(entity.pickup_points_count, 10) || 0) > 0) {
          chips.push('<span class="pub-entity-chip">' + esc(text('pickup_only', 'Pickup available')) + '</span>');
        }
        if (parseInt(entity.id, 10) === activeId) {
          chips.push('<span class="pub-entity-chip">' + esc(text('selected', 'Selected')) + '</span>');
        }

        button.innerHTML =
          '<div class="pub-entity-option__body">'
          + '<div class="pub-entity-option__name">' + esc(entity.name || text('select_branch', 'Select branch')) + '</div>'
          + '<p class="pub-entity-option__addr">' + esc([entity.address_line1 || '', entity.address_line2 || ''].filter(Boolean).join(' | ')) + '</p>'
          + '<div class="pub-entity-option__meta">' + chips.join('') + '</div>'
          + '</div>'
          + '<div class="pub-entity-option__distance">' + (entity.distance_km != null ? esc(parseFloat(entity.distance_km).toFixed(1) + ' km') : '') + '</div>';

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

    // Only run side-effects if NOT in discovery mode, 
    // or if specifically requested by context.
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

      // Fallback to PHP session user injected in <head>
      if (!u || !u.id) {
        u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser) ? window.pubSessionUser : null;
        if (u && u.id) {
          try { localStorage.setItem('pubUser', JSON.stringify(u)); } catch (e) {}
        }
      }

      if (!u || !u.id) return;

      var displayName = u.name || u.username || 'User';

      // Update header login links that still point to login.php
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
        order: 'ًں“¦', payment: 'ًں’³', shipment: 'ًںڑڑ', 'return': 'â†©ï¸ڈ',
        review: 'â­گ', promotion: 'ًںژ‰', system: 'âڑ™ï¸ڈ', entities: 'ًںڈ¢',
        support: 'ًں†ک', wallet: 'ًں’°', loyalty: 'ًںڈ…',
        audit_completed: 'âœ…', audit_rejected: 'â‌Œ',
      };
      return icons[code] || 'ًں””';
    }

    function renderList() {
      if (!list) return;
      if (!notifications.length) {
        list.innerHTML = '<div class="pub-notif-empty">'
          + (window.pubTranslations && window.pubTranslations.common && window.pubTranslations.common.no_notifications ? window.pubTranslations.common.no_notifications : 'No notifications')
          + '</div>';
        return;
      }
      list.innerHTML = notifications.map(function (n) {
        var isSeen = n.is_read || seenIds.indexOf(String(n.id)) !== -1;
        var icon   = typeIcon(n.type_code || '');
        var time   = n.sent_at ? n.sent_at.replace('T', ' ').substring(0, 16) : '';
        var title  = (n.title   || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var msg    = (n.message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<div class="pub-notif-item' + (isSeen ? '' : ' unread') + '" data-id="' + n.id + '">'
          + '<span class="pub-notif-icon">' + icon + '</span>'
          + '<div class="pub-notif-body">'
          + '<p class="pub-notif-title">' + title + '</p>'
          + (msg  ? '<p class="pub-notif-msg">'  + msg  + '</p>' : '')
          + (time ? '<div class="pub-notif-time">' + time + '</div>' : '')
          + '</div></div>';
      }).join('');

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

  /* -------------------------------------------------------
   * 13. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initSidebar();
    markActiveNav();
    lazyLoadImages();
    initSearch();
    initSliders();
    animateCounters();
    initFilterSelects();
    initBackToTop();
    initEntityContext();
    initCartBadge();
    updateUserDisplay();
    initNotifBell();

    // PWA service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/frontend/sw.js').catch(function () {});
    }
  });

})();


/* -------------------------------------------------------
 * Global comparison helpers
 * ----------------------------------------------------- */

/**
 * Toggle a product in/out of comparison list.
 * Max 4 products allowed. Strict login required.
 */
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
    alert(window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_max ? window.pubTranslations.products.compare_max : 'Max 4 products can be compared.');
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
          btn.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare) ? window.pubTranslations.products.compare : 'Compare';
        } else {
          inList.push(String(pid));
          btn.classList.add('active');
          btn.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_added) ? window.pubTranslations.products.compare_added : 'In Comparison';
        }
        localStorage.setItem('pub_compare', inList.join(','));
        pubUpdateCompareBadge();
      }
    })
    .catch(function () {})
    .finally(function () { btn.disabled = false; });
}

/**
 * Update comparison badge counts and button states.
 */
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
      el.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare_added) ? window.pubTranslations.products.compare_added : 'In Comparison';
    } else {
      el.classList.remove('active');
      el.title = (window.pubTranslations && window.pubTranslations.products && window.pubTranslations.products.compare) ? window.pubTranslations.products.compare : 'Compare';
    }
  });
}

// Auto-init comparison on page load
document.addEventListener('DOMContentLoaded', function() {
  pubUpdateCompareBadge();
});


/* -------------------------------------------------------
 * Global cart helpers (available on all pages, no module wrap)
 * ----------------------------------------------------- */

/**
 * Increment / decrement quantity in #pubQtyInput by delta.
 */
function pubQtyChange(delta) {
  var inp = document.getElementById('pubQtyInput');
  if (!inp) return;
  var v = parseInt(inp.value, 10) || 1;
  v = Math.max(1, Math.min(parseInt(inp.max, 10) || 999, v + delta));
  inp.value = v;
}

/**
 * Add a product to cart.
 * Saves to DB when logged in; always writes to localStorage as fallback.
 */
function pubAddToCart(btn) {
  // Require login - server session is authoritative
  var pubU = null;

  // 1. Check server-injected session user FIRST (authoritative source)
  if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
    pubU = window.pubSessionUser;
    try { localStorage.setItem('pubUser', JSON.stringify(pubU)); } catch (e) {}
  }

  // 2. If no server session, do NOT trust localStorage - it may be stale
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
    // Single-tenant fallback: use entity 1 if no entity is resolved
    // This prevents silent failures on marketplace/discovery pages
    eid = 1;
  }

  // ── Entity conflict check ──────────────────────────────
  var tenantIdForCheck = pubGetTenantId();
  var cartKeyPrefix = 'pub_cart_t' + String(tenantIdForCheck) + '_e';
  var oldColonPrefix = 'pub_cart:' + String(tenantIdForCheck) + ':';
  var conflictingEid = 0;

  try {
    // Clean up stale old-format colon keys first
    var keysToRemove = [];
    for (var j = 0; j < localStorage.length; j++) {
      var oldKey = localStorage.key(j);
      if (oldKey && oldKey.indexOf(oldColonPrefix) === 0) {
        keysToRemove.push(oldKey);
      }
    }
    for (var k = 0; k < keysToRemove.length; k++) {
      localStorage.removeItem(keysToRemove[k]);
    }

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
  } catch(e) {}

  if (conflictingEid > 0) {
      // PREMIUM: Open custom conflict modal instead of confirm()
      var conflictModal = document.getElementById('pubCartConflictModal');
      if (conflictModal) {
          conflictModal.hidden = false;
          // Store pending data to retry after clear
          window._pubPendingAdd = { btn: btn, eid: eid, otherEid: conflictingEid };
          
          // One-time setup for buttons inside modal
          var switchBtn = document.getElementById('pubCartConflictSwitch');
          var cancelBtn = document.getElementById('pubCartConflictCancel');
          var closeBtn  = document.getElementById('pubCartConflictCloseBtn');
          var backdrop  = document.getElementById('pubCartConflictCloseBackdrop');

          var _cleanup = function() {
              conflictModal.hidden = true;
              delete window._pubPendingAdd;
          };

          if (switchBtn) {
              switchBtn.onclick = function() {
                  var p = window._pubPendingAdd;
                  if (p) {
                      pubClearScopedCart(p.otherEid, tenantIdForCheck);
                      _cleanup();
                      pubAddToCart(p.btn); // retry with new context
                  }
              };
          }
          if (cancelBtn) cancelBtn.onclick = _cleanup;
          if (closeBtn)  closeBtn.onclick  = _cleanup;
          if (backdrop)  backdrop.onclick  = _cleanup;
          
          return; // Exit, wait for modal
      }
  }

  // 1. Update localStorage immediately
  var cart = pubLoadScopedCart(eid);
  if (!Array.isArray(cart)) cart = [];

  var found = false;
  cart.forEach(function (item) {
    if (parseInt(item.id, 10) === id) { 
      item.qty = (parseInt(item.qty, 10) || 0) + qty; 
      item.name = name || item.name;
      item.image = img || item.image;
      item.price = price;
      item.sale_price = (sale !== null && sale < price) ? sale : null;
      item.sku = sku || item.sku;
      found = true; 
    }
  });

  if (!found) {
    cart.push({ 
      id: id, 
      name: name, 
      price: price, 
      sale_price: (sale !== null && sale < price ? sale : null),
      qty: qty, 
      image: img, 
      currency: cur, 
      sku: sku, 
      entity_id: eid 
    });
  }
  pubSaveScopedCart(cart, eid);

  // Track event
  if (typeof window.pubTrackEvent === 'function') {
    window.pubTrackEvent('product', id, 'add_to_cart', price || null);
  }

  // 2. Sync to DB (fire-and-forget)
  var tenantId = pubGetTenantId();
  if (typeof fetch !== 'undefined') {
    fetch('/api/public/cart/add?tenant_id=' + tenantId + '&_t=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ product_id: id, product_name: name, sku: sku, unit_price: price, qty: qty, entity_id: eid, currency_code: cur })
    })
    .then(function(r) {
       // Handle auth failures - session expired
       if (r.status === 401 || r.status === 403) {
         try { localStorage.removeItem('pubUser'); } catch (e) {}
         window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
         throw new Error('AUTH_REDIRECT');
       }
       if (!r.ok) {
         throw new Error('Server error: ' + r.status);
       }
       return r.json();
    })
    .then(function(resp) {
       if (!resp || !resp.success) {
         console.error('Cart add failed:', resp);
       }
       pubSyncCartBadges();
       btn.textContent = (window.pubTranslations && window.pubTranslations.cart && window.pubTranslations.cart.added) ? window.pubTranslations.cart.added : '\u2705';
       btn.disabled = true;
       setTimeout(function () {
         window.location.href = '/frontend/public/cart.php';
       }, 800);
    })
    .catch(function (err) {
       if (err && err.message === 'AUTH_REDIRECT') return;
       console.error('Cart sync failed:', err);
       // Still redirect to cart since localStorage was already updated
       setTimeout(function() { window.location.href = '/frontend/public/cart.php'; }, 1000);
    });
    return; // Exit here, handled in .then()
  }

  // 3. Update all cart badges
  pubSyncCartBadges();
  var orig = btn.textContent;
  btn.textContent = (window.pubTranslations && window.pubTranslations.cart && window.pubTranslations.cart.added) ? window.pubTranslations.cart.added : 'âœ…';
  btn.disabled = true;
  setTimeout(function () {
    window.location.href = '/frontend/public/cart.php';
  }, 1200);
}


/* â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
 * Wishlist helpers
 * â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ */

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
          btn.title       = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.add) ? window.pubTranslations.wishlist.add : 'Add to wishlist';
          btn.textContent = 'â™،';
        } else {
          btn.classList.add('pub-wishlist-active');
          btn.title       = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.added) ? window.pubTranslations.wishlist.added : 'In wishlist';
          btn.textContent = 'â™¥';
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
          btn.textContent = 'â™¥';
          btn.title = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.added) ? window.pubTranslations.wishlist.added : 'In wishlist';
        } else {
          btn.classList.remove('pub-wishlist-active');
          btn.textContent = 'â™،';
          btn.title = (window.pubTranslations && window.pubTranslations.wishlist && window.pubTranslations.wishlist.add) ? window.pubTranslations.wishlist.add : 'Add to wishlist';
        }
      });
    })
    .catch(function () {});
}

// Auto-refresh wishlist badge on page load when user is logged in
(function () {
  var u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) ? window.pubSessionUser : null;
  if (u && u.id && document.querySelector('.pub-wishlist-btn')) {
    pubRefreshWishlistBadge();
  }
}());


