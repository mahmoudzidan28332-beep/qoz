<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
$user = $_SESSION['user'] ?? null;

if (!$pdo instanceof PDO || !$user) {
    ResponseFormatter::error('User or database not initialized', 500);
    exit;
}

require_once dirname(__DIR__) . '/models/tenant_users/repositories/PdoTenant_usersRepository.php';
require_once dirname(__DIR__) . '/models/tenant_users/validators/Tenant_usersValidator.php';
require_once dirname(__DIR__) . '/models/tenant_users/services/Tenant_usersService.php';
require_once dirname(__DIR__) . '/models/tenant_users/controllers/Tenant_usersController.php';

$tenantUsersRepo       = new PdoTenant_usersRepository($pdo);
$tenantUsersValidator  = new Tenant_usersValidator();
$tenantUsersService    = new Tenant_usersService($tenantUsersRepo, $tenantUsersValidator);
$tenantUsersController = new Tenant_usersController($tenantUsersService);

// جلب جميع tenants التي ينتمي إليها المستخدم
$tenantsData = $tenantUsersController->getTenantsByUserId($user['id']);

// جلب الصلاحيات لكل دور ضمن الـ tenants
$rolesPermissions = [];
foreach ($tenantsData as $td) {
    $roleId = $td['role_id'];
    $tenantId = $td['tenant_id'];
    if ($roleId) {
        $perms = $tenantUsersController->getPermissionsByRoleAndTenant($roleId, $tenantId);
        $rolesPermissions[$tenantId] = $perms;
    }
}

// إعداد الرد النهائي
$response = [
    'ok' => true,
    'module' => 'admin',
    'db_connected' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'preferred_language' => $user['preferred_language'] ?? 'ar',
        'is_active' => $user['is_active'] ?? true,
        'tenants' => []
    ]
];

foreach ($tenantsData as $td) {
    $tid = $td['tenant_id'];
    $response['user']['tenants'][] = [
        'tenant_id' => $tid,
        'tenant_name' => $td['tenant_name'],
        'tenant_domain' => $td['tenant_domain'],
        'role_id' => $td['role_id'],
        'role_key' => $td['role_key'],
        'role_name' => $td['role_name'],
        'is_active' => (bool)$td['tenant_user_active'],
        'permissions' => $rolesPermissions[$tid] ?? []
    ];
}

ResponseFormatter::success($response);