/**
 * public-header.js — Production v5.0
 * نظام sticky header واحد نظيف بدون تعارض
 */

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Category Bar — horizontal scroll with arrows
   * ----------------------------------------------------- */
  function initCatBar() {
    var bar    = document.getElementById('pubCatBarInner');
    var wrap   = bar && bar.closest('.pub-cat-bar__viewport');
    var parent = bar && bar.closest('.pub-cat-bar');
    var arrowS = document.getElementById('pubCatArrowStart');
    var arrowE = document.getElementById('pubCatArrowEnd');

    if (!bar || !wrap) return;

    function updateArrows() {
      if (!parent) return;
      var atStart = bar.scrollLeft <= 4;
      var atEnd   = bar.scrollLeft >= (bar.scrollWidth - bar.clientWidth - 4);
      parent.classList.toggle('pub-cat-bar--can-start', !atStart);
      parent.classList.toggle('pub-cat-bar--can-end',   !atEnd);
    }

    bar.addEventListener('scroll', updateArrows, { passive: true });
    updateArrows();

    if (arrowS) {
      arrowS.addEventListener('click', function () {
        bar.scrollBy({ left: -200, behavior: 'smooth' });
      });
    }
    if (arrowE) {
      arrowE.addEventListener('click', function () {
        bar.scrollBy({ left: 200, behavior: 'smooth' });
      });
    }
  }

  /* -------------------------------------------------------
   * 2. Category Slider Drawer
   * ----------------------------------------------------- */
  function initCatSlider() {
    var openBtn  = document.getElementById('pubCatSliderBtn');
    var closeBtn = document.getElementById('pubCatSliderClose');
    var slider   = document.getElementById('pubCatSlider');
    var backdrop = document.getElementById('pubCatSliderBackdrop');

    if (!slider) return;

    function openSlider() {
      slider.classList.add('active', 'open');
      slider.setAttribute('aria-hidden', 'false');
      if (backdrop) {
        backdrop.removeAttribute('hidden');
        backdrop.classList.add('open');
      }
      document.body.style.overflow = 'hidden';
      if (closeBtn) closeBtn.focus();
    }

    function closeSlider() {
      slider.classList.remove('active', 'open');
      slider.setAttribute('aria-hidden', 'true');
      if (backdrop) {
        backdrop.classList.remove('open');
        setTimeout(function () { backdrop.setAttribute('hidden', ''); }, 320);
      }
      document.body.style.overflow = '';
    }

    if (openBtn)  openBtn.addEventListener('click', openSlider);
    if (closeBtn) closeBtn.addEventListener('click', closeSlider);
    if (backdrop) backdrop.addEventListener('click', closeSlider);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && slider.classList.contains('open')) closeSlider();
    });
  }

  /* -------------------------------------------------------
   * 3. Mega Menu
   * ----------------------------------------------------- */
  function initMegaMenu() {
    var catBar  = document.getElementById('pubCatBar');
    var megaMenu = document.getElementById('pubMegaMenu');
    if (!catBar || !megaMenu) return;

    catBar.addEventListener('mouseleave', function () {
      megaMenu.classList.remove('open');
    });
  }

  /* -------------------------------------------------------
   * 4. Language Dropdown
   * ----------------------------------------------------- */
  function initLangDropdown() {
    var btn      = document.getElementById('pubLangBtn');
    var dropdown = document.getElementById('pubLangDropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.hasAttribute('hidden');
      if (isOpen) {
        dropdown.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
      } else {
        dropdown.setAttribute('hidden', '');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', function (e) {
      if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.setAttribute('hidden', '');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* -------------------------------------------------------
   * 5. Install Button (PWA)
   * ----------------------------------------------------- */
  function initInstallBtn() {
    var btn = document.getElementById('pubInstallBtn');
    if (!btn) return;

    var deferredPrompt = null;
    btn.style.display = 'none';

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      btn.style.display = '';
    });

    btn.addEventListener('click', function () {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function () {
          deferredPrompt = null;
          btn.style.display = 'none';
        });
      } else {
        var msg = document.body.dataset.installInstructions || '';
        if (msg) alert(msg);
      }
    });

    window.addEventListener('appinstalled', function () {
      btn.style.display = 'none';
      deferredPrompt = null;
    });
  }

  /* -------------------------------------------------------
   * 6. Search Suggest
   * ----------------------------------------------------- */
  function initSearchSuggest() {
    var input   = document.getElementById('pubGlobalSearchInput');
    var suggest = document.getElementById('pubSearchSuggest');
    var clear   = document.getElementById('pubSearchClear');

    if (!input || !suggest) return;

    var timer = null;
    var basePath = document.body.dataset.basePath || '/frontend/public';

    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (clear) clear.hidden = !q;
      clearTimeout(timer);
      if (q.length < 2) { suggest.hidden = true; suggest.textContent = ''; return; }

      timer = setTimeout(function () {
        var tid = document.body.dataset.tenantId || '1';
        var lang = document.body.dataset.lang || 'ar';
        fetch('/api/public/search/suggest?q=' + encodeURIComponent(q) + '&tenant_id=' + tid + '&lang=' + lang, {
          credentials: 'include'
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          suggest.textContent = '';
          var items = (data && data.data) ? data.data : [];
          if (!items.length) { suggest.hidden = true; return; }
          items.slice(0, 8).forEach(function (item) {
            var li = document.createElement('li');
            li.role = 'option';
            li.tabIndex = -1;
            li.style.cssText = 'padding:9px 14px;cursor:pointer;list-style:none;font-size:.88rem;';
            li.textContent = item.label || item.name || item.title || '';
            li.addEventListener('mousedown', function (e) {
              e.preventDefault();
              input.value = li.textContent;
              suggest.hidden = true;
              if (clear) clear.hidden = false;
              input.closest('form').submit();
            });
            li.addEventListener('mouseover', function () { li.style.background = 'var(--pub-surface,#f5f5f5)'; });
            li.addEventListener('mouseout',  function () { li.style.background = ''; });
            suggest.appendChild(li);
          });
          suggest.hidden = false;
        })
        .catch(function () { suggest.hidden = true; });
      }, 280);
    });

    if (clear) {
      clear.addEventListener('click', function () {
        input.value = '';
        clear.hidden = true;
        suggest.hidden = true;
        input.focus();
      });
    }

    document.addEventListener('click', function (e) {
      if (!input.contains(e.target) && !suggest.contains(e.target)) {
        suggest.hidden = true;
      }
    });
  }

  /* -------------------------------------------------------
   * 7. Load Categories in Cat Bar & Slider
   * ----------------------------------------------------- */
  function loadCategories() {
    var inner    = document.getElementById('pubCatBarInner');
    var sliderBody = document.getElementById('pubCatSliderBody');
    if (!inner) return;

    var tid      = document.body.dataset.tenantId || '1';
    var lang     = document.body.dataset.lang || 'ar';
    var basePath = document.body.dataset.basePath || '/frontend/public';

    fetch('/api/public/categories?tenant_id=' + tid + '&lang=' + lang + '&featured=1&per=20', {
      credentials: 'include'
    })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
      var cats = (data && data.data && data.data.data) ? data.data.data : (data && data.data ? data.data : []);
      if (!Array.isArray(cats) || !cats.length) { inner.textContent = ''; return; }

      // Cat Bar pills
      inner.textContent = '';
      cats.forEach(function (cat) {
        var a = document.createElement('a');
        a.href = basePath + '/categories.php?id=' + encodeURIComponent(cat.id || '');
        a.className = 'pub-cat-item';
        if (cat.image_url) {
          var img = document.createElement('img');
          img.src = cat.image_url;
          img.alt = cat.name || '';
          img.className = 'pub-cat-item__icon';
          img.onerror = function () { img.style.display = 'none'; };
          a.appendChild(img);
        }
        var span = document.createElement('span');
        span.textContent = cat.name || '';
        a.appendChild(span);
        inner.appendChild(a);
      });

      // Update arrows after loading
      var barScroll = inner;
      var parentBar = inner.closest('.pub-cat-bar');
      if (parentBar) {
        var atEnd = barScroll.scrollWidth > barScroll.clientWidth + 4;
        parentBar.classList.toggle('pub-cat-bar--can-end', atEnd);
      }

      // Slider body
      if (sliderBody) {
        sliderBody.textContent = '';
        cats.forEach(function (cat) {
          var item = document.createElement('div');
          item.className = 'pub-cat-slider__item';
          var link = document.createElement('a');
          link.href = basePath + '/categories.php?id=' + encodeURIComponent(cat.id || '');
          link.className = 'pub-cat-slider__link';

          if (cat.image_url) {
            var img = document.createElement('img');
            img.src = cat.image_url;
            img.alt = cat.name || '';
            img.className = 'pub-cat-slider__img';
            img.onerror = function () { img.style.display = 'none'; };
            link.appendChild(img);
          } else {
            var icon = document.createElement('span');
            icon.className = 'pub-cat-slider__icon';
            icon.textContent = '📂';
            link.appendChild(icon);
          }

          var name = document.createElement('span');
          name.className = 'pub-cat-slider__name';
          name.textContent = cat.name || '';
          link.appendChild(name);

          if (cat.products_count) {
            var count = document.createElement('span');
            count.className = 'pub-cat-slider__count';
            count.textContent = cat.products_count;
            link.appendChild(count);
          }

          item.appendChild(link);
          sliderBody.appendChild(item);
        });
      }
    })
    .catch(function () {
      inner.textContent = '';
    });
  }

  /* -------------------------------------------------------
   * 8. STICKY HEADER — top-based animation + hysteresis
   *    Animates `top` instead of `transform` to avoid
   *    sticky rendering conflicts. Measures header height
   *    dynamically for precise hide offset.
   * ----------------------------------------------------- */
  function initStickyHeader() {
    var header = document.querySelector('.pub-header');
    var catBar = document.getElementById('pubCatBar');
    if (!header) return;

    // — Measure header + cat-bar heights for precise offsets —
    var spacer = document.getElementById('pubHeaderSpacer');
    function measureHeight() {
      var hH = header.offsetHeight;
      var cH = catBar ? catBar.offsetHeight : 0;
      var totalH = hH + cH;

      // Header hide offset
      header.style.setProperty('--_pub-header-hide', '-' + (hH + 10) + 'px');
      document.documentElement.style.setProperty('--pub-header-h', hH + 'px');

      // Cat-bar: position below header + its own hide offset
      if (catBar) {
        catBar.style.setProperty('--_pub-catbar-hide', '-' + (totalH + 10) + 'px');
      }

      // Spacer accounts for both header + cat-bar
      document.documentElement.style.setProperty('--pub-total-header-h', totalH + 'px');
      if (spacer) spacer.style.height = totalH + 'px';
    }
    measureHeight();
    window.addEventListener('resize', measureHeight, { passive: true });

    var lastY = window.scrollY;
    var state = 'visible';
    var ticking = false;
    var accumDown = 0;
    var accumUp = 0;

    var TOP_ZONE = 80;
    var HIDE_DISTANCE = 60;
    var SHOW_DISTANCE = 30;

    function update() {
      var y = window.scrollY;
      var diff = y - lastY;

      // — Top zone: always visible —
      if (y <= TOP_ZONE) {
        accumDown = 0;
        accumUp = 0;
        if (state !== 'visible') {
          header.classList.remove('is-hidden');
          if (catBar) catBar.classList.remove('is-hidden');
          state = 'visible';
        }
        lastY = y;
        ticking = false;
        return;
      }

      // — Accumulate scroll distance per direction —
      if (diff > 0) {
        accumDown += diff;
        accumUp = 0;
      } else if (diff < 0) {
        accumUp += Math.abs(diff);
        accumDown = 0;
      }

      // — Hide after 60px sustained downward scroll —
      if (accumDown >= HIDE_DISTANCE && state !== 'hidden') {
        header.classList.add('is-hidden');
        if (catBar) catBar.classList.add('is-hidden');
        state = 'hidden';
        accumDown = 0;
      }

      // — Show after 30px sustained upward scroll —
      if (accumUp >= SHOW_DISTANCE && state !== 'visible') {
        header.classList.remove('is-hidden');
        if (catBar) catBar.classList.remove('is-hidden');
        state = 'visible';
        accumUp = 0;
      }

      lastY = y;
      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(update);
        ticking = true;
      }
    }, { passive: true });
  }

  /* -------------------------------------------------------
   * 9. DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    initStickyHeader();
    initCatBar();
    initCatSlider();
    initMegaMenu();
    initLangDropdown();
    initInstallBtn();
    initSearchSuggest();
    loadCategories();
  });

})();