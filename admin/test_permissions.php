<?php
/**
 * Test File for Admin Context Permission System
 * 
 * This file tests both role-based and resource-based permissions
 */

// Simulate session and database setup
session_start();

// Mock database structure
class MockDatabase {
    private $users = [
        1 => [
            'id' => 1,
            'username' => 'super_admin_user',
            'email' => 'super@example.com',
            'preferred_language' => 'en',
            'phone' => '+1234567890',
            'timezone' => 'UTC',
            'is_active' => 1
        ],
        2 => [
            'id' => 2,
            'username' => 'content_manager',
            'email' => 'manager@example.com',
            'preferred_language' => 'en',
            'phone' => '+1234567891',
            'timezone' => 'UTC',
            'is_active' => 1
        ],
        3 => [
            'id' => 3,
            'username' => 'editor_user',
            'email' => 'editor@example.com',
            'preferred_language' => 'ar',
            'phone' => '+1234567892',
            'timezone' => 'Asia/Dubai',
            'is_active' => 1
        ]
    ];
    
    private $tenant_users = [
        ['user_id' => 1, 'tenant_id' => 1, 'role_id' => 1, 'is_active' => 1],
        ['user_id' => 2, 'tenant_id' => 1, 'role_id' => 2, 'is_active' => 1],
        ['user_id' => 3, 'tenant_id' => 1, 'role_id' => 3, 'is_active' => 1],
    ];
    
    private $roles = [
        1 => ['id' => 1, 'key_name' => 'super_admin'],
        2 => ['id' => 2, 'key_name' => 'content_manager'],
        3 => ['id' => 3, 'key_name' => 'editor'],
    ];
    
    private $permissions = [
        ['id' => 1, 'key_name' => 'users.view', 'tenant_id' => 1],
        ['id' => 2, 'key_name' => 'users.create', 'tenant_id' => 1],
        ['id' => 3, 'key_name' => 'users.edit', 'tenant_id' => 1],
        ['id' => 4, 'key_name' => 'users.delete', 'tenant_id' => 1],
        ['id' => 5, 'key_name' => 'posts.view', 'tenant_id' => 1],
        ['id' => 6, 'key_name' => 'posts.create', 'tenant_id' => 1],
        ['id' => 7, 'key_name' => 'posts.edit', 'tenant_id' => 1],
        ['id' => 8, 'key_name' => 'posts.delete', 'tenant_id' => 1],
    ];
    
    private $role_permissions = [
        // Content Manager permissions
        ['role_id' => 2, 'permission_id' => 5, 'tenant_id' => 1],  // posts.view
        ['role_id' => 2, 'permission_id' => 6, 'tenant_id' => 1],  // posts.create
        ['role_id' => 2, 'permission_id' => 7, 'tenant_id' => 1],  // posts.edit
        
        // Editor permissions
        ['role_id' => 3, 'permission_id' => 5, 'tenant_id' => 1],  // posts.view
        ['role_id' => 3, 'permission_id' => 6, 'tenant_id' => 1],  // posts.create
        ['role_id' => 3, 'permission_id' => 7, 'tenant_id' => 1],  // posts.edit
    ];
    
    private $resource_permissions = [
        // Content Manager - Posts
        [
            'role_id' => 2,
            'permission_id' => 5,
            'tenant_id' => 1,
            'resource_type' => 'posts',
            'can_view_all' => 1,
            'can_view_own' => 1,
            'can_view_tenant' => 1,
            'can_create' => 1,
            'can_edit_all' => 1,
            'can_edit_own' => 1,
            'can_delete_all' => 0,
            'can_delete_own' => 1,
        ],
        
        // Editor - Posts
        [
            'role_id' => 3,
            'permission_id' => 5,
            'tenant_id' => 1,
            'resource_type' => 'posts',
            'can_view_all' => 0,
            'can_view_own' => 1,
            'can_view_tenant' => 0,
            'can_create' => 1,
            'can_edit_all' => 0,
            'can_edit_own' => 1,
            'can_delete_all' => 0,
            'can_delete_own' => 1,
        ],
        
        // Content Manager - Products
        [
            'role_id' => 2,
            'permission_id' => null,
            'tenant_id' => 1,
            'resource_type' => 'products',
            'can_view_all' => 0,
            'can_view_own' => 1,
            'can_view_tenant' => 1,
            'can_create' => 1,
            'can_edit_all' => 0,
            'can_edit_own' => 1,
            'can_delete_all' => 0,
            'can_delete_own' => 1,
        ],
    ];
}

// Test scenarios
function runTests() {
    echo "<h1>🧪 Admin Context Permission System Tests</h1>\n";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .test-section h2 { margin-top: 0; color: #333; }
        .success { color: green; }
        .failure { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .result { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #4CAF50; }
    </style>\n";
    
    // Test 1: Super Admin
    testSuperAdmin();
    
    // Test 2: Content Manager
    testContentManager();
    
    // Test 3: Editor
    testEditor();
    
    // Test 4: Helper Functions
    testHelperFunctions();
    
    // Test 5: Resource Permissions
    testResourcePermissions();
    
    echo "<h2 style='color: green;'>✅ All Tests Completed!</h2>\n";
}

function testSuperAdmin() {
    echo "<div class='test-section'>\n";
    echo "<h2>Test 1: Super Admin User</h2>\n";
    
    // Setup session for super admin
    $_SESSION['user_id'] = 1;
    $_SESSION['tenant_id'] = 1;
    $_SESSION['roles'] = ['super_admin'];
    $_SESSION['permissions'] = [
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'posts.view', 'posts.create', 'posts.edit', 'posts.delete'
    ];
    $_SESSION['resource_permissions'] = [
        'posts' => [
            'can_view_all' => true,
            'can_view_own' => true,
            'can_view_tenant' => true,
            'can_create' => true,
            'can_edit_all' => true,
            'can_edit_own' => true,
            'can_delete_all' => true,
            'can_delete_own' => true,
            'permission_key' => 'super_admin',
        ]
    ];
    $_SESSION['user'] = [
        'id' => 1,
        'username' => 'super_admin_user',
        'email' => 'super@example.com',
        'preferred_language' => 'en',
    ];
    
    // Require admin_context.php
    require_once 'admin_context.php';
    
    echo "<div class='result'>\n";
    echo "<strong>User Info:</strong><br>\n";
    echo "- User ID: " . admin_user_id() . "<br>\n";
    echo "- Username: " . admin_username() . "<br>\n";
    echo "- Email: " . admin_email() . "<br>\n";
    echo "- Tenant ID: " . admin_tenant_id() . "<br>\n";
    echo "- Language: " . admin_lang() . "<br>\n";
    echo "- Direction: " . admin_dir() . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Role Checks:</strong><br>\n";
    echo "- is_super_admin(): " . (is_super_admin() ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- has_role('super_admin'): " . (has_role('super_admin') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- Roles: " . implode(', ', admin_roles()) . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Permission Checks:</strong><br>\n";
    echo "- can('users.view'): " . (can('users.view') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can('users.create'): " . (can('users.create') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can('posts.delete'): " . (can('posts.delete') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- Total Permissions: " . count(admin_permissions()) . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Resource Permissions (Posts):</strong><br>\n";
    echo "- can_view_all('posts'): " . (can_view_all('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_create('posts'): " . (can_create('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_edit_all('posts'): " . (can_edit_all('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_delete_all('posts'): " . (can_delete_all('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_view_resource('posts', 5, 1): " . (can_view_resource('posts', 5, 1) ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

function testContentManager() {
    echo "<div class='test-section'>\n";
    echo "<h2>Test 2: Content Manager User</h2>\n";
    
    // Clear previous session
    session_destroy();
    session_start();
    
    // Setup session for content manager
    $_SESSION['user_id'] = 2;
    $_SESSION['tenant_id'] = 1;
    $_SESSION['roles'] = ['content_manager'];
    $_SESSION['permissions'] = ['posts.view', 'posts.create', 'posts.edit'];
    $_SESSION['resource_permissions'] = [
        'posts' => [
            'can_view_all' => true,
            'can_view_own' => true,
            'can_view_tenant' => true,
            'can_create' => true,
            'can_edit_all' => true,
            'can_edit_own' => true,
            'can_delete_all' => false,
            'can_delete_own' => true,
            'permission_key' => 'posts.manage',
        ],
        'products' => [
            'can_view_all' => false,
            'can_view_own' => true,
            'can_view_tenant' => true,
            'can_create' => true,
            'can_edit_all' => false,
            'can_edit_own' => true,
            'can_delete_all' => false,
            'can_delete_own' => true,
            'permission_key' => null,
        ]
    ];
    $_SESSION['user'] = [
        'id' => 2,
        'username' => 'content_manager',
        'email' => 'manager@example.com',
        'preferred_language' => 'en',
    ];
    
    // Re-initialize context
    unset($GLOBALS['ADMIN_UI']);
    require 'admin_context.php';
    
    echo "<div class='result'>\n";
    echo "<strong>User Info:</strong><br>\n";
    echo "- User ID: " . admin_user_id() . "<br>\n";
    echo "- Username: " . admin_username() . "<br>\n";
    echo "- is_super_admin(): " . (is_super_admin() ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Permission Checks:</strong><br>\n";
    echo "- can('posts.view'): " . (can('posts.view') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can('posts.create'): " . (can('posts.create') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can('posts.edit'): " . (can('posts.edit') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can('posts.delete'): " . (can('posts.delete') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can('users.create'): " . (can('users.create') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Resource Permissions (Posts):</strong><br>\n";
    echo "- can_view_all('posts'): " . (can_view_all('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_edit_all('posts'): " . (can_edit_all('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_delete_all('posts'): " . (can_delete_all('posts') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can_delete_own('posts'): " . (can_delete_own('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_delete_resource('posts', 2): " . (can_delete_resource('posts', 2) ? '✅ YES (Own)' : '❌ NO') . "<br>\n";
    echo "- can_delete_resource('posts', 5): " . (can_delete_resource('posts', 5) ? '✅ YES' : '❌ NO (Expected - Not Own)') . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Resource Permissions (Products):</strong><br>\n";
    echo "- can_view_all('products'): " . (can_view_all('products') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can_view_tenant('products'): " . (can_view_tenant('products') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_edit_own('products'): " . (can_edit_own('products') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_edit_all('products'): " . (can_edit_all('products') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

function testEditor() {
    echo "<div class='test-section'>\n";
    echo "<h2>Test 3: Editor User</h2>\n";
    
    // Clear previous session
    session_destroy();
    session_start();
    
    // Setup session for editor
    $_SESSION['user_id'] = 3;
    $_SESSION['tenant_id'] = 1;
    $_SESSION['roles'] = ['editor'];
    $_SESSION['permissions'] = ['posts.view', 'posts.create', 'posts.edit'];
    $_SESSION['resource_permissions'] = [
        'posts' => [
            'can_view_all' => false,
            'can_view_own' => true,
            'can_view_tenant' => false,
            'can_create' => true,
            'can_edit_all' => false,
            'can_edit_own' => true,
            'can_delete_all' => false,
            'can_delete_own' => true,
            'permission_key' => 'posts.edit',
        ]
    ];
    $_SESSION['user'] = [
        'id' => 3,
        'username' => 'editor_user',
        'email' => 'editor@example.com',
        'preferred_language' => 'ar',
    ];
    
    // Re-initialize context
    unset($GLOBALS['ADMIN_UI']);
    require 'admin_context.php';
    
    echo "<div class='result'>\n";
    echo "<strong>User Info:</strong><br>\n";
    echo "- User ID: " . admin_user_id() . "<br>\n";
    echo "- Username: " . admin_username() . "<br>\n";
    echo "- Language: " . admin_lang() . "<br>\n";
    echo "- Direction: " . admin_dir() . " (Should be RTL for Arabic)<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Resource Permissions (Posts):</strong><br>\n";
    echo "- can_view_all('posts'): " . (can_view_all('posts') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can_view_own('posts'): " . (can_view_own('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_view_tenant('posts'): " . (can_view_tenant('posts') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can_edit_own('posts'): " . (can_edit_own('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_edit_all('posts'): " . (can_edit_all('posts') ? '✅ YES' : '❌ NO (Expected)') . "<br>\n";
    echo "- can_edit_resource('posts', 3): " . (can_edit_resource('posts', 3) ? '✅ YES (Own)' : '❌ NO') . "<br>\n";
    echo "- can_edit_resource('posts', 5): " . (can_edit_resource('posts', 5) ? '✅ YES' : '❌ NO (Expected - Not Own)') . "<br>\n";
    echo "- can_view_resource('posts', 3, 1): " . (can_view_resource('posts', 3, 1) ? '✅ YES (Own)' : '❌ NO') . "<br>\n";
    echo "- can_view_resource('posts', 5, 1): " . (can_view_resource('posts', 5, 1) ? '✅ YES' : '❌ NO (Expected - Not Own)') . "<br>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

function testHelperFunctions() {
    echo "<div class='test-section'>\n";
    echo "<h2>Test 4: Helper Functions</h2>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>General Helpers:</strong><br>\n";
    echo "- is_admin_logged_in(): " . (is_admin_logged_in() ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- admin_user_id(): " . admin_user_id() . "<br>\n";
    echo "- admin_username(): " . admin_username() . "<br>\n";
    echo "- admin_email(): " . admin_email() . "<br>\n";
    echo "- admin_tenant_id(): " . admin_tenant_id() . "<br>\n";
    echo "- admin_lang(): " . admin_lang() . "<br>\n";
    echo "- admin_dir(): " . admin_dir() . "<br>\n";
    echo "- admin_csrf() length: " . strlen(admin_csrf()) . " chars<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Permission Helpers:</strong><br>\n";
    echo "- can_any(['posts.edit', 'posts.delete']): " . (can_any(['posts.edit', 'posts.delete']) ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- can_all(['posts.view', 'posts.create']): " . (can_all(['posts.view', 'posts.create']) ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "- has_any_resource_permission('posts'): " . (has_any_resource_permission('posts') ? '✅ YES' : '❌ NO') . "<br>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Context Data:</strong><br>\n";
    $context = admin_context();
    echo "- Context keys: " . implode(', ', array_keys($context)) . "<br>\n";
    $user = admin_user();
    echo "- User keys: " . implode(', ', array_keys($user)) . "<br>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

function testResourcePermissions() {
    echo "<div class='test-section'>\n";
    echo "<h2>Test 5: Resource Permissions Detailed</h2>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>Get Resource Permissions:</strong><br>\n";
    $postPerms = get_resource_permissions('posts');
    echo "<pre>" . print_r($postPerms, true) . "</pre>\n";
    echo "</div>\n";
    
    echo "<div class='result'>\n";
    echo "<strong>All Resource Permissions:</strong><br>\n";
    $allPerms = admin_resource_permissions();
    echo "- Resource types: " . implode(', ', array_keys($allPerms)) . "<br>\n";
    echo "<pre>" . print_r($allPerms, true) . "</pre>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

// Run all tests
runTests();
