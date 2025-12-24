<?php
namespace App\Models;

use PDO;
use DateTime;
use DatePeriod;
use DateInterval;
use App\Helpers\AppHelper;

class Timecard
{
    protected $emsPdo;
    protected $schedulerPdo;
    protected $biologsPdo;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
        $this->biologsPdo = AppHelper::getBiologsConnection();
    }

    public function getSchedule($acNo, $startDate, $endDate)
    {
        $data = [];
        $sqlSched = "SELECT schedule_Date, Shift_code, Time_In, Time_Out FROM [SchedulerDB].[dbo].[FinalizedSchedule] WHERE Ac_no = ? AND schedule_Date BETWEEN ? AND ?";
        $stmt = $this->schedulerPdo->prepare($sqlSched);
        $stmt->execute([$acNo, $startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dStr = AppHelper::normalizeDate($row['schedule_Date']);
            $data[$dStr] = $this->formatScheduleRow($row);
        }

        if (empty($data)) {
            // Draft
            $sqlDraft = "SELECT es.schedule_date, st.shift_code, st.time_in, st.time_out 
                 FROM schedules es
                 JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON es.employee_id = e.emp_id
                 LEFT JOIN shift_types st ON es.shift_type_id = st.id
                 WHERE e.ac_no = ? AND es.schedule_date BETWEEN ? AND ?";
            $stmtDraft = $this->schedulerPdo->prepare($sqlDraft);
            $stmtDraft->execute([$acNo, $startDate, $endDate]);
            while ($row = $stmtDraft->fetch(PDO::FETCH_ASSOC)) {
                 $dStr = AppHelper::normalizeDate($row['schedule_date']);
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
        if (!$this->biologsPdo) return [];
        $sqlLogs = "SELECT CAST(LogTime AS DATE) as LogDate, LogTime, CheckType FROM AttendanceData WHERE AC_No = ? AND CAST(LogTime AS DATE) BETWEEN ? AND DATEADD(day, 1, ?) ORDER BY LogTime ASC";
        $stmt = $this->biologsPdo->prepare($sqlLogs);
        $stmt->execute([$acNo, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }

    public function getAdjustments($acNo, $startDate, $endDate)
    {
        $sqlAdj = "SELECT adj_date, adj_type, time_value FROM TimecardAdjustments WHERE ac_no = ? AND adj_date BETWEEN ? AND ?";
        $stmtAdj = $this->schedulerPdo->prepare($sqlAdj);
        $stmtAdj->execute([$acNo, $startDate, $endDate]);
        $adjustments = [];
        while($row = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
            $adjustments[$row['adj_date']][$row['adj_type']] = $row['time_value'];
        }
        return $adjustments;
    }

    public function generateDtr($acNo, $startDate, $endDate)
    {
        $data = [];
        $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
        
        $schedData = $this->getSchedule($acNo, $startDate, $endDate);
        $rawLogs = $this->getRawLogs($acNo, $startDate, $endDate);
        $adjustments = $this->getAdjustments($acNo, $startDate, $endDate);

        $isLogTypeIn = function($type) { $t = strtolower(trim($type)); return ($t === '0' || $t === 'i' || strpos($t, 'in') !== false); };

        foreach ($period as $dt) {
            $dStr = $dt->format('Y-m-d');
            $sched = $schedData[$dStr] ?? ['code' => '-', 'sched_in' => null, 'sched_out' => null, 'sched_in_obj' => null, 'sched_out_obj' => null, 'remarks' => ''];
            
            $day = [
                'date' => $dStr,
                'day' => $dt->format('l'),
                'sched_code' => $sched['code'],
                'sched_in' => $sched['sched_in'],
                'sched_out' => $sched['sched_out'],
                'sched_in_obj' => $sched['sched_in_obj'],
                'sched_out_obj' => $sched['sched_out_obj'],
                'actual_in' => null, 'actual_out' => null,
                'is_manual_in' => false, 'is_manual_out' => false,
                'hours' => 0, 'late_mins' => 0, 'ut_mins' => 0, 'ot_hours' => 0,
                'remarks' => $sched['remarks'],
                'status_color' => ''
            ];

            // 1. Logs
            $schedInObj = $day['sched_in_obj'];
            $schedOutObj = $day['sched_out_obj'];
            $isNightShift = ($schedInObj && $schedOutObj && ($schedInObj > $schedOutObj || (int)$schedInObj->format('H') >= 20));

            $foundIn = null; $foundOut = null;
            $nextDayStr = date('Y-m-d', strtotime($dStr . ' +1 day'));

            if ($isNightShift) {
                if (isset($rawLogs[$dStr])) {
                    foreach ($rawLogs[$dStr] as $log) {
                        $time = new DateTime($log['LogTime']);
                        if ($isLogTypeIn($log['CheckType']) || (int)$time->format('H') >= 18) { $foundIn = $time->format('H:i'); break; }
                    }
                    if (!$foundIn && !empty($rawLogs[$dStr])) $foundIn = (new DateTime(end($rawLogs[$dStr])['LogTime']))->format('H:i');
                }
                if (isset($rawLogs[$nextDayStr])) {
                    foreach ($rawLogs[$nextDayStr] as $log) {
                        $time = new DateTime($log['LogTime']);
                        if ((int)$time->format('H') < 14) {
                            if (!$isLogTypeIn($log['CheckType'])) { $foundOut = $time->format('H:i'); } 
                            else { if ($foundOut === null) $foundOut = $time->format('H:i'); }
                        }
                    }
                }
            } else {
                if (isset($rawLogs[$dStr])) {
                    $logs = $rawLogs[$dStr];
                    if (count($logs) > 0) {
                        $foundIn = (new DateTime($logs[0]['LogTime']))->format('H:i');
                        if (count($logs) > 1) $foundOut = (new DateTime(end($logs)['LogTime']))->format('H:i');
                        elseif (!$isLogTypeIn($logs[0]['CheckType'])) { $foundOut = $foundIn; $foundIn = null; }
                    }
                }
            }

            if (isset($adjustments[$dStr]['IN'])) { $foundIn = $adjustments[$dStr]['IN']; $day['is_manual_in'] = true; }
            if (isset($adjustments[$dStr]['OUT'])) { $foundOut = $adjustments[$dStr]['OUT']; $day['is_manual_out'] = true; }

            $day['actual_in'] = $foundIn;
            $day['actual_out'] = $foundOut;

            // 2. Status & Remarks
            // Note: getHolidayType should ideally be a method in a HolidayService. For now, assuming Global availability or logic duplication.
            $holidayType = function_exists('getHolidayType') ? getHolidayType($this->emsPdo, $dStr) : 'REGULAR_DAY';

            $isOff = in_array($day['sched_code'], ['OFF', 'HOLIDAY OFF', 'REST DAY']);
            $isLeave = in_array($day['sched_code'], ['VL', 'SL', 'EL', 'BL', 'ML', 'PL', 'SPL']);
            $isLWOP = ($day['sched_code'] === 'LWOP');
            $hasLog = !empty($foundIn);

            if (!$hasLog) {
                if ($isLeave) {
                    $day['remarks'] = $day['sched_code'] . " (Leave)";
                    $day['status_color'] = 'bg-blue-50'; 
                } elseif ($holidayType == 'REGULAR') {
                    $day['remarks'] = "Regular Holiday";
                    $day['status_color'] = 'bg-primary-50'; 
                } elseif ($holidayType == 'SPECIAL') {
                    $day['remarks'] = "Special Holiday";
                    $day['status_color'] = 'bg-primary-50';
                } elseif ($isOff) {
                    $day['remarks'] = "Day Off";
                    $day['status_color'] = 'bg-gray-100'; 
                } elseif ($isLWOP) {
                    $day['remarks'] = "LWOP";
                    $day['status_color'] = 'bg-red-50';
                } elseif ($day['sched_code'] !== '-') {
                    $day['remarks'] = "ABSENT";
                    $day['status_color'] = 'bg-red-50 text-red-700'; 
                }
            } else {
                if ($holidayType == 'REGULAR' || $holidayType == 'SPECIAL') {
                    $day['remarks'] = $holidayType . " HOLIDAY WORK";
                } elseif ($isOff) {
                    $day['remarks'] = "Rest Day Duty";
                }
                
                // 3. Calc
                if (!empty($foundOut)) {
                    $tsIn = strtotime("$dStr $foundIn");
                    $tsOut = strtotime("$dStr $foundOut");
                    if ($tsOut < $tsIn) $tsOut += 86400;
                    $day['hours'] = number_format(($tsOut - $tsIn) / 3600, 2);
                }
        
                if (!$isOff && !$isLeave && !$isLWOP && $day['sched_code'] !== '-') {
                    $sInStr = $day['sched_in'];
                    $sOutStr = $day['sched_out'];
                    
                    $tsSchedIn = $sInStr ? strtotime("$dStr $sInStr") : null;
                    $tsSchedOut = $sOutStr ? strtotime("$dStr $sOutStr") : null;
                    if ($tsSchedOut && $tsSchedIn && $tsSchedOut < $tsSchedIn) $tsSchedOut += 86400;
        
                    if ($tsSchedIn) {
                        $tsActIn = strtotime("$dStr $foundIn");
                        if ($tsActIn > $tsSchedIn) {
                            $day['late_mins'] = floor(($tsActIn - $tsSchedIn) / 60);
                        }
                    }
                    
                    if ($tsSchedOut && !empty($foundOut)) {
                        $tsActOut = strtotime("$dStr $foundOut");
                        if ($tsActOut < strtotime("$dStr $foundIn")) $tsActOut += 86400;
        
                        if ($tsActOut < $tsSchedOut) {
                            $day['ut_mins'] = floor(($tsSchedOut - $tsActOut) / 60);
                        } 
                        elseif ($tsActOut > $tsSchedOut) {
                            $day['ot_hours'] = number_format(($tsActOut - $tsSchedOut) / 3600, 2);
                        }
                    }
                }
            }
            $data[$dStr] = $day;
        }

        return $data;
    }

    public function updateManualLog($acNo, $date, $type, $time)
    {
         $check = $this->schedulerPdo->prepare("SELECT id FROM TimecardAdjustments WHERE ac_no=? AND adj_date=? AND adj_type=?");
         $check->execute([$acNo, $date, $type]);
         $exists = $check->fetch();
 
         if ($exists) {
             $sql = "UPDATE TimecardAdjustments SET time_value = ?, updated_at = GETDATE() WHERE id = ?";
             return $this->schedulerPdo->prepare($sql)->execute([$time, $exists['id']]);
         } else {
             $sql = "INSERT INTO TimecardAdjustments (ac_no, adj_date, adj_type, time_value) VALUES (?, ?, ?, ?)";
             return $this->schedulerPdo->prepare($sql)->execute([$acNo, $date, $type, $time]);
         }
    }
}
