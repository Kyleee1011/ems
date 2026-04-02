<?php

namespace App\Controllers;

use PDO;

class AuthController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Display the login form.
     */
    public function showLoginForm()
    {
require_once dirname(dirname(__DIR__)) . '/config_session.php';

        // If user is already logged in, redirect to home
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . baseUrl('home'));
            exit;
        }

        $success = '';
        if (isset($_GET['changed'])) {
            $success = "Password changed successfully. Please login with your new password.";
        }

        $this->render('login_view', ['error' => '', 'success' => $success]);
    }

    /**
     * Handle the login attempt.
     */
    public function login()
    {
require_once dirname(dirname(__DIR__)) . '/config_session.php';

        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->render('login_view', ['error' => 'Invalid CSRF token. Please refresh the page.', 'success' => '']);
            return;
        }
        
        $error = '';
        $ac_no = trim($_POST['ac_no']);
        $password = trim($_POST['password']);

        if (empty($ac_no) || empty($password)) {
            $this->render('login_view', ['error' => 'Please enter AC No. and Password.', 'success' => '']);
            return;
        }

        try {
            // Use the injected PDO connection
            $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.dept_id, 
                           d.dept_code, d.dept_name, 
                           e.employee_status, e.password_hash, e.approval_role 
                    FROM employees e 
                    LEFT JOIN departments d ON e.dept_id = d.dept_id 
                    WHERE e.ac_no = :ac_no";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ac_no', $ac_no);
            $stmt->execute();
            
            if ($row = $stmt->fetch()) {
                $authenticated = (!empty($row['password_hash']) && password_verify($password, $row['password_hash'])) || 
                                 (empty($row['password_hash']) && $password === $row['ac_no']);

                if ($authenticated) {
                    if ($row['employee_status'] !== 'Active') {
                        $error = "This account is inactive. Please contact System Admin.";
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $row['emp_id'];
                        $_SESSION['ac_no'] = $row['ac_no'];
                        $_SESSION['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
                        $_SESSION['dept_id'] = $row['dept_id'];
                        $_SESSION['approval_role'] = $row['approval_role'] ?? 'Employee';
                        $_SESSION['role'] = ($row['dept_name'] === 'Human Resources' || $row['dept_code'] === 'HRD') ? 'HR' : 'Employee';

                        // Redirect based on role
                        header("Location: " . baseUrl('home'));
                        exit;
                    }
                } else {
                    $error = "Invalid Password.";
                }
            } else {
                $error = "Account not found.";
            }
        } catch(PDOException $e) {
            $error = "Database Error: Could not connect."; // Don't expose detailed error
        }

        $this->render('login_view', ['error' => $error, 'success' => '']);
    }

    protected function render(string $view, array $data = [])
    {
        extract($data);
        require BASE_PATH . "/src/Views/{$view}.php";
    }
}


