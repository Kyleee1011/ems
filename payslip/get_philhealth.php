<?php
function getPhilHealthDeduction($conn, $salary) {
    $stmt = $conn->prepare("SELECT TOP 1 rate FROM Payroll_PhilHealth_Table");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ($salary * $row['rate']) : 0.00;
}
?>