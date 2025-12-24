<?php
namespace App\Helpers;

use PDO;
use Exception;
use DateTime;

class AppHelper
{
    public static function getBiologsConnection() {
        $serverName = "192.168.21.52,1433"; 
        $database = "biologs_db"; 
        $username = "sa"; 
        $password = "Azzurro2025"; 
        try {
            $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (\PDOException $e) { 
            return null; 
        }
    }

    public static function generateCutoffPeriods() {
        $periods = [];
        $current = new DateTime();
        $current->modify('-4 months'); 
    
        for ($i = 0; $i < 8; $i++) { 
            $year = $current->format('Y');
            $month = $current->format('m');
            
            $p1_start = "$year-$month-08";
            $p1_end   = "$year-$month-22";
            $periods[] = ['val' => "$p1_start|$p1_end", 'label' => date('M d', strtotime($p1_start)) . " - " . date('M d', strtotime($p1_end)) . ", $year"];
    
            $p2_start = "$year-$month-23";
            $nextMonthDate = clone $current;
            $nextMonthDate->modify('+1 month');
            $p2_end = $nextMonthDate->format('Y-m-07');
            $nextYearLabel = $nextMonthDate->format('Y');
            $periods[] = ['val' => "$p2_start|$p2_end", 'label' => date('M d', strtotime($p2_start)) . " - " . date('M d', strtotime($p2_end)) . ", $nextYearLabel"];
    
            $current->modify('+1 month');
        }
        return array_reverse($periods);
    }

    public static function normalizeDate($input) {
        if ($input instanceof DateTime) return $input->format('Y-m-d');
        return date('Y-m-d', strtotime($input));
    }
}
