<?php
// Withholding Tax Calculation
function calculateTax($conn, $salary) {
    // Ensure salary is a number
    $salary = (float)$salary;
    
    // 1. Find the matching tax bracket from SQL
    $stmt = $conn->prepare("SELECT TOP 1 base_tax, excess_rate, min_salary FROM Payroll_Tax_Table WHERE ? BETWEEN min_salary AND max_salary");
    $stmt->execute([$salary]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // 2. Calculate Excess
        // We use floor() to round down the bracket min (e.g., 20833.01 becomes 20833)
        // This ensures your math matches: 25,000 - 20,833 = 4,167
        $bracket_floor = floor($row['min_salary']);
        
        // Handle the first bracket (0-20833) where floor might be 0
        if($row['min_salary'] <= 0) {
            $excess = 0;
        } else {
            $excess = $salary - $bracket_floor;
        }
        
        // 3. Final Calculation: Base Tax + (Excess * Rate)
        return $row['base_tax'] + ($excess * $row['excess_rate']);
    }
    
    // Default to 0 if no bracket found
    return 0.00;
}
?>