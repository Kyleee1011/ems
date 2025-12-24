<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Display success message from password change
if (isset($_GET['changed'])) {
    $success = "Password changed successfully. Please login with your new password.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ac_no = trim($_POST['ac_no']);
    $password = trim($_POST['password']);

    if (empty($ac_no) || empty($password)) {
        $error = "Please enter AC No. and Password.";
    } else {
        try {
            $conn = $pdo;
            
            // FETCH USER, APPROVAL ROLE, & DEPARTMENT DETAILS
            $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.dept_id, 
                           d.dept_code, d.dept_name, 
                           e.employee_status, e.password_hash, e.approval_role 
                    FROM Employees e 
                    LEFT JOIN Departments d ON e.dept_id = d.dept_id 
                    WHERE e.ac_no = :ac_no";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':ac_no', $ac_no);
            $stmt->execute();
            
            if ($row = $stmt->fetch()) {
                $authenticated = false;

                // 1. Check custom password (hash)
                if (!empty($row['password_hash'])) {
                    if (password_verify($password, $row['password_hash'])) {
                        $authenticated = true;
                    }
                } 
                // 2. Check default password (AC No)
                else {
                    if ($password === $row['ac_no']) {
                        $authenticated = true;
                    }
                }

                if ($authenticated) {
                    if ($row['employee_status'] !== 'Active') {
                        $error = "This account is inactive. Please contact System Admin.";
                    } else {
                        // SET SESSION VARIABLES
                        $_SESSION['user_id'] = $row['emp_id'];
                        $_SESSION['ac_no'] = $row['ac_no'];
                        $_SESSION['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
                        $_SESSION['dept_id'] = $row['dept_id'];
                        
                        // Store Approval Role
                        $_SESSION['approval_role'] = $row['approval_role'] ?? 'Employee';

                        // --- ROLE ASSIGNMENT LOGIC ---
                        // Based on your data: 
                        // ID 5 = Human Resources
                        // Code = HRD
                        
                        // We normalize the session role to 'HR' so index.php handles it easily
                        if ($row['dept_name'] === 'Human Resources' || $row['dept_code'] === 'HRD') {
                            $_SESSION['role'] = 'HR'; 
                        } else {
                            $_SESSION['role'] = 'Employee';
                        }

                        header("location: index.php");
                        exit;
                    }
                } else {
                    $error = "Invalid Password.";
                }
            } else {
                $error = "Account not found.";
            }
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | EMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 h-screen flex items-center justify-center">

    <div class="max-w-md w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-blue-600 p-8 text-center">
            <h1 class="text-2xl font-bold text-white mb-2">EMS Login</h1>
            <p class="text-blue-100 text-sm">Employee Management System</p>
        </div>
        
        <div class="p-8">
            <?php if($success): ?>
                <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4 border border-green-100">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 text-sm p-3 rounded-lg mb-4 border border-red-100">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">AC Number</label>
                    <input type="text" name="ac_no" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Enter your AC No." required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Enter password" required>
                    <div class="text-right mt-1">
                        <a href="change_password.php" class="text-xs text-blue-600 hover:text-blue-800 hover:underline">Change Password?</a>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition duration-200">
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center text-xs text-gray-500">
                <p>Default password is your AC Number.</p>
            </div>
        </div>
    </div>

</body>
</html>