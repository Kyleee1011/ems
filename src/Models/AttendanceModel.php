<?php
namespace App\Models;

use PDO;

class AttendanceModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAttendanceLogs($deptId = null, $startDate = null, $endDate = null)
    {
        // Default to today if no dates provided
        if (!$startDate) $startDate = date('Y-m-d');
        if (!$endDate) $endDate = date('Y-m-d');

        $sql = "SELECT 
                    e.emp_id, 
                    e.last_name, 
                    e.first_name, 
                    e.ac_no,
                    d.dept_name,
                    c.CHECKTIME as check_time, 
                    c.CHECKTYPE as check_type, 
                    c.sn as device_sn
                FROM ems.employees e
                JOIN biometric_logs.userinfo u ON e.ac_no = u.BADGENUMBER
                JOIN biometric_logs.checkinout c ON u.USERID = c.USERID
                LEFT JOIN ems.departments d ON e.dept_id = d.dept_id
                WHERE DATE(c.CHECKTIME) BETWEEN :start AND :end";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($deptId && $deptId != 'all') {
            $sql .= " AND e.dept_id = :deptId";
            $params[':deptId'] = $deptId;
        }

        $sql .= " ORDER BY c.CHECKTIME DESC LIMIT 1000";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeeLogs($acNo)
    {
        $sql = "SELECT 
                    c.CHECKTIME as check_time, 
                    c.CHECKTYPE as check_type, 
                    c.sn as device_sn
                FROM biometric_logs.userinfo u
                JOIN biometric_logs.checkinout c ON u.USERID = c.USERID
                WHERE u.BADGENUMBER = :acNo
                ORDER BY c.CHECKTIME DESC
                LIMIT 500";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':acNo' => $acNo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
