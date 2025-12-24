<?php
session_start();

// ============================================
// 1. BOOTSTRAPPING MVC
// ============================================

// Legacy Helper support (since getHolidayType is global)
if(file_exists('payslip/get_holiday.php')) {
    require_once 'payslip/get_holiday.php';
}


require_once 'config.php'; 

// Autoloader (Manual)
require_once 'src/Utils/AppHelpers.php';
require_once 'src/Models/Timecard.php';
require_once 'src/Controllers/TimecardController.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;
use App\Controllers\TimecardController;

// Dispatch
$controller = new TimecardController($pdo, $schedulerPdo);
$controller->index();
