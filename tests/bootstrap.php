<?php
/**
 * PHPUnit Bootstrap File
 * Sets up the testing environment before running tests
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Override environment for testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';

// Create test database connections (mock or test database)
// For now, we'll set up the connection but tests should mock PDO
try {
    // You can set up a test database connection here
    // For unit tests, we'll mock PDO instead of using real connections
    
    // Example test database setup (commented out - use mocking instead):
    /*
    $testPdo = new PDO(
        "sqlsrv:Server=" . getenv('DB_SERVER') . ";Database=EMS_Test",
        getenv('DB_USER'),
        getenv('DB_PASS')
    );
    $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    */
    
} catch (Exception $e) {
    // In testing, we should use mocks, so this is okay to fail
    // echo "Note: Using mocked database connections for unit tests\n";
}

// Helper function to create mock PDO for tests
function createMockPdo() {
    return new class extends PDO {
        public function __construct() {
            // Empty constructor - no actual connection
        }
        
        public function prepare($statement, $options = []) {
            return new class {
                public function execute($params = []) { return true; }
                public function fetch($mode = PDO::FETCH_BOTH) { return []; }
                public function fetchAll($mode = PDO::FETCH_BOTH) { return []; }
                public function fetchColumn($column = 0) { return 0; }
            };
        }
        
        public function query($statement) {
            return $this->prepare($statement);
        }
    };
}

// Set up any global test helpers or fixtures here
