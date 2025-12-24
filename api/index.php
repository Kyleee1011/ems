<?php
session_start();

require_once __DIR__ . '/../config.php';

// Manual Autoloader for API classes (since we don't have Composer yet)
spl_autoload_register(function ($class) {
    $prefix = 'App\\Api\\';
    $base_dir = __DIR__ . '/../src/Api/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        // Fallback to standard App models
        $prefixOld = 'App\\';
        $base_dirOld = __DIR__ . '/../src/';
        if (strncmp($prefixOld, $class, strlen($prefixOld)) === 0) {
             $relative_class = substr($class, strlen($prefixOld));
             $file = $base_dirOld . str_replace('\\', '/', $relative_class) . '.php';
             if (file_exists($file)) require $file;
        }
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Api\Router;
use App\Api\Controllers\TimecardApiController;
use App\Api\Controllers\LeaveApiController;
use App\Api\Controllers\LoanApiController;
use App\Api\Controllers\DashboardApiController;

// Init Router
$router = new Router($pdo, $schedulerPdo);

// =================================
// REGISTER ROUTES
// =================================

// Timecard
if (class_exists('App\Api\Controllers\TimecardApiController')) {
    $router->get('/timecard', [TimecardApiController::class, 'index']);
    $router->post('/timecard/log', [TimecardApiController::class, 'punch']);
}

// Leave
if (class_exists('App\Api\Controllers\LeaveApiController')) {
    $router->get('/leave', [LeaveApiController::class, 'index']);
    $router->post('/leave/apply', [LeaveApiController::class, 'apply']);
}

// Loan
if (class_exists('App\Api\Controllers\LoanApiController')) {
    $router->get('/loan', [LoanApiController::class, 'index']);
}

// Dashboard
if (class_exists('App\Api\Controllers\DashboardApiController')) {
    $router->get('/dashboard/stats', [DashboardApiController::class, 'stats']);
    $router->get('/dashboard/schedules', [DashboardApiController::class, 'getSchedules']);
    $router->get('/dashboard/batches/pending', [DashboardApiController::class, 'getPendingBatches']);
    $router->post('/dashboard/batch/update', [DashboardApiController::class, 'updateBatchStatus']);
    $router->get('/dashboard/employees', [DashboardApiController::class, 'getEmployeesByDept']);
}

// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
