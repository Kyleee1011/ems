<?php
session_start();

// ============================================
// 1. INCLUDES & INTEGRITY CHECKS (Kept for Legacy/Helper support)
// ============================================
$required_files = [
    'payslip/get_sss.php',
    'payslip/get_philhealth.php',
    'payslip/get_pagibig.php',
    'payslip/get_tax.php',
    'payslip/get_nightdiff.php',
    'payslip/get_overtime.php',
    'payslip/get_undertime.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        $missing_files[] = $file;
    }
}

if(file_exists('payslip/get_holiday.php')) {
    require_once 'payslip/get_holiday.php';
} else {
    function getHolidayType($conn, $date) { return 'REGULAR_DAY'; } 
}

if (!empty($missing_files)) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#ffebeb;'>
            <strong>System Error:</strong> Missing files: " . implode(', ', $missing_files) . "
         </div>");
}

// ============================================
// 2. BOOTSTRAPPING MVC
// ============================================
require_once 'config.php'; // Provides $pdo (EMS) and $schedulerPdo (Scheduler)

// Autoloader (Manual since composer might not be fully set up for this namespace yet, or specific to this file)
// In a real scenario, standard composer autoload is preferred.
// Here we manually include classes if not using composer autoload. 
// Assuming the user might not have composer autoloader dumping purely for this yet, I'll `require` them.
require_once 'src/Models/Payslip.php';
require_once 'src/Controllers/PayslipController.php';

use App\Controllers\PayslipController;

// Dispatch
$controller = new PayslipController($pdo, $schedulerPdo);
$controller->index();