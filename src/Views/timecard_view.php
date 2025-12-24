<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        body { background-color: #FAFAFA; color: #1F1F1F; }
        .editable { cursor: pointer; position: relative; }
        .editable:hover { background-color: #fef3c7; color: #d97706; font-weight: bold; border: 1px dashed #d97706; }
        .editable:hover::after { content: "✎ Edit"; position: absolute; top: -15px; right: 0; font-size: 9px; background: #d97706; color: white; padding: 2px 4px; border-radius: 4px; }
        .manual-entry { color: #d97706; font-weight: bold; position: relative; }
        .manual-entry::after { content: "•"; position: absolute; top: -5px; right: -5px; color: orange; font-size: 10px; }
        .stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); }
        .select2-container .select2-selection--single { height: 40px; border-color: #d1d5db; border-radius: 0.5rem; padding: 5px; }
        @media print { .no-print { display: none !important; } .print-only { display: block; } body { background: white; } .shadow-sm, .border, .stat-card { box-shadow: none; border: none; } }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-5xl mx-auto">
        <div class="stat-card p-6 mb-6 no-print">
            <div class="mb-2">
        <a href="home.php" class="inline-flex items-center gap-2 text-gray-400 hover:text-gray-900 transition-colors text-xs font-bold uppercase tracking-widest"><i class="fa-solid fa-arrow-left"></i> Return</a>
    </div>
            <div class="flex flex-col md:flex-row justify-between items-end gap-4">
                <div class="w-full md:w-auto">
                    <h1 class="text-xl font-bold text-gray-900 mb-4"><i class="fa-regular fa-clock text-primary-600 mr-2"></i>Daily Time Record</h1>
                    <form method="GET" class="flex flex-col md:flex-row gap-4">
                        <?php if ($is_hr): ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Employee</label>
                            <select name="search_ac" id="hr_search" class="w-full" onchange="this.form.submit()">
                                <option value="<?php echo $_SESSION['ac_no']; ?>">Myself</option>
                                <?php foreach ($empList as $emp): ?>
                                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                                        <?php echo $emp['last_name'] . ', ' . $emp['first_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Period</label>
                            <select name="cutoff" class="w-full border border-gray-300 rounded-lg p-2 text-sm outline-none bg-white h-10" onchange="this.form.submit()">
                                <?php foreach ($cutoffs as $c): ?>
                                    <option value="<?php echo $c['val']; ?>" <?php echo ($selected_cutoff == $c['val']) ? 'selected' : ''; ?>>
                                        <?php echo $c['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition shadow-lg"><i class="fa-solid fa-print"></i> Print DTR</button>
            </div>
        </div>

        <div class="stat-card p-8" id="printable">
            <div class="text-center mb-8 border-b-2 border-gray-900 pb-4">
                <h1 class="text-2xl font-bold text-gray-900 tracking-wider">AZZURRO HOTEL</h1>
                <p class="text-sm font-semibold text-gray-500 tracking-[0.2em] mt-1">OFFICIAL TIME RECORD</p>
            </div>
            
            <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 text-sm">
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">NAME</span><span class="font-bold text-gray-900 uppercase"><?php echo htmlspecialchars($target_name); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">ID NUMBER</span><span class="font-bold text-gray-900 font-mono"><?php echo htmlspecialchars($target_ac_no); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">PERIOD</span><span class="font-bold text-gray-900"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">DEPARTMENT</span><span class="font-bold text-gray-900">--</span></div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-xs text-center">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700">
                            <th rowspan="2" class="border border-gray-300 p-2 w-16">Date</th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-12">Day</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Schedule</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Actual Log <?php if($is_hr) echo '<i class="fa-solid fa-pen-to-square text-orange-500 ml-1"></i>'; ?></th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-16">Total<br>Hrs</th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-12 bg-gray-50 text-gray-800">OT<br>Hrs</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Variance</th>
                            <th rowspan="2" class="border border-gray-300 p-2">Remarks</th>
                        </tr>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 p-1 text-gray-600">IN</th>
                            <th class="border border-gray-300 p-1 text-gray-600">OUT</th>
                            <th class="border border-gray-300 p-1 text-gray-900 font-bold">IN</th>
                            <th class="border border-gray-300 p-1 text-gray-900 font-bold">OUT</th>
                            <th class="border border-gray-300 p-1 text-red-600">Late</th>
                            <th class="border border-gray-300 p-1 text-red-600">UT</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-700">
                        <?php foreach ($data as $day): 
                            $rowColor = $day['status_color'] ?? ''; 
                            $editIn = $is_hr ? "onclick=\"editTime('{$day['date']}', 'IN', '{$day['actual_in']}')\" class='editable'" : "";
                            $editOut = $is_hr ? "onclick=\"editTime('{$day['date']}', 'OUT', '{$day['actual_out']}')\" class='editable'" : "";
                            
                            $clsIn = $day['is_manual_in'] ? 'manual-entry' : '';
                            $clsOut = $day['is_manual_out'] ? 'manual-entry' : '';
                        ?>
                        <tr class="<?php echo $rowColor; ?>">
                            <td class="border border-gray-300 p-2 font-sans"><?php echo date('m/d', strtotime($day['date'])); ?></td>
                            <td class="border border-gray-300 p-2 font-sans text-[10px] uppercase font-bold text-gray-500"><?php echo substr($day['day'], 0, 3); ?></td>
                            <td class="border border-gray-300 p-2 text-gray-500"><?php echo $day['sched_in'] ?: $day['sched_code']; ?></td>
                            <td class="border border-gray-300 p-2 text-gray-500"><?php echo $day['sched_out']; ?></td>
                            
                            <td <?php echo $editIn; ?> class="border border-gray-300 p-2 font-bold <?php echo $clsIn; ?> <?php echo $day['late_mins'] > 0 ? 'text-red-600' : ''; ?>">
                                <?php echo $day['actual_in'] ?: '<span class="text-gray-300">-</span>'; ?>
                            </td>
                            
                            <td <?php echo $editOut; ?> class="border border-gray-300 p-2 font-bold <?php echo $clsOut; ?> <?php echo $day['ut_mins'] > 0 ? 'text-red-600' : ''; ?>">
                                <?php echo $day['actual_out'] ?: '<span class="text-gray-300">-</span>'; ?>
                            </td>
                            
                            <td class="border border-gray-300 p-2 font-bold"><?php echo $day['hours'] > 0 ? $day['hours'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 font-bold text-primary-700 bg-primary-50/20"><?php echo $day['ot_hours'] > 0 ? $day['ot_hours'] : '-'; ?></td>
                            <td class="border border-gray-300 p-2 <?php echo $day['late_mins'] > 0 ? 'bg-red-50 text-red-600' : ''; ?>"><?php echo $day['late_mins'] > 0 ? $day['late_mins'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 <?php echo $day['ut_mins'] > 0 ? 'bg-red-50 text-red-600' : ''; ?>"><?php echo $day['ut_mins'] > 0 ? $day['ut_mins'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 text-[10px] text-left font-sans font-semibold text-gray-600">
                                <?php echo htmlspecialchars($day['remarks']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 grid grid-cols-2 gap-20 no-print">
                <div class="text-center"><div class="border-b border-gray-900 w-full mb-2"></div><p class="text-xs font-bold text-gray-600 uppercase">Employee Signature</p></div>
                <div class="text-center"><div class="border-b border-gray-900 w-full mb-2"></div><p class="text-xs font-bold text-gray-600 uppercase">Department Head / HR</p></div>
            </div>
            
            <div class="mt-4 text-center text-[10px] text-gray-400 print-only">Generated by EMS System on <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() { $('#hr_search').select2({ placeholder: "Search Employee...", width: '100%' }); });

        function editTime(date, type, currentVal) {
            let newVal = prompt(`FORCE ADJUST ${type} for ${date}\nEnter Time (HH:MM):`, currentVal);
            if (newVal !== null) { 
                $.post('', {
                    action: 'update_log', ac_no: '<?php echo $target_ac_no; ?>',
                    date: date, type: type, new_time: newVal
                }, function(res) {
                    if (res.success) location.reload(); else alert("Error: " + res.message);
                }, 'json');
            }
        }
    </script>
</body>
</html>
