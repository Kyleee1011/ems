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

    public function __construct(PDO $pdo)
    {
        $this->model = new Loan($pdo);
        
        if (!isset($_SESSION['user_id'])) { header("Location: " . baseUrl('login')); exit; }
        
        $this->currentFullname = $_SESSION['full_name'];
        $this->userRole = $_SESSION['approval_role'] ?? 'Employee';
        $this->currentEmpId = $_SESSION['user_id'] ?? null; // Fixed: Session key is user_id
    }

    public function index()
    {
        $message = $_SESSION['flash_message'] ?? '';
        $messageType = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        // Handle POST logic first
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $filterEmp = $_GET['view_emp'] ?? '';
        $filterStatus = $_GET['status'] ?? '';
        $activeTab = $_GET['tab'] ?? 'my_loans';

        $stats = $this->model->getStats();
        $employees = $this->model->getEmployees(); // Only HR/CEO really need this list
        
        $myLoans = [];
        $pendingLoans = [];
        $allLoans = [];

        // Role-based data fetching
        if ($this->userRole === 'Employee') {
            // Employees only see their own loans
            if ($this->currentEmpId) {
                $myLoans = $this->model->getLoans($this->currentEmpId);
            }
        } else {
            // HR/CEO can see everything
            // 1. My Loans (If they are also an employee)
            if ($this->currentEmpId) {
                $myLoans = $this->model->getLoans($this->currentEmpId);
            }
            
            // 2. Pending Loans (Task Inbox)
            if ($this->userRole === 'HR' || $this->userRole === 'CEO') {
                $pendingLoans = $this->model->getPendingLoans($this->userRole);
            }

            // 3. All Loans (For management)
            $allLoans = $this->model->getLoans($filterEmp, $filterStatus);
        }

        // 1-Year Rule Check
        $isEligible = true;
        // Verify eligibility via Employee Model logic if strict needed, 
        // for now getting hired date from session or re-query could be done. 
        // Assuming visual check or backend validation later.

        // Variables for View
        extract([
            'current_fullname' => $this->currentFullname,
            'user_role' => $this->userRole,
            'message' => $message,
            'messageType' => $messageType,
            'totalActive' => $stats['active'],
            'totalPending' => $stats['pending'],
            'totalReceivable' => $stats['receivable'],
            'loansThisMonth' => $stats['new_this_month'],
            'employees' => $employees,
            'myLoans' => $myLoans,
            'pendingLoans' => $pendingLoans,
            'allLoans' => $allLoans,
            'filter_emp' => $filterEmp,
            'filter_status' => $filterStatus,
            'active_tab' => $activeTab
        ]);

        require __DIR__ . '/../Views/loan_view.php';
    }

    private function handlePost()
    {
        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->setFlash("Invalid CSRF token. Please refresh the page.", "error");
            header("Location: " . baseUrl('loan'));
            exit;
        }

        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_loan') {
                // If Employee, force own ID
                if ($this->userRole === 'Employee') {
                    $_POST['emp_id'] = $this->currentEmpId;
                }
                $this->model->createLoan($_POST);
                $this->setFlash("Loan application submitted successfully! Waiting for HR approval.", "success");
            } 
            elseif ($action === 'status_update_hr') {
                $this->checkPermission(['HR']);
                $this->model->updateHRStatus($_POST['loan_id'], $_POST['status'], $_SESSION['user_id'], $_POST['reason'] ?? null);
                $this->setFlash("Loan " . $_POST['status'] . " by HR.", "success");
            }
            elseif ($action === 'status_update_ceo') {
                $this->checkPermission(['CEO']);
                $this->model->updateCEOStatus($_POST['loan_id'], $_POST['status'], $_SESSION['user_id'], $_POST['reason'] ?? null);
                $this->setFlash("Loan " . $_POST['status'] . " by CEO.", "success");
            }
            elseif ($action === 'revise_loan') {
                $this->checkPermission(['HR']);
                $this->model->updateLoanDetails(
                    $_POST['loan_id'], 
                    $_POST['principal_amount'], 
                    $_POST['interest_rate'], 
                    $_POST['months_to_pay'], 
                    $_POST['deduction_frequency']
                );
                // Also approve it if 'approve_after' is set? Or just save.
                if (isset($_POST['approve_now']) && $_POST['approve_now'] == 1) {
                     $this->model->updateHRStatus($_POST['loan_id'], 'Approved', $_SESSION['user_id']);
                     $this->setFlash("Loan details revised and Approved.", "success");
                } else {
                     $this->setFlash("Loan details updated.", "success");
                }
            }
            elseif ($action === 'update_status') {
                $this->checkPermission(['HR', 'CEO']); // Manual override
                $this->model->updateStatus($_POST['loan_id'], $_POST['new_status']);
                $this->setFlash("Loan status updated to {$_POST['new_status']}.", "success");
            }
        } catch (Exception $e) {
            $this->setFlash("Error: " . $e->getMessage(), "error");
        }
        header("Location: " . baseUrl('loan'));
        exit;
    }

    private function checkPermission($roles) {
        if (!in_array($this->userRole, $roles)) {
            throw new Exception("Unauthorized Action");
        }
    }

    private function setFlash($msg, $type)
    {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
}
