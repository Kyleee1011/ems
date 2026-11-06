<?php

namespace App\Services\Payroll;

/**
 * Calculates employee overtime pay based on Philippine Labor Code multipliers.
 *
 * Statutory DOLE / Labor Code Multipliers:
 * - Ordinary Day: 100% base + 25% OT premium = 125% (1.25)
 * - Rest Day: 130% base * 1.30 OT premium = 169% (1.69)
 * - Special Non-Working Holiday: 130% base * 1.30 OT premium = 169% (1.69)
 * - Special Non-Working Holiday on Rest Day: 150% base * 1.30 OT premium = 195% (1.95)
 * - Regular Holiday: 200% base * 1.30 OT premium = 260% (2.60)
 * - Regular Holiday on Rest Day: 260% base * 1.30 OT premium = 338% (3.38)
 * - Double Regular Holiday: 300% base * 1.30 OT premium = 390% (3.90)
 * - Double Regular Holiday on Rest Day: 390% base * 1.30 OT premium = 507% (5.07)
 */
class OvertimeCalculator
{
    // Holiday Types matching HolidayCalculator
    public const DAY_TYPE_REGULAR = 'REGULAR';
    public const DAY_TYPE_REGULAR_HOLIDAY = 'REGULAR';
    public const DAY_TYPE_SPECIAL = 'SPECIAL';
    public const DAY_TYPE_SPECIAL_HOLIDAY = 'SPECIAL';
    public const DAY_TYPE_DOUBLE = 'DOUBLE';
    public const DAY_TYPE_DOUBLE_HOLIDAY = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'REGULAR_DAY';

    // Multipliers based on Philippine Labor Code
    public const MULTIPLIER_ORDINARY = 1.25;                  // Ordinary day OT: +25%
    public const MULTIPLIER_REST_DAY = 1.69;                  // Rest day OT: 130% * 1.30
    public const MULTIPLIER_SPECIAL_HOLIDAY = 1.69;           // Special holiday OT: 130% * 1.30
    public const MULTIPLIER_SPECIAL_HOLIDAY_REST_DAY = 1.95;  // Special holiday on rest day OT: 150% * 1.30
    public const MULTIPLIER_REGULAR_HOLIDAY = 2.60;           // Regular holiday OT: 200% * 1.30
    public const MULTIPLIER_REGULAR_HOLIDAY_REST_DAY = 3.38;   // Regular holiday on rest day OT: 260% * 1.30
    public const MULTIPLIER_DOUBLE_HOLIDAY = 3.90;            // Double holiday OT: 300% * 1.30
    public const MULTIPLIER_DOUBLE_HOLIDAY_REST_DAY = 5.07;   // Double holiday on rest day OT: 390% * 1.30

    /**
     * Returns the appropriate overtime multiplier based on day type and rest day status.
     *
     * @param string $dayType   The type of day ('REGULAR', 'SPECIAL', 'DOUBLE', 'REGULAR_DAY', etc.)
     * @param bool   $isRestDay Whether the overtime occurred on a rest day
     * @return float
     */
    public function getMultiplier(string $dayType, bool $isRestDay = false): float
    {
        $type = strtoupper(trim($dayType));

        if ($isRestDay) {
            switch ($type) {
                case self::DAY_TYPE_REGULAR:
                case 'REGULAR_HOLIDAY':
                    return self::MULTIPLIER_REGULAR_HOLIDAY_REST_DAY; // 3.38
                case self::DAY_TYPE_SPECIAL:
                case 'SPECIAL_HOLIDAY':
                    return self::MULTIPLIER_SPECIAL_HOLIDAY_REST_DAY; // 1.95
                case self::DAY_TYPE_DOUBLE:
                case 'DOUBLE_HOLIDAY':
                    return self::MULTIPLIER_DOUBLE_HOLIDAY_REST_DAY; // 5.07
                default:
                    return self::MULTIPLIER_REST_DAY; // 1.69
            }
        }

        switch ($type) {
            case self::DAY_TYPE_REGULAR:
            case 'REGULAR_HOLIDAY':
                return self::MULTIPLIER_REGULAR_HOLIDAY; // 2.60
            case self::DAY_TYPE_SPECIAL:
            case 'SPECIAL_HOLIDAY':
                return self::MULTIPLIER_SPECIAL_HOLIDAY; // 1.69
            case self::DAY_TYPE_DOUBLE:
            case 'DOUBLE_HOLIDAY':
                return self::MULTIPLIER_DOUBLE_HOLIDAY; // 3.90
            default:
                return self::MULTIPLIER_ORDINARY; // 1.25
        }
    }

    /**
     * Calculates the overtime pay.
     *
     * @param float  $otHours    The number of overtime hours worked.
     * @param float  $hourlyRate The employee's regular hourly rate.
     * @param string $dayType    The type of day (e.g., 'REGULAR', 'SPECIAL', 'REGULAR_DAY').
     * @param bool   $isRestDay  Whether the overtime occurred on a scheduled rest day.
     * @return float The calculated overtime pay.
     */
    public function calculate(float $otHours, float $hourlyRate, string $dayType = self::DAY_TYPE_ORDINARY, bool $isRestDay = false): float
    {
        if ($otHours <= 0 || $hourlyRate <= 0) {
            return 0.0;
        }

        $multiplier = $this->getMultiplier($dayType, $isRestDay);

        return round($otHours * $hourlyRate * $multiplier, 2);
    }
}
