<?php
declare(strict_types=1);

// api/tests/test_permissions_user7.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../shared/core/ResponseFormatter.php';
require_once __DIR__ . '/../shared/helpers/safe_helpers.php';
require_once __DIR__ . '/../shared/config/db.php';
require_once __DIR__ . '/../shared/security/PermissionService.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    die('Database not initialized - ADMIN_DB not set');
}

// Simulate session for user 7, tenant 1
$_SESSION['user_id'] = 7;
$_SESSION['tenant_id'] = 1;

echo "=== PermissionService Test for User 7 ===\n\n";

echo "1. Session Information:\n";
$sessionInfo = PermissionService::getSessionInfo();
echo json_encode($sessionInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "2. Session Validation:\n";
$validation = PermissionService::validateSession($pdo);
echo json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. User Details:\n";
$userId = PermissionService::getCurrentUserId();
if ($userId) {
    $userDetails = PermissionService::getUserDetails($pdo, $userId);
    echo json_encode($userDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} else {
    echo "No user in session\n\n";
}

echo "4. User Permissions:\n";
$permissions = PermissionService::getCurrentUserPermissions($pdo);
echo "Permissions Count: " . count($permissions) . "\n";
echo "Permissions: " . implode(', ', $permissions) . "\n\n";

echo "5. User Roles:\n";
$role = PermissionService::getCurrentUserRole($pdo);
if ($role) {
    echo json_encode($role, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} else {
    echo "No role found\n\n";
}

echo "6. Role Permissions:\n";
if ($role && isset($role['id'])) {
    $rolePerms = PermissionService::getRolePermissions($pdo, $role['id'], 1);
    echo "Role Permissions Count: " . count($rolePerms) . "\n";
    echo json_encode($rolePerms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} else {
    echo "No role permissions\n\n";
}

echo "7. Check Specific Permission (manage_users):\n";
$hasPerm = PermissionService::currentUserHasPermission($pdo, 'manage_users');
echo "Has 'manage_users': " . ($hasPerm ? 'Yes' : 'No') . "\n\n";

echo "8. All Permissions for Tenant 1:\n";
$allPerms = PermissionService::getPermissions($pdo, 1);
echo "Total Permissions: " . count($allPerms) . "\n";
echo json_encode($allPerms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "9. All Roles for Tenant 1:\n";
$allRoles = PermissionService::getRoles($pdo, 1);
echo "Total Roles: " . count($allRoles) . "\n";
echo json_encode($allRoles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "10. Complete User Profile:\n";
$profile = PermissionService::getCompleteUserProfile($pdo);
echo json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Test Completed ===\n";
?>