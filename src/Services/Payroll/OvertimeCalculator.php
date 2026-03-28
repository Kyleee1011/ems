<?php

namespace App\Services\Payroll;

/**
 * Calculates employee overtime pay based on Philippine labor code multipliers.
 */
class OvertimeCalculator
{
    // Constants for Holiday Types to avoid magic strings
    public const DAY_TYPE_REGULAR = 'REGULAR';
    public const DAY_TYPE_SPECIAL = 'SPECIAL';
    public const DAY_TYPE_DOUBLE = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'ORDINARY_DAY'; // Or any other default value

    // Multipliers based on Philippine Labor Code
    private const MULTIPLIER_ORDINARY = 1.25;         // +25% for ordinary day OT
    private const MULTIPLIER_REGULAR_HOLIDAY = 2.60;  // 200% (holiday pay) * 1.30 (OT premium) = 260%
    private const MULTIPLIER_SPECIAL_HOLIDAY = 1.69;  // 130% (holiday pay) * 1.30 (OT premium) = 169%
    private const MULTIPLIER_DOUBLE_HOLIDAY = 3.90;   // 300% (holiday pay) * 1.30 (OT premium) = 390%

    /**
     * Calculates the overtime pay.
     *
     * @param float $otHours The number of overtime hours worked.
     * @param float $hourlyRate The employee's regular hourly rate.
     * @param string $dayType The type of day (e.g., 'REGULAR', 'SPECIAL', 'ORDINARY_DAY').
     * @return float The calculated overtime pay.
     */
    public function calculate(float $otHours, float $hourlyRate, string $dayType): float
    {
        if ($otHours <= 0 || $hourlyRate <= 0) {
            return 0;
        }

        $multiplier = self::MULTIPLIER_ORDINARY;

        switch ($dayType) {
            case self::DAY_TYPE_REGULAR:
                $multiplier = self::MULTIPLIER_REGULAR_HOLIDAY;
                break;
            case self::DAY_TYPE_SPECIAL:
                $multiplier = self::MULTIPLIER_SPECIAL_HOLIDAY;
                break;
            case self::DAY_TYPE_DOUBLE:
                $multiplier = self::MULTIPLIER_DOUBLE_HOLIDAY;
                break;
        }

        return $otHours * $hourlyRate * $multiplier;
    }
}
