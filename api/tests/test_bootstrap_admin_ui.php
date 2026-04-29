<?php
declare(strict_types=1);

/**
 * api/tests/test_bootstrap_admin_ui.php
 *
 * ملف اختبار bootstrap_admin_ui.php
 * يتحقق من:
 *  1. اتصال قاعدة البيانات
 *  2. وجود الجداول المطلوبة (users, tenant_users, roles, permissions, role_permissions)
 *  3. هيكل جدول users (لا يحتوي على role_id أو tenant_id)
 *  4. هيكل جدول tenant_users (يحتوي على user_id, role_id, tenant_id)
 *  5. قراءة بيانات المستخدم عبر tenant_users بشكل صحيح
 *  6. بناء ADMIN_UI بالشكل الصحيح
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$results  = [];
$allPassed = true;

// -------------------------------------------------------
// 1. اتصال قاعدة البيانات
// -------------------------------------------------------
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

if ($pdo instanceof PDO) {
    $results['db_connection'] = ['ok' => true, 'message' => 'ADMIN_DB is a valid PDO instance'];
} else {
    $results['db_connection'] = ['ok' => false, 'error' => 'ADMIN_DB not initialized or not a PDO instance'];
    $allPassed = false;
}

// -------------------------------------------------------
// 2. وجود الجداول المطلوبة
// -------------------------------------------------------
// Table names are from a hardcoded allowlist — no user input involved.
$requiredTables = ['users', 'tenant_users', 'roles', 'permissions', 'role_permissions', 'tenants'];
$allowedTables  = array_flip($requiredTables);
$results['required_tables'] = [];

if ($pdo instanceof PDO) {
    foreach ($requiredTables as $table) {
        // Guard: only query names from the known allowlist.
        if (!array_key_exists($table, $allowedTables)) {
            continue;
        }
        try {
            // Safe: table name is from the hardcoded allowlist above.
            $stmt = $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            $results['required_tables'][$table] = ['ok' => true];
        } catch (Throwable $e) {
            $results['required_tables'][$table] = ['ok' => false, 'error' => $e->getMessage()];
            $allPassed = false;
        }
    }
}

// -------------------------------------------------------
// 3. هيكل جدول users — يجب ألا يحتوي على role_id أو tenant_id
// -------------------------------------------------------
$results['users_schema'] = ['ok' => false];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $hasRoleId   = in_array('role_id',   $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        $hasId       = in_array('id',        $columns, true);
        $hasUsername = in_array('username',  $columns, true);

        $schemaOk = $hasId && $hasUsername && !$hasRoleId && !$hasTenantId;

        $results['users_schema'] = [
            'ok'               => $schemaOk,
            'columns'          => $columns,
            'has_id'           => $hasId,
            'has_username'     => $hasUsername,
            'has_role_id'      => $hasRoleId,   // should be false
            'has_tenant_id'    => $hasTenantId, // should be false
            'message'          => $schemaOk
                ? 'users table schema is correct (no role_id/tenant_id)'
                : 'users table schema mismatch',
        ];

        if (!$schemaOk) {
            $allPassed = false;
        }
    } catch (Throwable $e) {
        $results['users_schema'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
}

// -------------------------------------------------------
// 4. هيكل جدول tenant_users — يجب أن يحتوي على user_id, role_id, tenant_id
// -------------------------------------------------------
$results['tenant_users_schema'] = ['ok' => false];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("DESCRIBE tenant_users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $hasUserId   = in_array('user_id',   $columns, true);
        $hasRoleId   = in_array('role_id',   $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        $hasIsActive = in_array('is_active', $columns, true);

        $schemaOk = $hasUserId && $hasRoleId && $hasTenantId && $hasIsActive;

        $results['tenant_users_schema'] = [
            'ok'             => $schemaOk,
            'columns'        => $columns,
            'has_user_id'    => $hasUserId,
            'has_role_id'    => $hasRoleId,
            'has_tenant_id'  => $hasTenantId,
            'has_is_active'  => $hasIsActive,
            'message'        => $schemaOk
                ? 'tenant_users table schema is correct'
                : 'tenant_users table schema mismatch',
        ];

        if (!$schemaOk) {
            $allPassed = false;
        }
    } catch (Throwable $e) {
        $results['tenant_users_schema'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
}

// -------------------------------------------------------
// 5. استعلام بيانات المستخدم عبر tenant_users
// -------------------------------------------------------
$results['user_tenant_join'] = ['ok' => false, 'skipped' => false];

if ($pdo instanceof PDO) {
    try {
        // جلب أول مستخدم له سجل في tenant_users
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.email, u.is_active,
                   tu.tenant_id, tu.role_id
            FROM users u
            INNER JOIN tenant_users tu ON tu.user_id = u.id
            WHERE tu.is_active = 1
            LIMIT 1
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($row) {
            $results['user_tenant_join'] = [
                'ok'        => true,
                'user_id'   => $row['id'],
                'username'  => $row['username'],
                'tenant_id' => $row['tenant_id'],
                'role_id'   => $row['role_id'],
                'is_active' => $row['is_active'],
                'message'   => 'JOIN between users and tenant_users works correctly',
            ];
        } else {
            $results['user_tenant_join'] = [
                'ok'      => true,
                'skipped' => true,
                'message' => 'No active tenant_users records found — query is valid but table is empty',
            ];
        }
    } catch (Throwable $e) {
        $results['user_tenant_join'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
}

// -------------------------------------------------------
// 6. تحميل bootstrap_admin_ui.php والتحقق من هيكل ADMIN_UI
// -------------------------------------------------------
$results['admin_ui_structure'] = ['ok' => false];

$bootstrapFile = __DIR__ . '/../bootstrap_admin_ui.php';
if (is_file($bootstrapFile)) {
    try {
        require_once $bootstrapFile;

        $adminUi = $GLOBALS['ADMIN_UI'] ?? null;

        if (is_array($adminUi)) {
            $userKeys = ['id', 'username', 'email', 'role_id', 'roles', 'permissions', 'is_active', 'preferred_language', 'tenant_id'];
            $missingKeys = array_diff($userKeys, array_keys($adminUi['user'] ?? []));

            $structureOk = empty($missingKeys) && isset($adminUi['settings'], $adminUi['lang'], $adminUi['direction']);

            $results['admin_ui_structure'] = [
                'ok'           => $structureOk,
                'has_user'     => isset($adminUi['user']),
                'has_settings' => isset($adminUi['settings']),
                'has_lang'     => isset($adminUi['lang']),
                'has_direction'=> isset($adminUi['direction']),
                'missing_user_keys' => array_values($missingKeys),
                'user_tenant_id' => $adminUi['user']['tenant_id'] ?? null,
                'user_role_id'   => $adminUi['user']['role_id'] ?? null,
                'message'      => $structureOk
                    ? 'ADMIN_UI structure is correct'
                    : 'ADMIN_UI structure has missing keys: ' . implode(', ', $missingKeys),
            ];

            if (!$structureOk) {
                $allPassed = false;
            }
        } else {
            $results['admin_ui_structure'] = ['ok' => false, 'error' => 'ADMIN_UI global not set after loading bootstrap_admin_ui.php'];
            $allPassed = false;
        }
    } catch (Throwable $e) {
        $results['admin_ui_structure'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
} else {
    $results['admin_ui_structure'] = ['ok' => false, 'error' => 'bootstrap_admin_ui.php not found at ' . $bootstrapFile];
    $allPassed = false;
}

// -------------------------------------------------------
// الخلاصة
// -------------------------------------------------------
http_response_code($allPassed ? 200 : 500);
echo json_encode([
    'success'     => $allPassed,
    'message'     => $allPassed
        ? 'All bootstrap_admin_ui checks passed'
        : 'Some checks failed — see results',
    'results'     => $results,
    'php_version' => PHP_VERSION,
    'timestamp'   => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);