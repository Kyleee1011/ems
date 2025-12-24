<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HRCore | Loan Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { navy: { 800: '#1e293b', 900: '#0f172a' }, brand: { 500: '#10b981', 600: '#059669' } }
                }
            }
        }
    </script>
    <style>
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .nav-link.active { color: #0f172a; font-weight: 600; }
        .select2-container .select2-selection--single { height: 42px; border-color: #e2e8f0; border-radius: 0.5rem; padding-top: 6px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    </style>
</head>
<body class="bg-gray-50 text-slate-600 font-sans">

    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-12">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-lg"><i class="fa-solid fa-smile"></i></div>
                    <span class="font-bold text-xl text-slate-800 tracking-tight">HRCore</span>
                </div>
                <nav class="hidden md:flex gap-8 text-sm text-gray-500">
                    <a href="home.php" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-medium text-sm">
                        <i class="fa-solid fa-arrow-left"></i> Return to Home
                    </a>
                    <a href="dashboard.php" class="nav-link hover:text-slate-900 transition-colors">Overview</a>
                    <a href="employee.php" class="nav-link hover:text-slate-900 transition-colors">Employees</a>
                    <a href="dashboard.php?tab=approvals" class="nav-link hover:text-slate-900 transition-colors">Approvals</a>
                    <a href="dashboard.php?tab=global_schedule" class="nav-link hover:text-slate-900 transition-colors">Global Schedule</a>
                    <a href="dashboard.php?tab=payroll" class="nav-link hover:text-slate-900 transition-colors">Payroll</a>
                    <a href="loan.php" class="nav-link text-slate-900 font-semibold active">Loans</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold text-gray-500"><?php echo htmlspecialchars($current_fullname); ?></span>
                <div class="w-8 h-8 rounded-full bg-navy-900 text-white flex items-center justify-center text-xs font-bold"><?php echo substr($user_role,0,2); ?></div>
            </div>
        </div>
    </header>

    <div class="bg-[#111827] pb-32 pt-10 px-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl font-bold text-white mb-1">Company Loans</h1>
            <p class="text-gray-400 text-sm">Manage employee balances, advances, and amortizations</p>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 -mt-24 pb-12 relative min-h-screen">

        <?php if ($message): ?>
            <div class="mb-6 px-4 py-3 rounded-lg shadow-sm border-l-4 <?php echo $messageType == 'success' ? 'bg-white border-green-500 text-green-700' : 'bg-white border-red-500 text-red-700'; ?> flex items-center gap-3 animate-fade-in relative">
                <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <p class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></p>
                <button onclick="this.parentElement.remove()" class="absolute right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 fade-in">
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-xs font-medium mb-2 uppercase tracking-wide">Active Loans</p>
                <h2 class="text-3xl font-bold text-gray-800"><?php echo number_format($totalActive); ?></h2>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-xs font-medium mb-2 uppercase tracking-wide">New This Month</p>
                <h2 class="text-3xl font-bold text-gray-800"><?php echo number_format($loansThisMonth); ?></h2>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-xs font-medium mb-2 uppercase tracking-wide">Total Receivables</p>
                <h2 class="text-3xl font-bold text-indigo-600">₱<?php echo number_format($totalReceivable, 2); ?></h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 fade-in">
            <!-- Form -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <div class="flex items-center gap-2 mb-6 border-b border-gray-100 pb-3">
                        <div class="w-8 h-8 rounded bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-pen-to-square"></i></div>
                        <h3 class="font-bold text-gray-800">New Application</h3>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="add_loan">
                        
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Employee</label>
                            <select name="emp_id" id="emp_selector" class="w-full" required onchange="window.location.href='loan.php?view_emp='+this.value">
                                <option value="">Select Employee...</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['emp_id']; ?>" <?php echo ($filter_emp == $emp['emp_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?php echo $emp['ac_no']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Type</label>
                                <select name="loan_category" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="Salary Advance">Salary Advance</option>
                                    <option value="Emergency Loan">Emergency Loan</option>
                                    <option value="Gadget Loan">Gadget Loan</option>
                                    <option value="SSS/Pag-IBIG">SSS/Pag-IBIG</option>
                                    <option value="Company Loan">Company Loan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Frequency</label>
                                <select name="deduction_frequency" id="freq" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" onchange="calc()">
                                    <option value="Semi-monthly">Semi-mo (x2)</option>
                                    <option value="Monthly">Monthly (x1)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Description</label>
                            <input type="text" name="description" placeholder="E.g. Hospital Bill" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Principal (₱)</label>
                                <input type="number" step="0.01" name="principal_amount" id="principal" class="w-full border border-gray-300 rounded-lg p-2 text-sm font-bold text-gray-700 focus:ring-2 focus:ring-indigo-500 outline-none" required oninput="calc()">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Interest (%)</label>
                                <input type="number" step="0.01" name="interest_rate" id="rate" value="0" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" oninput="calc()">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Terms (Months)</label>
                            <input type="number" name="months_to_pay" id="months" value="1" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required oninput="calc()">
                        </div>

                        <div class="mb-6 bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Interest:</span>
                                <span id="disp_interest">0.00</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-700 font-bold border-b border-indigo-200 pb-2 mb-2">
                                <span>Total Payable:</span>
                                <span id="disp_total">0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs uppercase font-bold text-indigo-800">Deduction<br>(Per Cutoff)</span>
                                <span class="text-xl font-bold text-indigo-600 font-mono" id="disp_deduction">0.00</span>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Start Deduction</label>
                            <input type="date" name="start_date" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <button type="submit" class="w-full bg-navy-900 hover:bg-navy-800 text-white font-bold py-3 rounded-lg shadow-md transition-transform transform hover:-translate-y-0.5">
                            Submit Application
                        </button>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden min-h-[600px]">
                    
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-gray-800">Loan History</h3>
                            <?php if(!empty($filter_emp) || !empty($filter_status)): ?>
                                <a href="loan.php" class="text-xs text-red-500 hover:underline ml-2 bg-red-50 px-2 py-1 rounded border border-red-100"><i class="fa-solid fa-times mr-1"></i>Clear Filters</a>
                            <?php endif; ?>
                        </div>
                        
                        <form method="GET" class="flex gap-2 w-full sm:w-auto">
                            <?php if(!empty($filter_emp)): ?>
                                <input type="hidden" name="view_emp" value="<?php echo $filter_emp; ?>">
                            <?php endif; ?>

                            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg text-sm p-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Paid" <?php echo $filter_status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Hold" <?php echo $filter_status == 'Hold' ? 'selected' : ''; ?>>Hold</option>
                            </select>
                        </form>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-3">Employee / Details</th>
                                    <th class="px-6 py-3 text-right">Payable</th>
                                    <th class="px-6 py-3 text-right">Balance</th>
                                    <th class="px-6 py-3 text-right">Amortization</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <th class="px-6 py-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (count($loans) > 0): ?>
                                    <?php foreach($loans as $loan): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-navy-900"><?php echo htmlspecialchars($loan['last_name'].', '.$loan['first_name']); ?></div>
                                                <div class="text-xs text-indigo-600 font-semibold"><?php echo htmlspecialchars($loan['loan_category']); ?></div>
                                                <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($loan['description']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right font-mono text-gray-600">
                                                <?php echo number_format($loan['total_payable'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-mono font-bold text-indigo-700">
                                                <?php echo number_format($loan['remaining_balance'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="font-mono text-red-500 font-medium">- <?php echo number_format($loan['per_cutoff_deduction'], 2); ?></div>
                                                <div class="text-[10px] text-gray-400"><?php echo $loan['deduction_frequency']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <?php 
                                                    $s = $loan['status'];
                                                    $cls = 'bg-gray-100 text-gray-500 border-gray-200';
                                                    if($s == 'Active') $cls = 'bg-green-50 text-green-700 border-green-200';
                                                    if($s == 'Hold') $cls = 'bg-yellow-50 text-yellow-700 border-yellow-200';
                                                ?>
                                                <span class="<?php echo $cls; ?> border px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider"><?php echo $s; ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                                                    <select name="new_status" onchange="this.form.submit()" class="text-[10px] border border-gray-300 rounded px-1 py-1 bg-white focus:border-indigo-500 outline-none">
                                                        <option value="" disabled selected>Edit</option>
                                                        <option value="Active">Active</option>
                                                        <option value="Hold">Hold</option>
                                                        <option value="Paid">Paid</option>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">
                                            No loan records found matching criteria.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        $(document).ready(function() {
            $('#emp_selector').select2({ placeholder: "Search Employee...", allowClear: true });
        });

        function calc() {
            // Get inputs
            let P = parseFloat(document.getElementById('principal').value) || 0;
            let R = parseFloat(document.getElementById('rate').value) || 0;
            let T = parseFloat(document.getElementById('months').value) || 1;
            let freq = document.getElementById('freq').value;

            // Compute
            let interest = P * (R / 100);
            let total = P + interest;
            let monthly = total / T;
            let deduction = (freq === 'Semi-monthly') ? (monthly / 2) : monthly;

            // Display
            document.getElementById('disp_interest').innerText = interest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('disp_total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('disp_deduction').innerText = deduction.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    </script>
</body>
</html>
