<?php
/**
 * Root Entry Point
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user = current_user();
if ($user && $user['role'] === 'admin') {
    redirect('admin/dashboard.php');
} else {
    redirect('user/dashboard.php');
}
