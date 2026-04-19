<?php
declare(strict_types=1);

/**
 * Security Verification: Multi-Tenant Isolation & Response Formatting
 */

require_once __DIR__ . '/../../api/shared/core/ResponseFormatter.php';
require_once __DIR__ . '/../../api/shared/security/SecurityValidators.php';

define('TEST_MODE', true);

echo "--- Running Multi-Tenant security tests ---\n";

// 1. Test ResponseFormatter hardening
echo "[Test] ResponseFormatter::serverError concealment... ";
ob_start();
ResponseFormatter::serverError("Secret path: /local/secrets/config.php");
$output = ob_get_clean();
$response = json_decode($output, true);

if (($response['message'] ?? '') === 'Internal Server Error' && !str_contains($output, 'secrets')) {
    echo "PASS (Data hidden)\n";
} else {
    echo "FAIL (Data leaked: " . ($response['message'] ?? 'empty') . ")\n";
}

// 2. Test MultiTenantValidator (Mock PDO)
echo "[Test] MultiTenantValidator existence... ";
if (class_exists('MultiTenantValidator')) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// 3. Test Ownership Verification Logic (Simulated)
class MockPDO extends PDO {
    public function __construct() {}
    public function prepare($sql, $options = []): PDOStatement|false {
        return new class extends PDOStatement {
            public function __construct() {}
            public function execute($params = null): bool { return true; }
            public function fetchColumn($column = 0): mixed {
                // Simulate: ID 1 belongs to Tenant 10
                $id = $GLOBALS['mock_id'] ?? 0;
                $tid = $GLOBALS['mock_tid'] ?? 0;
                return ($id == 1 && $tid == 10) ? 1 : 0;
            }
        };
    }
}

$mockPdo = new MockPDO();

echo "[Test] Ownership: Correct Tenant... ";
$GLOBALS['mock_id'] = 1; $GLOBALS['mock_tid'] = 10;
if (MultiTenantValidator::verifyOwnership($mockPdo, 'addresses', 1, 10)) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

echo "[Test] Ownership: Wrong Tenant (Cross-tenant probe)... ";
$GLOBALS['mock_id'] = 1; $GLOBALS['mock_tid'] = 99;
if (!MultiTenantValidator::verifyOwnership($mockPdo, 'addresses', 1, 99)) {
    echo "PASS (Blocked)\n";
} else {
    echo "FAIL (Allowed cross-tenant access!)\n";
}

echo "[Test] Ownership: Invalid Table Whitelist... ";
if (!MultiTenantValidator::verifyOwnership($mockPdo, 'users; DROP TABLE users;--', 1, 10)) {
    echo "PASS (Rejected Injection)\n";
} else {
    echo "FAIL (Allowed non-whitelisted table!)\n";
}

echo "[Test] Ownership: Column Whitelist... ";
if (!MultiTenantValidator::verifyOwnership($mockPdo, 'users', 1, 10, 'is_admin')) {
    echo "PASS (Rejected non-whitelisted column)\n";
} else {
    echo "FAIL (Allowed non-whitelisted column!)\n";
}

// 4. Test RateLimiter (requires real DB or more complex mock)
echo "[Test] RateLimitValidator exists... ";
if (class_exists('RateLimitValidator')) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

echo "--- Verification Complete ---\n";

