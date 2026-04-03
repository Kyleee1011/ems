<?php
require_once __DIR__ . '/config_session.php';

if (file_exists('payslip/get_holiday.php')) {
    require_once 'payslip/get_holiday.php';
}

require_once 'config.php';
require_once 'src/Utils/AppHelpers.php';
require_once 'src/Models/Timecard.php';
require_once 'src/Services/Payroll/HolidayCalculator.php';

use App\Utils\AppHelpers;
use App\Models\Timecard;

$currentRole = $_SESSION['approval_role'] ?? 'HR';
$isHr = true; // Bypass restriction
$myAcNo = $_SESSION['ac_no'] ?? '';
$targetAcNo = $myAcNo;
$targetName = $_SESSION['full_name'] ?? '';
$targetPosition = '';
$targetDepartment = '';

if (isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
    $targetAcNo = $_GET['search_ac'];
}

if (empty($targetAcNo)) {
    // Default to first employee
    $stmtEmp = $pdo->query("SELECT ac_no FROM employees WHERE IsActive = 1 ORDER BY last_name LIMIT 1");
    $firstEmp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if ($firstEmp) {
        $targetAcNo = $firstEmp['ac_no'];
    }
}

// Fetch details for target employee
$stmt = $pdo->prepare("SELECT e.first_name, e.last_name, e.job_title, d.dept_name 
                       FROM employees e 
                       LEFT JOIN departments d ON e.dept_id = d.dept_id 
                       WHERE e.ac_no = ?");
$stmt->execute([$targetAcNo]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if ($res) {
    $targetName = $res['first_name'] . ' ' . $res['last_name'];
    $targetPosition = $res['job_title'];
    $targetDepartment = $res['dept_name'];
}

// Cutoffs
$cutoffs = AppHelpers::generateCutoffPeriods($pdo, null, null, 3, -2);
$selectedCutoff = $_GET['cutoff'] ?? ($cutoffs[0]['value'] ?? '');
if ($selectedCutoff) {
    list($startDate, $endDate) = explode('|', $selectedCutoff);
} else {
    $startDate = $endDate = date('Y-m-d');
}

// Fetch DTR Data
$timecardModel = new Timecard($pdo);
$data = $timecardModel->generateDtr($targetAcNo, $startDate, $endDate);

// Fetch Employee List
$empList = [];
if ($isHr) {
    $stmtEmp = $pdo->query("SELECT ac_no, first_name, last_name FROM employees WHERE IsActive = 1 ORDER BY last_name");
    $empList = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Time Record - Azzurro Hotel by SGC</title>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: Arial, sans-serif;
    background: #e0e0e0;
  }

  @page {
    size: A4 landscape;
    margin: 0;
  }
  .page {
    width: 297mm;
    min-height: 210mm;
    background: white;
    margin: 0 auto;
    padding: 10mm;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    page-break-after: always;
    box-sizing: border-box;
  }

  /* ===== FRONT PAGE ===== */
  .header-right {
    text-align: right;
    margin-bottom: 3mm;
  }
  .header-right .hotel-name {
    font-size: 7pt;
    font-weight: normal;
    letter-spacing: 0.5px;
  }
  .header-right .form-title {
    font-size: 12pt;
    font-weight: 900;
    letter-spacing: 2px;
  }

  .info-grid {
    display: grid;
    grid-template-columns: auto 1fr auto 1fr auto 1fr;
    border: 1.5px solid #000;
    margin-bottom: 0;
  }
  .info-grid-row2 {
    display: grid;
    grid-template-columns: auto 1fr auto 1fr auto 1fr auto 1fr;
    border: 1.5px solid #000;
    border-top: none;
    margin-bottom: 0;
  }

  .info-label {
    background: #fff;
    font-size: 7pt;
    font-weight: bold;
    padding: 2mm 3mm;
    border-right: 1px solid #000;
    white-space: nowrap;
    display: flex;
    align-items: center;
  }
  .info-value {
    padding: 2mm 3mm;
    border-right: 1px solid #000;
    min-width: 30mm;
    font-size: 8pt;
  }
  .info-value:last-child { border-right: none; }

  /* Main Table */
  .main-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
    font-size: 6.5pt;
  }
  .main-table th, .main-table td {
    border: 1px solid #000;
    text-align: center;
    vertical-align: middle;
    padding: 1.5mm 1mm;
  }
  .main-table th {
    font-weight: bold;
    font-size: 6pt;
    line-height: 1.2;
    background: #fff;
  }
  .main-table .data-row td {
    height: 7mm;
  }
  .main-table .total-row td {
    font-weight: bold;
    height: 7mm;
  }
  .col-date   { width: 14mm; }
  .col-day    { width: 10mm; }
  .col-sched  { width: 22mm; }
  .col-in     { width: 12mm; }
  .col-out    { width: 12mm; }
  .col-rh     { width: 16mm; }
  .col-lates  { width: 12mm; }
  .col-abs    { width: 14mm; }
  .col-rot    { width: 13mm; }
  .col-nd     { width: 13mm; }
  .col-ndot   { width: 13mm; }
  .col-vlsl   { width: 11mm; }
  .col-lh     { width: 15mm; }
  .col-lhot   { width: 15mm; }
  .col-sh     { width: 15mm; }
  .col-shot   { width: 18mm; }
  .col-rdot   { width: 15mm; }

  /* ===== BACK PAGE ===== */
  .back-page .remarks-title {
    font-size: 18pt;
    font-weight: 900;
    letter-spacing: 3px;
    text-align: right;
    margin-bottom: 2mm;
  }

  .remarks-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
  }
  .remarks-table th, .remarks-table td {
    border: 1px solid #000;
    padding: 1.5mm 2mm;
    vertical-align: top;
  }
  .remarks-table th {
    font-weight: bold;
    font-size: 7pt;
    text-align: center;
  }
  .remarks-table .col-date  { width: 22mm; }
  .remarks-table .col-time  { width: 22mm; }
  .remarks-table .col-reason { width: auto; }
  .remarks-table .col-approved { width: 35mm; }

  .remarks-table .data-row td {
    height: 9mm;
  }

  .form-number {
    text-align: right;
    font-size: 6pt;
    margin-top: 3mm;
    color: #333;
  }

  /* Web Interface Additions */
  .controls-card {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-family: Arial, sans-serif;
  }
  
  .controls-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
  }

  .control-group {
    flex: 1;
  }

  .control-label {
    display: block;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
  }

  .control-input {
    width: 100%;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ccc;
    font-size: 14px;
  }

  .btn-print {
    padding: 10px 20px;
    background: #333;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
  }

  .btn-print:hover {
    background: #000;
  }

  .select2-container .select2-selection--single {
    height: 35px;
    border: 1px solid #ccc;
    border-radius: 4px;
    display: flex;
    align-items: center;
  }

  @media print {
    body { background: white; }
    .page { margin: 0; box-shadow: none; page-break-after: always; }
    .page:last-child { page-break-after: avoid; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<!-- Controls Card -->
<div class="controls-card no-print">
  <form method="GET" class="controls-form">
    <?php if ($isHr): ?>
      <div class="control-group">
        <label class="control-label">Employee</label>
        <select name="search_ac" id="hr_search" class="control-input" onchange="this.form.submit()">
          <option value="<?php echo $myAcNo; ?>">Myself</option>
          <?php foreach ($empList as $emp): ?>
              <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($targetAcNo == $emp['ac_no']) ? 'selected' : ''; ?>>
                  <?php echo $emp['last_name'] . ', ' . $emp['first_name']; ?>
              </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="control-group">
      <label class="control-label">Period</label>
      <select name="cutoff" class="control-input" onchange="this.form.submit()">
          <?php foreach ($cutoffs as $c): ?>
              <option value="<?php echo $c['val']; ?>" <?php echo ($selectedCutoff == $c['val']) ? 'selected' : ''; ?>>
                  <?php echo $c['label']; ?>
              </option>
          <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="btn-print" onclick="window.print()">
      Print DTR
    </button>
  </form>
</div>

<!-- FRONT PAGE -->
<div class="page front-page">
  <div class="header-right">
    <div class="hotel-name">AZZURRO HOTEL BY SGC</div>
    <div class="form-title">DAILY TIME RECORD</div>
  </div>

  <!-- Row 1: Name, Position, Department -->
  <div class="info-grid">
    <div class="info-label">NAME</div>
    <div class="info-value"><?php echo htmlspecialchars($targetName); ?></div>
    <div class="info-label">POSITION</div>
    <div class="info-value"><?php echo htmlspecialchars($targetPosition); ?></div>
    <div class="info-label">DEPARTMENT</div>
    <div class="info-value"><?php echo htmlspecialchars($targetDepartment); ?></div>
  </div>

  <!-- Row 2: ID No, Period, Payclass, Bi Monthly -->
  <div class="info-grid-row2">
    <div class="info-label">ID NO</div>
    <div class="info-value"><?php echo htmlspecialchars($targetAcNo); ?></div>
    <div class="info-label">PERIOD</div>
    <div class="info-value"><?php echo date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)); ?></div>
    <div class="info-label" style="border-left:1px solid #000;">PAYCLASS</div>
    <div class="info-value"></div>
    <div class="info-label">BI MONTHLY</div>
    <div class="info-value" style="border-right:none;"></div>
  </div>

  <!-- Main Table -->
  <table class="main-table">
    <thead>
      <tr>
        <th class="col-date" rowspan="2">DATE</th>
        <th class="col-day" rowspan="2">DAY</th>
        <th class="col-sched" rowspan="2">SCHEDULE</th>
        <th class="col-in" rowspan="2">IN</th>
        <th class="col-out" rowspan="2">OUT</th>
        <th class="col-rh" rowspan="2">REGULAR HOURS</th>
        <th class="col-lates" rowspan="2">LATES</th>
        <th class="col-abs" rowspan="2">ABSENCES</th>
        <th class="col-rot" rowspan="2">REGULAR OT</th>
        <th class="col-nd" rowspan="2">NIGHT DIFF</th>
        <th class="col-ndot" rowspan="2">NIGHT DIFF OT</th>
        <th class="col-vlsl" rowspan="2">VL/ SL</th>
        <th class="col-lh" rowspan="2">LEGAL HOLIDAY</th>
        <th class="col-lhot" rowspan="2">LEGAL HOLIDAY OT</th>
        <th class="col-sh" rowspan="2">SPECIAL HOLIDAY</th>
        <th class="col-shot" rowspan="2">SPECIAL HOLIDAY OT/ RD OT</th>
        <th class="col-rdot" rowspan="2">RESTDAY OT</th>
      </tr>
      <tr></tr>
    </thead>
    <tbody>
      <?php 
      $totals = [
          'rh' => 0, 'lates' => 0, 'abs' => 0, 'rot' => 0, 
          'nd' => 0, 'ndot' => 0, 'vlsl' => 0, 'lh' => 0, 
          'lhot' => 0, 'sh' => 0, 'shot' => 0, 'rdot' => 0
      ];
      $remarksLog = [];

      $rowCount = 0;
      foreach ($data as $day): 
          $rowCount++;
          
          // Determine values
          $lateMins = (float)($day['late_mins'] ?? 0);
          $isAbsent = (isset($day['remarks']) && $day['remarks'] === 'ABSENT');
          $regHrs = (float)($day['regular_hrs'] ?? 0);
          $regOtHrs = (float)($day['regular_ot_hrs'] ?? 0);
          $ndHrs = (float)($day['nd_hrs'] ?? 0);
          $ndOtHrs = (float)($day['nd_ot_hrs'] ?? 0);
          
          $lhHrs = (float)($day['regular_holiday_hrs'] ?? 0) + (float)($day['double_holiday_hrs'] ?? 0);
          $lhotHrs = (float)($day['regular_holiday_ot_hrs'] ?? 0) + (float)($day['double_holiday_ot_hrs'] ?? 0);
          
          $shHrs = (float)($day['special_holiday_hrs'] ?? 0);
          $shotHrs = (float)($day['special_holiday_ot_hrs'] ?? 0) + (float)($day['rest_day_special_holiday_hrs'] ?? 0);
          
          $rdotHrs = (float)($day['rest_day_hrs'] ?? 0) + (float)($day['rest_day_ot_hrs'] ?? 0);

          // 1-hour break deduction logic for regular shifts crossing 5+ hours
          if ($regHrs > 5) $regHrs -= 1;
          elseif ($lhHrs > 5) $lhHrs -= 1;
          elseif ($shHrs > 5) $shHrs -= 1;
          elseif ($rdotHrs > 5) $rdotHrs -= 1;

          // Accumulate totals
          $totals['lates'] += $lateMins;
          $totals['abs'] += $isAbsent ? 1 : 0;
          $totals['rh'] += $regHrs;
          $totals['rot'] += $regOtHrs;
          $totals['nd'] += $ndHrs;
          $totals['ndot'] += $ndOtHrs;
          $totals['lh'] += $lhHrs;
          $totals['lhot'] += $lhotHrs;
          $totals['sh'] += $shHrs;
          $totals['shot'] += $shotHrs;
          $totals['rdot'] += $rdotHrs;

          if (!empty($day['remarks']) && $day['remarks'] !== 'ABSENT') {
              $remarksLog[] = [
                  'date' => $day['date'],
                  'time' => '', // specific time is not stored in $day, keep blank
                  'reason' => $day['remarks']
              ];
          }
      ?>
      <tr class="data-row">
        <td><?php echo date('m/d/Y', strtotime($day['date'])); ?></td>
        <td><?php echo strtoupper(substr($day['day'], 0, 3)); ?></td>
        <td><?php echo $day['sched_in'] ? $day['sched_in'] . '-' . $day['sched_out'] : $day['sched_code']; ?></td>
        <td><?php echo $day['actual_in'] ?: ''; ?></td>
        <td><?php echo $day['actual_out'] ?: ''; ?></td>
        <td><?php echo $regHrs > 0 ? $regHrs : ''; ?></td>
        <td><?php echo $lateMins > 0 ? $lateMins : ''; ?></td>
        <td><?php echo $isAbsent ? '1' : ''; ?></td>
        <td><?php echo $regOtHrs > 0 ? $regOtHrs : ''; ?></td>
        <td><?php echo $ndHrs > 0 ? $ndHrs : ''; ?></td>
        <td><?php echo $ndOtHrs > 0 ? $ndOtHrs : ''; ?></td>
        <td></td> <!-- VL/SL -->
        <td><?php echo $lhHrs > 0 ? $lhHrs : ''; ?></td>
        <td><?php echo $lhotHrs > 0 ? $lhotHrs : ''; ?></td>
        <td><?php echo $shHrs > 0 ? $shHrs : ''; ?></td>
        <td><?php echo $shotHrs > 0 ? $shotHrs : ''; ?></td>
        <td><?php echo $rdotHrs > 0 ? $rdotHrs : ''; ?></td>
      </tr>
      <?php endforeach; ?>
      <?php 
      // Pad empty rows if less than 16 (for aesthetic reasons)
      while ($rowCount < 16) {
          echo '<tr class="data-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
          $rowCount++;
      }
      ?>
      <!-- Total Row -->
      <tr class="total-row">
        <td colspan="5" style="text-align:center; font-weight:bold;">TOTAL</td>
        <td><?php echo $totals['rh'] > 0 ? $totals['rh'] : ''; ?></td>
        <td><?php echo $totals['lates'] > 0 ? $totals['lates'] : ''; ?></td>
        <td><?php echo $totals['abs'] > 0 ? $totals['abs'] : ''; ?></td>
        <td><?php echo $totals['rot'] > 0 ? $totals['rot'] : ''; ?></td>
        <td><?php echo $totals['nd'] > 0 ? $totals['nd'] : ''; ?></td>
        <td><?php echo $totals['ndot'] > 0 ? $totals['ndot'] : ''; ?></td>
        <td></td>
        <td><?php echo $totals['lh'] > 0 ? $totals['lh'] : ''; ?></td>
        <td><?php echo $totals['lhot'] > 0 ? $totals['lhot'] : ''; ?></td>
        <td><?php echo $totals['sh'] > 0 ? $totals['sh'] : ''; ?></td>
        <td><?php echo $totals['shot'] > 0 ? $totals['shot'] : ''; ?></td>
        <td><?php echo $totals['rdot'] > 0 ? $totals['rdot'] : ''; ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- BACK PAGE -->
<div class="page back-page">
  <div class="remarks-title">REMARKS</div>

  <table class="remarks-table">
    <thead>
      <tr>
        <th class="col-date">DATE</th>
        <th class="col-time">TIME</th>
        <th class="col-reason">REASON FOR OT/ LATE AND ABSENCES</th>
        <th class="col-approved">APPROVED BY:</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $remarkCount = 0;
      foreach ($remarksLog as $r):
          $remarkCount++;
      ?>
      <tr class="data-row">
        <td><?php echo date('m/d/Y', strtotime($r['date'])); ?></td>
        <td><?php echo $r['time']; ?></td>
        <td><?php echo htmlspecialchars($r['reason']); ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
      <?php 
      while ($remarkCount < 18) {
          echo '<tr class="data-row"><td></td><td></td><td></td><td></td></tr>';
          $remarkCount++;
      }
      ?>
    </tbody>
  </table>

  <div class="form-number">HRD FORM #001-2024</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() { 
        if($('#hr_search').length) {
            $('#hr_search').select2({ placeholder: "Search Employee...", width: '100%' }); 
        }
    });
</script>
</body>
</html>

