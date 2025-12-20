<?php
function getSSSDeduction($conn, $salary) {
    // Select the row where salary falls between min and max
    $stmt = $conn->prepare("SELECT TOP 1 ee_share FROM Payroll_SSS_Table WHERE ? BETWEEN min_salary AND max_salary");
    $stmt->execute([$salary]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return the deduction or 0 if not found
    return $row ? $row['ee_share'] : 0.00;
}
?>