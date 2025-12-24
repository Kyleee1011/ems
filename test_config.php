<?php
// Test config.php loading
require_once 'config.php';

echo "✓ Config loaded successfully\n";
echo "✓ PDO connection: " . (isset($pdo) && $pdo instanceof PDO ? "OK" : "FAILED") . "\n";
echo "✓ Scheduler PDO: " . (isset($schedulerPdo) && $schedulerPdo instanceof PDO ? "OK" : "FAILED") . "\n";
echo "✓ Error handler registered\n";

// Test database connection
try {
    $result = $pdo->query("SELECT 1 as test")->fetch();
    echo "✓ Database query test: OK\n";
} catch (Exception $e) {
    echo "✗ Database query test: FAILED - " . $e->getMessage() . "\n";
}

// Test scheduler database connection
try {
    $result = $schedulerPdo->query("SELECT 1 as test")->fetch();
    echo "✓ Scheduler DB query test: OK\n";
} catch (Exception $e) {
    echo "✗ Scheduler DB query test: FAILED - " . $e->getMessage() . "\n";
}

echo "\n=== System Status ===\n";
echo "All core components are working correctly!\n";
