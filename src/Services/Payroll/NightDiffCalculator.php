<?php

namespace App\Services\Payroll;

use DateTime;
use DateTimeZone;

/**
 * Calculates employee night differential pay.
 */
class NightDiffCalculator
{
    private const NIGHT_DIFF_RATE = 0.10;

    /**
     * Calculates the night differential pay for a given time-in and time-out.
     *
     * Night differential is a 10% premium for hours worked between 10:00 PM and 6:00 AM.
     * This method calculates the number of hours an employee worked during this
     * window and computes the premium.
     *
     * @param DateTime|null $timeIn The employee's time-in.
     * @param DateTime|null $timeOut The employee's time-out.
     * @param float $hourlyRate The employee's hourly rate.
     * @return float The total night differential pay.
     */
    public function calculate(?DateTime $timeIn, ?DateTime $timeOut, float $hourlyRate): float
    {
        if (!$timeIn || !$timeOut || $hourlyRate <= 0) {
            return 0;
        }

        $workStartTs = $timeIn->getTimestamp();
        $workEndTs = $timeOut->getTimestamp();

        if ($workStartTs >= $workEndTs) {
            return 0;
        }
        
        $totalNightHours = 0;

        // The legacy code creates three windows (previous day, current day, next day)
        // to handle shifts that cross midnight. This is a robust way to capture all overlap.
        $checkDate = new DateTime("@{$workStartTs}");
        $checkDate->setTimezone($timeIn->getTimezone());

        for ($i = -1; $i <= 1; $i++) {
            // Define the night differential window for a given day.
            // Window starts at 10 PM of the reference day.
            $nightWindowStart = clone $checkDate;
            $nightWindowStart->modify("$i day")->setTime(22, 0, 0);

            // Window ends at 6 AM of the *next* day.
            $nightWindowEnd = clone $checkDate;
            $nightWindowEnd->modify(($i + 1) . " day")->setTime(6, 0, 0);

            $nightWindowStartTs = $nightWindowStart->getTimestamp();
            $nightWindowEndTs = $nightWindowEnd->getTimestamp();
            
            // Calculate the overlap in seconds between the work shift and the night window.
            $overlapSeconds = max(0, min($workEndTs, $nightWindowEndTs) - max($workStartTs, $nightWindowStartTs));
            
            if ($overlapSeconds > 0) {
                $totalNightHours += ($overlapSeconds / 3600);
            }
        }

        if ($totalNightHours > 0) {
            return $totalNightHours * $hourlyRate * self::NIGHT_DIFF_RATE;
        }

        return 0;
    }
}
