<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Payslips (<?php echo htmlspecialchars($startDate . ' to ' . $endDate); ?>) — Azzurro HR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=DM+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #eef2f6;
            color: #1e293b;
            font-size: 12px;
            padding: 20px;
        }

        .no-print-toolbar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background: #008080;
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-print:hover { background: #006666; }

        .btn-back {
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover { color: #0f172a; }

        /* Payslip Sheet */
        .payslip-sheet {
            max-width: 900px;
            margin: 0 auto 30px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.05);
            padding: 30px 36px;
            border: 1px solid #e2e8f0;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 2px solid #008080;
            margin-bottom: 16px;
        }

        .company-name { font-size: 18px; font-weight: 800; color: #008080; letter-spacing: -0.02em; }
        .company-sub { font-size: 10.5px; color: #64748b; margin-top: 2px; }
        .doc-badge {
            background: #f0fdfa;
            color: #008080;
            border: 1px solid #ccfbf1;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 11.5px;
        }
        .meta-label { color: #64748b; font-size: 9.5px; text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
        .meta-val { font-weight: 700; color: #0f172a; }

        .breakdown-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }

        .column-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .column-head {
            background: #f1f5f9;
            padding: 8px 14px;
            font-weight: 800;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #334155;
            display: flex;
            justify-content: space-between;
        }
        .table-rows {
            width: 100%;
            border-collapse: collapse;
        }
        .table-rows td {
            padding: 7px 14px;
            font-size: 11.5px;
            border-bottom: 1px dashed #f1f5f9;
        }
        .table-rows td:last-child {
            text-align: right;
            font-family: 'DM Mono', monospace;
            font-weight: 600;
        }
        .table-total {
            background: #f8fafc;
            font-weight: 800;
            border-top: 1px solid #cbd5e1;
        }

        .net-pay-banner {
            background: linear-gradient(135deg, #008080, #005959);
            color: #ffffff;
            padding: 14px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .net-label { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .net-amount { font-size: 22px; font-weight: 900; font-family: 'DM Mono', monospace; }

        .sig-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding-top: 16px;
            font-size: 11px;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 36px;
            padding-top: 6px;
            text-align: center;
            color: #475569;
            font-weight: 600;
        }

        /* Print formatting */
        @media print {
            body { background: #ffffff; padding: 0; }
            .no-print-toolbar { display: none !important; }
            .payslip-sheet {
                box-shadow: none !important;
                border: none !important;
                border-bottom: 2px dashed #ccc !important;
                padding: 24px 20px !important;
                margin: 0 0 0 0 !important;
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>
<body>

<div class="no-print-toolbar">
    <a href="<?php echo baseUrl('payslip?cutoff=' . urlencode($selectedCutoff)); ?>" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Back to Single View
    </a>
    <div style="font-size: 13px; font-weight: 700; color: #334155;">
        Batch Report: <span style="color:#008080;"><?php echo count($payslips); ?> Payslips</span> (<?php echo date('M d', strtotime($startDate)) . ' – ' . date('M d, Y', strtotime($endDate)); ?>)
    </div>
    <button onclick="window.print()" class="btn-print">
        <i class="fa-solid fa-print"></i> Print / Save as PDF
    </button>
</div>

<?php 
function formatAmt($v) {
    $n = (float)$v;
    return $n == 0 ? '-' : '₱ ' . number_format($n, 2);
}
?>

<?php foreach ($payslips as $p): ?>
    <?php 
    $emp = $p['employee'];
    $bd = $p['breakdown'];
    $allw = $p['allowances'];
    $loans = $p['loans'];
    $gov = $p['gov_deductions'];
    ?>
    <div class="payslip-sheet">
        <div class="header-section">
            <div>
                <div class="company-name"><i class="fa-solid fa-layer-group"></i> AzzurroHR Management System</div>
                <div class="company-sub">Official Employee Payslip & Earnings Statement</div>
            </div>
            <div class="doc-badge">Pay Period: <?php echo date('M d', strtotime($startDate)) . ' – ' . date('M d, Y', strtotime($endDate)); ?></div>
        </div>

        <div class="meta-grid">
            <div>
                <div class="meta-label">Employee Name</div>
                <div class="meta-val"><?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?></div>
            </div>
            <div>
                <div class="meta-label">Employee AC No.</div>
                <div class="meta-val" style="font-family:'DM Mono';"><?php echo htmlspecialchars($emp['ac_no']); ?></div>
            </div>
            <div>
                <div class="meta-label">Position</div>
                <div class="meta-val"><?php echo htmlspecialchars($emp['job_title'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <div class="meta-label">Department</div>
                <div class="meta-val"><?php echo htmlspecialchars($emp['dept_name'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <div class="breakdown-grid">
            <!-- EARNINGS -->
            <div class="column-box">
                <div class="column-head">
                    <span>Earnings & Allowances</span>
                    <span>Amount</span>
                </div>
                <table class="table-rows">
                    <tbody>
                        <tr><td>Basic Pay</td><td><?php echo formatAmt($bd['pay_basic'] ?? 0); ?></td></tr>
                        <?php if (!empty($bd['pay_holiday'])): ?>
                            <tr><td>Holiday Pay</td><td><?php echo formatAmt($bd['pay_holiday']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($bd['pay_overtime'])): ?>
                            <tr><td>Overtime Pay</td><td><?php echo formatAmt($bd['pay_overtime']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($bd['pay_nightdiff'])): ?>
                            <tr><td>Night Differential</td><td><?php echo formatAmt($bd['pay_nightdiff']); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($allw as $a): ?>
                            <tr><td><?php echo htmlspecialchars($a['name']); ?></td><td><?php echo formatAmt($a['amount']); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!empty($bd['tax_refund'])): ?>
                            <tr><td>Tax Refund / Adj</td><td><?php echo formatAmt($bd['tax_refund']); ?></td></tr>
                        <?php endif; ?>
                        <tr class="table-total">
                            <td style="font-weight: 800;">GROSS PAY</td>
                            <td style="color:#008080; font-size:13px;"><?php echo formatAmt($p['gross_pay']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- DEDUCTIONS -->
            <div class="column-box">
                <div class="column-head">
                    <span>Deductions</span>
                    <span>Amount</span>
                </div>
                <table class="table-rows">
                    <tbody>
                        <tr><td>SSS Contribution</td><td><?php echo formatAmt($gov['sss'] ?? 0); ?></td></tr>
                        <tr><td>PhilHealth Contribution</td><td><?php echo formatAmt($gov['philhealth'] ?? 0); ?></td></tr>
                        <tr><td>Pag-IBIG Contribution</td><td><?php echo formatAmt($gov['pagibig'] ?? 0); ?></td></tr>
                        <tr><td>Withholding Tax</td><td><?php echo formatAmt($gov['tax'] ?? 0); ?></td></tr>
                        <?php if (!empty($bd['deduct_late'])): ?>
                            <tr><td>Tardiness / Late</td><td><?php echo formatAmt($bd['deduct_late']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($bd['deduct_ut'])): ?>
                            <tr><td>Undertime</td><td><?php echo formatAmt($bd['deduct_ut']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($bd['deduct_absent'])): ?>
                            <tr><td>Absences</td><td><?php echo formatAmt($bd['deduct_absent']); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($loans as $l): ?>
                            <tr><td>Loan: <?php echo htmlspecialchars($l['type'] ?? $l['category'] ?? 'Loan'); ?></td><td><?php echo formatAmt($l['amount']); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="table-total">
                            <td style="font-weight: 800;">TOTAL DEDUCTIONS</td>
                            <td style="color:#dc2626; font-size:13px;"><?php echo formatAmt($p['total_deductions']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="net-pay-banner">
            <div>
                <div class="net-label">Net Take-Home Pay</div>
                <div style="font-size:10px; opacity:0.85; margin-top:2px;">Disbursed via Direct Bank Deposit</div>
            </div>
            <div class="net-amount">₱ <?php echo number_format($p['net_pay'], 2); ?></div>
        </div>

        <div class="sig-section">
            <div>
                <div class="sig-line">Prepared & Certified by HR Department</div>
            </div>
            <div>
                <div class="sig-line">Received & Acknowledged by Employee</div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

</body>
</html>
