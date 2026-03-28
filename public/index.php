<?php
// public/index.php

// Set the base path and URL of the application
// Set the base path and URL of the application
define('BASE_PATH', dirname(__DIR__));

// Autoload dependencies and load application configuration
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config.php';

// Start the session
require_once dirname(__DIR__) . '/config_session.php';

// Basic Error Handling for now
ini_set('display_errors', 1);
error_reporting(E_ALL);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Ensure no trailing slash unless it's just /
if ($scriptDir !== '/' && substr($scriptDir, -1) === '/') {
    $scriptDir = rtrim($scriptDir, '/');
}
define('BASE_URL', $scriptDir);

// Initialize the router
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Redirect root to home
    $r->addRoute('GET', '/', function() {
        header("Location: " . BASE_URL . "/home");
        exit();
    });

    // Home page
    $r->addRoute('GET', '/home', ['App\Controllers\HomeController', 'show']);
    $r->addRoute('POST', '/home/post', ['App\Controllers\HomeController', 'createPost']);
    $r->addRoute('POST', '/home/like', ['App\Controllers\HomeController', 'likePost']); // Assuming like logic might be needed too, but checking HomeController later.
    $r->addRoute('POST', '/home/holiday', ['App\Controllers\HomeController', 'holidayAction']); // HR calendar holiday management

    // Auth routes
    $r->addRoute('GET', '/login', ['App\Controllers\AuthController', 'showLoginForm']);
    $r->addRoute('POST', '/login', ['App\Controllers\AuthController', 'login']);
    $r->addRoute('GET', '/logout', function() {
require_once dirname(__DIR__) . '/session_config.php';
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/ems1');
        header("Location: " . BASE_URL . "/login");
        exit();
    });

    // Dashboard routes
    $r->addRoute('GET', '/dashboard', ['App\Controllers\DashboardController', 'index']);
    $r->addRoute('POST', '/dashboard', ['App\Controllers\DashboardController', 'handlePost']);
    $r->addRoute('GET', '/ceodashboard', ['App\Controllers\DashboardController', 'ceoIndex']);
    $r->addRoute('GET', '/analytics', ['App\Controllers\DashboardController', 'analytics']);

    // Employee routes
    $r->addRoute(['GET', 'POST'], '/employee', ['App\Controllers\EmployeeController', 'index']);

    // Leave routes
    $r->addRoute(['GET', 'POST'], '/leave', ['App\Controllers\LeaveController', 'index']);
    $r->addRoute(['GET', 'POST'], '/changesched', ['App\Controllers\LeaveController', 'changeSchedule']);
    $r->addRoute(['GET', 'POST'], '/overtime', ['App\Controllers\LeaveController', 'overtime']);

    // Loan routes
    $r->addRoute(['GET', 'POST'], '/loan', ['App\Controllers\LoanController', 'index']);

    // Payslip routes
    $r->addRoute(['GET', 'POST'], '/payslip', ['App\Controllers\PayslipController', 'index']);

    // Payroll Calculator route
    $r->addRoute('GET', '/payroll-calculator', ['App\Controllers\PayrollCalculatorController', 'index']);

    // Allowance Management route
    $r->addRoute(['GET', 'POST'], '/allowances', ['App\Controllers\AllowanceController', 'index']);

    // Cutoff Setup route
    $r->addRoute(['GET', 'POST'], '/cutoff-setup', ['App\Controllers\CutoffController', 'index']);

    // Timecard routes
    $r->addRoute(['GET', 'POST'], '/timecard', ['App\Controllers\TimecardController', 'index']);
    $r->addRoute('POST', '/timecard/clock', ['App\Controllers\TimecardController', 'clock']); 
    
    // Schedule routes
    $r->addRoute('GET', '/schedule', ['App\Controllers\ScheduleController', 'index']);
    $r->addRoute(['GET', 'POST'], '/schedule/api', ['App\Controllers\ScheduleController', 'handleApi']);

    // Schedule routes (Using DashboardController or specific ScheduleController if strictly needed, but dashboard seems to handle global schedule)
    //$r->addRoute(['GET', 'POST'], '/schedule', ['App\Controllers\DashboardController', 'index']); // Mapping to dashboard for now as schedule is part of it usually, or create ScheduleController later if needed.
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
// Adjust URI for subdirectory if necessary
$uri = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

// Remove the script directory from the URI to get the path relative to the app root
if (strpos($uri, BASE_URL) === 0) {
    $uri = substr($uri, strlen(BASE_URL));
}

// Remove /index.php if it exists in the remaining URI
if (strpos($uri, '/index.php') === 0) {
    $uri = substr($uri, 10);
}

// Ensure URI starts with /
if ($uri === '' || $uri[0] !== '/') {
    $uri = '/' . $uri;
}

// If URI is empty, default to a specific route
if (empty($uri)) {
    $uri = '/'; 
}


$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '404 Not Found';
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        echo '405 Method Not Allowed';
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        
        // Ensure PDO is available
        global $pdo;

        if (is_array($handler) && class_exists($handler[0]) && method_exists($handler[0], $handler[1])) {
            // Pass $pdo to the controller constructor
            $controller = new $handler[0]($pdo);
            $method = $handler[1];
            $controller->$method($vars);
        } elseif (is_callable($handler)) {
            call_user_func($handler, $vars);
        } else {
            http_response_code(500);
            echo '500 Internal Server Error - Invalid handler';
        }
        break;
}



