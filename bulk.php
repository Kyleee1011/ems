<?php
require_once 'config.php';

use App\Utils\AppHelpers;

// Initialize notification variables
$notification_message = '';
$notification_type = '';
$processed_data = [];

// Define the expected CSV headers (matching DB columns)
$csv_headers = [
    'emp_id', 'first_name', 'last_name', 'birth_date', 'gender', 'position', 
    'employee_status', 'dept_id', 'supervisor_id', 'hire_date', 'salary', 
    'email', 'phone', 'address'
];

// Mandatory fields for insertion
$mandatory_fields = ['emp_id', 'first_name', 'last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bulk_file'])) {
    $file = $_FILES['bulk_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $notification_message = "File upload failed with error code: " . $file['error'];
        $notification_type = 'error';
    } elseif ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel') {
        $notification_message = "Invalid file type. Please upload a CSV file.";
        $notification_type = 'error';
    } else {
        try {
            $conn = $pdo;
            $conn->beginTransaction();
            $file_handle = fopen($file['tmp_name'], 'r');
            
            if ($file_handle === false) {
                throw new Exception("Could not open the uploaded file.");
            }

            // 1. Read Header and map indices
            $header_row = fgetcsv($file_handle);
            if ($header_row === false || empty($header_row)) {
                throw new Exception("The CSV file is empty or header is missing.");
            }
            
            // Normalize header names by trimming and lowercasing
            $header_map = [];
            foreach ($header_row as $index => $col) {
                $header_map[strtolower(trim($col))] = $index;
            }

            // Verify mandatory fields exist in the header
            foreach ($mandatory_fields as $field) {
                if (!isset($header_map[$field])) {
                    throw new Exception("Mandatory column '{$field}' is missing from the CSV header.");
                }
            }
            
            $insert_count = 0;
            $skip_count = 0;
            $error_rows = [];
            $row_number = 1; // Start counting after header

            // 2. Prepare the SQL statement structure
            // Use REPLACE INTO logic if primary key conflict (emp_id) should update, 
            // but for HR, explicit INSERT/UPDATE is safer. We'll use INSERT IGNORE 
            // or better, a MERGE/IF EXISTS pattern for SQL Server, which is complex 
            // in raw PHP. For simplicity, we'll rely on the DB to throw an exception
            // on duplicate emp_id and catch it as a "skip/error."
            
            $sql = "INSERT INTO Employees (
                emp_id, first_name, last_name, birth_date, gender, position, 
                employee_status, dept_id, supervisor_id, hire_date, salary, 
                email, phone, address
            ) VALUES (
                :emp_id, :first_name, :last_name, :birth_date, :gender, :position, 
                :employee_status, :dept_id, :supervisor_id, :hire_date, :salary, 
                :email, :phone, :address
            )";
            $stmt = $conn->prepare($sql);

            // 3. Process data rows
            while (($row = fgetcsv($file_handle)) !== FALSE) {
                $row_number++;
                $data = [];
                $valid_row = true;
                
                // Extract data based on the mapped headers
                foreach ($csv_headers as $field) {
                    $index = $header_map[$field] ?? null;
                    $value = ($index !== null && isset($row[$index])) ? trim($row[$index]) : '';
                    
                    // Check mandatory fields
                    if (in_array($field, $mandatory_fields) && empty($value)) {
                        $error_rows[] = "Row {$row_number}: Mandatory field '{$field}' is empty.";
                        $valid_row = false;
                        break;
                    }

                    // Type casting/NULL handling
                    if ($value === '') {
                        $data[$field] = null;
                    } elseif (in_array($field, ['dept_id', 'supervisor_id'])) {
                        // Integer IDs: Ensure it's a valid integer or NULL
                        $data[$field] = filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : null;
                    } elseif ($field === 'salary') {
                        // Decimal Salary: Ensure it's a valid number or NULL
                        $data[$field] = is_numeric($value) ? (float)$value : null;
                    } elseif (in_array($field, ['birth_date', 'hire_date'])) {
                        // Date fields: Check simple date format (YYYY-MM-DD or similar)
                                                    $data[$field] = AppHelpers::cleanDate($value);                    } else {
                        $data[$field] = $value;
                    }
                }
                
                if (!$valid_row) {
                    $skip_count++;
                    continue;
                }

                // Default employee_status if missing
                if ($data['employee_status'] === null) {
                    $data['employee_status'] = 'Active';
                }

                try {
                    // Bind parameters from the processed data array
                    foreach ($data as $key => $value) {
                         $stmt->bindValue(":$key", $value);
                    }
                    $stmt->execute();
                    $insert_count++;
                    
                    // Add successful row data for display
                    $processed_data[] = [
                        'row' => $row_number, 
                        'status' => 'Success', 
                        'id' => $data['emp_id'], 
                        'name' => $data['first_name'] . ' ' . $data['last_name']
                    ];
                    
                } catch (PDOException $e) {
                    // Handle DB specific errors (e.g., duplicate emp_id, FK violation)
                    $error_message = $e->getMessage();
                    if (strpos($error_message, 'Violation of PRIMARY KEY constraint') !== false) {
                        $error_rows[] = "Row {$row_number} (ID: {$data['emp_id']}): Employee ID already exists. Skipped.";
                    } elseif (strpos($error_message, 'FOREIGN KEY constraint') !== false) {
                        $error_rows[] = "Row {$row_number} (ID: {$data['emp_id']}): Invalid Department ID or Supervisor ID (FK violation). Skipped.";
                    } else {
                        $error_rows[] = "Row {$row_number} (ID: {$data['emp_id']}): Database error: " . substr($error_message, 0, 100) . "...";
                    }
                    $skip_count++;
                    
                    // Add error row data for display
                    $processed_data[] = [
                        'row' => $row_number, 
                        'status' => 'Error', 
                        'id' => $data['emp_id'], 
                        'name' => $data['first_name'] . ' ' . $data['last_name'],
                        'details' => end($error_rows)
                    ];
                }
            }

            fclose($file_handle);
            $conn->commit();
            
            if ($insert_count > 0) {
                $notification_message = "Bulk upload complete! Inserted {$insert_count} employee(s). Skipped: {$skip_count}.";
                $notification_type = $skip_count > 0 ? 'warning' : 'success';
            } elseif ($skip_count > 0) {
                 $notification_message = "Bulk upload completed with errors. {$skip_count} row(s) skipped.";
                $notification_type = 'error';
            } else {
                $notification_message = "File was processed, but no valid records were found.";
                $notification_type = 'warning';
            }

        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $notification_message = "A critical error occurred: " . $e->getMessage();
            $notification_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Employee Upload</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9; /* Light Gray Background */
        }
        .bg-primary { background-color: #104060; } /* Deep Corporate Blue */
        .text-primary { color: #104060; }
        .bg-accent { background-color: #00A896; } /* Calming Teal */
        .hover\:bg-accent\/90:hover { background-color: #009886; }
    </style>
</head>
<body class="min-h-screen">

    <!-- Notification Message Area -->
    <div id="notification-area" class="fixed top-5 right-5 z-50 w-full max-w-sm">
        <?php if ($notification_message): ?>
            <div id="notification-box" class="p-4 rounded-lg shadow-xl mb-4 transition-opacity duration-300 
                <?php 
                    if ($notification_type === 'success') echo 'bg-green-500 text-white'; 
                    elseif ($notification_type === 'warning') echo 'bg-yellow-500 text-white'; 
                    else echo 'bg-red-500 text-white';
                ?>" 
                role="alert">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $notification_type === 'success' ? 'M5 13l4 4L19 7' : ($notification_type === 'warning' ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.332 16c-.77 1.333.192 3 1.732 3z' : 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z') ?>"></path></svg>
                    <span class="font-semibold"><?= htmlspecialchars($notification_message) ?></span>
                    <button onclick="document.getElementById('notification-box').style.opacity = '0'; setTimeout(() => document.getElementById('notification-box').remove(), 300);" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-white hover:text-white rounded-lg focus:ring-2 focus:ring-white p-1.5 hover:bg-opacity-20 inline-flex items-center justify-center h-8 w-8" aria-label="Close">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    const box = document.getElementById('notification-box');
                    if (box) box.style.opacity = '0';
                    setTimeout(() => box.remove(), 300);
                }, 8000); // Keep open longer for bulk messages
            </script>
        <?php endif; ?>
    </div>
    
    <!-- Main Container -->
    <div class="container mx-auto p-4 md:p-8">

        <!-- Header Card -->
        <div class="bg-primary rounded-xl shadow-2xl p-6 md:p-8 mb-8 text-white">
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Bulk Employee Upload (CSV)</h1>
            <p class="mt-2 text-primary-200 opacity-80">Upload multiple employee records using a structured CSV file.</p>
        </div>

        <!-- Instructions and Form Card -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Instructions Panel -->
            <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg h-fit">
                <h2 class="text-xl font-semibold text-primary mb-3 border-b pb-2">Guidelines</h2>
                <ul class="text-sm text-gray-600 space-y-3 list-disc pl-5">
                    <li>**Mandatory Fields:** `emp_id`, `first_name`, and `last_name` must be filled for every record.</li>
                    <li>**Optional Fields:** All other fields (`position`, `salary`, `email`, etc.) can be left blank (empty cells) in the CSV.</li>
                    <li>**Foreign Keys:** `dept_id` and `supervisor_id` must match existing IDs in their respective tables. If left blank, they will be set to NULL.</li>
                    <li>**Dates:** Use `YYYY-MM-DD` format (e.g., 2024-01-15).</li>
                    <li>**File Type:** Only CSV (`.csv`) files are accepted.</li>
                </ul>
                <a href="employee_bulk_template.csv" download class="mt-4 inline-flex items-center bg-accent text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent/90 transition duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download Template
                </a>
            </div>
            
            <!-- Upload Form -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-primary mb-4 border-b pb-2">Upload CSV File</h2>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <label class="block">
                        <span class="sr-only">Choose file</span>
                        <input type="file" name="bulk_file" accept=".csv" required 
                               class="block w-full text-sm text-gray-700
                               file:mr-4 file:py-2 file:px-4
                               file:rounded-full file:border-0
                               file:text-sm file:font-semibold
                               file:bg-primary file:text-white
                               hover:file:bg-primary/90 transition duration-150
                               ">
                    </label>
                    <button type="submit" class="w-full bg-accent text-white px-5 py-3 rounded-lg font-semibold shadow-md hover:bg-accent/90 transition duration-150 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 014 4v2m-5 4l-4-4m0 0l-4 4m4-4v11"></path></svg>
                        Process Upload
                    </button>
                </form>
            </div>
        </div>

        <!-- Processing Results Table -->
        <?php if (!empty($processed_data)): ?>
            <div class="mt-8 bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold text-primary mb-4">Processing Summary</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Row #</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($processed_data as $item): ?>
                                <tr class="<?= $item['status'] === 'Error' ? 'bg-red-50' : 'hover:bg-green-50' ?>">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?= $item['row'] ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($item['id']) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $item['status'] === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $item['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600 max-w-sm break-words"><?= htmlspecialchars($item['details'] ?? 'Successfully inserted.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer / Back Button -->
        <div class="mt-8 flex justify-center">
            <a href="employee.php" class="inline-flex items-center bg-gray-500 text-white px-5 py-2 rounded-lg font-medium shadow-md hover:bg-gray-600 transition duration-150">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Employee List
            </a>
        </div>

    </div>

</body>
</html>