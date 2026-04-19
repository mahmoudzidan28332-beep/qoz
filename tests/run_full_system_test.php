<?php
declare(strict_types=1);

// ✅ أضف هذه الأسطر في البداية لتصحيح الأخطاء
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// أو استخدم هذا للسجلات
error_log("=== Starting test suite ===");

/**
 * run_full_system_test.php — Entry point for the Architecture & Performance Test Suite
 * 
 * Usage (CLI):
 *   php tests/run_full_system_test.php
 *   php tests/run_full_system_test.php --format=html > report.html
 *   php tests/run_full_system_test.php --format=json
 *   php tests/run_full_system_test.php --format=markdown
 *   php tests/run_full_system_test.php --modules=arch,perf,security
 *   php tests/run_full_system_test.php --project-root=/custom/path
 * 
 * Usage (Browser):
 *   Navigate to tests/run_full_system_test.php
 *   Navigate to tests/run_full_system_test.php?format=html
 *   Navigate to tests/run_full_system_test.php?format=json
 *   Navigate to tests/run_full_system_test.php?format=markdown
 *   Navigate to tests/run_full_system_test.php?modules=arch,perf
 * 
 * Options:
 *   --format=cli|html|json|markdown   Output format (default: auto-detect)
 *   --modules=arch,perf,...           Comma-separated list of modules to run
 *   --project-root=/path              Override project root directory
 * 
 * Available modules:
 *   arch      - Architecture Validation
 *   perf      - Performance Validation  
 *   tenant    - Multi-Tenant Safety
 *   security  - Security Validation
 *   types     - Type Safety Validation
 *   config    - Configuration Safety
 *   exception - Exception Handling
 *   quality   - Code Quality Validation
 *   runtime   - Runtime Simulation
 */

// ── Load the test suite ──────────────────────────────────────────────────────
$testSuitePath = __DIR__ . '/Architecture/AdvancedSystemTest.php';

if (!file_exists($testSuitePath)) {
    die("Error: Cannot find AdvancedSystemTest.php at: {$testSuitePath}\n");
}

require_once $testSuitePath;

// ── Parse Arguments ──────────────────────────────────────────────────────────
$format       = 'auto';
$moduleFilter = null;
$projectRoot  = dirname(__DIR__); // Default: parent of tests/ directory

if (PHP_SAPI === 'cli') {
    // Parse CLI arguments
    for ($i = 1; $i < $_SERVER['argc']; $i++) {
        $arg = $_SERVER['argv'][$i];
        
        if (str_starts_with($arg, '--format=')) {
            $format = substr($arg, strlen('--format='));
        } elseif (str_starts_with($arg, '--modules=')) {
            $moduleFilter = explode(',', substr($arg, strlen('--modules=')));
            $moduleFilter = array_map('trim', $moduleFilter);
        } elseif (str_starts_with($arg, '--project-root=')) {
            $projectRoot = substr($arg, strlen('--project-root='));
            if (!is_dir($projectRoot)) {
                die("Error: Project root directory not found: {$projectRoot}\n");
            }
        } elseif ($arg === '--help' || $arg === '-h') {
            echo file_get_contents(__FILE__);
            exit(0);
        }
    }
} else {
    // Parse HTTP query parameters
    $format = $_GET['format'] ?? 'auto';
    
    if (isset($_GET['modules'])) {
        $moduleFilter = explode(',', $_GET['modules']);
        $moduleFilter = array_map('trim', $moduleFilter);
    }
    
    if (isset($_GET['project_root'])) {
        $projectRoot = $_GET['project_root'];
    }
}

// Auto-detect format
if ($format === 'auto') {
    $format = (PHP_SAPI === 'cli') ? 'cli' : 'html';
}

// Validate format
$validFormats = ['cli', 'html', 'json', 'markdown', 'md'];
if (!in_array($format, $validFormats)) {
    $format = (PHP_SAPI === 'cli') ? 'cli' : 'html';
}

// Normalize markdown format
if ($format === 'md') {
    $format = 'markdown';
}

// ── Module Mapping ───────────────────────────────────────────────────────────
$moduleMap = [
    'arch'     => ArchitectureValidation::class,
    'perf'     => PerformanceValidation::class,
    'tenant'   => MultiTenantSafety::class,
    'security' => SecurityValidation::class,
    'types'    => TypeSafetyValidation::class,
    'config'   => ConfigurationSafety::class,
    'exception'=> ExceptionHandling::class,
    'quality'  => CodeQualityValidation::class,
    'runtime'  => RuntimeSimulation::class,
];

// ── Build Runner ─────────────────────────────────────────────────────────────
$runner = AdvancedSystemTestRunner::createDefault($projectRoot);
$report = $runner->getReport();

if ($moduleFilter !== null && !empty($moduleFilter)) {
    // Create a new runner with only selected modules
    $customRunner = new AdvancedSystemTestRunner($projectRoot);
    $customReport = $customRunner->getReport();
    
    foreach ($moduleFilter as $moduleKey) {
        $moduleKey = strtolower(trim($moduleKey));
        
        if (isset($moduleMap[$moduleKey])) {
            $className = $moduleMap[$moduleKey];
            $customRunner->addModule(new $className($customReport, $projectRoot));
        } else {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Warning: Unknown module '{$moduleKey}'. Available: " . implode(', ', array_keys($moduleMap)) . "\n");
            }
        }
    }
    
    $runner = $customRunner;
    $report = $customReport;
}

// ── Run Tests ────────────────────────────────────────────────────────────────
$startTime = hrtime(true);
$testReport = $runner->run();
$elapsedMs = round((hrtime(true) - $startTime) / 1e6, 1);

// ── Output Results ───────────────────────────────────────────────────────────
$summary = $testReport->summaryCounts();
$hasErrors = ($summary[Severity::CRITICAL] ?? 0) > 0;

switch ($format) {
    case 'html':
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo ReportFormatter::html($testReport);
        echo "\n<!-- Total execution time: {$elapsedMs} ms -->\n";
        break;
        
    case 'json':
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/json; charset=UTF-8');
        }
        
        // Build enhanced JSON output
        $timings = [];
        foreach ($testReport->getTimings() as $timing) {
            $timings[] = [
                'name' => $timing->name,
                'elapsed_ms' => round($timing->elapsedMs, 2),
                'tests_run' => $timing->testsRun,
                'findings' => count($testReport->findingsByModule($timing->name))
            ];
        }
        
        $findings = [];
        foreach ($testReport->getFindings() as $finding) {
            $findings[] = [
                'severity' => $finding->severity,
                'module' => $finding->module,
                'category' => $finding->category,
                'message' => $finding->message,
                'file' => $finding->file,
                'line' => $finding->line,
                'suggestion' => $finding->suggestion
            ];
        }
        
        $output = [
            'meta' => [
                'version' => '3.0.0',
                'generated' => date('c'),
                'execution_time_ms' => $elapsedMs,
                'project_root' => $projectRoot,
                'format' => $format
            ],
            'score' => [
                'value' => $testReport->score(),
                'grade' => $testReport->grade()[0],
                'label' => $testReport->grade()[2],
                'color' => $testReport->grade()[1]
            ],
            'summary' => [
                'tests_run' => $testReport->getTestsRun(),
                'critical' => $summary[Severity::CRITICAL] ?? 0,
                'warning' => $summary[Severity::WARNING] ?? 0,
                'info' => $summary[Severity::INFO] ?? 0,
                'has_errors' => $hasErrors
            ],
            'timings' => $timings,
            'findings' => $findings
        ];
        
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
        
    case 'markdown':
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: text/markdown; charset=UTF-8');
        }
        echo ReportFormatter::markdown($testReport);
        echo "\n---\n*Report generated in {$elapsedMs} ms*\n";
        break;
        
    case 'cli':
    default:
        echo ReportFormatter::cli($testReport);
        echo "\n  ⏱  Completed in {$elapsedMs} ms\n";
        
        // Show module execution details
        if (count($testReport->getTimings()) > 1) {
            echo "\n  📊 Module Breakdown:\n";
            foreach ($testReport->getTimings() as $timing) {
                $barLength = min(40, (int)($timing->elapsedMs / 5));
                $bar = str_repeat('█', $barLength) . str_repeat('░', 40 - $barLength);
                printf("    %-20s %s %6.1f ms (%3d tests)\n", 
                    $timing->name, 
                    $bar, 
                    $timing->elapsedMs, 
                    $timing->testsRun
                );
            }
        }
        
        // Show next steps if there are critical issues
        if ($hasErrors) {
            echo "\n  ❌ Found CRITICAL issues! Recommended actions:\n";
            echo "     1. Fix all CRITICAL issues first (highest priority)\n";
            echo "     2. Run with --format=html > report.html to see detailed report\n";
            echo "     3. Focus on 'Layer Flow' and 'Multi-Tenant' categories\n";
        } elseif ($summary[Severity::WARNING] > 0) {
            echo "\n  ⚠️  Found warnings. Consider addressing them for better code quality.\n";
        } else {
            echo "\n  ✅ Excellent! No issues found.\n";
        }
        
        break;
}

// ── Exit with appropriate code for CI/CD ─────────────────────────────────────
// Exit with non-zero code if there are CRITICAL findings
exit($hasErrors ? 1 : 0);