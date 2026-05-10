<?php
require_once __DIR__ . '/store_sections/icons.php';

// -- Footer variables (safe fallbacks) ----------------------------------------
$_ctx      = $GLOBALS['PUB_CONTEXT']      ?? [];
$_appName  = $GLOBALS['PUB_APP_NAME']     ?? 'QOOQZ';
$_basePath = $GLOBALS['PUB_BASE_PATH']    ?? '/frontend/public';
$_year     = date('Y');
$_user     = $_ctx['user']                ?? [];
?>

    </main><!-- .pub-main-content -->
</div><!-- .pub-layout -->


<!-- ============================================================
     FOOTER
============================================================ -->
<footer class="pub-footer" role="contentinfo">
    <div class="pub-container">
        <div class="pub-footer-grid">

            <!-- Brand column -->
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
        &copy; <?= $_year ?> <?= e($_appName) ?> &mdash; <?= e(t('footer.rights')) ?>
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
    <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-bottom-nav__item pub-bottom-nav__item--cart">
        <span class="pub-bottom-nav__icon pub-bottom-nav__icon--cart" aria-hidden="true">
            <?= icon('cart', 22) ?>
            <span id="pubCartCountFooter" class="pub-bottom-nav__badge"></span>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.cart', 'Cart')) ?></span>
    </a>

    <!-- Profile -->
    <a href="<?= e($_isLoggedIn ? $_authPath . '/profile.php' : $_authPath . '/login.php') ?>"
       class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true"><?= icon('user', 22) ?></span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.profile', 'Profile')) ?></span>
    </a>

</nav>

<!-- Cart Branch Conflict Modal -->
<div id="pubCartConflictModal"
     class="pub-modal pub-modal--top"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="pubCartConflictTitle">
    <div class="pub-modal-backdrop" id="pubCartConflictCloseBackdrop"></div>
    <div class="pub-modal-content pub-cart-conflict">
        <button type="button"
                class="pub-modal-close"
                id="pubCartConflictCloseBtn"
                aria-label="<?= e(t('common.close')) ?>">
            <?= icon('x', 24) ?>
        </button>
        <div class="pub-cart-conflict__icon"><?= icon('cart-x', 56) ?></div>
        <h3 id="pubCartConflictTitle" class="pub-cart-conflict__title">
            <?= e(t('cart.conflict_title')) ?>
        </h3>
        <p class="pub-cart-conflict__text">
            <?= e(t('cart.conflict_msg')) ?>
        </p>
        <div class="pub-cart-conflict__actions">
            <button type="button"
                    id="pubCartConflictSwitch"
                    class="pub-btn pub-btn--primary pub-cart-conflict__button">
                <?= e(t('cart.switch_and_clear')) ?>
            </button>
            <button type="button"
                    id="pubCartConflictCancel"
                    class="pub-btn pub-cart-conflict__button pub-cart-conflict__button--secondary">
                <?= e(t('common.cancel')) ?>
            </button>
        </div>
    </div>
</div>

<!-- Back-to-top button -->
<?php $_btt_side = ($_ctx['dir'] ?? 'ltr') === 'rtl' ? 'left' : 'right'; ?>
<button id="pubBackToTop"
        class="pub-back-to-top pub-back-to-top--<?= e($_btt_side) ?>"
        title="<?= e(t('footer.back_to_top')) ?>"
        aria-label="<?= e(t('footer.back_to_top')) ?>">&#8593;</button>


<?php
// ============================================================
// Device Registration Fallback
// ============================================================
$_devRegUserId = 0;
if (!empty($_user['id'])) {
    $_devRegUserId = (int)$_user['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $_devRegUserId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $_devRegUserId = (int)$_SESSION['user']['id'];
}

if ($_devRegUserId > 0):
    $_devRegJsV = @filemtime(FRONTEND_BASE . '/assets/js/device-registration.js') ?: '1';
?>
<script src="/frontend/assets/js/device-registration.js?v=<?= $_devRegJsV ?>" defer></script>
<?php endif; ?>


<?php
// ============================================================
// Firebase Push Notifications - Device Registration
// ============================================================
$_fcmEnabled = defined('FCM_ENABLED') ? FCM_ENABLED : false;

if (!$_fcmEnabled) {
    $_envPath = dirname(__DIR__, 2) . '/api/.env';
    if (!file_exists($_envPath)) {
        $_envPath = dirname(__DIR__, 2) . '/api/shared/config/.env';
    }
    if (file_exists($_envPath) && is_readable($_envPath)) {
        foreach (file($_envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_eLine) {
            $_eLine = trim($_eLine);
            if ($_eLine === '' || str_starts_with($_eLine, '#') || !str_contains($_eLine, '=')) {
                continue;
            }
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
if (!empty($_user['id'])) {
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
<div id="pubFcmConfig"
     hidden
     data-fcm-api-key="<?= e($_fcmCfg['apiKey']) ?>"
     data-fcm-auth-domain="<?= e($_fcmCfg['authDomain']) ?>"
     data-fcm-project-id="<?= e($_fcmCfg['projectId']) ?>"
     data-fcm-messaging-sender-id="<?= e($_fcmCfg['messagingSenderId']) ?>"
     data-fcm-app-id="<?= e($_fcmCfg['appId']) ?>"
     data-fcm-vapid-key="<?= e($_fcmCfg['vapidKey']) ?>"
     data-api-base="/api"
     data-user-id="<?= $_fcmUserId ?>"></div>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js" defer></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js" defer></script>
<?php $_fcmJsV = @filemtime(FRONTEND_BASE . '/assets/js/firebase.js') ?: '1'; ?>
<script src="/frontend/assets/js/firebase.js?v=<?= $_fcmJsV ?>" defer></script>
<?php endif; ?>
<?php endif; ?>

<!-- ============================================================
     STICKY HEADER - SINGLE PRODUCTION SCRIPT
     ============================================================ -->
<script>
(function () {
    'use strict';

    const inner = document.querySelector('.pub-header-inner');
    if (!inner) return;

    inner.style.transition = 'transform 0.25s ease-out';
    inner.style.willChange = 'transform';

    let lastY = window.scrollY;
    let state = 'visible';
    let timeout = null;

    const HIDE_AFTER = 100;
    const DELTA = 8;

    function show() {
        if (state === 'visible') return;
        state = 'visible';
        inner.style.transform = '';
    }

    function hide() {
        if (state === 'hidden') return;
        state = 'hidden';
        inner.style.transform = 'translateY(-100%)';
    }

    window.addEventListener('scroll', () => {
        const y = window.scrollY;
        const diff = y - lastY;

        if (timeout) clearTimeout(timeout);

        if (y <= HIDE_AFTER) {
            show();
        }
        else if (diff > DELTA) {
            hide();
        }
        else if (diff < -DELTA) {
            show();
        }

        timeout = setTimeout(() => {
            show();
        }, 150);

        lastY = y;
    }, { passive: true });

})();
</script>

</body>
</html>