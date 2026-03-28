<?php

namespace App\Services\Payroll;

use DateTime;

/**
 * Calculates deductions for undertime.
 */
class UndertimeCalculator
{
    /**
     * Calculates the deduction amount for working less than the scheduled hours.
     *
     * @param DateTime|null $scheduledOut The employee's scheduled departure time.
     * @param DateTime|null $actualOut The employee's actual departure time.
     * @param float $hourlyRate The employee's hourly rate.
     * @return float The total deduction amount for undertime.
     */
    public function calculateDeduction(?DateTime $scheduledOut, ?DateTime $actualOut, float $hourlyRate): float
    {
        if (!$scheduledOut || !$actualOut || $hourlyRate <= 0) {
            return 0;
        }

        $undertimeMinutes = $this->calculateMinutes($scheduledOut, $actualOut);

        if ($undertimeMinutes > 0) {
            $minuteRate = $hourlyRate / 60;
            return $undertimeMinutes * $minuteRate;
        }

        return 0.00;
    }

    /**
     * Calculates the number of minutes an employee was undertime.
     *
     * @param DateTime $scheduledOut The employee's scheduled departure time.
     * @param DateTime $actualOut The employee's actual departure time.
     * @return int The number of undertime minutes.
     */
    public function calculateMinutes(DateTime $scheduledOut, DateTime $actualOut): int
    {
        $scheduledTs = $scheduledOut->getTimestamp();
        $actualTs = $actualOut->getTimestamp();

        // If logged out earlier than scheduled
        if ($actualTs < $scheduledTs) {
            $seconds = $scheduledTs - $actualTs;
            return (int)floor($seconds / 60);
        }

        return 0;
    }
}
