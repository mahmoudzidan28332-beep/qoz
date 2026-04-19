<?php
declare(strict_types=1);

/**
 * JWT Security Verification Script
 * This script tests the fixes for CWE-347 (none algorithm)
 */

require_once __DIR__ . '/../api/shared/security/SecurityValidators.php';
require_once __DIR__ . '/../api/shared/security/SecurityMiddleware.php';

// Mock config for testing
class MockSecurityConfig {
    public static array $allowedJwtAlgs = ['HS256'];
}

// In a real scenario, we'd use the real config
// But for a unit-style test, we can inject or see if we can use the class directly

$secret = 'test_secret_12345';

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function create_fake_token($header, $payload, $secret = null) {
    $h = base64UrlEncode(json_encode($header));
    $p = base64UrlEncode(json_encode($payload));
    
    if ($header['alg'] === 'none' || $secret === null) {
        $s = '';
    } else {
        $s = base64UrlEncode(hash_hmac('sha256', "$h.$p", $secret, true));
    }
    
    return "$h.$p.$s";
}

$testCases = [
    [
        'name' => 'Valid HS256 Token',
        'header' => ['alg' => 'HS256', 'typ' => 'JWT'],
        'payload' => ['sub' => '123', 'exp' => time() + 3600],
        'secret' => $secret,
        'expected' => true
    ],
    [
        'name' => 'Algorithm "none" (Should fail)',
        'header' => ['alg' => 'none', 'typ' => 'JWT'],
        'payload' => ['sub' => '123', 'exp' => time() + 3600],
        'secret' => null,
        'expected' => false
    ],
    [
        'name' => 'Algorithm "RS256" (Whitelisted but Not Implemented - Should fail securely)',
        'header' => ['alg' => 'RS256', 'typ' => 'JWT'],
        'payload' => ['sub' => '123', 'exp' => time() + 3600],
        'secret' => 'anything',
        'expected' => false
    ],
    [
        'name' => 'HS256 with invalid signature (Should fail)',
        'header' => ['alg' => 'HS256', 'typ' => 'JWT'],
        'payload' => ['sub' => '123', 'exp' => time() + 3600],
        'secret' => 'wrong_secret',
        'expected' => false
    ],
    [
        'name' => 'Missing "alg" header (Should fail)',
        'header' => ['typ' => 'JWT'],
        'payload' => ['sub' => '123', 'exp' => time() + 3600],
        'secret' => $secret,
        'expected' => false
    ]
];

echo "Running JWT Security Tests...\n";
echo str_repeat("-", 40) . "\n";

$allPassed = true;
foreach ($testCases as $case) {
    $token = create_fake_token($case['header'], $case['payload'], $case['secret']);
    
    // We need to make sure SecurityConfig matches what we want to test
    SecurityConfig::$allowedJwtAlgs = ['HS256', 'RS256'];
    
    $result = JwtValidator::validate($token, $secret);
    
    $status = ($result['valid'] === $case['expected']) ? "PASS" : "FAIL";
    echo sprintf("[%s] %s\n", $status, $case['name']);
    
    if (!$result['valid']) {
        echo "      Error: " . $result['error'] . "\n";
    }
    
    if ($status === "FAIL") {
        $allPassed = false;
    }
}

echo str_repeat("-", 40) . "\n";
if ($allPassed) {
    echo "SUCCESS: All security tests passed.\n";
} else {
    echo "FAILURE: Some security tests failed.\n";
    exit(1);
}
