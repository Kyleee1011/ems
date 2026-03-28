<?php
namespace App\Controllers;

use PDO;

class PayrollCalculatorController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . baseUrl('login'));
            exit;
        }

        // Fetch lookup tables and embed as JSON for client-side calculation
        $sssTable = $this->pdo->query("SELECT min_salary, max_salary, ee_share FROM payroll_sss_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
        $philhealthTable = $this->pdo->query("SELECT rate, min_salary, max_salary FROM payroll_philhealth_table LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $pagibigTable = $this->pdo->query("SELECT fixed_amt FROM payroll_pagibig_table LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $taxTable = $this->pdo->query("SELECT min_salary, max_salary, base_tax, excess_rate FROM payroll_tax_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch OT and holiday multipliers from payroll_general_settings
        $settingsStmt = $this->pdo->query("SELECT setting_key, setting_value FROM payroll_general_settings");
        $settings = [];
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        extract([
            'sssTable' => $sssTable,
            'philhealthTable' => $philhealthTable,
            'pagibigTable' => $pagibigTable,
            'taxTable' => $taxTable,
            'settings' => $settings,
        ]);

        require __DIR__ . '/../Views/payroll_calculator_view.php';
    }
}
