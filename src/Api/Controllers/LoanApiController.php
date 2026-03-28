<?php
namespace App\Api\Controllers;

use App\Api\Controllers\BaseApiController;
use App\Models\Loan;
use PDO;

class LoanApiController extends BaseApiController
{
    protected $model;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->model = new Loan($pdo); // Loan only needs EMS PDO
    }

    public function index()
    {
        // GET /loan?status=Active
        if ($this->userRole !== 'HR' && $this->userRole !== 'CEO') {
            $this->sendError('Access Restricted', 403);
        }

        $status = $_GET['status'] ?? '';
        $emp = $_GET['view_emp'] ?? '';
        
        $loans = $this->model->getLoans($emp, $status);
        $this->jsonResponse(['loans' => $loans]);
    }
}
