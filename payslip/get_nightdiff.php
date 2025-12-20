<?php
// Calculate Night Differential Amount
// Night diff is usually 10% premium for hours between 10PM and 6AM
function calculateNightDiffAmount($in, $out, $hourly_rate) {
    if (!$in || !$out) return 0;

    $start = $in->getTimestamp();
    $end = $out->getTimestamp();
    
    $nd_hours = 0;
    
    // Create windows for 10PM to 6AM coverage
    $windows = [];
    $d1 = new DateTime("@$start"); 
    $d1->setTimezone($in->getTimezone()); // Sync timezone
    
    // Check previous day, current day, next day windows to cover shifts crossing midnight
    for($i = -1; $i <= 1; $i++) {
        $wStart = clone $d1; 
        $wStart->modify("$i day")->setTime(22, 0, 0); // 10 PM
        
        $wEnd = clone $d1;
        $wEnd->modify(($i+1) . " day")->setTime(6, 0, 0); // 6 AM next day
        
        $windows[] = [$wStart->getTimestamp(), $wEnd->getTimestamp()];
    }

    foreach($windows as $win) {
        // Calculate overlap
        $overlap = max(0, min($end, $win[1]) - max($start, $win[0]));
        $nd_hours += ($overlap / 3600);
    }

    // 10% Premium
    if ($nd_hours > 0) {
        return $nd_hours * $hourly_rate * 0.10;
    }
    return 0;
}
?>