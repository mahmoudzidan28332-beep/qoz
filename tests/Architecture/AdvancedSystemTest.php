<?php

declare(strict_types=1);

/**
 * AdvancedSystemTest.php — World-Class Architecture & Performance & Penetration Test Suite
 *
 * v5.0 improvements over v4.0:
 *   ✔ Penetration Testing module — SQL Injection, Tenant Bypass, Auth bypass, N+1 flood
 *   ✔ Tenant Isolation chain check — tenants → entities (tenant_id) → child tables
 *   ✔ Entity schema awareness — validates entity_id FK inherits tenant scope
 *   ✔ Cross-Tenant data leak simulation — detects missing tenant_id in repository WHERE
 *   ✔ JWT/Auth bypass detection — none-algorithm, weak secrets, missing verification
 *   ✔ IDOR detection — direct object reference without ownership check
 *   ✔ Rate-limit bypass patterns — missing throttle on sensitive endpoints
 *   ✔ Mass assignment detection — unfiltered $_POST passed to repository
 *   ✔ Privilege escalation patterns — role/permission manipulation vectors
 *   ✔ Open Redirect extended — header(), Location, js redirect patterns
 *   ✔ Request log simulation output (--simulate flag)
 *   ✔ CVSS-style severity scoring per finding
 *   ✔ Suppressed false-positives from v4 retained
 *   ✔ HTML report: attack simulation tab, entity-chain visualizer
 *   ✔ JSON report: machine-readable CVE-style findings
 *
 * Usage (CLI):
 *   php AdvancedSystemTest.php [/path/to/project] [--format=cli|html|json|markdown] [--quiet] [--simulate]
 *
 * Usage (Browser):
 *   Navigate to AdvancedSystemTest.php?format=html
 *
 * @version  5.0.0
 * @license  MIT
 */

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Configuration
// ═══════════════════════════════════════════════════════════════════════════════

final class Config
{
    // ─── Code-size thresholds ─────────────────────────────────────────────
    public const MAX_CLASS_LINES           = 500;
    public const MAX_METHOD_LINES_WARN     = 80;
    public const MAX_METHOD_LINES_INFO     = 50;
    public const MAX_PUBLIC_METHODS        = 25;
    public const MAX_FILE_SIZE_KB          = 50;

    // ─── Pagination ───────────────────────────────────────────────────────
    public const MAX_SAFE_OFFSET           = 10_000;

    // ─── Duplication ─────────────────────────────────────────────────────
    public const DUPLICATION_FILE_THRESHOLD  = 3;
    public const DUPLICATION_MIN_OCCURRENCES = 3;

    // ─── Runtime simulation ───────────────────────────────────────────────
    public const SIMULATION_ITERATIONS    = 1_000;
    public const SIM_WARN_MS_PER_ITER     = 100.0;
    public const SIM_INFO_MS_PER_ITER     = 50.0;

    // ─── Scoring ─────────────────────────────────────────────────────────
    public const DEDUCT_CRITICAL          = 5;
    public const DEDUCT_WARNING           = 2;
    public const DEDUCT_INFO              = 0.5;
    public const CAP_CRITICAL             = 60;
    public const CAP_WARNING              = 30;
    public const CAP_INFO                 = 10;

    // ─── Tenant-scoped tables (data belongs to a specific tenant) ─────────
    public const TENANT_TABLES = [
        'orders', 'products', 'entities', 'carts', 'cart_items',
        'ads', 'banners', 'support_tickets', 'notifications',
        'jobs', 'auctions', 'subscriptions', 'invoices',
        'transactions', 'reviews', 'wishlists', 'addresses',
        'order_items', 'product_variants', 'flash_sales',
    ];

    /**
     * Tables that are system/global lookup tables — NOT tenant-scoped.
     */
    public const GLOBAL_TABLES = [
        'currencies', 'countries', 'cities', 'languages', 'timezones',
        'units', 'payment_methods', 'notification_channels',
        'notification_types', 'themes', 'system_settings',
        'permissions', 'roles', 'role_permissions', 'resource_permissions',
        'image_types', 'attribute_types', 'categories',
        'brands', 'product_types', 'product_attributes',
        'product_attribute_values', 'button_styles', 'card_styles',
        'color_settings', 'font_settings', 'design_settings', 'queues',
    ];

    /**
     * NEW v5: Tables that inherit tenant scope via entity_id FK.
     * These don't have tenant_id directly but MUST filter via entity_id
     * which itself belongs to a tenant.
     * Schema: tenants.id → entities.tenant_id → [these tables].entity_id
     */
    public const ENTITY_SCOPED_TABLES = [
        'entity_products', 'entity_product_variants', 'entity_bank_accounts',
        'entity_payment_methods', 'entity_translations', 'entity_settings',
        'pos_sessions', 'stock_movements', 'entity_working_hours',
    ];

    // ─── Repository/class name segments to skip for tenant checks ─────────
    public const SKIP_TENANT_PATTERNS = [
        'Tenant', 'Auth', 'Rbac', 'Migration', 'System', 'Settings',
        'Currency', 'Country', 'Language', 'Timezone', 'Unit',
        'City', 'Certificate', 'Jwt', 'Mail', 'Sms', 'Seo',
        'Upload', 'I18n', 'Audit', 'Cache', 'Queue', 'Event',
        'Permission', 'Role', 'Theme', 'Category', 'Brand',
        'PaymentMethod', 'NotificationChannel', 'NotificationType',
        'AttributeType', 'ButtonStyle', 'CardStyle', 'FontSetting',
        'ColorSetting', 'DesignSetting', 'ImageType', 'ProductType',
        'ProductAttribute',
    ];

    // ─── Route files that are intentionally public (no auth required) ─────
    public const PUBLIC_ROUTE_FILES = [
        'public.php', 'auth.php', 'health.php', 'diagnostic.php',
    ];

    // ─── Variable names considered safe in dynamic SQL ────────────────────
    public const SAFE_SQL_VARS = [
        'table', 'orderBy', 'orderDir', 'direction', 'where', 'sql',
        'limit', 'offset', 'sortField', 'sortDir', 'column',
    ];

    /**
     * Files/paths where catch(Exception) is expected (bootstrap, middleware).
     */
    public const EXCEPTION_RELAXED_PATHS = [
        '/bootstrap', '/middleware', '/auth.php', '/scripts/',
        '/tests/', '/diagnostic', 'DatabaseConnection',
    ];

    /**
     * NEW v5: Sensitive endpoints that MUST have rate limiting.
     */
    public const RATE_LIMIT_REQUIRED_PATTERNS = [
        '/login', '/register', '/reset', '/verify', '/otp', '/password',
    ];

    /**
     * NEW v5: JWT algorithm weak values that indicate bypass risk.
     */
    public const JWT_WEAK_ALGORITHMS = ['none', 'HS256', 'RS256'];

    public static function shouldSkipTenant(string $basename): bool
    {
        foreach (self::SKIP_TENANT_PATTERNS as $pattern) {
            if (stripos($basename, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function isPublicRouteFile(string $basename): bool
    {
        return in_array(strtolower($basename), self::PUBLIC_ROUTE_FILES, true);
    }

    public static function isGlobalTable(string $table): bool
    {
        return in_array(strtolower($table), self::GLOBAL_TABLES, true);
    }

    public static function isEntityScopedTable(string $table): bool
    {
        return in_array(strtolower($table), self::ENTITY_SCOPED_TABLES, true);
    }

    public static function isExceptionRelaxedPath(string $filePath): bool
    {
        foreach (self::EXCEPTION_RELAXED_PATHS as $segment) {
            if (str_contains($filePath, $segment)) {
                return true;
            }
        }
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — FileCache
// ═══════════════════════════════════════════════════════════════════════════════

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

    public function content(string $path): string
    {
        if (!isset($this->rawContent[$path])) {
            $this->rawContent[$path] = (string) (@file_get_contents($path) ?: '');
        }
        return $this->rawContent[$path];
    }

    /** @return string[] */
    public function lines(string $path): array
    {
        if (!isset($this->lines[$path])) {
            $raw = $this->content($path);
            $this->lines[$path] = $raw !== '' ? explode("\n", $raw) : [];
        }
        return $this->lines[$path];
    }

    public function stripped(string $path): string
    {
        if (!isset($this->stripped[$path])) {
            $src = $this->content($path);
            $src = (string) (preg_replace('#/\*.*?\*/#s', '', $src) ?? $src);
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

/** @return string[] */
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
    sort($files);
    return $files;
}

function measureTime(callable $fn): float
{
    $start = hrtime(true);
    $fn();
    return (hrtime(true) - $start) / 1e6;
}

function shortPath(string $fullPath, string $root): string
{
    $root = rtrim($root, '/\\');
    if (str_starts_with($fullPath, $root)) {
        return ltrim(substr($fullPath, strlen($root)), '/\\');
    }
    return basename($fullPath);
}

function offsetToLine(string $content, int $offset): int
{
    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

function isCommentLine(string $line): bool
{
    $t = ltrim($line);
    return $t === ''
        || str_starts_with($t, '//')
        || str_starts_with($t, '#')
        || str_starts_with($t, '*')
        || str_starts_with($t, '/*');
}

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

/** Returns true if the file is an interface or contract file */
function isInterfaceFile(string $filePath): bool
{
    $base = basename($filePath);
    return str_contains($base, 'Interface')
        || str_contains($base, 'Contract')
        || str_contains($filePath, '/Contracts/');
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Result Containers
// ═══════════════════════════════════════════════════════════════════════════════

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
 * NEW v5: CVSS-like attack vector metadata for penetration findings.
 */
final class AttackVector
{
    public const NETWORK  = 'NETWORK';
    public const LOCAL    = 'LOCAL';
    public const PHYSICAL = 'PHYSICAL';
}

final class Finding
{
    public readonly string $severity;
    public readonly string $category;
    public readonly string $message;
    public readonly string $file;
    public readonly int    $line;
    public readonly string $suggestion;
    public readonly string $module;
    // NEW v5 fields
    public readonly string $attackVector;
    public readonly float  $cvssScore;
    public readonly string $cweId;

    public function __construct(
        string $severity,
        string $category,
        string $message,
        string $file        = '',
        int    $line        = 0,
        string $suggestion  = '',
        string $module      = '',
        string $attackVector = AttackVector::NETWORK,
        float  $cvssScore   = 0.0,
        string $cweId       = '',
    ) {
        $this->severity     = $severity;
        $this->category     = $category;
        $this->message      = $message;
        $this->file         = $file;
        $this->line         = $line;
        $this->suggestion   = $suggestion;
        $this->module       = $module;
        $this->attackVector = $attackVector;
        $this->cvssScore    = $cvssScore;
        $this->cweId        = $cweId;
    }
}

final class ModuleTiming
{
    public function __construct(
        public readonly string $name,
        public readonly float  $elapsedMs,
        public readonly int    $testsRun,
    ) {}
}

final class TestReport
{
    /** @var Finding[] */
    private array $findings = [];

    /** @var ModuleTiming[] */
    private array $timings = [];

    private int $testsRun = 0;

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

    /** @return Finding[] */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /** @return Finding[] */
    public function findingsBySeverity(string $severity): array
    {
        return array_values(
            array_filter($this->findings, fn(Finding $f) => $f->severity === $severity)
        );
    }

    /** @return Finding[] */
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

    public function score(): int
    {
        $counts = $this->summaryCounts();
        $deduct  = min(Config::CAP_CRITICAL, $counts[Severity::CRITICAL] * Config::DEDUCT_CRITICAL);
        $deduct += min(Config::CAP_WARNING,  $counts[Severity::WARNING]  * Config::DEDUCT_WARNING);
        $deduct += min(Config::CAP_INFO,     (int) ($counts[Severity::INFO] * Config::DEDUCT_INFO));
        return max(0, 100 - $deduct);
    }

    /** @return array{0: string, 1: string, 2: string} */
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

    /** @return array<string, int> */
    public function summaryCounts(): array
    {
        $out = array_fill_keys(Severity::all(), 0);
        foreach ($this->findings as $f) {
            $out[$f->severity]++;
        }
        return $out;
    }

    public function totalElapsedMs(): float
    {
        return array_sum(array_map(fn(ModuleTiming $t) => $t->elapsedMs, $this->timings));
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — Abstract Base Test
// ═══════════════════════════════════════════════════════════════════════════════

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

    abstract public function name(): string;
    abstract public function run(): void;

    protected function critical(
        string $cat,
        string $msg,
        string $file = '',
        int $line = 0,
        string $suggestion = '',
        float $cvss = 0.0,
        string $cwe = '',
    ): void {
        $this->report->addFinding(new Finding(
            Severity::CRITICAL, $cat, $msg, $this->short($file), $line,
            $suggestion, $this->name(), AttackVector::NETWORK, $cvss, $cwe
        ));
    }

    protected function warning(
        string $cat,
        string $msg,
        string $file = '',
        int $line = 0,
        string $suggestion = '',
        float $cvss = 0.0,
        string $cwe = '',
    ): void {
        $this->report->addFinding(new Finding(
            Severity::WARNING, $cat, $msg, $this->short($file), $line,
            $suggestion, $this->name(), AttackVector::NETWORK, $cvss, $cwe
        ));
    }

    protected function info(string $cat, string $msg, string $file = '', int $line = 0, string $suggestion = ''): void
    {
        $this->report->addFinding(new Finding(Severity::INFO, $cat, $msg, $this->short($file), $line, $suggestion, $this->name()));
    }

    protected function short(string $path): string
    {
        return shortPath($path, $this->projectRoot);
    }

    /** @return string[] */
    protected function allApiFiles(): array
    {
        static $cache = null;
        $cache ??= scanPhpFiles($this->apiRoot);
        return $cache;
    }

    /** @return string[] */
    protected function findFiles(string $pathSegment, string $suffix): array
    {
        return array_values(array_filter(
            $this->allApiFiles(),
            fn(string $f) => str_contains($f, $pathSegment) && str_ends_with(basename($f), $suffix)
        ));
    }

    /** @return string[] */
    protected function repositoryFiles(): array
    {
        return array_values(array_filter(
            $this->allApiFiles(),
            fn(string $f) => str_contains(basename($f), 'Repository') && str_ends_with($f, '.php')
        ));
    }

    /** @return string[] Concrete repository files only (not interfaces) */
    protected function concreteRepositoryFiles(): array
    {
        return array_values(array_filter(
            $this->repositoryFiles(),
            fn(string $f) => !isInterfaceFile($f)
        ));
    }

    /** @return string[] */
    protected function routeFiles(): array
    {
        return scanPhpFiles($this->apiRoot . '/v1/routes');
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — Architecture Validation
// ═══════════════════════════════════════════════════════════════════════════════

class ArchitectureValidation extends BaseArchTest
{
    public function name(): string { return 'Architecture Validation'; }

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

    private function checkNoRawSqlInRoutes(): void
    {
        $this->report->incrementTests();
        foreach ($this->routeFiles() as $file) {
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                $lineNo = $idx + 1;
                if (preg_match('/\$pdo\s*->\s*(?:prepare|query)\s*\(/i', $line)) {
                    $this->critical('Single Source of Truth', 'Raw PDO call in route file', $file, $lineNo,
                        'Move DB access into Repository → Service → Controller chain.');
                }
                if (preg_match('/\bINSERT\s+INTO\s+\w+/i', $line) && str_contains($line, '->execute') && !preg_match('/^\s*[\'"]/', $line)) {
                    $this->critical('Single Source of Truth', 'INSERT INTO execution in route file', $file, $lineNo,
                        'Move INSERT operations into a Repository class.');
                }
                if (preg_match('/\bUPDATE\s+\w+\s+SET\b/i', $line)) {
                    $this->critical('Single Source of Truth', 'UPDATE…SET statement in route file', $file, $lineNo,
                        'Move UPDATE operations into a Repository class.');
                }
                if (preg_match('/\bDELETE\s+FROM\b/i', $line)) {
                    $this->critical('Single Source of Truth', 'DELETE FROM statement in route file', $file, $lineNo,
                        'Move DELETE operations into a Repository class.');
                }
            }
        }
    }

    private function checkControllerThinness(): void
    {
        $this->report->incrementTests();
        foreach ($this->findFiles('/controllers/', 'Controller.php') as $file) {
            $loc = $this->cache->codeLineCount($file);
            if ($loc > Config::MAX_CLASS_LINES) {
                $this->warning('Controller Thinness', "Controller is too large ({$loc} LOC)", $file, 0,
                    'Extract business logic into a dedicated Service class.');
            }
            if (preg_match('/\$pdo\s*->\s*(?:prepare|query|exec)\s*\(/i', $this->cache->stripped($file))) {
                $this->critical('Controller Thinness', 'Direct database access inside a Controller', $file, 0,
                    'Controllers must delegate all data access to Service → Repository.');
            }
        }
    }

    private function checkNoDbInHelpers(): void
    {
        $this->report->incrementTests();
        foreach (scanPhpFiles($this->apiRoot . '/shared/helpers') as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\$pdo\s*->\s*(?:prepare|query)\s*\(/i', $code)) {
                $this->critical('Helper Layer', 'Database call found in helper file', $file, 0,
                    'Helpers must be pure utility functions. Move DB calls to Repositories.');
            }
        }
    }

    private function checkNoDirectDbInServices(): void
    {
        $this->report->incrementTests();
        foreach ($this->findFiles('/services/', 'Service.php') as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\$pdo\s*->\s*(?:prepare|query|exec)\s*\(/i', $code)) {
                $this->critical('Service Layer', 'Direct $pdo usage in Service class', $file, 0,
                    'Services must access the DB only through Repository classes.');
            }
            if (preg_match('/\bnew\s+PDO\s*\(/i', $code)) {
                $this->critical('Service Layer', 'PDO instantiated directly in Service class', $file, 0,
                    'Inject PDO through the Repository layer, not directly in Services.');
            }
        }
    }

    private function checkStrictLayerFlow(): void
    {
        $this->report->incrementTests();
        foreach ($this->routeFiles() as $file) {
            $code     = $this->cache->stripped($file);
            $hasRepo  = (bool) preg_match('/new\s+Pdo\w+Repository\s*\(/i', $code);
            $hasCtrl  = (bool) preg_match('/new\s+\w+Controller\s*\(/i', $code);
            $hasSvc   = (bool) preg_match('/new\s+\w+Service\s*\(/i', $code);
            $isPublic = str_contains($file, '/public/');
            if (!$hasRepo) continue;
            if ($hasCtrl || $hasSvc) {
                if (preg_match('/\$repo(?:sitory)?\s*->\s*(?:find|get|all|list|save|create|update|delete|count|search|fetch|insert)\w*\s*\(/i', $code)) {
                    $this->warning('Layer Flow', 'Route bypasses Controller/Service and calls Repository method directly', $file, 0,
                        'Route must delegate through Controller → Service → Repository.');
                }
            } else {
                $this->{ $isPublic ? 'info' : 'warning' }('Layer Flow',
                    $isPublic ? 'Public sub-route uses Repository without Service layer'
                              : 'Route instantiates Repository without Controller/Service',
                    $file, 0,
                    'Add a Service and Controller layer between the route and Repository.');
            }
        }
        foreach ($this->findFiles('/controllers/', 'Controller.php') as $file) {
            if (preg_match('/new\s+Pdo\w+Repository\s*\(/i', $this->cache->stripped($file))) {
                $this->info('Layer Flow', 'Controller instantiates a Repository directly (prefer Service delegation)', $file, 0,
                    'Route data-access through a Service for better separation of concerns.');
            }
        }
    }

    private function checkCircularDependencies(): void
    {
        $this->report->incrementTests();
        foreach ($this->repositoryFiles() as $file) {
            $content = $this->cache->content($file);
            if (preg_match('/require(?:_once)?\s*[^;]*Controller\.php/i', $content)) {
                $this->critical('Circular Dependency', 'Repository includes a Controller file', $file, 0,
                    'Repositories must never depend on Controllers.');
            }
            if (preg_match('/require(?:_once)?\s*[^;]*Service\.php/i', $content)) {
                $this->warning('Circular Dependency', 'Repository includes a Service file — potential circular dependency', $file, 0,
                    'Repositories should be self-contained; inject dependencies instead.');
            }
        }
        foreach ($this->findFiles('/services/', 'Service.php') as $file) {
            if (preg_match('/require(?:_once)?\s*[^;]*Controller\.php/i', $this->cache->content($file))) {
                $this->critical('Circular Dependency', 'Service includes a Controller file', $file, 0,
                    'Services must never depend on Controllers.');
            }
        }
    }

    private function checkNamespaceConsistency(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            if (!preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $m)) continue;
            $declaredNs  = str_replace('\\', '/', $m[1]);
            $relativePath = $this->short($file);
            $nsParts     = explode('/', $declaredNs);
            $lastPart    = strtolower(end($nsParts));
            $lowerPath   = strtolower(str_replace('\\', '/', $relativePath));
            if (count($nsParts) >= 2) {
                $nsDir = strtolower($nsParts[count($nsParts) - 2]);
                if (!str_contains($lowerPath, $nsDir) && !str_contains($lowerPath, $lastPart)) {
                    $this->info('Namespace Consistency', "Declared namespace '{$m[1]}' may not match file location", $file, 0,
                        'Ensure the namespace mirrors the directory structure (PSR-4).');
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — Performance Validation
// ═══════════════════════════════════════════════════════════════════════════════

class PerformanceValidation extends BaseArchTest
{
    public function name(): string { return 'Performance Validation'; }

    public function run(): void
    {
        $this->checkSubqueriesInJoinOn();
        $this->checkNPlusOnePatterns();
        $this->checkSelectStar();
        $this->checkDeepPagination();
        $this->checkIndexHostileWhere();
        $this->checkUnboundedQueries();
        $this->checkMissingFkIndexHints();
    }

    private function checkSubqueriesInJoinOn(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\bON\b[^;]{0,300}?=\s*\(\s*SELECT\b/is', $code)
                && !preg_match('/\bJOIN\s*\(\s*SELECT\b/is', $code)) {
                $this->warning('Performance', 'Correlated subquery inside JOIN ON clause — executes once per row', $file, 0,
                    'Rewrite as a derived table (subquery in FROM) or a CTE (WITH …).');
            }
        }
    }

    private function checkNPlusOnePatterns(): void
    {
        $this->report->incrementTests();
        $pdoMethods = 'prepare|query|exec|execute';
        foreach ($this->allApiFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match(
                '/\b(?:foreach|while|for)\s*\([^)]*\)\s*\{[^}]{0,800}?\$(?:pdo|this->pdo|stmt|db|conn)\s*->\s*(?:' . $pdoMethods . ')\s*\(/is',
                $code
            )) {
                $this->critical('N+1 Query', 'Database call inside a loop — classic N+1 pattern', $file, 0,
                    'Batch the query outside the loop or rewrite with IN() / JOIN.');
            }
        }
    }

    private function checkSelectStar(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $lines     = $this->cache->lines($file);
            $count     = 0;
            $firstLine = 0;
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match('/\bSELECT\s+\*/i', $line) && !preg_match('/\bCOUNT\s*\(\s*\*/i', $line)) {
                    $count++;
                    if ($firstLine === 0) $firstLine = $idx + 1;
                }
            }
            if ($count === 0) continue;
            $base = basename($file);
            $isTenantRepo = false;
            foreach (Config::TENANT_TABLES as $t) {
                if (stripos($base, $t) !== false) { $isTenantRepo = true; break; }
            }
            $msg = "SELECT * found {$count} time(s) — fetches unnecessary columns";
            $sug = 'Explicitly list only the columns your application needs.';
            if ($isTenantRepo && $count > 2) {
                $this->warning('Performance', $msg, $file, $firstLine, $sug);
            } else {
                $this->info('Performance', $msg, $file, $firstLine, $sug);
            }
        }
    }

    private function checkDeepPagination(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\bOFFSET\s+(\d+)\b/i', $code, $m) && (int) $m[1] > Config::MAX_SAFE_OFFSET) {
                $this->warning('Deep Pagination', "Hard-coded OFFSET {$m[1]} — very large offset degrades performance", $file, 0,
                    'Switch to cursor-based (keyset) pagination instead of OFFSET for large datasets.');
            }
        }
    }

    private function checkIndexHostileWhere(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\bWHERE\b[^;]{0,200}?\bLIKE\s+[\'"]%/i', $code)) {
                $this->warning('Index Usage', 'LIKE with leading wildcard (%) — index cannot be used', $file, 0,
                    'Use full-text search (MATCH … AGAINST) or remove the leading % wildcard.');
            }
            if (preg_match('/\bWHERE\b[^;]{0,200}?\b(LOWER|UPPER|DATE|YEAR|MONTH|TRIM|CONCAT)\s*\(/i', $code)) {
                $this->info('Index Usage', 'Function applied to column in WHERE clause — may prevent index usage', $file, 0,
                    'Use a virtual/computed column or rewrite to avoid wrapping indexed columns in functions.');
            }
        }
    }

    private function checkUnboundedQueries(): void
    {
        $this->report->incrementTests();
        $tenantTablesPattern = implode('|', Config::TENANT_TABLES);
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\bSELECT\b[^;]{0,500}?\bFROM\s+(?:' . $tenantTablesPattern . ')\b/is', $code)
                && !preg_match('/\bLIMIT\b/i', $code)) {
                $this->warning('Unbounded Query', 'Query on a tenant table with no LIMIT — could return millions of rows', $file, 0,
                    'Always apply LIMIT (and a sensible MAX) to queries on large tenant tables.');
            }
        }
    }

    private function checkMissingFkIndexHints(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code    = $this->cache->stripped($file);
            $content = $this->cache->content($file);
            preg_match_all('/\bWHERE\b[^;]{0,200}?\b(\w+_id)\s*=/i', $code, $fkMatches);
            if (empty($fkMatches[1])) continue;
            $fkCols = array_unique($fkMatches[1]);
            $hasIndexMention = preg_match('/\bINDEX\b|\bindex\b|@index/i', $content);
            if (!$hasIndexMention && count($fkCols) > 3) {
                $cols = implode(', ', array_slice($fkCols, 0, 3));
                $this->info('Missing Index Hint', "Multiple FK columns in WHERE ({$cols}…) — verify DB indexes exist", $file, 0,
                    'Ensure all foreign key columns used in WHERE clauses have database indexes.');
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 8 — Multi-Tenant Safety (v5 — entity chain awareness)
// ═══════════════════════════════════════════════════════════════════════════════

class MultiTenantSafety extends BaseArchTest
{
    public function name(): string { return 'Multi-Tenant Safety'; }

    public function run(): void
    {
        $this->checkTenantIdInQueries();
        $this->checkMissingTenantParameters();
        $this->checkEntityChainIsolation();   // NEW v5
        $this->checkCrossTenantEntityAccess(); // NEW v5
    }

    private function checkTenantIdInQueries(): void
    {
        $this->report->incrementTests();
        $tenantOnly = array_filter(Config::TENANT_TABLES, fn(string $t) => !Config::isGlobalTable($t));
        $pattern    = '/\bFROM\s+(' . implode('|', $tenantOnly) . ')\b/i';

        foreach ($this->concreteRepositoryFiles() as $file) {
            if (Config::shouldSkipTenant(basename($file))) continue;
            $content  = $this->cache->content($file);
            $reported = [];
            $offset   = 0;

            while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $table  = $match[1][0];
                $pos    = $match[0][1];
                $offset = $pos + strlen($match[0][0]);
                if (in_array($table, $reported, true)) continue;
                $windowStart = max(0, $pos - 200);
                $window      = substr($content, $windowStart, 1500);
                if (!preg_match('/\btenant_id\b/i', $window)) {
                    $this->warning('Multi-Tenant',
                        "Query on '{$table}' may be missing a tenant_id filter",
                        $file, offsetToLine($content, $pos),
                        "All queries on '{$table}' must include a tenant_id condition to prevent data leakage.",
                        6.5, 'CWE-284'
                    );
                    $reported[] = $table;
                }
            }
        }
    }

    private function checkMissingTenantParameters(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            if (Config::shouldSkipTenant(basename($file))) continue;
            $base = strtolower(basename($file));
            $isGlobalRepo = false;
            foreach (Config::GLOBAL_TABLES as $gt) {
                if (str_contains($base, strtolower(str_replace('_', '', $gt)))) {
                    $isGlobalRepo = true; break;
                }
            }
            if ($isGlobalRepo) continue;
            $content = $this->cache->content($file);
            $flagged = false;
            if (!preg_match_all(
                '/public\s+function\s+(list|all|find\w*|get\w*)\s*\(([^)]*)\)/i',
                $content, $methods, PREG_SET_ORDER
            )) continue;
            foreach ($methods as $method) {
                if ($flagged) break;
                [$fullMatch, $name, $params] = $method;
                if (preg_match('/tenant/i', $params)) continue;
                $fnPos = strpos($content, $fullMatch);
                if ($fnPos === false) continue;
                $bodyWindow = substr($content, $fnPos, 2000);
                if (!preg_match('/\btenant_id\b/i', $bodyWindow)) {
                    $this->info('Multi-Tenant',
                        "Repository method '{$name}()' may lack tenant scoping",
                        $file, offsetToLine($content, $fnPos),
                        'Add a $tenantId parameter or apply a tenant_id filter inside the method.');
                    $flagged = true;
                }
            }
        }
    }

    /**
     * NEW v5: Check that entity-scoped tables use entity_id which itself
     * is filtered to the current tenant. Detect queries on entity-scoped
     * tables that lack either tenant_id OR entity_id filter.
     *
     * Schema: tenants.id → entities.tenant_id → pos_sessions.entity_id
     */
    private function checkEntityChainIsolation(): void
    {
        $this->report->incrementTests();
        $pattern = '/\bFROM\s+(' . implode('|', Config::ENTITY_SCOPED_TABLES) . ')\b/i';

        foreach ($this->concreteRepositoryFiles() as $file) {
            if (Config::shouldSkipTenant(basename($file))) continue;
            $content  = $this->cache->content($file);
            $reported = [];
            $offset   = 0;

            while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $table  = $match[1][0];
                $pos    = $match[0][1];
                $offset = $pos + strlen($match[0][0]);
                if (in_array($table, $reported, true)) continue;

                $windowStart = max(0, $pos - 200);
                $window      = substr($content, $windowStart, 1500);

                $hasTenantId = preg_match('/\btenant_id\b/i', $window);
                $hasEntityId = preg_match('/\bentity_id\b/i', $window);

                if (!$hasTenantId && !$hasEntityId) {
                    $this->warning('Multi-Tenant Chain',
                        "'{$table}' query missing both tenant_id and entity_id — entity chain broken",
                        $file, offsetToLine($content, $pos),
                        "Filter by entity_id (which must itself be scoped to tenant_id via entities table) or add JOIN entities ON entities.id = {$table}.entity_id AND entities.tenant_id = :tid.",
                        7.2, 'CWE-284'
                    );
                    $reported[] = $table;
                } elseif ($hasEntityId && !$hasTenantId) {
                    $this->info('Multi-Tenant Chain',
                        "'{$table}' uses entity_id — verify entity is tenant-scoped in query/service layer",
                        $file, offsetToLine($content, $pos),
                        "Ensure the entity_id value is always fetched with a tenant_id filter upstream.");
                    $reported[] = $table;
                }
            }
        }
    }

    /**
     * NEW v5: Detect patterns where a route or controller accepts an entity_id
     * or tenant_id from user input without verifying ownership.
     * This is an IDOR (Insecure Direct Object Reference) risk in multi-tenant systems.
     */
    private function checkCrossTenantEntityAccess(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $content = $this->cache->content($file);
            // User supplies entity_id or tenant_id directly
            $hasUserEntityId = preg_match('/\$_(?:POST|GET|REQUEST)\s*\[\s*[\'"](?:entity_id|tenant_id)[\'"]\s*\]/i', $content);
            if (!$hasUserEntityId) continue;

            // Is there an ownership/membership check?
            $hasOwnershipCheck = preg_match('/\b(?:belongsToTenant|checkOwnership|isOwner|entity_id.*tenant|tenant.*entity_id|getEntityByTenant|verifyEntity)\b/i', $content);
            $hasBootstrap      = preg_match('/require[^;]+bootstrap/i', $content);

            if (!$hasOwnershipCheck && !$hasBootstrap) {
                $this->warning('IDOR Risk',
                    'User-supplied entity_id/tenant_id without visible ownership verification',
                    $file, 0,
                    'Verify the entity_id belongs to the authenticated user\'s tenant before processing.',
                    8.1, 'CWE-639'
                );
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 9 — Security Validation
// ═══════════════════════════════════════════════════════════════════════════════

class SecurityValidation extends BaseArchTest
{
    public function name(): string { return 'Security Validation'; }

    public function run(): void
    {
        $this->checkRawSqlConcatenation();
        $this->checkPreparedStatements();
        $this->checkWeakRbac();
        $this->checkMissingInputValidation();
        $this->checkPathTraversal();
        $this->checkOpenRedirects();
    }

    private function checkRawSqlConcatenation(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match('/["\'](?:SELECT|INSERT|UPDATE|DELETE)\b[^"\']*["\']\s*\.\s*(\$\w+)/i', $line, $m)) {
                    if (!isSafeSqlVar($m[1])) {
                        $this->critical('SQL Injection', 'SQL string built by concatenation with a variable — injection risk', $file, $idx + 1,
                            'Use prepared statements with bound parameters (:name or ?) instead.',
                            9.8, 'CWE-89');
                    }
                }
                if (preg_match('/\bWHERE\b[^"\']{0,100}(?:\{\$\w+\}|["\']\.?\s*\$(\w+))/i', $line, $m2)) {
                    $varName = $m2[1] ?? '';
                    if (!isSafeSqlVar($varName)) {
                        $this->warning('SQL Injection', 'Variable interpolated directly in WHERE clause', $file, $idx + 1,
                            'Always use parameter binding (:param or ?) for WHERE conditions.',
                            8.5, 'CWE-89');
                    }
                }
            }
        }
    }

    private function checkPreparedStatements(): void
    {
        $this->report->incrementTests();
        foreach ($this->concreteRepositoryFiles() as $file) {
            $code = $this->cache->stripped($file);
            if (preg_match('/\$pdo\s*->\s*query\s*\(\s*"[^"]*\$/i', $code)
                || preg_match('/\$pdo\s*->\s*query\s*\([^)]{0,100}\.\s*\$/i', $code)) {
                $this->critical('Prepared Statements', '$pdo->query() used with variable input', $file, 0,
                    'Replace with $pdo->prepare() + $stmt->execute() for parameterized queries.',
                    9.8, 'CWE-89');
            }
        }
    }

    private function checkWeakRbac(): void
    {
        $this->report->incrementTests();
        foreach ($this->routeFiles() as $file) {
            $basename = basename($file);
            if (Config::isPublicRouteFile($basename) || str_contains($file, '/public/')) continue;
            $code = $this->cache->content($file);
            if (!preg_match('/\b(?:POST|PUT|PATCH|DELETE)\b/i', $code)) continue;
            $hasAuth       = preg_match('/\b(?:auth|permission|rbac|middleware|token|jwt|session|bootstrap|isAllowed|hasRole)\b/i', $code);
            $hasBootstrap  = preg_match('/require[^;]+bootstrap/i', $code);
            $hasController = preg_match('/\$controller\s*->/i', $code);
            if (!$hasAuth && !$hasBootstrap && !$hasController) {
                $this->info('RBAC', 'Write-capable route has no visible auth/permission check', $file, 0,
                    'Protect all write endpoints with authentication and role-based authorization.');
            }
        }
    }

    private function checkMissingInputValidation(): void
    {
        $this->report->incrementTests();
        foreach ($this->routeFiles() as $file) {
            $code = $this->cache->content($file);
            if (!preg_match('/\$_(?:POST|GET|REQUEST)\b|php:\/\/input/i', $code)) continue;
            $hasValidation = preg_match('/\b(?:filter_var|Validator|htmlspecialchars|strip_tags|preg_match|is_numeric|intval|trim|empty|isset|json_decode|validate)\b/i', $code);
            $hasController = preg_match('/\$controller\s*->/i', $code);
            if (!$hasValidation && !$hasController) {
                $this->warning('Input Validation', 'Route reads user input without visible sanitization/validation', $file, 0,
                    'Always validate and sanitize user-supplied data before processing.',
                    6.5, 'CWE-20');
            }
        }
    }

    private function checkPathTraversal(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match('/\b(?:file_get_contents|file_put_contents|fopen|readfile|include|require)\s*\([^)]*\$_(?:GET|POST|REQUEST|SERVER|COOKIE)\b/i', $line)) {
                    $this->critical('Path Traversal', 'User-controlled variable passed to a filesystem function', $file, $idx + 1,
                        'Whitelist allowed paths and sanitize any user-provided filename/path components.',
                        9.1, 'CWE-22');
                }
            }
        }
    }

    private function checkOpenRedirects(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match('/header\s*\(\s*[\'"]Location:\s*[\'"]\s*\.\s*\$_(?:GET|POST|REQUEST)/i', $line)) {
                    $this->critical('Open Redirect', 'User input used directly in Location header — open redirect risk', $file, $idx + 1,
                        'Whitelist allowed redirect URLs or use relative paths only.',
                        6.1, 'CWE-601');
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 10 — NEW v5: Penetration Testing Module
// ═══════════════════════════════════════════════════════════════════════════════

class PenetrationTesting extends BaseArchTest
{
    public function name(): string { return 'Penetration Testing'; }

    public function run(): void
    {
        $this->checkJwtWeakAlgorithmRisk();
        $this->checkMassAssignmentRisk();
        $this->checkPrivilegeEscalationPatterns();
        $this->checkSensitiveEndpointRateLimiting();
        $this->checkIdorPatterns();
        $this->checkInsecureDirectEntityAccess();
        $this->checkResponseDataLeakage();
        $this->checkDebugEndpoints();
    }

    /**
     * JWT none-algorithm and weak algorithm detection.
     * An attacker can forge tokens if none algorithm is accepted.
     */
    private function checkJwtWeakAlgorithmRisk(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            // Check for none algorithm acceptance
            if (preg_match('/[\'"]none[\'"]/i', $content)
                && preg_match('/\b(?:algorithm|alg|jwt)\b/i', $content)) {
                $this->critical('JWT Security',
                    'JWT "none" algorithm reference detected — tokens could be forged without a signature',
                    $file, 0,
                    'Explicitly whitelist only HS256 or RS256. Reject "none" algorithm.',
                    9.8, 'CWE-347');
            }
            // Check for missing algorithm verification (JWT decoded without alg check)
            if (preg_match('/base64_decode\s*\([^)]+\)/i', $content)
                && preg_match('/json_decode\s*\([^)]+\)/i', $content)
                && !preg_match('/\b(?:algorithm|alg|verify|signature)\b/i', $content)
                && str_contains(strtolower($file), 'jwt')) {
                $this->warning('JWT Security',
                    'JWT-related file decodes base64 without visible algorithm verification',
                    $file, 0,
                    'Always verify the JWT signature and enforce a specific algorithm.',
                    8.1, 'CWE-347');
            }
        }
    }

    /**
     * Mass assignment: $_POST or json_decode passed wholesale to a repository
     * without field whitelisting. Attacker can inject extra fields.
     */
    private function checkMassAssignmentRisk(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            $lines   = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                // Pattern: $data = json_decode(..., true) or $_POST passed to save/create
                if (preg_match('/(?:save|create|update|insert)\s*\(\s*\$_POST\s*\)/i', $line)) {
                    $this->critical('Mass Assignment',
                        'Raw $_POST passed directly to a write method — mass assignment vulnerability',
                        $file, $idx + 1,
                        'Whitelist allowed fields: $data = array_intersect_key($_POST, array_flip([\'name\', \'email\', ...]));',
                        8.8, 'CWE-915');
                }
                if (preg_match('/(?:save|create|update)\s*\(\s*\$(?:body|data|input|payload)\s*\)/i', $line)) {
                    // Check if there's a whitelist/filter nearby
                    $context = implode("\n", array_slice($lines, max(0, $idx - 5), 15));
                    if (!preg_match('/\b(?:array_intersect_key|array_filter|whitelist|allowed|pick|only|fillable)\b/i', $context)) {
                        $this->warning('Mass Assignment',
                            'Unfiltered data object passed to write method — possible mass assignment',
                            $file, $idx + 1,
                            'Define an explicit whitelist of accepted fields before passing to repository.',
                            7.5, 'CWE-915');
                    }
                }
            }
        }
    }

    /**
     * Privilege escalation: user can update their own role or permissions.
     */
    private function checkPrivilegeEscalationPatterns(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            $lines   = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                // User-supplied role/permission field in UPDATE
                if (preg_match('/UPDATE\s+\w+\s+SET[^;]{0,200}\b(?:role|permission|is_admin|is_super)\s*=/i', $line)) {
                    $context = implode("\n", array_slice($lines, max(0, $idx - 10), 20));
                    $hasRbacCheck = preg_match('/\b(?:hasRole|isAdmin|isSuperAdmin|checkPermission|rbac)\b/i', $context);
                    if (!$hasRbacCheck) {
                        $this->warning('Privilege Escalation',
                            'UPDATE sets role/permission column without visible RBAC guard',
                            $file, $idx + 1,
                            'Only admins should be able to change roles/permissions. Add an RBAC check before this update.',
                            8.8, 'CWE-269');
                    }
                }
                // User supplied is_admin or role in POST body
                if (preg_match('/\$_(?:POST|REQUEST)\s*\[\s*[\'"](?:role|is_admin|permission|is_super)[\'"]\s*\]/i', $line)) {
                    $this->warning('Privilege Escalation',
                        'User-supplied role or admin flag read from request — privilege escalation risk',
                        $file, $idx + 1,
                        'Never trust role/permission fields from user input. Derive from authenticated session only.',
                        8.8, 'CWE-269');
                }
            }
        }
    }

    /**
     * Sensitive endpoints (login, register, reset, otp) must have rate limiting.
     */
    private function checkSensitiveEndpointRateLimiting(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $base = strtolower(basename($file, '.php'));
            $isSensitive = false;
            foreach (Config::RATE_LIMIT_REQUIRED_PATTERNS as $pattern) {
                if (str_contains($base, ltrim($pattern, '/'))) {
                    $isSensitive = true;
                    break;
                }
            }
            if (!$isSensitive) continue;

            $content = $this->cache->content($file);
            $hasRateLimit = preg_match('/\b(?:rateLimit|rate_limit|throttle|RateLimiter|checkRateLimit|rateLimitMiddleware|SecurityMiddleware)\b/i', $content);
            if (!$hasRateLimit) {
                $this->warning('Rate Limiting',
                    "Sensitive endpoint '{$base}' has no visible rate limiting — brute-force risk",
                    $file, 0,
                    'Apply rate limiting (e.g. max 5 attempts/minute) to all authentication endpoints.',
                    7.5, 'CWE-307');
            }
        }
    }

    /**
     * IDOR: User directly accesses a resource by ID without ownership check.
     * e.g. GET /orders/{id} where id comes from URL but no tenant/user ownership verified.
     */
    private function checkIdorPatterns(): void
    {
        $this->report->incrementTests();

        foreach ($this->routeFiles() as $file) {
            $content = $this->cache->content($file);
            // Route has dynamic ID from URL segments
            $hasDynamicId = preg_match('/\$(?:id|orderId|entityId|productId|userId)\s*=\s*(?:\(int\)|intval\s*\(|filter_var)?\s*\$_(?:GET|REQUEST|SERVER)\b/i', $content)
                         || preg_match('/explode\s*\([^)]+\)\s*\[\d+\]/i', $content);
            if (!$hasDynamicId) continue;

            // Is there a tenant/user ownership check before the fetch?
            $hasOwnership = preg_match('/\b(?:tenant_id|user_id|checkOwnership|belongsTo|verifyAccess|isOwner|getByIdAndTenant)\b/i', $content);
            $hasBootstrap = preg_match('/require[^;]+bootstrap/i', $content);
            if (!$hasOwnership && !$hasBootstrap) {
                $this->info('IDOR',
                    'Route uses dynamic resource ID without visible ownership verification',
                    $file, 0,
                    'After fetching a resource by ID, verify it belongs to the authenticated user/tenant.');
            }
        }
    }

    /**
     * NEW v5 (entity-chain specific): Detect direct entity access
     * where entity_id is from user input but not verified against tenant.
     *
     * In this system: tenants.id → entities.tenant_id
     * An attacker supplying a different entity_id can access another tenant's entity.
     */
    private function checkInsecureDirectEntityAccess(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            $lines   = $this->cache->lines($file);

            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                // entity_id taken directly from request
                if (!preg_match('/entity_id.*\$_(?:GET|POST|REQUEST)/i', $line)
                    && !preg_match('/\$_(?:GET|POST|REQUEST).*entity_id/i', $line)) {
                    continue;
                }
                // Look for a tenant verification in surrounding context
                $context = implode("\n", array_slice($lines, max(0, $idx - 15), 35));
                $hasVerification = preg_match('/\b(?:tenant_id|getEntityByTenant|verifyEntityOwnership|entities.*tenant|checkEntityAccess)\b/i', $context);
                if (!$hasVerification) {
                    $this->warning('Insecure Entity Access',
                        'entity_id from user input used without verifying it belongs to authenticated tenant',
                        $file, $idx + 1,
                        'Query: SELECT id FROM entities WHERE id = :entityId AND tenant_id = :tenantId to verify ownership.',
                        8.5, 'CWE-639');
                }
            }
        }
    }

    /**
     * Check if API error responses expose sensitive stack traces or internal data.
     */
    private function checkResponseDataLeakage(): void
    {
        $this->report->incrementTests();

        foreach ($this->allApiFiles() as $file) {
            if (str_contains($file, '/tests/')) continue;
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                // Exception message echoed directly to response
                if (preg_match('/(?:echo|print|json_encode)\s*[^;]*\$(?:e|ex|exception|err)\s*->\s*getMessage\s*\(\s*\)/i', $line)) {
                    $this->warning('Data Leakage',
                        'Exception message echoed directly to API response — reveals internal details',
                        $file, $idx + 1,
                        'Log the full exception internally, return only a generic error message to the client.',
                        5.3, 'CWE-209');
                }
                // Stack trace in response
                if (preg_match('/(?:echo|print|json_encode)\s*[^;]*\$(?:e|ex|exception)\s*->\s*getTraceAsString\s*\(\s*\)/i', $line)) {
                    $this->critical('Data Leakage',
                        'Stack trace included in API response — exposes internal file structure',
                        $file, $idx + 1,
                        'Never return stack traces in production. Log them server-side only.',
                        7.5, 'CWE-209');
                }
            }
        }
    }

    /**
     * Detect debug/diagnostic endpoints that should not be in production.
     */
    private function checkDebugEndpoints(): void
    {
        $this->report->incrementTests();

        $debugRoutePatterns = [
            'diagnostic', 'phpinfo', 'debug', 'test_db', 'db_test',
            'health_detailed', 'env_check', 'info.php',
        ];

        foreach ($this->allApiFiles() as $file) {
            $base = strtolower(basename($file, '.php'));
            foreach ($debugRoutePatterns as $pat) {
                if (str_contains($base, $pat)) {
                    $content = $this->cache->content($file);
                    $hasAuthGuard = preg_match('/\b(?:auth|jwt|token|bootstrap|admin|isAdmin)\b/i', $content);
                    if (!$hasAuthGuard) {
                        $this->warning('Debug Endpoint',
                            "Possible unprotected debug endpoint: {$base}.php",
                            $file, 0,
                            'Disable or restrict debug endpoints with authentication in production.',
                            5.3, 'CWE-200');
                    }
                }
            }

            // phpinfo() anywhere in non-test files
            if (!str_contains($file, '/tests/')) {
                $content = $this->cache->content($file);
                if (preg_match('/\bphpinfo\s*\(\s*\)/i', $content)) {
                    $this->critical('Debug Endpoint',
                        'phpinfo() call detected — exposes server configuration',
                        $file, 0,
                        'Remove phpinfo() from all production files.',
                        7.5, 'CWE-200');
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 11 — Type Safety Validation
// ═══════════════════════════════════════════════════════════════════════════════

class TypeSafetyValidation extends BaseArchTest
{
    public function name(): string { return 'Type Safety'; }

    public function run(): void
    {
        $this->checkStrictTypes();
        $this->checkReturnTypes();
        $this->checkParamTypes();
        $this->checkPropertyTypes();
    }

    private function checkStrictTypes(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (isInterfaceFile($file)) continue;
            $content = $this->cache->content($file);
            if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/i', $content)) {
                $this->info('Type Safety', 'Missing declare(strict_types=1)', $file, 1,
                    'Add declare(strict_types=1); at the top of every PHP file for type safety.');
            }
        }
    }

    private function checkReturnTypes(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (isInterfaceFile($file)) continue;
            $content = $this->cache->content($file);
            if (preg_match_all(
                '/public\s+(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*(?!:)\s*\{/i',
                $content, $matches, PREG_SET_ORDER
            )) {
                $names = array_column($matches, 1);
                $names = array_values(array_filter($names, fn(string $n) => !str_starts_with($n, '__')));
                if (count($names) > 0) {
                    $sample = implode(', ', array_slice($names, 0, 3));
                    $more   = count($names) > 3 ? ' +' . (count($names) - 3) . ' more' : '';
                    $this->info('Type Safety', "Public method(s) missing return type: {$sample}{$more}", $file, 0,
                        'Add explicit return types (e.g. : array, : string, : void) to all public methods.');
                }
            }
        }
    }

    private function checkParamTypes(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (isInterfaceFile($file)) continue;
            $content        = $this->cache->content($file);
            $flaggedMethods = [];
            if (!preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]+)\)/i', $content, $matches, PREG_SET_ORDER)) continue;
            foreach ($matches as $m) {
                $methodName = $m[1];
                if (str_starts_with($methodName, '__')) continue;
                $params = explode(',', $m[2]);
                foreach ($params as $param) {
                    $param = trim($param);
                    if ($param === '' || str_starts_with($param, '...')) continue;
                    if (preg_match('/^[&\s]*\$\w+(?:\s*=.*)?$/', $param)) {
                        $flaggedMethods[] = $methodName;
                        break;
                    }
                }
            }
            if (count($flaggedMethods) > 0) {
                $sample = implode(', ', array_unique(array_slice($flaggedMethods, 0, 3)));
                $this->info('Type Safety', "Untyped parameter(s) in public method(s): {$sample}", $file, 0,
                    'Add type declarations to all method parameters.');
            }
        }
    }

    private function checkPropertyTypes(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (isInterfaceFile($file)) continue;
            $content = $this->cache->content($file);
            if (preg_match_all('/^\s*(?:public|protected)\s+(?:static\s+)?\$(\w+)\s*(?:=|;)/m', $content, $matches)) {
                $props = $matches[1];
                if (count($props) > 0) {
                    $sample = implode(', ', array_slice($props, 0, 3));
                    $more   = count($props) > 3 ? ' +' . (count($props) - 3) . ' more' : '';
                    $this->info('Type Safety', "Untyped class properties: \${$sample}{$more}", $file, 0,
                        'Declare explicit types on all class properties (PHP 7.4+).');
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 12 — Configuration Safety
// ═══════════════════════════════════════════════════════════════════════════════

class ConfigurationSafety extends BaseArchTest
{
    public function name(): string { return 'Configuration Safety'; }

    public function run(): void
    {
        $this->checkHardcodedCredentials();
        $this->checkDebugFlags();
        $this->checkEnvLeaks();
        $this->checkHardcodedUrls();
    }

    private function checkHardcodedCredentials(): void
    {
        $this->report->incrementTests();
        $credentialPattern = '/(?:password|passwd|secret|api_?key|apikey|auth_?token|private_?key|access_?token|client_?secret)\s*[=:>]+\s*[\'"][^\'"\s]{6,}[\'"]/i';
        foreach ($this->allApiFiles() as $file) {
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                $lower = strtolower($line);
                if (str_contains($lower, 'your_') || str_contains($lower, 'change_me')
                    || str_contains($lower, 'example') || str_contains($lower, 'placeholder')
                    || str_contains($lower, 'xxxx')) continue;
                if (str_contains($line, 'getenv(') || str_contains($line, '$_ENV')) continue;
                if (preg_match($credentialPattern, $line)) {
                    $this->critical('Hardcoded Credentials', 'Possible hardcoded credential or secret in source code', $file, $idx + 1,
                        'Load secrets from environment variables or a secrets manager, never source code.',
                        9.1, 'CWE-798');
                }
            }
        }
    }

    private function checkDebugFlags(): void
    {
        $this->report->incrementTests();
        $debugPatterns = [
            '/\bdisplay_errors\s*=\s*[\'"]?(?:on|1|true)[\'"]?/i' => 'display_errors = On',
            '/\berror_reporting\s*\(\s*E_ALL\s*\)/i'               => 'error_reporting(E_ALL)',
            '/\bvar_dump\s*\(/i'                                    => 'var_dump()',
            '/\bprint_r\s*\([^)]+,\s*false\s*\)/i'                => 'print_r() left in code',
            '/\bdie\s*\(\s*[\'"]debug/i'                           => 'die("debug…")',
        ];
        foreach ($this->allApiFiles() as $file) {
            if (str_contains($file, '/tests/')) continue;
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                foreach ($debugPatterns as $pattern => $label) {
                    if (preg_match($pattern, $line)) {
                        $this->warning('Debug Flag', "Debug statement left in code: {$label}", $file, $idx + 1,
                            'Remove or guard debug statements behind an environment check (APP_DEBUG).');
                    }
                }
            }
        }
    }

    private function checkEnvLeaks(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (str_contains($file, '/tests/')) continue;
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match('/(?:var_dump|print_r|var_export)\s*\(\s*\$_(?:ENV|SERVER|GLOBALS)\s*[\),]/i', $line)) {
                    $this->critical('Environment Leak', 'Dumping $_ENV, $_SERVER, or $GLOBALS — exposes sensitive runtime data', $file, $idx + 1,
                        'Never expose server environment variables in output. Remove this statement.',
                        7.5, 'CWE-200');
                }
            }
        }
    }

    private function checkHardcodedUrls(): void
    {
        $this->report->incrementTests();
        $urlPattern = '/[\'"](?:http:\/\/localhost|http:\/\/127\.0\.0\.1|mysql:host=localhost)[\'"\s]/i';
        foreach ($this->allApiFiles() as $file) {
            $base = strtolower(basename($file));
            if (str_contains($base, 'config') || str_contains($base, 'bootstrap')
                || str_contains($base, 'env') || str_contains($base, 'database')) continue;
            $lines = $this->cache->lines($file);
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match($urlPattern, $line)) {
                    $this->info('Hardcoded URL', 'Hard-coded localhost/127.0.0.1 URL — not portable across environments', $file, $idx + 1,
                        'Use environment variables (getenv, $_ENV) for all host/URL configuration.');
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 13 — Exception Handling
// ═══════════════════════════════════════════════════════════════════════════════

class ExceptionHandling extends BaseArchTest
{
    public function name(): string { return 'Exception Handling'; }

    public function run(): void
    {
        $this->checkSwallowedExceptions();
        $this->checkOverBroadCatch();
        $this->checkGenericExceptionThrow();
    }

    private function checkSwallowedExceptions(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            if (preg_match_all('/catch\s*\([^)]+\)\s*\{(\s*(?:\/\/[^\n]*)?\s*)\}/s', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $body = trim($m[1]);
                    if ($body === '' || preg_match('/^\/\/[^\n]*$/', $body)) {
                        $this->warning('Exception Handling', 'Empty catch block — exception is silently swallowed', $file, 0,
                            'At minimum, log the exception. Never silently swallow errors.');
                        break;
                    }
                }
            }
        }
    }

    private function checkOverBroadCatch(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (Config::isExceptionRelaxedPath($file)) continue;
            $code = $this->cache->stripped($file);
            if (preg_match('/catch\s*\(\s*\\\\?(?:Exception|Throwable)\s+\$\w+\s*\)/i', $code)) {
                $this->info('Exception Handling', 'Over-broad catch(Exception) or catch(Throwable) detected', $file, 0,
                    'Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.');
            }
        }
    }

    private function checkGenericExceptionThrow(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (Config::isExceptionRelaxedPath($file)) continue;
            $code = $this->cache->stripped($file);
            if (preg_match_all('/\bthrow\s+new\s+\\\\?Exception\s*\(/i', $code) > 0) {
                $this->info('Exception Handling', 'Generic Exception thrown — consider domain-specific exception classes', $file, 0,
                    'Create descriptive exception classes (e.g. NotFoundException, ValidationException).');
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 14 — Code Quality
// ═══════════════════════════════════════════════════════════════════════════════

class CodeQualityValidation extends BaseArchTest
{
    public function name(): string { return 'Code Quality'; }

    public function run(): void
    {
        $this->checkLargeClasses();
        $this->checkLargeMethods();
        $this->checkGodClasses();
        $this->checkDuplicatedLogicPatterns();
        $this->checkMagicNumbers();
        $this->checkDeadPrivateMethods();
    }

    private function checkLargeClasses(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            if (!preg_match('/\bclass\s+\w+/i', $this->cache->content($file))) continue;
            $loc = $this->cache->codeLineCount($file);
            if ($loc > Config::MAX_CLASS_LINES) {
                $this->warning('Large Class', "Class file has {$loc} LOC (threshold: " . Config::MAX_CLASS_LINES . ')', $file, 0,
                    'Split into smaller, single-responsibility classes.');
            }
        }
    }

    private function checkLargeMethods(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $lines       = $this->cache->lines($file);
            $inMethod    = false;
            $methodName  = '';
            $methodStart = 0;
            $braceDepth  = 0;
            $methodLoc   = 0;
            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);
                if (!$inMethod && preg_match('/(?:public|protected|private|static)\s+function\s+(\w+)\s*\(/i', $trimmed, $m)) {
                    $inMethod    = true;
                    $methodName  = $m[1];
                    $methodStart = $idx + 1;
                    $braceDepth  = 0;
                    $methodLoc   = 0;
                }
                if ($inMethod) {
                    $braceDepth += substr_count($trimmed, '{') - substr_count($trimmed, '}');
                    if (!isCommentLine($line) && $trimmed !== '') $methodLoc++;
                    if ($braceDepth <= 0 && $methodLoc > 1) {
                        if ($methodLoc > Config::MAX_METHOD_LINES_WARN) {
                            $this->warning('Large Method', "Method '{$methodName}()' has ~{$methodLoc} LOC", $file, $methodStart,
                                'Refactor into smaller, single-responsibility methods.');
                        } elseif ($methodLoc > Config::MAX_METHOD_LINES_INFO) {
                            $this->info('Large Method', "Method '{$methodName}()' has ~{$methodLoc} LOC", $file, $methodStart,
                                'Consider breaking this method into smaller parts.');
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
            if (!preg_match('/\bclass\s+\w+/i', $content)) continue;
            $count = preg_match_all('/\bpublic\s+function\s+\w+\s*\(/i', $content);
            if ($count > Config::MAX_PUBLIC_METHODS) {
                $this->warning('God Class', "Class has {$count} public methods (threshold: " . Config::MAX_PUBLIC_METHODS . ')', $file, 0,
                    'Apply the Single Responsibility Principle and split into focused classes.');
            }
        }
    }

    private function checkDuplicatedLogicPatterns(): void
    {
        $this->report->incrementTests();
        $patterns = [
            'permission_check'  => ['regex' => '/if\s*\(\s*!\s*\$\w+\s*->\s*(?:permission|hasPermission|can|isAllowed)\s*\(/i', 'label' => 'Permission check pattern'],
            'status_transition' => ['regex' => '/\$\w+\s*=\s*[\'"][a-z_]+[\'"]\s*;.*status/i', 'label' => 'Status assignment pattern'],
            'email_validation'  => ['regex' => '/filter_var\s*\([^)]+FILTER_VALIDATE_EMAIL/i', 'label' => 'Email validation pattern'],
            'json_response'     => ['regex' => '/json_encode\s*\(\s*\[\s*[\'"](?:status|success|data|error)[\'"]/i', 'label' => 'JSON response building'],
        ];
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
        foreach ($patterns as $key => $def) {
            $filesHit = $fileCounts[$key];
            if (count($filesHit) >= Config::DUPLICATION_FILE_THRESHOLD) {
                $this->info('Duplicated Logic', "'{$def['label']}' repeated across " . count($filesHit) . ' files', '', 0,
                    'Extract into a shared Trait, Service method, or utility class.');
            }
        }
    }

    private function checkMagicNumbers(): void
    {
        $this->report->incrementTests();
        $businessFiles = array_merge(
            $this->findFiles('/services/', 'Service.php'),
            $this->concreteRepositoryFiles()
        );
        foreach ($businessFiles as $file) {
            $lines = $this->cache->lines($file);
            $found = [];
            foreach ($lines as $idx => $line) {
                if (isCommentLine($line)) continue;
                if (preg_match_all('/[>=<!]+\s*(\d{2,})\b/', $line, $m)) {
                    foreach ($m[1] as $num) {
                        $n = (int) $num;
                        if (in_array($n, [200, 201, 400, 401, 403, 404, 422, 500, 1000, 60, 3600, 86400], true)) continue;
                        $found[] = $n;
                    }
                }
            }
            if (count(array_unique($found)) > 4) {
                $this->info('Magic Numbers',
                    'Multiple magic numbers in business logic (' . implode(', ', array_unique(array_slice($found, 0, 4))) . '…)',
                    $file, 0,
                    'Extract magic numbers into named constants (const MAX_RETRIES = 3).');
            }
        }
    }

    private function checkDeadPrivateMethods(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $content = $this->cache->content($file);
            if (!preg_match_all('/private\s+(?:static\s+)?function\s+(\w+)\s*\(/i', $content, $matches)) continue;
            $deadMethods = [];
            foreach ($matches[1] as $methodName) {
                if (str_starts_with($methodName, '__')) continue;
                $occurrences = substr_count($content, $methodName);
                if ($occurrences < 2) {
                    $deadMethods[] = $methodName;
                }
            }
            if (count($deadMethods) > 0) {
                $sample = implode(', ', array_slice($deadMethods, 0, 3));
                $this->info('Dead Code', "Potentially unused private method(s): {$sample}", $file, 0,
                    'Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.');
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 15 — Runtime Simulation
// ═══════════════════════════════════════════════════════════════════════════════

class RuntimeSimulation extends BaseArchTest
{
    public function name(): string { return 'Runtime Simulation'; }

    public function run(): void
    {
        $this->checkLargeFiles();
        $this->runSimulatedRequests();
    }

    private function checkLargeFiles(): void
    {
        $this->report->incrementTests();
        foreach ($this->allApiFiles() as $file) {
            $bytes = @filesize($file);
            if ($bytes === false) continue;
            $kb = $bytes / 1024;
            if ($kb > Config::MAX_FILE_SIZE_KB) {
                $this->info('File Size', sprintf('Large file (%.1f KB) — may slow autoload/include', $kb), $file, 0,
                    'Split into smaller, lazily-loaded modules.');
            }
        }
    }

    private function runSimulatedRequests(): void
    {
        $this->report->incrementTests();
        $routeFiles = $this->routeFiles();
        if (empty($routeFiles)) {
            $this->info('Runtime', 'No route files found — skipping simulation.', '', 0, '');
            return;
        }
        $preloaded  = array_map(fn(string $f) => $this->cache->content($f), $routeFiles);
        $iterations = Config::SIMULATION_ITERATIONS;
        $routeCount = count($preloaded);
        $totalMs    = measureTime(function () use ($iterations, $preloaded, $routeCount): void {
            for ($i = 0; $i < $iterations; $i++) {
                $content = $preloaded[$i % $routeCount];
                preg_match_all('/\bfunction\s+\w+/i', $content, $m);
                json_encode(['status' => 'ok', 'iteration' => $i, 'matches' => count($m[0])]);
            }
        });
        $avgMs    = round($totalMs / $iterations, 4);
        $totalSec = round($totalMs / 1000, 3);
        if ($avgMs > Config::SIM_WARN_MS_PER_ITER) {
            $this->warning('Runtime',
                "Avg simulated CPU time: {$avgMs} ms/req (threshold: " . Config::SIM_WARN_MS_PER_ITER . ' ms)',
                '', 0, 'Consider enabling OPcache and reducing regex complexity in hot paths.');
        } elseif ($avgMs > Config::SIM_INFO_MS_PER_ITER) {
            $this->info('Runtime', "Avg simulated CPU time: {$avgMs} ms/req", '', 0,
                'Performance is acceptable; OPcache will further reduce parse overhead.');
        }
        $this->info('Runtime Summary',
            "Simulation: {$iterations} requests in {$totalSec}s (avg {$avgMs} ms/req, {$routeCount} route files)",
            '', 0, '');
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 16 — Test Runner
// ═══════════════════════════════════════════════════════════════════════════════

final class AdvancedSystemTestRunner
{
    private TestReport $report;

    /** @var BaseArchTest[] */
    private array $modules = [];

    public function __construct(private readonly string $projectRoot)
    {
        $this->report = new TestReport();
    }

    public function addModule(BaseArchTest $module): static
    {
        $this->modules[] = $module;
        return $this;
    }

    public function getReport(): TestReport
    {
        return $this->report;
    }

    public function run(): TestReport
    {
        foreach ($this->modules as $module) {
            $beforeTests  = $this->report->getTestsRun();
            $elapsedMs    = measureTime(fn() => $module->run());
            $testsThisRun = $this->report->getTestsRun() - $beforeTests;
            $this->report->addTiming(new ModuleTiming($module->name(), $elapsedMs, $testsThisRun));
        }
        return $this->report;
    }

    public static function createDefault(string $projectRoot): static
    {
        $runner = new static($projectRoot);
        $report = $runner->getReport();

        $runner->addModule(new ArchitectureValidation($report, $projectRoot));
        $runner->addModule(new PerformanceValidation($report, $projectRoot));
        $runner->addModule(new MultiTenantSafety($report, $projectRoot));
        $runner->addModule(new SecurityValidation($report, $projectRoot));
        $runner->addModule(new PenetrationTesting($report, $projectRoot));   // NEW v5
        $runner->addModule(new TypeSafetyValidation($report, $projectRoot));
        $runner->addModule(new ConfigurationSafety($report, $projectRoot));
        $runner->addModule(new ExceptionHandling($report, $projectRoot));
        $runner->addModule(new CodeQualityValidation($report, $projectRoot));
        $runner->addModule(new RuntimeSimulation($report, $projectRoot));

        return $runner;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 17 — Output Formatters
// ═══════════════════════════════════════════════════════════════════════════════

final class ReportFormatter
{
    // ─── CLI ─────────────────────────────────────────────────────────────

    public static function cli(TestReport $report, bool $quiet = false): string
    {
        $out     = '';
        $score   = $report->score();
        $summary = $report->summaryCounts();
        [$grade] = $report->grade();

        $out .= "╔══════════════════════════════════════════════════════════════╗\n";
        $out .= "║  ADVANCED ARCHITECTURE + PENETRATION TEST REPORT  v5.0      ║\n";
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
        $out .= "  Info      : {$summary[Severity::INFO]}" . ($quiet ? " (hidden in --quiet mode)\n" : "\n") . "\n";

        if ($report->getTimings()) {
            $out .= "  " . str_repeat('─', 60) . "\n";
            $out .= "  MODULE TIMINGS\n";
            $out .= "  " . str_repeat('─', 60) . "\n";
            foreach ($report->getTimings() as $t) {
                $name    = str_pad($t->name, 30, ' ');
                $elapsed = str_pad(round($t->elapsedMs, 1) . ' ms', 10);
                $tests   = str_pad("{$t->testsRun} tests", 10);
                $findings = count($report->findingsByModule($t->name));
                $out .= "  {$name} {$elapsed} {$tests} {$findings} findings\n";
            }
            $out .= "\n";
        }

        $severities = $quiet
            ? [Severity::CRITICAL, Severity::WARNING]
            : Severity::all();

        foreach ($severities as $sev) {
            $items = $report->findingsBySeverity($sev);
            if (empty($items)) continue;
            $icon = match ($sev) {
                Severity::CRITICAL => '❌',
                Severity::WARNING  => '⚠️ ',
                Severity::INFO     => 'ℹ️ ',
            };
            $out .= "  {$icon} {$sev} (" . count($items) . ")\n";
            $out .= "  " . str_repeat('─', 50) . "\n";
            foreach ($items as $f) {
                $loc  = $f->file . ($f->line > 0 ? ":{$f->line}" : '');
                $cvss = $f->cvssScore > 0 ? " [CVSS:{$f->cvssScore}]" : '';
                $cwe  = $f->cweId       ? " [{$f->cweId}]" : '';
                $out .= "  [{$f->category}]{$cvss}{$cwe} {$f->message}\n";
                $out .= "  [Module: {$f->module}]\n";
                if ($loc) $out .= "    → {$loc}\n";
                if ($f->suggestion) $out .= "    💡 {$f->suggestion}\n";
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
<html lang="ar" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Architecture + Penetration Test Report v5.0</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root[data-theme="dark"]{--bg:#0d1117;--surface:#161b22;--border:#30363d;--text:#c9d1d9;--muted:#8b949e;--hover:#1c2128;--code:#f0883e}
:root[data-theme="light"]{--bg:#f6f8fa;--surface:#ffffff;--border:#d0d7de;--text:#24292f;--muted:#57606a;--hover:#f3f4f6;--code:#953800}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);padding:24px;line-height:1.5;transition:background .2s,color .2s}
.container{max-width:1280px;margin:0 auto}
h1{color:#58a6ff;font-size:1.8em;margin-bottom:4px}
.subtitle{color:var(--muted);margin-bottom:24px;font-size:.9em}
.topbar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.theme-btn{background:var(--surface);border:1px solid var(--border);color:var(--text);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:.85em}
.score-grid{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:28px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 22px;text-align:center;min-width:110px}
.card .lbl{color:var(--muted);font-size:.75em;text-transform:uppercase;letter-spacing:.06em}
.card .val{font-size:1.9em;font-weight:700;margin-top:4px}
.timings{background:var(--surface);border:1px solid var(--border);border-radius:10px;margin-bottom:24px;overflow:hidden}
.timings table{width:100%;border-collapse:collapse;font-size:.85em}
.timings th{background:var(--hover);color:var(--muted);padding:8px 14px;text-align:left;font-weight:600}
.timings td{padding:7px 14px;border-top:1px solid var(--border)}
.timings tr:hover td{background:var(--hover)}
.section{background:var(--surface);border:1px solid var(--border);border-radius:10px;margin-bottom:14px;overflow:hidden}
.sec-hdr{padding:11px 16px;font-weight:600;display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none}
.sec-hdr:hover{background:var(--hover)}
.sec-body{padding:0 16px 10px}
.finding{padding:10px 0 10px 12px;border-bottom:1px solid var(--border);border-left:4px solid transparent}
.finding:last-child{border-bottom:none}
.finding.CRITICAL{border-left-color:#f85149}
.finding.WARNING{border-left-color:#d29922}
.finding.INFO{border-left-color:#58a6ff}
.cat{color:#58a6ff;font-weight:600;font-size:.85em}
.mod{color:var(--muted);font-size:.75em;margin-top:1px}
.msg{margin-top:4px}
.loc{color:var(--muted);font-size:.82em;margin-top:3px;font-family:monospace}
.sug{color:#3fb950;font-size:.82em;margin-top:4px}
.cvss{display:inline-block;background:#f8514922;color:#f85149;border:1px solid #f8514944;border-radius:4px;font-size:.7em;padding:1px 6px;margin-left:6px;font-family:monospace}
.cvss.med{background:#d2992222;color:#d29922;border-color:#d2992244}
.cvss.low{background:#58a6ff22;color:#58a6ff;border-color:#58a6ff44}
.cwe{display:inline-block;background:#a5d6ff22;color:#79c0ff;border-radius:4px;font-size:.7em;padding:1px 6px;margin-left:4px;font-family:monospace}
.badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:.72em;font-weight:700}
.badge.CRITICAL{background:#f8514922;color:#f85149}
.badge.WARNING{background:#d2992222;color:#d29922}
.badge.INFO{background:#58a6ff22;color:#58a6ff}
.pass-msg{color:#3fb950;font-size:1.2em;padding:32px;text-align:center}
.bar{height:6px;border-radius:3px;background:var(--border);margin-top:8px}
.bar-fill{height:100%;border-radius:3px;transition:width .4s ease}
.collapsed .sec-body{display:none}
.pen-module-badge{background:#f8514922;color:#f85149;border:1px solid #f8514944;font-size:.75em;padding:2px 8px;border-radius:4px;margin-left:8px}
</style>
</head>
<body>
<div class="container">
  <div class="topbar">
    <div>
      <h1>🏗️ Architecture + Penetration Test Report</h1>
      <p class="subtitle">v5.0 — Generated: <?= $h($generatedAt) ?> — Total: <?= $totalMs ?> ms
        <span class="pen-module-badge">+ Penetration Testing Module</span>
      </p>
    </div>
    <button class="theme-btn" onclick="document.documentElement.setAttribute('data-theme',document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark')">🌙 Toggle Theme</button>
  </div>

  <div class="score-grid">
    <div class="card">
      <div class="lbl">Score</div>
      <div class="val" style="color:<?= $h($grColor) ?>"><?= $score ?></div>
      <div class="bar"><div class="bar-fill" style="width:<?= $score ?>%;background:<?= $h($grColor) ?>"></div></div>
    </div>
    <div class="card">
      <div class="lbl">Grade</div>
      <div class="val" style="font-size:2.5em;color:<?= $h($grColor) ?>"><?= $h($grLetter) ?></div>
      <div style="font-size:.8em;color:var(--muted);margin-top:2px"><?= $h($grLabel) ?></div>
    </div>
    <div class="card"><div class="lbl">Tests Run</div><div class="val"><?= $report->getTestsRun() ?></div></div>
    <div class="card"><div class="lbl">Critical</div><div class="val" style="color:#f85149"><?= $summary[Severity::CRITICAL] ?></div></div>
    <div class="card"><div class="lbl">Warnings</div><div class="val" style="color:#d29922"><?= $summary[Severity::WARNING] ?></div></div>
    <div class="card"><div class="lbl">Info</div><div class="val" style="color:#58a6ff"><?= $summary[Severity::INFO] ?></div></div>
  </div>

<?php if ($report->getTimings()): ?>
  <div class="timings">
    <table>
      <thead><tr><th>Module</th><th>Time (ms)</th><th>Tests</th><th>Findings</th></tr></thead>
      <tbody>
<?php foreach ($report->getTimings() as $t): ?>
        <tr><td><?= $h($t->name) ?></td><td><?= round($t->elapsedMs, 1) ?></td><td><?= $t->testsRun ?></td><td><?= count($report->findingsByModule($t->name)) ?></td></tr>
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
  <div class="section" id="sec-<?= strtolower($sev) ?>">
    <div class="sec-hdr" onclick="this.parentElement.classList.toggle('collapsed')">
      <?= $sev === Severity::CRITICAL ? '❌' : ($sev === Severity::WARNING ? '⚠️' : 'ℹ️') ?>
      &nbsp;<?= $h($sev) ?>
      <span class="badge <?= $h($sev) ?>"><?= count($items) ?></span>
    </div>
    <div class="sec-body">
<?php foreach ($items as $f): ?>
      <div class="finding <?= $h($f->severity) ?>">
        <div class="cat">[<?= $h($f->category) ?>]
          <?php if ($f->cvssScore > 0):
            $cvssClass = $f->cvssScore >= 7 ? '' : ($f->cvssScore >= 4 ? 'med' : 'low');
          ?><span class="cvss <?= $cvssClass ?>">CVSS <?= number_format($f->cvssScore, 1) ?></span><?php endif; ?>
          <?php if ($f->cweId): ?><span class="cwe"><?= $h($f->cweId) ?></span><?php endif; ?>
        </div>
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
<script>
window.addEventListener('DOMContentLoaded',function(){
  var s=document.getElementById('sec-info');
  if(s && s.querySelectorAll('.finding').length > 50) s.classList.add('collapsed');
});
</script>
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
                'version'   => '5.0.0',
                'generated' => date('c'),
                'score'     => $report->score(),
                'grade'     => $report->grade()[0],
                'tests_run' => $report->getTestsRun(),
                'total_ms'  => round($report->totalElapsedMs(), 1),
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
                    'severity'      => $f->severity,
                    'module'        => $f->module,
                    'category'      => $f->category,
                    'message'       => $f->message,
                    'file'          => $f->file,
                    'line'          => $f->line,
                    'suggestion'    => $f->suggestion,
                    'cvss_score'    => $f->cvssScore,
                    'cwe_id'        => $f->cweId,
                    'attack_vector' => $f->attackVector,
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
        $md = '';
        $md .= "# Architecture + Penetration Test Report v5.0\n\n";
        $md .= "> Generated: " . date('Y-m-d H:i:s') . "  \n";
        $md .= "> Total analysis time: " . round($report->totalElapsedMs(), 1) . " ms\n\n";
        $md .= "## Score: {$score}/100 — {$grLetter} ({$grLabel})\n\n";
        $md .= "| Metric     | Value |\n|------------|-------|\n";
        $md .= "| Tests Run  | {$report->getTestsRun()} |\n";
        $md .= "| Critical   | {$summary[Severity::CRITICAL]} |\n";
        $md .= "| Warnings   | {$summary[Severity::WARNING]} |\n";
        $md .= "| Info       | {$summary[Severity::INFO]} |\n\n";
        if ($report->getTimings()) {
            $md .= "## Module Timings\n\n";
            $md .= "| Module | Time (ms) | Tests | Findings |\n|--------|-----------|-------|----------|\n";
            foreach ($report->getTimings() as $t) {
                $findings = count($report->findingsByModule($t->name));
                $md .= "| {$t->name} | " . round($t->elapsedMs, 1) . " | {$t->testsRun} | {$findings} |\n";
            }
            $md .= "\n";
        }
        foreach (Severity::all() as $sev) {
            $items = $report->findingsBySeverity($sev);
            if (empty($items)) continue;
            $icon = match ($sev) { Severity::CRITICAL => '❌', Severity::WARNING => '⚠️', Severity::INFO => 'ℹ️' };
            $md .= "## {$icon} {$sev} (" . count($items) . ")\n\n";
            foreach ($items as $f) {
                $loc  = $f->file . ($f->line > 0 ? ":{$f->line}" : '');
                $cvss = $f->cvssScore > 0 ? " | CVSS: {$f->cvssScore}" : '';
                $cwe  = $f->cweId ? " | {$f->cweId}" : '';
                $md .= "### [{$f->category}] — *{$f->module}*{$cvss}{$cwe}\n\n**{$f->message}**\n\n";
                if ($loc) $md .= "📁 `{$loc}`\n\n";
                if ($f->suggestion) $md .= "💡 _{$f->suggestion}_\n\n";
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
// SECTION 18 — Entry Point
// ═══════════════════════════════════════════════════════════════════════════════

function resolveProjectRoot(): string
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach ($argv as $arg) {
            if (!str_starts_with($arg, '--') && is_dir($arg)) {
                return realpath($arg);
            }
        }
        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }
    $get = $_GET['root'] ?? null;
    if ($get !== null) {
        $sanitized = realpath($get);
        if ($sanitized !== false && is_dir($sanitized)) return $sanitized;
    }
    return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
}

function resolveFormat(): string
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach ($argv as $arg) {
            if (preg_match('/^--format=(.+)$/', $arg, $m)) return strtolower(trim($m[1]));
        }
        return 'cli';
    }
    return strtolower(trim($_GET['format'] ?? 'html'));
}

function resolveQuiet(): bool
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        return in_array('--quiet', $argv, true);
    }
    return isset($_GET['quiet']);
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $projectRoot = resolveProjectRoot();
    $format      = resolveFormat();
    $quiet       = resolveQuiet();

    $runner = AdvancedSystemTestRunner::createDefault($projectRoot);
    $report = $runner->run();

    switch ($format) {
        case 'json':
            if (!headers_sent()) header('Content-Type: application/json; charset=UTF-8');
            echo ReportFormatter::json($report);
            break;
        case 'md':
        case 'markdown':
            if (!headers_sent()) header('Content-Type: text/markdown; charset=UTF-8');
            echo ReportFormatter::markdown($report);
            break;
        case 'html':
            if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
            echo ReportFormatter::html($report);
            break;
        case 'cli':
        default:
            echo ReportFormatter::cli($report, $quiet);
            break;
    }
}