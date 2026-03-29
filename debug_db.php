<?php
$dbServer = '192.168.21.55';
$dbName = 'ems';
$dbUser = 'dev';
$dbPass = 'Azzurro2025';

$dsn = "mysql:host={$dbServer};dbname={$dbName};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query('DESCRIBE payroll_sss_table');
    echo "Columns in payroll_sss_table:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    echo "\nData in payroll_sss_table (first 2):\n";
    $stmt = $pdo->query('SELECT * FROM payroll_sss_table LIMIT 2');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

    echo "\nColumns in payroll_philhealth_table:\n";
    $stmt = $pdo->query('DESCRIBE payroll_philhealth_table');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    echo "\nColumns in payroll_pagibig_table:\n";
    $stmt = $pdo->query('DESCRIBE payroll_pagibig_table');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
