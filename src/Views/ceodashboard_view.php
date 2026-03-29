<?php
$pageTitle = 'CEO Portal — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Executive Dashboard</h1>
        <p class="page-sub">Azzurro HR Business Overview</p>
    </div>
    <div class="header-actions">
        <div class="pill-btn" style="padding: 2px 10px;">
            <span style="font-size: 10px; color: var(--ink-4); margin-right: 5px;">Period:</span>
            <form method="GET" style="display: inline;">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                <select name="cutoff" onchange="this.form.submit()" style="border: none; background: transparent; font-size: 11px; font-weight: 600; color: var(--teal); outline: none; cursor: pointer;">
                    <?php foreach($cutoff_options as $opt): ?>
                        <option value="<?php echo $opt['value']; ?>" <?php echo $default_cutoff === $opt['value'] ? 'selected' : ''; ?>><?php echo $opt['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="flex-row mb-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <a href="?tab=dashboard" class="nav-item <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'dashboard' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Executive Overview</a>
    <a href="?tab=schedule" class="nav-item <?php echo $activeTab === 'schedule' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'schedule' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
        Schedule
        <?php if($counts['sched'] > 0): ?><span class="tag tag-red" style="margin-left: 5px;"><?php echo $counts['sched']; ?></span><?php endif; ?>
    </a>
    <a href="?tab=overtime" class="nav-item <?php echo $activeTab === 'overtime' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'overtime' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
        Overtime
        <?php if($counts['ot'] > 0): ?><span class="tag tag-red" style="margin-left: 5px;"><?php echo $counts['ot']; ?></span><?php endif; ?>
    </a>
    <a href="?tab=leave" class="nav-item <?php echo $activeTab === 'leave' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'leave' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
        Leave
        <?php if($counts['leave'] > 0): ?><span class="tag tag-red" style="margin-left: 5px;"><?php echo $counts['leave']; ?></span><?php endif; ?>
    </a>
    <a href="?tab=loan" class="nav-item <?php echo $activeTab === 'loan' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'loan' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
        Loans
        <?php if($counts['loan'] > 0): ?><span class="tag tag-red" style="margin-left: 5px;"><?php echo $counts['loan']; ?></span><?php endif; ?>
    </a>
    <a href="?tab=global_schedule" class="nav-item <?php echo $activeTab === 'global_schedule' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'global_schedule' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Global Matrix</a>
</div>

<?php if ($activeTab === 'dashboard'): ?>
    <!-- MANPOWER EXPENSE CARDS -->
    <div class="stat-grid mb-20">
        <div class="stat-card c-purple" style="grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="stat-ico-wrap ico-purple" style="margin-bottom: 0;"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <div class="stat-lbl">Overall Total Manpower Expense</div>
                    <div class="stat-val purple">₱<?php echo number_format($manpowerStats['total_manpower_expense'], 2); ?></div>
                    <div class="stat-meta">Monthly Salary + ER Contributions (SSS, PH, PI)</div>
                </div>
            </div>
            <div style="text-align: right; padding-right: 20px;">
                <div class="stat-lbl" style="margin-bottom: 2px;">Total Monthly Salary</div>
                <div style="font-size: 16px; font-weight: 700; color: var(--ink-2);">₱<?php echo number_format($manpowerStats['total_salary'], 2); ?></div>
            </div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-ico-wrap ico-blue"><i class="fa-solid fa-building-columns"></i></div>
            <div class="stat-lbl">ER SSS Share Total</div>
            <div class="stat-val blue">₱<?php echo number_format($manpowerStats['total_er_sss'], 2); ?></div>
            <div class="stat-meta">Employer SSS Contribution</div>
        </div>
        <div class="stat-card c-purple">
            <div class="stat-ico-wrap ico-purple"><i class="fa-solid fa-heart-pulse"></i></div>
            <div class="stat-lbl">ER PhilHealth Total</div>
            <div class="stat-val purple">₱<?php echo number_format($manpowerStats['total_er_philhealth'], 2); ?></div>
            <div class="stat-meta">Employer PhilHealth Contribution</div>
        </div>
        <div class="stat-card c-green">
            <div class="stat-ico-wrap ico-green"><i class="fa-solid fa-house-chimney-user"></i></div>
            <div class="stat-lbl">ER Pag-IBIG Total</div>
            <div class="stat-val green">₱<?php echo number_format($manpowerStats['total_er_pagibig'], 2); ?></div>
            <div class="stat-meta">Employer Pag-IBIG Contribution</div>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card c-teal">
            <div class="stat-ico-wrap ico-teal"><i class="fa-solid fa-crown"></i></div>
            <div class="stat-lbl">Organization Size</div>
            <div class="stat-val teal"><?php echo number_format($totalEmp); ?></div>
            <div class="stat-meta">Total Active Employees</div>
        </div>
        <div class="stat-card c-red">
            <div class="stat-ico-wrap ico-red"><i class="fa-solid fa-bell"></i></div>
            <div class="stat-lbl">Action Required</div>
            <div class="stat-val" style="color: var(--red);"><?php echo $totalPending; ?></div>
            <div class="stat-meta">Pending Approvals</div>
        </div>
    </div>

    <div class="grid-3 mt-20">
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-user-slash"></i> Absenteeism Dept</div></div>
            <div class="card-body">
                <?php if ($highestAbsentDept): ?>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--ink-1);"><?php echo htmlspecialchars($highestAbsentDept['dept_name']); ?></h3>
                    <p style="font-size: 12px; color: var(--red); font-weight: 600; margin-top: 5px;"><?php echo $highestAbsentDept['absent_count']; ?> Absences</p>
                <?php else: ?>
                    <p style="color: var(--ink-4); font-style: italic;">No records.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Lateness Dept</div></div>
            <div class="card-body">
                <?php if ($highestLateDept): ?>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--ink-1);"><?php echo htmlspecialchars($highestLateDept['dept_name']); ?></h3>
                    <p style="font-size: 12px; color: var(--amber); font-weight: 600; margin-top: 5px;"><?php echo $highestLateDept['late_count']; ?> Late Events</p>
                <?php else: ?>
                    <p style="color: var(--ink-4); font-style: italic;">No records.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fa-solid fa-calendar-check"></i> On Leave</div>
                <span class="tag tag-teal"><?php echo count($employeesOnLeave); ?></span>
            </div>
            <div class="card-body" style="max-height: 120px; overflow-y: auto;">
                <?php if (!empty($employeesOnLeave)): foreach($employeesOnLeave as $lv): ?>
                    <div class="flex-between mb-10">
                        <span style="font-weight: 600; font-size: 12px;"><?php echo htmlspecialchars($lv['last_name']); ?></span>
                        <span class="tag tag-blue" style="font-size: 9px;"><?php echo $lv['leave_type']; ?></span>
                    </div>
                <?php endforeach; else: ?>
                    <p style="color: var(--ink-4); font-style: italic; font-size: 11px;">No one on leave.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mt-20">
        <div class="card-head"><div class="card-title"><i class="fa-solid fa-chart-pie"></i> Department Distribution</div></div>
        <div class="card-body">
            <div style="height: 200px; width: 100%;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const ctx = document.getElementById('deptChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $deptLabels; ?>,
                    datasets: [{
                        label: 'Employees',
                        data: <?php echo $deptCounts; ?>,
                        backgroundColor: '#177488',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>

<?php elseif (in_array($activeTab, ['schedule', 'overtime', 'leave'])): ?>
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid <?php echo ($activeTab==='schedule'?'fa-calendar-check':($activeTab==='overtime'?'fa-clock':'fa-plane-departure')); ?>"></i> 
                Pending <?php echo ucfirst($activeTab); ?> Approvals
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <?php if ($activeTab === 'schedule'): ?>
                            <tr><th>Department</th><th>Cutoff Period</th><th style="text-align: right;">Action</th></tr>
                        <?php elseif ($activeTab === 'overtime'): ?>
                            <tr><th>Employee</th><th>Date</th><th>Hours</th><th style="text-align: right;">Action</th></tr>
                        <?php else: ?>
                            <tr><th>Employee</th><th>Type</th><th>Dates</th><th style="text-align: right;">Action</th></tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if(empty($approvalData)): ?>
                            <tr><td colspan="3" style="text-align: center; padding: 40px; color: var(--ink-4);">No pending <?php echo $activeTab; ?> requests.</td></tr>
                        <?php else: foreach($approvalData as $row): ?>
                            <tr>
                                <?php if ($activeTab === 'schedule'): ?>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($row['dept_name']); ?></td>
                                    <td style="font-size: 11px;"><?php echo $row['display']; ?></td>
                                    <td style="text-align: right;">
                                        <a href="<?php echo baseUrl('schedule?dept_id=' . $row['dept_id'] . '&range=' . urlencode($row['range_val'])); ?>" class="pill-btn">Review & Approve</a>
                                    </td>
                                <?php elseif ($activeTab === 'overtime'): ?>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
                                    <td style="font-size: 11px;"><?php echo date('M d, Y', strtotime($row['ot_date'])); ?></td>
                                    <td style="font-family: 'DM Mono'; font-weight: 700;"><?php echo $row['ot_hours']; ?>h</td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display: inline-flex; gap: 4px;">
                                            <input type="hidden" name="action" value="process_ot"><input type="hidden" name="ot_id" value="<?php echo $row['ot_id']; ?>">
                                            <button name="decision" value="approve" class="icon-btn ico-green" style="width: 26px; height: 26px;"><i class="fa-solid fa-check"></i></button>
                                            <button name="decision" value="reject" class="icon-btn ico-red" style="width: 26px; height: 26px;"><i class="fa-solid fa-times"></i></button>
                                        </form>
                                    </td>
                                <?php else: ?>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
                                    <td><span class="tag tag-teal"><?php echo $row['leave_type']; ?></span></td>
                                    <td style="font-size: 10px;"><?php echo date('M d', strtotime($row['start_date'])); ?> - <?php echo date('M d', strtotime($row['end_date'])); ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display: inline-flex; gap: 4px;">
                                            <input type="hidden" name="action" value="process_leave"><input type="hidden" name="leave_id" value="<?php echo $row['leave_id']; ?>">
                                            <button name="decision" value="approve" class="icon-btn ico-green" style="width: 26px; height: 26px;"><i class="fa-solid fa-check"></i></button>
                                            <button name="decision" value="reject" class="icon-btn ico-red" style="width: 26px; height: 26px;"><i class="fa-solid fa-times"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($activeTab === 'loan'): ?>
    <div class="card" style="max-width: 500px; margin: 40px auto; text-align: center;">
        <div class="card-body" style="padding: 40px;">
            <div class="stat-ico-wrap ico-amber" style="width: 50px; height: 50px; margin: 0 auto 20px; font-size: 20px;"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--ink-1); margin-bottom: 10px;">Loan Approvals</h3>
            <p style="color: var(--ink-3); font-size: 13px; margin-bottom: 25px;">You have <?php echo $counts['loan']; ?> loan applications pending your final executive review.</p>
            <a href="<?php echo baseUrl('loan?tab=pending'); ?>" class="btn-primary" style="padding: 10px 30px;">Manage Loans</a>
        </div>
    </div>

<?php elseif ($activeTab === 'global_schedule'): ?>
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-calendar-week"></i> Organization Schedule Matrix</div>
            <div class="flex-row">
                <select id="viewer_dept" class="input-field" style="width: 160px; height: 32px; font-size: 11px;">
                    <?php foreach($depts as $d): ?><option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option><?php endforeach; ?>
                </select>
                <select id="viewer_range" class="input-field" style="width: 180px; height: 32px; font-size: 11px;">
                    <?php foreach($cutoff_options as $opt): ?><option value="<?php echo $opt['value']; ?>"><?php echo $opt['label']; ?></option><?php endforeach; ?>
                </select>
                <button onclick="loadGlobalGrid()" class="btn-primary" style="height: 32px; padding: 0 15px;">Load Matrix</button>
            </div>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <div id="grid_container">
                <p style="text-align: center; color: var(--ink-4); padding: 50px;">Select filters to view the schedule grid.</p>
            </div>
        </div>
    </div>

    <script>
        function loadGlobalGrid() {
            let d = $('#viewer_dept').val(); let r = $('#viewer_range').val();
            $('#grid_container').html('<p style="text-align: center; color: var(--ink-4); padding: 50px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading data...</p>');
            
            $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_schedules', dept_id: d, range: r }, function(res){
                if(!res.success) { $('#grid_container').html('<p style="text-align:center; padding:30px; color:var(--red);">Error.</p>'); return; }
                
                let dates = []; let startStr = r.split('|')[0]; let start = new Date(startStr); let end = new Date(r.split('|')[1]); let curr = new Date(start);
                while (curr <= end) { dates.push(new Date(curr)); curr.setDate(curr.getDate() + 1); }
                
                let html = '<div class="table-wrap" style="border:none; border-radius:0;"><table><thead><tr><th style="min-width:180px;">Employee</th>';
                dates.forEach(dt => { html += `<th style="text-align:center; font-size:9px;">${dt.toISOString().substring(5, 10)}</th>`; });
                html += '</tr></thead><tbody>';

                $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_employees_by_dept', dept_id: d }, function(empRes){
                    empRes.employees.forEach(emp => {
                        html += `<tr><td style="font-weight:600; font-size:11px;">${emp.name}</td>`;
                        dates.forEach(dt => {
                            let dateStr = dt.toISOString().split('T')[0];
                            let cell = res.schedules[emp.id] && res.schedules[emp.id][dateStr] ? res.schedules[emp.id][dateStr] : {code:'-', time:'-'};
                            let style = cell.code === 'OFF' ? 'color:var(--teal); font-weight:700;' : 'color:var(--ink-4);';
                            html += `<td style="text-align:center; font-size:9px; ${style}">${cell.time}</td>`; 
                        });
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    $('#grid_container').html(html);
                }, 'json');
            }, 'json');
        }
    </script>
<?php endif; ?>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
