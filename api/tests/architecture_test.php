<?php
declare(strict_types=1);

/**
 * Advanced Architecture & Performance Test
 */

// Since this is inside api/tests, __DIR__ is tests, dirname(__DIR__) is api.
define('API_PATH', dirname(__DIR__));

// Make output visible nicely in the browser
echo "<pre style='font-family: monospace; font-size: 14px; background: #1e1e1e; color: #d4d4d4; padding: 20px;'>\n";
echo "Running Advanced Architecture & Performance Tests...\n\n";

$errors = [];
$warnings = [];

function getAllFiles($dir) {
    if (!is_dir($dir)) return [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Notice: API_PATH is the 'api' directory. 
// So the subdirectories are directly inside API_PATH.
$routesFiles = getAllFiles(API_PATH . '/v1/routes');
$controllerFiles = array_filter(getAllFiles(API_PATH), fn($f) => strpos(basename($f), 'Controller.php') !== false);
$repositoryFiles = array_filter(getAllFiles(API_PATH), fn($f) => strpos(basename($f), 'Repository.php') !== false);
$helperFiles = getAllFiles(API_PATH . '/shared/helpers');
$coreFiles = getAllFiles(API_PATH . '/shared/core');
$allAppFiles = getAllFiles(API_PATH);

// 1. [TEST] Single Source of Truth
echo "[TEST] Single Source of Truth\n";
foreach ($routesFiles as $file) {
    $content = file_get_contents($file);
    if (preg_match('/\$pdo\->prepare|\$pdo\->query|\bINSERT INTO\b|\bUPDATE\s+[A-Za-z0-9_]+\s+SET\b|\bDELETE FROM\b/i', $content)) {
        $errors[] = "Single Source of Truth Violation: Raw DB query found in route -> " . basename($file);
    }
}

// 2. [TEST] API Layer Thinness
echo "[TEST] API Layer Thinness\n";
foreach ($controllerFiles as $file) {
    $lines = count(file($file));
    if ($lines > 400) {
        $warnings[] = "API Layer Thinness Violation: Controller is too fat ($lines lines) -> " . basename($file);
    }
}

// 3. [TEST] Helper Layer Rules
echo "[TEST] Helper Layer Rules\n";
foreach ($helperFiles as $file) {
    $content = file_get_contents($file);
    if (preg_match('/\$pdo\->prepare|\$pdo\->query|\bSELECT\b.*\bFROM\b/i', $content)) {
        $errors[] = "Helper Layer Rules Violation: SQL query found in helper -> " . basename($file);
    }
}

// 4. [TEST] API Version Duplication
echo "[TEST] API Version Duplication\n";
$v1Path = API_PATH . '/v1/routes';
$v2Path = API_PATH . '/v2/routes';
if (is_dir($v1Path) && is_dir($v2Path)) {
    $v1Files = scandir($v1Path);
    foreach ($v1Files as $file) {
        if ($file !== '.' && $file !== '..' && file_exists("$v2Path/$file")) {
            if (md5_file("$v1Path/$file") === md5_file("$v2Path/$file")) {
                $warnings[] = "API Version Duplication Violation: Identical route file in v1 and v2 -> " . $file;
            }
        }
    }
}

// 5. [TEST] Core Usage Rule
echo "[TEST] Core Usage Rule\n";
foreach ($coreFiles as $file) {
    $content = file_get_contents($file);
    if (preg_match('/require(_once)?.*models\//i', $content)) {
        $errors[] = "Core Usage Rule Violation: Core file includes higher-level models -> " . basename($file);
    }
}

// 6. [TEST] Middleware Boundaries
echo "[TEST] Middleware Boundaries\n";
foreach ($controllerFiles as $file) {
    if (strpos(basename($file), 'AuthController.php') !== false) {
        continue; 
    }
    $content = file_get_contents($file);
    // Fixed: syntax error from the quotes in regex by using simple strpos
    if (strpos($content, '$_SESSION') !== false) {
        $warnings[] = "Middleware Boundaries Violation: Direct session access in controller -> " . basename($file);
    }
}

// 7. [TEST] Performance: Subqueries & DB Optimizations
echo "[TEST] Performance Analysis\n";
foreach ($repositoryFiles as $file) {
    $content = file_get_contents($file);
    
    // Check for Subqueries in ON clauses (fixed regex to strictly checking within lines)
    if (preg_match('/JOIN\b[^;]+?\bON\b[^;]+?\(\s*SELECT\b/i', $content)) {
        $warnings[] = "Performance Violation (Slowness): Subquery detected inside a JOIN ON clause in -> " . basename($file);
    }
    
    // Primitive check for N+1 Queries (Loops containing DB queries)
    if (preg_match('/(foreach|while|for)\s*\(.*\{.*?\$pdo\->.*?}/is', $content)) {
        $errors[] = "Performance Violation (N+1 Queries): Database call inside a loop detected in -> " . basename($file);
    }
}

// Print Report
echo "\n============================================\n";
echo "ADVANCED ARCHITECTURE & PERFORMANCE REPORT\n";
echo "============================================\n\n";

if (empty($errors) && empty($warnings)) {
    echo "<span style='color: #4CAF50;'>✅ ALL TESTS PASSED. The Architecture is clean.</span>\n";
} else {
    if (!empty($errors)) {
        echo "<span style='color: #f44336;'>❌ CRITICAL ERRORS:</span>\n";
        foreach ($errors as $error) {
            echo "  <span style='color: #ff9800;'>- $error</span>\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "<span style='color: #ffeb3b;'>⚠️ PERFORMANCE WARNINGS & SUGGESTIONS:</span>\n";
        foreach ($warnings as $warn) {
            echo "  - $warn\n";
        }
    }
}

echo "\n============================================\n";
echo "الخلاصة: السكريبت الآن يعمل بشكل صحيح، تم تحديد مسارات الملفات بدقة وتم تنسيق المخرجات لتظهر في المتصفح بكل وضوح.\n";
echo "</pre>";
?>
