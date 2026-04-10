<?php
// admin/test-rbac.php
// Lightweight diagnostic for RBAC / get_current_user / DB connectivity
// Save as UTF-8 without BOM and open in browser: https://your-site/admin/test-rbac.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "RBAC / DB diagnostic\n";
echo "=====================\n\n";

$base = __DIR__ . '/../api/helpers/rbac.php';
echo "RBAC helper path: $base\n";
echo "RBAC helper readable: " . (is_readable($base) ? 'yes' : 'no') . "\n\n";

if (is_readable($base)) {
    try {
        require_once $base;
        echo "Included rbac helper successfully.\n";
    } catch (Throwable $e) {
        echo "Include failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    exit(1);
}

// start session safely if function available
if (function_exists('start_session_safe')) {
    start_session_safe();
} else {
    if (session_status() === PHP_SESSION_NONE) @session_start();
}

echo "\nPHP session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive') . "\n";
echo "Session id: " . session_id() . "\n";
echo "Session keys: " . implode(', ', array_keys($_SESSION)) . "\n\n";

// show error_log path
$elog = ini_get('error_log') ?: '(none)';
echo "PHP error_log: $elog\n\n";

// get_db test
echo "Testing get_db() ...\n";
$db = null;
if (function_exists('get_db')) {
    try {
        $db = get_db();
        if ($db instanceof mysqli) {
            echo "get_db(): mysqli instance OK\n";
            // show server info safely
            $hi = '';
            try { $hi = $db->host_info ?? ''; } catch (Throwable $e) { $hi = ''; }
            echo "mysqli host_info: " . ($hi ?: '(unknown)') . "\n";
        } else {
            echo "get_db(): returned null or non-mysqli (" . gettype($db) . ")\n";
        }
    } catch (Throwable $e) {
        echo "get_db() threw: " . $e->getMessage() . "\n";
    }
} else {
    echo "get_db() function not defined\n";
}

echo "\nget_current_user() ...\n";
try {
    if (function_exists('get_current_user')) {
        $u = get_current_user();
        echo "get_current_user():\n";
        echo json_encode($u, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "get_current_user() function not defined\n";
    }
} catch (Throwable $e) {
    echo "get_current_user() threw: " . $e->getMessage() . "\n";
}

// show session permissions if any
echo "\nSession permissions:\n";
if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
    echo json_encode(array_values($_SESSION['permissions']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "(none)\n";
}

// attempt to load permissions into session for current user id (if known)
echo "\nAttempting load_user_permissions_into_session() if possible...\n";
try {
    $uid = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    if (!empty($uid) && function_exists('load_user_permissions_into_session')) {
        $perms = load_user_permissions_into_session((int)$uid);
        echo "Permissions loaded for user id $uid: \n";
        echo json_encode($perms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "No user id in session or function missing (user_id: " . var_export($uid, true) . ")\n";
    }
} catch (Throwable $e) {
    echo "load_user_permissions_into_session() threw: " . $e->getMessage() . "\n";
}

// class RBAC (if present) and getPermissions
echo "\nClass RBAC test (if available)...\n";
try {
    if (class_exists('RBAC')) {
        $rbacDb = null;
        if ($db instanceof mysqli) $rbacDb = $db;
        $uid = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
        try {
            $rb = new RBAC($rbacDb, $uid);
            if (method_exists($rb, 'getPermissions')) {
                $rp = $rb->getPermissions(true);
                echo "RBAC->getPermissions(): " . json_encode($rp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "RBAC class exists but getPermissions method not found\n";
            }
        } catch (Throwable $e) {
            echo "RBAC instantiation or method threw: " . $e->getMessage() . "\n";
        }
    } else {
        echo "RBAC class not defined\n";
    }
} catch (Throwable $e) {
    echo "RBAC class check threw: " . $e->getMessage() . "\n";
}

// test user_has for a few common perms
$tests = ['view_drivers','create_drivers','edit_drivers','manage_driver_docs'];
echo "\nuser_has() checks:\n";
foreach ($tests as $t) {
    try {
        $res = function_exists('user_has') ? (user_has($t) ? 'yes' : 'no') : '(user_has not defined)';
    } catch (Throwable $e) {
        $res = 'threw: ' . $e->getMessage();
    }
    echo " - $t: $res\n";
}

echo "\nFinished diagnostic.\n";