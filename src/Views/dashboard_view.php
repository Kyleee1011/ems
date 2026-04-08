<?php
$pageTitle = 'HR Dashboard — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <?php if(isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div style="grid-column: 1 / -1; background: var(--red-bg); color: var(--red); padding: 12px 20px; border-radius: 8px; border: 1px solid var(--red-bdr); margin-bottom: 20px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            Invalid security token. The form has expired, please try again.
        </div>
    <?php endif; ?>
    <div>
        <h1 class="page-title">HR Dashboard</h1>
        <p class="page-sub">Centralized workforce management and analytics</p>
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
        <button class="btn-primary" onclick="location.reload()"><i class="fa-solid fa-sync"></i> Refresh Data</button>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="flex-row mb-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <a href="?tab=dashboard" class="nav-item <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'dashboard' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Overview</a>
    <a href="?tab=approvals" class="nav-item <?php echo $activeTab === 'approvals' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'approvals' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
        Approvals 
        <?php if($totalPending > 0): ?><span class="tag tag-red" style="margin-left: 5px;"><?php echo $totalPending; ?></span><?php endif; ?>
    </a>
    <a href="?tab=payroll" class="nav-item <?php echo $activeTab === 'payroll' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'payroll' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Payroll Config</a>
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

    <!-- STATS GRID -->
    <div class="stat-grid">
        <div class="stat-card c-teal">
            <div class="stat-ico-wrap ico-teal"><i class="fa-solid fa-users"></i></div>
            <div class="stat-lbl">Total Workforce</div>
            <div class="stat-val teal"><?php echo number_format($totalEmp); ?></div>
            <div class="stat-meta">Active Employees</div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-ico-wrap ico-blue"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="stat-lbl">Avg. Monthly Salary</div>
            <div class="stat-val blue">₱<?php echo number_format($avgSalary/1000, 1); ?>k</div>
            <div class="stat-meta">Across all departments</div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-ico-wrap ico-amber"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-lbl">Pending Approvals</div>
            <div class="stat-val amber"><?php echo $totalPending; ?></div>
            <div class="stat-meta">Actions required</div>
        </div>
        <div class="stat-card c-green">
            <div class="stat-ico-wrap ico-green"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="stat-lbl">Active Loans</div>
            <div class="stat-val green"><?php echo $pendingLoansCount; ?></div>
            <div class="stat-meta">Pending Review</div>
        </div>
    </div>

    <!-- SECOND ROW: ANALYTICS -->
    <div class="grid-3 mt-20">
        <!-- ABSENTEEISM -->
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-user-slash"></i> Highest Absenteeism</div></div>
            <div class="card-body">
                <?php if ($highestAbsentDept): ?>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--ink-1);"><?php echo htmlspecialchars($highestAbsentDept['dept_name']); ?></h3>
                    <p style="font-size: 12px; color: var(--red); font-weight: 600; margin-top: 5px;"><?php echo $highestAbsentDept['absent_count']; ?> Absences</p>
                <?php else: ?>
                    <p style="color: var(--ink-4); font-style: italic;">No records found.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- LATENESS -->
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Most Frequent Lates</div></div>
            <div class="card-body">
                <?php if ($highestLateDept): ?>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--ink-1);"><?php echo htmlspecialchars($highestLateDept['dept_name']); ?></h3>
                    <p style="font-size: 12px; color: var(--amber); font-weight: 600; margin-top: 5px;"><?php echo $highestLateDept['late_count']; ?> Late Events</p>
                <?php else: ?>
                    <p style="color: var(--ink-4); font-style: italic;">No records found.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- ON LEAVE -->
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

    <!-- THIRD ROW: TALLIES & CHARTS -->
    <div class="grid-2 mt-20" style="grid-template-columns: 2fr 1fr;">
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-chart-simple"></i> Attendance Tally (<?php echo $analytics_range_label; ?>)</div></div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrap" style="border: none; border-radius: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th style="text-align: center;">Lates</th>
                                <th style="text-align: center;">Total Mins</th>
                                <th style="text-align: center;">Absents</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Merge tallies for display
                            $mergedTally = [];
                            foreach($lateTallyList as $lt) {
                                $mergedTally[$lt['name']] = ['dept' => $lt['department'], 'lates' => $lt['late_count'], 'mins' => $lt['total_late_mins'], 'absents' => 0];
                            }
                            foreach($absentTallyList as $ab) {
                                if(!isset($mergedTally[$ab['name']])) {
                                    $mergedTally[$ab['name']] = ['dept' => $ab['department'], 'lates' => 0, 'mins' => 0, 'absents' => $ab['absent_count']];
                                } else {
                                    $mergedTally[$ab['name']]['absents'] = $ab['absent_count'];
                                }
                            }
                            
                            if(empty($mergedTally)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--ink-4);">No attendance issues this period.</td></tr>
                            <?php else: 
                                foreach(array_slice($mergedTally, 0, 10) as $name => $data): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></td>
                                    <td style="font-size: 11px; color: var(--ink-4);"><?php echo htmlspecialchars($data['dept']); ?></td>
                                    <td style="text-align: center;"><span class="tag tag-amber"><?php echo $data['lates']; ?>x</span></td>
                                    <td style="text-align: center; font-family: 'DM Mono'; font-weight: 600;"><?php echo $data['mins']; ?>m</td>
                                    <td style="text-align: center;"><span class="tag tag-red"><?php echo $data['absents']; ?>d</span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-venus-mars"></i> Gender Ratio</div></div>
            <div class="card-body" style="display: flex; flex-direction: column; align-items: center;">
                <div style="width: 150px; height: 150px; margin-bottom: 20px;">
                    <canvas id="genderChart"></canvas>
                </div>
                <div style="width: 100%; space-y-10;">
                    <div class="flex-between mb-10">
                        <div class="flex-row"><div style="width: 10px; height: 10px; border-radius: 2px; background: var(--blue);"></div> <span style="font-size: 12px;">Male</span></div>
                        <span style="font-weight: 700; color: var(--blue);"><?php echo $malePercent; ?>%</span>
                    </div>
                    <div class="flex-between mb-10">
                        <div class="flex-row"><div style="width: 10px; height: 10px; border-radius: 2px; background: var(--red);"></div> <span style="font-size: 12px;">Female</span></div>
                        <span style="font-weight: 700; color: var(--red);"><?php echo $femalePercent; ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const ctx = document.getElementById('genderChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female', 'Other'],
                    datasets: [{
                        data: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>, <?php echo $otherCount; ?>],
                        backgroundColor: ['#2563eb', '#db2777', '#94a3b8'],
                        borderWidth: 0,
                        cutout: '75%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>

<?php elseif ($activeTab === 'approvals'): ?>
    <div class="grid-2" style="grid-template-columns: 1fr 1fr; align-items: start;">
        <!-- LEFT: LEAVE & OT -->
        <div class="space-y-20">
            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-calendar-day"></i> Leave Requests</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pendingLeaveList as $l): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($l['first_name'].' '.$l['last_name']); ?></td>
                                    <td><span class="tag tag-teal"><?php echo htmlspecialchars($l['leave_type']); ?></span></td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($l['start_date'])); ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display: inline-flex; gap: 4px;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="process_leave"><input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>">
                                            <button name="decision" value="approve" class="icon-btn ico-green" style="width: 26px; height: 26px;"><i class="fa-solid fa-check"></i></button>
                                            <button name="decision" value="reject" class="icon-btn ico-red" style="width: 26px; height: 26px;"><i class="fa-solid fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($pendingLeaveList)) echo "<tr><td colspan='4' style='text-align:center; padding:20px; color:var(--ink-4);'>No pending leaves.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-business-time"></i> Overtime Requests</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Hours</th>
                                    <th>Date</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pendingOTList as $ot): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($ot['first_name'].' '.$ot['last_name']); ?></td>
                                    <td style="font-family: 'DM Mono'; font-weight: 600;"><?php echo $ot['ot_hours']; ?>h</td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($ot['ot_date'])); ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display: inline-flex; gap: 4px;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="process_ot"><input type="hidden" name="ot_id" value="<?php echo $ot['ot_id']; ?>">
                                            <button name="decision" value="approve" class="icon-btn ico-green" style="width: 26px; height: 26px;"><i class="fa-solid fa-check"></i></button>
                                            <button name="decision" value="reject" class="icon-btn ico-red" style="width: 26px; height: 26px;"><i class="fa-solid fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($pendingOTList)) echo "<tr><td colspan='4' style='text-align:center; padding:20px; color:var(--ink-4);'>No pending overtime.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-calendar-exchange"></i> Change Schedule</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Target Date</th>
                                    <th>New Shift</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pendingChangeSchedList as $cs): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($cs['first_name'].' '.$cs['last_name']); ?></td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($cs['schedule_date'])); ?></td>
                                    <td><span class="tag tag-teal"><?php echo htmlspecialchars($cs['new_shift_code']); ?></span></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display: inline-flex; gap: 4px;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="process_change_sched">
                                            <input type="hidden" name="req_id" value="<?php echo $cs['id']; ?>">
                                            <button name="decision" value="approve" class="icon-btn ico-green" style="width: 26px; height: 26px;" title="Approve"><i class="fa-solid fa-check"></i></button>
                                            <button name="decision" value="reject" class="icon-btn ico-red" style="width: 26px; height: 26px;" title="Reject"><i class="fa-solid fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($pendingChangeSchedList)) echo "<tr><td colspan='4' style='text-align:center; padding:20px; color:var(--ink-4);'>No pending requests.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: SCHED & LOANS -->
        <div class="space-y-20">
            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-layer-group"></i> Schedule Batches</div></div>
                <div class="card-body">
                    <div id="batch_list" class="space-y-10">
                        <p style="text-align: center; color: var(--ink-4); font-style: italic; font-size: 12px; padding: 20px;">Loading batches...</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-hand-holding-dollar"></i> Loan Requests</div></div>
                <div class="card-body" style="text-align: center; padding: 30px;">
                    <div class="stat-ico-wrap ico-amber" style="margin: 0 auto 15px; width: 44px; height: 44px; font-size: 18px;"><i class="fa-solid fa-money-bill-transfer"></i></div>
                    <p style="font-size: 13px; font-weight: 600; color: var(--ink-2); margin-bottom: 15px;">You have <?php echo $pendingLoansCount; ?> loans pending review.</p>
                    <a href="<?php echo baseUrl('loan?tab=pending'); ?>" class="btn-primary">Go to Loan Module</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            $.get('<?php echo baseUrl('schedule/api?action=get_pending_approvals'); ?>', function(res){
                if(res.success && res.approvals.length > 0) {
                    let html = '';
                    res.approvals.forEach(batch => {
                        html += `
                            <div style="background: var(--bg-subtle); padding: 12px; border-radius: 8px; border: 1px solid var(--border-lt); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 700; font-size: 13px;">${batch.dept_name}</div>
                                    <div style="font-size: 10px; color: var(--ink-4);">${batch.cutoff_label}</div>
                                </div>
                                <button onclick="reviewBatch(${batch.dept_id}, '${batch.range_value}')" class="pill-btn" style="background: var(--teal); color: #fff; border: none; font-size: 11px;">Review</button>
                            </div>
                        `;
                    });
                    $('#batch_list').html(html);
                } else {
                    $('#batch_list').html('<p style="text-align: center; color: var(--ink-4); font-style: italic; font-size: 11px; padding: 20px;">No pending schedule batches.</p>');
                }
            }, 'json');
        });
        function reviewBatch(deptId, range) { 
            window.location.href = '<?php echo baseUrl('schedule'); ?>?dept_id=' + deptId + '&range=' + encodeURIComponent(range);
        }
    </script>

<?php elseif ($activeTab === 'payroll'): ?>
    <div class="grid-2" style="grid-template-columns: 240px 1fr; gap: 20px; align-items: start;">
        <!-- SUB-NAVIGATION -->
        <div class="card">
            <div class="card-head"><div class="card-title">Config Menu</div></div>
            <div class="card-body" style="padding: 5px;">
                <?php 
                $paySubs = [
                    'general'=>'<i class="fa-solid fa-cog"></i> General',
                    'cutoff'=>'<i class="fa-solid fa-calendar-check"></i> Cutoff Setup',
                    'sss'=>'<i class="fa-solid fa-shield-halved"></i> SSS Table',
                    'philhealth'=>'<i class="fa-solid fa-heart-pulse"></i> PhilHealth',
                    'pagibig'=>'<i class="fa-solid fa-house-chimney"></i> Pag-IBIG',
                    'tax'=>'<i class="fa-solid fa-percent"></i> Tax Table',
                    'allowances'=>'<i class="fa-solid fa-gift"></i> Allowances',
                    'ot'=>'<i class="fa-solid fa-clock"></i> Overtime',
                    'holidays'=>'<i class="fa-solid fa-calendar-day"></i> Holidays'
                ];
                foreach($paySubs as $key => $lbl): ?>
                    <a href="?tab=payroll&sub=<?php echo $key; ?>" class="nav-item <?php echo $activePaySub === $key ? 'active' : ''; ?>" style="padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-bottom: 2px;">
                        <?php echo $lbl; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CONFIG CONTENT -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <?php 
                        $titles = ['general'=>'General Settings', 'cutoff'=>'Cutoff Setup', 'sss'=>'SSS Table', 'philhealth'=>'PhilHealth Table', 'pagibig'=>'Pag-IBIG Table', 'tax'=>'Tax Table', 'allowances'=>'Allowances', 'ot'=>'Overtime Rules', 'holidays'=>'Holiday Management'];
                        echo $titles[$activePaySub] ?? 'Payroll Configuration';
                    ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Each sub-tab content goes here, simplified for brevity but maintaining structure -->
                <?php if($activePaySub === 'sss'): ?>
                    <form method="POST">
                                            <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_sss">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Min Salary</th>
                                        <th>Max Salary</th>
                                        <th>EE Share</th>
                                        <th>MPF</th>
                                        <th>EE Total</th>
                                        <th>ER Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($sssData as $idx => $row): ?>
                                    <tr>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][min]" value="<?php echo $row['min_salary']; ?>" class="input-field" style="border:none; text-align:center;"></td>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][max]" value="<?php echo $row['max_salary']; ?>" class="input-field" style="border:none; text-align:center;"></td>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][ee]" value="<?php echo $row['ee_share']; ?>" class="input-field" style="border:none; text-align:center; font-weight:700; color:var(--teal);"></td>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][mpf]" value="<?php echo $row['mpf'] ?? 0; ?>" class="input-field" style="border:none; text-align:center;"></td>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][ee_total]" value="<?php echo $row['ee_total'] ?? 0; ?>" class="input-field" style="border:none; text-align:center;"></td>
                                        <td><input type="number" step="0.01" name="sss[<?php echo $idx; ?>][er]" value="<?php echo $row['er_share'] ?? 0; ?>" class="input-field" style="border:none; text-align:center;"></td>
                                        <input type="hidden" name="sss[<?php echo $idx; ?>][id]" value="<?php echo $row['id']; ?>">
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="btn-primary mt-20">Save SSS Table</button>
                    </form>
                <?php elseif($activePaySub === 'holidays'): ?>
                    <!-- Holiday management simplified -->
                    <div class="grid-2" style="grid-template-columns: 1fr 2fr;">
                        <div>
                            <h4 class="form-label">Add Holiday</h4>
                            <form method="POST" class="space-y-10">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add_holiday">
                                <div><label class="form-label">Name</label><input type="text" name="holiday_name" class="input-field" required></div>
                                <div><label class="form-label">Date</label><input type="date" name="holiday_date" class="input-field" required></div>
                                <div><label class="form-label">Type</label><select name="holiday_type" class="input-field"><option value="REGULAR">Regular</option><option value="SPECIAL">Special</option></select></div>
                                <button class="btn-primary" style="width:100%;">Add</button>
                            </form>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Date</th><th>Name</th><th>Type</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach($holidaysList as $hol): ?>
                                    <tr>
                                        <td style="font-size:11px;"><?php echo $hol['holiday_date']; ?></td>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($hol['holiday_name']); ?></td>
                                        <td><span class="tag <?php echo $hol['holiday_type']==='REGULAR'?'tag-red':'tag-amber'; ?>"><?php echo $hol['holiday_type']; ?></span></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Delete?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_holiday"><input type="hidden" name="holiday_id" value="<?php echo $hol['id']; ?>">
                                                <button class="icon-btn" style="border:none; background:none; color:var(--red);"><i class="fa-solid fa-trash-can"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--ink-4); padding: 40px;">Selected sub-tab content is being integrated. Please check back shortly.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
