<?php
namespace App\Utils;

use DateTime;
use PDO;

class AppHelpers
{
    public static function cleanNum($value) {
        if ($value === '' || $value === null) return null;
        // Strip commas and keep only numbers and decimal point
        $cleaned = preg_replace('/[^-0-9.]/', '', $value);
        return $cleaned === '' ? null : $cleaned;
    }

    public static function cleanDate($value) {
        return ($value === '' || $value === null) ? null : $value;
    }

    public static function calculateRatesFromMonthly($monthly) {
        if ($monthly > 0) {
            $workdays_in_month = 313 / 12; 
            $daily = round($monthly / $workdays_in_month, 4);
            $hourly = round($daily / 8, 4);
            return ['daily' => $daily, 'hourly' => $hourly];
        }
        return ['daily' => 0, 'hourly' => 0];
    }
    
    public static function generateCutoffPeriods($pdo, $year = null, $month = null, $iterations = 1, $offsetMonths = 0) {
        $cutoffs = [];
        
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM payroll_general_settings WHERE setting_key LIKE 'cutoff_%'");
        $settings = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $c1_start = (int)($settings['cutoff_1_start'] ?? 8);
        $c1_end   = (int)($settings['cutoff_1_end']   ?? 22);
        $c2_start = (int)($settings['cutoff_2_start'] ?? 23);
        $c2_end   = (int)($settings['cutoff_2_end']   ?? 7);

        if ($year && $month) {
            $baseDate = new DateTime("$year-$month-01");
        } else {
            $baseDate = new DateTime("first day of this month");
        }

        if ($offsetMonths !== 0) {
            $baseDate->modify("$offsetMonths months");
        }

        $current = clone $baseDate;

        for ($i = 0; $i < $iterations; $i++) {
            $y = $current->format('Y');
            $m = $current->format('m');
            
            $p1S = sprintf("%04d-%02d-%02d", $y, $m, $c1_start);
            $p1E = sprintf("%04d-%02d-%02d", $y, $m, $c1_end);
            
            $cutoffs[] = [
                'val'   => "$p1S|$p1E", // Legacy
                'value' => "$p1S|$p1E", 
                'label' => date('M d', strtotime($p1S)) . " - " . date('M d', strtotime($p1E)) . ", $y"
            ];

            $p2S = sprintf("%04d-%02d-%02d", $y, $m, $c2_start);
            $nextMonth = clone $current;
            $nextMonth->modify('first day of next month'); 
            $p2E = $nextMonth->format("Y-m") . "-" . sprintf("%02d", $c2_end);
            
            $cutoffs[] = [
                'val'   => "$p2S|$p2E", // Legacy
                'value' => "$p2S|$p2E",
                'label' => date('M d', strtotime($p2S)) . " - " . date('M d', strtotime($p2E)) . ", " . $nextMonth->format('Y')
            ];

            $current->modify('first day of next month');
        }

        return ($iterations > 1) ? array_reverse($cutoffs) : $cutoffs;
    }

    public static function normalizeDate($input) {
        if ($input instanceof DateTime) return $input->format('Y-m-d');
        if (is_numeric($input)) return date('Y-m-d', $input);
        return date('Y-m-d', strtotime($input));
    }
    // Add other generic helper functions here
}
