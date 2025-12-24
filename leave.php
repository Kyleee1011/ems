<?php
session_start();

// ============================================
// 1. BOOTSTRAPPING MVC
// ============================================
require_once 'config.php'; 

// Autoloader (Manual)
require_once 'src/Models/Leave.php';
require_once 'src/Controllers/LeaveController.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;
use App\Controllers\LeaveController;

// Dispatch
$controller = new LeaveController($pdo, $schedulerPdo);
$controller->index();