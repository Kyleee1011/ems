<?php
// Calculate Undertime Minutes
function calculateUndertimeMinutes($schedOut, $actOut) {
    if (!$schedOut || !$actOut) return 0;
    
    // If logged out earlier than scheduled out
    if ($actOut < $schedOut) {
        $seconds = $schedOut->getTimestamp() - $actOut->getTimestamp();
        return floor($seconds / 60);
    }
    return 0;
}

// Calculate Deduction Amount
function computeUndertimeDeduction($minutes, $minute_rate) {
    return $minutes * $minute_rate;
}
?>