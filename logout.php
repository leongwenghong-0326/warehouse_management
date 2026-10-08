<?php
/**
 * Logout Handler
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout_user();

set_flash('info', 'You have been successfully signed out.');
redirect('login.php');
