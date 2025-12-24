<?php
session_start();

// ============================================
// 1. BOOTSTRAPPING MVC
// ============================================
require_once 'config.php'; 

// Autoloader (Manual)
require_once 'src/Utils/AppHelpers.php';
require_once 'src/Models/DashboardModel.php';
require_once 'src/Models/PayrollConfigModel.php';
require_once 'src/Models/ScheduleBatchModel.php';
require_once 'src/Models/LeaveModel.php';
require_once 'src/Models/OvertimeModel.php';
require_once 'src/Controllers/DashboardController.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;
use App\Models\DashboardModel;
use App\Models\PayrollConfigModel;
use App\Models\ScheduleBatchModel;
use App\Models\LeaveModel;
use App\Models\OvertimeModel;
use App\Controllers\DashboardController;

// Dispatch
$controller = new DashboardController($pdo, $schedulerPdo); // Pass both connections
$controller->index();