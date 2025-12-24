<?php
// Simple Test Runner
// Scans for *Test.php files in tests/Unit/ and executes methods starting with 'test'

require_once __DIR__ . '/../config.php';

// Mock/Stub if needed, but we rely on Integration mostly for this legacy app
echo "Running Tests...\n\n";

$dir = __DIR__ . '/Unit';
$files = glob($dir . '/*Test.php');

$passed = 0;
$failed = 0;

foreach ($files as $file) {
    require_once $file;
    $className = 'Tests\\Unit\\' . basename($file, '.php');
    
    if (class_exists($className)) {
        $testClass = new $className($pdo, $schedulerPdo);
        $methods = get_class_methods($testClass);
        
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                try {
                    $testClass->$method();
                    echo "✔ $className::$method passed.\n";
                    $passed++;
                } catch (Exception $e) {
                    echo "✘ $className::$method FAILED: " . $e->getMessage() . "\n";
                    $failed++;
                } catch (Throwable $e) {
                    echo "✘ $className::$method ERROR: " . $e->getMessage() . "\n";
                    $failed++;
                }
            }
        }
    }
}

echo "\nResult: $passed Passed, $failed Failed.\n";
