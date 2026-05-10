(function () {
  'use strict';

  var body = document.body;
  var rawUser = body ? body.dataset.sessionUser : '';
  var user = null;
  try { user = rawUser ? JSON.parse(rawUser) : window.pubSessionUser; } catch (e) { user = window.pubSessionUser; }
  if (!user || !user.id) return;

  var STORAGE_KEY = 'qz_dev_reg';
  var ANON_KEY = 'qz_anon_token';
  var DAY_MS = 86400000;

  try {
    var lastReg = localStorage.getItem(STORAGE_KEY);
    if (lastReg && (Date.now() - parseInt(lastReg, 10)) < DAY_MS) return;

    var anon = localStorage.getItem(ANON_KEY);
    if (!anon) {
      anon = (window.crypto && crypto.randomUUID)
        ? crypto.randomUUID()
        : (Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2));
      localStorage.setItem(ANON_KEY, anon);
    }

    fetch('/api/public/user_devices', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ anonymous_token: anon })
    }).then(function (response) {
      if (response.ok) localStorage.setItem(STORAGE_KEY, String(Date.now()));
    }).catch(function () {});
  } catch (e) {}
})();
