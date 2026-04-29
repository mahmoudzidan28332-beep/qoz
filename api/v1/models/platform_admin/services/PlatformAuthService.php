<?php
declare(strict_types=1);

namespace App\V1\Models\PlatformAdmin\Services;

use PdoUsersRepository;
use Exception;

class PlatformAuthService
{
    private PdoUsersRepository $userRepo;

    public function __construct(PdoUsersRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function authenticate(string $username, string $password): array
    {
        // 1. Find platform user
        $user = $this->userRepo->findPlatformUserByUsername($username);
        if (!$user) {
            throw new Exception('Invalid credentials or access denied.', 401);
        }

        // 2. Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Invalid credentials.', 401);
        }

        // 3. Check status
        if (($user['is_active'] ?? 0) != 1) {
            throw new Exception('Account is inactive.', 403);
        }
        if (($user['platform_active'] ?? 0) != 1) {
            throw new Exception('Platform access deactivated.', 403);
        }

        return $user;
    }

    public function bootstrapSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['platform_admin']   = true;
        $_SESSION['platform_role']    = $user['platform_role'] ?? 'support';
        $_SESSION['platform_user_id'] = (int)($user['platform_user_id'] ?? 0);
        $_SESSION['user_id']          = $user['id'];
        $_SESSION['tenant_id']        = null; // Platform users are global and don't belong to a specific tenant
        $_SESSION['user']             = $user;
        $_SESSION['logged_in']        = true;
        $_SESSION['last_activity']    = time();
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['platform_admin'], $_SESSION['platform_role'], $_SESSION['platform_user_id']);
        // Keep session alive but clear platform privilege
    }
}
