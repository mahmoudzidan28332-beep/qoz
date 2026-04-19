<?php

declare(strict_types=1);

/**
 * AdvancedSystemTest.php — World-Class Architecture & Performance Test Suite
 *
 * Validates the entire PHP MVC codebase for:
 *   - Architecture integrity (strict layer flow, circular dependencies)
 *   - Performance anti-patterns (N+1, wildcard LIKE, deep pagination, subqueries)
 *   - Multi-tenant data safety (tenant_id scoping, missing filters)
 *   - Security (SQL injection, prepared statements, RBAC, input validation,
 *               hardcoded credentials, path traversal, CSRF vectors)
 *   - Type safety (strict_types, return types, param types)
 *   - Configuration safety (debug flags, env leaks, hardcoded secrets)
 *   - Exception handling quality (swallowed exceptions, over-broad catches)
 *   - Code quality metrics (large classes/methods, God classes, duplication)
 *   - Runtime simulation (file-load cost, parse overhead)
 *
 * Improvements over v1:
 *   ✔ FileCache singleton — every file is read exactly once
 *   ✔ Config class — all thresholds in one place
 *   ✔ Module timing — see how long each module takes
 *   ✔ Fixed logical bug: !preg_match() === false (operator precedence)
 *   ✔ Fixed RuntimeSimulation — no longer reads 1000 real files
 *   ✔ Fixed O(n²) in DuplicatedLogicPatterns
 *   ✔ Four output formats: CLI, HTML, JSON, Markdown
 *   ✔ Weighted scoring per severity category
 *   ✔ Three new modules: TypeSafety, ConfigurationSafety, ExceptionHandling
 *   ✔ Full PSR-12 compliance throughout
 *
 * Usage (CLI):
 *   php AdvancedSystemTest.php [/path/to/project]
 *
 * Usage (Browser):
 *   Navigate to AdvancedSystemTest.php?format=html
 *
 * @version  3.0.0
 * @license  MIT
 */

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Configuration (all thresholds in one place)
// ═══════════════════════════════════════════════════════════════════════════════

final class Config
{
    // Code-size thresholds (lines of code, excluding blank lines & comments)
    public const MAX_CLASS_LINES         = 500;
    public const MAX_METHOD_LINES_WARN   = 80;   // WARNING
    public const MAX_METHOD_LINES_INFO   = 50;   // INFO
    public const MAX_PUBLIC_METHODS      = 25;   // God-class threshold
    public const MAX_FILE_SIZE_KB        = 50;   // Large-file warning

    // Pagination
    public const MAX_SAFE_OFFSET         = 10_000;

    // Duplication: flag if a pattern appears in >= N files
    public const DUPLICATION_FILE_THRESHOLD = 3;
    public const DUPLICATION_MIN_OCCURRENCES = 3;

    // Runtime simulation
    public const SIMULATION_ITERATIONS   = 1_000;
    public const SIM_WARN_MS_PER_ITER    = 100.0;  // ms — WARNING
    public const SIM_INFO_MS_PER_ITER    = 50.0;   // ms — INFO

    // Scoring deductions (capped independently)
    public const DEDUCT_CRITICAL         = 5;
    public const DEDUCT_WARNING          = 2;
    public const DEDUCT_INFO             = 0.5;
    public const CAP_CRITICAL            = 60;
    public const CAP_WARNING             = 30;
    public const CAP_INFO                = 10;

    // Tables that must be tenant-scoped
    public const TENANT_TABLES = [
        'orders', 'products', 'entities', 'carts', 'cart_items',
        'ads', 'banners', 'support_tickets', 'notifications',
        'jobs', 'auctions', 'subscriptions', 'invoices',
        'transactions', 'reviews', 'wishlists',
    ];

    // Repository/service/class name segments to skip for tenant checks
    public const SKIP_TENANT_PATTERNS = [
        'Tenant', 'Auth', 'Rbac', 'Migration', 'System', 'Settings',
        'Currency', 'Country', 'Language', 'Timezone', 'Unit',
        'City', 'Certificate', 'Jwt', 'Mail', 'Sms', 'Seo',
        'Upload', 'I18n', 'Audit', 'Cache', 'Queue', 'Event',
    ];

    // Route files excluded from auth/RBAC checks (they are intentionally public)
    public const PUBLIC_ROUTE_FILES = [
        'public.php', 'auth.php', 'health.php', 'diagnostic.php',
    ];

    // Patterns considered "safe" variable names in dynamic SQL (not injection risks)
    public const SAFE_SQL_VARS = [
        'table', 'orderBy', 'orderDir', 'direction', 'where', 'sql',
        'limit', 'offset', 'sortField', 'sortDir', 'column',
    ];

    /** Returns true if the basename matches any skip pattern. */
    public static function shouldSkipTenant(string $basename): bool
    {
        foreach (self::SKIP_TENANT_PATTERNS as $pattern) {
            if (stripos($basename, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Returns true if the basename is a known public route file. */
    public static function isPublicRouteFile(string $basename): bool
    {
        return in_array(strtolower($basename), self::PUBLIC_ROUTE_FILES, true);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — FileCache (read each file exactly once)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Singleton cache so every test module can share the same file reads.
 *
 * Stores: raw content, line array, comment-stripped content, code-line count.
 */
final class FileCache
{
    private static ?self $instance = null;

    /** @var array<string, string> */
    private array $rawContent = [];

    /** @var array<string, string[]> */
    private array $lines = [];

    /** @var array<string, string> */
    private array $stripped = [];

    /** @var array<string, int> */
    private array $codeLines = [];

    private function __construct() {}

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Raw file content (empty string on failure). */
    public function content(string $path): string
    {
        if (!isset($this->rawContent[$path])) {
            $this->rawContent[$path] = (string) (@file_get_contents($path) ?: '');
        }
        return $this->rawContent[$path];
    }

    /**
     * File as an array of lines (without line-ending characters).
     *
     * @return string[]
     */
    public function lines(string $path): array
    {
        if (!isset($this->lines[$path])) {
            $raw = $this->content($path);
            $this->lines[$path] = $raw !== '' ? explode("\n", $raw) : [];
        }
        return $this->lines[$path];
    }

    /**
     * Content with block-comments and single-line-comments removed.
     * Useful for pattern matching that must ignore comment-only lines.
     */
    public function stripped(string $path): string
    {
        if (!isset($this->stripped[$path])) {
            $src = $this->content($path);
            // Remove /* ... */ block comments (non-greedy, DOTALL)
            $src = (string) (preg_replace('#/\*.*?\*/#s', '', $src) ?? $src);
            // Remove // and # single-line comments
            $lines = explode("\n", $src);
            $out   = [];
            foreach ($lines as $line) {
                $t = ltrim($line);
                if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '#')) {
                    continue;
                }
                $out[] = $line;
            }
            $this->stripped[$path] = implode("\n", $out);
        }
        return $this->stripped[$path];
    }

    /**
     * Count non-blank, non-comment lines of code.
     */
    public function codeLineCount(string $path): int
    {
        if (!isset($this->codeLines[$path])) {
            $lines   = $this->lines($path);
            $count   = 0;
            $inBlock = false;

            foreach ($lines as $line) {
                $t = trim($line);

                if ($t === '') {
                    continue;
                }

                if (!$inBlock && (str_starts_with($t, '/*') || str_starts_with($t, '/**'))) {
                    $inBlock = true;
                }

                if ($inBlock) {
                    if (str_contains($t, '*/')) {
                        $inBlock = false;
                    }
                    continue;
                }

                if (str_starts_with($t, '//') || str_starts_with($t, '#')) {
                    continue;
                }

                $count++;
            }

            $this->codeLines[$path] = $count;
        }

        return $this->codeLines[$path];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — Helper Utilities
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Recursively scan a directory for PHP files.
 *
 * @return string[]
 */
function scanPhpFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files); // deterministic ordering
    return $files;
}

/**
 * Measure execution time of a callable.
 *
 * @return float  Elapsed time in milliseconds.
 */
function measureTime(callable $fn): float
{
    $start = hrtime(true);
    $fn();
    return (hrtime(true) - $start) / 1e6;
}

/**
 * Return the path relative to project root for display purposes.
 */
function shortPath(string $fullPath, string $root): string
{
    $root = rtrim($root, '/\\');
    if (str_starts_with($fullPath, $root)) {
        return ltrim(substr($fullPath, strlen($root)), '/\\');
    }
    return basename($fullPath);
}

/**
 * Determine the line number of a byte-offset within a string.
 */
function offsetToLine(string $content, int $offset): int
{
    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

/**
 * Return true if the trimmed line is a comment (does not contain executable code).
 */
function isCommentLine(string $line): bool
{
    $t = ltrim($line);
    return $t === ''
        || str_starts_with($t, '//')
        || str_starts_with($t, '#')
        || str_starts_with($t, '*')
        || str_starts_with($t, '/*');
}

/**
 * Check whether a variable name is in the "safe" SQL variable whitelist.
 */
function isSafeSqlVar(string $varName): bool
{
    $lower = strtolower(trim($varName, '$'));
    foreach (Config::SAFE_SQL_VARS as $safe) {
        if (stripos($lower, strtolower($safe)) !== false) {
            return true;
        }
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Result Containers
// ═══════════════════════════════════════════════════════════════════════════════

/** Severity constants — these are the ONLY valid values for Finding::$severity. */
final class Severity
{
    public const CRITICAL = 'CRITICAL';
    public const WARNING  = 'WARNING';
    public const INFO     = 'INFO';

    /** @return string[] */
    public static function all(): array
    {
        return [self::CRITICAL, self::WARNING, self::INFO];
    }
}

/**
 * A single test finding (one issue discovered during analysis).
 */
final class Finding
{
    public readonly string $severity;
    public readonly string $category;
    public readonly string $message;
    public readonly string $file;
    public readonly int    $line;
    public readonly string $suggestion;
    public readonly string $module;

    public function __construct(
        string $severity,
        string $category,
        string $message,
        string $file       = '',
        int    $line       = 0,
        string $suggestion = '',
        string $module     = '',
    ) {
        $this->severity   = $severity;
        $this->category   = $category;
        $this->message    = $message;
        $this->file       = $file;
        $this->line       = $line;
        $this->suggestion = $suggestion;
        $this->module     = $module;
    }
}

/**
 * Timing record for a single module's execution.
 */
final class ModuleTiming
{
    public function __construct(
        public readonly string $name,
        public readonly float  $elapsedMs,
        public readonly int    $testsRun,
    ) {}
}

/**
 * Aggregates all findings and computes the final health score.
 */
final class TestReport
{
    /** @var Finding[] */
    private array $findings = [];

    /** @var ModuleTiming[] */
    private array $timings = [];

    private int $testsRun = 0;

    // ─── Mutation ────────────────────────────────────────────────────────

    public function addFinding(Finding $f): void
    {
        $this->findings[] = $f;
    }

    public function addTiming(ModuleTiming $t): void
    {
        $this->timings[] = $t;
    }

    public function incrementTests(int $n = 1): void
    {
        $this->testsRun += $n;
    }

    // ─── Query ───────────────────────────────────────────────────────────

    /** @return Finding[] */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /** @return Finding[] Filtered by severity. */
    public function findingsBySeverity(string $severity): array
    {
        return array_values(
            array_filter($this->findings, fn(Finding $f) => $f->severity === $severity)
        );
    }

    /** @return Finding[] Filtered by module name. */
    public function findingsByModule(string $module): array
    {
        return array_values(
            array_filter($this->findings, fn(Finding $f) => $f->module === $module)
        );
    }

    /** @return ModuleTiming[] */
    public function getTimings(): array
    {
        return $this->timings;
    }

    public function getTestsRun(): int
    {
        return $this->testsRun;
    }

    /**
     * Compute a health score from 0 to 100.
     *
     * Deductions (capped per severity tier):
     *   CRITICAL → −5 each (cap −60)
     *   WARNING  → −2 each (cap −30)
     *   INFO     → −0.5 each (cap −10)
     */
    public function score(): int
    {
        $counts = $this->summaryCounts();

        $deduct  = min(Config::CAP_CRITICAL, $counts[Severity::CRITICAL] * Config::DEDUCT_CRITICAL);
        $deduct += min(Config::CAP_WARNING,  $counts[Severity::WARNING]  * Config::DEDUCT_WARNING);
        $deduct += min(Config::CAP_INFO,     (int) ($counts[Severity::INFO] * Config::DEDUCT_INFO));

        return max(0, 100 - $deduct);
    }

    /**
     * Grade label derived from the score.
     *
     * @return array{0: string, 1: string, 2: string}  [letter, hexColor, label]
     */
    public function grade(): array
    {
        return match (true) {
            $this->score() >= 90 => ['A', '#4CAF50', 'Excellent'],
            $this->score() >= 75 => ['B', '#8BC34A', 'Good'],
            $this->score() >= 60 => ['C', '#FF9800', 'Fair'],
            $this->score() >= 40 => ['D', '#f44336', 'Poor'],
            default              => ['F', '#9C27B0', 'Critical'],
        };
    }

    /**
     * @return array<string, int>  Counts keyed by severity constant.
     */
    public function summaryCounts(): array
    {
        $out = array_fill_keys(Severity::all(), 0);
        foreach ($this->findings as $f) {
            $out[$f->severity]++;
        }
        return $out;
    }

    /** Total elapsed time across all modules (ms). */
    public function totalElapsedMs(): float
    {
        return array_sum(array_map(fn(ModuleTiming $t) => $t->elapsedMs, $this->timings));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — Abstract Base Test
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Every test module extends this class.
 *
 * Subclasses call $this->critical/warning/info to record findings.
 * They call $this->report->incrementTests() once per logical check.
 */
abstract class BaseArchTest
{
    protected TestReport $report;
    protected string     $apiRoot;
    protected string     $projectRoot;
    protected FileCache  $cache;

    public function __construct(TestReport $report, string $projectRoot)
    {
        $this->report      = $report;
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->apiRoot     = $this->projectRoot . '/api';
        $this->cache       = FileCache::instance();
    }

    /** Human-readable name of this module (used in output). */
    abstract public function name(): string;

    /** Execute all checks. */
    abstract public function run(): void;

    // ─── Convenience wrappers ─────────────────────────────────────────────

    protected function critical(
        string $cat,
        string $msg,
        string $file       = '',
        int    $line       = 0,
        string $suggestion = '',
    ): void {
        $this->report->addFinding(new Finding(
            Severity::CRITICAL, $cat, $msg,
            $this->short($file), $line, $suggestion, $this->name()
        ));
    }

    protected function warning(
        string $cat,
        string $msg,
        string $file       = '',
        int    $line       = 0,
        string $suggestion = '',
    ): void {
        $this->report->addFinding(new Finding(
            Severity::WARNING, $cat, $msg,
            $this->short($file), $line, $suggestion, $this->name()
        ));
    }

    protected function info(
        string $cat,
        string $msg,
        string $file       = '',
        int    $line       = 0,
        string $suggestion = '',
    ): void {
        $this->report->addFinding(new Finding(
            Severity::INFO, $cat, $msg,
            $this->short($file), $line, $suggestion, $this->name()
        ));
    }

    // ─── Shared utilities ─────────────────────────────────────────────────

    protected function short(string $path): string
    {
        return shortPath($path, $this->projectRoot);
    }

    /**
     * Return all PHP files under $this->apiRoot.
     *
     * @return string[]
     */
    protected function allApiFiles(): array
    {
        static $cache = null;
        $cache ??= scanPhpFiles($this->apiRoot);
        return $cache;
    }

    /**
     * Filter allApiFiles() by a path-segment substring AND a filename suffix.
     *
     * @return string[]
     */
    protected function findFiles(string $pathSegment, string $suffix): array
    {
        return array_values(array_filter(
            $this->allApiFiles(),
            fn(string $f) => str_contains($f, $pathSegment)
                           && str_ends_with(basename($f), $suffix)
        ));
    }

    /**
     * Return all Repository PHP files.
     *
     * @return string[]
     */
    protected function repositoryFiles(): array
    {
        return array_values(array_filter(
            $this->allApiFiles(),
            fn(string $f) => str_contains(basename($f), 'Repository')
                           && str_ends_with($f, '.php')
        ));
    }

    /**
     * Return all route PHP files under api/v1/routes.
     *
     * @return string[]
     */
    protected function routeFiles(): array
    {
        return scanPhpFiles($this->apiRoot . '/v1/routes');
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — Architecture Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Validates strict layer flow:
 *   Routes → Controllers → Services → Repositories
 *
 * Checks:
 *   - No raw SQL in route files
 *   - Controllers are thin (no DB calls, not oversized)
 *   - Helpers contain no DB calls
 *   - Services use repositories, not raw PDO
 *   - Strict layer flow (no skipped layers)
 *   - No circular dependencies
 *   - Namespaces are consistent with directory structure
 */
class ArchitectureValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Architecture Validation';
    }

    public function run(): void
    {
        $this->checkNoRawSqlInRoutes();
        $this->checkControllerThinness();
        $this->checkNoDbInHelpers();
        $this->checkNoDirectDbInServices();
        $this->checkStrictLayerFlow();
        $this->checkCircularDependencies();
        $this->checkNamespaceConsistency();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Route files must not contain raw PDO calls or bare SQL DML statements.
     */
    private function checkNoRawSqlInRoutes(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                $lineNo = $idx + 1;

                if (preg_match('/\$pdo\s*->\s*(?:prepare|query)\s*\(/i', $line)) {
                    $this->critical(
                        'Single Source of Truth',
                        'Raw PDO call in route file — all DB access must go through Repositories',
                        $file, $lineNo,
                        'Move this database call into a Repository class and invoke it via a Service.'
                    );
                }

                // INSERT INTO ... execute() pattern (actual execution, not string constants)
                if (
                    preg_match('/\bINSERT\s+INTO\s+\w+/i', $line)
                    && str_contains($line, '->execute')
                    && !preg_match('/^\s*[\'"]/', $line)
                ) {
                    $this->critical(
                        'Single Source of Truth',
                        'INSERT INTO execution in route file',
                        $file, $lineNo,
                        'Move INSERT operations into a Repository class.'
                    );
                }

                if (preg_match('/\bUPDATE\s+\w+\s+SET\b/i', $line)) {
                    $this->critical(
                        'Single Source of Truth',
                        'UPDATE…SET statement in route file',
                        $file, $lineNo,
                        'Move UPDATE operations into a Repository class.'
                    );
                }

                if (preg_match('/\bDELETE\s+FROM\b/i', $line)) {
                    $this->critical(
                        'Single Source of Truth',
                        'DELETE FROM statement in route file',
                        $file, $lineNo,
                        'Move DELETE operations into a Repository class.'
                    );
                }
            }
        }
    }

    /**
     * Controllers must not contain direct DB calls and should not be oversized.
     */
    private function checkControllerThinness(): void
    {
        $this->report->incrementTests();

        foreach ($this->findFiles('/controllers/', 'Controller.php') as $file) {
            $loc = $this->cache->codeLineCount($file);

            if ($loc > Config::MAX_CLASS_LINES) {
                $this->warning(
                    'Controller Thinness',
                    "Controller is too large ({$loc} LOC — threshold: " . Config::MAX_CLASS_LINES . ')',
                    $file, 0,
                    'Extract business logic into a dedicated Service class.'
                );
            }

            if (preg_match('/\$pdo\s*->\s*(?:prepare|query|exec)\s*\(/i', $this->cache->stripped($file))) {
                $this->critical(
                    'Controller Thinness',
                    'Direct database access inside a Controller',
                    $file, 0,
                    'Controllers must delegate all data access to Service → Repository.'
                );
            }
        }
    }

    /**
     * Helper files must not perform database queries.
     */
    private function checkNoDbInHelpers(): void
    {
        $this->report->incrementTests();

        foreach (scanPhpFiles($this->apiRoot . '/shared/helpers') as $file) {
            $code = $this->cache->stripped($file);

            if (preg_match('/\$pdo\s*->\s*(?:prepare|query)\s*\(/i', $code)) {
                $this->critical(
                    'Helper Layer',
                    'Database call found in helper file',
                    $file, 0,
                    'Helpers must be pure utility functions. Move DB calls to Repositories.'
                );
            }

            if (
                preg_match('/\bSELECT\b.+\bFROM\b/is', $code)
                && preg_match('/\b(?:prepare|query|execute)\b/i', $code)
            ) {
                $this->critical(
                    'Helper Layer',
                    'SQL query pattern found in helper file',
                    $file, 0,
                    'Helpers must not contain data-access logic.'
                );
            }
        }
    }

    /**
     * Service classes must not instantiate PDO directly or call PDO methods.
     */
    private function checkNoDirectDbInServices(): void
    {
        $this->report->incrementTests();

        foreach ($this->findFiles('/services/', 'Service.php') as $file) {
            $code = $this->cache->stripped($file);

            if (preg_match('/\$pdo\s*->\s*(?:prepare|query|exec)\s*\(/i', $code)) {
                $this->critical(
                    'Service Layer',
                    'Direct $pdo usage in Service class',
                    $file, 0,
                    'Services must access the DB only through Repository classes.'
                );
            }

            if (preg_match('/\bnew\s+PDO\s*\(/i', $code)) {
                $this->critical(
                    'Service Layer',
                    'PDO instantiated directly in Service class',
                    $file, 0,
                    'Inject PDO through the Repository layer, not directly in Services.'
                );
            }
        }
    }

    /**
     * Enforce Routes → Controllers → Services → Repositories flow.
     *
     * Routes may wire up DI (new XxxRepository → new XxxService → new XxxController)
     * but must NOT call repository methods directly.
     */
    private function checkStrictLayerFlow(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $code = $this->cache->stripped($file);

            $hasRepo       = (bool) preg_match('/new\s+Pdo\w+Repository\s*\(/i', $code);
            $hasController = (bool) preg_match('/new\s+\w+Controller\s*\(/i', $code);
            $hasService    = (bool) preg_match('/new\s+\w+Service\s*\(/i', $code);
            $isPublic      = str_contains($file, '/public/');

            if (!$hasRepo) {
                continue;
            }

            if ($hasController || $hasService) {
                // Proper DI wiring — only flag if route also calls repo methods directly
                if (preg_match(
                    '/\$repo(?:sitory)?\s*->\s*(?:find|get|all|list|save|create|update|delete|count|search|fetch|insert)\w*\s*\(/i',
                    $code
                )) {
                    $this->warning(
                        'Layer Flow',
                        'Route bypasses Controller/Service and calls Repository method directly',
                        $file, 0,
                        'Route must delegate through Controller → Service → Repository.'
                    );
                }
            } else {
                if ($isPublic) {
                    $this->info(
                        'Layer Flow',
                        'Public sub-route uses Repository without Service layer',
                        $file, 0,
                        'For complex logic, introduce a Service layer between route and Repository.'
                    );
                } else {
                    $this->warning(
                        'Layer Flow',
                        'Route instantiates Repository without Controller/Service — missing layering',
                        $file, 0,
                        'Add a Service and Controller layer between the route and Repository.'
                    );
                }
            }
        }

        // Controllers should not directly instantiate repositories
        foreach ($this->findFiles('/controllers/', 'Controller.php') as $file) {
            if (preg_match('/new\s+Pdo\w+Repository\s*\(/i', $this->cache->stripped($file))) {
                $this->info(
                    'Layer Flow',
                    'Controller instantiates a Repository directly (prefer Service delegation)',
                    $file, 0,
                    'Route data-access through a Service for better separation of concerns.'
                );
            }
        }
    }

    /**
     * Detect circular includes between architectural layers.
     *
     * Repository must not include Controller or Service.
     * Service must not include Controller.
     */
    private function checkCircularDependencies(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $content = $this->cache->content($file);

            if (preg_match('/require(?:_once)?\s*[^;]*Controller\.php/i', $content)) {
                $this->critical(
                    'Circular Dependency',
                    'Repository includes a Controller file',
                    $file, 0,
                    'Repositories must never depend on Controllers.'
                );
            }

            if (preg_match('/require(?:_once)?\s*[^;]*Service\.php/i', $content)) {
                $this->warning(
                    'Circular Dependency',
                    'Repository includes a Service file — potential circular dependency',
                    $file, 0,
                    'Repositories should be self-contained; inject dependencies instead.'
                );
            }
        }

        foreach ($this->findFiles('/services/', 'Service.php') as $file) {
            if (preg_match('/require(?:_once)?\s*[^;]*Controller\.php/i', $this->cache->content($file))) {
                $this->critical(
                    'Circular Dependency',
                    'Service includes a Controller file',
                    $file, 0,
                    'Services must never depend on Controllers.'
                );
            }
        }
    }

    /**
     * Heuristic namespace consistency check.
     *
     * If a file declares a namespace that does not match its directory path, flag it.
     */
    private function checkNamespaceConsistency(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            if (!preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $m)) {
                continue; // No namespace declaration — skip
            }

            $declaredNs = str_replace('\\', '/', $m[1]);
            $relativePath = $this->short($file);

            // The last component of the namespace should appear in the file path
            $nsParts   = explode('/', $declaredNs);
            $lastPart  = strtolower(end($nsParts));
            $lowerPath = strtolower(str_replace('\\', '/', $relativePath));

            // Compare second-to-last namespace segment against directory
            if (count($nsParts) >= 2) {
                $nsDir = strtolower($nsParts[count($nsParts) - 2]);
                if (!str_contains($lowerPath, $nsDir) && !str_contains($lowerPath, $lastPart)) {
                    $this->info(
                        'Namespace Consistency',
                        "Declared namespace '{$m[1]}' may not match file location",
                        $file, 0,
                        'Ensure the namespace mirrors the directory structure (PSR-4).'
                    );
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — Performance Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detects common performance anti-patterns:
 *   - Correlated subqueries in JOIN ON clauses
 *   - N+1 query patterns (DB call inside a loop)
 *   - SELECT * usage
 *   - Deep/unbounded pagination (large OFFSET)
 *   - Index-hostile WHERE clauses (leading LIKE %, function on column)
 *   - Unbounded queries (no LIMIT)
 */
class PerformanceValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Performance Validation';
    }

    public function run(): void
    {
        $this->checkSubqueriesInJoinOn();
        $this->checkNPlusOnePatterns();
        $this->checkSelectStar();
        $this->checkDeepPagination();
        $this->checkIndexHostileWhere();
        $this->checkUnboundedQueries();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Detect correlated subqueries inside JOIN … ON clauses.
     *
     * Pattern: JOIN table ON col = (SELECT …)
     * These cause per-row subquery evaluation and can be catastrophic on large tables.
     *
     * Derived tables — JOIN (SELECT …) AS alias ON … — are acceptable.
     */
    private function checkSubqueriesInJoinOn(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $code = $this->cache->stripped($file);

            // Only flag ON col = (SELECT …) — not JOIN (SELECT …) derived tables
            if (
                preg_match('/\bON\b[^;]{0,300}?=\s*\(\s*SELECT\b/is', $code)
                && !preg_match('/\bJOIN\s*\(\s*SELECT\b/is', $code)
            ) {
                $this->warning(
                    'Performance',
                    'Correlated subquery inside JOIN ON clause — executes once per row',
                    $file, 0,
                    'Rewrite as a derived table (subquery in FROM) or a CTE (WITH …).'
                );
            }

            // Also catch: JOIN table ON ... (SELECT — even if there is also a derived table
            if (
                preg_match('/\bJOIN\s*\(\s*SELECT\b/is', $code)
                && preg_match('/\bON\b[^;]{0,300}?=\s*\(\s*SELECT\b/is', $code)
            ) {
                $this->warning(
                    'Performance',
                    'Correlated subquery in ON clause alongside derived table — verify intent',
                    $file, 0,
                    'Replace the correlated subquery with a pre-computed JOIN or CTE.'
                );
            }
        }
    }

    /**
     * Detect database calls inside loops — the N+1 query anti-pattern.
     *
     * Uses a window match: foreach/while/for opening brace followed within
     * 1 KB by a PDO method call.
     *
     * Note: multi-line method chains can fool this heuristic, so the threshold
     * window is intentionally conservative (800 chars).
     */
    private function checkNPlusOnePatterns(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $code = $this->cache->stripped($file);

            if (preg_match(
                '/\b(?:foreach|while|for)\s*\([^)]*\)\s*\{[^}]{0,800}?\$(?:pdo|this->pdo|stmt|db)\s*->\s*(?:prepare|query|exec)\s*\(/is',
                $code
            )) {
                $this->critical(
                    'N+1 Query',
                    'Database call inside a loop — classic N+1 pattern',
                    $file, 0,
                    'Batch the query outside the loop or rewrite with IN() / JOIN.'
                );
            }
        }
    }

    /**
     * Detect SELECT * in repository files (excludes COUNT(*)).
     */
    private function checkSelectStar(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $lines = $this->cache->lines($file);
            $count = 0;
            $firstLine = 0;

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }
                // Exclude COUNT(*), which is standard
                if (
                    preg_match('/\bSELECT\s+\*/i', $line)
                    && !preg_match('/\bCOUNT\s*\(\s*\*/i', $line)
                ) {
                    $count++;
                    if ($firstLine === 0) {
                        $firstLine = $idx + 1;
                    }
                }
            }

            if ($count > 0) {
                $this->info(
                    'Performance',
                    "SELECT * found {$count} time(s) — fetches unnecessary columns",
                    $file, $firstLine,
                    'Explicitly list only the columns your application needs.'
                );
            }
        }
    }

    /**
     * Detect hard-coded OFFSET values exceeding the safe threshold.
     *
     * Dynamic OFFSET (e.g. OFFSET :offset) is fine — only hard-coded large values are flagged.
     */
    private function checkDeepPagination(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $code = $this->cache->stripped($file);

            if (
                preg_match('/\bOFFSET\s+(\d+)\b/i', $code, $m)
                && (int) $m[1] > Config::MAX_SAFE_OFFSET
            ) {
                $this->warning(
                    'Deep Pagination',
                    "Hard-coded OFFSET {$m[1]} — very large offset degrades performance",
                    $file, 0,
                    'Switch to cursor-based (keyset) pagination instead of OFFSET for large datasets.'
                );
            }
        }
    }

    /**
     * Detect WHERE clause patterns that defeat index usage:
     *   - LIKE '%...' (leading wildcard)
     *   - Function call on a column (LOWER, UPPER, DATE, YEAR, MONTH, TRIM, CONCAT)
     */
    private function checkIndexHostileWhere(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $code = $this->cache->stripped($file);

            if (preg_match('/\bWHERE\b[^;]{0,200}?\bLIKE\s+[\'"]%/i', $code)) {
                $this->warning(
                    'Index Usage',
                    'LIKE with leading wildcard (%) — index cannot be used',
                    $file, 0,
                    'Use full-text search (MATCH … AGAINST) or remove the leading % wildcard.'
                );
            }

            if (preg_match('/\bWHERE\b[^;]{0,200}?\b(LOWER|UPPER|DATE|YEAR|MONTH|TRIM|CONCAT)\s*\(/i', $code)) {
                $this->info(
                    'Index Usage',
                    'Function applied to column in WHERE clause — may prevent index usage',
                    $file, 0,
                    'Use a virtual/computed column or rewrite to avoid wrapping indexed columns in functions.'
                );
            }
        }
    }

    /**
     * Detect queries that fetch from large tenant tables without a LIMIT clause.
     * An unbounded SELECT on orders/products can return millions of rows.
     */
    private function checkUnboundedQueries(): void
    {
        $this->report->incrementTests();

        $tenantTablesPattern = implode('|', Config::TENANT_TABLES);

        foreach ($this->repositoryFiles() as $file) {
            $code = $this->cache->stripped($file);

            // Find SELECT … FROM <tenant-table> blocks without LIMIT
            if (
                preg_match('/\bSELECT\b[^;]{0,500}?\bFROM\s+(?:' . $tenantTablesPattern . ')\b/is', $code)
                && !preg_match('/\bLIMIT\b/i', $code)
            ) {
                $this->warning(
                    'Unbounded Query',
                    'Query on a tenant table with no LIMIT — could return millions of rows',
                    $file, 0,
                    'Always apply LIMIT (and a sensible MAX) to queries on large tenant tables.'
                );
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 8 — Multi-Tenant Safety Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Verifies that tenant-scoped data is always filtered by tenant_id:
 *   - Every FROM <tenant-table> in a non-system repository has a nearby tenant_id condition
 *   - Repository public list/find/get methods accept a tenant parameter
 */
class MultiTenantSafety extends BaseArchTest
{
    public function name(): string
    {
        return 'Multi-Tenant Safety';
    }

    public function run(): void
    {
        $this->checkTenantIdInQueries();
        $this->checkMissingTenantParameters();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Each query touching a tenant-scoped table must have a tenant_id filter
     * within a 1500-character window around the FROM clause.
     */
    private function checkTenantIdInQueries(): void
    {
        $this->report->incrementTests();

        $pattern = '/\bFROM\s+(' . implode('|', Config::TENANT_TABLES) . ')\b/i';

        foreach ($this->repositoryFiles() as $file) {
            if (Config::shouldSkipTenant(basename($file))) {
                continue;
            }

            $content = $this->cache->content($file);
            $reported = []; // Track reported tables to avoid duplicate findings per file

            $offset = 0;
            while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $table  = $match[1][0];
                $pos    = $match[0][1];
                $offset = $pos + strlen($match[0][0]);

                if (in_array($table, $reported, true)) {
                    continue; // Already reported for this table in this file
                }

                $windowStart = max(0, $pos - 200);
                $window      = substr($content, $windowStart, 1500);

                if (!preg_match('/\btenant_id\b/i', $window)) {
                    $this->warning(
                        'Multi-Tenant',
                        "Query on '{$table}' may be missing a tenant_id filter",
                        $file, offsetToLine($content, $pos),
                        "All queries on '{$table}' must include a tenant_id condition to prevent data leakage."
                    );
                    $reported[] = $table;
                }
            }
        }
    }

    /**
     * Repository public data-retrieval methods should accept a tenantId parameter
     * or reference tenant_id in their body.
     */
    private function checkMissingTenantParameters(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            if (Config::shouldSkipTenant(basename($file))) {
                continue;
            }

            $content = $this->cache->content($file);
            $flagged = false;

            if (!preg_match_all(
                '/public\s+function\s+(list|all|find\w*|get\w*)\s*\(([^)]*)\)/i',
                $content,
                $methods,
                PREG_SET_ORDER
            )) {
                continue;
            }

            foreach ($methods as $method) {
                if ($flagged) {
                    break;
                }

                [$fullMatch, $name, $params] = $method;

                // Skip if the parameter list already mentions 'tenant'
                if (preg_match('/tenant/i', $params)) {
                    continue;
                }

                // Check the method body (next 2000 chars)
                $fnPos = strpos($content, $fullMatch);
                if ($fnPos === false) {
                    continue;
                }

                $bodyWindow = substr($content, $fnPos, 2000);
                if (!preg_match('/\btenant_id\b/i', $bodyWindow)) {
                    $this->info(
                        'Multi-Tenant',
                        "Repository method '{$name}()' may lack tenant scoping",
                        $file, offsetToLine($content, $fnPos),
                        'Add a $tenantId parameter or apply a tenant_id filter inside the method.'
                    );
                    $flagged = true;
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 9 — Security Validation Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Security checks:
 *   - SQL injection via string concatenation
 *   - pdo->query() with variable interpolation
 *   - Missing RBAC on write routes
 *   - Missing input validation
 *   - Path traversal vectors
 *   - CSRF-unsafe state-changing requests (non-JSON API endpoints)
 */
class SecurityValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Security Validation';
    }

    public function run(): void
    {
        $this->checkRawSqlConcatenation();
        $this->checkPreparedStatements();
        $this->checkWeakRbac();
        $this->checkMissingInputValidation();
        $this->checkPathTraversal();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Detect SQL built via string concatenation with user-controlled variables.
     *
     * Heuristic: "SELECT|INSERT|UPDATE|DELETE …" . $var
     * where $var is not a whitelisted structural variable.
     */
    private function checkRawSqlConcatenation(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                // Pattern: "SELECT|INSERT|UPDATE|DELETE … " . $someVar
                if (preg_match(
                    '/["\'](?:SELECT|INSERT|UPDATE|DELETE)\b[^"\']*["\']\s*\.\s*(\$\w+)/i',
                    $line,
                    $m
                )) {
                    if (!isSafeSqlVar($m[1])) {
                        $this->critical(
                            'SQL Injection',
                            'SQL string built by concatenation with a variable — injection risk',
                            $file, $idx + 1,
                            'Use prepared statements with bound parameters (:name or ?) instead.'
                        );
                    }
                }

                // Pattern: WHERE col = {$var} or WHERE col = ' . $var
                if (preg_match('/\bWHERE\b[^"\']{0,100}(?:\{\$\w+\}|["\']\.?\s*\$(\w+))/i', $line, $m2)) {
                    $varName = $m2[1] ?? '';
                    if (!isSafeSqlVar($varName)) {
                        $this->warning(
                            'SQL Injection',
                            'Variable interpolated directly in WHERE clause',
                            $file, $idx + 1,
                            'Always use parameter binding (:param or ?) for WHERE conditions.'
                        );
                    }
                }
            }
        }
    }

    /**
     * Detect $pdo->query() with variable input (instead of prepare/execute).
     */
    private function checkPreparedStatements(): void
    {
        $this->report->incrementTests();

        foreach ($this->repositoryFiles() as $file) {
            $code = $this->cache->stripped($file);

            // $pdo->query("… $var …") or $pdo->query("… " . $var)
            if (
                preg_match('/\$pdo\s*->\s*query\s*\(\s*"[^"]*\$/i', $code)
                || preg_match('/\$pdo\s*->\s*query\s*\([^)]{0,100}\.\s*\$/i', $code)
            ) {
                $this->critical(
                    'Prepared Statements',
                    '$pdo->query() used with variable input',
                    $file, 0,
                    'Replace with $pdo->prepare() + $stmt->execute() for parameterized queries.'
                );
            }
        }
    }

    /**
     * Routes handling write operations (POST/PUT/PATCH/DELETE) should have a
     * visible authentication or authorization guard.
     */
    private function checkWeakRbac(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $basename = basename($file);

            if (Config::isPublicRouteFile($basename)) {
                continue;
            }
            if (str_contains($file, '/public/')) {
                continue;
            }

            $code = $this->cache->content($file);

            if (!preg_match('/\b(?:POST|PUT|PATCH|DELETE)\b/i', $code)) {
                continue; // Read-only routes don't need write-auth check
            }

            $hasAuth = preg_match('/\b(?:auth|permission|rbac|middleware|token|jwt|session|bootstrap|isAllowed|hasRole)\b/i', $code);
            $hasBootstrap  = preg_match('/require[^;]+bootstrap/i', $code);
            $hasController = preg_match('/\$controller\s*->/i', $code);

            if (!$hasAuth && !$hasBootstrap && !$hasController) {
                $this->info(
                    'RBAC',
                    'Write-capable route has no visible auth/permission check',
                    $file, 0,
                    'Protect all write endpoints with authentication and role-based authorization.'
                );
            }
        }
    }

    /**
     * Routes that read user input must sanitize/validate it.
     */
    private function checkMissingInputValidation(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $code = $this->cache->content($file);

            $readsInput = preg_match(
                '/\$_(?:POST|GET|REQUEST)\b|php:\/\/input/i',
                $code
            );

            if (!$readsInput) {
                continue;
            }

            $hasValidation = preg_match(
                '/\b(?:filter_var|Validator|htmlspecialchars|strip_tags|preg_match|is_numeric|intval|trim|empty|isset|json_decode|validate)\b/i',
                $code
            );
            $hasController = preg_match('/\$controller\s*->/i', $code);

            if (!$hasValidation && !$hasController) {
                $this->warning(
                    'Input Validation',
                    'Route reads user input without visible sanitization/validation',
                    $file, 0,
                    'Always validate and sanitize user-supplied data before processing.'
                );
            }
        }
    }

    /**
     * Detect path traversal vectors — user input passed to file functions.
     *
     * Patterns:
     *   file_get_contents($userInput)
     *   include/require with $_GET/$_POST
     */
    private function checkPathTraversal(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                // file_get_contents / file_put_contents / fopen with a $_GET/$_POST/$_REQUEST variable
                if (preg_match('/\b(?:file_get_contents|file_put_contents|fopen|readfile|include|require)\s*\([^)]*\$_(?:GET|POST|REQUEST|SERVER|COOKIE)\b/i', $line)) {
                    $this->critical(
                        'Path Traversal',
                        'User-controlled variable passed to a filesystem function — path traversal risk',
                        $file, $idx + 1,
                        'Whitelist allowed paths and sanitize any user-provided filename/path components.'
                    );
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 10 — Type Safety Validation Module  (NEW)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Verifies PHP type-safety practices:
 *   - Every file declares strict_types=1
 *   - Public method signatures have return type declarations
 *   - Public method parameters have type declarations
 *   - Class properties have type declarations (PHP 7.4+)
 */
class TypeSafetyValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Type Safety';
    }

    public function run(): void
    {
        $this->checkStrictTypes();
        $this->checkReturnTypes();
        $this->checkParamTypes();
        $this->checkPropertyTypes();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Every PHP file should declare strict_types=1 as the very first statement.
     */
    private function checkStrictTypes(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            // The declaration must appear before any class/function definition
            $firstClassOrFn = strpos($content, 'class ')
                ?? strpos($content, 'function ');

            $hasDeclare = preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/i', $content);

            if (!$hasDeclare) {
                $this->info(
                    'Type Safety',
                    'Missing declare(strict_types=1)',
                    $file, 1,
                    'Add declare(strict_types=1); at the top of every PHP file for type safety.'
                );
            }
        }
    }

    /**
     * Public class methods should declare return types.
     */
    private function checkReturnTypes(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            // Find public functions WITHOUT a return type (no colon before {)
            // Regex: public [static] function name(...) { — no colon after )
            if (preg_match_all(
                '/public\s+(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*(?!:)\s*\{/i',
                $content,
                $matches,
                PREG_SET_ORDER
            )) {
                // Report once per file to avoid noise
                $names = array_column($matches, 1);
                // Filter out magic methods (they have defined return semantics)
                $names = array_values(array_filter($names, fn(string $n) => !str_starts_with($n, '__')));

                if (count($names) > 0) {
                    $sample = implode(', ', array_slice($names, 0, 3));
                    $more   = count($names) > 3 ? ' +' . (count($names) - 3) . ' more' : '';
                    $this->info(
                        'Type Safety',
                        "Public method(s) missing return type: {$sample}{$more}",
                        $file, 0,
                        'Add explicit return types (e.g. : array, : string, : void) to all public methods.'
                    );
                }
            }
        }
    }

    /**
     * Public method parameters should be type-hinted.
     *
     * Flags methods where at least one parameter has no type hint.
     */
    private function checkParamTypes(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            $flaggedMethods = [];

            if (!preg_match_all(
                '/public\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]+)\)/i',
                $content,
                $matches,
                PREG_SET_ORDER
            )) {
                continue;
            }

            foreach ($matches as $m) {
                $methodName = $m[1];
                if (str_starts_with($methodName, '__')) {
                    continue;
                }

                $params = explode(',', $m[2]);
                foreach ($params as $param) {
                    $param = trim($param);
                    if ($param === '' || str_starts_with($param, '...')) {
                        continue;
                    }

                    // A typed param looks like: Type $var or ?Type $var or array $var
                    // An un-typed param looks like: $var or &$var or ...$var
                    if (preg_match('/^[&\s]*\$\w+(?:\s*=.*)?$/', $param)) {
                        $flaggedMethods[] = $methodName;
                        break;
                    }
                }
            }

            if (count($flaggedMethods) > 0) {
                $sample = implode(', ', array_unique(array_slice($flaggedMethods, 0, 3)));
                $this->info(
                    'Type Safety',
                    "Untyped parameter(s) in public method(s): {$sample}",
                    $file, 0,
                    'Add type declarations to all method parameters.'
                );
            }
        }
    }

    /**
     * Class properties should have type declarations (PHP 7.4+).
     *
     * Flags files with untyped public or protected properties.
     */
    private function checkPropertyTypes(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            // Untyped property: public/protected $var or public/protected static $var
            // Typed property: public string $var or public ?int $var
            if (preg_match_all(
                '/^\s*(?:public|protected)\s+(?:static\s+)?\$(\w+)\s*(?:=|;)/m',
                $content,
                $matches
            )) {
                $props = $matches[1];
                if (count($props) > 0) {
                    $sample = implode(', ', array_slice($props, 0, 3));
                    $more   = count($props) > 3 ? ' +' . (count($props) - 3) . ' more' : '';
                    $this->info(
                        'Type Safety',
                        "Untyped class properties: \${$sample}{$more}",
                        $file, 0,
                        'Declare explicit types on all class properties (PHP 7.4+).'
                    );
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 11 — Configuration Safety Module  (NEW)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detects dangerous configuration practices:
 *   - Hardcoded credentials (passwords, API keys, secrets in source)
 *   - Debug flags left enabled in production-like files
 *   - Environment variable leaks (dumping $_ENV/$_SERVER)
 *   - error_reporting(E_ALL) / display_errors = On in non-dev files
 *   - Hard-coded IP addresses / localhost URLs
 */
class ConfigurationSafety extends BaseArchTest
{
    public function name(): string
    {
        return 'Configuration Safety';
    }

    public function run(): void
    {
        $this->checkHardcodedCredentials();
        $this->checkDebugFlags();
        $this->checkEnvLeaks();
        $this->checkHardcodedUrls();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Detect hardcoded passwords, API keys, and secrets.
     *
     * Heuristic patterns:
     *   $password = 'literal'
     *   'apiKey' => 'literal'
     *   define('SECRET', 'literal')
     *   $apiKey = 'literal'
     */
    private function checkHardcodedCredentials(): void
    {
        $this->report->incrementTests();

        $credentialPattern = '/(?:password|passwd|secret|api_?key|apikey|auth_?token|private_?key|access_?token|client_?secret)\s*[=:>]+\s*[\'"][^\'"\s]{6,}[\'"]/i';

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                // Skip .env.example and placeholder lines
                $lower = strtolower($line);
                if (
                    str_contains($lower, 'your_')
                    || str_contains($lower, 'change_me')
                    || str_contains($lower, 'example')
                    || str_contains($lower, 'placeholder')
                    || str_contains($lower, 'xxxx')
                ) {
                    continue;
                }

                // Skip getenv() and $_ENV usage — those are fine
                if (str_contains($line, 'getenv(') || str_contains($line, '$_ENV')) {
                    continue;
                }

                if (preg_match($credentialPattern, $line)) {
                    $this->critical(
                        'Hardcoded Credentials',
                        'Possible hardcoded credential or secret in source code',
                        $file, $idx + 1,
                        'Load secrets from environment variables or a secrets manager, never source code.'
                    );
                }
            }
        }
    }

    /**
     * Detect debug flags that should not be present in production code.
     */
    private function checkDebugFlags(): void
    {
        $this->report->incrementTests();

        $debugPatterns = [
            '/\bdisplay_errors\s*=\s*[\'"]?(?:on|1|true)[\'"]?/i'     => 'display_errors = On',
            '/\berror_reporting\s*\(\s*E_ALL\s*\)/i'                   => 'error_reporting(E_ALL)',
            '/\bvar_dump\s*\(/i'                                        => 'var_dump()',
            '/\bprint_r\s*\([^)]+,\s*false\s*\)/i'                    => 'print_r() left in code',
            '/\bdie\s*\(\s*[\'"]debug/i'                               => 'die("debug…")',
        ];

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                foreach ($debugPatterns as $pattern => $label) {
                    if (preg_match($pattern, $line)) {
                        $this->warning(
                            'Debug Flag',
                            "Debug statement left in code: {$label}",
                            $file, $idx + 1,
                            'Remove or guard debug statements behind an environment check (APP_DEBUG).'
                        );
                    }
                }
            }
        }
    }

    /**
     * Detect accidental environment/server variable dumps.
     *
     * var_dump($_ENV) or print_r($_SERVER) expose all server internals.
     */
    private function checkEnvLeaks(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }

                if (preg_match('/(?:var_dump|print_r|var_export)\s*\(\s*\$_(?:ENV|SERVER|GLOBALS)\s*[\),]/i', $line)) {
                    $this->critical(
                        'Environment Leak',
                        'Dumping $_ENV, $_SERVER, or $GLOBALS — exposes sensitive runtime data',
                        $file, $idx + 1,
                        'Never expose server environment variables in output. Remove this statement.'
                    );
                }
            }
        }
    }

    /**
     * Detect hard-coded localhost/127.0.0.1 URLs or IP addresses in non-config files.
     *
     * Hard-coded database hosts, API endpoints, etc. prevent environment portability.
     */
    private function checkHardcodedUrls(): void
    {
        $this->report->incrementTests();

        $urlPattern = '/[\'"](?:http:\/\/localhost|http:\/\/127\.0\.0\.1|mysql:host=localhost)[\'"\s]/i';

        foreach ($this->allApiFiles() as $file) {
            // Config/bootstrap files are expected to have these — skip
            $base = strtolower(basename($file));
            if (
                str_contains($base, 'config')
                || str_contains($base, 'bootstrap')
                || str_contains($base, 'env')
                || str_contains($base, 'database')
            ) {
                continue;
            }

            $lines = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) {
                    continue;
                }
                if (preg_match($urlPattern, $line)) {
                    $this->info(
                        'Hardcoded URL',
                        'Hard-coded localhost/127.0.0.1 URL — not portable across environments',
                        $file, $idx + 1,
                        'Use environment variables (getenv, $_ENV) for all host/URL configuration.'
                    );
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 12 — Exception Handling Quality Module  (NEW)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Checks for poor exception handling practices:
 *   - Empty catch blocks (exceptions silently swallowed)
 *   - catch (Exception $e) {} — over-broad catch that hides bugs
 *   - catch (\Throwable $e) {} — swallowing fatal errors
 *   - throw new Exception("message") without proper exception type
 *   - Missing finally for resource cleanup
 */
class ExceptionHandling extends BaseArchTest
{
    public function name(): string
    {
        return 'Exception Handling';
    }

    public function run(): void
    {
        $this->checkSwallowedExceptions();
        $this->checkOverBroadCatch();
        $this->checkGenericExceptionThrow();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Detect empty or near-empty catch blocks (swallowed exceptions).
     *
     * Pattern: catch (...) { } or catch (...) { (nothing) }
     *                                      ^^^^^^^^^^^
     *                                      تم التعديل هنا
     */
    private function checkSwallowedExceptions(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            // catch block with only optional whitespace/comments inside
            if (preg_match_all(
                '/catch\s*\([^)]+\)\s*\{(\s*(?:\/\/[^\n]*)?\s*)\}/s',
                $content,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $m) {
                    $body = trim($m[1]);
                    // Body is empty or is only a single-line comment
                    if ($body === '' || preg_match('/^\/\/[^\n]*$/', $body)) {
                        $this->warning(
                            'Exception Handling',
                            'Empty catch block — exception is silently swallowed',
                            $file, 0,
                            'At minimum, log the exception. Never silently swallow errors.'
                        );
                        break; // One finding per file
                    }
                }
            }
        }
    }
    /**
     * Detect over-broad catch clauses that mask bugs.
     *
     * catch (Exception $e) or catch (\Throwable $e) with trivial bodies.
     */
    private function checkOverBroadCatch(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $code = $this->cache->stripped($file);

            if (preg_match('/catch\s*\(\s*\\\\?(?:Exception|Throwable)\s+\$\w+\s*\)/i', $code)) {
                $this->info(
                    'Exception Handling',
                    'Over-broad catch(Exception) or catch(Throwable) detected',
                    $file, 0,
                    'Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.'
                );
            }
        }
    }

    /**
     * Detect throw new Exception("message") instead of domain-specific exception types.
     */
    private function checkGenericExceptionThrow(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $code = $this->cache->stripped($file);

            // throw new Exception( or throw new \Exception(
            if (preg_match_all('/\bthrow\s+new\s+\\\\?Exception\s*\(/i', $code)) {
                $this->info(
                    'Exception Handling',
                    'Generic Exception thrown — consider domain-specific exception classes',
                    $file, 0,
                    'Create descriptive exception classes (e.g. NotFoundException, ValidationException).'
                );
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 13 — Code Quality Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Code quality metrics:
 *   - Large classes (> 500 LOC)
 *   - Large methods (> 50 LOC info, > 80 LOC warning)
 *   - God classes (> 25 public methods)
 *   - Duplicated logic patterns across multiple files
 */
class CodeQualityValidation extends BaseArchTest
{
    public function name(): string
    {
        return 'Code Quality';
    }

    public function run(): void
    {
        $this->checkLargeClasses();
        $this->checkLargeMethods();
        $this->checkGodClasses();
        $this->checkDuplicatedLogicPatterns();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    private function checkLargeClasses(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            if (!preg_match('/\bclass\s+\w+/i', $this->cache->content($file))) {
                continue;
            }

            $loc = $this->cache->codeLineCount($file);

            if ($loc > Config::MAX_CLASS_LINES) {
                $this->warning(
                    'Large Class',
                    "Class file has {$loc} LOC (threshold: " . Config::MAX_CLASS_LINES . ')',
                    $file, 0,
                    'Split into smaller, single-responsibility classes.'
                );
            }
        }
    }

    /**
     * Parse methods using a brace counter to measure method body size.
     *
     * Limitations: heredocs and string literals containing braces can skew the
     * brace count. This is a heuristic — results should be verified manually.
     */
    private function checkLargeMethods(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);

            $inMethod    = false;
            $methodName  = '';
            $methodStart = 0;
            $braceDepth  = 0;
            $methodLoc   = 0;

            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);

                if (!$inMethod && preg_match(
                    '/(?:public|protected|private|static)\s+function\s+(\w+)\s*\(/i',
                    $trimmed,
                    $m
                )) {
                    $inMethod    = true;
                    $methodName  = $m[1];
                    $methodStart = $idx + 1;
                    $braceDepth  = 0;
                    $methodLoc   = 0;
                }

                if ($inMethod) {
                    $braceDepth += substr_count($trimmed, '{') - substr_count($trimmed, '}');

                    if (!isCommentLine($line) && $trimmed !== '') {
                        $methodLoc++;
                    }

                    if ($braceDepth <= 0 && $methodLoc > 1) {
                        if ($methodLoc > Config::MAX_METHOD_LINES_WARN) {
                            $this->warning(
                                'Large Method',
                                "Method '{$methodName}()' has ~{$methodLoc} LOC (threshold: " . Config::MAX_METHOD_LINES_WARN . ')',
                                $file, $methodStart,
                                'Refactor into smaller, single-responsibility methods.'
                            );
                        } elseif ($methodLoc > Config::MAX_METHOD_LINES_INFO) {
                            $this->info(
                                'Large Method',
                                "Method '{$methodName}()' has ~{$methodLoc} LOC (threshold: " . Config::MAX_METHOD_LINES_INFO . ')',
                                $file, $methodStart,
                                'Consider breaking this method into smaller parts.'
                            );
                        }
                        $inMethod = false;
                    }
                }
            }
        }
    }

    private function checkGodClasses(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);

            if (!preg_match('/\bclass\s+\w+/i', $content)) {
                continue;
            }

            $count = preg_match_all('/\bpublic\s+function\s+\w+\s*\(/i', $content);

            if ($count > Config::MAX_PUBLIC_METHODS) {
                $this->warning(
                    'God Class',
                    "Class has {$count} public methods (threshold: " . Config::MAX_PUBLIC_METHODS . ')',
                    $file, 0,
                    'Apply the Single Responsibility Principle and split into focused classes.'
                );
            }
        }
    }

    /**
     * Detect duplicated logic patterns.
     *
     * Pre-scan ALL files once into a map, then evaluate — O(n) not O(n²).
     */
    private function checkDuplicatedLogicPatterns(): void
    {
        $this->report->incrementTests();

        $patterns = [
            'permission_check' => [
                'regex'   => '/if\s*\(\s*!\s*\$\w+\s*->\s*(?:permission|hasPermission|can|isAllowed)\s*\(/i',
                'label'   => 'Permission check pattern',
            ],
            'status_transition' => [
                'regex'   => '/\$\w+\s*=\s*[\'"][a-z_]+[\'"]\s*;.*status/i',
                'label'   => 'Status assignment pattern',
            ],
            'email_validation' => [
                'regex'   => '/filter_var\s*\([^)]+FILTER_VALIDATE_EMAIL/i',
                'label'   => 'Email validation pattern',
            ],
            'json_response' => [
                'regex'   => '/json_encode\s*\(\s*\[\s*[\'"](?:status|success|data|error)[\'"]/i',
                'label'   => 'JSON response building',
            ],
        ];

        // Pre-compute per-file match counts (single pass over all files)
        /** @var array<string, array<string, int>> $fileCounts [patternKey][file] => count */
        $fileCounts = array_fill_keys(array_keys($patterns), []);

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            foreach ($patterns as $key => $def) {
                $n = preg_match_all($def['regex'], $content);
                if ($n >= Config::DUPLICATION_MIN_OCCURRENCES) {
                    $fileCounts[$key][$this->short($file)] = $n;
                }
            }
        }

        // Report patterns that appear in many files
        foreach ($patterns as $key => $def) {
            $filesHit = $fileCounts[$key];
            if (count($filesHit) >= Config::DUPLICATION_FILE_THRESHOLD) {
                $this->info(
                    'Duplicated Logic',
                    "'{$def['label']}' repeated across " . count($filesHit) . ' files',
                    '', 0,
                    'Extract into a shared Trait, Service method, or utility class.'
                );
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 14 — Runtime Simulation Module
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Simulates runtime cost without performing actual I/O in the hot path:
 *   - Flags files > 50 KB (parse overhead)
 *   - Benchmarks in-memory string operations as a proxy for request overhead
 */
class RuntimeSimulation extends BaseArchTest
{
    public function name(): string
    {
        return 'Runtime Simulation';
    }

    public function run(): void
    {
        $this->checkLargeFiles();
        $this->runSimulatedRequests();
    }

    // ─── Checks ───────────────────────────────────────────────────────────

    /**
     * Flag PHP files whose on-disk size exceeds the threshold.
     * Large files incur higher parse-time cost even with OPcache warm.
     */
    private function checkLargeFiles(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $bytes = @filesize($file);
            if ($bytes === false) {
                continue;
            }

            $kb = $bytes / 1024;

            if ($kb > Config::MAX_FILE_SIZE_KB) {
                $this->info(
                    'File Size',
                    sprintf('Large file (%.1f KB) — may slow autoload/include', $kb),
                    $file, 0,
                    'Split into smaller, lazily-loaded modules.'
                );
            }
        }
    }

    /**
     * In-memory simulation of N request parse cycles.
     *
     * We perform regex + JSON operations on cached content (no real I/O)
     * to measure CPU overhead per simulated request.
     *
     * FIX: The original version called file_get_contents() 1000 times in a loop,
     * making the "simulation" actually measure disk I/O — not useful and very slow.
     * This version uses already-cached content for a meaningful CPU benchmark.
     */
    private function runSimulatedRequests(): void
    {
        $this->report->incrementTests();

        $routeFiles = $this->routeFiles();

        if (empty($routeFiles)) {
            $this->info('Runtime', 'No route files found — skipping simulation.', '', 0, '');
            return;
        }

        // Pre-load content into memory (uses FileCache — O(1) after first load)
        $preloaded = [];
        foreach ($routeFiles as $f) {
            $preloaded[] = $this->cache->content($f);
        }

        $iterations = Config::SIMULATION_ITERATIONS;
        $routeCount = count($preloaded);

        $totalMs = measureTime(function () use ($iterations, $preloaded, $routeCount): void {
            for ($i = 0; $i < $iterations; $i++) {
                $content = $preloaded[$i % $routeCount]; // cycle through cached content
                // Simulate route-matching overhead
                preg_match_all('/\bfunction\s+\w+/i', $content, $m);
                // Simulate response serialisation
                json_encode(['status' => 'ok', 'iteration' => $i, 'matches' => count($m[0])]);
            }
        });

        $avgMs    = round($totalMs / $iterations, 4);
        $totalSec = round($totalMs / 1000, 3);

        if ($avgMs > Config::SIM_WARN_MS_PER_ITER) {
            $this->warning(
                'Runtime',
                "Avg simulated CPU time: {$avgMs} ms/req (threshold: " . Config::SIM_WARN_MS_PER_ITER . ' ms) — total: ' . $totalSec . "s for {$iterations} iterations",
                '', 0,
                'Consider enabling OPcache and reducing regex complexity in hot paths.'
            );
        } elseif ($avgMs > Config::SIM_INFO_MS_PER_ITER) {
            $this->info(
                'Runtime',
                "Avg simulated CPU time: {$avgMs} ms/req — total: {$totalSec}s for {$iterations} iterations",
                '', 0,
                'Performance is acceptable; OPcache will further reduce parse overhead.'
            );
        }

        // Always record a summary finding
        $this->info(
            'Runtime Summary',
            "Simulation: {$iterations} requests in {$totalSec}s (avg {$avgMs} ms/req, {$routeCount} route files)",
            '', 0, ''
        );
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 15 — Test Runner
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Orchestrates all test modules and collects results.
 */
final class AdvancedSystemTestRunner
{
    private TestReport $report;

    /** @var BaseArchTest[] */
    private array $modules = [];

    public function __construct(private readonly string $projectRoot)
    {
        $this->report = new TestReport();
    }

    /** Register a test module. */
    public function addModule(BaseArchTest $module): static
    {
        $this->modules[] = $module;
        return $this;
    }

    /** Expose the report (useful for pre-run access by createDefault). */
    public function getReport(): TestReport
    {
        return $this->report;
    }

    /**
     * Run all registered modules, recording per-module timing.
     */
    public function run(): TestReport
    {
        foreach ($this->modules as $module) {
            $beforeTests = $this->report->getTestsRun();

            $elapsedMs = measureTime(fn() => $module->run());

            $testsThisRun = $this->report->getTestsRun() - $beforeTests;
            $this->report->addTiming(new ModuleTiming($module->name(), $elapsedMs, $testsThisRun));
        }

        return $this->report;
    }

    /**
     * Factory: construct runner with all default modules.
     */
    public static function createDefault(string $projectRoot): static
    {
        $runner = new static($projectRoot);
        $report = $runner->getReport();

        $runner->addModule(new ArchitectureValidation($report, $projectRoot));
        $runner->addModule(new PerformanceValidation($report, $projectRoot));
        $runner->addModule(new MultiTenantSafety($report, $projectRoot));
        $runner->addModule(new SecurityValidation($report, $projectRoot));
        $runner->addModule(new TypeSafetyValidation($report, $projectRoot));
        $runner->addModule(new ConfigurationSafety($report, $projectRoot));
        $runner->addModule(new ExceptionHandling($report, $projectRoot));
        $runner->addModule(new CodeQualityValidation($report, $projectRoot));
        $runner->addModule(new RuntimeSimulation($report, $projectRoot));

        return $runner;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 16 — Output Formatters
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Renders the TestReport in multiple formats.
 */
final class ReportFormatter
{
    // ─── CLI ─────────────────────────────────────────────────────────────

    public static function cli(TestReport $report): string
    {
        $out     = '';
        $score   = $report->score();
        $summary = $report->summaryCounts();
        [$grade] = $report->grade();

        $out .= "╔══════════════════════════════════════════════════════════════╗\n";
        $out .= "║   ADVANCED ARCHITECTURE & PERFORMANCE TEST REPORT  v3.0     ║\n";
        $out .= "╚══════════════════════════════════════════════════════════════╝\n\n";

        $gradeEmoji = match (true) {
            $score >= 90 => '🟢',
            $score >= 75 => '🟡',
            $score >= 60 => '🟠',
            $score >= 40 => '🔴',
            default      => '⛔',
        };

        $out .= "  Score     : {$score}/100  {$gradeEmoji} {$grade}\n";
        $out .= "  Tests run : {$report->getTestsRun()}\n";
        $out .= "  Total time: " . round($report->totalElapsedMs(), 1) . " ms\n";
        $out .= "  Critical  : {$summary[Severity::CRITICAL]}\n";
        $out .= "  Warnings  : {$summary[Severity::WARNING]}\n";
        $out .= "  Info      : {$summary[Severity::INFO]}\n\n";

        // Module timing table
        if ($report->getTimings()) {
            $out .= "  " . str_repeat('─', 56) . "\n";
            $out .= "  MODULE TIMINGS\n";
            $out .= "  " . str_repeat('─', 56) . "\n";
            foreach ($report->getTimings() as $t) {
                $name    = str_pad($t->name, 35, ' ');
                $elapsed = str_pad(round($t->elapsedMs, 1) . ' ms', 10);
                $tests   = "{$t->testsRun} tests";
                $out .= "  {$name} {$elapsed} {$tests}\n";
            }
            $out .= "\n";
        }

        foreach (Severity::all() as $sev) {
            $items = $report->findingsBySeverity($sev);
            if (empty($items)) {
                continue;
            }

            $icon = match ($sev) {
                Severity::CRITICAL => '❌',
                Severity::WARNING  => '⚠️ ',
                Severity::INFO     => 'ℹ️ ',
            };

            $out .= "  {$icon} {$sev} (" . count($items) . ")\n";
            $out .= "  " . str_repeat('─', 50) . "\n";

            foreach ($items as $f) {
                $loc = $f->file . ($f->line > 0 ? ":{$f->line}" : '');
                $out .= "  [{$f->category}] {$f->message}\n";
                $out .= "  [Module: {$f->module}]\n";
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

    // ─── HTML ────────────────────────────────────────────────────────────

    public static function html(TestReport $report): string
    {
        $score       = $report->score();
        $summary     = $report->summaryCounts();
        [$grLetter, $grColor, $grLabel] = $report->grade();
        $generatedAt = date('Y-m-d H:i:s');
        $totalMs     = round($report->totalElapsedMs(), 1);

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Architecture &amp; Performance Report v3.0</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0d1117;color:#c9d1d9;padding:24px;line-height:1.5}
.container{max-width:1280px;margin:0 auto}
h1{color:#58a6ff;font-size:1.8em;margin-bottom:4px}
.subtitle{color:#8b949e;margin-bottom:24px;font-size:.9em}
/* Score cards */
.score-grid{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:28px}
.card{background:#161b22;border:1px solid #30363d;border-radius:10px;padding:14px 22px;text-align:center;min-width:110px}
.card .lbl{color:#8b949e;font-size:.75em;text-transform:uppercase;letter-spacing:.06em}
.card .val{font-size:1.9em;font-weight:700;margin-top:4px}
.card .grade{font-size:3em;font-weight:800;line-height:1}
/* Timings */
.timings{background:#161b22;border:1px solid #30363d;border-radius:10px;margin-bottom:24px;overflow:hidden}
.timings table{width:100%;border-collapse:collapse;font-size:.85em}
.timings th{background:#1c2128;color:#8b949e;padding:8px 14px;text-align:left;font-weight:600}
.timings td{padding:7px 14px;border-top:1px solid #21262d}
.timings tr:hover td{background:#1c2128}
/* Findings */
.section{background:#161b22;border:1px solid #30363d;border-radius:10px;margin-bottom:14px;overflow:hidden}
.sec-hdr{padding:11px 16px;font-weight:600;display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none}
.sec-hdr:hover{background:#1c2128}
.sec-body{padding:0 16px 10px}
.finding{padding:10px 0 10px 12px;border-bottom:1px solid #21262d;border-left:4px solid transparent}
.finding:last-child{border-bottom:none}
.finding.CRITICAL{border-left-color:#f85149}
.finding.WARNING{border-left-color:#d29922}
.finding.INFO{border-left-color:#58a6ff}
.cat{color:#58a6ff;font-weight:600;font-size:.85em}
.mod{color:#6e7681;font-size:.75em;margin-top:1px}
.msg{margin-top:4px}
.loc{color:#8b949e;font-size:.82em;margin-top:3px;font-family:monospace}
.sug{color:#3fb950;font-size:.82em;margin-top:4px}
.badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:.72em;font-weight:700}
.badge.CRITICAL{background:#f8514922;color:#f85149}
.badge.WARNING{background:#d2992222;color:#d29922}
.badge.INFO{background:#58a6ff22;color:#58a6ff}
.pass-msg{color:#3fb950;font-size:1.2em;padding:32px;text-align:center}
.bar{height:6px;border-radius:3px;background:#21262d;margin-top:8px}
.bar-fill{height:100%;border-radius:3px;transition:width .4s ease}
</style>
</head>
<body>
<div class="container">
  <h1>🏗️ Architecture &amp; Performance Report</h1>
  <p class="subtitle">v3.0 — Generated: <?= $h($generatedAt) ?> — Total analysis time: <?= $totalMs ?> ms</p>

  <div class="score-grid">
    <div class="card">
      <div class="lbl">Score</div>
      <div class="val" style="color:<?= $h($grColor) ?>"><?= $score ?></div>
      <div class="bar"><div class="bar-fill" style="width:<?= $score ?>%;background:<?= $h($grColor) ?>"></div></div>
    </div>
    <div class="card">
      <div class="lbl">Grade</div>
      <div class="grade" style="color:<?= $h($grColor) ?>"><?= $h($grLetter) ?></div>
      <div style="font-size:.8em;color:#8b949e;margin-top:2px"><?= $h($grLabel) ?></div>
    </div>
    <div class="card">
      <div class="lbl">Tests Run</div>
      <div class="val"><?= $report->getTestsRun() ?></div>
    </div>
    <div class="card">
      <div class="lbl">Critical</div>
      <div class="val" style="color:#f85149"><?= $summary[Severity::CRITICAL] ?></div>
    </div>
    <div class="card">
      <div class="lbl">Warnings</div>
      <div class="val" style="color:#d29922"><?= $summary[Severity::WARNING] ?></div>
    </div>
    <div class="card">
      <div class="lbl">Info</div>
      <div class="val" style="color:#58a6ff"><?= $summary[Severity::INFO] ?></div>
    </div>
  </div>

<?php if ($report->getTimings()): ?>
  <div class="timings">
    <table>
      <thead><tr><th>Module</th><th>Time (ms)</th><th>Tests</th><th>Findings</th></tr></thead>
      <tbody>
<?php foreach ($report->getTimings() as $t): ?>
        <tr>
          <td><?= $h($t->name) ?></td>
          <td><?= round($t->elapsedMs, 1) ?></td>
          <td><?= $t->testsRun ?></td>
          <td><?= count($report->findingsByModule($t->name)) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if (empty($report->getFindings())): ?>
  <div class="pass-msg">✅ ALL TESTS PASSED — Architecture is clean!</div>
<?php else: ?>
<?php foreach (Severity::all() as $sev): ?>
<?php $items = $report->findingsBySeverity($sev); if (empty($items)) continue; ?>
  <div class="section">
    <div class="sec-hdr">
      <?= $sev === Severity::CRITICAL ? '❌' : ($sev === Severity::WARNING ? '⚠️' : 'ℹ️') ?>
      &nbsp;<?= $h($sev) ?>
      <span class="badge <?= $h($sev) ?>"><?= count($items) ?></span>
    </div>
    <div class="sec-body">
<?php foreach ($items as $f): ?>
      <div class="finding <?= $h($f->severity) ?>">
        <div class="cat">[<?= $h($f->category) ?>]</div>
        <div class="mod">Module: <?= $h($f->module) ?></div>
        <div class="msg"><?= $h($f->message) ?></div>
<?php if ($f->file): ?><div class="loc">📁 <?= $h($f->file) ?><?= $f->line > 0 ? ':' . $f->line : '' ?></div><?php endif; ?>
<?php if ($f->suggestion): ?><div class="sug">💡 <?= $h($f->suggestion) ?></div><?php endif; ?>
      </div>
<?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</body>
</html>
<?php
        return (string) ob_get_clean();
    }

    // ─── JSON ────────────────────────────────────────────────────────────

    public static function json(TestReport $report): string
    {
        $data = [
            'meta' => [
                'version'     => '3.0.0',
                'generated'   => date('c'),
                'score'       => $report->score(),
                'grade'       => $report->grade()[0],
                'tests_run'   => $report->getTestsRun(),
                'total_ms'    => round($report->totalElapsedMs(), 1),
            ],
            'summary' => $report->summaryCounts(),
            'modules' => array_map(
                fn(ModuleTiming $t) => [
                    'name'     => $t->name,
                    'ms'       => round($t->elapsedMs, 2),
                    'tests'    => $t->testsRun,
                    'findings' => count($report->findingsByModule($t->name)),
                ],
                $report->getTimings()
            ),
            'findings' => array_map(
                fn(Finding $f) => [
                    'severity'   => $f->severity,
                    'module'     => $f->module,
                    'category'   => $f->category,
                    'message'    => $f->message,
                    'file'       => $f->file,
                    'line'       => $f->line,
                    'suggestion' => $f->suggestion,
                ],
                $report->getFindings()
            ),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ─── Markdown ────────────────────────────────────────────────────────

    public static function markdown(TestReport $report): string
    {
        $score   = $report->score();
        $summary = $report->summaryCounts();
        [$grLetter, , $grLabel] = $report->grade();
        $md      = '';

        $md .= "# Architecture & Performance Report v3.0\n\n";
        $md .= "> Generated: " . date('Y-m-d H:i:s') . "  \n";
        $md .= "> Total analysis time: " . round($report->totalElapsedMs(), 1) . " ms\n\n";

        $md .= "## Score: {$score}/100 — {$grLetter} ({$grLabel})\n\n";
        $md .= "| Metric     | Value |\n";
        $md .= "|------------|-------|\n";
        $md .= "| Tests Run  | {$report->getTestsRun()} |\n";
        $md .= "| Critical   | {$summary[Severity::CRITICAL]} |\n";
        $md .= "| Warnings   | {$summary[Severity::WARNING]} |\n";
        $md .= "| Info       | {$summary[Severity::INFO]} |\n\n";

        if ($report->getTimings()) {
            $md .= "## Module Timings\n\n";
            $md .= "| Module | Time (ms) | Tests | Findings |\n";
            $md .= "|--------|-----------|-------|----------|\n";
            foreach ($report->getTimings() as $t) {
                $findings = count($report->findingsByModule($t->name));
                $md .= "| {$t->name} | " . round($t->elapsedMs, 1) . " | {$t->testsRun} | {$findings} |\n";
            }
            $md .= "\n";
        }

        foreach (Severity::all() as $sev) {
            $items = $report->findingsBySeverity($sev);
            if (empty($items)) {
                continue;
            }

            $icon = match ($sev) {
                Severity::CRITICAL => '❌',
                Severity::WARNING  => '⚠️',
                Severity::INFO     => 'ℹ️',
            };

            $md .= "## {$icon} {$sev} (" . count($items) . ")\n\n";

            foreach ($items as $f) {
                $loc = $f->file . ($f->line > 0 ? ":{$f->line}" : '');
                $md .= "### [{$f->category}] — *{$f->module}*\n\n";
                $md .= "**{$f->message}**\n\n";
                if ($loc) {
                    $md .= "📁 `{$loc}`\n\n";
                }
                if ($f->suggestion) {
                    $md .= "💡 _{$f->suggestion}_\n\n";
                }
                $md .= "---\n\n";
            }
        }

        if (empty($report->getFindings())) {
            $md .= "## ✅ ALL TESTS PASSED — Architecture is clean!\n";
        }

        return $md;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 17 — Entry Point
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detect project root:
 *   CLI  → first argument, or parent of the script directory
 *   HTTP → $_GET['root'] (sanitized), or parent of the script directory
 */
function resolveProjectRoot(): string
{
    if (PHP_SAPI === 'cli') {
        $arg = $argv[1] ?? null;
        if ($arg !== null && is_dir($arg)) {
            return realpath($arg);
        }
        // Default: parent of the directory containing this script
        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }

    // HTTP
    $get = $_GET['root'] ?? null;
    if ($get !== null) {
        $sanitized = realpath($get);
        if ($sanitized !== false && is_dir($sanitized)) {
            return $sanitized;
        }
    }

    return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
}

/**
 * Determine the requested output format.
 *   CLI  → 'cli' (unless --format=json|md|markdown is passed)
 *   HTTP → ?format=html|json|md (default: html)
 */
function resolveFormat(): string
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach ($argv as $arg) {
            if (preg_match('/^--format=(.+)$/', $arg, $m)) {
                return strtolower(trim($m[1]));
            }
        }
        return 'cli';
    }

    return strtolower(trim($_GET['format'] ?? 'html'));
}

// ─── Run (only when invoked directly, not when require'd) ────────────────────

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $projectRoot = resolveProjectRoot();
    $format      = resolveFormat();

    $runner = AdvancedSystemTestRunner::createDefault($projectRoot);
    $report = $runner->run();

    switch ($format) {
        case 'json':
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo ReportFormatter::json($report);
            break;

        case 'md':
        case 'markdown':
            if (!headers_sent()) {
                header('Content-Type: text/markdown; charset=UTF-8');
            }
            echo ReportFormatter::markdown($report);
            break;

        case 'html':
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo ReportFormatter::html($report);
            break;

        case 'cli':
        default:
            echo ReportFormatter::cli($report);
            break;
    }
}
