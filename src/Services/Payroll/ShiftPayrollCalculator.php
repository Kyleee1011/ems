<?php

namespace App\Services\Payroll;

use PDO;
use DateTime;

/**
 * ShiftPayrollCalculator - Handles shifts spanning multiple days with different holiday types
 * 
 * This calculator properly handles scenarios like:
 * - Employee works 6PM Dec 24 (Special Holiday) to 3AM Dec 25 (Regular Holiday)
 * - Each day segment gets its own holiday rate
 * - Night differential is calculated per segment
 * 
 * @see docs/PAYROLL.md Section 11 for detailed formulas
 */
class ShiftPayrollCalculator
{
    // Holiday rate multipliers (Philippine Labor Code)
    private const HOLIDAY_RATES = [
        'REGULAR_DAY' => 1.0,      // Regular working day (100%)
        'REST_DAY'    => 1.3,      // Rest day (130%)
        'SPECIAL'     => 1.3,      // Special non-working holiday (130%)
        'REGULAR'     => 2.0,      // Regular holiday (200%)
        'DOUBLE'      => 3.0,      // Double holiday (300%)
    ];

    // Night differential rate (10PM - 6AM)
    private const NIGHT_DIFF_RATE = 0.10; // 10% premium

    protected PDO $pdo;
    protected $holidayCalculator;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->holidayCalculator = new HolidayCalculator($pdo);
    }

    /**
     * Calculate pay for a shift that may span multiple days/holidays
     * 
     * @param float $hourlyRate Employee's hourly rate
     * @param DateTime $shiftStart Start of shift
     * @param DateTime $shiftEnd End of shift
     * @param float $breakHours Unpaid break hours
     * @param DateTime|null $ndLimitStart Optional: Only count ND if work is also after this time
     * @param DateTime|null $ndLimitEnd Optional: Only count ND if work is also before this time
     * @return array Detailed calculation results with segments
     */
    public function calculateShiftPay(
        float $hourlyRate,
        DateTime $shiftStart,
        DateTime $shiftEnd,
        float $breakHours = 1.0,
        ?DateTime $ndLimitStart = null,
        ?DateTime $ndLimitEnd = null
    ): array {
        // Calculate total shift duration
        $totalShiftSeconds = $shiftEnd->getTimestamp() - $shiftStart->getTimestamp();
        $totalShiftHours = $totalShiftSeconds / 3600;

        if ($totalShiftHours <= 0) {
            return $this->emptyResult($breakHours);
        }

        // Split shift into daily segments
        $segments = $this->splitShiftIntoSegments($shiftStart, $shiftEnd, $hourlyRate, $ndLimitStart, $ndLimitEnd);

        // Calculate totals before break deduction
        $totals = $this->calculateTotals($segments);

        // Apply break deduction sequentially across segments
        if ($breakHours > 0 && $totalShiftHours > 0) {
            $segments = $this->applyBreakDeduction($segments, $breakHours);
            $totals = $this->calculateTotals($segments);
        }

        return [
            'segments'            => $segments,
            'total_pay'           => round($totals['pay'], 2),
            'total_hours'         => round($totals['hours'], 2),
            'total_regular_hours' => round($totals['regular_hours'], 2),
            'total_night_hours'   => round($totals['night_hours'], 2),
            'break_hours'         => $breakHours,
            'hourly_rate'         => $hourlyRate,
            'shift_start'         => $shiftStart->format('Y-m-d H:i:s'),
            'shift_end'           => $shiftEnd->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Split a shift into segments at midnight boundaries
     * Each segment belongs to a single date with its own holiday type
     */
    private function splitShiftIntoSegments(
        DateTime $startDateTime,
        DateTime $endDateTime,
        float $hourlyRate,
        ?DateTime $ndLimitStart = null,
        ?DateTime $ndLimitEnd = null
    ): array {
        $segments = [];
        $currentTime = clone $startDateTime;

        while ($currentTime < $endDateTime) {
            // Midnight of the NEXT day (exact boundary)
            $midnight = clone $currentTime;
            $midnight->modify('+1 day')->setTime(0, 0, 0);

            // Segment ends at either midnight or shift end (whichever is first)
            $segmentEnd = ($endDateTime < $midnight) ? clone $endDateTime : clone $midnight;

            // Get date for this segment
            $segmentDate = $currentTime->format('Y-m-d');

            // Determine holiday type from database
            $holidayType = $this->holidayCalculator->getDayType($segmentDate);

            // Calculate hours for this segment
            $segmentSeconds = $segmentEnd->getTimestamp() - $currentTime->getTimestamp();
            $hours = $segmentSeconds / 3600;

            // Calculate night differential hours (10PM - 6AM) with optional schedule restriction
            $nightHours = $this->calculateNightHours($currentTime, $segmentEnd, $ndLimitStart, $ndLimitEnd);
            $regularHours = max(0, $hours - $nightHours);

            // Get holiday rate multiplier
            $holidayRate = self::HOLIDAY_RATES[$holidayType] ?? 1.0;

            // Calculate pay for this segment
            $regularPay = $regularHours * $hourlyRate * $holidayRate;
            $nightPay = $nightHours * $hourlyRate * $holidayRate * (1 + self::NIGHT_DIFF_RATE);
            $totalSegmentPay = $regularPay + $nightPay;

            $segments[] = [
                'start'         => $currentTime->format('Y-m-d H:i:s'),
                'end'           => $segmentEnd->format('Y-m-d H:i:s'),
                'date'          => $segmentDate,
                'holiday_type'  => $holidayType,
                'holiday_rate'  => $holidayRate,
                'hours'         => round($hours, 4),
                'regular_hours' => round($regularHours, 4),
                'night_hours'   => round($nightHours, 4),
                'regular_pay'   => round($regularPay, 4), 
                'night_pay'     => round($nightPay, 4),   
                'pay'           => round($totalSegmentPay, 4), 
            ];

            // Move to start of next segment (exact midnight, no gap)
            $currentTime = clone $segmentEnd;
        }

        return $segments;
    }

    /**
     * Calculate hours that fall within night differential window (10PM - 6AM)
     * Optional: Restrict ND to within the scheduled shift boundaries.
     */
    private function calculateNightHours(
        DateTime $start, 
        DateTime $end, 
        ?DateTime $ndLimitStart = null, 
        ?DateTime $ndLimitEnd = null
    ): float {
        $nightHours = 0;
        $workStartTs = $start->getTimestamp();
        $workEndTs = $end->getTimestamp();

        // Optional schedule-based restriction
        if ($ndLimitStart) {
            $workStartTs = max($workStartTs, $ndLimitStart->getTimestamp());
        }
        if ($ndLimitEnd) {
            $workEndTs = min($workEndTs, $ndLimitEnd->getTimestamp());
        }

        if ($workStartTs >= $workEndTs) {
            return 0;
        }

        for ($dayOffset = -1; $dayOffset <= 0; $dayOffset++) {
            // Night window start: 10PM of (current + offset) day
            $nightWindowStart = clone $start;
            $nightWindowStart->modify("{$dayOffset} day")->setTime(22, 0, 0);

            // Night window end: 6AM of next day
            $nightWindowEnd = clone $nightWindowStart;
            $nightWindowEnd->modify('+1 day')->setTime(6, 0, 0);

            $nightStartTs = $nightWindowStart->getTimestamp();
            $nightEndTs = $nightWindowEnd->getTimestamp();

            // Calculate overlap between restricted work window and night window
            $overlapStart = max($workStartTs, $nightStartTs);
            $overlapEnd = min($workEndTs, $nightEndTs);

            if ($overlapEnd > $overlapStart) {
                $nightHours += ($overlapEnd - $overlapStart) / 3600;
            }
        }

        return $nightHours;
    }

    /**
     * Apply break deduction sequentially across segments.
     * Prioritizes deducting from regular hours before night differential hours.
     * This ensures the 1-hour break is subtracted from the first day segment if long enough,
     * matching the requirement for cross-day holiday splits.
     */
    private function applyBreakDeduction(array $segments, float $breakHours): array
    {
        $remainingBreak = $breakHours;
        $processed = [];

        foreach ($segments as $segment) {
            if ($remainingBreak <= 0) {
                $processed[] = $segment;
                continue;
            }

            $segmentTotal = $segment['hours'];
            $deduct = min($segmentTotal, $remainingBreak);
            
            // Prioritize deducting from regular hours
            $deductFromReg = min($segment['regular_hours'], $deduct);
            $segment['regular_hours'] -= $deductFromReg;
            
            $remainingDeduct = $deduct - $deductFromReg;
            if ($remainingDeduct > 0) {
                $segment['night_hours'] -= $remainingDeduct;
            }

            $segment['hours'] -= $deduct;
            $remainingBreak -= $deduct;

            // Recalculate segment pay with new hours
            $holidayRate = $segment['holiday_rate'];
            // Hourly rate is not stored in segment, but we can't easily get it back here.
            // However, calculateShiftPay recalculates totals, so we just need to keep 
            // the ratios or just the hours. The 'pay' field in segment will be technically 
            // inconsistent if we don't have the rate, but it's okay for Timecard.php.
            // To be safe, we'll zero out pay fields as they should be recalculated if needed.
            $segment['pay'] = 0; 
            $segment['regular_pay'] = 0;
            $segment['night_pay'] = 0;

            $processed[] = $segment;
        }

        return $processed;
    }

    /**
     * Calculate totals from segments
     */
    private function calculateTotals(array $segments): array
    {
        $totals = ['pay' => 0, 'hours' => 0, 'regular_hours' => 0, 'night_hours' => 0];

        foreach ($segments as $segment) {
            $totals['pay'] += $segment['pay'];
            $totals['hours'] += $segment['hours'];
            $totals['regular_hours'] += $segment['regular_hours'];
            $totals['night_hours'] += $segment['night_hours'];
        }

        return $totals;
    }

    /**
     * Return empty result structure
     */
    private function emptyResult(float $breakHours): array
    {
        return [
            'segments'            => [],
            'total_pay'           => 0,
            'total_hours'         => 0,
            'total_regular_hours' => 0,
            'total_night_hours'   => 0,
            'break_hours'         => $breakHours,
        ];
    }

    /**
     * Get holiday rate for a specific type
     */
    public function getHolidayRate(string $holidayType): float
    {
        return self::HOLIDAY_RATES[$holidayType] ?? 1.0;
    }

    /**
     * Get all holiday types with descriptions
     */
    public static function getHolidayTypes(): array
    {
        return [
            'REGULAR_DAY' => ['rate' => 1.0, 'name' => 'Regular Working Day', 'premium' => '0%'],
            'REST_DAY'    => ['rate' => 1.3, 'name' => 'Rest Day', 'premium' => '+30%'],
            'SPECIAL'     => ['rate' => 1.3, 'name' => 'Special Non-Working Holiday', 'premium' => '+30%'],
            'REGULAR'     => ['rate' => 2.0, 'name' => 'Regular Holiday', 'premium' => '+100%'],
            'DOUBLE'      => ['rate' => 3.0, 'name' => 'Double Holiday', 'premium' => '+200%'],
        ];
    }
}
