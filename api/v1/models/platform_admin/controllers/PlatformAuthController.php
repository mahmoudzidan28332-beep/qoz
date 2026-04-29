<?php
declare(strict_types=1);

namespace App\V1\Models\PlatformAdmin\Controllers;

use App\V1\Models\PlatformAdmin\Services\PlatformAuthService;
use ResponseFormatter;
use Throwable;

class PlatformAuthController
{
    private PlatformAuthService $authService;

    public function __construct(PlatformAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $username = trim((string)($input['identifier'] ?? $input['username'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $csrfToken = (string)($input['csrf_token'] ?? '');

            // CSRF Check
            if (session_status() === PHP_SESSION_NONE) session_start();
            $sessionCsrf = $_SESSION['csrf_token'] ?? '';
            if (!$sessionCsrf || !hash_equals($sessionCsrf, $csrfToken)) {
                ResponseFormatter::error('Invalid CSRF token. Please reload the page.', 403);
                return;
            }

            if (!$username || !$password) {
                ResponseFormatter::error('Username/Email and password are required.', 400);
                return;
            }

            $user = $this->authService->authenticate($username, $password);
            $this->authService->bootstrapSession($user);

            ResponseFormatter::success([
                'ok' => true,
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['platform_role'] ?? 'support',
                'redirect' => '/admin/dashboard.php'
            ], 'Authentication successful.');

        } catch (Throwable $e) {
            $code = $e->getCode() ?: 401;
            ResponseFormatter::error($e->getMessage(), is_int($code) ? $code : 401);
        }
    }

    public function logout(): void
    {
        $this->authService->logout();
        ResponseFormatter::success(null, 'Logged out successfully.');
    }

    public function status(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isAuthenticated = !empty($_SESSION['platform_admin']);
        
        ResponseFormatter::success([
            'is_authenticated' => $isAuthenticated,
            'role' => $_SESSION['platform_role'] ?? null
        ]);
    }
}
