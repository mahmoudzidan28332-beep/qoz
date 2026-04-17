<?php
declare(strict_types=1);

/**
 * run_full_system_test.php — Entry point for the Architecture & Performance Test Suite
 *
 * Usage (CLI):
 *   php tests/run_full_system_test.php
 *   php tests/run_full_system_test.php --format=html > report.html
 *   php tests/run_full_system_test.php --format=json
 *
 * Usage (Browser):
 *   Navigate to tests/run_full_system_test.php
 *
 * Options:
 *   --format=cli|html|json   Output format (default: auto-detect)
 *   --modules=arch,perf,...   Comma-separated list of modules to run
 *   --project-root=/path     Override project root directory
 */

// ── Load the test suite ──────────────────────────────────────────────────────
require_once __DIR__ . '/Architecture/AdvancedSystemTest.php';

// ── Configuration ────────────────────────────────────────────────────────────

// Detect project root (tests/ is one level below the project root)
$defaultRoot = dirname(__DIR__);

// Parse CLI arguments
$format      = 'auto';
$moduleFilter = null;
$projectRoot  = $defaultRoot;

if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--format=')) {
            $format = substr($arg, strlen('--format='));
        } elseif (str_starts_with($arg, '--modules=')) {
            $moduleFilter = explode(',', substr($arg, strlen('--modules=')));
        } elseif (str_starts_with($arg, '--project-root=')) {
            $projectRoot = substr($arg, strlen('--project-root='));
        }
    }
}

// Auto-detect format
if ($format === 'auto') {
    $format = (PHP_SAPI === 'cli') ? 'cli' : 'html';
}

// ── Build & Run ──────────────────────────────────────────────────────────────

$startTime = hrtime(true);

$runner = new AdvancedSystemTestRunner($projectRoot);
$report = $runner->getReport();

// Available module map (short name → class)
$moduleMap = [
    'arch'     => ArchitectureValidation::class,
    'perf'     => PerformanceValidation::class,
    'tenant'   => MultiTenantSafety::class,
    'security' => SecurityValidation::class,
    'quality'  => CodeQualityValidation::class,
    'runtime'  => RuntimeSimulation::class,
];

if ($moduleFilter !== null) {
    // Run only requested modules
    foreach ($moduleFilter as $key) {
        $key = trim(strtolower($key));
        if (isset($moduleMap[$key])) {
            $cls = $moduleMap[$key];
            $runner->addModule(new $cls($report, $projectRoot));
        }
    }
} else {
    // Run all modules
    foreach ($moduleMap as $cls) {
        $runner->addModule(new $cls($report, $projectRoot));
    }
}

$testReport = $runner->run();
$elapsed    = round((hrtime(true) - $startTime) / 1e6, 1); // ms

// ── Output ───────────────────────────────────────────────────────────────────

switch ($format) {
    case 'html':
        echo AdvancedSystemTestRunner::formatHtml($testReport);
        break;

    case 'json':
        $output = [
            'score'     => $testReport->score(),
            'tests_run' => $testReport->getTestsRun(),
            'summary'   => $testReport->summary(),
            'elapsed_ms' => $elapsed,
            'findings'  => array_map(fn(Finding $f) => [
                'severity'   => $f->severity,
                'category'   => $f->category,
                'message'    => $f->message,
                'file'       => $f->file,
                'line'       => $f->line,
                'suggestion' => $f->suggestion,
            ], $testReport->getFindings()),
        ];
        if (PHP_SAPI === 'cli') {
            echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($output, JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'cli':
    default:
        $text = AdvancedSystemTestRunner::formatCli($testReport);
        echo $text;
        echo "\n  ⏱  Completed in {$elapsed} ms\n\n";
        break;
}

// Exit with non-zero if there are CRITICAL findings (useful for CI pipelines)
$summary = $testReport->summary();
if ($summary[Severity::CRITICAL] > 0) {
    exit(1);
}
exit(0);
