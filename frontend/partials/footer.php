<?php
require_once __DIR__ . '/store_sections/icons.php';
?>

    </main><!-- .pub-main-content -->
</div><!-- .pub-layout -->

<!-- =============================================
     FOOTER
============================================= -->
<footer class="pub-footer" role="contentinfo">
    <div class="pub-container">
        <div class="pub-footer-grid">

            <!-- Brand column with SVG icon -->
            <div class="pub-footer-col">
                <p class="pub-footer-brand-name">
                    <span class="pub-footer-brand-icon"><?= icon('globe', 18) ?></span>
                    <?= e($_appName) ?>
                </p>
                <p class="pub-footer-brand-desc"><?= e(t('footer.tagline')) ?></p>
            </div>

            <!-- Quick links -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.quick_links')) ?></p>
                <a href="<?= e($_basePath . '/index.php') ?>"><?= e(t('nav.home')) ?></a>
                <a href="<?= e($_basePath . '/products.php') ?>"><?= e(t('nav.products')) ?></a>
                <a href="<?= e($_basePath . '/categories.php') ?>"><?= e(t('nav.categories')) ?></a>
                <a href="<?= e($_basePath . '/jobs.php') ?>"><?= e(t('nav.jobs')) ?></a>
                <a href="<?= e($_basePath . '/entities.php') ?>"><?= e(t('nav.entities')) ?></a>
                <a href="<?= e($_basePath . '/tenants.php') ?>"><?= e(t('nav.tenants')) ?></a>
            </div>

            <!-- Support -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.support')) ?></p>
                <a href="<?= e($_basePath . '/about.php') ?>"><?= e(t('footer.about')) ?></a>
                <a href="<?= e($_basePath . '/contact.php') ?>"><?= e(t('footer.contact')) ?></a>
                <a href="<?= e($_basePath . '/privacy.php') ?>"><?= e(t('footer.privacy')) ?></a>
                <a href="<?= e($_basePath . '/terms.php') ?>"><?= e(t('footer.terms')) ?></a>
                <a href="<?= e($_basePath . '/support.php') ?>"><?= e(t('footer.support_center')) ?></a>
            </div>

            <!-- Auth -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.account')) ?></p>
                <a href="/frontend/login.php"><?= e(t('nav.login')) ?></a>
                <a href="/frontend/login.php?tab=register"><?= e(t('nav.register')) ?></a>
            </div>

        </div>
    </div>

    <div class="pub-footer-bottom">
        © <?= $_year ?> <?= e($_appName) ?> — <?= e(t('footer.rights')) ?>
    </div>
</footer>

<!-- Mobile bottom navigation bar -->
<?php
$_isLoggedIn = !empty(($GLOBALS['PUB_CONTEXT']['user'] ?? [])['id']);
$_authPath   = '/frontend';
?>
<nav class="pub-bottom-nav" aria-label="<?= e(t('nav.actions', 'Navigation')) ?>">
    <!-- Home -->
    <a href="<?= e($_basePath . '/index.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true"><?= icon('house', 22) ?></span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.home', 'Home')) ?></span>
    </a>
    <!-- Categories -->
    <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true"><?= icon('grid', 22) ?></span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.categories', 'Categories')) ?></span>
    </a>
    <!-- Cart -->
    <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-bottom-nav__item" style="position:relative;">
        <span class="pub-bottom-nav__icon" aria-hidden="true" style="position:relative;">
            <?= icon('cart', 22) ?>
            <span id="pubCartCountFooter" style="position:absolute; top:-4px; right:-8px; background:var(--pub-danger, #ef4444); color:white; font-size:10px; font-weight:bold; padding:2px 5px; border-radius:10px; display:none; line-height:1;"></span>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.cart', 'Cart')) ?></span>
    </a>
    <!-- Profile -->
    <a href="<?= e($_isLoggedIn ? $_authPath . '/profile.php' : $_authPath . '/login.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true"><?= icon('user', 22) ?></span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.profile', 'Profile')) ?></span>
    </a>
</nav>

<!-- Branch Conflict Modal -->
<div id="pubCartConflictModal" class="pub-modal" hidden style="z-index:11000;">
    <div class="pub-modal-backdrop" id="pubCartConflictCloseBackdrop"></div>
    <div class="pub-modal-content" style="max-width:420px; text-align:center; padding:32px 24px; border-radius: 20px; box-shadow: var(--shadow-2xl);">
        <button type="button" class="pub-modal-close" id="pubCartConflictCloseBtn" aria-label="<?= e(t('common.close')) ?>"><?= icon('x', 24) ?></button>
        <div style="font-size:3.5rem; margin-bottom:16px; color: var(--pub-primary); opacity: 0.9;"><?= icon('cart-x', 56) ?></div>
        <h3 style="font-size:1.3rem; font-weight: 700; margin:0 0 12px; color:var(--pub-text);"><?= e(t('cart.conflict_title')) ?></h3>
        <p style="color:var(--pub-muted); font-size:0.92rem; line-height:1.6; margin:0 0 24px;">
            <?= e(t('cart.conflict_msg')) ?>
        </p>
        <div style="display:grid; gap:10px;">
            <button type="button" id="pubCartConflictSwitch" class="pub-btn pub-btn--primary" style="width:100%; padding:14px; border-radius: 12px;">
                <?= e(t('cart.switch_and_clear')) ?>
            </button>
            <button type="button" id="pubCartConflictCancel" class="pub-btn" style="width:100%; padding:14px; background: rgba(0,0,0,0.05); color: var(--pub-text); border-radius: 12px;">
                <?= e(t('common.cancel')) ?>
            </button>
        </div>
    </div>
</div>

<!-- Back-to-top button -->
<?php $_btt_side = ($_ctx['dir'] ?? 'ltr') === 'rtl' ? 'left' : 'right'; ?>
<button id="pubBackToTop" title="<?= e(t('footer.back_to_top')) ?>"
        style="display:none;position:fixed;bottom:20px;<?= e($_btt_side) ?>:20px;
               z-index:200;width:40px;height:40px;background:var(--pub-primary);color:var(--pub-btn-primary-text,#fff);
               border:none;border-radius:50%;font-size:1.2rem;cursor:pointer;align-items:center;
               justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.2);">↑</button>


<!-- Public JS moved to header.php for consistent loading -->

<?php
// ════════════════════════════════════════════════════════════
// Device Registration Fallback
// ════════════════════════════════════════════════════════════
$_devRegUserId = 0;
if (isset($_user) && !empty($_user['id'])) {
    $_devRegUserId = (int)$_user['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $_devRegUserId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $_devRegUserId = (int)$_SESSION['user']['id'];
}

if ($_devRegUserId > 0):
?>
<script>
(function(){
    var STORAGE_KEY='qz_dev_reg', ANON_KEY='qz_anon_token', DAY_MS=86400000;
    var lastReg=localStorage.getItem(STORAGE_KEY);
    if(lastReg && (Date.now()-parseInt(lastReg,10))<DAY_MS) return;

    var anon = localStorage.getItem(ANON_KEY);
    if (!anon) {
        try {
            anon = (typeof crypto!=='undefined' && crypto.randomUUID) ? crypto.randomUUID() : 
                   (Math.random().toString(36).substring(2,15) + Math.random().toString(36).substring(2,15));
            localStorage.setItem(ANON_KEY, anon);
        } catch(e) { anon = 'fallback-' + Date.now(); }
    }

    fetch('/api/public/user_devices',{
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ anonymous_token: anon })
    }).then(function(r){
        if(r.ok) localStorage.setItem(STORAGE_KEY,''+Date.now());
    }).catch(function(){});
})();
</script>
<?php endif; // $_devRegUserId check ?>

<?php
// ════════════════════════════════════════════════════════════
// Firebase Push Notifications — Device Registration
// ════════════════════════════════════════════════════════════
$_fcmEnabled = defined('FCM_ENABLED') ? FCM_ENABLED : false;
if (!$_fcmEnabled) {
    $_envPath = dirname(__DIR__, 2) . '/api/.env';
    if (!file_exists($_envPath)) {
        $_envPath = dirname(__DIR__, 2) . '/api/shared/config/.env';
    }
    if (file_exists($_envPath) && is_readable($_envPath)) {
        foreach (file($_envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_eLine) {
            $_eLine = trim($_eLine);
            if ($_eLine === '' || str_starts_with($_eLine, '#') || !str_contains($_eLine, '=')) continue;
            [$_eK, $_eV] = array_map('trim', explode('=', $_eLine, 2));
            if ($_eK !== '' && getenv($_eK) === false) {
                putenv("{$_eK}={$_eV}");
            }
        }
    }

    $_constFile = dirname(__DIR__, 2) . '/api/shared/config/constants.php';
    if (file_exists($_constFile)) {
        $_consts = @include $_constFile;
        if (is_array($_consts)) {
            $_fcmEnabled = !empty($_consts['FCM_ENABLED']);
            if ($_fcmEnabled) {
                foreach ($_consts as $_ck => $_cv) {
                    if (str_starts_with($_ck, 'FCM_') && !defined($_ck)) {
                        define($_ck, $_cv);
                    }
                }
            }
        }
    }
}

$_fcmUserId = 0;
if (isset($_user) && !empty($_user['id'])) {
    $_fcmUserId = (int)$_user['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $_fcmUserId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $_fcmUserId = (int)$_SESSION['user']['id'];
}

if ($_fcmEnabled && $_fcmUserId > 0):
    $_fcmCfg = [
        'apiKey'            => defined('FCM_API_KEY')             ? FCM_API_KEY             : '',
        'authDomain'        => defined('FCM_AUTH_DOMAIN')         ? FCM_AUTH_DOMAIN         : '',
        'projectId'         => defined('FCM_PROJECT_ID')          ? FCM_PROJECT_ID          : '',
        'messagingSenderId' => defined('FCM_MESSAGING_SENDER_ID') ? FCM_MESSAGING_SENDER_ID : '',
        'appId'             => defined('FCM_APP_ID')              ? FCM_APP_ID              : '',
        'vapidKey'          => defined('FCM_VAPID_KEY')           ? FCM_VAPID_KEY           : '',
    ];
    if (!empty($_fcmCfg['projectId'])):
?>
<script>
(function() {
    window.APP_CONFIG = window.APP_CONFIG || {};
    Object.assign(window.APP_CONFIG, {
        FCM_API_KEY:             <?= json_encode($_fcmCfg['apiKey']) ?>,
        FCM_AUTH_DOMAIN:         <?= json_encode($_fcmCfg['authDomain']) ?>,
        FCM_PROJECT_ID:          <?= json_encode($_fcmCfg['projectId']) ?>,
        FCM_MESSAGING_SENDER_ID: <?= json_encode($_fcmCfg['messagingSenderId']) ?>,
        FCM_APP_ID:              <?= json_encode($_fcmCfg['appId']) ?>,
        FCM_VAPID_KEY:           <?= json_encode($_fcmCfg['vapidKey']) ?>,
        API_BASE:                '/api',
        USER_ID:                 <?= $_fcmUserId ?>
    });
})();
</script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js" defer></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js" defer></script>
<?php $_fcmJsV = @filemtime(FRONTEND_BASE . '/assets/js/firebase.js') ?: '1'; ?>
<script src="/frontend/assets/js/firebase.js?v=<?= $_fcmJsV ?>" defer></script>
<?php endif; // projectId check ?>
<?php endif; // fcmEnabled + userId check ?>
</body>
</html>