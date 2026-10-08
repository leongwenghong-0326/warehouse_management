<?php
/**
 * User Login Page
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = current_user()['role'] ?? 'user';
    redirect($role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$error = '';
$usernameVal = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // CSRF verification
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $usernameVal = $username;

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $db = get_db();
                $stmt = $db->prepare('SELECT id, full_name, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                    // Successful login
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('user/dashboard.php');
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon-wrap">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <h2 class="auth-title">Warehouse Portal</h2>
            <p class="auth-subtitle">Sign in to access your inventory workspace</p>
        </div>

        <div class="auth-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= url('login.php') ?>" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        value="<?= e($usernameVal) ?>" 
                        required 
                        autofocus
                        placeholder="Enter your username"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        required 
                        placeholder="Enter your password"
                    >
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px;">
                        Sign In
                    </button>
                </div>
            </form>

            <div class="demo-credentials-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h4 style="margin: 0;">Development Demo Accounts</h4>
                    <span style="font-size: 11px; color: var(--primary); font-weight: 600;">(Click to Auto Fill)</span>
                </div>
                <div class="demo-creds-item" data-fill-user="admin" data-fill-pass="admin123" title="Click to auto-fill Admin credentials">
                    <span><strong>Admin:</strong> admin</span>
                    <span>password: <code>admin123</code></span>
                </div>
                <div class="demo-creds-item" data-fill-user="user" data-fill-pass="user123" title="Click to auto-fill User credentials">
                    <span><strong>User:</strong> user</span>
                    <span>password: <code>user123</code></span>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.demo-creds-item').forEach(item => {
            item.addEventListener('click', () => {
                const usernameInput = document.getElementById('username');
                const passwordInput = document.getElementById('password');
                if (usernameInput && passwordInput) {
                    usernameInput.value = item.dataset.fillUser || '';
                    passwordInput.value = item.dataset.fillPass || '';
                    passwordInput.focus();
                }
            });
        });
    });
    </script>
</body>
</html>
