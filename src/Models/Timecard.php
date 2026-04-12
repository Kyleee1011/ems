<?php
namespace App\Models;

use PDO;
use DateTime;
use DatePeriod;
use DateInterval;
use App\Utils\AppHelpers;
use App\Services\Payroll\HolidayCalculator;
use App\Services\Payroll\ShiftPayrollCalculator;

class Timecard
{
    protected $pdo;
    protected $holidayCalculator;
    protected $shiftPayrollCalculator;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->holidayCalculator = new HolidayCalculator($pdo);
        $this->shiftPayrollCalculator = new ShiftPayrollCalculator($pdo);
    }

    public function getSchedule($acNo, $startDate, $endDate)
    {
        $data = [];
        $sqlSched = "SELECT schedule_date, shift_code, time_in, time_out FROM finalized_schedule WHERE ac_no = ? AND schedule_date BETWEEN ? AND ?";
        $stmt = $this->pdo->prepare($sqlSched);
        $stmt->execute([$acNo, $startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dStr = AppHelpers::normalizeDate($row['schedule_date']);
            $data[$dStr] = $this->formatScheduleRow($row);
        }

        // Overlay approved schedule changes (safety net for any missed updates)
        $sqlChanges = "SELECT r.schedule_date, r.new_shift_code, st.time_in, st.time_out
                       FROM schedule_change_requests r
                       JOIN employees e ON r.emp_id = e.emp_id
                       LEFT JOIN shift_types st ON st.shift_code = r.new_shift_code
                       WHERE e.ac_no = ? AND r.schedule_date BETWEEN ? AND ? AND r.status = 'Approved'
                       ORDER BY r.approval_date DESC";
        $stmtChanges = $this->pdo->prepare($sqlChanges);
        $stmtChanges->execute([$acNo, $startDate, $endDate]);
        
        while ($row = $stmtChanges->fetch(PDO::FETCH_ASSOC)) {
            $dStr = AppHelpers::normalizeDate($row['schedule_date']);
            $existingCode = $data[$dStr]['code'] ?? null;
            
            // Only overlay if the current schedule doesn't match the approved change
            if ($existingCode !== $row['new_shift_code']) {
                $data[$dStr] = $this->formatScheduleRow([
                    'shift_code' => $row['new_shift_code'],
                    'time_in'    => $row['time_in'],
                    'time_out'   => $row['time_out']
                ]);
            }
        }

        if (empty($data)) {
            // Draft Fallback
            $sqlDraft = "SELECT es.schedule_date, st.shift_code, st.time_in, st.time_out 
                 FROM schedules es
                 JOIN employees e ON es.employee_id = e.emp_id
                 LEFT JOIN shift_types st ON es.shift_type_id = st.id
                 WHERE e.ac_no = ? AND es.schedule_date BETWEEN ? AND ?";
            $stmtDraft = $this->pdo->prepare($sqlDraft);
            $stmtDraft->execute([$acNo, $startDate, $endDate]);
            while ($row = $stmtDraft->fetch(PDO::FETCH_ASSOC)) {
                 $dStr = AppHelpers::normalizeDate($row['schedule_date']);
                 $formatted = $this->formatScheduleRow($row);
                 $formatted['remarks'] = '(Draft)';
                 $data[$dStr] = $formatted;
            }
        }
        return $data;
    }

    private function formatScheduleRow($row)
    {
        $code = $row['Shift_code'] ?? $row['shift_code'];
        $tInVal = $row['Time_In'] ?? $row['time_in'];
        $tOutVal = $row['Time_Out'] ?? $row['time_out'];
        
        $tIn = $tInVal ? ($tInVal instanceof DateTime ? $tInVal : new DateTime($tInVal)) : null;
        $tOut = $tOutVal ? ($tOutVal instanceof DateTime ? $tOutVal : new DateTime($tOutVal)) : null;

        return [
            'code' => $code,
            'sched_in_obj' => $tIn,
            'sched_out_obj' => $tOut,
            'sched_in' => $tIn ? $tIn->format('H:i') : null,
            'sched_out' => $tOut ? $tOut->format('H:i') : null,
            'remarks' => ''
        ];
    }

    public function getRawLogs($acNo, $startDate, $endDate)
    {
        // Fetch wider range (-1 day to +2 days) to cover night shift crossovers
        $sqlLogs = "SELECT c.ID as LogID, c.CHECKTIME as LogTime, c.VERIFYCODE as VerifyCode 
                    FROM biometric_logs.checkinout c
                    JOIN biometric_logs.userinfo u ON c.USERID = u.USERID
                    WHERE u.BADGENUMBER = ? 
                    AND c.CHECKTIME BETWEEN DATE_ADD(?, INTERVAL -1 DAY) AND DATE_ADD(?, INTERVAL 2 DAY) 
                    ORDER BY c.CHECKTIME ASC";
                    
        $stmt = $this->pdo->prepare($sqlLogs);
        $stmt->execute([$acNo, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdjustments($acNo, $startDate, $endDate)
    {
        $sqlAdj = "SELECT adj_date, adj_type, time_value FROM timecard_adjustments WHERE ac_no = ? AND adj_date BETWEEN ? AND ?";
        $stmtAdj = $this->pdo->prepare($sqlAdj);
        $stmtAdj->execute([$acNo, $startDate, $endDate]);
        $adjustments = [];
        while($row = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
            $adjustments[$row['adj_date']][$row['adj_type']] = $row['time_value'];
        }
        return $adjustments;
    }

    /**
     * Calculate the number of night differential minutes between two timestamps.
     * Night differential window: 10:00 PM - 6:00 AM.
     * Uses a 3-window approach to handle shifts crossing midnight.
     */
    private function calculateNightDiffMinutes(int $tsIn, int $tsOut): int
    {
        if ($tsIn >= $tsOut) return 0;

        $totalNdSeconds = 0;
        $checkDate = new DateTime("@{$tsIn}");
        // Use the default timezone from php config
        $checkDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        for ($i = -1; $i <= 1; $i++) {
            // Night window: 10 PM of day+i to 6 AM of day+i+1
            $ndStart = (clone $checkDate)->modify("$i day")->setTime(22, 0, 0);
            $ndEnd   = (clone $checkDate)->modify(($i + 1) . " day")->setTime(6, 0, 0);

            $overlap = max(0, min($tsOut, $ndEnd->getTimestamp()) - max($tsIn, $ndStart->getTimestamp()));
            if ($overlap > 0) {
                $totalNdSeconds += $overlap;
            }
        }

        return (int)floor($totalNdSeconds / 60);
    }

    /**
     * Initialize all classification fields with zero values.
     */
    private function initClassification(): array
    {
        return [
            'night_diff_mins'              => 0,
            'regular_hrs'                  => 0,
            'regular_ot_hrs'               => 0,
            'nd_hrs'                       => 0,
            'nd_ot_hrs'                    => 0,
            'rest_day_hrs'                 => 0,
            'rest_day_ot_hrs'              => 0,
            'regular_holiday_hrs'          => 0,
            'regular_holiday_ot_hrs'       => 0,
            'special_holiday_hrs'          => 0,
            'special_holiday_ot_hrs'       => 0,
            'double_holiday_hrs'           => 0,
            'double_holiday_ot_hrs'        => 0,
            'nd_regular_holiday_hrs'       => 0,
            'nd_special_holiday_hrs'       => 0,
            'nd_double_holiday_hrs'        => 0,
            'nd_rest_day_hrs'              => 0,
            'nd_rest_day_regular_holiday_hrs' => 0,
            'nd_rest_day_special_holiday_hrs' => 0,
            'rest_day_regular_holiday_hrs' => 0,
            'rest_day_special_holiday_hrs' => 0,
            'day_type'                     => 'REGULAR_DAY',
            'is_rest_day'                  => false,
        ];
    }

    /**
     * Classify worked hours into the correct buckets based on day type, rest day, OT, and night diff.
     */
    private function classifyHours(float $totalHours, float $otHours, int $ndMins, string $dayType, bool $isRestDay): array
    {
        $cls = $this->initClassification();
        $cls['night_diff_mins'] = $ndMins;
        $cls['day_type'] = $dayType;
        $cls['is_rest_day'] = $isRestDay;

        if ($totalHours <= 0) return $cls;

        $regularHours = max(0, $totalHours - $otHours);
        $ndHours = round($ndMins / 60, 2);

        // Determine which bucket to place hours in
        if ($isRestDay && $dayType !== HolidayCalculator::DAY_TYPE_ORDINARY) {
            // Rest day + holiday combo
            $key = match($dayType) {
                HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY => 'rest_day_regular_holiday',
                HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY => 'rest_day_special_holiday',
                default => 'rest_day',
            };
            $cls[$key . '_hrs'] = round($regularHours, 2);
            if ($otHours > 0) {
                // OT goes to rest_day_ot for combined scenarios
                $cls['rest_day_ot_hrs'] = round($otHours, 2);
            }
        } elseif ($isRestDay) {
            // Pure rest day
            $cls['rest_day_hrs'] = round($regularHours, 2);
            $cls['rest_day_ot_hrs'] = round($otHours, 2);
        } elseif ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
            $cls['regular_holiday_hrs'] = round($regularHours, 2);
            $cls['regular_holiday_ot_hrs'] = round($otHours, 2);
        } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
            $cls['special_holiday_hrs'] = round($regularHours, 2);
            $cls['special_holiday_ot_hrs'] = round($otHours, 2);
        } elseif ($dayType === HolidayCalculator::DAY_TYPE_DOUBLE_HOLIDAY) {
            $cls['double_holiday_hrs'] = round($regularHours, 2);
            $cls['double_holiday_ot_hrs'] = round($otHours, 2);
        } else {
            // Regular day
            $cls['regular_hrs'] = round($regularHours, 2);
            $cls['regular_ot_hrs'] = round($otHours, 2);
        }

        // Night differential breakdown — split ND hours by day type and rest day status
        if ($ndHours > 0) {
            if ($isRestDay) {
                if ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                    $cls['nd_rest_day_regular_holiday_hrs'] = round($ndHours, 2);
                } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
                    $cls['nd_rest_day_special_holiday_hrs'] = round($ndHours, 2);
                } else {
                    $cls['nd_rest_day_hrs'] = round($ndHours, 2);
                }
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                $cls['nd_regular_holiday_hrs'] = round($ndHours, 2);
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
                $cls['nd_special_holiday_hrs'] = round($ndHours, 2);
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_DOUBLE_HOLIDAY) {
                $cls['nd_double_holiday_hrs'] = round($ndHours, 2);
            } else {
                // Regular day ND: split into regular ND and ND-OT
                // ND during OT = min(ndHours, otHours). Remainder is regular ND.
                $ndInOt = ($otHours > 0) ? min($ndHours, $otHours) : 0;
                $ndInRegular = $ndHours - $ndInOt;
                $cls['nd_hrs'] = round($ndInRegular, 2);
                $cls['nd_ot_hrs'] = round($ndInOt, 2);
            }
        }

        return $cls;
    }

    /**
     * Classify hours for cross-day shifts — splits at midnight and applies
     * the correct holiday type to each segment.
     * 
     * @param int    $tsIn       Actual clock-in timestamp
     * @param int    $tsOut      Actual clock-out timestamp
     * @param float  $totalHours Total paid hours (after flexi/cap logic)
     * @param float  $otHours    Approved OT hours
     * @param string $schedDate  Schedule date string 'Y-m-d'
     * @param bool   $isRestDay  Whether the schedule date is a rest day
     * @param int|null $ndLimitTsIn Optional: Original schedule IN for ND limit
     * @param int|null $ndLimitTsOut Optional: Original schedule OUT for ND limit
     * @return array Merged classification buckets
     */
    private function classifyHoursCrossDay(
        int $tsIn, 
        int $tsOut, 
        float $totalHours, 
        float $otHours, 
        string $schedDate, 
        bool $isRestDay,
        ?int $ndLimitTsIn = null,
        ?int $ndLimitTsOut = null,
        int $utMins = 0
    ): array {
        // 1. Calculate Shift using the unified Engine
        $breakHours = ($totalHours > 5 && $utMins <= 120) ? 1.0 : 0.0;
        
        $effectiveStart = new DateTime("@$tsIn");
        $effectiveStart->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $effectiveEnd = new DateTime("@$tsOut");
        $effectiveEnd->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        $ndLimitStart = null;
        if ($ndLimitTsIn) {
            $ndLimitStart = new DateTime("@$ndLimitTsIn");
            $ndLimitStart->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        $ndLimitEnd = null;
        if ($ndLimitTsOut) {
            $ndLimitEnd = new DateTime("@$ndLimitTsOut");
            $ndLimitEnd->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        $shiftResult = $this->shiftPayrollCalculator->calculateShiftPay(
            1.0, 
            $effectiveStart,
            $effectiveEnd,
            $breakHours,
            $ndLimitStart,
            $ndLimitEnd
        );

        $merged = $this->initClassification();
        $merged['night_diff_mins'] = (int)round($shiftResult['total_night_hours'] * 60);
        $merged['is_rest_day'] = $isRestDay;
        
        // Compound label for UI if multiple holidays involved
        $types = [];

        // 2. Aggregate each segment into the correct Timecard buckets
        foreach ($shiftResult['segments'] as $seg) {
            $dayType = $seg['holiday_type'];
            $types[] = $dayType;
            
            $segTotal = $seg['hours'];
            // OT handling: For cross-day, OT is usually the tail end.
            // Distribute OT from the end backwards if needed, or just use the same ratio.
            $segOt = round($otHours * ($segTotal / $totalHours), 2);
            $segReg = round($segTotal - $segOt, 2);
            $segND = $seg['night_hours'];

            // Map to buckets (Simplified version of classifyHours logic)
            if ($isRestDay && $dayType !== HolidayCalculator::DAY_TYPE_ORDINARY) {
                $key = match($dayType) {
                    HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY => 'rest_day_regular_holiday',
                    HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY => 'rest_day_special_holiday',
                    default => 'rest_day',
                };
                $merged[$key . '_hrs'] += $segReg;
                $merged['rest_day_ot_hrs'] += $segOt;
            } elseif ($isRestDay) {
                $merged['rest_day_hrs'] += $segReg;
                $merged['rest_day_ot_hrs'] += $segOt;
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                $merged['regular_holiday_hrs'] += $segReg;
                $merged['regular_holiday_ot_hrs'] += $segOt;
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
                $merged['special_holiday_hrs'] += $segReg;
                $merged['special_holiday_ot_hrs'] += $segOt;
            } elseif ($dayType === HolidayCalculator::DAY_TYPE_DOUBLE_HOLIDAY) {
                $merged['double_holiday_hrs'] += $segReg;
                $merged['double_holiday_ot_hrs'] += $segOt;
            } else {
                $merged['regular_hrs'] += $segReg;
                $merged['regular_ot_hrs'] += $segOt;
            }

            // Night Diff Buckets
            if ($segND > 0) {
                if ($isRestDay) {
                    if ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                        $merged['nd_rest_day_regular_holiday_hrs'] += $segND;
                    } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
                        $merged['nd_rest_day_special_holiday_hrs'] += $segND;
                    } else {
                        $merged['nd_rest_day_hrs'] += $segND;
                    }
                } elseif ($dayType === HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                    $merged['nd_regular_holiday_hrs'] += $segND;
                } elseif ($dayType === HolidayCalculator::DAY_TYPE_SPECIAL_HOLIDAY) {
                    $merged['nd_special_holiday_hrs'] += $segND;
                } elseif ($dayType === HolidayCalculator::DAY_TYPE_DOUBLE_HOLIDAY) {
                    $merged['nd_double_holiday_hrs'] += $segND;
                } else {
                    $ndInOt = ($segOt > 0) ? min($segND, $segOt) : 0;
                    $ndInRegular = $segND - $ndInOt;
                    $merged['nd_hrs'] += $ndInRegular;
                    $merged['nd_ot_hrs'] += $ndInOt;
                }
            }
        }


        // Clean up UI labels
        $uniqueTypes = array_unique($types);
        $merged['day_type'] = implode('/', $uniqueTypes);

        // Final rounding
        foreach ($merged as $k => $v) {
            if (is_numeric($v) && $k !== 'night_diff_mins') {
                $merged[$k] = round($v, 2);
            }
        }

        return $merged;
    }

    /**
     * Get approved overtime applications for an employee within a date range.
     * Returns an associative array keyed by date: ['2026-02-22' => 2.0, ...]
     */
    private function getApprovedOT(string $acNo, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ot.ot_date, ot.ot_hours
             FROM overtime_applications ot
             JOIN employees e ON ot.emp_id = e.emp_id
             WHERE e.ac_no = ?
             AND ot.ot_date BETWEEN ? AND ?
             AND ot.status = 'Approved'"
        );
        $stmt->execute([$acNo, $startDate, $endDate]);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['ot_date']] = (float)$row['ot_hours'];
        }
        return $result;
    }

    public function generateDtr($acNo, $startDate, $endDate)
    {
        $data = [];
        $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
        
        $schedData = $this->getSchedule($acNo, $startDate, $endDate);
        $timelineLogs = $this->getRawLogs($acNo, $startDate, $endDate); 
        $adjustments = $this->getAdjustments($acNo, $startDate, $endDate);
        $approvedOT = $this->getApprovedOT($acNo, $startDate, $endDate);
        $usedLogIds = [];

        foreach ($period as $dt) {
            $dStr = $dt->format('Y-m-d');
            $sched = $schedData[$dStr] ?? ['code' => '-', 'sched_in' => null, 'sched_out' => null, 'remarks' => ''];
            
            $day = [
                'date' => $dStr,
                'day' => $dt->format('l'),
                'sched_code' => $sched['code'],
                'sched_in' => $sched['sched_in'],
                'sched_out' => $sched['sched_out'],
                'actual_in' => null, 'actual_out' => null,
                'is_manual_in' => false, 'is_manual_out' => false,
                'hours' => 0, 'late_mins' => 0, 'ut_mins' => 0, 'ot_hours' => 0, 'ot_status' => 'none',
                'remarks' => $sched['remarks'],
                'status_color' => ''
            ];

            // 1. ESTABLISH TARGET TIMES
            $tsSchedIn = $sched['sched_in'] ? strtotime("$dStr " . $sched['sched_in']) : strtotime("$dStr 08:00:00");
            $tsSchedOut = $sched['sched_out'] ? strtotime("$dStr " . $sched['sched_out']) : strtotime("$dStr 17:00:00");

            // Handle Night Shift Logic (In > Out)
            if ($tsSchedOut < $tsSchedIn) {
                $tsSchedOut += 86400; // Add 24 hours to Sched Out
            }

            // Define Windows
            // IN: 4 hours before sched to 10 hours after (widened to accommodate early arrivals)
            $inWindowStart = $tsSchedIn - (3600 * 4);
            $inWindowEnd   = $tsSchedIn + (3600 * 10);
            
            // Out Window: Sched Out +/- 12 hours (kept wide for OT, but validated by distance)
            $outWindowStart = $tsSchedOut - (3600 * 10); 
            $outWindowEnd   = $tsSchedOut + (3600 * 12);

            // ---------------------------------------------------------
            // 2. FIND "IN" LOG (Best Match Strategy)
            // ---------------------------------------------------------
            $foundInLog = null;
            $bestInDiff = PHP_INT_MAX;
            
            foreach ($timelineLogs as $log) {
                if (in_array($log['LogID'], $usedLogIds)) continue;

                $logTs = strtotime($log['LogTime']);
                
                if ($logTs >= $inWindowStart && $logTs <= $inWindowEnd) {
                    $type = $this->classifyLogType($log['VerifyCode'], $logTs, $tsSchedIn, $tsSchedOut);
                    
                    if ($type === 'IN') {
                        $diff = abs($logTs - $tsSchedIn);
                        if ($diff < $bestInDiff) {
                            $bestInDiff = $diff;
                            $foundInLog = $log;
                        }
                    }
                }
            }

            if ($foundInLog) {
                $day['actual_in'] = (new DateTime($foundInLog['LogTime']))->format('H:i');
                $usedLogIds[] = $foundInLog['LogID'];
                
                // ADJUST Out Window: Cannot be before IN
                $outWindowStart = strtotime($foundInLog['LogTime']);
            }

            // ---------------------------------------------------------
            // 3. FIND "OUT" LOG (Best Match Strategy)
            // ---------------------------------------------------------
            $foundOutLog = null;
            $bestOutDiff = PHP_INT_MAX;

            foreach ($timelineLogs as $log) {
                if (in_array($log['LogID'], $usedLogIds)) continue;

                $logTs = strtotime($log['LogTime']);

                if ($logTs > $outWindowStart && $logTs <= $outWindowEnd) {
                    $type = $this->classifyLogType($log['VerifyCode'], $logTs, $tsSchedIn, $tsSchedOut);

                    if ($type === 'OUT') {
                        $diff = abs($logTs - $tsSchedOut);
                        if ($diff < $bestOutDiff) {
                            $bestOutDiff = $diff;
                            $foundOutLog = $log;
                        }
                    }
                }
            }

            if ($foundOutLog) {
                $day['actual_out'] = (new DateTime($foundOutLog['LogTime']))->format('H:i');
                $usedLogIds[] = $foundOutLog['LogID'];
            }

            // ---------------------------------------------------------
            // 4. MANUAL & CALCULATIONS
            // ---------------------------------------------------------
            if (isset($adjustments[$dStr]['IN'])) { $day['actual_in'] = $adjustments[$dStr]['IN']; $day['is_manual_in'] = true; }
            if (isset($adjustments[$dStr]['OUT'])) { $day['actual_out'] = $adjustments[$dStr]['OUT']; $day['is_manual_out'] = true; }

            // Determine day type using HolidayCalculator
            $dayType = $this->holidayCalculator->getDayType($dStr);
            $isOff = in_array($day['sched_code'], ['OFF', 'HOLIDAY OFF', 'REST DAY']);
            $isRestDay = in_array($day['sched_code'], ['OFF', 'REST DAY', 'RD']);
            $hasLog = !empty($day['actual_in']);

            if (!$hasLog) {
                 // Do not mark as ABSENT if it's an OFF day
                 if (!$isOff && $day['sched_code'] !== '-') { 
                     $day['remarks'] = "ABSENT"; 
                     $day['status_color'] = 'bg-red-50 text-red-700'; 
                 }
            } else {
                if (!empty($day['actual_out'])) {
                    $tsIn = strtotime("$dStr " . $day['actual_in']);
                    $tsOut = strtotime("$dStr " . $day['actual_out']);
                    
                    // If OUT < IN (Night shift scenario), assume next day
                    if ($tsOut < $tsIn) $tsOut += 86400;

                    $day['hours'] = number_format(($tsOut - $tsIn) / 3600, 2);
                    
                    // Late/UT/OT Logic - Punch-Based Transparency Fix
                    if (!$isOff && $day['sched_in']) {
                        $tsSchedInActual = strtotime("$dStr " . $day['sched_in']);
                        $tsSchedOutActual = strtotime("$dStr " . $day['sched_out']);
                        if ($tsSchedOutActual < $tsSchedInActual) $tsSchedOutActual += 86400; 
                        
                        // 1. Calculate LATE (Actual In vs Sched In)
                        // Snap to 0 if they timed in early or exactly on time
                        $day['late_mins'] = ($tsIn > $tsSchedInActual) ? floor(($tsIn - $tsSchedInActual) / 60) : 0;

                        // 2. Calculate UNDERTIME (Actual Out vs Sched Out)
                        // Only counts if they leave BEFORE the scheduled out time
                        $day['ut_mins'] = ($tsOut < $tsSchedOutActual) ? floor(($tsSchedOutActual - $tsOut) / 60) : 0;

                        // 3. Calculate TOTAL PAID HOURS & OT
                        $expectedGross = ($tsSchedOutActual - $tsSchedInActual) / 3600;
                        $rawGross = ($tsOut - $tsIn) / 3600;

                        // Calculate OT (Work performed BEYOND the scheduled gross hours)
                        // Note: actual_in is capped by effectiveTsIn for paid time, 
                        // but OT is usually based on staying LATER than sched_out.
                        $maxApprovedOT = $approvedOT[$dStr] ?? 0;
                        
                        if ($tsOut > $tsSchedOutActual) {
                            $rawOT = ($tsOut - $tsSchedOutActual) / 3600;
                            $appliedOT = min($rawOT, $maxApprovedOT);
                            $day['ot_hours'] = number_format($appliedOT, 2);
                            $day['ot_status'] = ($maxApprovedOT > 0) ? 'approved' : 'unapproved';
                        } else {
                            $day['ot_hours'] = 0;
                            $day['ot_status'] = 'none';
                        }

                        // Final Paid Hours = (Expected Gross - Late - UT) + Approved OT
                        // Convert mins to hours
                        $deductions = ($day['late_mins'] + $day['ut_mins']) / 60;
                        $paidBase = max(0, $expectedGross - $deductions);
                        $totalPaid = $paidBase + (float)$day['ot_hours'];
                        
                        $day['hours'] = number_format($totalPaid, 2);
                    }

                    // ---------------------------------------------------------
                    // 5. CLASSIFICATION — Night Diff & Hour Type Breakdown
                    //    SCHEDULE-BASED EFFECTIVE WINDOW
                    //    ND, holiday type, and cross-day splits are all determined
                    //    by the scheduled shift boundaries, NOT actual punch times.
                    //    This ensures:
                    //    - ND is only counted within the scheduled shift (+approved OT)
                    //    - Cross-midnight schedules correctly produce ND
                    //    - Cross-day holiday pay splits at midnight based on schedule
                    // ---------------------------------------------------------
                    $totalHrsFloat = (float)$day['hours'];
                    $otHrsFloat = (float)$day['ot_hours'];

                    // Build the SCHEDULE-BASED effective window for classification
                    $ndLimitTsIn = null;
                    $ndLimitTsOut = null;

                    if (!$isOff && $day['sched_in'] && $day['sched_out']) {
                        // Use schedule boundaries as the classification window
                        $classifyTsIn = strtotime("$dStr " . $day['sched_in']);
                        $classifyTsOut = strtotime("$dStr " . $day['sched_out']);
                        // Handle cross-midnight schedule (e.g., 17:00 - 02:00)
                        if ($classifyTsOut <= $classifyTsIn) {
                            $classifyTsOut += 86400;
                        }

                        // Store original schedule boundaries for ND restriction
                        $ndLimitTsIn = $classifyTsIn;
                        $ndLimitTsOut = $classifyTsOut;

                        // Extend classification window by approved OT (beyond sched_out)
                        $approvedOTHours = (float)$day['ot_hours'];
                        if ($approvedOTHours > 0) {
                            $classifyTsOut += (int)($approvedOTHours * 3600);
                        }
                    } else {
                        // OFF day or no schedule: fall back to actual punches
                        $classifyTsIn = $tsIn;
                        $classifyTsOut = $tsOut;
                    }

                    $effectiveStart = new DateTime("@$classifyTsIn");
                    $effectiveStart->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    $effectiveEnd = new DateTime("@$classifyTsOut");
                    $effectiveEnd->setTimezone(new \DateTimeZone(date_default_timezone_get()));

                    $ndLimitStart = $ndLimitTsIn ? (new DateTime("@$ndLimitTsIn"))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : null;
                    $ndLimitEnd = $ndLimitTsOut ? (new DateTime("@$ndLimitTsOut"))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : null;

                    // Break: 1-hour if > 5 hours, no break if undertime > 120 mins
                    $breakHours = ($totalHrsFloat > 5 && $day['ut_mins'] <= 120) ? 1.0 : 0.0;
                    
                    $shiftResult = $this->shiftPayrollCalculator->calculateShiftPay(
                        1.0, 
                        $effectiveStart,
                        $effectiveEnd,
                        $breakHours,
                        $ndLimitStart,
                        $ndLimitEnd
                    );

                    $ndHours = $shiftResult['total_night_hours'];
                    $ndMins = (int)round($ndHours * 60);

                    // Cross-day detection: does the schedule window cross midnight?
                    $midnightTs = strtotime("$dStr +1 day 00:00:00");
                    if ($classifyTsOut > $midnightTs) {
                        $classification = $this->classifyHoursCrossDay($classifyTsIn, $classifyTsOut, $totalHrsFloat, $otHrsFloat, $dStr, $isRestDay, $ndLimitTsIn, $ndLimitTsOut, $day['ut_mins']);
                    } else {
                        // Deduct mandatory break for paid-hour classification
                        // (cross-day path already does this via ShiftPayrollCalculator)
                        $paidHrsFloat = max(0, $totalHrsFloat - $breakHours);
                        $paidOtFloat = min($otHrsFloat, $paidHrsFloat);
                        $classification = $this->classifyHours($paidHrsFloat, $paidOtFloat, $ndMins, $dayType, $isRestDay);
                    }
                    $day = array_merge($day, $classification);
                    
                    // Deduct break from total display hours to match classification logic
                    $currentTotal = (float)$day['hours'];
                    if ($currentTotal > 5 && $day['ut_mins'] <= 120) {
                        $day['hours'] = number_format($currentTotal - 1.0, 2);
                    }
                    
                    if ($day['sched_in'] === '17:00') {
                        $debOut = sprintf("[%s] in=%d, out=%d, isOff=%d, ndMinsCalc=%d, classifiedND=%d\n",
                            $dStr, $classifyTsIn, $classifyTsOut, $isOff, $ndMins, $day['night_diff_mins'] ?? -1);
                        file_put_contents(__DIR__ . '/../../debug_timecard.txt', $debOut, FILE_APPEND);
                    }
                }
            }

            // Add empty classification if not already set (for days without logs)
            if (!isset($day['night_diff_mins'])) {
                $day = array_merge($day, $this->initClassification());
                $day['day_type'] = $dayType;
                $day['is_rest_day'] = $isRestDay ?? false;
            }

            $data[$dStr] = $day;
        }

        return $data;
    }

    /**
     * INTELLIGENT CLASSIFIER
     * Determines if a log is IN or OUT based on VERIFYCODE AND Time
     * 
     * VERIFYCODE from biometric device:
     * 0 = Check IN
     * 1 = Check OUT
     * 
     * Enhanced with time-based validation to handle edge cases
     */
    private function classifyLogType($verifyCode, $logTs, $schedInTs, $schedOutTs)
    {
        // Calculate time distances for validation
        $distToIn = abs($logTs - $schedInTs);
        $distToOut = abs($logTs - $schedOutTs);
        
        // Convert VERIFYCODE to string for comparison (handles both int and string types)
        $code = (string)$verifyCode;
        
        // --- PRIMARY LOGIC: Use VERIFYCODE ---
        // 0 = IN, 1 = OUT (standard biometric device codes)
        if ($code === '0') {
            // Explicit IN from device
            // Validate: If this IN punch is much closer to OUT time, it might be misclassified
            // But generally trust the device for '0'
            if ($distToOut < $distToIn && $distToOut < ($distToIn / 3)) {
                // Very unusual case: device says IN but time suggests OUT
                return 'OUT';
            }
            return 'IN';
        }
        
        if ($code === '1') {
            // Explicit OUT from device
            // SMART OVERRIDE: If punch is very close to Sched IN and far from Sched OUT,
            // it's likely the start of shift (user may have pressed wrong button)
            if ($distToIn < (90 * 60)) { // Within 90 mins of scheduled start
                if ($distToOut > ($distToIn * 2)) {
                    // Much closer to IN than OUT - override to IN
                    return 'IN';
                }
            }
            return 'OUT';
        }
        
        // --- FALLBACK: Unknown/NULL VERIFYCODE ---
        // Use pure time-based classification
        if ($distToIn < $distToOut) {
            return 'IN';
        } else {
            return 'OUT';
        }
    }

    public function updateManualLog($acNo, $date, $type, $time)
    {
         $check = $this->pdo->prepare("SELECT id FROM timecard_adjustments WHERE ac_no=? AND adj_date=? AND adj_type=?");
         $check->execute([$acNo, $date, $type]);
         $exists = $check->fetch();
 
         if ($exists) {
             $sql = "UPDATE timecard_adjustments SET time_value = ?, updated_at = NOW() WHERE id = ?";
             return $this->pdo->prepare($sql)->execute([$time, $exists['id']]);
         } else {
             $sql = "INSERT INTO timecard_adjustments (ac_no, adj_date, adj_type, time_value) VALUES (?, ?, ?, ?)";
             return $this->pdo->prepare($sql)->execute([$acNo, $date, $type, $time]);
         }
    }

    /**
     * Get late and absent summaries for ALL employees with finalized schedules
     * in the given date range. Uses the EXACT same logic as generateDtr() so
     * the dashboard matches the timecard 100%.
     *
     * Returns:
     *   [
     *     'late'   => [ ['name', 'department', 'date', 'sched_in', 'actual_in', 'late_mins'], ... ],
     *     'absent' => [ ['name', 'department', 'date'], ... ],
     *     'late_tally'   => [ ['name', 'department', 'late_count', 'total_late_mins'], ... ],
     *     'absent_tally' => [ ['name', 'department', 'absent_count'], ... ],
     *   ]
     */
    /**
     * Identify "broken" logs (e.g., missing clock-outs or clock-ins) for audit.
     */
    public function getBrokenLogs($startDate, $endDate)
    {
        $stmt = $this->pdo->query("SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, d.dept_name 
                                   FROM employees e 
                                   LEFT JOIN departments d ON e.dept_id = d.dept_id 
                                   WHERE e.employee_status = 'Active' AND e.IsActive = 1
                                   ORDER BY d.dept_name, e.last_name");
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $broken = [];
        foreach ($employees as $emp) {
            $dtr = $this->generateDtr($emp['ac_no'], $startDate, $endDate);
            foreach ($dtr['days'] as $dateStr => $day) {
                // Skip if it's an OFF day or no schedule
                if (empty($day['sched_code']) || $day['sched_code'] === '-' || $day['sched_code'] === 'OFF') continue;
                
                $hasIn = !empty($day['actual_in']);
                $hasOut = !empty($day['actual_out']);

                if (($hasIn && !$hasOut) || (!$hasIn && $hasOut)) {
                    $broken[] = [
                        'emp_id' => $emp['emp_id'],
                        'ac_no' => $emp['ac_no'],
                        'name' => $emp['first_name'] . ' ' . $emp['last_name'],
                        'dept' => $emp['dept_name'] ?? 'Unassigned',
                        'date' => $dateStr,
                        'sched' => $day['sched_in'] . ' - ' . $day['sched_out'],
                        'actual_in' => $day['actual_in'] ?? '--:--',
                        'actual_out' => $day['actual_out'] ?? '--:--',
                        'issue' => ($hasIn && !$hasOut) ? 'Missing Clock-out' : 'Missing Clock-in'
                    ];
                }
            }
        }
        return $broken;
    }

    public function getAttendanceSummary(string $startDate, string $endDate): array
    {
        // 1. Get all employees with finalized schedules in this range
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT fs.ac_no, fs.name, fs.department
             FROM finalized_schedule fs
             WHERE fs.schedule_date BETWEEN ? AND ?
             AND fs.time_in IS NOT NULL
             ORDER BY fs.name"
        );
        $stmt->execute([$startDate, $endDate]);
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lateList   = [];
        $absentList = [];
        $lateTally  = [];
        $absentTally = [];

        foreach ($employees as $emp) {
            $acNo = $emp['ac_no'];
            $name = $emp['name'];
            $dept = $emp['department'];

            // 2. Run the exact same generateDtr logic for this employee
            $dtr = $this->generateDtr($acNo, $startDate, $endDate);

            $empLateDays   = 0;
            $empLateMins   = 0;
            $empAbsentDays = 0;

            foreach ($dtr as $dateStr => $day) {
                // Skip future dates — timecard only shows past/today
                if ($dateStr > date('Y-m-d')) continue;

                // LATE: same as timecard — late_mins > 0
                if (!empty($day['late_mins']) && $day['late_mins'] > 0) {
                    $lateList[] = [
                        'name'        => $name,
                        'department'  => $dept,
                        'date_str'    => date('D, M d', strtotime($dateStr)),
                        'scheduled_in'=> $day['sched_in'] ?? '',
                        'actual_in'   => $day['actual_in'] ?? '',
                        'minutes_late'=> (int)$day['late_mins'],
                    ];
                    $empLateDays++;
                    $empLateMins += (int)$day['late_mins'];
                }

                // ABSENT: same as timecard — remarks === 'ABSENT'
                if (isset($day['remarks']) && $day['remarks'] === 'ABSENT') {
                    $absentList[] = [
                        'name'       => $name,
                        'department' => $dept,
                        'date_str'   => date('D, M d', strtotime($dateStr)),
                    ];
                    $empAbsentDays++;
                }
            }

            if ($empLateDays > 0) {
                $lateTally[] = [
                    'name'            => $name,
                    'department'      => $dept,
                    'late_count'      => $empLateDays,
                    'total_late_mins' => $empLateMins,
                ];
            }
            if ($empAbsentDays > 0) {
                $absentTally[] = [
                    'name'         => $name,
                    'department'   => $dept,
                    'absent_count' => $empAbsentDays,
                ];
            }
        }

        // Sort: most late/absent first
        usort($lateList,   fn($a, $b) => $b['minutes_late'] <=> $a['minutes_late']);
        usort($absentList, fn($a, $b) => $a['name'] <=> $b['name']);
        usort($lateTally,  fn($a, $b) => $b['late_count'] <=> $a['late_count']);
        usort($absentTally,fn($a, $b) => $b['absent_count'] <=> $a['absent_count']);

        return [
            'late'         => array_slice($lateList,   0, 20),
            'absent'       => array_slice($absentList, 0, 50),
            'late_tally'   => array_slice($lateTally,  0, 30),
            'absent_tally' => array_slice($absentTally,0, 30),
        ];
    }
}
