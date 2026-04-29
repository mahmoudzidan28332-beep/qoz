<?php
/**
 * admin/includes/footer.php
 * Dynamic footer with DB-driven theme/colors consistent with header.php
 */
declare(strict_types=1);

// If API/XHR/JSON request, do not output footer
$uri = $_SERVER['REQUEST_URI'] ?? '';
$xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
if ($xhr || $acceptJson || strpos((string)$uri, '/api/') === 0) {
    return;
}

// ════════════════════════════════════════════════════════════
// USE THE SAME PAYLOAD AS HEADER.PHP
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$theme   = $payload['theme']     ?? [];
$user    = $payload['user']      ?? [];
$lang    = $payload['lang']      ?? 'en';
$dir     = $payload['direction'] ?? 'ltr';

// ════════════════════════════════════════════════════════════
// EXTRACT COLORS FROM THEME (SAME AS HEADER.PHP)
// ════════════════════════════════════════════════════════════
$colors = [];
foreach ($theme['color_settings'] ?? [] as $c) {
    if (!empty($c['color_value'])) {
        $colors[$c['setting_key']] = $c['color_value'];
    }
}

$footerText = '© ' . date('Y') . ' Admin Panel';
foreach ($theme['design_settings'] ?? [] as $d) {
    if (($d['setting_key'] ?? '') === 'footer_text') {
        $footerText = $d['setting_value'] ?? $footerText;
        break;
    }
}

$brand = $payload['strings']['brand'] ?? 'Admin';

// ════════════════════════════════════════════════════════════
// FCM: قراءة إعدادات Firebase من config أو DB
// ════════════════════════════════════════════════════════════
$fcmEnabled = defined('FCM_ENABLED') ? FCM_ENABLED : false;

// إعدادات Firebase — تُقرأ من constants/config أو DB
$fcmConfig = [];
if ($fcmEnabled) {
    $fcmConfig = [
        'apiKey'            => defined('FCM_API_KEY')             ? FCM_API_KEY             : '',
        'authDomain'        => defined('FCM_AUTH_DOMAIN')         ? FCM_AUTH_DOMAIN         : '',
        'projectId'         => defined('FCM_PROJECT_ID')          ? FCM_PROJECT_ID          : '',
        'messagingSenderId' => defined('FCM_MESSAGING_SENDER_ID') ? FCM_MESSAGING_SENDER_ID : '',
        'appId'             => defined('FCM_APP_ID')              ? FCM_APP_ID              : '',
        'vapidKey'          => defined('FCM_VAPID_KEY')           ? FCM_VAPID_KEY           : '',
    ];

    // بديل: جلب من DB إذا كانت مخزّنة في جدول settings
    // foreach ($theme['fcm_settings'] ?? [] as $s) {
    //     $fcmConfig[$s['key']] = $s['value'];
    // }
}

// هل المستخدم مسجّل دخوله ومعرّفه متاح؟
$currentUserId = $user['id'] ?? 0;
$apiBase = defined('API_BASE_URL') ? API_BASE_URL : '/api';
$csrfToken = $payload['csrf'] ?? ($_SESSION['csrf_token'] ?? '');
?>
    </main><!-- #adminMainContent -->
  </div><!-- .admin-layout -->

  <footer class="admin-footer" role="contentinfo">
    <div class="container">
      <small data-i18n="footer.copyright"><?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?></small>
    </div>
  </footer>

<style>
:root {
    <?php foreach ($colors as $key => $value):
        $hkey = str_replace('_', '-', $key);
    ?>
    --<?= htmlspecialchars($key) ?>: <?= htmlspecialchars($value) ?>;
    <?php if ($hkey !== $key): ?>--<?= htmlspecialchars($hkey) ?>: <?= htmlspecialchars($value) ?>;
    <?php endif; ?>
    <?php endforeach; ?>
}

.admin-footer {
    background: var(--footer_background, var(--background_secondary, #1e2533)) !important;
    color: var(--footer_text, var(--text_secondary, #B0B0B0)) !important;
    border-top: 1px solid var(--border_color);
    padding: 1rem 0;
    margin-top: auto;
}

.admin-footer .container {
    display: flex;
    justify-content: center;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.admin-footer small {
    font-size: 0.875rem;
    opacity: 0.9;
}
</style>

<script>
(function(){
  'use strict';

  window.Admin = window.Admin || {};

  // ── Sidebar toggle ────────────────────────────────────────────────
  (function(){
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('adminSidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar) return;

    const stateKey = 'admin_sidebar_collapsed';
    try {
      if (localStorage.getItem(stateKey) === '1') {
        document.body.classList.add('sidebar-collapsed');
      }
    } catch(e){}

    function setCollapsed(val) {
      document.body.classList.toggle('sidebar-collapsed', val);
      try { localStorage.setItem(stateKey, val ? '1' : '0'); } catch(e){}
    }

    toggle.addEventListener('click', function(e){
      e.preventDefault();
      setCollapsed(document.body.classList.toggle('sidebar-collapsed'));
    });

    if (backdrop) {
      backdrop.addEventListener('click', function(){
        document.body.classList.remove('sidebar-open');
      });
    }
  })();

  // ── fetchAndInsert fallback ───────────────────────────────────────
  if (!window.Admin.__installed) {
    window.Admin.fetchAndInsert = function(url, targetSelector) {
      const target = document.querySelector(targetSelector);
      if (!target) return Promise.reject(new Error('Target not found'));
      return fetch(url, { credentials: 'same-origin' })
        .then(res => res.ok ? res.text() : Promise.reject(new Error('HTTP ' + res.status)))
        .then(html => { target.innerHTML = html; return html; });
    };
  }

  // ── AJAX helper ───────────────────────────────────────────────────
  window.Admin.ajax = function(url, opts = {}) {
    opts = Object.assign({ method: 'GET', headers: {}, credentials: 'same-origin' }, opts);
    return fetch(url, opts)
      .then(res => res.headers.get('content-type').includes('json')
        ? res.json() : res.text());
  };

  // ── Apply theme from DB ───────────────────────────────────────────
  window.Admin.applyTheme = function(theme) {
    if (!theme || !Array.isArray(theme.colors)) return;
    const root = document.documentElement;
    theme.colors.forEach(c => {
      if (c.setting_key && c.color_value) {
        root.style.setProperty('--' + c.setting_key, c.color_value);
        const hk = c.setting_key.replace(/_/g, '-');
        if (hk !== c.setting_key) root.style.setProperty('--' + hk, c.color_value);
      }
    });
  };

  <?php if (!empty($theme)): ?>
  window.Admin.applyTheme(<?= json_encode(['colors' => $theme['color_settings'] ?? []]) ?>);
  <?php endif; ?>

  // ── Notify ────────────────────────────────────────────────────────
  window.Admin.notify = function(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:10px;border-radius:5px;z-index:10000;';
    const rootStyles = getComputedStyle(document.documentElement);
    const colorMap = {
      info: '--info_color', success: '--success_color',
      warning: '--warning_color', error: '--danger_color'
    };
    const bg = rootStyles.getPropertyValue(colorMap[type] || '--primary_color').trim();
    if (bg) toast.style.background = bg;
    const fg = rootStyles.getPropertyValue('--sidebar_text').trim();
    toast.style.color = fg || 'inherit';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  };

  // ── data-load-url links ───────────────────────────────────────────
  document.addEventListener('click', function(e){
    const a = e.target.closest('a[data-load-url]');
    if (!a) return;
    e.preventDefault();
    window.Admin.fetchAndInsert(
      a.getAttribute('data-load-url'),
      a.getAttribute('data-target') || '#adminMainContent'
    ).catch(() => window.Admin.notify('Failed to load', 'error'));
  });

  // ── APP_CONFIG: إضافة FCM keys للـ window ────────────────────────
  window.APP_CONFIG = window.APP_CONFIG || {};
  <?php if ($fcmEnabled && !empty($fcmConfig['projectId'])): ?>
  Object.assign(window.APP_CONFIG, {
    FCM_API_KEY:             <?= json_encode($fcmConfig['apiKey']) ?>,
    FCM_AUTH_DOMAIN:         <?= json_encode($fcmConfig['authDomain']) ?>,
    FCM_PROJECT_ID:          <?= json_encode($fcmConfig['projectId']) ?>,
    FCM_MESSAGING_SENDER_ID: <?= json_encode($fcmConfig['messagingSenderId']) ?>,
    FCM_APP_ID:              <?= json_encode($fcmConfig['appId']) ?>,
    FCM_VAPID_KEY:           <?= json_encode($fcmConfig['vapidKey']) ?>,
    API_BASE:                <?= json_encode($apiBase) ?>,
    CSRF_TOKEN:              <?= json_encode($csrfToken) ?>,
    USER_ID:                 <?= (int)$currentUserId ?>,
  });
  <?php else: ?>
  /* FCM disabled or not configured — skipping FCM_* keys */
  window.APP_CONFIG.API_BASE   = window.APP_CONFIG.API_BASE   || <?= json_encode($apiBase) ?>;
  window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || <?= json_encode($csrfToken) ?>;
  window.APP_CONFIG.USER_ID    = window.APP_CONFIG.USER_ID    || <?= (int)$currentUserId ?>;
  <?php endif; ?>

})();
</script>

<?php if ($fcmEnabled && !empty($fcmConfig['projectId']) && $currentUserId > 0): ?>
<!-- ══════════════════════════════════════════════════════════
     Firebase Push Notifications
     يُحمَّل فقط إذا:
       1. FCM_ENABLED = true في config
       2. المستخدم مسجّل دخوله (user_id > 0)
       3. المتصفح يدعم Service Worker
     ══════════════════════════════════════════════════════════ -->
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js" defer></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js" defer></script>
<script src="/assets/js/fcm-init.js" defer></script>
<?php endif; ?>

</body>
</html>