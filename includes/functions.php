<?php
/**
 * Helper Functions
 * Warehouse Management System
 */

declare(strict_types=1);

/**
 * Escape HTML output for XSS protection
 */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Build absolute URL path taking BASE_PATH into account
 */
function url(string $path = ''): string
{
    $cleanPath = ltrim($path, '/');
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    if ($base === '') {
        return '/' . $cleanPath;
    }
    return $base . ($cleanPath !== '' ? '/' . $cleanPath : '');
}

/**
 * Redirect safely to another page
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Generate or get current CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf_token(?string $token = null): bool
{
    $tokenToVerify = $token ?? $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($tokenToVerify)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$tokenToVerify);
}

/**
 * Validate CSRF token or abort with 403 response
 */
function check_csrf_or_abort(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>'
                . '<div style="max-width:500px;margin:50px auto;padding:20px;font-family:sans-serif;background:#fff1f2;border:1px solid #fda4af;border-radius:8px;color:#9f1239;">'
                . '<h3>Invalid CSRF Token</h3>'
                . '<p>Your session may have expired or the security token is invalid. Please refresh the page and try again.</p>'
                . '<p><a href="javascript:history.back()" style="color:#e11d48;">Go Back</a></p>'
                . '</div></body></html>');
        }
    }
}

/**
 * Set a flash notification message (e.g., success, error, warning, info)
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message markup if set
 */
function display_flash(): string
{
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $type = e($flash['type']);
    $message = e($flash['message']);

    return "<div class=\"alert alert-{$type}\" role=\"alert\">"
        . "<span class=\"alert-icon\"></span>"
        . "<span class=\"alert-text\">{$message}</span>"
        . "<button type=\"button\" class=\"alert-close\" onclick=\"this.parentElement.remove();\">&times;</button>"
        . "</div>";
}

/**
 * Format date for display
 */
function format_date(?string $date, string $format = 'Y-m-d H:i'): string
{
    if (!$date) {
        return '-';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Send JSON response
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
