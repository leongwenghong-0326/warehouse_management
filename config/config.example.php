<?php
/**
 * Application Configuration Template (Example)
 * Warehouse Management System
 * 
 * Duplicate this file to config/config.php and update your credentials.
 */

declare(strict_types=1);

// Application Settings
define('APP_NAME', 'Warehouse Management System');
define('APP_VERSION', '1.0.0');

// Environment: 'development' or 'production'
define('APP_ENV', 'development');

// Determine Base Path automatically for XAMPP / subfolder or root domain
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$subdirs = ['/admin', '/user', '/api', '/config', '/includes', '/database'];
$cleanDir = $scriptDir;
foreach ($subdirs as $subdir) {
    if (str_ends_with($cleanDir, $subdir)) {
        $cleanDir = substr($cleanDir, 0, -strlen($subdir));
        break;
    }
}
define('BASE_PATH', rtrim($cleanDir, '/'));

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'warehouse_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_samesite', 'Lax');
}
