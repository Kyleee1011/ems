<?php
session_start();

// ============================================
// 1. BOOTSTRAPPING MVC
// ============================================
require_once 'config.php'; 

// Autoloader (Manual)
require_once 'src/Models/Loan.php';
require_once 'src/Controllers/LoanController.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;
use App\Controllers\LoanController;

// Dispatch
$controller = new LoanController($pdo);
$controller->index();