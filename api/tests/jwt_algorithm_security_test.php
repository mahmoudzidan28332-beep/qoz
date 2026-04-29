<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$rootDir = dirname(__DIR__);
$generatedKeyDir = null;
[$privateKeyPath, $publicKeyPath, $keyMode, $keyDetail] = (static function (): array {
    $configuredPrivate = getenv('JWT_PRIVATE_KEY_PATH') ?: '';
    $configuredPublic = getenv('JWT_PUBLIC_KEY_PATH') ?: '';

    if ($configuredPrivate !== '' && $configuredPublic !== ''
        && is_readable($configuredPrivate) && is_readable($configuredPublic)) {
        return [$configuredPrivate, $configuredPublic, 'configured', 'Using configured JWT key paths'];
    }

    if (function_exists('openssl_pkey_new')) {
        $keyResource = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keyResource !== false) {
            $tempDir = sys_get_temp_dir() . '/jwt_alg_test_' . bin2hex(random_bytes(6));
            if (mkdir($tempDir, 0700, true) || is_dir($tempDir)) {
                $privateKeyPath = $tempDir . '/jwt_private.pem';
                $publicKeyPath = $tempDir . '/jwt_public.pem';

                openssl_pkey_export($keyResource, $privateKey);
                $details = openssl_pkey_get_details($keyResource);
                $publicKey = is_array($details) ? ($details['key'] ?? null) : null;

                if (is_string($privateKey) && is_string($publicKey)) {
                    file_put_contents($privateKeyPath, $privateKey);
                    file_put_contents($publicKeyPath, $publicKey);
                    $GLOBALS['__jwt_alg_test_temp_dir'] = $tempDir;
                    return [$privateKeyPath, $publicKeyPath, 'generated', 'Generated temporary RSA keys for the test'];
                }
            }
        }
    }

    return ['', '', 'unavailable', 'JWT keys are not configured and temporary key generation failed'];
})();

if ($privateKeyPath !== '' && $publicKeyPath !== '') {
    putenv('JWT_PRIVATE_KEY_PATH=' . $privateKeyPath);
    putenv('JWT_PUBLIC_KEY_PATH=' . $publicKeyPath);
    $_ENV['JWT_PRIVATE_KEY_PATH'] = $privateKeyPath;
    $_ENV['JWT_PUBLIC_KEY_PATH'] = $publicKeyPath;
}

putenv('JWT_EXPIRY=3600');
$_ENV['JWT_EXPIRY'] = '3600';

require_once $rootDir . '/shared/helpers/jwt.php';

function jwt_alg_test_base64url(array $data): string
{
    return rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
}

function jwt_alg_test_assert(bool $condition, string $name, string $detail = ''): array
{
    return [
        'test' => $name,
        'status' => $condition ? 'PASS' : 'FAIL',
        'detail' => $detail,
    ];
}

function jwt_alg_test_skip(string $name, string $detail = ''): array
{
    return [
        'test' => $name,
        'status' => 'SKIP',
        'detail' => $detail,
    ];
}

function jwt_alg_test_token(array $header, array $payload, string $signature = ''): string
{
    return jwt_alg_test_base64url($header) . '.' . jwt_alg_test_base64url($payload) . '.' . $signature;
}

$now = time();
$payload = [
    'user_id' => 123,
    'iat' => $now,
    'exp' => $now + 300,
    'jti' => bin2hex(random_bytes(16)),
];

$noneToken = jwt_alg_test_token(
    ['typ' => 'JWT', 'alg' => 'none'],
    $payload
);

$hs256Token = jwt_alg_test_token(
    ['typ' => 'JWT', 'alg' => 'HS256'],
    $payload,
    rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')
);

$authPhp = file_get_contents($rootDir . '/auth.php');
$results = [];

$results[] = [
    'test' => 'Key Mode',
    'status' => $keyMode === 'unavailable' ? 'SKIP' : 'PASS',
    'detail' => $keyDetail,
];

$results[] = jwt_alg_test_assert(
    JWT::getAllowedAlgorithms() === ['RS256', 'ES256'],
    'Allowlist Is Exact',
    implode(', ', JWT::getAllowedAlgorithms())
);

$results[] = jwt_alg_test_assert(
    JWT::hasAllowedHeaderAlgorithm($noneToken) === false,
    'Reject Header alg=none',
    'JWT::hasAllowedHeaderAlgorithm returned false'
);

$results[] = jwt_alg_test_assert(
    JWT::decode($noneToken) === false,
    'Reject Decode alg=none',
    'JWT::decode rejected unsigned token'
);

$results[] = jwt_alg_test_assert(
    JWT::hasAllowedHeaderAlgorithm($hs256Token) === false,
    'Reject Header alg=HS256',
    'HS256 is outside the allowlist'
);

$results[] = jwt_alg_test_assert(
    JWT::decode($hs256Token) === false,
    'Reject Decode alg=HS256',
    'JWT::decode rejected HS256 token'
);

if ($keyMode === 'unavailable') {
    $results[] = jwt_alg_test_skip(
        'Accept Valid RS256 Token',
        'Skipped because JWT key paths are unavailable in this environment'
    );
} else {
    $validToken = JWT::encode(['user_id' => 321], 300);
    $validPayload = JWT::decode($validToken);

    $results[] = jwt_alg_test_assert(
        is_array($validPayload) && ($validPayload['user_id'] ?? null) === 321,
        'Accept Valid RS256 Token',
        'JWT::encode and JWT::decode round-trip succeeded'
    );
}

$results[] = jwt_alg_test_assert(
    is_string($authPhp)
    && str_contains($authPhp, 'Rejected bearer token with alg=none')
    && str_contains($authPhp, 'getAllowedAlgorithms'),
    'auth.php Has Early Guard',
    'auth.php contains explicit alg=none rejection and allowlist usage'
);

$failed = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 'FAIL'));

echo json_encode([
    'success' => $failed === [],
    'message' => $failed === [] ? 'JWT algorithm security tests passed' : 'JWT algorithm security tests failed',
    'mode' => PHP_SAPI,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (isset($GLOBALS['__jwt_alg_test_temp_dir']) && is_string($GLOBALS['__jwt_alg_test_temp_dir'])) {
    @unlink($GLOBALS['__jwt_alg_test_temp_dir'] . '/jwt_private.pem');
    @unlink($GLOBALS['__jwt_alg_test_temp_dir'] . '/jwt_public.pem');
    @rmdir($GLOBALS['__jwt_alg_test_temp_dir']);
}

exit($failed === [] ? 0 : 1);
