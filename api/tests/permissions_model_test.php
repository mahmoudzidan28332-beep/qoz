<?php
// api/tests/permissions_model_test.php
// Run from CLI: php api/tests/permissions_model_test.php
declare(strict_types=1);

chdir(__DIR__ . '/..'); // ensure paths relative to api/

// load model
require_once __DIR__ . '/../models/permissions.php';

echo "Permissions model test\n";
echo "----------------------\n";

$model = new PermissionModel();

function logMsg($s){ echo date('c') . " - $s\n"; file_put_contents(__DIR__ . '/../error_log.txt', "[".date('c')."] TEST: $s\n", FILE_APPEND); }

try {
    // list existing
    logMsg("Fetching all permissions...");
    $all = $model->all();
    logMsg("Found " . count($all) . " permissions.");

    // create
    $uniq = 'test_perm_' . time() . '_' . rand(1000,9999);
    $data = ['key_name' => $uniq, 'display_name' => 'Test Permission ' . $uniq, 'description' => 'Created by test script'];
    logMsg("Creating permission with key: $uniq");
    $newId = $model->create($data);
    if ($newId) {
        logMsg("Created id: $newId");
    } else {
        logMsg("Create failed");
        exit(1);
    }

    // find by id
    $found = $model->find($newId);
    if ($found) logMsg("Find by id OK: " . json_encode($found));
    else { logMsg("Find by id FAILED"); }

    // find by key
    $fbk = $model->findByKey($uniq);
    if ($fbk) logMsg("Find by key OK: id=" . ($fbk['id'] ?? 'n/a'));
    else logMsg("Find by key FAILED");

    // update
    $updateData = ['key_name' => $uniq, 'display_name' => 'Updated ' . $uniq, 'description' => 'Updated by test'];
    logMsg("Updating id $newId");
    $ok = $model->update($newId, $updateData);
    logMsg("Update returned: " . ($ok ? 'true' : 'false'));

    $after = $model->find($newId);
    logMsg("After update: " . json_encode($after));

    // delete
    logMsg("Deleting id $newId");
    $delOk = $model->delete($newId);
    logMsg("Delete returned: " . ($delOk ? 'true' : 'false'));

    $afterDel = $model->find($newId);
    logMsg("After delete find: " . json_encode($afterDel));

    echo "Model test finished. Check api/error_log.txt for any logged errors.\n";
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../error_log.txt', "[".date('c')."] TEST exception: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}