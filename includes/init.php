<?php
/**
 * Application Bootstrap & Initialization
 * Warehouse Management System
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Load application configuration
require_once __DIR__ . '/../config/config.php';

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database and core dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
