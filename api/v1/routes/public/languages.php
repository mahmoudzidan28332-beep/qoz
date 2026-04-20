<?php
declare(strict_types=1);
/**
 * Public API sub-route: languages
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'languages') {
    try {
        $rows = $pdoList("SELECT code, name, direction FROM languages ORDER BY name ASC", []);
    } catch (\Throwable $e) {
        $rows = [];
    }
    ResponseFormatter::success([
        'data' => $rows,
        'meta' => ['total' => count($rows)],
    ]);
    exit;
}