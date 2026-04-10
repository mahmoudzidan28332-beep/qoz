<?php
declare(strict_types=1);

/**
 * archive_queues.php
 * Automated maintenance script for the queue system.
 * Designed to be run via CRON.
 * 
 * Usage: php archive_queues.php [--archive-seconds=10] [--purge-days=30]
 */

require_once __DIR__ . '/../bootstrap.php';
require_once API_VERSION_PATH . '/models/queues/repositories/PdoQueuesRepository.php';
require_once API_VERSION_PATH . '/models/queues/validators/QueuesValidator.php';
require_once API_VERSION_PATH . '/models/queues/services/QueuesService.php';

try {
    if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
        throw new Exception("Database connection not available.");
    }

    $repository = new PdoQueuesRepository($GLOBALS['ADMIN_DB']);
    $service    = new QueuesService($repository);

    // Parse arguments
    $opts = getopt('', ['archive-seconds::', 'purge-days::']);
    $archiveSeconds = isset($opts['archive-seconds']) ? (int)$opts['archive-seconds'] : 10;
    $purgeDays      = isset($opts['purge-days'])      ? (int)$opts['purge-days']      : 30;

    echo "[" . date('Y-m-d H:i:s') . "] Starting queue maintenance...\n";

    // 1. Archive completed/exhausted jobs
    echo "[" . date('Y-m-d H:i:s') . "] Archiving jobs older than {$archiveSeconds} seconds...\n";
    $archived = $service->archiveJobs($archiveSeconds);
    echo "[" . date('Y-m-d H:i:s') . "] Archived {$archived} jobs.\n";

    // 2. Purge old archives
    echo "[" . date('Y-m-d H:i:s') . "] Purging archives older than {$purgeDays} days...\n";
    $purged = $service->purgeArchives($purgeDays);
    echo "[" . date('Y-m-d H:i:s') . "] Purged {$purged} records from archive.\n";

    echo "[" . date('Y-m-d H:i:s') . "] Maintenance completed successfully.\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    safe_log('error', 'Queue maintenance script failed: ' . $e->getMessage());
    exit(1);
}
