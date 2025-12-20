<?php
// Calculate Overtime Amount (Philippine Labor Code)
function computeOvertimePay($hours, $hourly_rate, $holiday_type) {
    if ($hours <= 0) return 0;

    // Default: Ordinary Day OT (125%)
    $multiplier = 1.25; 
    
    if ($holiday_type == 'REGULAR') {
        // Rule: 200% Basic + 30% of that 200%
        // Math: 2.0 * 1.3 = 2.6
        $multiplier = 2.6; 
    } 
    elseif ($holiday_type == 'SPECIAL') {
        // Rule: 130% Basic + 30% of that 130%
        // Math: 1.3 * 1.3 = 1.69
        $multiplier = 1.69; 
    } 
    elseif ($holiday_type == 'DOUBLE') {
        // Rule: 300% Basic + 30% of that 300%
        // Math: 3.0 * 1.3 = 3.9
        $multiplier = 3.9;
    }

    return $hours * $hourly_rate * $multiplier;
}
?>