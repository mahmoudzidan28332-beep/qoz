<?php
// htdocs/api/shared/helpers/auth_helper.php
// Basic auth helper functions

function get_authenticated_user_with_permissions($pdo) {
    // استخدم RBAC
    if (class_exists('RBAC')) {
        $user = RBAC::get_current_user();
        if ($user) {
            RBAC::load_permissions_for_user($user['id']);
            $user['permissions'] = RBAC::get_user_permissions();
            $user['roles'] = RBAC::get_user_roles();
            return $user;
        }
    }
    return null;
}

function authenticate_user(string $username, string $password, $pdo) {
    require_once __DIR__ . '/../core/repositories/AuthRepository.php';
    $repo = new AuthRepository($pdo);
    $user = $repo->findUserByUsername($username);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user;
        RBAC::load_permissions_for_user($user['id']);
        return true;
    }
    return false;
}

function logout_user() {
    // استخدم RBAC
    if (class_exists('RBAC')) {
        RBAC::logout();
    } else {
        session_destroy();
    }
}

function is_user_logged_in(): bool {
    if (class_exists('RBAC')) {
        return RBAC::get_current_user() !== null;
    }
    return isset($_SESSION['user_id']);
}