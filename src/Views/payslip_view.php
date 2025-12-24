<?php function fmt($n) { return number_format((float)$n, 2); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'], mono: ['"Roboto Mono"', 'monospace'] },
                    colors: {
                        primary: { DEFAULT: '#a3e635', 50: '#f7fee7', 100: '#ecfccb', 500: '#84cc16', 600: '#65a30d', foreground: '#1F1F1F' }
                    },
                    borderRadius: { lg: '0.625rem' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #FAFAFA; color: #1F1F1F; }
        .stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); }
        .select2-container .select2-selection--single { height: 42px; padding: 6px; border-color: #d1d5db; border-radius: 0.5rem; }
        @media print {
            @page { size: A4 portrait; margin: 5mm; }
            body { background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; zoom: 90%; }
            .no-print { display: none !important; }
            .payslip-container { box-shadow: none !important; border: 2px solid #000 !important; width: 100% !important; max-width: 200mm !important; margin: 0 auto !important; padding: 15px !important; page-break-inside: avoid; }
            .print-compact-y { margin-bottom: 4px !important; }
            .print-compact-gap { gap: 10px !important; }
            .print-compact-text { font-size: 10px !important; }
            table td { padding-top: 2px !important; padding-bottom: 2px !important; font-size: 11px !important; }
            .bg-gray-100 { background-color: #f3f4f6 !important; }
            .bg-gray-900 { background-color: #111827 !important; color: white !important; }
            .watermark-container img { width: 300px !important; opacity: 0.08 !important; }
        }
    </style>
</head>
<body class="p-6">

    <div class="w-full max-w-4xl mx-auto px-4 py-8 print:p-0">
        
        <div class="stat-card p-6 mb-6 no-print">
            <div class="mb-4">
                <a href="home.php" class="inline-flex items-center gap-2 text-gray-400 hover:text-gray-900 transition-colors text-xs font-bold uppercase tracking-widest"><i class="fa-solid fa-arrow-left"></i> Return to Home</a>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div class="w-full md:w-auto flex-1">
                    <h1 class="text-xl font-bold text-gray-900 mb-2">Payslip Generator</h1>
                    
                    <div class="mb-4">
                        <?php if($is_posted): ?>
                            <span class="bg-primary-50 text-primary-700 text-xs font-bold px-2.5 py-1 rounded border border-primary-200 uppercase tracking-wide">
                                <i class="fa-solid fa-check-circle mr-1"></i> Posted
                            </span>
                            <span class="text-xs text-gray-400 ml-2">By <?php echo $poster_name; ?></span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded border border-gray-200 uppercase tracking-wide">
                                <i class="fa-solid fa-pen-ruler mr-1"></i> Draft / Not Posted
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="GET" class="flex flex-col md:flex-row gap-4 mb-4">
                        <?php if ($is_hr): ?>
                        <div class="w-full md:w-72">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Search Employee</label>
                            <select name="search_ac" id="employee_search" class="w-full" onchange="this.form.submit()">
                                <option value="<?php echo $_SESSION['ac_no']; ?>">-- My Payslip --</option>
                                <?php foreach ($empList as $emp): ?>
                                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Period</label>
                            <select name="cutoff" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm bg-white" onchange="this.form.submit()">
                                <?php foreach ($cutoffs as $c): ?>
                                    <option value="<?php echo $c['val']; ?>" <?php echo ($sel_cutoff == $c['val']) ? 'selected' : ''; ?>>
                                        <?php echo $c['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($is_hr): ?>
                        <form method="POST" onsubmit="return confirm('<?php echo $is_posted ? "Unposting will REVERSE loan deductions. Continue?" : "Posting will DEDUCT loans from balances. Continue?"; ?>');">
                            <input type="hidden" name="action" value="toggle_posting">
                            <input type="hidden" name="new_status" value="<?php echo $is_posted ? '0' : '1'; ?>">
                            <?php if($is_posted): ?>
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-bold underline">
                                    <i class="fa-solid fa-ban"></i> Unpost / Hide Payslips
                                </button>
                            <?php else: ?>
                                <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-primary-foreground text-sm font-bold px-4 py-2 rounded shadow transition">
                                    <i class="fa-solid fa-bullhorn mr-1"></i> POST PAYROLL NOW
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if(!$access_denied): ?>
                <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition shadow-lg">
                    <i class="fa-solid fa-print"></i> Print (A4)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($access_denied): ?>
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-xl p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                    <i class="fa-regular fa-clock text-2xl text-gray-400"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Payslip Not Available Yet</h2>
                <p class="text-gray-500 max-w-md mx-auto">
                    The payroll for the period <strong><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></strong> is currently being processed. Please check back later once HR has posted it.
                </p>
            </div>
        <?php else: ?>
            <div class="bg-white border border-gray-300 payslip-container shadow-lg mx-auto p-8 relative overflow-hidden">
                <div class="watermark-container absolute inset-0 flex justify-center items-center pointer-events-none z-0">
                    <img src="azzurro1.png" class="w-full max-w-lg opacity-[0.08] grayscale" alt="Watermark">
                </div>

                <div class="relative z-10 border-b-2 border-gray-900 pb-4 mb-4 print-compact-y">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <img src="azzurro1.png" alt="Logo" class="h-14 w-auto grayscale">
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-gray-900">AZZURRO HOTEL</h1>
                                <p class="text-xs text-gray-500 uppercase tracking-widest">Official Payslip</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-500 uppercase">Period Covered</div>
                            <div class="text-lg font-bold text-gray-900"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 bg-gray-50/80 p-3 rounded-lg mb-4 border border-gray-200 backdrop-blur-sm print-compact-y">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm print-compact-text">
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Employee Name</span>
                            <span class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">ID Number</span>
                            <span class="font-mono text-gray-900 font-bold"><?php echo $employee['ac_no']; ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Designation</span>
                            <span class="text-gray-900 font-bold"><?php echo $employee['job_title'] ?: 'N/A'; ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Rates</span>
                            <div class="flex gap-3">
                                <?php 
                                    $dr = $employee['daily_rate'] > 0 ? $employee['daily_rate'] : ($employee['salary_rate'] / 26.0833);
                                    $hr = $employee['hourly_rate'] > 0 ? $employee['hourly_rate'] : ($dr / 8);
                                ?>
                                <span class="text-xs text-gray-600">Daily: <b><?php echo fmt($dr); ?></b></span>
                                <span class="text-xs text-gray-600">Hourly: <b><?php echo fmt($hr); ?></b></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-8 mb-4 print-compact-gap">
                    
                    <div>
                        <h3 class="text-xs font-bold text-primary-600 uppercase border-b-2 border-primary-500 pb-1 mb-2">Earnings</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-dashed divide-gray-200">
                                <tr>
                                    <td class="py-2 text-gray-600">Basic Pay <span class="text-[10px] text-gray-400 ml-1">(<?php echo $breakdown['days_worked']; ?> days)</span></td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_basic']); ?></td>
                                </tr>
                                
                                <?php foreach($allowances as $allw): ?>
                                <tr>
                                    <td class="py-2 text-blue-600">
                                        <?php echo htmlspecialchars($allw['name']); ?>
                                        <span class="text-[9px] text-gray-400 block"><?php echo $allw['notes']; ?></span>
                                    </td>
                                    <td class="py-2 text-right font-mono font-medium text-blue-600"><?php echo fmt($allw['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if($breakdown['pay_holiday'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Holiday Premium</td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_holiday']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($breakdown['pay_overtime'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Overtime <span class="text-[10px] text-gray-400 ml-1">(<?php echo fmt($breakdown['hours_ot']); ?> hrs)</span></td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_overtime']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($breakdown['pay_nightdiff'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Night Differential</td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_nightdiff']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pt-2 font-bold text-gray-900 text-xs uppercase">Total Earnings</td>
                                    <td class="pt-2 text-right font-bold font-mono text-gray-900"><?php echo fmt($gross_pay); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-red-600 uppercase border-b-2 border-red-500 pb-1 mb-2">Deductions</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-dashed divide-gray-200">
                                <?php if($breakdown['deduct_late'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-red-600">Late <span class="text-[10px] text-red-400 ml-1">(<?php echo $breakdown['mins_late']; ?> mins)</span></td>
                                    <td class="py-2 text-right font-mono text-red-600"><?php echo fmt($breakdown['deduct_late']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if($breakdown['deduct_ut'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-red-600">Undertime <span class="text-[10px] text-red-400 ml-1">(<?php echo $breakdown['mins_ut']; ?> mins)</span></td>
                                    <td class="py-2 text-right font-mono text-red-600"><?php echo fmt($breakdown['deduct_ut']); ?></td>
                                </tr>
                                <?php endif; ?>

                                <tr>
                                    <td class="py-2 text-gray-600">SSS Contribution</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($gov_deductions['sss']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">PhilHealth</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($gov_deductions['philhealth']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Pag-IBIG</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($gov_deductions['pagibig']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Withholding Tax</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($gov_deductions['tax']); ?></td>
                                </tr>

                                <?php foreach($loans as $loan): ?>
                                <tr>
                                    <td class="py-2 text-red-600 italic">
                                        <?php echo htmlspecialchars($loan['type']); ?>
                                        <span class="text-[10px] text-gray-400 ml-1 block">Bal: <?php echo fmt($loan['balance']); ?></span>
                                    </td>
                                    <td class="py-2 text-right font-mono font-semibold text-red-600"><?php echo fmt($loan['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pt-2 font-bold text-gray-900 text-xs uppercase">Total Deductions</td>
                                    <td class="pt-2 text-right font-bold font-mono text-red-600">(<?php echo fmt($total_deductions); ?>)</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="relative z-10 bg-gray-900 text-white p-4 rounded-lg flex flex-row justify-between items-center shadow-md print:bg-gray-900 print:text-white print-compact-y">
                    <div class="text-left">
                        <p class="text-xs text-gray-400 uppercase tracking-widest">Net Pay</p>
                        <p class="text-[10px] text-gray-500">Amount Received</p>
                    </div>
                    <div class="text-3xl font-bold font-mono tracking-tight">
                        <span class="text-lg align-top mr-1">PHP</span><?php echo fmt($net_pay); ?>
                    </div>
                </div>

                <div class="relative z-10 mt-8 grid grid-cols-2 gap-20 print:mt-4 print:gap-12">
                    <div class="text-center">
                        <div class="border-b border-gray-400 w-full mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Employee Signature</p>
                    </div>
                    <div class="text-center">
                        <div class="border-b border-gray-400 w-full mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Authorized By (HR)</p>
                    </div>
                </div>

                <div class="relative z-10 mt-4 text-center text-[9px] text-gray-400">
                    System Generated: <?php echo date('Y-m-d H:i:s'); ?> | <?php echo $_SESSION['full_name']; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#employee_search').select2({
                placeholder: "Type name to search...",
                allowClear: false
            });
        });
    </script>
</body>
</html>
