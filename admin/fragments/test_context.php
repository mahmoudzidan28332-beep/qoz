<?php
declare(strict_types=1);

// Load admin context
require_once __DIR__ . '/../includes/admin_context.php';

header('Content-Type: application/json; charset=utf-8');

// Display current admin UI context
echo json_encode([
    'admin_ui' => $GLOBALS['ADMIN_UI'] ?? null,
    'is_logged_in' => is_admin_logged_in(),
    'user_id' => admin_user_id(),
    'username' => admin_username(),
    'user_type' => get_user_type(),
    'is_super_admin' => is_super_admin(),
    'can_view_tenant_users' => function_exists('can_view_all') ? can_view_all('tenant_users') : false,
    'session_user_id' => $_SESSION['user_id'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);