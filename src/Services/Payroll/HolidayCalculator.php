<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Determines the type of day (e.g., holiday, regular day).
 */
class HolidayCalculator
{
    // Constants for Holiday Types to avoid magic strings
    public const DAY_TYPE_REGULAR_HOLIDAY = 'REGULAR';
    public const DAY_TYPE_SPECIAL_HOLIDAY = 'SPECIAL';
    public const DAY_TYPE_DOUBLE_HOLIDAY = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'REGULAR_DAY';

    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Checks the database to determine if a given date is a holiday.
     *
     * @param string $dateStr The date to check in 'Y-m-d' format.
     * @return string The type of day (e.g., 'REGULAR', 'SPECIAL', 'REGULAR_DAY').
     */
    public function getDayType(string $dateStr): string
    {
        // Check database for a holiday entry in scheduler_holidays table
        $stmt = $this->pdo->prepare(
            "SELECT holiday_type FROM scheduler_holidays WHERE holiday_date = ?"
        );
        $stmt->execute([$dateStr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['holiday_type'])) {
            return $row['holiday_type'];
        }

        return self::DAY_TYPE_ORDINARY;
    }
}
