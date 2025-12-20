<?php
/**
 * Database Configuration File
 *
 * Update these settings according to your SQL Server setup
 */

// SQL Server Configuration
define('DB_SERVER', 'your_server_address, 1433'); // or 'localhost\SQLEXPRESS' for named instance
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user'); // Your SQL Server username
define('DB_PASS', 'your_db_password'); // Your SQL Server password

// Connection Options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

/**
 * Get Database Connection
 *
 * @return PDO
 * @throws PDOException
 */
function getDBConnection() {
    try {
        $dsn = "sqlsrv:server=" . DB_SERVER . ";Database=" . DB_NAME;
        $conn = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        return $conn;
    } catch(PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        throw new PDOException("Could not connect to database. Please check your configuration.");
    }
}
?>
