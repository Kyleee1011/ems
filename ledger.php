<?php
session_start();

// ============================================
// 1. CONFIGURATION & DATABASE
// ============================================
$serverName = "192.168.21.52,1433"; 
$database = "SchedulerDB"; 
$username = "sa"; 
$password = "Azzurro2025"; 

try {
    $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Connection failed: " . $e->getMessage()); }

// Security Check: Only HR
if (!isset($_SESSION['user_id']) || ($_SESSION['approval_role'] ?? '') !== 'HR') {
    header("Location: home.php"); exit;
}

// ============================================
// 2. EXPORT HANDLER
// ============================================
if (isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loan_ledger_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Date Paid', 'Batch Period', 'AC No', 'Employee Name', 'Department', 'Loan Type', 'Amount Paid', 'Remaining Balance']);

    $dept = $_GET['dept'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT 
                l.date_paid,
                pb.cutoff_start, pb.cutoff_end,
                e.ac_no, e.first_name, e.last_name,
                d.dept_name,
                el.loan_category, el.description,
                l.amount_paid,
                el.remaining_balance
            FROM LoanLedger l
            JOIN [EmployeeManagementSystem].[dbo].[EmployeeLoans] el ON l.loan_id = el.loan_id
            JOIN PayslipHistory ph ON l.payslip_id = ph.payslip_id
            JOIN PayrollBatches pb ON ph.batch_id = pb.batch_id
            JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON el.emp_id = e.emp_id
            LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
            WHERE 1=1";
    
    $params = [];
    if (!empty($dept)) { $sql .= " AND d.dept_name = ?"; $params[] = $dept; }
    if (!empty($search)) { $sql .= " AND (e.last_name LIKE ? OR e.first_name LIKE ? OR e.ac_no LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY l.date_paid DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $period = date('M d', strtotime($row['cutoff_start'])) . ' - ' . date('M d', strtotime($row['cutoff_end']));
        $name = $row['last_name'] . ', ' . $row['first_name'];
        $type = $row['loan_category'] . ($row['description'] ? ' - ' . $row['description'] : '');
        
        fputcsv($output, [
            $row['date_paid'],
            $period,
            $row['ac_no'],
            $name,
            $row['dept_name'],
            $type,
            $row['amount_paid'],
            $row['remaining_balance']
        ]);
    }
    fclose($output);
    exit;
}

// ============================================
// 3. FETCH DATA FOR VIEW
// ============================================
// Filters
$filter_dept = $_GET['dept'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT 
            l.ledger_id,
            l.date_paid,
            l.amount_paid,
            pb.cutoff_start, 
            pb.cutoff_end,
            e.ac_no, 
            e.first_name, 
            e.last_name,
            d.dept_name,
            el.loan_category, 
            el.description,
            el.remaining_balance
        FROM LoanLedger l
        JOIN [EmployeeManagementSystem].[dbo].[EmployeeLoans] el ON l.loan_id = el.loan_id
        JOIN PayslipHistory ph ON l.payslip_id = ph.payslip_id
        JOIN PayrollBatches pb ON ph.batch_id = pb.batch_id
        JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON el.emp_id = e.emp_id
        LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
        WHERE 1=1";

$params = [];

if (!empty($filter_dept)) {
    $sql .= " AND d.dept_name = ?";
    $params[] = $filter_dept;
}

if (!empty($search_term)) {
    $sql .= " AND (e.last_name LIKE ? OR e.first_name LIKE ? OR e.ac_no LIKE ?)";
    $term = "%$search_term%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY l.date_paid DESC";

// Get Total for Summary
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_collected = 0;
foreach($records as $r) $total_collected += $r['amount_paid'];

// Fetch Departments for Filter
$depts = $conn->query("SELECT DISTINCT dept_name FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name")->fetchAll(PDO::FETCH_COLUMN);

function fmt($n) { return number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Ledger | HR Core</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        border: 'hsl(var(--border))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: { DEFAULT: '#a3e635', 50: '#f7fee7', 100: '#ecfccb', 500: '#84cc16', 600: '#65a30d', foreground: '#1F1F1F' }
                    },
                    borderRadius: { lg: '0.625rem' }
                }
            }
        }
    </script>
    <style>
        :root { --border: 220 13% 91%; --background: 0 0% 100%; --foreground: 224 71.4% 4.1%; }
        body { background-color: #FAFAFA; color: #1F1F1F; }
        .stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); }
        .input-field { border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem; width: 100%; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .input-field:focus { border-color: #84cc16; box-shadow: 0 0 0 2px rgba(132, 204, 22, 0.2); }
        .label-text { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 0.25rem; display: block; }
    </style>
</head>
<body class="p-6">

    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Loan Ledger</h1>
            <p class="text-gray-500 text-sm">Track loan payments deducted via payroll.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card p-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Collected (View)</div>
                <div class="text-3xl font-bold text-gray-900">₱ <?php echo fmt($total_collected); ?></div>
            </div>
            <div class="stat-card p-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Transactions Found</div>
                <div class="text-3xl font-bold text-gray-900"><?php echo count($records); ?></div>
            </div>
            <div class="stat-card p-6 flex flex-col justify-center items-start bg-gray-900 text-white border-gray-900">
                <div class="w-full flex justify-between items-center">
                    <div>
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Export Data</div>
                        <div class="text-sm opacity-90">Download CSV report</div>
                    </div>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['action' => 'export_csv'])); ?>" class="bg-primary text-primary-foreground px-4 py-2 rounded-lg font-bold text-sm hover:bg-primary-500 transition shadow-lg">
                        <i class="fa-solid fa-file-csv mr-1"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <div class="stat-card p-4 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="label-text">Search Employee</label>
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="ID, First or Last Name..." class="input-field pl-9">
                    </div>
                </div>
                <div class="w-full md:w-64">
                    <label class="label-text">Department</label>
                    <select name="dept" class="input-field bg-white">
                        <option value="">All Departments</option>
                        <?php foreach($depts as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo ($filter_dept == $d) ? 'selected' : ''; ?>>
                                <?php echo $d; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-gray-900 text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-black transition w-full md:w-auto">
                    Filter
                </button>
                <?php if($filter_dept || $search_term): ?>
                    <a href="ledger.php" class="bg-gray-100 text-gray-600 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-gray-200 transition w-full md:w-auto text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="stat-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Date Paid</th>
                            <th class="px-6 py-4">Employee</th>
                            <th class="px-6 py-4">Department</th>
                            <th class="px-6 py-4">Loan Details</th>
                            <th class="px-6 py-4">Payroll Period</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                            <th class="px-6 py-4 text-right">Remaining</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(count($records) > 0): ?>
                            <?php foreach($records as $row): ?>
                                <tr class="hover:bg-primary-50/10 transition">
                                    <td class="px-6 py-4 text-gray-600 font-mono text-xs">
                                        <?php echo date('Y-m-d', strtotime($row['date_paid'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></div>
                                        <div class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($row['ac_no']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase border border-gray-200">
                                            <?php echo htmlspecialchars($row['dept_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['loan_category']); ?></div>
                                        <?php if($row['description']): ?>
                                            <div class="text-xs text-gray-500 italic"><?php echo htmlspecialchars($row['description']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        <?php echo date('M d', strtotime($row['cutoff_start'])) . ' - ' . date('M d', strtotime($row['cutoff_end'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-green-600">
                                        + <?php echo fmt($row['amount_paid']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-gray-400">
                                        <?php echo fmt($row['remaining_balance']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">
                                    No records found for this filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>