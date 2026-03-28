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
require_once __DIR__ . '/src/Helpers/UrlHelper.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate required environment variables
$dotenv->required(['DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS']);

// Register global error handler (Monolog + custom error pages)
use App\Helpers\ErrorHandler;
$errorHandler = new ErrorHandler();
$errorHandler->register();

// Set timezone
date_default_timezone_set('Asia/Manila');

// Database Configuration from environment variables
$dbServer = $_ENV['DB_SERVER'] ?? getenv('DB_SERVER');
$dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
$dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER');
$dbPass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');

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
