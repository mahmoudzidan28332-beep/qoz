<?php
declare(strict_types=1);

/**
 * AdvancedSystemTest.php — Professional Architecture & Performance Test Suite
 *
 * This test system validates the entire PHP MVC codebase for:
 *   - Architecture integrity (strict layer flow)
 *   - Performance anti-patterns (N+1, subqueries, deep pagination)
 *   - Multi-tenant data safety
 *   - Security best practices
 *   - Code quality metrics
 *   - Runtime simulation
 *
 * Usage (CLI):
 *   php tests/run_full_system_test.php
 *
 * Usage (Browser):
 *   Navigate to tests/run_full_system_test.php
 *
 * @version  2.0.0
 * @license  MIT
 */

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Helper Utilities
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Recursively scan a directory for PHP files.
 */
function scanPhpFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

/**
 * Check if a file's content matches a pattern — returns all match locations.
 *
 * @return array<int, array{line: int, text: string}>  Matched lines
 */
function findPatternInFile(string $filepath, string $pattern): array
{
    $matches = [];
    $lines = @file($filepath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }
    foreach ($lines as $idx => $line) {
        if (preg_match($pattern, $line)) {
            $matches[] = ['line' => $idx + 1, 'text' => trim($line)];
        }
    }
    return $matches;
}

/**
 * Multi-line pattern search (content-level, not line-level).
 */
function findMultilinePattern(string $filepath, string $pattern): bool
{
    $content = @file_get_contents($filepath);
    if ($content === false) {
        return false;
    }
    return (bool) preg_match($pattern, $content);
}

/**
 * Count lines in a file (excluding blank lines and comments).
 */
function countCodeLines(string $filepath): int
{
    $lines = @file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return 0;
    }
    $count = 0;
    $inBlock = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        // Block comment tracking
        if (!$inBlock && (str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '/**'))) {
            $inBlock = true;
        }
        if ($inBlock) {
            if (str_contains($trimmed, '*/')) {
                $inBlock = false;
            }
            continue;
        }
        // Single-line comment
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $count++;
    }
    return $count;
}

/**
 * Measure execution time of a callable (in ms).
 */
function measureTime(callable $fn): float
{
    $start = hrtime(true);
    $fn();
    return (hrtime(true) - $start) / 1e6; // nanoseconds → milliseconds
}

/**
 * Determine the short path relative to the project root for display.
 */
function shortPath(string $fullPath, string $root): string
{
    if (str_starts_with($fullPath, $root)) {
        return ltrim(substr($fullPath, strlen($root)), '/\\');
    }
    return basename($fullPath);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — Result Container
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Severity levels for findings.
 */
class Severity
{
    public const CRITICAL = 'CRITICAL';
    public const WARNING  = 'WARNING';
    public const INFO     = 'INFO';
}

/**
 * A single test finding.
 */
class Finding
{
    public string $severity;
    public string $category;
    public string $message;
    public string $file;
    public int    $line;
    public string $suggestion;

    public function __construct(
        string $severity,
        string $category,
        string $message,
        string $file = '',
        int    $line = 0,
        string $suggestion = ''
    ) {
        $this->severity   = $severity;
        $this->category   = $category;
        $this->message    = $message;
        $this->file       = $file;
        $this->line       = $line;
        $this->suggestion = $suggestion;
    }
}

/**
 * Collects all findings and computes the final score.
 */
class TestReport
{
    /** @var Finding[] */
    private array $findings = [];
    private int   $testsRun = 0;

    public function add(Finding $f): void
    {
        $this->findings[] = $f;
    }

    public function incrementTests(int $n = 1): void
    {
        $this->testsRun += $n;
    }

    /** @return Finding[] */
    public function getFindings(): array
    {
        return $this->findings;
    }

    public function getTestsRun(): int
    {
        return $this->testsRun;
    }

    /**
     * Compute a 0–100 score.
     *
     * Deductions:
     *   CRITICAL → −5 each (capped at −60)
     *   WARNING  → −2 each (capped at −30)
     *   INFO     → −0.5 each (capped at −10)
     */
    public function score(): int
    {
        $criticals = 0;
        $warnings  = 0;
        $infos     = 0;

        foreach ($this->findings as $f) {
            match ($f->severity) {
                Severity::CRITICAL => $criticals++,
                Severity::WARNING  => $warnings++,
                Severity::INFO     => $infos++,
            };
        }

        $deduct  = min(60, $criticals * 5);
        $deduct += min(30, $warnings * 2);
        $deduct += min(10, (int) ($infos * 0.5));

        return max(0, 100 - $deduct);
    }

    /**
     * Return summary counts by severity.
     *
     * @return array{CRITICAL: int, WARNING: int, INFO: int}
     */
    public function summary(): array
    {
        $out = [Severity::CRITICAL => 0, Severity::WARNING => 0, Severity::INFO => 0];
        foreach ($this->findings as $f) {
            $out[$f->severity]++;
        }
        return $out;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — Abstract Base Test
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Every test module extends this base.
 */
abstract class BaseArchTest
{
    protected TestReport $report;
    protected string     $apiRoot;
    protected string     $projectRoot;

    public function __construct(TestReport $report, string $projectRoot)
    {
        $this->report      = $report;
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->apiRoot     = $this->projectRoot . '/api';
    }

    /** Human-readable name for the module. */
    abstract public function name(): string;

    /** Run all checks in this module. */
    abstract public function run(): void;

    // Convenience helpers -------------------------------------------------

    protected function short(string $path): string
    {
        return shortPath($path, $this->projectRoot);
    }

    protected function critical(string $cat, string $msg, string $file = '', int $line = 0, string $suggestion = ''): void
    {
        $this->report->add(new Finding(Severity::CRITICAL, $cat, $msg, $this->short($file), $line, $suggestion));
    }

    protected function warning(string $cat, string $msg, string $file = '', int $line = 0, string $suggestion = ''): void
    {
        $this->report->add(new Finding(Severity::WARNING, $cat, $msg, $this->short($file), $line, $suggestion));
    }

    protected function info(string $cat, string $msg, string $file = '', int $line = 0, string $suggestion = ''): void
    {
        $this->report->add(new Finding(Severity::INFO, $cat, $msg, $this->short($file), $line, $suggestion));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Architecture Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

class ArchitectureValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Architecture Validation';
    }

    public function run(): void
    {
        $this->testSingleSourceOfTruth();
        $this->testNoBusinessLogicInControllers();
        $this->testNoBusinessLogicInHelpers();
        $this->testNoDirectDbInServices();
        $this->testStrictLayerFlow();
        $this->testCircularDependencies();
    }

    /**
     * TEST: No raw SQL queries should exist in route files.
     * Only repositories are allowed to contain SQL.
     */
    private function testSingleSourceOfTruth(): void
    {
        $this->report->incrementTests();
        $routeFiles = scanPhpFiles($this->apiRoot . '/v1/routes');

        foreach ($routeFiles as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Skip comment-only matches: only flag if the SQL-like token is NOT inside a comment
            $lines = explode("\n", $content);
            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);

                // Skip pure comment lines
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (preg_match('/\$pdo\s*->\s*prepare\s*\(|\$pdo\s*->\s*query\s*\(/i', $trimmed)) {
                    $this->critical(
                        'Single Source of Truth',
                        'Raw DB call ($pdo->prepare/query) found in route file',
                        $file,
                        $idx + 1,
                        'Move this database call into a Repository class and call it via a Service.'
                    );
                }

                if (preg_match('/\bINSERT\s+INTO\b/i', $trimmed) && !preg_match('/[\'"].*INSERT\s+INTO.*[\'"]/i', $trimmed) === false) {
                    // Only flag if it looks like actual SQL execution, not a string constant
                    if (preg_match('/\bINSERT\s+INTO\s+[a-z_]+/i', $trimmed) && strpos($trimmed, '->execute') !== false) {
                        $this->critical(
                            'Single Source of Truth',
                            'INSERT INTO statement found in route file',
                            $file,
                            $idx + 1,
                            'Move INSERT operations into a Repository class.'
                        );
                    }
                }

                if (preg_match('/\bUPDATE\s+[A-Za-z0-9_]+\s+SET\b/i', $trimmed) && !str_starts_with($trimmed, '//') && !str_starts_with($trimmed, '*')) {
                    $this->critical(
                        'Single Source of Truth',
                        'UPDATE…SET statement found in route file',
                        $file,
                        $idx + 1,
                        'Move UPDATE operations into a Repository class.'
                    );
                }

                if (preg_match('/\bDELETE\s+FROM\b/i', $trimmed) && !str_starts_with($trimmed, '//') && !str_starts_with($trimmed, '*')) {
                    $this->critical(
                        'Single Source of Truth',
                        'DELETE FROM statement found in route file',
                        $file,
                        $idx + 1,
                        'Move DELETE operations into a Repository class.'
                    );
                }
            }
        }
    }

    /**
     * TEST: Controllers should be thin — no heavy business logic patterns.
     *
     * Heuristic: flag controllers with > 400 lines or containing SQL patterns.
     */
    private function testNoBusinessLogicInControllers(): void
    {
        $this->report->incrementTests();
        $controllers = $this->findFilesByPattern('/controllers/', 'Controller.php');

        foreach ($controllers as $file) {
            $lines = countCodeLines($file);
            if ($lines > 400) {
                $this->warning(
                    'Controller Thinness',
                    "Controller is too large ({$lines} lines of code)",
                    $file,
                    0,
                    'Extract business logic into a dedicated Service class.'
                );
            }

            // Controllers should not contain SQL
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/\$pdo\s*->\s*(prepare|query|exec)\s*\(/i', $content)) {
                $this->critical(
                    'Controller Thinness',
                    'Direct database access found inside a controller',
                    $file,
                    0,
                    'Controllers should delegate data access to Services/Repositories.'
                );
            }

            // Controllers should not do heavy validation/calculation loops
            // (placeholder for future method-level analysis)
        }
    }

    /**
     * TEST: Helper files should NOT contain SQL queries.
     */
    private function testNoBusinessLogicInHelpers(): void
    {
        $this->report->incrementTests();
        $helperDir = $this->apiRoot . '/shared/helpers';
        $helpers   = scanPhpFiles($helperDir);

        foreach ($helpers as $file) {
            $content = @file_get_contents($file) ?: '';

            // Skip comment lines when checking for SQL
            $codeLines = array_filter(explode("\n", $content), function (string $l) {
                $t = trim($l);
                return $t !== '' && !str_starts_with($t, '//') && !str_starts_with($t, '*') && !str_starts_with($t, '/*') && !str_starts_with($t, '#');
            });
            $codeOnly = implode("\n", $codeLines);

            if (preg_match('/\$pdo\s*->\s*(prepare|query)\s*\(/i', $codeOnly)) {
                $this->critical(
                    'Helper Layer',
                    'Database call found in helper file',
                    $file,
                    0,
                    'Helpers should be pure utility functions. Move DB calls to Repositories.'
                );
            }

            if (preg_match('/\bSELECT\b.*\bFROM\b/i', $codeOnly) && preg_match('/prepare|query|execute/i', $codeOnly)) {
                $this->critical(
                    'Helper Layer',
                    'SQL query pattern found in helper file',
                    $file,
                    0,
                    'Helpers must not contain data-access logic.'
                );
            }
        }
    }

    /**
     * TEST: Service classes should NOT directly use $pdo — they must go through repositories.
     */
    private function testNoDirectDbInServices(): void
    {
        $this->report->incrementTests();
        $services = $this->findFilesByPattern('/services/', 'Service.php');

        foreach ($services as $file) {
            $content = @file_get_contents($file) ?: '';

            if (preg_match('/\$pdo\s*->\s*(prepare|query|exec)\s*\(/i', $content)) {
                $this->critical(
                    'Service Layer',
                    'Direct $pdo usage found in Service class',
                    $file,
                    0,
                    'Services must access the database only through Repository classes.'
                );
            }

            if (preg_match('/\bnew\s+PDO\s*\(/i', $content)) {
                $this->critical(
                    'Service Layer',
                    'Direct PDO instantiation in Service class',
                    $file,
                    0,
                    'Inject PDO through the Repository layer, not directly in Services.'
                );
            }
        }
    }

    /**
     * TEST: Enforce strict layer flow.
     *
     * Routes → Controllers → Services → Repositories.
     * Routes should not reference Repository classes directly.
     * Controllers should not reference Repository classes directly.
     */
    private function testStrictLayerFlow(): void
    {
        $this->report->incrementTests();

        // Routes should not directly CALL repository methods.
        // However, routes ARE allowed to INSTANTIATE repositories for dependency-injection wiring
        // (e.g., $repo = new PdoXxxRepository($pdo); $service = new XxxService($repo); $controller = new XxxController($service);)
        // We only flag when the route calls a method on a repository variable directly.
        $routeFiles = scanPhpFiles($this->apiRoot . '/v1/routes');
        foreach ($routeFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            $codeLines = $this->stripComments($content);

            // Check if route instantiates a repository
            if (preg_match('/new\s+Pdo\w+Repository\s*\(/i', $codeLines)) {
                // Check if route ALSO has a Controller/Service instantiation (proper DI wiring)
                $hasController = preg_match('/new\s+\w+Controller\s*\(/i', $codeLines);
                $hasService    = preg_match('/new\s+\w+Service\s*\(/i', $codeLines);

                // Public sub-routes (loaded by dispatcher) use inline repos for read-heavy endpoints.
                // This is a known pattern — downgrade to info instead of warning.
                $isPublicSubRoute = str_contains($file, '/public/');

                if ($hasController || $hasService) {
                    // This is DI wiring — acceptable. Only flag if repo methods are called directly.
                    if (preg_match('/\$repo(?:sitory)?\s*->\s*(find|get|all|list|save|create|update|delete|count|search|fetch|insert)\w*\s*\(/i', $codeLines)) {
                        $this->warning(
                            'Layer Flow',
                            'Route directly calls Repository methods — bypass Service/Controller layer',
                            $file,
                            0,
                            'Route should delegate to Controller→Service→Repository, not call Repository directly.'
                        );
                    }
                    // Otherwise it's proper DI wiring — no warning
                } else {
                    if ($isPublicSubRoute) {
                        // Public sub-routes with inline repo are a known pattern
                        $this->info(
                            'Layer Flow',
                            'Public sub-route uses Repository directly — consider adding Service layer',
                            $file,
                            0,
                            'For complex logic, add a Service layer between the route and the Repository.'
                        );
                    } else {
                        // Admin/authenticated route instantiates repo without controller/service
                        $this->warning(
                            'Layer Flow',
                            'Route instantiates a Repository without Controller/Service — missing layering',
                            $file,
                            0,
                            'Add a Service and Controller layer between the route and the Repository.'
                        );
                    }
                }
            }
        }

        // Controllers should ideally delegate to Services, not Repositories
        $controllers = $this->findFilesByPattern('/controllers/', 'Controller.php');
        foreach ($controllers as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/new\s+Pdo\w+Repository\s*\(/i', $content)) {
                $this->info(
                    'Layer Flow',
                    'Controller directly instantiates a Repository (prefer Service delegation)',
                    $file,
                    0,
                    'Consider routing data-access through a Service class for better separation.'
                );
            }
        }
    }

    /**
     * TEST: Detect circular dependencies between layers.
     *
     * Repositories should not require/include controllers or services.
     * Services should not require/include controllers.
     */
    private function testCircularDependencies(): void
    {
        $this->report->incrementTests();

        // Repositories must not reference Controller or Service files
        $repos = $this->findFilesByPattern('/repositories/', 'Repository.php');
        foreach ($repos as $file) {
            $content = @file_get_contents($file) ?: '';

            if (preg_match('/require(_once)?\s*.*Controller\.php/i', $content)) {
                $this->critical(
                    'Circular Dependency',
                    'Repository file includes a Controller — circular dependency',
                    $file,
                    0,
                    'Repositories must never depend on Controllers.'
                );
            }
            if (preg_match('/require(_once)?\s*.*Service\.php/i', $content)) {
                $this->warning(
                    'Circular Dependency',
                    'Repository file includes a Service — potential circular dependency',
                    $file,
                    0,
                    'Repositories should be self-contained; consider injecting dependencies.'
                );
            }
        }

        // Services must not reference Controller files
        $services = $this->findFilesByPattern('/services/', 'Service.php');
        foreach ($services as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/require(_once)?\s*.*Controller\.php/i', $content)) {
                $this->critical(
                    'Circular Dependency',
                    'Service file includes a Controller — circular dependency',
                    $file,
                    0,
                    'Services must never depend on Controllers.'
                );
            }
        }
    }

    // Internal helpers ----------------------------------------------------

    /** Find files matching a path segment and filename suffix. */
    private function findFilesByPattern(string $pathSegment, string $suffix): array
    {
        $all = scanPhpFiles($this->apiRoot);
        return array_filter($all, fn(string $f) => str_contains($f, $pathSegment) && str_ends_with(basename($f), $suffix));
    }

    /** Strip single-line and block comments from PHP source. */
    private function stripComments(string $source): string
    {
        // Remove block comments
        $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
        // Remove single-line comments
        $lines = explode("\n", $source);
        $filtered = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if (str_starts_with($t, '//') || str_starts_with($t, '#')) {
                continue;
            }
            $filtered[] = $line;
        }
        return implode("\n", $filtered);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — Performance Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

class PerformanceValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Performance Validation';
    }

    public function run(): void
    {
        $this->testSubqueriesInJoinOn();
        $this->testNPlus1Patterns();
        $this->testSelectStar();
        $this->testDeepPagination();
        $this->testMissingIndexHeuristic();
    }

    /**
     * TEST: Detect correlated subqueries inside JOIN … ON clauses.
     *
     * Pattern: JOIN <table> ON … (SELECT …
     * These cause per-row subquery evaluation and are severe performance killers.
     */
    private function testSubqueriesInJoinOn(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        foreach ($repos as $file) {
            $content = @file_get_contents($file) ?: '';

            // Match: JOIN ... ON ... (SELECT  — but NOT when (SELECT is in a separate statement (no ;)
            if (preg_match('/\bJOIN\b[^;]{0,500}?\bON\b[^;]{0,500}?\(\s*SELECT\b/is', $content)) {
                // Verify it's not just a derived table (JOIN (SELECT ...) alias ON ...)
                // Derived tables have: JOIN (SELECT ...) AS alias ON
                // Correlated subqueries have: JOIN table ON col = (SELECT ...)
                if (!preg_match('/\bJOIN\s*\(\s*SELECT\b/is', $content)) {
                    $this->warning(
                        'Performance',
                        'Subquery detected inside a JOIN ON clause — potential per-row evaluation',
                        $file,
                        0,
                        'Rewrite using a derived table (subquery in FROM) or a pre-computed JOIN.'
                    );
                } else {
                    // Could be both: check for ON ... (SELECT separately
                    if (preg_match('/\bON\b[^;]{0,300}?=\s*\(\s*SELECT\b/is', $content)) {
                        $this->warning(
                            'Performance',
                            'Correlated subquery in ON clause detected alongside derived table',
                            $file,
                            0,
                            'Replace the correlated subquery with a pre-computed JOIN or CTE.'
                        );
                    }
                }
            }
        }
    }

    /**
     * TEST: Detect N+1 query patterns — DB calls inside loops.
     */
    private function testNPlus1Patterns(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        foreach ($allFiles as $file) {
            $content = @file_get_contents($file) ?: '';

            // Pattern: foreach/while/for loop containing $pdo-> or ->prepare( or ->query(
            if (preg_match('/\b(foreach|while|for)\s*\([^)]*\)\s*\{[^}]{0,800}?\$(?:pdo|this->pdo|stmt|db)\s*->\s*(prepare|query|exec)\s*\(/is', $content)) {
                $this->critical(
                    'N+1 Query',
                    'Database query inside a loop detected — N+1 pattern',
                    $file,
                    0,
                    'Batch the query outside the loop or use a single query with IN() / JOIN.'
                );
            }
        }
    }

    /**
     * TEST: Detect SELECT * usage in repository files.
     */
    private function testSelectStar(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        foreach ($repos as $file) {
            $hits = findPatternInFile($file, '/\bSELECT\s+\*/i');
            $selectStarCount = 0;
            $firstLine = 0;
            foreach ($hits as $hit) {
                // Exclude comments
                if (str_starts_with(trim($hit['text']), '//') || str_starts_with(trim($hit['text']), '*') || str_starts_with(trim($hit['text']), '/*')) {
                    continue;
                }
                // Also exclude COUNT(*) which is standard
                if (preg_match('/\bCOUNT\s*\(\s*\*/i', $hit['text'])) {
                    continue;
                }
                $selectStarCount++;
                if ($firstLine === 0) {
                    $firstLine = $hit['line'];
                }
            }
            // Report once per file with count
            if ($selectStarCount > 0) {
                $this->info(
                    'Performance',
                    "SELECT * usage found {$selectStarCount} time(s) — may fetch unnecessary columns",
                    $file,
                    $firstLine,
                    'Explicitly list only the columns you need.'
                );
            }
        }
    }

    /**
     * TEST: Detect deep pagination (OFFSET > 10000).
     */
    private function testDeepPagination(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        foreach ($repos as $file) {
            $content = @file_get_contents($file) ?: '';

            // Hard-coded large offsets — this is a definite warning
            if (preg_match('/\bOFFSET\s+(\d+)/i', $content, $m)) {
                if ((int) $m[1] > 10000) {
                    $this->warning(
                        'Deep Pagination',
                        "Hard-coded OFFSET {$m[1]} — very large offset degrades performance",
                        $file,
                        0,
                        'Use cursor-based (keyset) pagination instead of OFFSET for large datasets.'
                    );
                }
            }

            // Dynamic OFFSET is standard pagination — only flag as info if file has no safeguard
            // and deals with potentially large tables (orders, products, etc.)
            // Skipped: most repos correctly use LIMIT+OFFSET for pagination
        }
    }

    /**
     * TEST: Heuristic check for missing indexes.
     *
     * If a WHERE clause references a column that is not part of the table's
     * primary key or a commonly-indexed pattern (like tenant_id, *_id), flag it.
     */
    private function testMissingIndexHeuristic(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        foreach ($repos as $file) {
            $content = @file_get_contents($file) ?: '';

            // Detect WHERE col LIKE '%...%' (leading wildcard defeats indexes)
            if (preg_match('/WHERE\s+\w+\s+LIKE\s+[\'"]%/i', $content)) {
                $this->warning(
                    'Missing Index',
                    'LIKE with leading wildcard (%) — cannot use index',
                    $file,
                    0,
                    'Use full-text search or remove the leading % for index-friendly queries.'
                );
            }

            // Detect function calls on indexed columns in WHERE
            if (preg_match('/WHERE\s+(LOWER|UPPER|DATE|YEAR|MONTH|TRIM|CONCAT)\s*\(/i', $content)) {
                $this->info(
                    'Missing Index',
                    'Function applied to column in WHERE clause — may prevent index usage',
                    $file,
                    0,
                    'Use a computed/virtual column or rewrite the query to avoid wrapping columns in functions.'
                );
            }
        }
    }

    /** @return string[] */
    private function getRepositoryFiles(): array
    {
        $all = scanPhpFiles($this->apiRoot);
        return array_values(array_filter($all, fn(string $f) => str_contains(basename($f), 'Repository') && str_ends_with($f, '.php')));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — Multi-Tenant Safety Module
// ═══════════════════════════════════════════════════════════════════════════════

class MultiTenantSafety extends BaseArchTest
{
    public function name(): string
    {
        return 'Multi-Tenant Safety';
    }

    public function run(): void
    {
        $this->testTenantIdInQueries();
        $this->testMissingTenantFilters();
    }

    /**
     * TEST: Repository queries that reference certain tables should include a tenant_id filter.
     *
     * Heuristic: if a query has FROM <table> but no tenant_id condition, it may leak data.
     * We only flag tables that are known to be tenant-scoped (orders, products, entities, etc.).
     */
    private function testTenantIdInQueries(): void
    {
        $this->report->incrementTests();

        // Tables that should be tenant-scoped in a multi-tenant system.
        // Exclude system-level tables (tenants, currencies, countries, languages, etc.)
        $tenantTables = [
            'orders', 'products', 'entities', 'carts', 'cart_items',
            'ads', 'banners', 'support_tickets', 'notifications',
            'jobs', 'auctions', 'subscriptions',
        ];
        $pattern = '/\bFROM\s+(' . implode('|', $tenantTables) . ')\b/i';

        // Tables/repos to skip — these legitimately handle cross-tenant or system queries
        $skipRepoPatterns = [
            'Tenant', 'Auth', 'Rbac', 'Migration', 'System', 'Settings',
        ];

        $repos = $this->getRepositoryFiles();
        foreach ($repos as $file) {
            $basename = basename($file);
            // Skip system-level repos that don't need tenant filtering
            $skip = false;
            foreach ($skipRepoPatterns as $sp) {
                if (stripos($basename, $sp) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $content = @file_get_contents($file) ?: '';

            // Use offset tracking to find each unique occurrence
            $offset = 0;
            while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $table = $match[1][0];
                $pos   = $match[0][1];
                $offset = $pos + strlen($match[0][0]);

                // Search within a 1500-char window around this FROM position
                $windowStart = max(0, $pos - 200);
                $window = substr($content, $windowStart, 1500);
                if (!preg_match('/tenant_id/i', $window)) {
                    $this->warning(
                        'Multi-Tenant',
                        "Query on '{$table}' may lack tenant_id filter",
                        $file,
                        0,
                        "Ensure all queries on '{$table}' include a tenant_id condition to prevent data leakage."
                    );
                    // Only report once per table per file to reduce noise
                    break;
                }
            }
        }
    }

    /**
     * TEST: Detect repository methods that return data without accepting a tenant parameter.
     *
     * Heuristic: public methods named list/all/find* that don't mention tenant_id in their body.
     */
    private function testMissingTenantFilters(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        // Skip repos for system/global tables that are not tenant-scoped
        $skipRepoPatterns = [
            'Tenant', 'Auth', 'Rbac', 'Migration', 'System', 'Settings',
            'Currency', 'Country', 'Language', 'Timezone', 'Unit',
            'City', 'Certificate', 'Jwt', 'Mail', 'Sms', 'Seo',
            'Upload', 'I18n', 'Notification', 'Audit',
        ];

        foreach ($repos as $file) {
            $basename = basename($file);
            $skip = false;
            foreach ($skipRepoPatterns as $sp) {
                if (stripos($basename, $sp) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $content = @file_get_contents($file) ?: '';

            // Only flag the file once (not every method) to reduce noise
            $flagged = false;

            // Find public functions named list, all, find, get
            if (preg_match_all('/public\s+function\s+(list|all|find\w*|get\w*)\s*\(([^)]*)\)/i', $content, $methods, PREG_SET_ORDER)) {
                foreach ($methods as $method) {
                    if ($flagged) break;
                    $name   = $method[1];
                    $params = $method[2];

                    // If the function parameters don't mention tenantId AND the body doesn't mention tenant_id
                    if (!preg_match('/tenant/i', $params)) {
                        // Check body (rough: next 2000 chars)
                        $fnPos = strpos($content, $method[0]);
                        if ($fnPos === false) {
                            continue;
                        }
                        $bodyWindow = substr($content, $fnPos, 2000);
                        if (!preg_match('/tenant_id/i', $bodyWindow)) {
                            $this->info(
                                'Multi-Tenant',
                                "Repository has methods that may lack tenant scoping (e.g. '{$name}()')",
                                $file,
                                0,
                                'Consider adding a $tenantId parameter or ensure tenant filtering is applied.'
                            );
                            $flagged = true;
                        }
                    }
                }
            }
        }
    }

    /** @return string[] */
    private function getRepositoryFiles(): array
    {
        $all = scanPhpFiles($this->apiRoot);
        return array_values(array_filter($all, fn(string $f) => str_contains(basename($f), 'Repository') && str_ends_with($f, '.php')));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — Security Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

class SecurityValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Security Validation';
    }

    public function run(): void
    {
        $this->testRawSqlConcatenation();
        $this->testPreparedStatements();
        $this->testWeakRbac();
        $this->testMissingValidation();
    }

    /**
     * TEST: Detect raw string concatenation in SQL queries — SQL injection risk.
     *
     * Pattern: "SELECT ... " . $var   or   "WHERE id = $var"   (without binding)
     */
    private function testRawSqlConcatenation(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        foreach ($allFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            $lines   = explode("\n", $content);

            foreach ($lines as $idx => $line) {
                $t = trim($line);
                if (str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '#')) {
                    continue;
                }

                // Pattern: "SELECT|INSERT|UPDATE|DELETE ... " . $variable
                if (preg_match('/["\'](?:SELECT|INSERT|UPDATE|DELETE)\b[^"\']*["\']\s*\.\s*\$/i', $t)) {
                    // Exclude safe patterns like table-name variables that are whitelisted
                    if (!preg_match('/\.\s*\$(?:table|orderBy|orderDir|direction|where|sql|limit|offset)\b/i', $t)) {
                        $this->critical(
                            'SQL Injection',
                            'Possible SQL injection — string concatenation with variable in SQL',
                            $file,
                            $idx + 1,
                            'Use prepared statements with bound parameters instead of concatenation.'
                        );
                    }
                }

                // Pattern: "WHERE col = {$var}" or "WHERE col = ' . $var"
                if (preg_match('/WHERE\b[^"\']*(?:\{\$\w+\}|["\']\.?\s*\$\w+)/i', $t)) {
                    if (!preg_match('/\$(?:table|sql|where|order)/i', $t)) {
                        $this->warning(
                            'SQL Injection',
                            'Variable interpolation in WHERE clause — potential injection vector',
                            $file,
                            $idx + 1,
                            'Always use parameter binding (:param or ?) instead of variable interpolation.'
                        );
                    }
                }
            }
        }
    }

    /**
     * TEST: Ensure query execution uses prepared statements, not raw query().
     */
    private function testPreparedStatements(): void
    {
        $this->report->incrementTests();
        $repos = $this->getRepositoryFiles();

        foreach ($repos as $file) {
            $content = @file_get_contents($file) ?: '';

            // $pdo->query() with variables is dangerous
            if (preg_match('/\$pdo\s*->\s*query\s*\(\s*["\']/i', $content)) {
                // Only flag if there's variable interpolation
                if (preg_match('/\$pdo\s*->\s*query\s*\(\s*"[^"]*\$/i', $content) ||
                    preg_match('/\$pdo\s*->\s*query\s*\(\s*["\'].*\.\s*\$/i', $content)) {
                    $this->critical(
                        'Prepared Statements',
                        'pdo->query() used with variable input — use prepare() instead',
                        $file,
                        0,
                        'Replace $pdo->query() with $pdo->prepare() + execute() for parameterized queries.'
                    );
                }
            }
        }
    }

    /**
     * TEST: Detect weak RBAC patterns — routes/controllers that lack permission checks.
     */
    private function testWeakRbac(): void
    {
        $this->report->incrementTests();
        $routeFiles = scanPhpFiles($this->apiRoot . '/v1/routes');

        // Exclude public routes and auth routes
        $filtered = array_filter($routeFiles, function (string $f) {
            $base = basename($f);
            return !str_contains($f, '/public/')
                && $base !== 'auth.php'
                && $base !== 'health.php'
                && $base !== 'diagnostic.php'
                && $base !== 'public.php';
        });

        foreach ($filtered as $file) {
            $content = @file_get_contents($file) ?: '';

            // Routes handling POST/PUT/DELETE should have some auth/permission check.
            // Auth may be in the file itself, in an included bootstrap, or via controller.
            if (preg_match('/POST|PUT|PATCH|DELETE/i', $content)) {
                $hasAuth = preg_match('/auth|permission|rbac|middleware|token|jwt|session|bootstrap/i', $content);
                // Also check if the file includes bootstrap.php (which handles JWT/session auth)
                $hasBootstrap = preg_match('/require.*bootstrap/i', $content);
                // Or if it delegates to a controller that handles auth
                $hasController = preg_match('/\$controller\s*->/i', $content);
                if (!$hasAuth && !$hasBootstrap && !$hasController) {
                    $this->info(
                        'RBAC',
                        'Route handles write operations but no visible auth/permission check',
                        $file,
                        0,
                        'Ensure all write endpoints are protected by authentication and authorization.'
                    );
                }
            }
        }
    }

    /**
     * TEST: Detect missing input validation in routes/controllers.
     */
    private function testMissingValidation(): void
    {
        $this->report->incrementTests();
        $routeFiles = scanPhpFiles($this->apiRoot . '/v1/routes');

        foreach ($routeFiles as $file) {
            $content = @file_get_contents($file) ?: '';

            // If the route handles POST and uses $_POST/$_REQUEST but no validation
            if (preg_match('/\$_POST|\$_REQUEST|file_get_contents\s*\(\s*[\'"]php:\/\/input[\'"]\s*\)/i', $content)) {
                $hasValidation = preg_match('/filter_var|validate|Validator|htmlspecialchars|strip_tags|preg_match|is_numeric|intval|trim|empty|isset|json_decode/i', $content);
                // Also check if route delegates to a controller that handles validation
                $hasController = preg_match('/\$controller\s*->/i', $content);
                if (!$hasValidation && !$hasController) {
                    $this->warning(
                        'Input Validation',
                        'Route reads user input but no validation/sanitization detected',
                        $file,
                        0,
                        'Always validate and sanitize user input before processing.'
                    );
                }
            }
        }
    }

    /** @return string[] */
    private function getRepositoryFiles(): array
    {
        $all = scanPhpFiles($this->apiRoot);
        return array_values(array_filter($all, fn(string $f) => str_contains(basename($f), 'Repository') && str_ends_with($f, '.php')));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 8 — Code Quality Module
// ═══════════════════════════════════════════════════════════════════════════════

class CodeQualityValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Code Quality';
    }

    public function run(): void
    {
        $this->testLargeClasses();
        $this->testLargeMethods();
        $this->testGodClasses();
        $this->testDuplicatedLogicPatterns();
    }

    /**
     * TEST: Detect classes exceeding 500 lines of code.
     */
    private function testLargeClasses(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        foreach ($allFiles as $file) {
            // Only check files containing class definitions
            $content = @file_get_contents($file) ?: '';
            if (!preg_match('/\bclass\s+\w+/i', $content)) {
                continue;
            }

            $lines = countCodeLines($file);
            if ($lines > 500) {
                $this->warning(
                    'Large Class',
                    "Class file has {$lines} lines of code (threshold: 500)",
                    $file,
                    0,
                    'Consider splitting this class into smaller, focused classes.'
                );
            }
        }
    }

    /**
     * TEST: Detect methods exceeding 50 lines.
     */
    private function testLargeMethods(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        foreach ($allFiles as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            $inMethod    = false;
            $methodName  = '';
            $methodStart = 0;
            $braceCount  = 0;
            $methodLines = 0;

            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);

                // Detect method start
                if (!$inMethod && preg_match('/(?:public|protected|private|static)\s+function\s+(\w+)\s*\(/i', $trimmed, $m)) {
                    $inMethod    = true;
                    $methodName  = $m[1];
                    $methodStart = $idx + 1;
                    $braceCount  = 0;
                    $methodLines = 0;
                }

                if ($inMethod) {
                    $braceCount += substr_count($trimmed, '{') - substr_count($trimmed, '}');
                    if ($trimmed !== '' && !str_starts_with($trimmed, '//') && !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '/*')) {
                        $methodLines++;
                    }

                    if ($braceCount <= 0 && $methodLines > 1) {
                        // Method ended
                        if ($methodLines > 50) {
                            // Only flag as warning for very large methods (> 80 lines),
                            // use info for moderately large methods (51-80 lines)
                            if ($methodLines > 80) {
                                $this->warning(
                                    'Large Method',
                                    "Method '{$methodName}()' has ~{$methodLines} lines (threshold: 80)",
                                    $file,
                                    $methodStart,
                                    'Break this method into smaller, single-responsibility methods.'
                                );
                            } else {
                                $this->info(
                                    'Large Method',
                                    "Method '{$methodName}()' has ~{$methodLines} lines (threshold: 50)",
                                    $file,
                                    $methodStart,
                                    'Consider breaking this method into smaller parts.'
                                );
                            }
                        }
                        $inMethod = false;
                    }
                }
            }
        }
    }

    /**
     * TEST: Detect God classes — classes with too many public methods.
     *
     * Heuristic: a class with > 25 public methods is likely doing too much.
     * Repository classes with standard CRUD + filters naturally have many methods.
     */
    private function testGodClasses(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        foreach ($allFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            if (!preg_match('/\bclass\s+\w+/i', $content)) {
                continue;
            }

            $publicMethods = preg_match_all('/\bpublic\s+function\s+\w+\s*\(/i', $content);
            if ($publicMethods > 25) {
                $this->warning(
                    'God Class',
                    "Class has {$publicMethods} public methods — potential God class",
                    $file,
                    0,
                    'Split responsibilities into separate classes following the Single Responsibility Principle.'
                );
            }
        }
    }

    /**
     * TEST: Detect duplicated logic patterns across files.
     *
     * Looks for common business operations repeated in multiple files:
     * validate, calculate, permission checks, status transitions.
     */
    private function testDuplicatedLogicPatterns(): void
    {
        $this->report->incrementTests();

        $patterns = [
            'permission_check' => '/if\s*\(\s*!\s*\$.*(?:permission|hasPermission|can|isAllowed)\s*\(/i',
            'status_transition' => '/\$.*status\s*=\s*[\'"][a-z_]+[\'"]\s*;/i',
            'email_validation'  => '/filter_var\s*\(\s*\$.*FILTER_VALIDATE_EMAIL/i',
        ];

        foreach ($patterns as $patternName => $regex) {
            $filesWithPattern = [];
            $allFiles = scanPhpFiles($this->apiRoot);

            foreach ($allFiles as $file) {
                $content = @file_get_contents($file) ?: '';
                $count = preg_match_all($regex, $content);
                if ($count >= 3) {
                    $filesWithPattern[] = $this->short($file) . " ({$count}×)";
                }
            }

            if (count($filesWithPattern) > 3) {
                $this->info(
                    'Duplicated Logic',
                    "Pattern '{$patternName}' repeated in " . count($filesWithPattern) . ' files',
                    '',
                    0,
                    'Consider extracting shared logic into a reusable Service or Trait.'
                );
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 9 — Runtime Simulation Module
// ═══════════════════════════════════════════════════════════════════════════════

class RuntimeSimulation extends BaseArchTest
{
    public function name(): string
    {
        return 'Runtime Simulation';
    }

    public function run(): void
    {
        $this->testFileLoadPerformance();
        $this->testSimulatedConcurrentRequests();
    }

    /**
     * TEST: Measure how long it takes to require_once every PHP file (cold load).
     *
     * This simulates the cost of including files without actually executing them.
     */
    private function testFileLoadPerformance(): void
    {
        $this->report->incrementTests();
        $allFiles = scanPhpFiles($this->apiRoot);

        $slowFiles = [];
        foreach ($allFiles as $file) {
            $size = filesize($file);
            if ($size === false) {
                continue;
            }
            // Approximate: large files will be slow to parse
            if ($size > 50 * 1024) { // > 50 KB
                $slowFiles[] = ['file' => $file, 'size' => $size];
            }
        }

        foreach ($slowFiles as $sf) {
            $kb = round($sf['size'] / 1024, 1);
            $this->info(
                'File Size',
                "Large file ({$kb} KB) — may slow down include/autoload",
                $sf['file'],
                0,
                'Consider splitting into smaller, lazy-loaded modules.'
            );
        }
    }

    /**
     * TEST: Simulate 1000 concurrent "requests" (basic loop simulation).
     *
     * We measure how long it takes to:
     *   1. Scan all route files
     *   2. Parse a representative route (string operations)
     *   3. Measure average per-request overhead
     */
    private function testSimulatedConcurrentRequests(): void
    {
        $this->report->incrementTests();
        $routeFiles = scanPhpFiles($this->apiRoot . '/v1/routes');

        if (empty($routeFiles)) {
            $this->info('Runtime', 'No route files found — skipping simulation.', '', 0, '');
            return;
        }

        $iterations = 1000;
        $sampleFile = $routeFiles[array_rand($routeFiles)];
        $sampleContent = @file_get_contents($sampleFile) ?: '';

        // Simulate request processing: read content + basic string ops
        $totalMs = measureTime(function () use ($iterations, $routeFiles, $sampleContent) {
            for ($i = 0; $i < $iterations; $i++) {
                // Simulate route matching
                $target = $routeFiles[$i % count($routeFiles)];
                $content = @file_get_contents($target) ?: '';
                // Simulate basic parsing
                preg_match_all('/\bfunction\s+\w+/i', $content, $m);
                // Simulate response building
                json_encode(['status' => 'ok', 'iteration' => $i]);
            }
        });

        $avgMs = round($totalMs / $iterations, 2);
        $totalSec = round($totalMs / 1000, 2);

        if ($avgMs > 100) {
            $this->warning(
                'Runtime',
                "Average simulated request time: {$avgMs} ms (> 100 ms threshold) — total: {$totalSec}s for {$iterations} iterations",
                '',
                0,
                'Investigate slow file I/O or consider opcode caching (OPcache).'
            );
        } elseif ($avgMs > 50) {
            $this->info(
                'Runtime',
                "Average simulated request time: {$avgMs} ms — total: {$totalSec}s for {$iterations} iterations",
                '',
                0,
                'Performance is acceptable but could be optimized with caching.'
            );
        }
        // Always add a summary regardless
        $this->info(
            'Runtime Summary',
            "Simulation complete: {$iterations} requests in {$totalSec}s (avg {$avgMs} ms/req)",
            '',
            0,
            ''
        );
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 10 — Test Runner & Report Formatter
// ═══════════════════════════════════════════════════════════════════════════════

class AdvancedSystemTestRunner
{
    private string     $projectRoot;
    private TestReport $report;
    /** @var BaseArchTest[] */
    private array $modules = [];

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->report      = new TestReport();
    }

    /** Register a test module. */
    public function addModule(BaseArchTest $module): self
    {
        $this->modules[] = $module;
        return $this;
    }

    /** Run all registered modules and return the report. */
    public function run(): TestReport
    {
        foreach ($this->modules as $module) {
            $module->run();
        }
        return $this->report;
    }

    public function getReport(): TestReport
    {
        return $this->report;
    }

    /**
     * Build all default modules.
     */
    public static function createDefault(string $projectRoot): self
    {
        $runner = new self($projectRoot);
        $report = $runner->getReport();

        $runner->addModule(new ArchitectureValidation($report, $projectRoot));
        $runner->addModule(new PerformanceValidation($report, $projectRoot));
        $runner->addModule(new MultiTenantSafety($report, $projectRoot));
        $runner->addModule(new SecurityValidation($report, $projectRoot));
        $runner->addModule(new CodeQualityValidation($report, $projectRoot));
        $runner->addModule(new RuntimeSimulation($report, $projectRoot));

        return $runner;
    }

    // ─── Formatters ──────────────────────────────────────────────────────

    /**
     * Render results as plain-text suitable for CLI output.
     */
    public static function formatCli(TestReport $report): string
    {
        $out = '';
        $out .= "╔══════════════════════════════════════════════════════════════╗\n";
        $out .= "║  ADVANCED ARCHITECTURE & PERFORMANCE TEST REPORT            ║\n";
        $out .= "╚══════════════════════════════════════════════════════════════╝\n\n";

        $summary = $report->summary();
        $score   = $report->score();

        $out .= "  Tests run : {$report->getTestsRun()}\n";
        $out .= "  Score     : {$score} / 100\n";
        $out .= "  Critical  : {$summary[Severity::CRITICAL]}\n";
        $out .= "  Warnings  : {$summary[Severity::WARNING]}\n";
        $out .= "  Info      : {$summary[Severity::INFO]}\n\n";

        // Grade
        $grade = match (true) {
            $score >= 90 => '🟢 A — Excellent',
            $score >= 75 => '🟡 B — Good',
            $score >= 60 => '🟠 C — Fair',
            $score >= 40 => '🔴 D — Poor',
            default      => '⛔ F — Critical',
        };
        $out .= "  Grade     : {$grade}\n";
        $out .= "  " . str_repeat('─', 58) . "\n\n";

        // Group findings by severity
        foreach ([Severity::CRITICAL, Severity::WARNING, Severity::INFO] as $sev) {
            $items = array_filter($report->getFindings(), fn(Finding $f) => $f->severity === $sev);
            if (empty($items)) {
                continue;
            }

            $icon = match ($sev) {
                Severity::CRITICAL => '❌',
                Severity::WARNING  => '⚠️',
                Severity::INFO     => 'ℹ️',
            };

            $out .= "  {$icon} {$sev} (" . count($items) . ")\n";
            $out .= "  " . str_repeat('─', 50) . "\n";

            foreach ($items as $f) {
                $loc = $f->file;
                if ($f->line > 0) {
                    $loc .= ":{$f->line}";
                }
                $out .= "  [{$f->category}] {$f->message}\n";
                if ($loc) {
                    $out .= "    → {$loc}\n";
                }
                if ($f->suggestion) {
                    $out .= "    💡 {$f->suggestion}\n";
                }
                $out .= "\n";
            }
        }

        if (empty($report->getFindings())) {
            $out .= "  ✅ ALL TESTS PASSED — Architecture is clean!\n";
        }

        return $out;
    }

    /**
     * Render results as an HTML page suitable for browser display.
     */
    public static function formatHtml(TestReport $report): string
    {
        $summary = $report->summary();
        $score   = $report->score();

        $grade = match (true) {
            $score >= 90 => ['A', '#4CAF50', 'Excellent'],
            $score >= 75 => ['B', '#8BC34A', 'Good'],
            $score >= 60 => ['C', '#FF9800', 'Fair'],
            $score >= 40 => ['D', '#f44336', 'Poor'],
            default      => ['F', '#9C27B0', 'Critical'],
        };

        $generatedAt = date('Y-m-d H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Architecture &amp; Performance Report</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0d1117; color: #c9d1d9; padding: 24px; }
  .container { max-width: 1200px; margin: 0 auto; }
  h1 { color: #58a6ff; margin-bottom: 8px; font-size: 1.8em; }
  .subtitle { color: #8b949e; margin-bottom: 24px; }
  .score-card { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
  .score-box { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px 24px; text-align: center; min-width: 120px; }
  .score-box .label { color: #8b949e; font-size: 0.85em; text-transform: uppercase; }
  .score-box .value { font-size: 2em; font-weight: bold; margin-top: 4px; }
  .grade { font-size: 3em; font-weight: bold; }
  .section { background: #161b22; border: 1px solid #30363d; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
  .section-header { padding: 12px 16px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
  .section-header:hover { background: #1c2128; }
  .section-body { padding: 0 16px 12px; }
  .finding { padding: 10px 0; border-bottom: 1px solid #21262d; }
  .finding:last-child { border-bottom: none; }
  .finding .cat { color: #58a6ff; font-weight: 600; }
  .finding .msg { margin-top: 4px; }
  .finding .loc { color: #8b949e; font-size: 0.85em; margin-top: 2px; }
  .finding .sug { color: #3fb950; font-size: 0.85em; margin-top: 4px; }
  .sev-CRITICAL { border-left: 4px solid #f85149; }
  .sev-WARNING { border-left: 4px solid #d29922; }
  .sev-INFO { border-left: 4px solid #58a6ff; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; }
  .badge-CRITICAL { background: #f8514922; color: #f85149; }
  .badge-WARNING { background: #d2992222; color: #d29922; }
  .badge-INFO { background: #58a6ff22; color: #58a6ff; }
  .pass-msg { color: #3fb950; font-size: 1.2em; padding: 24px; text-align: center; }
  pre { background: #0d1117; border: 1px solid #30363d; border-radius: 4px; padding: 8px; font-size: 0.85em; overflow-x: auto; margin: 8px 0; }
</style>
</head>
<body>
<div class="container">
  <h1>🏗️ Architecture &amp; Performance Report</h1>
  <p class="subtitle">Generated: {$generatedAt}</p>

  <div class="score-card">
    <div class="score-box">
      <div class="label">Score</div>
      <div class="value" style="color: {$grade[1]}">{$score}</div>
    </div>
    <div class="score-box">
      <div class="label">Grade</div>
      <div class="grade" style="color: {$grade[1]}">{$grade[0]}</div>
      <div style="font-size:0.8em; color:#8b949e">{$grade[2]}</div>
    </div>
    <div class="score-box">
      <div class="label">Tests Run</div>
      <div class="value">{$report->getTestsRun()}</div>
    </div>
    <div class="score-box">
      <div class="label">Critical</div>
      <div class="value" style="color: #f85149">{$summary[Severity::CRITICAL]}</div>
    </div>
    <div class="score-box">
      <div class="label">Warnings</div>
      <div class="value" style="color: #d29922">{$summary[Severity::WARNING]}</div>
    </div>
    <div class="score-box">
      <div class="label">Info</div>
      <div class="value" style="color: #58a6ff">{$summary[Severity::INFO]}</div>
    </div>
  </div>

HTML;

        if (empty($report->getFindings())) {
            $html .= '<div class="pass-msg">✅ ALL TESTS PASSED — Architecture is clean!</div>';
        } else {
            foreach ([Severity::CRITICAL, Severity::WARNING, Severity::INFO] as $sev) {
                $items = array_filter($report->getFindings(), fn(Finding $f) => $f->severity === $sev);
                if (empty($items)) {
                    continue;
                }

                $icon = match ($sev) {
                    Severity::CRITICAL => '❌',
                    Severity::WARNING  => '⚠️',
                    Severity::INFO     => 'ℹ️',
                };

                $count = count($items);
                $html .= "<div class=\"section\">";
                $html .= "<div class=\"section-header\">{$icon} {$sev} ({$count})<span class=\"badge badge-{$sev}\">{$count}</span></div>";
                $html .= "<div class=\"section-body\">";

                foreach ($items as $f) {
                    $loc = htmlspecialchars($f->file, ENT_QUOTES, 'UTF-8');
                    if ($f->line > 0) {
                        $loc .= ":{$f->line}";
                    }
                    $msg = htmlspecialchars($f->message, ENT_QUOTES, 'UTF-8');
                    $cat = htmlspecialchars($f->category, ENT_QUOTES, 'UTF-8');
                    $sug = htmlspecialchars($f->suggestion, ENT_QUOTES, 'UTF-8');

                    $html .= "<div class=\"finding sev-{$sev}\">";
                    $html .= "<span class=\"cat\">[{$cat}]</span>";
                    $html .= "<div class=\"msg\">{$msg}</div>";
                    if ($loc) {
                        $html .= "<div class=\"loc\">📁 {$loc}</div>";
                    }
                    if ($sug) {
                        $html .= "<div class=\"sug\">💡 {$sug}</div>";
                    }
                    $html .= "</div>";
                }

                $html .= "</div></div>";
            }
        }

        $html .= <<<HTML

</div>
</body>
</html>
HTML;

        return $html;
    }
}
