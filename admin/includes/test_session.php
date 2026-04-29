<?php
declare(strict_types=1);

/**
 * admin/includes/test_session.php
 * اختبار جلسة الأدمن - يعرض تفاصيل الجلسة والهوية الحالية
 * 
 * المسار المتوقع: /admin/includes/test_session.php
 * أو استدعاؤه بعد تضمين session_boot.php
 */

// تضمين bootstrapper الجلسة
require_once __DIR__ . '/session_boot.php';

// بدء المخزن المؤقت للإخراج لتجنب مشاكل headers
ob_start();

// دالة مساعدة لعرض البيانات بشكل مرتب
function formatValue($value, $maxLength = 80) {
    if (is_null($value)) return '<span class="null-value">NULL</span>';
    if (is_bool($value)) return $value ? '<span class="bool-true">✓ true</span>' : '<span class="bool-false">✗ false</span>';
    if (is_array($value)) return '<pre class="array-output">' . htmlspecialchars(print_r($value, true)) . '</pre>';
    if (is_string($value) && strlen($value) > $maxLength) {
        return htmlspecialchars(substr($value, 0, $maxLength)) . '... <span class="truncated">(مقطوع)</span>';
    }
    return htmlspecialchars((string)$value);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار جلسة الأدمن</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-size: 1.3em;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f8f9fa;
            border-right: 4px solid #667eea;
            padding: 12px 15px;
            border-radius: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .info-value {
            font-family: 'Courier New', monospace;
            font-size: 1em;
            word-break: break-word;
        }
        .session-variable {
            background: #fff3cd;
            border-right-color: #ffc107;
        }
        .success-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            display: inline-block;
        }
        .warning-badge {
            background: #ffc107;
            color: #856404;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            display: inline-block;
        }
        .info-badge {
            background: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            display: inline-block;
        }
        .null-value {
            color: #6c757d;
            font-style: italic;
        }
        .bool-true {
            color: #28a745;
            font-weight: bold;
        }
        .bool-false {
            color: #dc3545;
            font-weight: bold;
        }
        .array-output {
            background: #2d3748;
            color: #fbbf24;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85em;
            overflow-x: auto;
            margin: 5px 0 0 0;
        }
        .truncated {
            color: #ffc107;
            font-size: 0.85em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #e9ecef;
            font-weight: bold;
        }
        .variable-key {
            font-weight: bold;
            color: #495057;
            font-family: monospace;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 2px solid #e9ecef;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9em;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #856404;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.85em;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- بطاقة معلومات الجلسة الأساسية -->
    <div class="card">
        <div class="card-header">
            📊 معلومات الجلسة الحالية (Session Info)
            <span style="font-size: 0.8em; float: left;">
                <?php echo date('Y-m-d H:i:s'); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">🍪 Session Name</div>
                    <div class="info-value"><?php echo formatValue(session_name()); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🆔 Session ID</div>
                    <div class="info-value"><?php echo formatValue(session_id()); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📋 Session Status</div>
                    <div class="info-value">
                        <?php 
                        $status = session_status();
                        $statusText = match($status) {
                            PHP_SESSION_DISABLED => '❌ معطلة (Disabled)',
                            PHP_SESSION_NONE => '⚠️ لم تبدأ (None)',
                            PHP_SESSION_ACTIVE => '✅ نشطة (Active)',
                            default => 'غير معروف'
                        };
                        echo $statusText;
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">📂 Save Path</div>
                    <div class="info-value"><?php echo formatValue(session_save_path() ?: '(الإعدادات الافتراضية)'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🌐 Module Name</div>
                    <div class="info-value"><?php echo formatValue(ini_get('session.name')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">⏱️ Cookie Lifetime</div>
                    <div class="info-value"><?php echo formatValue(ini_get('session.cookie_lifetime') . ' ثانية'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🔒 Cookie Secure</div>
                    <div class="info-value"><?php echo formatValue((bool)ini_get('session.cookie_secure')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🍪 Cookie SameSite</div>
                    <div class="info-value"><?php echo formatValue(ini_get('session.cookie_samesite') ?: 'غير محدد'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقة بيانات الجلسة (جميع المتغيرات) -->
    <div class="card">
        <div class="card-header">
            📦 متغيرات الجلسة (Session Variables)
            <span class="info-badge" style="margin-right: 10px;">المجموع: <?php echo count($_SESSION); ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($_SESSION)): ?>
                <div class="warning-badge" style="display: inline-block; padding: 10px;">
                    ⚠️ لا توجد متغيرات في الجلسة حالياً
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">المفتاح (Key)</th>
                            <th style="width: 70%;">القيمة (Value)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION as $key => $value): ?>
                            <tr>
                                <td class="variable-key"><?php echo htmlspecialchars($key); ?></td>
                                <td><?php echo formatValue($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- بطاقة معلومات المستخدم / الهوية (إذا وجدت) -->
    <div class="card">
        <div class="card-header">
            👤 معلومات الهوية (Identity / User Info)
        </div>
        <div class="card-body">
            <?php
            // محاولة استخراج معلومات المستخدم من مفاتيح شائعة
            $userKeys = ['user', 'admin', 'user_id', 'admin_id', 'user_data', 'auth', 'identity', 'userInfo', 'profile'];
            $found = false;
            
            foreach ($userKeys as $userKey) {
                if (isset($_SESSION[$userKey])) {
                    $found = true;
                    ?>
                    <div class="info-item session-variable">
                        <div class="info-label">🔑 مفتاح: <?php echo htmlspecialchars($userKey); ?></div>
                        <div class="info-value"><?php echo formatValue($_SESSION[$userKey]); ?></div>
                    </div>
                    <?php
                }
            }
            
            // التحقق من مفاتيح فرعية شائعة
            $commonNested = ['user_id', 'id', 'username', 'email', 'role', 'is_admin', 'name', 'full_name', 'permissions'];
            $nestedFound = false;
            
            foreach ($_SESSION as $key => $value) {
                if (is_array($value)) {
                    foreach ($commonNested as $nestedKey) {
                        if (isset($value[$nestedKey])) {
                            if (!$nestedFound) {
                                echo '<hr><strong>📌 معلومات مدمجة (Nested):</strong><br><br>';
                                $nestedFound = true;
                            }
                            ?>
                            <div class="info-item session-variable">
                                <div class="info-label">📎 <?php echo htmlspecialchars($key); ?>[<?php echo htmlspecialchars($nestedKey); ?>]</div>
                                <div class="info-value"><?php echo formatValue($value[$nestedKey]); ?></div>
                            </div>
                            <?php
                        }
                    }
                }
            }
            
            if (!$found && !$nestedFound) {
                echo '<div class="warning-badge" style="display: inline-block; padding: 10px;">';
                echo '⚠️ لم يتم العثور على بيانات هوية واضحة في الجلسة';
                echo '</div>';
                echo '<p style="margin-top: 15px; color: #6c757d;">المفاتيح المتوقعة: user, admin, user_id, admin_id, user_data, auth, identity</p>';
            }
            ?>
        </div>
    </div>

    <!-- بطاقة معلومات إضافية عن البيئة والكوكيز -->
    <div class="card">
        <div class="card-header">
            🌍 البيئة والطلب (Environment & Request)
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">🖥️ PHP Version</div>
                    <div class="info-value"><?php echo phpversion(); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📁 Document Root</div>
                    <div class="info-value"><?php echo formatValue($_SERVER['DOCUMENT_ROOT'] ?? 'غير معروف'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🌐 Request URI</div>
                    <div class="info-value"><?php echo formatValue($_SERVER['REQUEST_URI'] ?? 'غير معروف'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📡 Remote IP</div>
                    <div class="info-value"><?php echo formatValue($_SERVER['REMOTE_ADDR'] ?? 'غير معروف'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🍪 All Cookies</div>
                    <div class="info-value">
                        <?php 
                        if (empty($_COOKIE)) {
                            echo '<span class="null-value">لا توجد كوكيز</span>';
                        } else {
                            echo '<pre class="array-output" style="max-height: 200px;">' . htmlspecialchars(print_r($_COOKIE, true)) . '</pre>';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">📦 Session Config File</div>
                    <div class="info-value">
                        <?php 
                        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/session.php';
                        if (is_file($configPath)) {
                            echo '<span class="success-badge">✓ موجود</span> ' . htmlspecialchars($configPath);
                        } else {
                            echo '<span class="warning-badge">✗ غير موجود</span> ' . htmlspecialchars($configPath);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- أزرار الإجراءات -->
    <div class="card">
        <div class="card-body" style="text-align: center;">
            <a href="?action=refresh" class="btn">🔄 تحديث الصفحة</a>
            <a href="?action=destroy" class="btn btn-danger" onclick="return confirm('هل أنت متأكد؟ سيتم حذف جميع بيانات الجلسة!')">🗑️ تدمير الجلسة (Destroy)</a>
            <a href="?action=regenerate" class="btn btn-warning" onclick="return confirm('سيتم تغيير معرف الجلسة مع الاحتفاظ بالبيانات. هل تريد المتابعة؟')">🔑 تجديد Session ID</a>
            
            <?php
            // معالجة الإجراءات
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'destroy':
                        $_SESSION = [];
                        if (ini_get("session.use_cookies")) {
                            $params = session_get_cookie_params();
                            setcookie(session_name(), '', time() - 42000,
                                $params["path"], $params["domain"],
                                $params["secure"], $params["httponly"]
                            );
                        }
                        session_destroy();
                        echo '<script>setTimeout(function(){ window.location.href = window.location.pathname; }, 1000);</script>';
                        echo '<div class="success-badge" style="display: block; margin-top: 15px;">✅ تم تدمير الجلسة بنجاح، جاري تحديث الصفحة...</div>';
                        break;
                    case 'regenerate':
                        session_regenerate_id(true);
                        echo '<div class="success-badge" style="display: block; margin-top: 15px;">✅ تم تجديد معرف الجلسة بنجاح! معرف جديد: ' . htmlspecialchars(session_id()) . '</div>';
                        break;
                }
            }
            ?>
        </div>
    </div>

    <div class="footer">
        <strong>ملف الاختبار:</strong> <?php echo __FILE__; ?><br>
        <strong>تم التضمين:</strong> session_boot.php ✓
    </div>
</div>
</body>
</html>
<?php
ob_end_flush();
?>