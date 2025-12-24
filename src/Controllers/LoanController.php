<?php
namespace App\Controllers;

use App\Models\Loan;
use PDO;
use Exception;

class LoanController
{
    protected $model;
    protected $currentFullname;
    protected $userRole;

    public function __construct(PDO $emsPdo)
    {
        $this->model = new Loan($emsPdo);
        
        if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
        
        $this->currentFullname = $_SESSION['full_name'];
        $this->userRole = $_SESSION['approval_role'] ?? 'Employee';
        
        if ($this->userRole !== 'HR' && $this->userRole !== 'CEO') {
            header("Location: dashboard.php"); exit;
        }
    }

    public function index()
    {
        $message = $_SESSION['flash_message'] ?? '';
        $messageType = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $filterEmp = $_GET['view_emp'] ?? '';
        $filterStatus = $_GET['status'] ?? '';

        $stats = $this->model->getStats();
        $employees = $this->model->getEmployees();
        $loans = $this->model->getLoans($filterEmp, $filterStatus);

        // Variables for View
        extract([
            'current_fullname' => $this->currentFullname,
            'user_role' => $this->userRole,
            'message' => $message,
            'messageType' => $messageType,
            'totalActive' => $stats['active'],
            'totalReceivable' => $stats['receivable'],
            'loansThisMonth' => $stats['new_this_month'],
            'employees' => $employees,
            'loans' => $loans,
            'filter_emp' => $filterEmp,
            'filter_status' => $filterStatus
        ]);

        require __DIR__ . '/../Views/loan_view.php';
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_loan') {
                $this->model->createLoan($_POST);
                $this->setFlash("Loan application created successfully!", "success");
            } elseif ($action === 'update_status') {
                $this->model->updateStatus($_POST['loan_id'], $_POST['new_status']);
                $this->setFlash("Loan status updated to {$_POST['new_status']}.", "success");
            }
        } catch (Exception $e) {
            $this->setFlash("Error: " . $e->getMessage(), "error");
        }
        header("Location: loan.php");
        exit;
    }

    private function setFlash($msg, $type)
    {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
}
