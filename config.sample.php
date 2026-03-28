<?php
/**
 * Application Configuration File
 * 
 * This file bootstraps the application:
 * - Loads environment variables
 * - Sets up error handling
 * - Establishes database connection
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Register global error handler (Monolog + custom error pages)
use App\Helpers\ErrorHandler;
$errorHandler = new ErrorHandler();
$errorHandler->register();

// Set timezone
date_default_timezone_set('Asia/Manila');

// Database Configuration from environment variables
$dbServer = getenv('DB_SERVER') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'ems';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

// Connection Options
$dbOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

/**
 * Create Database Connection
 */
try {
    // Main Database Connection
    $dsn = "mysql:host={$dbServer};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, $dbOptions);
    
} catch(PDOException $e) {
    // Error handler will catch and log this
    throw new PDOException("Database connection failed: " . $e->getMessage());
}

// Application is now ready
// $pdo is available globally