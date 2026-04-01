<?php
$pageTitle = 'Payroll Payslip — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

function fmt($n) { 
    if ($n == 0 || $n === null || $n === '-') return '-';
    return number_format((float)$n, 2); 
}

function fmtHrs($n) {
    if ($n == 0 || $n === null || $n === '-') return '-';
    return number_format((float)$n, 2);
}

// Grouping Loans for the template
$sssLoan = 0;
$pagIbigLoan = 0;
$cashAdvance = 0;
$staffFolio = 0;
$arMobile = 0;
$medicalCharges = 0;
$otherCharges = 0;

if (isset($loans) && is_array($loans)) {
    foreach ($loans as $loan) {
        $cat = strtoupper($loan['category'] ?? '');
        $desc = strtoupper($loan['type'] ?? '');
        
        if (strpos($cat, 'SSS') !== false) $sssLoan += $loan['amount'];
        elseif (strpos($cat, 'PAG-IBIG') !== false || strpos($cat, 'HDMF') !== false || strpos($cat, 'PAGIBIG') !== false) $pagIbigLoan += $loan['amount'];
        elseif (strpos($cat, 'CASH ADVANCE') !== false) $cashAdvance += $loan['amount'];
        elseif (strpos($cat, 'STAFF FOLIO') !== false) $staffFolio += $loan['amount'];
        elseif (strpos($cat, 'MOBILE') !== false || strpos($desc, 'MOBILE') !== false) $arMobile += $loan['amount'];
        elseif (strpos($cat, 'MEDICAL') !== false) $medicalCharges += $loan['amount'];
        else $otherCharges += $loan['amount'];
    }
}

// Meal Allowance specifically
$mealAllowance = 0;
$otherAllowances = 0;
if (isset($allowances) && is_array($allowances)) {
    foreach ($allowances as $allw) {
        if (strpos(strtoupper($allw['name']), 'MEAL') !== false) {
            $mealAllowance += $allw['amount'];
        } else {
            $otherAllowances += $allw['amount'];
        }
    }
}

// Calculate Subtotals to match Template Logic
$earningsSubtotal = ($breakdown['pay_basic'] ?? 0) + 
                   ($breakdown['pay_rh'] ?? 0) + 
                   ($breakdown['pay_sh'] ?? 0) + 
                   ($breakdown['pay_rd'] ?? 0) + 
                   ($breakdown['pay_rd_sh'] ?? 0) +
                   ($breakdown['pay_overtime'] ?? 0) +
                   ($breakdown['pay_nightdiff'] ?? 0) +
                   ($breakdown['credits_adj'] ?? 0);

$tardinessTotal = ($breakdown['deduct_absent'] ?? 0) + 
                 ($breakdown['deduct_late'] ?? 0) + 
                 ($breakdown['deduct_ut'] ?? 0);

$totalIncome = $earningsSubtotal + $mealAllowance + $otherAllowances + ($breakdown['tax_refund'] ?? 0) - $tardinessTotal;

$govTotal = ($gov_deductions['sss'] ?? 0) + 
            ($gov_deductions['mpf'] ?? 0) + 
            ($gov_deductions['philhealth'] ?? 0) + 
            ($gov_deductions['pagibig'] ?? 0) + 
            ($gov_deductions['tax'] ?? 0);

$loanTotal = $sssLoan + $pagIbigLoan + $cashAdvance + $staffFolio + $arMobile + $medicalCharges + $otherCharges;

$totalDeductionsRequested = $govTotal + $loanTotal;
?>

<style>
    @media print {
        @page { size: A4 portrait; margin: 5mm; }
        body { background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; zoom: 90%; }
        .no-print { display: none !important; }
        .main { padding: 0 !important; animation: none !important; }
        .sidebar, .topbar { display: none !important; }
        .app { display: block; }
        .payslip-container { box-shadow: none !important; border: 2px solid #000 !important; width: 100% !important; max-width: 200mm !important; margin: 0 auto !important; padding: 15px !important; page-break-inside: avoid; }
        .bg-gray-50 { background-color: #f9fafb !important; }
        .bg-gray-900 { background-color: #111827 !important; color: white !important; }
    }
    
    .payslip-container { background: #fff; position: relative; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--sh-md); }
    .watermark-container { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: 0.05; pointer-events: none; transform: rotate(-30deg); z-index: 0; }
    .watermark-container img { width: 400px; grayscale: 1; }
    
    .data-table { width: 100%; font-size: 11px; border-collapse: collapse; }
    .data-table td { padding: 4px 0; vertical-align: top; }
    .data-table .label { color: var(--ink-3); width: 55%; }
    .data-table .hrs { text-align: center; color: var(--ink-4); font-family: 'DM Mono'; width: 15%; }
    .data-table .val { text-align: right; font-family: 'DM Mono'; font-weight: 600; width: 30%; }
    .section-header { font-size: 11px; font-weight: 800; border-bottom: 2px solid var(--border); padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-file-invoice-dollar"></i> Payslip Generator</h1>
        <p class="page-sub">Official statement of earnings and deductions</p>
    </div>
    <div class="header-actions">
        <?php if(!$access_denied): ?>
        <button onclick="window.print()" class="btn-primary"><i class="fa-solid fa-print"></i> Print (A4)</button>
        <?php endif; ?>
    </div>
</div>

<div class="card p-20 mb-20 no-print">
    <form method="GET" class="flex-row" style="flex-wrap: wrap; gap: 20px;">
        <?php if ($is_hr): ?>
        <div class="form-group mb-0" style="flex: 1; min-width: 250px;">
            <label class="form-label">Search Employee</label>
            <select name="search_ac" id="employee_search" class="input-field" onchange="this.form.submit()">
                <option value="<?php echo $_SESSION['ac_no']; ?>">-- My Personal Payslip --</option>
                <?php foreach ($empList as $emp): ?>
                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group mb-0" style="width: 250px;">
            <label class="form-label">Pay Period</label>
            <select name="cutoff" id="cutoff_select" class="input-field" onchange="this.form.submit()">
                <?php foreach ($cutoffs as $c): ?>
                    <option value="<?php echo $c['val']; ?>" <?php echo ($sel_cutoff == $c['val']) ? 'selected' : ''; ?>>
                        <?php echo $c['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($access_denied): ?>
    <div class="card" style="border: 2px dashed var(--border); background: var(--bg-subtle); text-align: center; padding: 60px;">
        <i class="fa-regular fa-clock" style="font-size: 40px; color: var(--ink-4); margin-bottom: 20px;"></i>
        <h2 style="font-size: 20px; font-weight: 700;">Payslip Under Review</h2>
        <p style="color: var(--ink-3); margin-top: 10px; max-width: 400px; margin-left: auto; margin-right: auto;">
            Payroll for <b><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></b> is currently being processed.
        </p>
    </div>
<?php else: ?>
    <div class="payslip-container p-20" style="max-width: 800px; margin: 0 auto;">
        <div class="watermark-container">
            <img src="azzurro1.png" alt="Watermark">
        </div>

        <div class="relative z-10" style="border-bottom: 2px solid var(--ink-1); padding-bottom: 15px; margin-bottom: 20px;">
            <div class="flex-between">
                <div class="flex-row">
                    <img src="azzurro1.png" style="height: 50px; grayscale: 1;">
                    <div>
                        <h1 style="font-size: 20px; font-weight: 800; letter-spacing: 1px;">AZZURRO HOTEL</h1>
                        <p style="font-size: 10px; font-weight: 600; color: var(--ink-3); text-transform: uppercase;">Official Statement of Earnings</p>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase;">Period Coverage</div>
                    <div style="font-size: 14px; font-weight: 800;"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></div>
                </div>
            </div>
        </div>

        <div class="relative z-10" style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <div class="grid-2" style="grid-template-columns: 1fr 1fr; gap: 15px 40px;">
                <div>
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">Employee Name</span>
                    <span style="font-weight: 800; font-size: 15px;"><?php echo htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']); ?></span>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">ID Number</span>
                    <span style="font-weight: 700; font-family: 'DM Mono';"><?php echo $employee['ac_no']; ?></span>
                </div>
                <div>
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">Designation</span>
                    <span style="font-weight: 700; font-size: 13px;"><?php echo $employee['job_title'] ?: 'N/A'; ?></span>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">Monthly Rate</span>
                    <span style="font-weight: 700; font-family: 'DM Mono'; font-size: 13px;"><?php echo number_format($employee['salary_rate'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="relative z-10 grid-2" style="gap: 40px; margin-bottom: 20px;">
            <!-- EARNINGS -->
            <div>
                <h3 class="section-header" style="color: var(--teal); border-color: var(--teal);">Income Breakdown</h3>
                <table class="data-table">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="text-align: left; font-size: 9px; color: var(--ink-4);">DESCRIPTION</th>
                            <th style="text-align: center; font-size: 9px; color: var(--ink-4);">HRS</th>
                            <th style="text-align: right; font-size: 9px; color: var(--ink-4);">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label">Regular Salary</td>
                            <td class="hrs">-</td>
                            <td class="val"><?php echo fmt($breakdown['pay_basic'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="font-weight: 700; padding-top: 8px; color: var(--ink-2);">Holiday / Rest Day Premium</td>
                        </tr>
                        <tr>
                            <td class="label" style="padding-left: 10px;">Rest Day</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hrs_rd'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_rd'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="padding-left: 10px;">Special Holiday</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hrs_sh'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_sh'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="padding-left: 10px;">RD + Special</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hrs_rd_sh'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_rd_sh'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="padding-left: 10px;">Regular Holiday</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hrs_rh'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_rh'] ?? 0); ?></td>
                        </tr>
                        <?php if(($breakdown['pay_overtime'] ?? 0) > 0): ?>
                        <tr>
                            <td class="label">Overtime</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hours_ot'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_overtime'] ?? 0); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if(($breakdown['pay_nightdiff'] ?? 0) > 0): ?>
                        <tr>
                            <td class="label">Night Differential</td>
                            <td class="hrs"><?php echo fmtHrs($breakdown['hrs_nd'] ?? 0); ?></td>
                            <td class="val"><?php echo fmt($breakdown['pay_nightdiff'] ?? 0); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="border-top: 1px dashed var(--border); font-weight: 700;">
                            <td style="padding: 8px 0;" colspan="2">Gross Income</td>
                            <td class="val" style="padding: 8px 0;">₱ <?php echo fmt($earningsSubtotal); ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="color: var(--blue);" colspan="2">Meal Allowance</td>
                            <td class="val" style="color: var(--blue);"><?php echo fmt($mealAllowance); ?></td>
                        </tr>
                        <?php if($otherAllowances > 0): ?>
                        <tr>
                            <td class="label" style="color: var(--blue);" colspan="2">Other Allowances</td>
                            <td class="val" style="color: var(--blue);"><?php echo fmt($otherAllowances); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if(($breakdown['tax_refund'] ?? 0) > 0): ?>
                        <tr>
                            <td class="label" style="color: var(--teal);" colspan="2">Tax Refund</td>
                            <td class="val" style="color: var(--teal);"><?php echo fmt($breakdown['tax_refund'] ?? 0); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h3 class="section-header" style="color: var(--red); border-color: var(--red); margin-top: 15px;">Adjustments (Less)</h3>
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td class="label">Absences</td>
                            <td class="hrs"><?php echo ($breakdown['days_absent'] ?? 0) ?: '-'; ?>d</td>
                            <td class="val" style="color: var(--red);"><?php echo fmt($breakdown['deduct_absent'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Undertime</td>
                            <td class="hrs"><?php echo ($breakdown['mins_ut'] ?? 0) ?: '-'; ?>m</td>
                            <td class="val" style="color: var(--red);"><?php echo fmt($breakdown['deduct_ut'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Lates</td>
                            <td class="hrs"><?php echo ($breakdown['mins_late'] ?? 0) ?: '-'; ?>m</td>
                            <td class="val" style="color: var(--red);"><?php echo fmt($breakdown['deduct_late'] ?? 0); ?></td>
                        </tr>
                    </tbody>
                    <tfoot style="border-top: 2px solid var(--ink-1);">
                        <tr style="font-weight: 800; font-size: 13px;">
                            <td style="padding: 10px 0;" colspan="2">TOTAL INCOME</td>
                            <td class="val" style="padding: 10px 0;">₱ <?php echo fmt($totalIncome); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- DEDUCTIONS -->
            <div>
                <h3 class="section-header" style="color: var(--ink-1); border-color: var(--ink-1);">Contributions & Taxes</h3>
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td class="label" colspan="2">SSS Contribution</td>
                            <td class="val"><?php echo fmt($gov_deductions['sss'] ?? 0); ?></td>
                        </tr>
                        <?php if(($gov_deductions['mpf'] ?? 0) > 0): ?>
                        <tr>
                            <td class="label" colspan="2">SSS MPF</td>
                            <td class="val"><?php echo fmt($gov_deductions['mpf'] ?? 0); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="label" colspan="2">PhilHealth (PHIC)</td>
                            <td class="val"><?php echo fmt($gov_deductions['philhealth'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label" colspan="2">Pag-IBIG (HDMF)</td>
                            <td class="val"><?php echo fmt($gov_deductions['pagibig'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <td class="label" colspan="2">Withholding Tax</td>
                            <td class="val"><?php echo fmt($gov_deductions['tax'] ?? 0); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3 class="section-header" style="color: var(--red); border-color: var(--red); margin-top: 15px;">Loan Payments & Charges</h3>
                <table class="data-table">
                    <tbody>
                        <tr><td class="label" colspan="2">SSS Loan</td><td class="val"><?php echo fmt($sssLoan); ?></td></tr>
                        <tr><td class="label" colspan="2">Pag-Ibig Loan</td><td class="val"><?php echo fmt($pagIbigLoan); ?></td></tr>
                        <tr><td class="label" colspan="2">Cash Advance</td><td class="val"><?php echo fmt($cashAdvance); ?></td></tr>
                        <tr><td class="label" colspan="2">Staff Folio</td><td class="val"><?php echo fmt($staffFolio); ?></td></tr>
                        <tr><td class="label" colspan="2">A/R - Mobile</td><td class="val"><?php echo fmt($arMobile); ?></td></tr>
                        <tr><td class="label" colspan="2">Medical Charges</td><td class="val"><?php echo fmt($medicalCharges); ?></td></tr>
                        <tr><td class="label" colspan="2">Other Charges</td><td class="val"><?php echo fmt($otherCharges); ?></td></tr>
                    </tbody>
                    <tfoot style="border-top: 2px solid var(--red);">
                        <tr style="font-weight: 800; color: var(--red); font-size: 13px;">
                            <td style="padding: 10px 0;" colspan="2">TOTAL DEDUCTIONS</td>
                            <td class="val" style="padding: 10px 0;">(<?php echo fmt($totalDeductionsRequested); ?>)</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="relative z-10" style="background: var(--ink-1); color: #fff; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--sh-md);">
            <div>
                <p style="font-size: 10px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; letter-spacing: 1px;">Net Take Home Pay</p>
                <p style="font-size: 10px; opacity: 0.6;">Verified Net Salary</p>
            </div>
            <div style="font-size: 32px; font-weight: 900; font-family: 'DM Mono';">
                <span style="font-size: 16px; font-weight: 400; opacity: 0.7; margin-right: 5px;">PHP</span><?php echo fmt($totalIncome - $totalDeductionsRequested); ?>
            </div>
        </div>

        <div class="relative z-10 grid-2" style="margin-top: 40px; gap: 60px;">
            <div>
                <p style="font-size: 11px; margin-bottom: 30px;">Received by: __________________________</p>
                <p style="font-size: 11px;">Date Received: __________________________</p>
            </div>
            <div style="text-align: center;">
                <div style="border-bottom: 1px solid var(--border); margin-bottom: 5px; height: 30px;"></div>
                <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase;">Authorized Representative</p>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center; font-size: 9px; color: var(--ink-4); opacity: 0.6;" class="relative z-10">
            System Generated Statement • <?php echo date('Y-m-d H:i:s'); ?> • Printed by <?php echo $_SESSION['full_name']; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
