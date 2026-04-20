/**
 * /frontend/assets/js/firebase.js
 * تسجيل FCM Token وحفظه في user_devices
 * يُحمَّل في footer.php بعد تسجيل الدخول
 */
(function () {
    'use strict';

    // ── إعداد Firebase (القيم من window.APP_CONFIG) ──────────
    const FIREBASE_CONFIG = {
        apiKey:            window.APP_CONFIG?.FCM_API_KEY            || '',
        authDomain:        window.APP_CONFIG?.FCM_AUTH_DOMAIN        || '',
        projectId:         window.APP_CONFIG?.FCM_PROJECT_ID         || '',
        messagingSenderId: window.APP_CONFIG?.FCM_MESSAGING_SENDER_ID|| '',
        appId:             window.APP_CONFIG?.FCM_APP_ID             || '',
    };

    const VAPID_KEY     = window.APP_CONFIG?.FCM_VAPID_KEY  || '';
    const API_DEVICES   = (window.APP_CONFIG?.API_BASE || '/api') + '/user_devices';
    const SW_PATH       = '/firebase-messaging-sw.js';
    const STORAGE_KEY   = 'fcm_token_registered';

    // ── نقطة الدخول ─────────────────────────────────────────
    async function init() {
        if (!('serviceWorker' in navigator) || !('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'denied') {
            return;
        }

        if (!FIREBASE_CONFIG.projectId) {
            return;
        }

        try {
            if (typeof firebase === 'undefined') {
                return;
            }

            if (!firebase.apps.length) {
                firebase.initializeApp(FIREBASE_CONFIG);
            }

            var messaging = firebase.messaging();

            var swRegistration = await navigator.serviceWorker.register(SW_PATH, {
                scope: '/',
            });

            var permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                return;
            }

            var tokenOpts = { serviceWorkerRegistration: swRegistration };
            if (VAPID_KEY && VAPID_KEY !== 'REPLACE_WITH_YOUR_VAPID_KEY') {
                tokenOpts.vapidKey = VAPID_KEY;
            }

            var token = await messaging.getToken(tokenOpts);
            if (!token) {
                return;
            }

            // حفظ فقط إذا تغيّر التوكن أو مرّ أكثر من 24 ساعة
            var lastToken = localStorage.getItem(STORAGE_KEY);
            var lastTime  = parseInt(localStorage.getItem(STORAGE_KEY + '_t'), 10) || 0;
            var dayMs     = 86400000; // 24h
            if (lastToken !== token || (Date.now() - lastTime) > dayMs) {
                await registerTokenOnServer(token);
                localStorage.setItem(STORAGE_KEY, token);
                localStorage.setItem(STORAGE_KEY + '_t', String(Date.now()));
            }

            // استقبال الإشعارات والتطبيق مفتوح (Foreground)
            messaging.onMessage(function (payload) {
                handleForegroundMessage(payload);
            });

        } catch (err) {
            // silent fail for push notifications
        }
    }

    // ── حفظ التوكن في user_devices ──────────────────────────
    async function registerTokenOnServer(token) {
        try {
            var body = {
                fcm_token:   token,
                device_type: detectDeviceType(),
                device_name: parseDeviceName(),
            };

            var res = await fetch(API_DEVICES, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            await res.json();
        } catch (err) {
            // silent fail
        }
    }

    // ── عرض الإشعار عند وصوله والتطبيق مفتوح ─────────────
    function handleForegroundMessage(payload) {
        var title = (payload.notification && payload.notification.title) || '';
        var body  = (payload.notification && payload.notification.body)  || '';
        var icon  = (payload.notification && payload.notification.icon)  || '/frontend/assets/images/logo.png';

        if (Notification.permission === 'granted' && title) {
            new Notification(title, { body: body, icon: icon });
        }

        // تحديث عداد الإشعارات
        if (typeof window.incrementNotifBadge === 'function') {
            window.incrementNotifBadge();
        }

        window.dispatchEvent(new CustomEvent('fcm:message', { detail: payload }));
    }

    // ── كشف نوع الجهاز ─────────────────────────────────────
    function detectDeviceType() {
        var ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua))          return 'android';
        if (/iphone|ipad|ipod/.test(ua)) return 'ios';
        if (/mobile|tablet/.test(ua))    return 'other';
        return 'web';
    }

    // ── اسم الجهاز المقروء ──────────────────────────────────
    function parseDeviceName() {
        var ua = navigator.userAgent;
        var browser = 'Browser';
        var os = 'Unknown';
        if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) browser = 'Chrome';
        else if (/Edg\//.test(ua))    browser = 'Edge';
        else if (/Firefox\//.test(ua)) browser = 'Firefox';
        else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) browser = 'Safari';
        if (/Windows/.test(ua))      os = 'Windows';
        else if (/Mac OS/.test(ua))  os = 'macOS';
        else if (/Android/.test(ua)) os = 'Android';
        else if (/iPhone|iPad/.test(ua)) os = 'iOS';
        else if (/Linux/.test(ua))   os = 'Linux';
        return browser + ' on ' + os;
    }

    // ── حذف التوكن عند تسجيل الخروج ─────────────────────────
    window.fcmDeregister = async function () {
        var token = localStorage.getItem(STORAGE_KEY);
        if (!token) return;

        try {
            await fetch(API_DEVICES + '/deregister', {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ fcm_token: token }),
            });
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(STORAGE_KEY + '_t');
        } catch (err) {
            // silent fail
        }
    };

    // ── تشغيل بعد تحميل الصفحة ──────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(init, 1500);
        });
    } else {
        setTimeout(init, 1500);
    }

})();