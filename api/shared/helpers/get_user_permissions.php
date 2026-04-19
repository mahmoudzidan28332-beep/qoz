<?php declare(strict_types=1);
// htdocs/api/helpers/get_user_permissions.php
// Delegates to repository layer — no direct SQL in helpers.
header('Content-Type: application/json; charset=utf-8');
session_start();

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found']);
    exit;
}
require_once $dbPath;

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// Resolve PDO connection
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    // Build PDO from the same config that provides $conn
    $dbHost = $GLOBALS['db_host'] ?? ($_ENV['DB_HOST'] ?? 'localhost');
    $dbName = $GLOBALS['db_name'] ?? ($_ENV['DB_NAME'] ?? '');
    $dbUser = $GLOBALS['db_user'] ?? ($_ENV['DB_USER'] ?? '');
    $dbPass = $GLOBALS['db_pass'] ?? ($_ENV['DB_PASS'] ?? '');
    if ($dbName) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
}

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

// Load repositories
$modelsBase = dirname(__DIR__, 2) . '/api/v1/models';
if (!is_dir($modelsBase)) {
    $modelsBase = dirname(__DIR__) . '/v1/models';
}
if (!is_dir($modelsBase)) {
    $modelsBase = __DIR__ . '/../../v1/models';
}

$usersRepoFile = $modelsBase . '/users_account/repositories/PdoUsersRepository.php';
$rbacRepoFile  = $modelsBase . '/users_account/repositories/PdoAuthRbacRepository.php';

if (is_readable($usersRepoFile)) { require_once $usersRepoFile; }
if (is_readable($rbacRepoFile))  { require_once $rbacRepoFile; }

try {
    if (class_exists('PdoUsersRepository') && class_exists('PdoAuthRbacRepository')) {
        $usersRepo = new PdoUsersRepository($pdo);
        $rbacRepo  = new PdoAuthRbacRepository($pdo);

        $userResult = $usersRepo->find($currentUserId);
        if (!$userResult) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $user = [
            'id'        => $userResult['id'],
            'username'  => $userResult['username'] ?? '',
            'email'     => $userResult['email'] ?? '',
            'is_active' => $userResult['is_active'] ?? 0,
        ];

        $roles = [];
        $roleId = (int)($userResult['role_id'] ?? 0);
        if ($roleId) {
            $roleKey = $rbacRepo->getRoleKeyById($roleId);
            $roles[] = [
                'id'           => $roleId,
                'key_name'     => $roleKey ?? '',
                'display_name' => $userResult['role_display_name'] ?? $roleKey ?? '',
            ];
        }

        $permissionMap = [];
        $permissions   = [];
        if ($roleId) {
            $permKeys = $rbacRepo->getPermissionKeysByRoleId($roleId);
            foreach ($permKeys as $key) {
                $permissionMap[$key] = true;
                $permissions[] = ['key_name' => $key];
            }
        }

        echo json_encode([
            'success'          => true,
            'user'             => $user,
            'roles'            => $roles,
            'permissions'      => $permissionMap,
            'permissions_full' => $permissions,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Fallback: repos not available, return minimal info
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Repository classes not available']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
    ]);
}