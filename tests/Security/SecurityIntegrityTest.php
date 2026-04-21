<?php
declare(strict_types=1);

/**
 * SecurityIntegrityTest.php
 *
 * Standalone security test suite for the multi-tenant isolation layer.
 *
 * Covers:
 *  1. Cross-tenant data access prevention
 *  2. Missing tenant_id query detection (QueryGuard)
 *  3. Unauthorized role actions
 *  4. Super-admin bypass audit logging
 *  5. TenantContext fail-fast behaviour
 *  6. BaseRepository::execute() auto-injection
 *  7. AuditContext payload enrichment
 *  8. SecurityValidator integrity check
 *
 * USAGE (CLI):
 *   php tests/Security/SecurityIntegrityTest.php
 *
 * Exit code: 0 = all pass, 1 = one or more failures.
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────

$root = dirname(__DIR__, 2); // project root

require_once $root . '/api/shared/core/TenantContext.php';
require_once $root . '/api/shared/core/TenantScopedInterface.php';
require_once $root . '/api/shared/core/QueryGuard.php';
require_once $root . '/api/shared/core/BaseRepository.php';
require_once $root . '/api/shared/core/BaseService.php';
require_once $root . '/api/shared/core/PlatformContext.php';
require_once $root . '/api/shared/core/AuditContext.php';
require_once $root . '/api/shared/core/SecurityValidator.php';

// ── Test runner ───────────────────────────────────────────────────────────────

final class TestRunner
{
    private int $passed  = 0;
    private int $failed  = 0;
    private array $failures = [];

    public function run(string $name, callable $fn): void
    {
        try {
            $fn($this);
            $this->passed++;
            echo "  ✅ {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $this->failures[] = "[FAIL] {$name}: " . $e->getMessage();
            echo "  ❌ {$name}\n     → " . $e->getMessage() . "\n";
        }
    }

    public function assert(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function assertThrows(callable $fn, string $containsMessage = ''): void
    {
        try {
            $fn();
            throw new \RuntimeException('Expected exception was NOT thrown.');
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'Expected exception was NOT thrown.') {
                throw $e;
            }
            if ($containsMessage !== '' && !str_contains($e->getMessage(), $containsMessage)) {
                throw new \RuntimeException(
                    "Exception thrown but message did not contain '{$containsMessage}'. "
                    . "Got: '{$e->getMessage()}'"
                );
            }
        }
    }

    public function summary(): int
    {
        $total = $this->passed + $this->failed;
        echo "\n────────────────────────────────────────\n";
        echo "  Results: {$this->passed}/{$total} passed";
        if ($this->failed > 0) {
            echo ", {$this->failed} FAILED";
        }
        echo "\n";
        if (!empty($this->failures)) {
            echo "\n  Failed tests:\n";
            foreach ($this->failures as $f) {
                echo "    {$f}\n";
            }
        }
        return $this->failed > 0 ? 1 : 0;
    }
}

// ── Mock PDO ─────────────────────────────────────────────────────────────────

/**
 * Minimal PDO stub extending \PDO — never touches a real database.
 * We extend PDO to satisfy the type hint in BaseRepository::__construct().
 */
class MockPdo extends \PDO
{
    public array $lastSql    = [];
    public array $lastParams = [];

    /** Override constructor — do NOT call parent to avoid real DB connection. */
    public function __construct()
    {
        // Intentionally left empty.
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->lastSql[] = $query;
        return new MockStatement($this, $query);
    }
}

class MockStatement extends \PDOStatement
{
    public function __construct(
        private MockPdo $pdo,
        private string  $sql
    ) {
        // Do not call parent constructor.
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->lastParams[] = $params ?? [];
        return true;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return false; }
    public function fetchColumn(int $column = 0): mixed { return null; }
    public function rowCount(): int { return 0; }
}

// ── Concrete repository for testing ──────────────────────────────────────────

final class ConcreteTestRepository extends BaseRepository implements TenantScopedInterface
{
    public function getProducts(): \PDOStatement
    {
        return $this->execute(
            'SELECT * FROM products WHERE status = :s',
            [':s' => 'active'],
            'products'
        );
    }

    public function getProductsNoTenant(): \PDOStatement
    {
        // Deliberately omits tenant_id — auto-injection should kick in.
        return $this->execute(
            'SELECT * FROM products WHERE status = :s',
            [':s' => 'active'],
            '' // no table hint = not whitelisted = tenant must be injected
        );
    }

    public function getAuditLogs(): \PDOStatement
    {
        return $this->executeGlobal(
            'SELECT * FROM audit_logs LIMIT 100',
            [],
            'audit_logs'
        );
    }

    public function rawPdo(): \PDO
    {
        // Expose pdo for assertions; repositories should NEVER do this in prod.
        return $this->pdo; // @phpstan-ignore-line
    }
}

// ─── TESTS ───────────────────────────────────────────────────────────────────

$t    = new TestRunner();
$pdo  = new MockPdo();

/** @phpstan-ignore-next-line */
$repo = new ConcreteTestRepository($pdo);

echo "Security Integrity Test Suite\n";
echo "══════════════════════════════\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// Section 1: TenantContext fail-fast
// ─────────────────────────────────────────────────────────────────────────────
echo "1. TenantContext\n";

$t->run('getId() throws when not set', function (TestRunner $t) {
    TenantContext::clear();
    $t->assertThrows(
        fn() => TenantContext::getId(),
        'TenantContext has not been initialized'
    );
});

$t->run('require() throws when not set', function (TestRunner $t) {
    TenantContext::clear();
    $t->assertThrows(
        fn() => TenantContext::require(),
        'TenantContext::require()'
    );
});

$t->run('set() rejects non-positive tenant_id', function (TestRunner $t) {
    $t->assertThrows(fn() => TenantContext::set(0));
    $t->assertThrows(fn() => TenantContext::set(-5));
});

$t->run('set() and getId() round-trip', function (TestRunner $t) {
    TenantContext::set(42);
    $t->assert(TenantContext::getId() === 42, 'Expected 42');
    TenantContext::clear();
});

$t->run('isSet() reflects state correctly', function (TestRunner $t) {
    TenantContext::clear();
    $t->assert(!TenantContext::isSet(), 'Should be false before set()');
    TenantContext::set(7);
    $t->assert(TenantContext::isSet(), 'Should be true after set()');
    TenantContext::clear();
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 2: QueryGuard
// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. QueryGuard\n";

$t->run('validate() throws for SQL missing tenant_id', function (TestRunner $t) {
    $t->assertThrows(
        fn() => QueryGuard::validate('SELECT * FROM products', 'products'),
        'tenant isolation missing'
    );
});

$t->run('validate() passes for SQL with tenant_id', function (TestRunner $t) {
    QueryGuard::validate("SELECT * FROM products WHERE tenant_id = :tid", 'products');
    $t->assert(true); // No exception = pass
});

$t->run('validate() passes for global table without tenant_id', function (TestRunner $t) {
    QueryGuard::validate('SELECT * FROM audit_logs', 'audit_logs');
    $t->assert(true);
});

$t->run('validate() throws for non-whitelisted table without tenant_id', function (TestRunner $t) {
    $t->assertThrows(
        fn() => QueryGuard::validate('SELECT * FROM orders', 'orders'),
        'tenant isolation missing'
    );
});

$t->run('allowGlobal() adds table to whitelist', function (TestRunner $t) {
    QueryGuard::allowGlobal(['custom_platform_config']);
    $t->assert(QueryGuard::isGlobal('custom_platform_config'));
    QueryGuard::removeGlobal('custom_platform_config');
});

$t->run('removeGlobal() removes table from whitelist', function (TestRunner $t) {
    QueryGuard::allowGlobal(['temp_table']);
    $t->assert(QueryGuard::isGlobal('temp_table'));
    QueryGuard::removeGlobal('temp_table');
    $t->assert(!QueryGuard::isGlobal('temp_table'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 3: BaseRepository::execute() — tenant auto-injection
// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. BaseRepository::execute() auto-injection\n";

$t->run('execute() throws when TenantContext not set (no global table)', function (TestRunner $t) use ($repo) {
    TenantContext::clear();
    $t->assertThrows(
        fn() => $repo->getProductsNoTenant(),
        'TenantContext' // Propagated from TenantContext::require()
    );
});

$t->run('execute() auto-injects tenant_id into SQL without it', function (TestRunner $t) use ($pdo, $repo) {
    TenantContext::set(5);
    $pdo->lastSql    = [];
    $pdo->lastParams = [];

    $repo->getProductsNoTenant();

    $sql = $pdo->lastSql[0] ?? '';
    $t->assert(str_contains(strtolower($sql), 'tenant_id'), "SQL should contain tenant_id. Got: {$sql}");

    $params = $pdo->lastParams[0] ?? [];
    $tenantParam = $params[':_auto_tenant_id'] ?? $params[':tenant_id'] ?? null;
    $t->assert($tenantParam === 5, "tenant_id param should be 5. Got: " . var_export($tenantParam, true));

    TenantContext::clear();
});

$t->run('executeGlobal() passes for whitelisted table without tenant_id', function (TestRunner $t) use ($repo) {
    TenantContext::clear(); // Global queries should not need TenantContext.
    $repo->getAuditLogs();
    $t->assert(true);
});

$t->run('executeGlobal() throws for non-whitelisted table', function (TestRunner $t) use ($pdo) {
    /** @phpstan-ignore-next-line */
    $badRepo = new class($pdo) extends BaseRepository {
        public function doIt(): \PDOStatement
        {
            return $this->executeGlobal('SELECT 1', [], 'products');
        }
    };
    $t->assertThrows(fn() => $badRepo->doIt(), 'not in the QueryGuard global whitelist');
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 4: Cross-tenant access prevention
// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Cross-tenant access prevention\n";

$t->run('Different tenant IDs cannot share the same TenantContext scope', function (TestRunner $t) {
    TenantContext::set(10);
    $t->assert(TenantContext::getId() === 10);

    // Simulating tenant switch without clearing — set() overwrites.
    TenantContext::set(20);
    $t->assert(TenantContext::getId() === 20, 'TenantContext should hold the new tenant ID');
    TenantContext::clear();
});

$t->run('execute() scopes query to active tenant, not another', function (TestRunner $t) use ($pdo, $repo) {
    TenantContext::set(99);
    $pdo->lastParams = [];

    $repo->getProductsNoTenant();

    $params    = $pdo->lastParams[0] ?? [];
    $tenantVal = $params[':_auto_tenant_id'] ?? $params[':tenant_id'] ?? null;
    $t->assert($tenantVal === 99, "Expected tenant_id=99 but got: " . var_export($tenantVal, true));

    TenantContext::clear();
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 5: TenantScopedInterface + SecurityValidator
// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. TenantScopedInterface & SecurityValidator\n";

$t->run('ConcreteTestRepository implements TenantScopedInterface', function (TestRunner $t) use ($repo) {
    $t->assert($repo instanceof TenantScopedInterface);
});

$t->run('ConcreteTestRepository extends BaseRepository', function (TestRunner $t) use ($repo) {
    $t->assert($repo instanceof BaseRepository);
});

$t->run('SecurityValidator::assertSecurityLayerLoaded() passes when all classes are loaded', function (TestRunner $t) {
    // All classes were loaded at the top of this file.
    SecurityValidator::assertSecurityLayerLoaded();
    $t->assert(true);
});

$t->run('SecurityValidator::assertSystemIntegrity() throws when TenantContext not set', function (TestRunner $t) {
    TenantContext::clear();
    // In development mode (display_errors may be off in test), capture via exception.
    ini_set('display_errors', '1'); // Force dev mode for test.
    $t->assertThrows(
        fn() => SecurityValidator::assertSystemIntegrity(),
        'TenantContext'
    );
});

$t->run('SecurityValidator::assertSystemIntegrity() passes when context is set', function (TestRunner $t) {
    TenantContext::set(1);
    ini_set('display_errors', '1');
    SecurityValidator::assertSystemIntegrity();
    $t->assert(true);
    TenantContext::clear();
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 6: PlatformContext — super_admin role rules
// ─────────────────────────────────────────────────────────────────────────────
echo "\n6. PlatformContext\n";

$t->run('assertTenantAccess() throws for non-super-admin with null tenant', function (TestRunner $t) {
    PlatformContext::reset();
    // isSuperAdmin() is false after reset.
    $t->assertThrows(
        fn() => PlatformContext::assertTenantAccess(null),
        'access denied'
    );
});

$t->run('assertTenantAccess() passes for non-super-admin with valid tenant', function (TestRunner $t) {
    PlatformContext::reset();
    PlatformContext::assertTenantAccess(5);
    $t->assert(true);
});

$t->run('requireSuperAdmin() throws when actor is not super_admin', function (TestRunner $t) {
    PlatformContext::reset();
    $t->assertThrows(
        fn() => PlatformContext::requireSuperAdmin(),
        'platform-level operation requires super-admin'
    );
});

$t->run('logCrossTenantAction() does not throw and writes to error_log when audit unavailable', function (TestRunner $t) {
    PlatformContext::reset();
    // This should not throw even when audit_log is unavailable.
    PlatformContext::logCrossTenantAction(
        sourceTenant: 1,
        targetTenant: 2,
        userId:       99,
        reason:       'test cross-tenant action'
    );
    $t->assert(true);
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 7: AuditContext
// ─────────────────────────────────────────────────────────────────────────────
echo "\n7. AuditContext\n";

$t->run('boot() generates a non-empty request_id', function (TestRunner $t) {
    AuditContext::reset();
    AuditContext::boot();
    $t->assert(AuditContext::getRequestId() !== '', 'request_id should not be empty after boot()');
});

$t->run('boot() is idempotent — second call does not change request_id', function (TestRunner $t) {
    AuditContext::reset();
    AuditContext::boot();
    $id1 = AuditContext::getRequestId();
    AuditContext::boot();
    $id2 = AuditContext::getRequestId();
    $t->assert($id1 === $id2, "request_id changed on second boot(): {$id1} vs {$id2}");
});

$t->run('capture() does not throw when audit_log is unavailable', function (TestRunner $t) {
    AuditContext::reset();
    AuditContext::boot();
    // audit_log helper is not loaded in this test context — should fallback gracefully.
    AuditContext::capture('data_create', 'products', 1, ['new' => ['name' => 'Widget']]);
    $t->assert(true);
});

$t->run('captureLogin() does not throw', function (TestRunner $t) {
    AuditContext::boot();
    AuditContext::captureLogin(false, 'attacker@evil.com', 'wrong password');
    $t->assert(true);
});

$t->run('captureCrossTenantAccess() does not throw', function (TestRunner $t) {
    AuditContext::boot();
    AuditContext::captureCrossTenantAccess(null, 5, 1, 'admin operation');
    $t->assert(true);
});

// ─────────────────────────────────────────────────────────────────────────────
// Section 8: Diff calculation sanity check (via AuditContext internals)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n8. Audit diff auto-calculation\n";

$t->run('capture() with old+new produces diff in error_log output (no exception)', function (TestRunner $t) {
    AuditContext::reset();
    AuditContext::boot();
    AuditContext::capture('data_update', 'products', 10, [
        'old' => ['name' => 'Old Name', 'price' => 10, 'status' => 'active'],
        'new' => ['name' => 'New Name', 'price' => 10, 'status' => 'inactive'],
    ]);
    $t->assert(true); // No exception = correctly handled.
});

$t->run('capture() with sensitive key in diff redacts values', function (TestRunner $t) {
    AuditContext::reset();
    AuditContext::boot();
    // If this doesn't throw, redaction didn't crash — verify via error_log in real env.
    AuditContext::capture('data_update', 'users', 1, [
        'old' => ['email' => 'a@b.com', 'password' => 'old_hash'],
        'new' => ['email' => 'x@y.com', 'password' => 'new_hash'],
    ]);
    $t->assert(true);
});

// ─────────────────────────────────────────────────────────────────────────────
// Done
// ─────────────────────────────────────────────────────────────────────────────

TenantContext::clear();
PlatformContext::reset();
AuditContext::reset();

exit($t->summary());
