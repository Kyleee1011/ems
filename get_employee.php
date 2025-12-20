<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    if (isset($_GET['emp_id'])) {
        $empId = $_GET['emp_id'];
        
        $sql = "SELECT 
                    e.*,
                    d.dept_name,
                    s.supervisor_name
                FROM Employees e
                LEFT JOIN Departments d ON e.dept_id = d.dept_id
                LEFT JOIN Supervisors s ON e.supervisor_id = s.supervisor_id
                WHERE e.emp_id = :emp_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':emp_id', $empId);
        $stmt->execute();
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo json_encode([
                'success' => true,
                'employee' => $employee
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Employee not found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No employee ID provided'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
