<?php
session_start();

// 1. DATABASE CONNECTION
function getDBConnection() {
    $serverName = "192.168.21.52,1433"; 
    $database = "SchedulerDB"; 
    $username = "sa"; 
    $password = "Azzurro2025"; 
    try {
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) { die("DB Connection failed."); }
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$conn = getDBConnection();
$message = "";

// 2. SAVE HANDLERS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Update OT Rules
    if (isset($_POST['action']) && $_POST['action'] == 'update_ot') {
        try {
            $sql = "UPDATE Payroll_OvertimeRules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = GETDATE() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['ot_id'] as $index => $id) {
                $stmt->execute([$_POST['ot_multiplier'][$index], $_POST['nd_multiplier'][$index], $id]);
            }
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Overtime rates updated successfully!</div>";
        } catch (Exception $e) { $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: ".$e->getMessage()."</div>"; }
    }

    // Update Holiday Rules
    if (isset($_POST['action']) && $_POST['action'] == 'update_holiday') {
        try {
            $sql = "UPDATE Payroll_HolidayRules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = GETDATE() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['hol_id'] as $index => $id) {
                $stmt->execute([$_POST['pay_unworked'][$index], $_POST['pay_worked'][$index], $id]);
            }
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Holiday rules updated successfully!</div>";
        } catch (Exception $e) { $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: ".$e->getMessage()."</div>"; }
    }

    // Update General Settings (Undertime/Late)
    if (isset($_POST['action']) && $_POST['action'] == 'update_general') {
        try {
            $sql = "UPDATE Payroll_GeneralSettings SET setting_value = ? WHERE setting_key = ?";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['settings'] as $key => $val) {
                $stmt->execute([$val, $key]);
            }
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>General settings updated successfully!</div>";
        } catch (Exception $e) { $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: ".$e->getMessage()."</div>"; }
    }
}

// 3. FETCH DATA
$ot_rules = $conn->query("SELECT * FROM Payroll_OvertimeRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hol_rules = $conn->query("SELECT * FROM Payroll_HolidayRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach($conn->query("SELECT * FROM Payroll_GeneralSettings") as $row) {
    $settings[$row['setting_key']] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Configuration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .nav-tab.active { border-bottom: 2px solid #2563eb; color: #2563eb; font-weight: 600; }
        .nav-tab { color: #6b7280; cursor: pointer; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-slate-800">

<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa-solid fa-gears text-blue-600 mr-2"></i>Payroll Configuration</h1>
    
    <?php echo $message; ?>

    <div class="bg-white rounded-t-xl shadow-sm border border-gray-200 border-b-0 px-6 pt-4 flex gap-6">
        <div onclick="showTab('ot')" id="tab-ot" class="nav-tab active pb-3 px-2">Overtime Rates</div>
        <div onclick="showTab('holiday')" id="tab-holiday" class="nav-tab pb-3 px-2">Holiday Pay</div>
        <div onclick="showTab('undertime')" id="tab-undertime" class="nav-tab pb-3 px-2">Undertime & Late</div>
    </div>

    <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 p-6 min-h-[400px]">
        
        <div id="content-ot" class="tab-content">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">Overtime & Night Differential Multipliers</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_ot">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="px-4 py-3">Day Type</th>
                                <th class="px-4 py-3">OT Multiplier (x)</th>
                                <th class="px-4 py-3">Night Diff %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($ot_rules as $idx => $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium"><?php echo $row['day_type']; ?></td>
                                <td class="px-4 py-3">
                                    <input type="hidden" name="ot_id[]" value="<?php echo $row['id']; ?>">
                                    <input type="number" step="0.0001" name="ot_multiplier[]" value="<?php echo $row['ot_multiplier']; ?>" class="border rounded px-2 py-1 w-24 bg-gray-50 focus:bg-white focus:ring-2 ring-blue-200 outline-none">
                                    <span class="text-xs text-gray-400 ml-2">(<?php echo ($row['ot_multiplier']*100); ?>%)</span>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.0001" name="nd_multiplier[]" value="<?php echo $row['night_diff_percent']; ?>" class="border rounded px-2 py-1 w-24 bg-gray-50 focus:bg-white focus:ring-2 ring-blue-200 outline-none">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-bold text-sm">Save Overtime Rates</button>
                </div>
            </form>
        </div>

        <div id="content-holiday" class="tab-content hidden">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">Holiday Pay Configuration</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_holiday">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="px-4 py-3">Holiday Type</th>
                                <th class="px-4 py-3">Pay if UNWORKED (x)</th>
                                <th class="px-4 py-3">Pay if WORKED (x)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($hol_rules as $idx => $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium"><?php echo $row['holiday_type']; ?></td>
                                <td class="px-4 py-3">
                                    <input type="hidden" name="hol_id[]" value="<?php echo $row['id']; ?>">
                                    <input type="number" step="0.0001" name="pay_unworked[]" value="<?php echo $row['pay_if_unworked']; ?>" class="border rounded px-2 py-1 w-24 bg-gray-50 focus:bg-white focus:ring-2 ring-blue-200 outline-none">
                                    <span class="text-xs text-gray-400 ml-2"><?php echo ($row['pay_if_unworked'] > 0 ? 'Paid' : 'No Pay'); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.0001" name="pay_worked[]" value="<?php echo $row['pay_if_worked']; ?>" class="border rounded px-2 py-1 w-24 bg-gray-50 focus:bg-white focus:ring-2 ring-blue-200 outline-none">
                                    <span class="text-xs text-gray-400 ml-2">(Double Pay = 2.0)</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-bold text-sm">Save Holiday Rules</button>
                </div>
            </form>
        </div>

        <div id="content-undertime" class="tab-content hidden">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">Undertime, Lateness & General Settings</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_general">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="p-4 bg-orange-50 rounded border border-orange-100">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Grace Period (Minutes)</label>
                        <input type="number" name="settings[late_grace_period_mins]" value="<?php echo $settings['late_grace_period_mins']['setting_value']; ?>" class="w-full border p-2 rounded">
                        <p class="text-xs text-gray-500 mt-1">Employees are considered "Late" after this many minutes.</p>
                    </div>

                    <div class="p-4 bg-orange-50 rounded border border-orange-100">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Undertime Deduction Rate</label>
                        <input type="number" step="0.1" name="settings[undertime_deduction_rate]" value="<?php echo $settings['undertime_deduction_rate']['setting_value']; ?>" class="w-full border p-2 rounded">
                        <p class="text-xs text-gray-500 mt-1">Multiplier. 1.0 = Deduct exact minutes missed.</p>
                    </div>

                    <div class="p-4 bg-indigo-50 rounded border border-indigo-100">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Night Shift Start</label>
                        <input type="time" name="settings[night_shift_start]" value="<?php echo $settings['night_shift_start']['setting_value']; ?>" class="w-full border p-2 rounded">
                    </div>

                    <div class="p-4 bg-indigo-50 rounded border border-indigo-100">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Night Shift End</label>
                        <input type="time" name="settings[night_shift_end]" value="<?php echo $settings['night_shift_end']['setting_value']; ?>" class="w-full border p-2 rounded">
                    </div>

                </div>
                <div class="mt-6 text-right">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-bold text-sm">Save General Settings</button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    function showTab(tabName) {
        // Hide all contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        // Remove active class from tabs
        document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active', 'border-b-2', 'border-blue-600', 'text-blue-600', 'font-bold'));
        
        // Show specific
        document.getElementById('content-' + tabName).classList.remove('hidden');
        // Activate specific tab
        let btn = document.getElementById('tab-' + tabName);
        btn.classList.add('active');
    }
</script>

</body>
</html>