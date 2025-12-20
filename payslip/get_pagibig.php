<?php
function getPagIBIGDeduction($conn, $salary) {
    $stmt = $conn->prepare("SELECT TOP 1 fixed_amt FROM Payroll_PagIBIG_Table");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['fixed_amt'] : 200.00;
}
?>