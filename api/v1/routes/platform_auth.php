<?php
declare(strict_types=1);
ini_set('display_errors', 0);
set_exception_handler(function(\Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    exit;
});
/**
 * Platform Admin Authentication Route
 * 
 * Handles login, logout, and session status for super-admins.
 * Follows Route -> Controller -> Service pattern.
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../shared/core/ResponseFormatter.php';
require_once __DIR__ . '/../models/users_account/repositories/PdoUsersRepository.php';
require_once __DIR__ . '/../models/platform_admin/services/PlatformAuthService.php';
require_once __DIR__ . '/../models/platform_admin/controllers/PlatformAuthController.php';

use App\V1\Models\PlatformAdmin\Controllers\PlatformAuthController;
use App\V1\Models\PlatformAdmin\Services\PlatformAuthService;

// Initialization
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database connection failed.', 500);
    exit;
}

$userRepo = new PdoUsersRepository($pdo);
$authService = new PlatformAuthService($userRepo);
$controller = new PlatformAuthController($authService);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Get action from GET, POST, or JSON input - avoid empty string overrides
$action = '';
if (!empty($_GET['action'])) {
    $action = $_GET['action'];
} elseif (!empty($_POST['action'])) {
    $action = $_POST['action'];
} elseif (!empty($input['action'])) {
    $action = $input['action'];
}

_pa_log('debug', 'platform_auth request', [
    'method' => $method,
    'action' => $action,
    'has_input' => !empty($input),
    'has_post' => !empty($_POST),
    'has_get' => !empty($_GET)
]);

// Fallback for legacy calls or missing action parameter
if ($action === '' && $method === 'POST') {
    $hasLoginFields = isset($_POST['identifier']) || isset($_POST['username']) || isset($input['identifier']) || isset($input['username']);
    if ($hasLoginFields && (isset($_POST['password']) || isset($input['password']))) {
        $action = 'login';
    }
}

try {
    if ($method === 'POST') {
        if ($action === 'login') {
            $controller->login();
        } elseif ($action === 'logout') {
            $controller->logout();
        } else {
            ResponseFormatter::error("Invalid action '{$action}' for POST request.", 400);
        }
    } elseif ($method === 'GET') {
        if ($action === 'status' || $action === 'me') {
            $controller->status();
        } elseif ($action === 'login') {
            ResponseFormatter::error('Login requires a POST request. Please use the login form.', 405);
        } else {
            ResponseFormatter::error("Invalid action '{$action}' for GET request.", 400);
        }
    } else {
        ResponseFormatter::error('Method not allowed.', 405);
    }
} catch (Throwable $e) {
    _pa_log('error', 'platform_auth exception', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal server error.', 500);
}

/**
 * Internal logging for platform auth
 */
function _pa_log(string $level, string $msg, array $ctx = []): void {
    if (function_exists('safe_log')) {
        safe_log($level, $msg, $ctx);
    } else {
        $file = __DIR__ . '/../../../logs/platform_auth.log';
        $log = sprintf("[%s] [%s] %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $msg, json_encode($ctx));
        @file_put_contents($file, $log, FILE_APPEND);
    }
}