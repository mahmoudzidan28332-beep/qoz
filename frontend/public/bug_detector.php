<?php
/**
 * QOOQZ Enterprise Observability Layer — Standalone Page
 * frontend/public/bug_detector.php
 *
 * يعمل بطريقتين:
 *  1) صفحة مستقلة كاملة (عند فتحه مباشرة في المتصفح)
 *  2) مضمّن include داخل صفحة أخرى (يُضيف الـ widget فقط)
 */
declare(strict_types=1);

// لا نعرض الأخطاء هنا حتى لا تتأثر بيئة الإنتاج أو قراءة إعدادات السيرفر
// error_reporting(E_ALL);

// ── حماية: كلمة مرور أو IP محلي ──────────────────────────────────
define('BD_PASS', 'qooqz2025');
$isLocal    = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
$passOk     = ($_GET['p'] ?? $_POST['p'] ?? '') === BD_PASS;
$standalone = !defined('QZ_APP_INCLUDED'); // هل يعمل كصفحة مستقلة؟

if (!$isLocal && !$passOk) {
    // إذا كان مضمّناً في صفحة أخرى وبيئة dev — اسمح
    $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev';
    if ($standalone || $appEnv === 'prod') {
        http_response_code(200); // لا نعطي 403
        echo '<!-- QZ BugDetector: add ?p='.BD_PASS.' to activate -->';
        // أظهر نموذج كلمة المرور بدلاً من حجب الصفحة
        if ($standalone) { ?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QOOQZ Bug Detector</title>
<style>
body{font-family:system-ui,sans-serif;background:#0d1117;display:flex;align-items:center;
justify-content:center;min-height:100vh;margin:0}
.box{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:2rem;
width:320px;text-align:center;color:#e6edf3}
h2{font-size:17px;font-weight:600;margin:0 0 .5rem;color:#f87171}
p{font-size:13px;color:#8b949e;margin:0 0 1.5rem}
input{width:100%;padding:9px 12px;background:#0d1117;border:1px solid #30363d;
border-radius:8px;color:#e6edf3;font-size:14px;box-sizing:border-box;margin-bottom:1rem}
button{width:100%;padding:9px;background:#ef4444;border:none;border-radius:8px;
color:#fff;font-size:14px;cursor:pointer;font-weight:600}
button:hover{background:#dc2626}
</style>
</head><body>
<div class="box">
  <h2>🔐 Bug Detector</h2>
  <p>أدخل كلمة المرور للوصول</p>
  <form method="GET">
    <input type="password" name="p" placeholder="كلمة المرور" autofocus>
    <button type="submit">دخول</button>
  </form>
</div>
</body></html>
<?php     }
        exit;
    }
}

// ── بدء الجلسة لقراءة بيانات المستخدم ───────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // جرّب الاسم الصحيح أولاً
    session_name('APP_SESSID');
    $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
    if (is_dir($__sp)) ini_set('session.save_path', $__sp);
    @session_start();
}

// ── تجميع بيانات الخادم ──────────────────────────────────────────
$srv = [
    'session_name'  => session_name(),
    'session_id'    => session_id() ?: '(none)',
    'session_match' => session_name() === 'APP_SESSID',
    'user'          => $_SESSION['user']['username'] ?? $_SESSION['user']['email'] ?? null,
    'sess_keys'     => !empty($_SESSION) ? implode(', ', array_keys($_SESSION)) : '(empty)',
    'cookies'       => array_map(fn($v) => substr($v,0,16).'…', $_COOKIE),
    'has_app_sess'  => isset($_COOKIE['APP_SESSID']),
    'has_php_sess'  => isset($_COOKIE['PHPSESSID']),
    'php_version'   => PHP_VERSION,
    'https'         => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'host'          => $_SERVER['HTTP_HOST'] ?? '',
    'doc_root'      => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'request_uri'   => $_SERVER['REQUEST_URI'] ?? '',
    'display_errors'=> ini_get('display_errors'),
    'sess_save_path'=> ini_get('session.save_path'),
    'sess_httponly' => ini_get('session.cookie_httponly'),
    'sess_lifetime' => ini_get('session.gc_maxlifetime'),
];

// ── فحص ملفات المشروع ────────────────────────────────────────────
$filesToCheck = [
    'logout.php'        => '/frontend/logout.php',
    'login.php'         => '/frontend/login.php',
    'session.php'       => '/api/shared/config/session.php',
    'db.php'            => '/api/shared/config/db.php',
    'auth route'        => '/api/routes/auth.php',
];
$fileResults = [];
foreach ($filesToCheck as $label => $rel) {
    $full = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $rel;
    if (!file_exists($full)) {
        $fileResults[$label] = ['status'=>'missing','path'=>$full,'note'=>'الملف غير موجود'];
        continue;
    }
    $src = file_get_contents($full);
    $notes = [];
    if ($label === 'logout.php') {
        if (!preg_match("/session_name\s*\(\s*['\"]APP_SESSID['\"]\s*\)/", $src))
            $notes[] = '⚠️ لا يضبط session_name APP_SESSID — سبب مشكلة تسجيل الخروج!';
        if (preg_match('/\$loginUrl\s*=\s*[\'"](.*admin.*login.*)[\'"]/', $src, $m))
            $notes[] = '⚠️ يوجّه لـ '.$m[1].' بدلاً من /frontend/login.php';
        if (preg_match("/REQUEST_METHOD.*!==.*'POST'/", $src))
            $notes[] = '⚠️ يرفض GET — رابط الخروج لن يعمل';
    }
    if ($label === 'login.php') {
        if (preg_match('/<a[^>]+href=[\'"][^"\']*logout[^"\']*[\'"][^>]*>/i', $src, $m))
            $notes[] = '⚠️ رابط خروج GET: '.htmlspecialchars(substr($m[0],0,60));
    }
    if ($label === 'auth route') {
        if (!preg_match("/session_name\s*\(\s*['\"]APP_SESSID['\"]\s*\)/", $src)
            && strpos($src,'session.php') === false)
            $notes[] = '⚠️ لا يضبط APP_SESSID صراحةً';
    }
    $fileResults[$label] = [
        'status' => count($notes) ? 'warn' : 'ok',
        'path'   => $full,
        'note'   => count($notes) ? implode('<br>', $notes) : '✓ لا مشاكل مكتشفة',
    ];
}

// ── اختبار قاعدة البيانات ─────────────────────────────────────────
$dbStatus = 'skip'; $dbMsg = 'ملف db.php غير موجود';
foreach ([
    ($_SERVER['DOCUMENT_ROOT']??'').'/api/shared/config/db.php',
    dirname(__DIR__,2).'/api/shared/config/db.php',
] as $p) {
    if (!file_exists($p)) continue;
    try {
        $dbc = require $p;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $dbc['host']??'localhost',(int)($dbc['port']??3306),$dbc['name']);
        $pdo = new PDO($dsn,$dbc['user'],$dbc['pass'],[PDO::ATTR_TIMEOUT=>4]);
        $cnt = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $dbStatus = 'ok';
        $dbMsg    = "متصل ✓ — المستخدمون: {$cnt}";
    } catch (\RuntimeException $e) {
        $dbStatus = 'error';
        $dbMsg    = 'فشل: '.$e->getMessage();
    }
    break;
}

// ── إذا كان مضمّناً (include) — أضف الـ widget فقط ──────────────
if (!$standalone) { goto widget_only; }

// ══════════════════════════════════════════════════════════════════
//  صفحة HTML كاملة (standalone mode)
// ══════════════════════════════════════════════════════════════════
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QOOQZ Bug Detector</title>
<style>
:root{
  --ok:#16a34a;--ok-bg:#f0fdf4;--ok-br:#bbf7d0;
  --warn:#d97706;--warn-bg:#fffbeb;--warn-br:#fde68a;
  --err:#dc2626;--err-bg:#fef2f2;--err-br:#fecaca;
  --info:#2563eb;--info-bg:#eff6ff;--info-br:#bfdbfe;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0d1117;color:#e6edf3;font-size:14px;line-height:1.6;min-height:100vh}
header{background:#161b22;border-bottom:1px solid #30363d;padding:1rem 1.5rem;display:flex;align-items:center;gap:1rem}
header h1{font-size:17px;font-weight:600;color:#e6edf3}
header span{font-size:12px;color:#8b949e}
.wrap{max-width:860px;margin:0 auto;padding:1.5rem}
.warn-note{background:#422006;border:1px solid #92400e;border-radius:8px;padding:.7rem 1rem;font-size:12px;color:#fcd34d;margin-bottom:1.5rem}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.5rem}
.sum{border-radius:8px;padding:12px 14px;text-align:center}
.sum.ok{background:var(--ok-bg);border:1px solid var(--ok-br)}.sum.ok .n{color:var(--ok)}
.sum.warn{background:var(--warn-bg);border:1px solid var(--warn-br)}.sum.warn .n{color:var(--warn)}
.sum.err{background:var(--err-bg);border:1px solid var(--err-br)}.sum.err .n{color:var(--err)}
.sum.info{background:var(--info-bg);border:1px solid var(--info-br)}.sum.info .n{color:var(--info)}
.n{font-size:26px;font-weight:700}.lbl{font-size:11px;color:#64748b;margin-top:2px}
.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
     color:#8b949e;margin:1.5rem 0 .6rem;padding-bottom:.4rem;border-bottom:1px solid #21262d}
.card{background:#161b22;border:1px solid #30363d;border-radius:10px;padding:.9rem 1.1rem;margin-bottom:.6rem}
.card-h{display:flex;align-items:center;gap:9px;cursor:pointer}
.dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.dot.ok{background:var(--ok)}.dot.warn{background:var(--warn)}.dot.err{background:var(--err)}.dot.info{background:var(--info)}
.card-t{font-size:13px;font-weight:500;flex:1;color:#e6edf3}
.badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase}
.badge.ok{background:var(--ok-bg);color:var(--ok)}
.badge.warn{background:var(--warn-bg);color:var(--warn)}
.badge.err{background:var(--err-bg);color:var(--err)}
.badge.info{background:var(--info-bg);color:var(--info)}
.card-b{margin-top:.7rem;padding-top:.7rem;border-top:1px solid #21262d;font-size:12px;color:#8b949e}
.card-b code{background:#0d1117;border:1px solid #30363d;border-radius:5px;
             padding:.5rem .75rem;display:block;margin-top:.5rem;white-space:pre-wrap;
             word-break:break-all;color:#34d399;line-height:1.7}
.fix{background:#0d1117;border:1px solid #ef4444;border-radius:5px;padding:.5rem .75rem;
     margin-top:.5rem;white-space:pre-wrap;word-break:break-all;color:#fca5a5;
     font-family:monospace;font-size:11.5px;line-height:1.7}
.fix-lbl{font-size:11px;font-weight:700;color:#ef4444;margin-bottom:3px}
.tabs{display:flex;gap:6px;margin-bottom:1rem;flex-wrap:wrap}
.tab{padding:5px 14px;border-radius:20px;border:1px solid #30363d;background:#161b22;
     font-size:12px;cursor:pointer;color:#8b949e}
.tab.active,.tab:hover{background:#21262d;color:#e6edf3;border-color:#8b949e}
[hidden]{display:none!important}
</style>
</head>
<body>
<header>
  <div>
    <h1>🕵️ QOOQZ Bug Detector</h1>
    <span>PHP <?= PHP_VERSION ?> &nbsp;|&nbsp; <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp; <?= htmlspecialchars($srv['host']) ?></span>
  </div>
  <div style="margin-right:auto"></div>
  <a href="?p=<?= BD_PASS ?>&refresh=1" style="font-size:12px;color:#60a5fa;text-decoration:none">↻ تحديث</a>
</header>

<div class="wrap">

<div class="warn-note">⚠️ احذف هذا الملف من السيرفر بعد الانتهاء من التشخيص — لا تتركه متاحاً للعامة</div>

<?php
// ── احسب الإجماليات ──
$totals = ['ok'=>0,'warn'=>0,'err'=>0,'info'=>0];

$checks = [];

// Session name
if ($srv['session_match']) {
    $checks[] = ['session','اسم الجلسة (session_name)','ok','session_name = APP_SESSID ✓',''];
    $totals['ok']++;
} else {
    $checks[] = ['session','اسم الجلسة (session_name)','err',
        "session_name = '{$srv['session_name']}' — المتوقع APP_SESSID\nهذا سبب عدم تسجيل الخروج!",
        "في logout.php أضف قبل session_start():\n  session_name('APP_SESSID');"];
    $totals['err']++;
}

// Cookies
if ($srv['has_app_sess']) {
    $checks[] = ['session','كوكي الجلسة (APP_SESSID)','ok','APP_SESSID موجود في المتصفح ✓',''];
    $totals['ok']++;
} elseif ($srv['has_php_sess'] && !$srv['has_app_sess']) {
    $checks[] = ['session','كوكي الجلسة','err',
        'PHPSESSID موجود بدون APP_SESSID — logout.php يحذف جلسة خاطئة!',
        "session_name('APP_SESSID'); // أضفه في logout.php و login.php"];
    $totals['err']++;
} else {
    $checks[] = ['session','كوكي الجلسة','info','لا توجد كوكي جلسة — المستخدم غير مسجّل أو تسجيل خروج تمّ بنجاح',''];
    $totals['info']++;
}

// المستخدم المسجّل
$checks[] = ['session','المستخدم الحالي',$srv['user']?'warn':'ok',
    $srv['user'] ? "مسجّل دخول بوصفه: {$srv['user']}" : 'لا يوجد مستخدم مسجّل (guest) ✓',''];
$totals[$srv['user']?'warn':'ok']++;

// مفاتيح الجلسة
$checks[] = ['session','$_SESSION keys','info',$srv['sess_keys'],''];
$totals['info']++;

// كوكيز الخادم
$checks[] = ['session','$_COOKIE على الخادم','info',
    !empty($srv['cookies']) ? implode("\n", array_map(fn($k,$v)=>"$k = $v", array_keys($srv['cookies']), $srv['cookies'])) : '(لا توجد كوكيز)',
    ''];
$totals['info']++;

// HTTPS
$checks[] = ['php','HTTPS',$srv['https']?'ok':'warn',
    $srv['https']?'الاتصال آمن HTTPS ✓':'HTTP — session.cookie_secure لن يعمل',
    $srv['https']?'':'استخدم HTTPS في الإنتاج'];
$totals[$srv['https']?'ok':'warn']++;

// display_errors
$checks[] = ['php','display_errors',
    in_array($srv['display_errors'],['1','On'],true)?'warn':'ok',
    "display_errors = {$srv['display_errors']}",
    in_array($srv['display_errors'],['1','On'],true)?'قم بالدخول للوحة السيرفر (cPanel) وتعديل php.ini أو أضف هذا السطر في ملف .htaccess الرئيسي:'."\n".'php_flag display_errors off':''];
$totals[in_array($srv['display_errors'],['1','On'],true)?'warn':'ok']++;

// session httponly
$checks[] = ['php','session.cookie_httponly',
    in_array($srv['sess_httponly'],['1','On'],true)?'ok':'warn',
    "session.cookie_httponly = {$srv['sess_httponly']}",
    in_array($srv['sess_httponly'],['1','On'],true)?'':'في لوحة السيرفر أو ملف .htaccess أضف:'."\n".'php_flag session.cookie_httponly on'."\n\n".'ملاحظة: المنصة برمجياً تفرض httponly لكن يفضل فرضه من السيرفر كطبقة أمان.'];
$totals[in_array($srv['sess_httponly'],['1','On'],true)?'ok':'warn']++;

// ملفات المشروع
foreach ($fileResults as $label => $r) {
    $s = $r['status'] === 'ok' ? 'ok' : ($r['status']==='missing'?'err':'warn');
    $checks[] = ['files',"ملف: {$label}",$s,$r['note'].'<br><small style="color:#4b5563">'.$r['path'].'</small>',''];
    $totals[$s]++;
}

// قاعدة البيانات
$dbS = $dbStatus === 'ok' ? 'ok' : ($dbStatus==='error'?'err':'info');
$checks[] = ['db','قاعدة البيانات',$dbS,$dbMsg,''];
$totals[$dbS]++;
?>

<!-- ملخص -->
<div class="summary">
  <div class="sum err"><div class="n"><?= $totals['err'] ?></div><div class="lbl">أخطاء</div></div>
  <div class="sum warn"><div class="n"><?= $totals['warn'] ?></div><div class="lbl">تحذيرات</div></div>
  <div class="sum ok"><div class="n"><?= $totals['ok'] ?></div><div class="lbl">سليم</div></div>
  <div class="sum info"><div class="n"><?= $totals['info'] ?></div><div class="lbl">معلومات</div></div>
</div>

<!-- فلتر -->
<div class="tabs">
  <button class="tab active" onclick="qzFilter('all',this)">الكل</button>
  <button class="tab" onclick="qzFilter('err',this)">أخطاء</button>
  <button class="tab" onclick="qzFilter('warn',this)">تحذيرات</button>
  <button class="tab" onclick="qzFilter('ok',this)">سليم</button>
  <button class="tab" onclick="qzFilter('session',this)">الجلسة</button>
  <button class="tab" onclick="qzFilter('files',this)">الملفات</button>
</div>

<?php
$lastCat = '';
$catNames = ['session'=>'الجلسة والكوكيز','php'=>'إعدادات PHP','files'=>'ملفات المشروع','db'=>'قاعدة البيانات'];
foreach ($checks as $i => $c):
    [$cat,$title,$status,$detail,$fix] = $c;
    if ($cat !== $lastCat) { $lastCat=$cat; echo '<div class="sec">'.$catNames[$cat].'</div>'; }
?>
<div class="card" data-status="<?= $status ?>" data-cat="<?= $cat ?>" id="c<?= $i ?>">
  <div class="card-h" onclick="qzToggle(<?= $i ?>)">
    <div class="dot <?= $status === 'err' ? 'err' : $status ?>"></div>
    <span class="card-t"><?= htmlspecialchars($title) ?></span>
    <span class="badge <?= $status === 'err' ? 'err' : $status ?>">
      <?= ['ok'=>'سليم','warn'=>'تحذير','err'=>'خطأ','info'=>'معلومة'][$status] ?>
    </span>
  </div>
  <?php if ($detail || $fix): ?>
  <div class="card-b" id="cb<?= $i ?>" <?= $status==='ok'?'hidden':'' ?>>
    <?php if ($detail): ?>
    <code><?= $detail ?></code>
    <?php endif; ?>
    <?php if ($fix): ?>
    <div class="fix-lbl" style="margin-top:.5rem">💡 الحل:</div>
    <div class="fix"><?= htmlspecialchars($fix) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- معلومات السيرفر الكاملة -->
<div class="sec">معلومات السيرفر</div>
<div class="card">
  <div class="card-h" onclick="qzToggle('srv')"><div class="dot info"></div>
    <span class="card-t">PHP / Server Info</span>
    <span class="badge info">تشخيص</span>
  </div>
  <div class="card-b" id="cbsrv" hidden>
    <code><?php foreach([
        'PHP_VERSION'=>PHP_VERSION,
        'session_name'=>session_name(),
        'session_id'=>session_id()?:'-',
        'session.save_path'=>ini_get('session.save_path'),
        'session.cookie_httponly'=>ini_get('session.cookie_httponly'),
        'session.cookie_secure'=>ini_get('session.cookie_secure'),
        'session.gc_maxlifetime'=>ini_get('session.gc_maxlifetime'),
        'DOCUMENT_ROOT'=>$_SERVER['DOCUMENT_ROOT']??'-',
        'HTTP_HOST'=>$_SERVER['HTTP_HOST']??'-',
        'HTTPS'=>$_SERVER['HTTPS']??'off',
        'SERVER_SOFTWARE'=>$_SERVER['SERVER_SOFTWARE']??'-',
    ] as $k=>$v) echo htmlspecialchars($k).' = '.htmlspecialchars((string)$v)."\n"; ?></code>
  </div>
</div>

</div>

<script>
function qzToggle(id){var b=document.getElementById('cb'+id);if(b)b.hidden=!b.hidden;}
function qzFilter(f,btn){
  document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.card[data-status]').forEach(c=>{
    var s=c.dataset.status, cat=c.dataset.cat;
    c.hidden=(f!=='all' && f!==s && f!==cat);
  });
  document.querySelectorAll('.sec').forEach(sec=>{
    var sib=sec.nextElementSibling, vis=false;
    while(sib&&!sib.classList.contains('sec')){if(sib.classList.contains('card')&&!sib.hidden)vis=true;sib=sib.nextElementSibling;}
    sec.hidden=!vis;
  });
}
// افتح تلقائياً كل الأخطاء
document.querySelectorAll('.card[data-status="err"] .card-b, .card[data-status="warn"] .card-b')
        .forEach(b=>b.hidden=false);
</script>

<?php
// ── Widget الطائر (يظهر دائماً أسفل الصفحة) ─────────────────────
widget_only:
?>

<!-- ════ QZ Observability Widget ════ -->
<style>
#qz-ob{position:fixed;bottom:18px;left:18px;width:390px;max-height:460px;
  background:#0d1117;color:#e6edf3;font-family:monospace;font-size:11.5px;
  z-index:999999;border-radius:10px;border:1.5px solid #ef4444;
  overflow:hidden;direction:ltr;text-align:left;
  box-shadow:0 12px 40px rgba(0,0,0,.7);display:flex;flex-direction:column;}
#qz-ob-hdr{background:#ef4444;color:#fff;padding:6px 10px;font-weight:700;
  display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
#qz-ob-tabs{display:flex;background:#161b22;border-bottom:1px solid #30363d;flex-shrink:0;}
.qz-t{padding:5px 14px;font-size:11px;cursor:pointer;border:none;
  border-bottom:2px solid transparent;background:none;color:#8b949e;font-family:monospace;}
.qz-t.on{color:#e6edf3;border-bottom-color:#ef4444;}
#qz-ob-body{overflow-y:auto;flex:1;}
.qi{padding:5px 8px;border-bottom:1px solid #21262d;word-break:break-all;line-height:1.5;}
.qe{color:#f87171}.qw{color:#facc15}.qp{color:#60a5fa}
.qn{color:#c084fc}.qs{color:#34d399}.qok{color:#4ade80}
.qc{background:#7f1d1d;border-radius:4px;padding:1px 6px;font-size:10px;margin-right:4px;}
.qx{background:none;border:none;color:#fff;cursor:pointer;font-size:14px;line-height:1;}
</style>

<div id="qz-ob">
  <div id="qz-ob-hdr">
    <span>🕵️ QZ Observability &nbsp;<span id="qz-cnt" class="qc">0</span></span>
    <button class="qx" onclick="document.getElementById('qz-ob').style.display='none'">✕</button>
  </div>
  <div id="qz-ob-tabs">
    <button class="qz-t on" onclick="qzTab('bugs')">Bugs</button>
    <button class="qz-t"    onclick="qzTab('sess')">Session</button>
    <button class="qz-t"    onclick="qzTab('perf')">Perf</button>
  </div>
  <div id="qz-ob-body">
    <div id="qz-bugs"></div>
    <div id="qz-sess" style="display:none">
      <?php
      $sesItems = [
        ['session_name','info', session_name()],
        ['session_id','info', session_id()?:'-'],
        ['logged_in_as', $srv['user']?'warn':'ok', $srv['user']??'(guest)'],
        ['$_SESSION keys','info',$srv['sess_keys']],
      ];
      if(!$srv['session_match'])
        $sesItems[]=['⚠️ session_name خاطئ!','err','المتوقع APP_SESSID — سبب فشل تسجيل الخروج'];
      foreach($sesItems as[$lbl,$cls,$val]):
        $clsMap=['info'=>'qs','ok'=>'qok','warn'=>'qw','err'=>'qe'];
      ?>
      <div class="qi <?= $clsMap[$cls] ?>">[SERVER] <?= htmlspecialchars($lbl) ?>: <?= htmlspecialchars($val) ?></div>
      <?php endforeach; ?>
    </div>
    <div id="qz-perf" style="display:none"></div>
  </div>
</div>

<script>
(function(){
  const seen=new Set();
  const ob=document.getElementById('qz-ob');
  const bugP=document.getElementById('qz-bugs');
  const perfP=document.getElementById('qz-perf');
  const cnt=document.getElementById('qz-cnt');
  let n=0;

  window.qzTab=function(t){
    ['bugs','sess','perf'].forEach(x=>{document.getElementById('qz-'+x).style.display=x===t?'':'none';});
    document.querySelectorAll('.qz-t').forEach((b,i)=>b.classList.toggle('on',['bugs','sess','perf'][i]===t));
  };

  function log(msg,type,pane){
    pane=pane||'bugs';
    var h=type+':'+msg;
    if(seen.has(h))return;
    seen.add(h);
    ob.style.display='flex';
    var cls={error:'qe',warn:'qw',performance:'qp',ui:'qw',network:'qn',api:'qn',session:'qs',pass:'qok'}[type]||'qe';
    var d=document.createElement('div');
    d.className='qi '+cls;
    d.textContent='['+type.toUpperCase()+'] '+msg;
    (pane==='perf'?perfP:bugP).appendChild(d);
    if(pane!=='perf'){cnt.textContent=++n;}
    if(window.Sentry&&['error','api'].includes(type))Sentry.captureMessage(msg,'error');
  }

  // JS Errors
  window.addEventListener('error',e=>log(e.message+' @ '+e.filename+':'+e.lineno,'error'));
  window.addEventListener('unhandledrejection',e=>log('Promise: '+e.reason,'error'));

  // Fetch
  var _f=window.fetch;
  window.fetch=async function(){
    try{var r=await _f.apply(this,arguments);if(!r.ok)log('Fetch '+r.status+' '+r.url,'api');return r;}
    catch(e){log('Network: '+e.message,'network');throw e;}
  };

  // Console
  var _ce=console.error;
  console.error=function(){log(Array.from(arguments).join(' '),'error');_ce.apply(console,arguments);};

  // Cookie check
  (function(){
    var c=document.cookie;
    var hasApp=c.indexOf('APP_SESSID=')>-1;
    var hasPHP=c.indexOf('PHPSESSID=')>-1;
    if(hasPHP&&!hasApp)log('PHPSESSID موجود بدلاً من APP_SESSID — logout.php لن يعمل!','session');
    else if(hasApp)log('APP_SESSID ✓','session');
    else log('لا توجد كوكي جلسة','session');
  })();

  // DOM + UX
  window.addEventListener('load',function(){
    var ric=window.requestIdleCallback||(function(cb){setTimeout(cb,80);});
    ric(function(){
      document.querySelectorAll('img').forEach(function(i){
        if(!i.complete||i.naturalWidth===0)log('Broken img: '+i.src.substring(0,55),'ui');
      });
      document.querySelectorAll('a').forEach(function(a){
        var h=a.getAttribute('href');
        if(h===''||h==='undefined'||h==='javascript:void(0)')log('Empty link: "'+a.innerText.substring(0,25)+'"','ui');
      });
      document.querySelectorAll('form button:not([type])').forEach(function(b){
        log('Button no type: "'+b.innerText.substring(0,20)+'"','warn');
      });
      var ww=window.innerWidth;
      if(document.documentElement.scrollWidth>ww+2){
        log('Horizontal scroll — صفحة أعرض من الشاشة','ui');
        Array.from(document.body.children).forEach(function(el){
          try{if(el.scrollWidth>ww+2)log('Overflow: <'+el.tagName+(el.id?'#'+el.id:'+')+'>','ui');}catch(e){}
        });
      }
      if(n===0)log('All checks passed ✓','pass');
    });

    // Perf
    ric(function(){
      var nav=performance.getEntriesByType('navigation')[0];
      if(nav){
        var load=nav.loadEventEnd-nav.startTime;
        var ttfb=nav.responseStart-nav.startTime;
        log('Load: '+load.toFixed(0)+'ms | TTFB: '+ttfb.toFixed(0)+'ms','performance','perf');
        if(load>4000)log('بطيء! Load '+load.toFixed(0)+'ms','performance','perf');
        if(ttfb>800) log('TTFB بطيء: '+ttfb.toFixed(0)+'ms','performance','perf');
      }
    });
  });

  // FPS
  var lt=performance.now(),fr=0;
  (function fps(){fr++;var n=performance.now();
    if(n>lt+1000){if(fr<25)log('Low FPS: '+fr+'fps','performance','perf');fr=0;lt=n;}
    requestAnimationFrame(fps);}
  )();

  // CLS
  try{var cls=0;new PerformanceObserver(function(l){
    l.getEntries().forEach(function(e){if(!e.hadRecentInput)cls+=e.value;});
    if(cls>0.15)log('High CLS: '+cls.toFixed(3),'performance','perf');
  }).observe({type:'layout-shift',buffered:true});}catch(e){}

  // LCP
  try{new PerformanceObserver(function(l){
    var entries = l.getEntries();
    var e = entries.length > 0 ? entries[entries.length - 1] : null;
    if(e)log('LCP: '+e.startTime.toFixed(0)+'ms'+(e.startTime>2500?' (Slow)':' (OK)'),'performance','perf');
  }).observe({type:'largest-contentful-paint',buffered:true});}catch(e){}

})();
</script>