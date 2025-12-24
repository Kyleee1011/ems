<?php
require_once 'config.php';

echo "Triggering test error...\n";

// 1. Log manually
$logger = new \App\Helpers\Logger();
$logger->info("This is a test info log.");

// 2. Trigger Exception (should be caught by Global Handler)
throw new Exception("Test Exception for ErrorHandler verification.");
