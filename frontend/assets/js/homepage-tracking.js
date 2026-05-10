(function () {
  'use strict';

  function initAdSliders() {
    document.querySelectorAll('.ads-hero-slider').forEach(function (slider) {
      if (slider.dataset.sliderReady === '1') return;
      slider.dataset.sliderReady = '1';
      var slides = Array.prototype.slice.call(slider.querySelectorAll('.ads-slide'));
      if (slides.length <= 1) return;
      var controls = slider.querySelector('.ads-slider-controls')
        || (slider.nextElementSibling && slider.nextElementSibling.classList.contains('ads-slider-controls') ? slider.nextElementSibling : null);
      var dotsContainer = controls ? controls.querySelector('.ads-dots') : null;
      var prevBtn = controls ? controls.querySelector('.ads-prev') : null;
      var nextBtn = controls ? controls.querySelector('.ads-next') : null;
      var dots = [];
      var current = 0;
      var timer = null;

      function goTo(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach(function (slide, i) { slide.classList.toggle('active', i === current); });
        dots.forEach(function (dot, i) {
          dot.classList.toggle('active', i === current);
          dot.setAttribute('aria-current', i === current ? 'true' : 'false');
        });
      }

      function start() {
        clearInterval(timer);
        timer = setInterval(function () { goTo(current + 1); }, 4500);
      }

      if (dotsContainer) {
        dotsContainer.textContent = '';
        slides.forEach(function (_, i) {
          var dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'ads-dot';
          dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
          dot.addEventListener('click', function () { goTo(i); start(); });
          dotsContainer.appendChild(dot);
          dots.push(dot);
        });
      }
      if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); start(); });
      if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); start(); });
      slider.addEventListener('mouseenter', function () { clearInterval(timer); });
      slider.addEventListener('mouseleave', start);
      goTo(0);
      start();
    });
  }

  function dailyStore(prefix) {
    var key = prefix + new Date().toISOString().slice(0, 10);
    var cache = null;

    function read() {
      if (cache !== null) return cache;
      try {
        cache = JSON.parse(localStorage.getItem(key) || '{}') || {};
      } catch (e) {
        cache = {};
      }
      try {
        for (var i = localStorage.length - 1; i >= 0; i--) {
          var k = localStorage.key(i);
          if (k && k.indexOf(prefix) === 0 && k !== key) localStorage.removeItem(k);
        }
      } catch (e) {}
      return cache;
    }

    return {
      has: function (id) { return !!read()[id]; },
      mark: function (id) {
        var data = read();
        data[id] = 1;
        try { localStorage.setItem(key, JSON.stringify(data)); } catch (e) {}
      },
      unmark: function (id) {
        var data = read();
        delete data[id];
        try { localStorage.setItem(key, JSON.stringify(data)); } catch (e) {}
      }
    };
  }

  var adStore = dailyStore('qz_ad_track_');
  var eventStore = dailyStore('qz_ce_');
  var adOptions = { method: 'POST', keepalive: true, credentials: 'include' };

  function postAd(adId, eventType) {
    return fetch('/api/public/ads/' + encodeURIComponent(adId) + '/' + eventType, adOptions);
  }

  function recordAd(adId, eventType) {
    if (!adId || adId === '0') return;
    var key = eventType.charAt(0) + adId;
    if (adStore.has(key)) return;
    adStore.mark(key);
    postAd(adId, eventType).catch(function () { adStore.unmark(key); });
  }

  window.__qzAdClick = function (adId) {
    recordAd(adId, 'view');
    recordAd(adId, 'click');
  };

  window.pubTrackEvent = function (entityType, entityId, eventType, value, onFail) {
    if (!entityType || !entityId || !eventType) return;
    var params = new URLSearchParams();
    params.set('entity_type', entityType);
    params.set('entity_id', entityId);
    params.set('event_type', eventType);
    if (value !== undefined && value !== null) params.set('value', value);

    fetch('/api/public/events', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString(),
      keepalive: true,
      credentials: 'include'
    }).then(function (response) {
      return response.json();
    }).then(function (json) {
      if (!(json && json.data && json.data.ok) && typeof onFail === 'function') onFail();
    }).catch(function () {
      if (typeof onFail === 'function') onFail();
    });
  };

  document.addEventListener('click', function (event) {
    var ad = event.target.closest('[data-ad-id]');
    if (ad) window.__qzAdClick(ad.dataset.adId);

    var tracked = event.target.closest('[data-track-type][data-track-id]');
    if (!tracked) return;
    var type = tracked.dataset.trackType;
    var id = tracked.dataset.trackId;
    var key = 'c_' + type + '_' + id;
    if (!type || !id || id === '0' || eventStore.has(key)) return;
    eventStore.mark(key);
    window.pubTrackEvent(type, parseInt(id, 10), 'click', undefined, function () {
      eventStore.unmark(key);
    });
  }, true);

  initAdSliders();

  if (!('IntersectionObserver' in window)) return;

  var timers = {};
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      var el = entry.target;
      var adId = el.dataset.adId;
      var trackType = el.dataset.trackType;
      var trackId = el.dataset.trackId;
      var key = adId ? 'ad_v_' + adId : 'v_' + trackType + '_' + trackId;

      if (entry.isIntersecting) {
        if (timers[key]) return;
        timers[key] = setTimeout(function () {
          if (adId) {
            recordAd(adId, 'view');
          } else if (trackType && trackId && trackId !== '0') {
            var eventKey = 'v_' + trackType + '_' + trackId;
            if (!eventStore.has(eventKey)) {
              eventStore.mark(eventKey);
              window.pubTrackEvent(trackType, parseInt(trackId, 10), 'view', undefined, function () {
                eventStore.unmark(eventKey);
              });
            }
          }
          delete timers[key];
        }, 1000);
      } else {
        clearTimeout(timers[key]);
        delete timers[key];
      }
    });
  }, { threshold: 0.5 });

  function observe(root) {
    if (root.matches && root.matches('[data-ad-id], [data-track-type][data-track-id]')) {
      observer.observe(root);
    }
    root.querySelectorAll('[data-ad-id], [data-track-type][data-track-id]').forEach(function (el) {
      observer.observe(el);
    });
  }

  observe(document);

  if ('MutationObserver' in window) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) observe(node);
          if (node.nodeType === 1) initAdSliders();
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
