<?php
// api/tests/permissions_model_test.php
// Verbose permissions model test — prints DB connection diagnostics and CRUD attempts
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../models/permissions.php';

function logf($msg) {
    $line = "[" . date('c') . "] TEST: " . $msg . PHP_EOL;
    echo $line;
    @file_put_contents(__DIR__ . '/../error_log.txt', $line, FILE_APPEND | LOCK_EX);
}

logf("Starting Permissions model verbose test");

// Try to detect DB available by different methods
$dbFound = false;
$db = null;

// 1) global ADMIN_DB
if (!empty($GLOBALS['ADMIN_DB']) && $GLOBALS['ADMIN_DB'] instanceof mysqli) {
    $db = $GLOBALS['ADMIN_DB'];
    logf("Found GLOBALS['ADMIN_DB'] (mysqli)");
}

// 2) global $db
if (!$db && !empty($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
    $db = $GLOBALS['db'];
    logf("Found GLOBALS['db'] (mysqli)");
}

// 3) connectDB()
if (!$db && function_exists('connectDB')) {
    try {
        $maybe = @connectDB();
        if ($maybe instanceof mysqli) {
            $db = $maybe;
            logf("connectDB() returned mysqli");
        } else {
            logf("connectDB() did not return mysqli (returned type: " . gettype($maybe) . ")");
        }
    } catch (Throwable $e) {
        logf("connectDB() threw: " . $e->getMessage());
    }
}

// 4) config file attempt
if (!$db) {
    $cfgPath = __DIR__ . '/../config/db.php';
    if (is_readable($cfgPath)) {
        $cfg = require $cfgPath;
        logf("Loaded config/db.php");
        $host = $cfg['host'] ?? ($cfg['DB_HOST'] ?? null);
        $user = $cfg['user'] ?? ($cfg['DB_USER'] ?? null);
        $pass = $cfg['pass'] ?? ($cfg['DB_PASS'] ?? null);
        $name = $cfg['name'] ?? ($cfg['DB_NAME'] ?? null);
        $port = isset($cfg['port']) ? (int)$cfg['port'] : 3306;
        if ($host && $user && $name) {
            $mysqli = @new mysqli($host, $user, $pass, $name, $port);
            if ($mysqli && !$mysqli->connect_errno) {
                $db = $mysqli;
                logf("Connected via config/db.php to {$host}/{$name}");
            } else {
                logf("mysqli connect failed (config): " . ($mysqli->connect_error ?? 'unknown'));
            }
        } else {
            logf("DB config incomplete in config/db.php");
        }
    } else {
        logf("config/db.php not readable");
    }
}

if ($db instanceof mysqli) {
    logf("Final DB: mysqli connected. server_info=" . ($db->server_info ?? '') . " client_info=" . (mysqli_get_client_info() ?? ''));
    $dbFound = true;
} else {
    logf("No mysqli DB connection available for CLI test. PermissionsModel may fail in CLI.");
}

// instantiate model and run CRUD
$model = new PermissionModel($db);

try {
    logf("Calling model->all()");
    $all = $model->all();
    $count = is_array($all) ? count($all) : 'n/a';
    logf("model->all() returned count: " . $count);

    $uniq = 'test_perm_' . time() . '_' . rand(1000,9999);
    $data = ['key_name' => $uniq, 'display_name' => 'Test Permission ' . $uniq, 'description' => 'Created by CLI test'];

    logf("Attempting create with key: {$uniq}");
    $newId = $model->create($data);
    if ($newId) {
        logf("Create succeeded id={$newId}");
        $found = $model->find((int)$newId);
        logf("Find after create: " . json_encode($found));
        logf("Updating created record...");
        $upd = $model->update((int)$newId, ['key_name'=>$uniq, 'display_name'=>'Updated '.$uniq, 'description'=>'Updated']);
        logf("Update returned: " . ($upd ? 'true' : 'false'));
        logf("Deleting created record...");
        $del = $model->delete((int)$newId);
        logf("Delete returned: " . ($del ? 'true' : 'false'));
    } else {
        logf("Create failed (newId null). Check api/error_log.txt for any logged DB errors.");
    }
} catch (Throwable $e) {
    logf("Exception in model test: " . $e->getMessage());
    logf($e->getTraceAsString());
}

logf("Model test finished. Inspect api/error_log.txt for details.");