<?php
$pageTitle = 'Payroll Payslip — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

function fmt($n) { return number_format((float)$n, 2); }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 36px; border-color: var(--border); border-radius: var(--r-sm); padding: 4px; background: var(--bg-card); color: var(--ink-1); }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--ink-1); line-height: 28px; }
    
    @media print {
        @page { size: A4 portrait; margin: 5mm; }
        body { background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; zoom: 90%; }
        .no-print { display: none !important; }
        .main { padding: 0 !important; animation: none !important; }
        .sidebar, .topbar { display: none !important; }
        .app { display: block; }
        .payslip-container { box-shadow: none !important; border: 2px solid #000 !important; width: 100% !important; max-width: 200mm !important; margin: 0 auto !important; padding: 15px !important; page-break-inside: avoid; }
        .print-compact-y { margin-bottom: 4px !important; }
        .bg-gray-50 { background-color: #f9fafb !important; }
        .bg-gray-900 { background-color: #111827 !important; color: white !important; }
    }
    
    .payslip-container { background: #fff; position: relative; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--sh-md); }
    .watermark-container { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: 0.05; pointer-events: none; transform: rotate(-30deg); z-index: 0; }
    .watermark-container img { width: 400px; grayscale: 1; }
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
    <div class="flex-row mb-20" style="gap: 15px; align-items: center;">
        <?php if($is_posted): ?>
            <span class="tag tag-teal" style="padding: 5px 12px;"><i class="fa-solid fa-check-circle"></i> POSTED</span>
            <span style="font-size: 11px; color: var(--ink-4);">Finalized by <?php echo $poster_name; ?></span>
        <?php else: ?>
            <span class="tag" style="padding: 5px 12px; background: var(--bg-subtle); color: var(--ink-3);"><i class="fa-solid fa-pen-ruler"></i> DRAFT</span>
        <?php endif; ?>
    </div>

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
            <select name="cutoff" class="input-field" onchange="this.form.submit()">
                <?php foreach ($cutoffs as $c): ?>
                    <option value="<?php echo $c['val']; ?>" <?php echo ($sel_cutoff == $c['val']) ? 'selected' : ''; ?>>
                        <?php echo $c['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($is_hr): ?>
        <form method="POST" class="mt-20 pt-20" style="border-top: 1px solid var(--border-lt);" onsubmit="return confirm('<?php echo $is_posted ? "Unposting will REVERSE loan deductions. Continue?" : "Posting will DEDUCT loans from balances. Continue?"; ?>');">
            <input type="hidden" name="action" value="toggle_posting">
            <input type="hidden" name="new_status" value="<?php echo $is_posted ? '0' : '1'; ?>">
            <?php if($is_posted): ?>
                <button type="submit" class="pill-btn" style="color:var(--red); border-color:var(--red-bdr); background:var(--red-bg);">
                    <i class="fa-solid fa-ban"></i> Unpost / Hide Payslips
                </button>
            <?php else: ?>
                <button type="submit" class="btn-primary" style="background: var(--blue); box-shadow: 0 4px 12px var(--blue-bg);">
                    <i class="fa-solid fa-bullhorn"></i> DEPLOY & POST PAYROLL
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
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
            <div class="grid-3" style="grid-template-columns: 2fr 1fr 1fr;">
                <div>
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">Employee Name</span>
                    <span style="font-weight: 800; font-size: 16px;"><?php echo htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']); ?></span>
                </div>
                <div>
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">ID Number</span>
                    <span style="font-weight: 700; font-family: 'DM Mono';"><?php echo $employee['ac_no']; ?></span>
                </div>
                <div>
                    <span style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; display: block;">Designation</span>
                    <span style="font-weight: 700;"><?php echo $employee['job_title'] ?: 'N/A'; ?></span>
                </div>
            </div>
        </div>

        <div class="relative z-10 grid-2" style="gap: 40px; margin-bottom: 20px;">
            <!-- EARNINGS -->
            <div>
                <h3 style="font-size: 11px; font-weight: 800; color: var(--teal); border-bottom: 2px solid var(--teal); padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Earnings</h3>
                <table style="width: 100%; font-size: 11px;">
                    <tbody>
                        <tr>
                            <td style="padding: 5px 0; color: var(--ink-3);">Basic Pay (<?php echo $breakdown['days_worked']; ?>d)</td>
                            <td style="text-align: right; font-family: 'DM Mono'; font-weight: 600;"><?php echo fmt($breakdown['pay_basic']); ?></td>
                        </tr>
                        <?php foreach($allowances as $allw): ?>
                        <tr>
                            <td style="padding: 5px 0; color: var(--blue);"><b><?php echo htmlspecialchars($allw['name']); ?></b></td>
                            <td style="text-align: right; font-family: 'DM Mono'; color: var(--blue); font-weight: 600;"><?php echo fmt($allw['amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if($breakdown['pay_holiday'] > 0): ?><tr><td style="padding:5px 0; color:var(--ink-3);">Holiday Premium</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($breakdown['pay_holiday']); ?></td></tr><?php endif; ?>
                        <?php if($breakdown['pay_overtime'] > 0): ?><tr><td style="padding:5px 0; color:var(--ink-3);">Overtime (<?php echo fmt($breakdown['hours_ot']); ?>h)</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($breakdown['pay_overtime']); ?></td></tr><?php endif; ?>
                        <?php if($breakdown['pay_nightdiff'] > 0): ?><tr><td style="padding:5px 0; color:var(--ink-3);">Night Differential</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($breakdown['pay_nightdiff']); ?></td></tr><?php endif; ?>
                    </tbody>
                    <tfoot style="border-top: 1px solid var(--border);">
                        <tr style="font-weight: 800;">
                            <td style="padding: 10px 0;">TOTAL EARNINGS</td>
                            <td style="text-align: right; font-family: 'DM Mono';">₱ <?php echo fmt($gross_pay); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- DEDUCTIONS -->
            <div>
                <h3 style="font-size: 11px; font-weight: 800; color: var(--red); border-bottom: 2px solid var(--red); padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Deductions</h3>
                <table style="width: 100%; font-size: 11px;">
                    <tbody>
                        <?php if($breakdown['deduct_late'] > 0): ?><tr><td style="padding:5px 0; color:var(--red);">Late (<?php echo $breakdown['mins_late']; ?>m)</td><td style="text-align:right; font-family:'DM Mono'; color:var(--red);"><?php echo fmt($breakdown['deduct_late']); ?></td></tr><?php endif; ?>
                        <?php if($breakdown['deduct_ut'] > 0): ?><tr><td style="padding:5px 0; color:var(--red);">Undertime (<?php echo $breakdown['mins_ut']; ?>m)</td><td style="text-align:right; font-family:'DM Mono'; color:var(--red);"><?php echo fmt($breakdown['deduct_ut']); ?></td></tr><?php endif; ?>
                        <tr><td style="padding:5px 0; color:var(--ink-3);">SSS Contribution</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($gov_deductions['sss']); ?></td></tr>
                        <tr><td style="padding:5px 0; color:var(--ink-3);">PhilHealth</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($gov_deductions['philhealth']); ?></td></tr>
                        <tr><td style="padding:5px 0; color:var(--ink-3);">Pag-IBIG</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($gov_deductions['pagibig']); ?></td></tr>
                        <tr><td style="padding:5px 0; color:var(--ink-3);">Withholding Tax</td><td style="text-align:right; font-family:'DM Mono';"><?php echo fmt($gov_deductions['tax']); ?></td></tr>
                        <?php foreach($loans as $loan): ?>
                        <tr>
                            <td style="padding: 5px 0; color: var(--red); font-style: italic;"><?php echo htmlspecialchars($loan['type']); ?></td>
                            <td style="text-align: right; font-family: 'DM Mono'; color: var(--red); font-weight: 600;"><?php echo fmt($loan['amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="border-top: 1px solid var(--border);">
                        <tr style="font-weight: 800; color: var(--red);">
                            <td style="padding: 10px 0;">TOTAL DEDUCTIONS</td>
                            <td style="text-align: right; font-family: 'DM Mono';">(<?php echo fmt($total_deductions); ?>)</td>
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
                <span style="font-size: 16px; font-weight: 400; opacity: 0.7; margin-right: 5px;">PHP</span><?php echo fmt($net_pay); ?>
            </div>
        </div>

        <div class="relative z-10 grid-2" style="margin-top: 40px; text-align: center; gap: 60px;">
            <div>
                <div style="border-bottom: 1px solid var(--border); margin-bottom: 10px;"></div>
                <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase;">Employee Signature</p>
            </div>
            <div>
                <div style="border-bottom: 1px solid var(--border); margin-bottom: 10px;"></div>
                <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase;">Authorized Representative</p>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center; font-size: 9px; color: var(--ink-4); opacity: 0.6;" class="relative z-10">
            System Generated Statement • <?php echo date('Y-m-d H:i:s'); ?> • <?php echo $_SESSION['full_name']; ?>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#employee_search').select2({
            placeholder: "Search employee name...",
            allowClear: false,
            width: '100%'
        });
    });
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
