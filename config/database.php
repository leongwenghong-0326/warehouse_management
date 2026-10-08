<?php
/**
 * Database Connection Handler
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get active PDO instance
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // If in development, log or show helpful instruction
                error_log("Database connection error: " . $e->getMessage());
                if (defined('APP_ENV') && APP_ENV === 'development') {
                    die("<div style='font-family:sans-serif;padding:24px;border:1px solid #f87171;background:#fef2f2;border-radius:8px;max-width:640px;margin:50px auto;color:#991b1b;'>"
                        . "<h3 style='margin-top:0;'>Database Connection Error</h3>"
                        . "<p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>"
                        . "<p style='color:#4b5563;font-size:14px;'>Please ensure MySQL is running in XAMPP and database <code>warehouse_management</code> exists. You can import <code>database/schema.sql</code> into phpMyAdmin.</p>"
                        . "</div>");
                }
                die("Unable to connect to the database. Please try again later.");
            }
        }

        return self::$instance;
    }
}

/**
 * Helper function to retrieve database connection
 */
function get_db(): PDO
{
    return Database::getConnection();
}
