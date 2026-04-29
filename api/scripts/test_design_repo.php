<?php
require_once __DIR__ . '/../bootstrap.php';
require_once API_VERSION_PATH . '/models/design_settings/repositories/PdoDesignSettingsRepository.php';

$pdo = $GLOBALS['ADMIN_DB'];
$repo = new PdoDesignSettingsRepository($pdo);
try {
    $res = $repo->all(1);
    echo "SUCCESS: Found " . count($res) . " records\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
