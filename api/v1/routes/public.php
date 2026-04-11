<?php
declare(strict_types=1);

/**
 * routes/public.php
 *
 * Public API Routes تحت /api/public/*
 * يدعم Auto-Discovery للملفات في مجلد public/
 */

$pubUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pubRel = (string) preg_replace('#^/api/public/?#i', '', $pubUri);
$segments = array_values(array_filter(explode('/', trim($pubRel, '/'))));

$first = strtolower($segments[0] ?? '');

/** @var PDO|null $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

// ====================== PDO Fallback ======================
if (!$pdo instanceof PDO) {
    $dbPaths = [
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php',
        dirname(__DIR__, 2) . '/shared/config/db.php',
    ];

    foreach ($dbPaths as $dbFile) {
        if ($dbFile && is_readable($dbFile)) {
            $cfg = require $dbFile;
            if (is_array($cfg) && isset($cfg['host'], $cfg['name'], $cfg['user'])) {
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                            $cfg['host'],
                            (int)($cfg['port'] ?? 3306),
                            $cfg['name'],
                            $cfg['charset'] ?? 'utf8mb4'
                        ),
                        $cfg['user'],
                        $cfg['pass'] ?? '',
                        [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT            => 5,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]
                    );
                    $GLOBALS['ADMIN_DB'] = $pdo;
                    break;
                } catch (Throwable $e) {
                    error_log('[Public API] PDO Connection Failed: ' . $e->getMessage());
                    $pdo = null;
                }
            }
        }
    }
}

// ====================== Common Variables ======================
$lang     = $_GET['lang'] ?? 'ar';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(100, max(1, (int)($_GET['per'] ?? $_GET['limit'] ?? 25)));
$offset   = ($page - 1) * $per;
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
            ? (int)$_GET['tenant_id']
            : null;

// ====================== PDO Helpers ======================
$pdoList = function (string $sql, array $params = []) use ($pdo): array {
    if (!$pdo instanceof PDO) return [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[Public API PDO List] ' . $e->getMessage());
        return [];
    }
};

$pdoOne = function (string $sql, array $params = []) use ($pdo): ?array {
    if (!$pdo instanceof PDO) return null;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('[Public API PDO One] ' . $e->getMessage());
        return null;
    }
};

$pdoCount = function (string $sql, array $params = []) use ($pdo): int {
    if (!$pdo instanceof PDO) return 0;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[Public API PDO Count] ' . $e->getMessage());
        return 0;
    }
};

// ====================== Special Routes ======================

// Home / Health Check
if ($first === '' || $first === 'home') {
    ResponseFormatter::success([
        'ok'      => true,
        'service' => 'QOOQZ Public API',
        'version' => '1.0',
        'time'    => date('c')
    ]);
    exit;
}

// Current User (للـ frontend)
if ($first === 'me') {
    $user = $_SESSION['user'] ?? null;
    ResponseFormatter::success([
        'user' => $user && isset($user['id']) && $user['id'] > 0 ? [
            'id'    => (int)$user['id'],
            'name'  => $user['name'] ?? $user['username'] ?? '',
            'email' => $user['email'] ?? '',
        ] : null
    ]);
    exit;
}

// ====================== Auto Discovery Route ======================

// منع الملفات الخطرة
$blocked = ['.', '..', 'index', '.htaccess', '.env', 'public', ''];

if (!empty($first) && !in_array($first, $blocked, true)) {

    $routeFile = __DIR__ . '/public/' . $first . '.php';

    if (file_exists($routeFile) && is_file($routeFile)) {
        // أمان إضافي: التأكد أن الملف يبدأ بـ PHP tag
        $handle = fopen($routeFile, 'rb');
        $start  = fread($handle, 10);
        fclose($handle);

        if (str_starts_with(trim($start), '<?php')) {
            // تمرير جميع المتغيرات إلى الملف الفرعي
            require $routeFile;
            exit;
        }
    }
}

// ====================== 404 Fallback ======================
ResponseFormatter::notFound('Public route not found: /' . htmlspecialchars($first));