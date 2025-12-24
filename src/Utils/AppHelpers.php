<?php
namespace App\Utils;

use DateTime;

class AppHelpers
{
    public static function cleanNum($value) {
        return ($value === '' || $value === null) ? null : $value;
    }

    public static function cleanDate($value) {
        return ($value === '' || $value === null) ? null : $value;
    }

    public static function calculateRatesFromMonthly($monthly) {
        if ($monthly > 0) {
            $workdays_in_month = 313 / 12; 
            $daily = $monthly / $workdays_in_month;
            $hourly = $daily / 8;
            return ['daily' => $daily, 'hourly' => $hourly];
        }
        return ['daily' => 0, 'hourly' => 0];
    }
    
    public static function generateCutoffPeriods() {
        $cutoffs = [];
        $current = new DateTime();
        $current->modify('-3 months'); 

        for ($i = 0; $i < 8; $i++) { 
            $year = $current->format('Y');
            $month = $current->format('m');
            
            // --- Period 1: 8th to 22nd ---
            $p1_start = "$year-$month-08";
            $p1_end   = "$year-$month-22";
            
            $cutoffs[] = [
                'value' => "$p1_start|$p1_end", 
                'label' => date('M d', strtotime($p1_start)) . " - " . date('M d', strtotime($p1_end)) . ", $year"
            ];

            // --- Period 2: 23rd to 7th (of the next month) ---
            $p2_start = "$year-$month-23";
            $nextMonthDate = clone $current;
            $nextMonthDate->modify('+1 month');
            $p2_end = $nextMonthDate->format('Y-m-07');
            $nextYearLabel = $nextMonthDate->format('Y');

            $cutoffs[] = [
                'value' => "$p2_start|$p2_end",
                'label' => date('M d', strtotime($p2_start)) . " - " . date('M d', strtotime($p2_end)) . ", $nextYearLabel"
            ];

            $current->modify('+1 month');
        }
        return $cutoffs;
    }
    // Add other generic helper functions here
}
