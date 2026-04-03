<?php
$pageTitle = 'Biometric Reliability Audit — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-microchip"></i> Biometric Integrity Audit</h1>
        <p class="page-sub">Identifying missing clock-ins/outs for the current payroll period</p>
    </div>
    <div class="header-actions">
        <form method="GET" action="" class="flex-row" style="gap: 10px;">
            <input type="hidden" name="action" value="audit">
            <select name="cutoff" class="input-field" style="width: 250px;" onchange="this.form.submit()">
                <?php foreach ($cutoffs as $c): ?>
                    <option value="<?php echo $c['value']; ?>" <?php echo $selected_cutoff === $c['value'] ? 'selected' : ''; ?>>
                        <?php echo $c['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="pill-btn"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-title">
            Incomplete Time Logs 
            <span class="tag tag-red" style="margin-left: 10px;"><?php echo count($brokenLogs); ?> Anomalies Found</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrap" style="border: none;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Shift Date</th>
                        <th>Schedule</th>
                        <th>Actual Log</th>
                        <th>Detected Issue</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($brokenLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px; color: var(--ink-4);">
                                <i class="fa-solid fa-circle-check" style="font-size: 40px; color: var(--green); margin-bottom: 15px; opacity: 0.5;"></i>
                                <p style="font-weight: 600;">Data Integrity Verified</p>
                                <p style="font-size: 12px;">No missing logs found for this period.</p>
                            </td>
                        </tr>
                    <?php else: foreach ($brokenLogs as $log): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($log['name']); ?></div>
                                <div style="font-size: 10px; color: var(--ink-4); font-family: 'DM Mono';"><?php echo $log['ac_no']; ?></div>
                            </td>
                            <td><span class="tag tag-teal"><?php echo htmlspecialchars($log['dept']); ?></span></td>
                            <td style="font-weight: 600;"><?php echo date('M d, Y (D)', strtotime($log['date'])); ?></td>
                            <td style="font-size: 11px; color: var(--ink-3);"><?php echo $log['sched']; ?></td>
                            <td>
                                <div class="flex-row" style="gap: 15px; font-family: 'DM Mono'; font-size: 12px; font-weight: 700;">
                                    <span style="color: <?php echo $log['actual_in'] === '--:--' ? 'var(--red)' : 'var(--green)'; ?>;">
                                        <i class="fa-solid fa-right-to-bracket"></i> <?php echo $log['actual_in']; ?>
                                    </span>
                                    <span style="color: <?php echo $log['actual_out'] === '--:--' ? 'var(--red)' : 'var(--green)'; ?>;">
                                        <i class="fa-solid fa-right-from-bracket"></i> <?php echo $log['actual_out']; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="tag tag-red" style="font-size: 10px; font-weight: 800;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $log['issue']; ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?php echo baseUrl('timecard?search_ac=' . $log['ac_no'] . '&cutoff=' . urlencode($selected_cutoff)); ?>" 
                                   class="btn-primary" style="font-size: 10px; padding: 5px 12px; height: 28px;">
                                    Resolve & Adjust
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-20" style="background: var(--bg-subtle);">
    <div class="card-body" style="font-size: 12px; color: var(--ink-3); display: flex; gap: 15px; align-items: flex-start;">
        <i class="fa-solid fa-circle-info" style="color: var(--teal); font-size: 16px; margin-top: 2px;"></i>
        <div>
            <p><b>Audit Logic:</b> This layer flags any workday where an employee has a schedule but only one punch (missing either IN or OUT). These must be corrected via <b>Manual Adjustments</b> before payroll is generated to ensure accurate overtime and undertime calculations.</p>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
