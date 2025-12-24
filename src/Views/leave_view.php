<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications | EMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        .subtab-btn { color: #6b7280; font-weight: 600; font-size: 0.875rem; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s; border: 1px solid transparent; }
        .subtab-btn:hover { color: #111827; background-color: #f3f4f6; }
        .subtab-btn.active { background-color: white; color: #1a2e05; border-color: #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .input-field { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none; transition: all; }
        .input-field:focus { border-color: #84cc16; box-shadow: 0 0 0 2px rgba(132, 204, 22, 0.2); }
        .label-text { display: block; font-size: 0.75rem; font-weight: 700; color: #4b5563; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.025em; }
        .panel { display: none; }
        .panel.active { display: block; }
        .status-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-Pending, .status-Submitted { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .status-Approved { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .select2-container .select2-selection--multiple { min-height: 42px; border-color: #d1d5db; border-radius: 0.5rem; }
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
                <a href="home.php" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-medium text-sm">
                    <i class="fa-solid fa-arrow-left"></i> Return to Home
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Request Application</h1>
                <p class="text-gray-500 text-sm">Submit and manage your leave, overtime, and schedule changes.</p>
            </div>
            
            <div class="bg-gray-100/50 p-1 rounded-lg flex gap-1 flex-wrap">
                <button onclick="switchTab('leave')" id="tab-leave" class="subtab-btn active"><i class="fa-solid fa-person-walking-luggage mr-2"></i> Leave</button>
                <button onclick="switchTab('changesched')" id="tab-changesched" class="subtab-btn"><i class="fa-solid fa-calendar-days mr-2"></i> Change Schedule</button>
                <?php if ($is_manager): ?>
                <button onclick="switchTab('ot')" id="tab-ot" class="subtab-btn"><i class="fa-regular fa-clock mr-2"></i> Overtime</button>
                <?php endif; ?>
                <?php if ($is_hr): ?>
                <button onclick="switchTab('hr-leave')" id="tab-hr-leave" class="subtab-btn">HR Leaves <?php if(count($hr_leaves) > 0): ?><span class="ml-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo count($hr_leaves); ?></span><?php endif; ?></button>
                <button onclick="switchTab('hr-ot')" id="tab-hr-ot" class="subtab-btn">HR OT <?php if(count($hr_ots) > 0): ?><span class="ml-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo count($hr_ots); ?></span><?php endif; ?></button>
                <button onclick="switchTab('hr-changesched')" id="tab-hr-changesched" class="subtab-btn">HR Schedules <?php if(count($hr_changes) > 0): ?><span class="ml-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo count($hr_changes); ?></span><?php endif; ?></button>
                <?php endif; ?>
            </div>
        </div>

        <div id="panel-leave" class="panel active fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="stat-card p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-gray-900 text-lg">New Leave Request</h3>
                            <div class="bg-primary-50 text-primary-700 text-xs px-2.5 py-1 rounded-full font-bold">SIL: <?php echo number_format($my_sil_credits, 1); ?></div>
                        </div>
                        <form method="POST" id="form-leave">
                            <input type="hidden" name="action" value="apply_leave">
                            <div class="mb-4">
                                <label class="label-text">Leave Type</label>
                                <select name="leave_type" required class="input-field bg-white">
                                    <option value="">Select...</option>
                                    <?php foreach($leaveTypes as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div><label class="label-text">Start Date</label><input type="date" name="start_date" required class="input-field"></div>
                                <div><label class="label-text">End Date</label><input type="date" name="end_date" required class="input-field"></div>
                            </div>
                            <div class="mb-6"><label class="label-text">Reason</label><textarea name="reason" rows="4" required placeholder="Details..." class="input-field"></textarea></div>
                            <button type="submit" class="w-full bg-gray-900 text-white font-bold py-2.5 rounded-lg hover:bg-black transition shadow-sm">Submit Request</button>
                        </form>
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="stat-card overflow-hidden p-0">
                        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center"><h3 class="font-bold text-gray-900">My Leave History</h3><span class="text-xs text-gray-500 font-bold bg-white border border-gray-200 px-2 py-1 rounded"><?php echo count($my_leaves); ?> Records</span></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Date Filed</th><th class="p-4">Type</th><th class="p-4">Dates</th><th class="p-4">Reason</th><th class="p-4">Status</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                                <?php if (count($my_leaves) > 0): foreach($my_leaves as $l): ?><tr class="hover:bg-primary-50/10 transition"><td class="p-4 text-gray-500 text-xs"><?php echo date('M d, Y', strtotime($l['created_at'])); ?></td><td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($l['leave_type']); ?></td><td class="p-4 text-gray-600 text-xs"><?php echo date('M d', strtotime($l['start_date'])); ?> - <?php echo date('M d', strtotime($l['end_date'])); ?></td><td class="p-4 text-gray-500 max-w-xs truncate text-xs"><?php echo htmlspecialchars($l['reason']); ?></td><td class="p-4"><span class="status-badge status-<?php echo $l['status']; ?>"><?php echo $l['status']; ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No leave applications found.</td></tr><?php endif; ?>
                            </tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-changesched" class="panel fade-in">
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

        <?php if ($is_manager): ?>
        <div id="panel-ot" class="panel fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="stat-card p-6">
                        <h3 class="font-bold text-gray-900 mb-6 text-lg">New Overtime Request</h3>
                        <form method="POST" id="form-ot">
                            <input type="hidden" name="action" value="apply_ot">
                            <div class="mb-4"><label class="label-text">Select Employees</label><select name="ot_employees[]" id="ot_employees" multiple required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm"><?php foreach ($deptEmployees as $emp): ?><option value="<?php echo $emp['emp_id']; ?>"><?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="grid grid-cols-2 gap-3 mb-4"><div><label class="label-text">Date</label><input type="date" name="ot_date" required class="input-field"></div><div><label class="label-text">No. of Hours</label><input type="number" name="ot_hours" step="0.5" min="0.5" required class="input-field" placeholder="e.g. 2"></div></div>
                            <div class="mb-6"><label class="label-text">Reason / Task</label><textarea name="reason" rows="4" required placeholder="Task details..." class="input-field"></textarea></div>
                            <button type="submit" class="w-full bg-gray-900 text-white font-bold py-2.5 rounded-lg hover:bg-black transition shadow-sm">Submit OT Request</button>
                        </form>
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="stat-card overflow-hidden p-0">
                        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center"><h3 class="font-bold text-gray-900">Department OT History</h3><span class="text-xs text-gray-500 font-bold bg-white border border-gray-200 px-2 py-1 rounded"><?php echo count($overtimes); ?> Records</span></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Employee</th><th class="p-4">OT Date</th><th class="p-4">Hours</th><th class="p-4">Reason</th><th class="p-4">Status</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                                <?php if (count($overtimes) > 0): foreach($overtimes as $ot): ?><tr class="hover:bg-primary-50/10 transition"><td class="p-4 font-bold text-gray-900"><?php echo htmlspecialchars(($ot['first_name'] ?? '') . ' ' . ($ot['last_name'] ?? '')); ?></td><td class="p-4 text-gray-500 text-xs"><?php echo date('M d, Y', strtotime($ot['ot_date'])); ?></td><td class="p-4 text-gray-800 font-bold"><?php echo $ot['ot_hours']; ?> hrs</td><td class="p-4 text-gray-500 max-w-xs truncate text-xs"><?php echo htmlspecialchars($ot['reason']); ?></td><td class="p-4"><span class="status-badge status-<?php echo $ot['status']; ?>"><?php echo $ot['status']; ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No overtime applications found.</td></tr><?php endif; ?>
                            </tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_hr): ?>
        <div id="panel-hr-leave" class="panel fade-in">
            <div class="stat-card overflow-hidden p-0">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50"><div><h3 class="font-bold text-gray-900 text-lg">Leave Requests Queue</h3></div><span class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"><?php echo count($hr_leaves); ?> Pending</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Employee</th><th class="p-4">Dept</th><th class="p-4">Type</th><th class="p-4">Dates</th><th class="p-4">Reason</th><th class="p-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (count($hr_leaves) > 0): foreach($hr_leaves as $l): ?>
                            <tr class="hover:bg-primary-50/10 transition"><td class="p-4 font-bold text-gray-900">
                                <?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?>
                                <span class="block text-[10px] text-yellow-600">SIL: <?php echo number_format($l['sil_credits'], 1); ?></span>
                            </td><td class="p-4 text-gray-500 text-xs"><?php echo htmlspecialchars($l['dept_name']); ?></td><td class="p-4 text-primary-600 font-bold"><?php echo htmlspecialchars($l['leave_type']); ?></td><td class="p-4 text-gray-800 text-xs"><?php $s = new DateTime($l['start_date']); $e = new DateTime($l['end_date']); echo $s->format('M d') . ' - ' . $e->format('M d') . ' <span class="text-gray-400">(' . ($s->diff($e)->days + 1) . 'd)</span>'; ?></td><td class="p-4 text-gray-500 max-w-xs text-xs"><?php echo htmlspecialchars($l['reason']); ?></td><td class="p-4 text-right"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="action" value="update_leave_status"><input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>"><button type="submit" name="status" value="Approved" onclick="return confirm('Approve leave?')" class="bg-primary-100 text-primary-700 hover:bg-primary-200 px-3 py-1.5 rounded-md text-xs font-bold transition">Approve</button><button type="submit" name="status" value="Rejected" onclick="return confirm('Reject leave?')" class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-bold transition">Reject</button></form></td></tr>
                        <?php endforeach; else: ?><tr><td colspan="6" class="p-12 text-center text-gray-400 italic">No pending leave requests.</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </div>
        </div>

        <div id="panel-hr-ot" class="panel fade-in">
            <div class="stat-card overflow-hidden p-0">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50"><div><h3 class="font-bold text-gray-900 text-lg">Overtime Requests Queue</h3></div><span class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"><?php echo count($hr_ots); ?> Pending</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b border-gray-200"><tr><th class="p-4">Employee</th><th class="p-4">Dept</th><th class="p-4">OT Date</th><th class="p-4">Hours</th><th class="p-4">Reason</th><th class="p-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (count($hr_ots) > 0): foreach($hr_ots as $ot): ?>
                            <tr class="hover:bg-primary-50/10 transition"><td class="p-4 font-bold text-gray-900"><?php echo htmlspecialchars($ot['first_name'] . ' ' . $ot['last_name']); ?></td><td class="p-4 text-gray-500 text-xs"><?php echo htmlspecialchars($ot['dept_name']); ?></td><td class="p-4 text-gray-800 text-xs"><?php echo date('M d, Y', strtotime($ot['ot_date'])); ?></td><td class="p-4 text-primary-600 font-bold"><?php echo $ot['ot_hours']; ?> hrs</td><td class="p-4 text-gray-500 max-w-xs text-xs"><?php echo htmlspecialchars($ot['reason']); ?></td><td class="p-4 text-right"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="action" value="update_ot_status"><input type="hidden" name="ot_id" value="<?php echo $ot['ot_id']; ?>"><button type="submit" name="status" value="Approved" onclick="return confirm('Approve OT?')" class="bg-primary-100 text-primary-700 hover:bg-primary-200 px-3 py-1.5 rounded-md text-xs font-bold transition">Approve</button><button type="submit" name="status" value="Rejected" onclick="return confirm('Reject OT?')" class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-bold transition">Reject</button></form></td></tr>
                        <?php endforeach; else: ?><tr><td colspan="6" class="p-12 text-center text-gray-400 italic">No pending overtime requests.</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </div>
        </div>

        <div id="panel-hr-changesched" class="panel fade-in">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#ot_employees').select2({ placeholder: "Select employees...", width: '100%' });
            
            // Re-apply active tab logic if page reloads (can be improved with localStorage)
            // For now simple tab switch
        });

        function switchTab(tabName) {
            document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.subtab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('panel-' + tabName).classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>
