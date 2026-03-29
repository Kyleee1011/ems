<?php
$pageTitle = 'Daily Time Record — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<style>
    .editable { cursor: pointer; position: relative; }
    .editable:hover { background-color: var(--amber-bg); color: var(--amber); font-weight: bold; border: 1px dashed var(--amber); }
    .editable:hover::after { content: "✎ Edit"; position: absolute; top: -15px; right: 0; font-size: 9px; background: var(--amber); color: white; padding: 2px 4px; border-radius: 4px; }
    
    .manual-entry { color: var(--amber); font-weight: bold; position: relative; }
    .manual-entry::after { content: "•"; position: absolute; top: -5px; right: -5px; color: var(--amber); font-size: 10px; }
    
    .cls-col { border-left: 1px solid var(--border-lt) !important; border-right: 1px solid var(--border-lt) !important; }
    .bg-nd { background-color: var(--teal-bg) !important; }
    .bg-rd { background-color: var(--blue-bg) !important; }
    .bg-hol { background-color: var(--amber-bg) !important; }
    .bg-red-tint { background-color: var(--red-bg) !important; }

    @media print { 
        .no-print { display: none !important; } 
        body { background: white; padding: 0; } 
        .main { padding: 0; animation: none; }
        .sidebar, .topbar { display: none !important; }
        .app { display: block; }
        .card { border: none !important; box-shadow: none !important; }
        table { border: 1px solid #000 !important; }
        th, td { border: 1px solid #000 !important; color: #000 !important; font-size: 7px !important; padding: 2px !important; }
    }
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fa-regular fa-clock"></i> Daily Time Record</h1>
        <p class="page-sub">Official attendance logs and comprehensive classification</p>
    </div>
    <div class="header-actions">
        <button onclick="window.print()" class="btn-primary"><i class="fa-solid fa-print"></i> Print DTR</button>
    </div>
</div>

<div class="card p-20 mb-20 no-print">
    <form method="GET" class="flex-row" style="flex-wrap: wrap; align-items: flex-end;">
        <?php if ($is_hr): ?>
        <div class="form-group mb-0" style="flex: 1; min-width: 250px;">
            <label class="form-label">Employee Selection</label>
            <select name="search_ac" id="hr_search" class="input-field" onchange="this.form.submit()">
                <option value="<?php echo $_SESSION['ac_no']; ?>">-- My Personal Record --</option>
                <?php foreach ($empList as $emp): ?>
                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                        <?php echo $emp['last_name'] . ', ' . $emp['first_name']; ?> (<?php echo $emp['ac_no']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group mb-0" style="width: 250px;">
            <label class="form-label">Pay Period</label>
            <select name="cutoff" id="cutoff_select" class="input-field" onchange="this.form.submit()">
                <?php foreach ($cutoffs as $c): ?>
                    <option value="<?php echo $c['val']; ?>" <?php echo ($selected_cutoff == $c['val']) ? 'selected' : ''; ?>>
                        <?php echo $c['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="card p-20" id="printable">
    <div style="text-align: center; border-bottom: 2px solid var(--ink-1); padding-bottom: 10px; margin-bottom: 15px;">
        <h1 style="font-size: 20px; font-weight: 800; letter-spacing: 2px;">AZZURRO HOTEL</h1>
        <p style="font-size: 10px; font-weight: 600; color: var(--ink-3); text-transform: uppercase; letter-spacing: 3px;">OFFICIAL TIME RECORD</p>
    </div>

    <div class="grid-2 mb-15" style="gap: 30px; font-size: 11px;">
        <div class="flex-between" style="border-bottom: 1px solid var(--border-lt); padding-bottom: 3px;">
            <span style="font-weight: 700; color: var(--ink-4);">NAME</span>
            <span style="font-weight: 800; text-transform: uppercase;"><?php echo htmlspecialchars($target_name); ?></span>
        </div>
        <div class="flex-between" style="border-bottom: 1px solid var(--border-lt); padding-bottom: 3px;">
            <span style="font-weight: 700; color: var(--ink-4);">ID NUMBER</span>
            <span style="font-weight: 800; font-family: 'DM Mono';"><?php echo htmlspecialchars($target_ac_no); ?></span>
        </div>
        <div class="flex-between" style="border-bottom: 1px solid var(--border-lt); padding-bottom: 3px;">
            <span style="font-weight: 700; color: var(--ink-4);">PERIOD</span>
            <span style="font-weight: 800;"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></span>
        </div>
        <div class="flex-between" style="border-bottom: 1px solid var(--border-lt); padding-bottom: 3px;">
            <span style="font-weight: 700; color: var(--ink-4);">DEPARTMENT</span>
            <span style="font-weight: 800; text-transform: uppercase;"><?php echo htmlspecialchars($targetDept); ?></span>
        </div>
    </div>

    <div class="table-wrap" style="border: 1px solid var(--border); border-radius: 4px; overflow-x: auto;">
        <table id="dtrTable" style="font-size: 8px; text-align: center; border-collapse: collapse; min-width: 1200px;">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 45px;">Date</th>
                    <th rowspan="2" style="width: 30px;">Day</th>
                    <th colspan="2" style="background: var(--bg-subtle);">Schedule</th>
                    <th colspan="2" style="background: var(--bg-subtle);">Actual Log</th>
                    <th rowspan="2" style="width: 35px;">Total<br>Hrs</th>
                    <th rowspan="2" style="width: 30px; background: var(--teal-bg);">OT<br>Hrs</th>
                    <th colspan="2" style="background: var(--red-bg);">Variance</th>
                    <th rowspan="2" style="min-width: 80px;">Remarks</th>
                    <th rowspan="2" class="cls-col bg-nd">ND<br>Min</th>
                    <th colspan="2" class="cls-col bg-nd">Night Diff</th>
                    <th colspan="2" class="cls-col bg-rd">Rest Day</th>
                    <th colspan="2" class="cls-col bg-hol">Regular Hol</th>
                    <th colspan="2" class="cls-col bg-hol">Special Hol</th>
                    <th colspan="2" class="cls-col bg-hol">Double Hol</th>
                    <th colspan="2" class="cls-col bg-nd">ND<br>RH</th>
                    <th colspan="2" class="cls-col bg-nd">ND<br>SH</th>
                    <th colspan="2" class="cls-col bg-nd">ND<br>DH</th>
                    <th colspan="2" class="cls-col bg-rd">RD<br>RH</th>
                    <th colspan="2" class="cls-col bg-rd">RD<br>SH</th>
                    <th rowspan="2" style="width: 40px;">Type</th>
                </tr>
                <tr>
                    <th style="background: var(--bg-raised);">IN</th>
                    <th style="background: var(--bg-raised);">OUT</th>
                    <th style="background: var(--bg-card); font-weight: 800;">IN</th>
                    <th style="background: var(--bg-card); font-weight: 800;">OUT</th>
                    <th style="color: var(--red);">Late</th>
                    <th style="color: var(--red);">UT</th>
                    
                    <th class="bg-nd">Hrs</th><th class="bg-nd">OT</th>
                    <th class="bg-rd">Hrs</th><th class="bg-rd">OT</th>
                    <th class="bg-hol">Hrs</th><th class="bg-hol">OT</th>
                    <th class="bg-hol">Hrs</th><th class="bg-hol">OT</th>
                    <th class="bg-hol">Hrs</th><th class="bg-hol">OT</th>
                    <th class="bg-nd">Hrs</th><th class="bg-nd">OT</th>
                    <th class="bg-nd">Hrs</th><th class="bg-nd">OT</th>
                    <th class="bg-nd">Hrs</th><th class="bg-nd">OT</th>
                    <th class="bg-rd">Hrs</th><th class="bg-rd">OT</th>
                    <th class="bg-rd">Hrs</th><th class="bg-rd">OT</th>
                </tr>
            </thead>
            <tbody style="font-family: 'DM Mono'; font-weight: 500;">
                <?php 
                $grand = [
                    'hrs'=>0, 'ot'=>0, 'late'=>0, 'ut'=>0, 'nd_m'=>0,
                    'nd_h'=>0, 'nd_ot'=>0, 'rd_h'=>0, 'rd_ot'=>0,
                    'rh_h'=>0, 'rh_ot'=>0, 'sh_h'=>0, 'sh_ot'=>0, 'dh_h'=>0, 'dh_ot'=>0,
                    'nd_rh_h'=>0, 'nd_rh_ot'=>0, 'nd_sh_h'=>0, 'nd_sh_ot'=>0, 'nd_dh_h'=>0, 'nd_dh_ot'=>0,
                    'rd_rh_h'=>0, 'rd_rh_ot'=>0, 'rd_sh_h'=>0, 'rd_sh_ot'=>0
                ];
                foreach ($data as $day): 
                    $grand['hrs'] += (float)$day['hours']; $grand['ot'] += (float)$day['ot_hours'];
                    $grand['late'] += (int)$day['late_mins']; $grand['ut'] += (int)$day['ut_mins'];
                    $grand['nd_m'] += (int)$day['night_diff_mins'];
                    $grand['nd_h'] += (float)$day['nd_hrs']; $grand['nd_ot'] += (float)$day['nd_ot_hrs'];
                    $grand['rd_h'] += (float)$day['rest_day_hrs']; $grand['rd_ot'] += (float)$day['rest_day_ot_hrs'];
                    $grand['rh_h'] += (float)$day['regular_holiday_hrs']; $grand['rh_ot'] += (float)$day['regular_holiday_ot_hrs'];
                    $grand['sh_h'] += (float)$day['special_holiday_hrs']; $grand['sh_ot'] += (float)$day['special_holiday_ot_hrs'];
                    $grand['dh_h'] += (float)$day['double_holiday_hrs']; $grand['dh_ot'] += (float)$day['double_holiday_ot_hrs'];
                    $grand['nd_rh_h'] += (float)$day['nd_regular_holiday_hrs']; $grand['nd_rh_ot'] += 0; // Model logic check?
                    $grand['nd_sh_h'] += (float)$day['nd_special_holiday_hrs'];
                    $grand['nd_dh_h'] += (float)$day['nd_double_holiday_hrs'];
                    $grand['rd_rh_h'] += (float)$day['rest_day_regular_holiday_hrs'];
                    $grand['rd_sh_h'] += (float)$day['rest_day_special_holiday_hrs'];

                    $editIn = $is_hr ? "onclick=\"editTime('{$day['date']}', 'IN', '{$day['actual_in']}')\" class='editable'" : "";
                    $editOut = $is_hr ? "onclick=\"editTime('{$day['date']}', 'OUT', '{$day['actual_out']}')\" class='editable'" : "";
                    $clsIn = $day['is_manual_in'] ? 'manual-entry' : ''; $clsOut = $day['is_manual_out'] ? 'manual-entry' : '';
                    
                    $v = fn($val) => ($val > 0) ? number_format($val, (floor($val) == $val ? 0 : 2)) : '';
                ?>
                <tr>
                    <td style="font-family:'Inter'; font-weight:600;"><?php echo date('m/d', strtotime($day['date'])); ?></td>
                    <td style="font-family:'Inter'; color:var(--ink-4); font-size:7px;"><?php echo substr($day['day'], 0, 3); ?></td>
                    <td style="color:var(--ink-4);"><?php echo $day['sched_in'] ?: $day['sched_code']; ?></td>
                    <td style="color:var(--ink-4);"><?php echo $day['sched_out']; ?></td>
                    <td <?php echo $editIn; ?> class="<?php echo $clsIn; ?> <?php echo $day['late_mins'] > 0 ? 'text-red' : ''; ?>" style="font-weight:700;"><?php echo $day['actual_in'] ?: '-'; ?></td>
                    <td <?php echo $editOut; ?> class="<?php echo $clsOut; ?> <?php echo $day['ut_mins'] > 0 ? 'text-red' : ''; ?>" style="font-weight:700;"><?php echo $day['actual_out'] ?: '-'; ?></td>
                    <td style="font-weight:700;"><?php echo $v($day['hours']); ?></td>
                    <td style="background:var(--teal-bg); font-weight:700;"><?php echo $v($day['ot_hours']); ?></td>
                    <td style="color:var(--red);"><?php echo $day['late_mins'] ?: ''; ?></td>
                    <td style="color:var(--red);"><?php echo $day['ut_mins'] ?: ''; ?></td>
                    <td style="font-family:'Inter'; font-size:7px; text-align:left; padding-left:4px;"><?php echo $day['remarks']; ?></td>
                    
                    <td class="bg-nd" style="font-weight:700;"><?php echo $day['night_diff_mins'] ?: ''; ?></td>
                    <td class="bg-nd"><?php echo $v($day['nd_hrs']); ?></td><td class="bg-nd"><?php echo $v($day['nd_ot_hrs']); ?></td>
                    <td class="bg-rd"><?php echo $v($day['rest_day_hrs']); ?></td><td class="bg-rd"><?php echo $v($day['rest_day_ot_hrs']); ?></td>
                    <td class="bg-hol"><?php echo $v($day['regular_holiday_hrs']); ?></td><td class="bg-hol"><?php echo $v($day['regular_holiday_ot_hrs']); ?></td>
                    <td class="bg-hol"><?php echo $v($day['special_holiday_hrs']); ?></td><td class="bg-hol"><?php echo $v($day['special_holiday_ot_hrs']); ?></td>
                    <td class="bg-hol"><?php echo $v($day['double_holiday_hrs']); ?></td><td class="bg-hol"><?php echo $v($day['double_holiday_ot_hrs']); ?></td>
                    <td class="bg-nd"><?php echo $v($day['nd_regular_holiday_hrs']); ?></td><td class="bg-nd"></td>
                    <td class="bg-nd"><?php echo $v($day['nd_special_holiday_hrs']); ?></td><td class="bg-nd"></td>
                    <td class="bg-nd"><?php echo $v($day['nd_double_holiday_hrs']); ?></td><td class="bg-nd"></td>
                    <td class="bg-rd"><?php echo $v($day['rest_day_regular_holiday_hrs']); ?></td><td class="bg-rd"></td>
                    <td class="bg-rd"><?php echo $v($day['rest_day_special_holiday_hrs']); ?></td><td class="bg-rd"></td>
                    <td style="font-family:'Inter'; font-size:6px; font-weight:700;"><?php echo $day['day_type']!=='REGULAR_DAY'?$day['day_type']:''; ?></td>
                </tr>
                <?php endforeach; ?>
                
                <tr style="font-weight:800; background:var(--bg-raised);">
                    <td colspan="6" style="text-align:right; padding-right:10px; font-family:'Inter';">TOTALS</td>
                    <td><?php echo number_format($grand['hrs'], 2); ?></td>
                    <td style="background:var(--teal-bg);"><?php echo number_format($grand['ot'], 2); ?></td>
                    <td style="color:var(--red);"><?php echo $grand['late']; ?></td>
                    <td style="color:var(--red);"><?php echo $grand['ut']; ?></td>
                    <td></td>
                    <td class="bg-nd"><?php echo $grand['nd_m']; ?></td>
                    <td class="bg-nd"><?php echo number_format($grand['nd_h'], 2); ?></td><td class="bg-nd"><?php echo number_format($grand['nd_ot'], 2); ?></td>
                    <td class="bg-rd"><?php echo number_format($grand['rd_h'], 2); ?></td><td class="bg-rd"><?php echo number_format($grand['rd_ot'], 2); ?></td>
                    <td class="bg-hol"><?php echo number_format($grand['rh_h'], 2); ?></td><td class="bg-hol"><?php echo number_format($grand['rh_ot'], 2); ?></td>
                    <td class="bg-hol"><?php echo number_format($grand['sh_h'], 2); ?></td><td class="bg-hol"><?php echo number_format($grand['sh_ot'], 2); ?></td>
                    <td class="bg-hol"><?php echo number_format($grand['dh_h'], 2); ?></td><td class="bg-hol"><?php echo number_format($grand['dh_ot'], 2); ?></td>
                    <td class="bg-nd"><?php echo number_format($grand['nd_rh_h'], 2); ?></td><td class="bg-nd">-</td>
                    <td class="bg-nd"><?php echo number_format($grand['nd_sh_h'], 2); ?></td><td class="bg-nd">-</td>
                    <td class="bg-nd"><?php echo number_format($grand['nd_dh_h'], 2); ?></td><td class="bg-nd">-</td>
                    <td class="bg-rd"><?php echo number_format($grand['rd_rh_h'], 2); ?></td><td class="bg-rd">-</td>
                    <td class="bg-rd"><?php echo number_format($grand['rd_sh_h'], 2); ?></td><td class="bg-rd">-</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="grid-2 no-print" style="margin-top: 40px; text-align: center; gap: 80px; padding: 0 40px;">
        <div><div style="border-bottom: 1px solid var(--ink-1); margin-bottom: 8px;"></div><p style="font-size:9px; font-weight:700; color:var(--ink-3); text-transform:uppercase;">Employee Signature</p></div>
        <div><div style="border-bottom: 1px solid var(--ink-1); margin-bottom: 8px;"></div><p style="font-size:9px; font-weight:700; color:var(--ink-3); text-transform:uppercase;">Authorized Signatory</p></div>
    </div>
</div>

<script>
    function editTime(date, type, currentVal) {
        let newVal = prompt(`FORCE ADJUST ${type} for ${date}\nEnter Time (HH:MM):`, currentVal);
        if (newVal !== null) {
            $.post('', { action:'update_log', ac_no:'<?php echo $target_ac_no; ?>', date:date, type:type, new_time:newVal }, res => { if(res.success) location.reload(); else alert("Error: "+res.message); }, 'json');
        }
    }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
