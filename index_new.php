<?php
// Front Controller - All requests route through here
session_start();

require_once __DIR__ . '/config.php';

use FastRoute\RouteCollector;
use App\Controllers\DashboardController;
use App\Controllers\TimecardController;
use App\Controllers\LeaveController;
use App\Controllers\LoanController;
use App\Controllers\PayslipController;
use App\Api\Controllers\EmployeeApiController;
use App\Api\Controllers\DashboardApiController;
use App\Api\Controllers\TimecardApiController;
use App\Api\Controllers\LeaveApiController;
use App\Api\Controllers\LoanApiController;

// Define routes
$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) use ($pdo, $schedulerPdo) {
    // Dashboard routes
    $r->addRoute('GET', '/', function() use ($pdo, $schedulerPdo) {
        header('Location: /ems/dashboard');
        exit;
    });
    
    $r->addRoute('GET', '/dashboard', function() use ($pdo, $schedulerPdo) {
        $controller = new DashboardController($pdo, $schedulerPdo);
        $controller->index();
    });
    
    // Timecard routes
    $r->addRoute('GET', '/timecard', function() use ($pdo, $schedulerPdo) {
        $controller = new TimecardController($pdo, $schedulerPdo);
        $controller->index();
    });
    
    // Leave routes
    $r->addRoute(['GET', 'POST'], '/leave', function() use ($pdo, $schedulerPdo) {
        $controller = new LeaveController($pdo, $schedulerPdo);
        $controller->index();
    });
    
    // Loan routes
    $r->addRoute(['GET', 'POST'], '/loan', function() use ($pdo, $schedulerPdo) {
        $controller = new LoanController($pdo);
        $controller->index();
    });
    
    // Payslip routes
    $r->addRoute('GET', '/payslip', function() use ($pdo, $schedulerPdo) {
        require_once 'src/Controllers/PayslipController.php';
        $controller = new PayslipController($pdo, $schedulerPdo);
        $controller->index();
    });

    // ==========================================
    // API ROUTES (v1)
    // ==========================================
    $r->addGroup('/api/v1', function (RouteCollector $r) use ($pdo, $schedulerPdo) {
        // Employees
        $r->addRoute('GET', '/employees', function() use ($pdo, $schedulerPdo) {
            $controller = new EmployeeApiController($pdo, $schedulerPdo);
            $controller->index();
        });
        
        $r->addRoute('GET', '/employees/{id:\d+}', function($vars) use ($pdo, $schedulerPdo) {
            $controller = new EmployeeApiController($pdo, $schedulerPdo);
            $controller->show($vars);
        });

        // Dashboard API
        $r->addRoute('GET', '/dashboard/stats', function() use ($pdo, $schedulerPdo) {
            $controller = new DashboardApiController($pdo, $schedulerPdo);
            $controller->stats();
        });

        // Timecard API
        $r->addRoute('GET', '/timecard', function() use ($pdo, $schedulerPdo) {
            $controller = new TimecardApiController($pdo, $schedulerPdo);
            $controller->index();
        });

        // Leave API
        $r->addRoute('GET', '/leave', function() use ($pdo, $schedulerPdo) {
            $controller = new LeaveApiController($pdo, $schedulerPdo);
            $controller->index();
        });
    });
});

// Fetch method and URI
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

// Remove /ems prefix if present (adjust based on your setup)
$uri = str_replace('/ems', '', $uri);
if (empty($uri)) $uri = '/';

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        break;
        
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo '<h1>405 Method Not Allowed</h1>';
        break;
        
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $handler($vars);
        break;
}
