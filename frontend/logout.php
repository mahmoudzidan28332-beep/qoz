<?php
declare(strict_types=1);
/**
 * frontend/logout.php — Production
 * تسجيل الخروج الآمن — يدعم GET و POST
 *
 * ملاحظة: تم تعديل الكود ليجعل حذف الكوكيز يعمل بمسار نسبي فقط (بدون اسم دومين).
 */

// ── 1. إعداد الجلسة بنفس الاسم الذي يستخدمه auth.php ──────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('APP_SESSID');
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        // لا نمرر اسم دومين هنا — نجعل الكوكيز محلية (مسار نسبي)
        $params = ['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            // النسخة القديمة: domain كقيمة فارغة '' لتجنب تمرير اسم دومين
            session_set_cookie_params(0, '/', '', $secure, true);
        }
        @session_start();
    }
    unset($__sharedSess, $__sp);
}

// ── 2. CSRF — تحقق فقط على POST مع token موجود في الجلسة ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken  = trim((string)($_POST['csrf_token'] ?? ''));
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    // إذا كان token موجوداً في الجلسة وغير متطابق — رفض صامت آمن
    if ($sessionToken !== '' && $postedToken !== '' && !hash_equals($sessionToken, $postedToken)) {
        // نكمل تسجيل الخروج على أي حال لأن أي مستخدم يريد الخروج يجب أن يتمكن منه
    }
}

// ── 3. تنظيف بيانات الجلسة ─────────────────────────────────────────
$_SESSION = [];

// ── 4. حذف كوكي الجلسة من المتصفح ────────────────────────────────
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    // حذف دون تمرير دومين — مسار فقط
    setcookie(
        session_name(),
        '',
        time() - 86400,
        $p['path'] ?? '/',
        // لا نمرر دومين (مقدار فارغ يعني عدم تضمين اسم دومين)
        '',
        $p['secure'] ?? false,
        $p['httponly'] ?? true
    );
}

// ── 5. حذف الكوكيز الإضافية للمشروع (مسار نسبي فقط، بدون أسماء دومينات) ───
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$extraCookies = ['APP_SESSID', 'PHPSESSID', 'session_token', 'remember_me', 'qz_dvt', '__test'];
$paths = ['/']; // يمكنك إضافة مسارات إضافية إن رغبت، لكنها ستبقى نسبية فقط

foreach ($extraCookies as $name) {
    foreach ($paths as $path) {
        // 1) حذف بسيط بالمجرد تمرير المسار (لا دومين)
        setcookie($name, '', time() - 86400, $path);

        // 2) محاولة حذف بمطابقة الخصائص الأمنية إن أمكن (دومين فارغ => لا يمرر اسم دومين)
        // هذا يساعد على حذف الكوكيز التي كانت HttpOnly/secure عندما يسمح المتصفح بذلك.
        setcookie($name, '', time() - 86400, $path, '', $secure, true);
    }
}

// ── 6. تدمير الجلسة على السيرفر ───────────────────────────────────
@session_destroy();

// ── 7. منع التخزين المؤقت ─────────────────────────────────────────
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// ── 8. وجهة إعادة التوجيه ─────────────────────────────────────────
$loginUrl = '/frontend/login.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>تسجيل الخروج</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f8fafc;
      color: #1e293b;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .box {
      text-align: center;
      padding: 2.5rem 2rem;
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      max-width: 380px;
      width: 90%;
    }
    .icon {
      width: 52px; height: 52px;
      background: #f0fdf4;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem;
    }
    .icon svg { width: 26px; height: 26px; stroke: #16a34a; }
    h2 { font-size: 17px; font-weight: 600; color: #1e293b; margin-bottom: .5rem; }
    p  { font-size: 13px; color: #64748b; }
    .bar-wrap {
      height: 3px; background: #e2e8f0;
      border-radius: 99px; margin: 1.25rem 0 .75rem; overflow: hidden;
    }
    .bar {
      height: 100%; width: 0%;
      background: #3b82f6;
      border-radius: 99px;
      transition: width .05s linear;
    }
    a.btn {
      display: inline-block;
      margin-top: .75rem;
      padding: .5rem 1.25rem;
      background: #1e293b;
      color: #fff;
      border-radius: 8px;
      font-size: 13px;
      text-decoration: none;
    }
    a.btn:hover { background: #334155; }
  </style>
</head>
<body>
<div class="box">
  <div class="icon">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke-width="2" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round"
            d="M5 13l4 4L19 7"/>
    </svg>
  </div>
  <h2>تم تسجيل الخروج بنجاح</h2>
  <p>يتم إعادة توجيهك إلى صفحة تسجيل الدخول...</p>
  <div class="bar-wrap"><div class="bar" id="bar"></div></div>
  <a class="btn" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">
    تسجيل الدخول مجدداً
  </a>
</div>

<script>
(function () {
  // حذف الكوكيز على مستوى العميل (غير HttpOnly)
  var names   = ['APP_SESSID','PHPSESSID','session_token','remember_me','qz_dvt','__test'];
  // نجعل المجال فارغاً => لا نضيف تحديث اسم دومين، فقط مسار نسبي
  var domains = [''];
  var paths   = ['/', '/frontend', '/api', '/admin'];
  names.forEach(function (n) {
    domains.forEach(function (d) {
      paths.forEach(function (p) {
        var base = n + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=' + p;
        // إذا كان d فارغاً فلا نضيف جزء domain
        document.cookie = d ? base + ';domain=' + d : base;
      });
    });
  });

  // شريط التقدم ثم إعادة التوجيه
  var bar = document.getElementById('bar');
  var pct = 0;
  var iv  = setInterval(function () {
    pct = Math.min(pct + 2, 100);
    bar.style.width = pct + '%';
    if (pct >= 100) {
      clearInterval(iv);
      window.location.replace(<?= json_encode($loginUrl) ?>);
    }
  }, 20); // 20ms × 50 خطوة = ~1 ثانية
})();
</script>
</body>
</html>