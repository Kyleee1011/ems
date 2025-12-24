<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .check-item .icon {
            font-size: 24px;
            margin-right: 15px;
            width: 30px;
        }
        
        .check-item .text {
            flex: 1;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .config-table th,
        .config-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .config-table th {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>

<?php
require_once 'config.php';

// Test Results
$tests = [];

// Test 1: PHP Version
$phpVersion = phpversion();
$tests['php_version'] = [
    'name' => 'PHP Version',
    'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'success' : 'error',
    'message' => "PHP $phpVersion " . (version_compare($phpVersion, '7.4.0', '>=') ? '(Compatible)' : '(Requires 7.4+)')
];

// Test 2: PDO Extension
$tests['pdo'] = [
    'name' => 'PDO Extension',
    'status' => extension_loaded('PDO') ? 'success' : 'error',
    'message' => extension_loaded('PDO') ? 'PDO is enabled' : 'PDO is NOT enabled - Check php.ini'
];

// Test 3: PDO SQL Server Driver
$tests['pdo_sqlsrv'] = [
    'name' => 'PDO SQL Server Driver',
    'status' => extension_loaded('pdo_sqlsrv') ? 'success' : 'error',
    'message' => extension_loaded('pdo_sqlsrv') ? 'SQL Server driver is enabled' : 'SQL Server driver is NOT enabled - Check php.ini'
];

// Test 4: Database Connection
try {
    $stmt = $pdo->query("SELECT @@VERSION AS version");
    $result = $stmt->fetch();
    $tests['db_connection'] = [
        'name' => 'Database Connection',
        'status' => 'success',
        'message' => 'Connected successfully to SQL Server',
        'details' => $result['version']
    ];
} catch(Exception $e) {
    $tests['db_connection'] = [
        'name' => 'Database Connection',
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Test 5: Check Tables
$tableCheck = ['status' => 'pending', 'message' => 'Skipped - Connection failed'];
if ($tests['db_connection']['status'] === 'success') {
    try {
        $conn = $pdo;
        $tables = ['Employees', 'Departments', 'Supervisors', 'TrainingCertifications', 'EmployeeStatusHistory', 'Users'];
        $existingTables = [];
        
        foreach ($tables as $table) {
            $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$table'");
            if ($stmt->fetchColumn() > 0) {
                $existingTables[] = $table;
            }
        }
        
        if (count($existingTables) === count($tables)) {
            $tableCheck = [
                'status' => 'success',
                'message' => 'All required tables exist (' . count($tables) . '/' . count($tables) . ')'
            ];
        } else {
            $tableCheck = [
                'status' => 'error',
                'message' => 'Missing tables: ' . count($existingTables) . '/' . count($tables) . ' found'
            ];
        }
    } catch(Exception $e) {
        $tableCheck = [
            'status' => 'error',
            'message' => 'Error checking tables: ' . $e->getMessage()
        ];
    }
}
$tests['tables'] = array_merge(['name' => 'Database Tables'], $tableCheck);

// Test 6: Check View
$viewCheck = ['status' => 'pending', 'message' => 'Skipped - Connection failed'];
if ($tests['db_connection']['status'] === 'success') {
    try {
        $conn = $pdo;
        $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_NAME = 'vw_EmployeeList'");
        if ($stmt->fetchColumn() > 0) {
            $viewCheck = [
                'status' => 'success',
                'message' => 'View vw_EmployeeList exists'
            ];
        } else {
            $viewCheck = [
                'status' => 'error',
                'message' => 'View vw_EmployeeList not found'
            ];
        }
    } catch(Exception $e) {
        $viewCheck = [
            'status' => 'error',
            'message' => 'Error checking view: ' . $e->getMessage()
        ];
    }
}
$tests['view'] = array_merge(['name' => 'Database View'], $viewCheck);

// Overall Status
$allSuccess = true;
foreach ($tests as $test) {
    if ($test['status'] !== 'success') {
        $allSuccess = false;
        break;
    }
}
?>

<div class="container">
    <h1>🔧 Database Connection Test</h1>
    <p class="subtitle">Employee Management System - Setup Verification</p>
    
    <?php if ($allSuccess): ?>
        <div class="success">
            <strong>✅ All tests passed!</strong> Your system is ready to use.
        </div>
    <?php else: ?>
        <div class="error">
            <strong>⚠️ Some tests failed.</strong> Please fix the issues below before using the system.
        </div>
    <?php endif; ?>
    
    <div class="test-section">
        <h3 style="margin-bottom: 15px; color: #667eea;">System Requirements</h3>
        
        <?php foreach ($tests as $key => $test): ?>
            <div class="check-item">
                <div class="icon">
                    <?php if ($test['status'] === 'success'): ?>
                        ✅
                    <?php elseif ($test['status'] === 'error'): ?>
                        ❌
                    <?php else: ?>
                        ⏸️
                    <?php endif; ?>
                </div>
                <div class="text">
                    <strong><?php echo $test['name']; ?></strong><br>
                    <small><?php echo $test['message']; ?></small>
                    <?php if (isset($test['details'])): ?>
                        <pre style="font-size: 0.8em; margin-top: 5px; padding: 8px;"><?php echo htmlspecialchars($test['details']); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="test-section">
        <h3 style="margin-bottom: 15px; color: #667eea;">Current Configuration</h3>
        <table class="config-table">
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Server</td>
                <td><?php echo htmlspecialchars(DB_SERVER); ?></td>
            </tr>
            <tr>
                <td>Database</td>
                <td><?php echo htmlspecialchars(DB_NAME); ?></td>
            </tr>
            <tr>
                <td>Username</td>
                <td><?php echo htmlspecialchars(DB_USER); ?></td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <td>PDO Drivers</td>
                <td><?php echo implode(', ', PDO::getAvailableDrivers()); ?></td>
            </tr>
        </table>
    </div>
    
    <?php if (!$allSuccess): ?>
        <div class="info">
            <strong>💡 Troubleshooting Tips:</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li>If PDO or SQL Server driver is missing, enable them in php.ini</li>
                <li>Download drivers from: <a href="https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server" target="_blank">Microsoft</a></li>
                <li>Check that SQL Server is running</li>
                <li>Verify database credentials in config.php</li>
                <li>Run the database schema SQL script if tables are missing</li>
                <li>For named instances, use format: localhost\SQLEXPRESS</li>
            </ul>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="employee.php" class="btn">
            <?php echo $allSuccess ? '🚀 Go to Employee Management System' : '🔄 Retry Test'; ?>
        </a>
    </div>
</div>

</body>
</html>
