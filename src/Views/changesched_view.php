<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Schedule | EMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#a3e635', 50: '#f7fee7', 100: '#ecfccb', 500: '#84cc16', 600: '#65a30d', foreground: '#1F1F1F' }
                    },
                    borderRadius: { lg: '0.625rem' }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAFAFA; color: #1F1F1F; }
        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .stat-card { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); }
        .input-field { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none; transition: all; }
        .input-field:focus { border-color: #84cc16; box-shadow: 0 0 0 2px rgba(132, 204, 22, 0.2); }
        .label-text { display: block; font-size: 0.75rem; font-weight: 700; color: #4b5563; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.025em; }
        .status-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-Pending, .status-Submitted { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .status-Approved { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 font-sans p-6">

    <div class="max-w-7xl mx-auto">
        <?php if ($message): ?>
            <div class="mb-6 px-4 py-3 rounded-lg shadow-sm border-l-4 <?php echo $messageType == 'success' ? 'bg-white border-primary text-green-700' : 'bg-white border-red-500 text-red-700'; ?> flex items-center gap-3 animate-fade-in relative">
                <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : (strpos($messageType, 'warning') !== false ? 'fa-triangle-exclamation' : 'fa-exclamation-circle'); ?>"></i>
                <p class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <a href="<?php echo baseUrl('home'); ?>" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-medium text-sm">
                    <i class="fa-solid fa-arrow-left"></i> Return to Home
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Change Schedule</h1>
                <p class="text-gray-500 text-sm">Request and manage your shift changes.</p>
            </div>
        </div>

        <div class="fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="stat-card p-6">
                        <h3 class="font-bold text-gray-900 mb-6 text-lg">Request Schedule Change</h3>
                        <form method="POST" id="form-changesched">
                            <input type="hidden" name="action" value="apply_change_sched">
                            <div class="mb-4">
                                <label class="label-text">Date of Shift</label>
                                <input type="date" name="sched_date" required class="input-field">
                            </div>
                            <div class="mb-4">
                                <label class="label-text">New Shift / Code</label>
                                <select name="new_shift_code" required class="input-field bg-white">
                                    <option value="">Select New Shift...</option>
                                    <optgroup label="Regular Shifts">
                                        <?php foreach($shifts as $s): ?>
                                            <option value="<?php echo $s['shift_code']; ?>"><?php echo $s['shift_name']; ?> (<?php echo substr($s['time_in'], 0, 5) . '-' . substr($s['time_out'], 0, 5); ?>)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Special / Off">
                                        <option value="OFF">OFF</option>
                                        <option value="FLEX">FLEX</option>
                                        <option value="HOLIDAY OFF">HOLIDAY OFF</option>
                                        <option value="LWOP">Leave Without Pay</option>
                                        <option value="LWP">Leave With Pay</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="mb-6"><label class="label-text">Reason</label><textarea name="reason" rows="4" required placeholder="Why do you need to change this shift?" class="input-field"></textarea></div>
                            <button type="submit" class="w-full bg-gray-900 text-white font-bold py-2.5 rounded-lg hover:bg-black transition shadow-sm">Submit Request</button>
                        </form>
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="stat-card overflow-hidden p-0">
                        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center"><h3 class="font-bold text-gray-900">My Change History</h3><span class="text-xs text-gray-500 font-bold bg-white border border-gray-200 px-2 py-1 rounded"><?php echo count($my_sched_changes); ?> Records</span></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Requested On</th><th class="p-4">Target Date</th><th class="p-4">New Shift</th><th class="p-4">Reason</th><th class="p-4">Status</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                                <?php if (count($my_sched_changes) > 0): foreach($my_sched_changes as $sc): ?><tr class="hover:bg-primary-50/10 transition"><td class="p-4 text-gray-500 text-xs"><?php echo date('M d, Y', strtotime($sc['created_at'])); ?></td><td class="p-4 font-bold text-gray-800"><?php echo date('M d, Y', strtotime($sc['schedule_date'])); ?></td><td class="p-4 text-primary-600 font-bold"><?php echo htmlspecialchars($sc['new_shift_code']); ?></td><td class="p-4 text-gray-500 max-w-xs truncate text-xs"><?php echo htmlspecialchars($sc['reason']); ?></td><td class="p-4"><span class="status-badge status-<?php echo $sc['status']; ?>"><?php echo $sc['status']; ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No schedule change requests found.</td></tr><?php endif; ?>
                            </tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_hr): ?>
        <div class="mt-8 fade-in">
            <div class="stat-card overflow-hidden p-0">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50"><div><h3 class="font-bold text-gray-900 text-lg">Schedule Change Queue</h3></div><span class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"><?php echo count($hr_changes); ?> Pending</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Employee</th><th class="p-4">Dept</th><th class="p-4">Target Date</th><th class="p-4">New Shift</th><th class="p-4">Reason</th><th class="p-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (count($hr_changes) > 0): foreach($hr_changes as $rc): ?>
                            <tr class="hover:bg-primary-50/10 transition"><td class="p-4 font-bold text-gray-900"><?php echo htmlspecialchars($rc['first_name'] . ' ' . $rc['last_name']); ?></td><td class="p-4 text-gray-500 text-xs"><?php echo htmlspecialchars($rc['dept_name']); ?></td><td class="p-4 text-gray-800 text-xs"><?php echo date('M d, Y', strtotime($rc['schedule_date'])); ?></td><td class="p-4 text-primary-600 font-bold"><?php echo htmlspecialchars($rc['new_shift_code']); ?></td><td class="p-4 text-gray-500 max-w-xs text-xs"><?php echo htmlspecialchars($rc['reason']); ?></td><td class="p-4 text-right"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="action" value="update_change_status"><input type="hidden" name="req_id" value="<?php echo $rc['id']; ?>"><button type="submit" name="status" value="Approved" onclick="return confirm('Approve Change?')" class="bg-primary-100 text-primary-700 hover:bg-primary-200 px-3 py-1.5 rounded-md text-xs font-bold transition">Approve</button><button type="submit" name="status" value="Rejected" onclick="return confirm('Reject Change?')" class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-bold transition">Reject</button></form></td></tr>
                        <?php endforeach; else: ?><tr><td colspan="6" class="p-12 text-center text-gray-400 italic">No pending schedule change requests.</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
