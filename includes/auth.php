<?php
/**
 * Authentication and Authorization Handler
 * Warehouse Management System
 */

declare(strict_types=1);

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user details from session
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'        => (int)$_SESSION['user_id'],
        'username'  => (string)($_SESSION['username'] ?? ''),
        'full_name' => (string)($_SESSION['full_name'] ?? ''),
        'role'      => (string)($_SESSION['role'] ?? 'user'),
    ];
}

/**
 * Check if the authenticated user has a specific role
 */
function has_role(string $role): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    return strtolower($user['role']) === strtolower($role);
}

/**
 * Require authentication or redirect to login
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        redirect('login.php');
    }
}

/**
 * Enforce role requirement (e.g. admin)
 */
function require_role(string $role): void
{
    require_login();

    if (!has_role($role)) {
        http_response_code(403);
        $user = current_user();
        $fallback = ($user && $user['role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
        
        die('<!DOCTYPE html><html><head><title>403 Access Denied</title><link rel="stylesheet" href="' . url('assets/css/style.css') . '"></head>'
            . '<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f3f4f6;font-family:sans-serif;">'
            . '<div style="background:#fff;padding:32px;border-radius:12px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);max-width:480px;text-align:center;">'
            . '<div style="font-size:48px;color:#ef4444;margin-bottom:12px;">&#9888;</div>'
            . '<h2 style="margin:0 0 8px;color:#111827;">Access Denied</h2>'
            . '<p style="color:#6b7280;margin:0 0 24px;line-height:1.5;">You do not have permission to view this resource. Your role is restricted.</p>'
            . '<a href="' . url($fallback) . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Return to Dashboard</a>'
            . '</div></body></html>');
    }
}

/**
 * Log user into session securely
 */
function login_user(array $user): void
{
    // Regenerate session id to protect against session fixation attacks
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = (string)$user['username'];
    $_SESSION['full_name'] = (string)$user['full_name'];
    $_SESSION['role']      = (string)$user['role'];
}

/**
 * Terminate user session securely
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
