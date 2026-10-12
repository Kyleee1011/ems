<?php
$pageTitle = 'Leave Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

$creditsMap = [
    'sil' => (float)($my_all_credits['sil_credits'] ?? 0.0),
    'vl' => (float)($my_all_credits['vl_credits'] ?? 0.0),
    'sl' => (float)($my_all_credits['sl_credits'] ?? 0.0),
    'paternity' => (float)($my_all_credits['paternity_credits'] ?? 7.0),
    'maternity' => (float)($my_all_credits['maternity_credits'] ?? 105.0),
    'bereavement_imm' => (float)($my_all_credits['bereavement_immediate_credits'] ?? 5.0),
    'bereavement_ext' => (float)($my_all_credits['bereavement_extended_credits'] ?? 3.0),
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-person-walking-luggage"></i> Leave Requests</h1>
        <p class="page-sub">Philippine Standard Leave Benefits & Workforce Approvals</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <div class="pill-btn" style="background: var(--amber-bg); color: var(--amber); border-color: var(--amber-bdr);" title="Service Incentive Leave Credits">
            SIL: <?php echo number_format($creditsMap['sil'], 1); ?>d
        </div>
        <div class="pill-btn" style="background: var(--teal-bg); color: var(--teal); border-color: var(--teal-border);" title="Vacation Leave Credits">
            VL: <?php echo number_format($creditsMap['vl'], 1); ?>d
        </div>
        <div class="pill-btn" style="background: var(--blue-bg, #eff6ff); color: var(--blue, #2563eb); border-color: var(--blue-border, #bfdbfe);" title="Sick Leave Credits">
            SL: <?php echo number_format($creditsMap['sl'], 1); ?>d
        </div>
        <div class="pill-btn" style="background: #fdf2f8; color: #db2777; border-color: #fbcfe8;" title="Maternity Leave Credits">
            ML: <?php echo number_format($creditsMap['maternity'], 0); ?>d
        </div>
        <div class="pill-btn" style="background: #f5f3ff; color: #7c3aed; border-color: #ddd6fe;" title="Paternity Leave Credits">
            PL: <?php echo number_format($creditsMap['paternity'], 0); ?>d
        </div>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="flex-row mb-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <button onclick="switchTab('leave')" id="tab-leave" class="nav-item active" style="padding: 10px 5px; border-bottom: 2px solid var(--teal); background: none;">Personal Service</button>
    <?php if ($is_hr): ?>
    <button onclick="switchTab('hr-leave')" id="tab-hr-leave" class="nav-item" style="padding: 10px 5px; border-bottom: 2px solid transparent; background: none;">
        Pending Approvals
        <?php if(count($hr_leaves) > 0): ?><span class="tag tag-red" style="margin-left:5px;"><?php echo count($hr_leaves); ?></span><?php endif; ?>
    </button>
    <button onclick="switchTab('hr-credits')" id="tab-hr-credits" class="nav-item" style="padding: 10px 5px; border-bottom: 2px solid transparent; background: none;">
        <i class="fa-solid fa-coins"></i> Employee Credits Manager (HR Only)
    </button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="card mb-20" style="background: <?php echo $messageType == 'success' ? 'var(--green-bg)' : ($messageType == 'warning' ? 'var(--amber-bg)' : 'var(--red-bg)'); ?>;">
        <div class="card-body" style="color: <?php echo $messageType == 'success' ? 'var(--green)' : ($messageType == 'warning' ? 'var(--amber)' : 'var(--red)'); ?>; font-weight: 600;">
            <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : ($messageType == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div id="panel-leave" class="panel active">
    <div class="grid-2" style="grid-template-columns: 360px 1fr; align-items: start; gap: 20px;">
        <!-- REQUEST FORM -->
        <div class="space-y-20">
            <div class="card" id="leave-form-card">
                <div class="card-head"><div class="card-title">New Leave Application</div></div>
                <div class="card-body">
                    <form method="POST" id="leaveForm" onsubmit="return validateLeaveFormBeforeSubmit()">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="apply_leave">
                        
                        <div class="form-group">
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" required class="input-field select2" id="leaveTypeSelect" onchange="checkLeaveRules()">
                                <option value="">Select Type...</option>
                                <?php foreach($leaveTypes as $type): 
                                    $disabled = false;
                                    $extraText = '';
                                    if ($type === 'Service Incentive Leave') {
                                        if ($creditsMap['sil'] <= 0) {
                                            $disabled = true;
                                            $extraText = ' (0 credit available - Cannot select)';
                                        } else {
                                            $extraText = ' (' . number_format($creditsMap['sil'], 1) . ' credits)';
                                        }
                                    } elseif ($type === 'Vacation Leave') {
                                        $extraText = ' (' . number_format($creditsMap['vl'], 1) . ' credits - 3 days notice)';
                                    } elseif ($type === 'Sick Leave') {
                                        $extraText = ' (' . number_format($creditsMap['sl'], 1) . ' credits - 2 hrs notice)';
                                    } elseif ($type === 'Bereavement (Immediate Family)') {
                                        $extraText = ' (Max 5 Days - Immediate Family)';
                                    } elseif ($type === 'Bereavement (Extended Family)') {
                                        $extraText = ' (Max 3 Days - Extended Family)';
                                    } elseif ($type === 'Paternity Leave') {
                                        $extraText = ' (7 Days RA 8187)';
                                    } elseif ($type === 'Maternity Leave (105 Days Unpaid)') {
                                        $extraText = ' (105 Days RA 11210 Unpaid)';
                                    }
                                ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $disabled ? 'disabled style="color:var(--ink-4);"' : ''; ?>>
                                        <?php echo htmlspecialchars($type . $extraText); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Policy Info Badge -->
                        <div id="leaveTypeNotice" style="display:none; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 11px;"></div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" required class="input-field" id="leaveStartDate" onchange="checkLeaveRules()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" required class="input-field" id="leaveEndDate" onchange="checkLeaveRules()">
                            </div>
                        </div>

                        <!-- Dynamic rule violation warning -->
                        <div id="leaveRuleWarning" style="display:none; background: var(--amber-bg); border: 1px solid var(--amber-bdr); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                            <div style="display: flex; align-items: start; gap: 8px;">
                                <i class="fa-solid fa-triangle-exclamation" style="color: var(--amber); margin-top: 2px;"></i>
                                <div>
                                    <div style="font-weight: 700; color: var(--amber); font-size: 12px;" id="ruleWarningTitle">Filing Requirement Notice</div>
                                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 2px;" id="ruleWarningText"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reason / Particulars</label>
                            <textarea name="reason" class="input-field" placeholder="Briefly explain your leave..." style="min-height: 90px;"></textarea>
                        </div>
                        <button type="submit" class="btn-primary" id="leaveSubmitBtn" style="width: 100%; justify-content: center; height: 40px;">Submit Request</button>
                    </form>
                </div>
            </div>

            <!-- APPEAL FORM (shown when rule violation occurs) -->
            <div class="card" id="appeal-form-card" style="display: <?php echo ($show_appeal && $appeal_data) ? 'block' : 'none'; ?>; border: 2px solid var(--amber-bdr);">
                <div class="card-head" style="background: var(--amber-bg);">
                    <div class="card-title" style="color: var(--amber);">
                        <i class="fa-solid fa-gavel"></i> Submit Leave Appeal to HR
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($appeal_data): ?>
                    <div style="background: var(--amber-bg); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 11px;">
                        <strong style="color: var(--amber);">Notice:</strong>
                        <span style="color: var(--ink-3);"><?php echo htmlspecialchars($appeal_data['violation_note'] ?? ''); ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="appealForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="apply_leave_appeal">
                        <input type="hidden" name="leave_type" value="<?php echo htmlspecialchars($appeal_data['leave_type'] ?? ''); ?>" id="appealLeaveType">
                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($appeal_data['start_date'] ?? ''); ?>" id="appealStartDate">
                        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($appeal_data['end_date'] ?? ''); ?>" id="appealEndDate">
                        <input type="hidden" name="reason" value="<?php echo htmlspecialchars($appeal_data['reason'] ?? ''); ?>" id="appealReason">
                        <input type="hidden" name="violation_note" value="<?php echo htmlspecialchars($appeal_data['violation_note'] ?? ''); ?>" id="appealViolationNote">

                        <div style="margin-bottom: 10px; font-size: 12px; color: var(--ink-3);">
                            <strong><?php echo htmlspecialchars($appeal_data['leave_type'] ?? ''); ?></strong>
                            &mdash;
                            <?php echo htmlspecialchars(($appeal_data['start_date'] ?? '') . ' to ' . ($appeal_data['end_date'] ?? '')); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="color: var(--amber); font-weight: 700;">Reason for Late Filing / Justification <span style="color: var(--red);">*</span></label>
                            <textarea name="appeal_reason" class="input-field" required placeholder="Explain emergency or reason for late submission..." style="min-height: 80px;"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Supporting Document <span style="font-weight: 400; color: var(--ink-4);">(Medical Certificate, proof, etc.)</span></label>
                            <input type="file" name="appeal_attachment" class="input-field" accept=".png,.jpg,.jpeg,.pdf,.docx" style="padding: 6px;">
                        </div>

                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px; background: var(--amber); border-color: var(--amber-bdr);">
                            <i class="fa-solid fa-gavel"></i> Submit Appeal for HR Approval
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- HISTORY & CREDITS BREAKDOWN -->
        <div class="space-y-20">
            <!-- PHILIPPINE LEAVE BENEFITS SUMMARY -->
            <div class="card">
                <div class="card-head"><div class="card-title"><i class="fa-solid fa-scale-balanced"></i> Philippine Standard Leave Allowances</div></div>
                <div class="card-body" style="padding: 12px 16px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; font-size: 11px;">
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: var(--teal);">Service Incentive (SIL)</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">5 days/yr under Labor Code Art. 95</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;"><?php echo number_format($creditsMap['sil'], 1); ?>d available</div>
                        </div>
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: var(--teal);">Vacation Leave (VL)</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">Min. 3 days advance filing rule</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;"><?php echo number_format($creditsMap['vl'], 1); ?>d available</div>
                        </div>
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: var(--teal);">Sick Leave (SL)</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">Min. 2 hours before duty rule</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;"><?php echo number_format($creditsMap['sl'], 1); ?>d available</div>
                        </div>
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: #7c3aed;">Bereavement Leave</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">Immediate: 5 days | Extended: 3 days</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;">5d / 3d statutory</div>
                        </div>
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: #2563eb;">Paternity Leave</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">7 days (Republic Act 8187)</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;"><?php echo number_format($creditsMap['paternity'], 0); ?>d entitlement</div>
                        </div>
                        <div style="padding: 8px; border-radius: 6px; background: var(--bg-raised); border: 1px solid var(--border-lt);">
                            <div style="font-weight: 700; color: #db2777;">Maternity Leave</div>
                            <div style="color: var(--ink-3); font-size: 10px; margin-top: 2px;">105 days unpaid (Republic Act 11210)</div>
                            <div style="font-weight: 800; font-size: 12px; margin-top: 4px;"><?php echo number_format($creditsMap['maternity'], 0); ?>d entitlement</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MY LEAVE HISTORY -->
            <div class="card">
                <div class="card-head"><div class="card-title">My Leave History</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr><th>Type</th><th>Dates</th><th>Duration</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($my_leaves as $l): 
                                    $sDate = new DateTime($l['start_date']);
                                    $eDate = new DateTime($l['end_date']);
                                    $daysCount = $sDate->diff($eDate)->days + 1;
                                ?>
                                <tr>
                                    <td style="font-weight: 700;">
                                        <?php echo htmlspecialchars($l['leave_type']); ?>
                                        <?php if (!empty($l['is_appeal'])): ?>
                                            <span class="tag tag-amber" style="font-size: 9px; margin-left: 4px;">APPEAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($l['start_date'])); ?> - <?php echo date('M d', strtotime($l['end_date'])); ?></td>
                                    <td style="font-size: 11px; font-weight: 700;"><?php echo $daysCount; ?>d</td>
                                    <td>
                                        <span class="tag <?php 
                                            echo $l['status'] === 'Approved' ? 'tag-green' : ($l['status'] === 'Rejected' ? 'tag-red' : 'tag-amber'); 
                                        ?>"><?php echo $l['status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($my_leaves)) echo "<tr><td colspan='4' style='text-align:center; padding:20px;'>No records.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CONSUMPTION -->
            <div class="card">
                <div class="card-head"><div class="card-title">Approved Leaves Consumption (Current Year)</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr><th>Leave Type</th><th>Date Range</th><th>Days</th><th>Reason</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($consumed_history as $h): ?>
                                <tr>
                                    <td style="font-weight: 700; font-size: 11px;"><?php echo htmlspecialchars($h['leave_type']); ?></td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($h['start_date'])); ?> - <?php echo date('M d', strtotime($h['end_date'])); ?></td>
                                    <td style="font-weight: 700; font-family: 'DM Mono';"><?php echo $h['days_used']; ?>d</td>
                                    <td style="font-size: 11px; color: var(--ink-4);"><?php echo htmlspecialchars($h['reason']); ?></td>
                                </tr>
                                <?php endforeach; if(empty($consumed_history)) echo "<tr><td colspan='4' style='text-align:center; padding:20px;'>No approved leaves recorded for this year.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($is_hr): ?>
<div id="panel-hr-leave" class="panel" style="display:none;">
    <div class="card">
        <div class="card-head"><div class="card-title">Pending Applications Queue</div></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Credits & Particulars</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($hr_leaves as $l): 
                            $s = new DateTime($l['start_date']); $e = new DateTime($l['end_date']);
                            $days = $s->diff($e)->days + 1;
                            $isAppeal = !empty($l['is_appeal']);

                            // Compute remaining credit for the requested type
                            $relCredit = null;
                            if ($l['leave_type'] === 'Service Incentive Leave') $relCredit = $l['sil_credits'];
                            elseif ($l['leave_type'] === 'Vacation Leave') $relCredit = $l['vl_credits'] ?? 0;
                            elseif ($l['leave_type'] === 'Sick Leave') $relCredit = $l['sl_credits'] ?? 0;
                            elseif ($l['leave_type'] === 'Paternity Leave') $relCredit = $l['paternity_credits'] ?? 7;
                            elseif (strpos($l['leave_type'], 'Maternity') !== false) $relCredit = $l['maternity_credits'] ?? 105;
                            elseif ($l['leave_type'] === 'Bereavement (Immediate Family)') $relCredit = $l['bereavement_immediate_credits'] ?? 5;
                            elseif ($l['leave_type'] === 'Bereavement (Extended Family)') $relCredit = $l['bereavement_extended_credits'] ?? 3;
                        ?>
                        <tr style="<?php echo $isAppeal ? 'background: var(--amber-bg);' : ''; ?>">
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?></div>
                                <div style="font-size: 10px; color: var(--ink-4);"><?php echo htmlspecialchars($l['dept_name'] ?? 'General'); ?></div>
                            </td>
                            <td>
                                <span class="tag tag-teal"><?php echo htmlspecialchars($l['leave_type']); ?></span>
                                <?php if ($isAppeal): ?>
                                    <span class="tag tag-amber" style="font-size: 9px; font-weight: 800; letter-spacing: 0.5px; margin-left: 3px;">
                                        <i class="fa-solid fa-gavel" style="margin-right: 2px;"></i> APPEAL
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11px;">
                                <?php echo $s->format('M d') . ' - ' . $e->format('M d'); ?>
                                <span style="color: var(--ink-4);">(<?php echo $days; ?>d)</span>
                            </td>
                            <td style="font-size: 11px; color: var(--ink-3); max-width: 250px;">
                                <?php if ($relCredit !== null): ?>
                                    <div style="font-size: 10px; font-weight: 700; color: var(--teal); margin-bottom: 2px;">
                                        Balance: <?php echo number_format($relCredit, 1); ?>d
                                    </div>
                                <?php endif; ?>
                                <div><?php echo htmlspecialchars($l['reason']); ?></div>

                                <?php if ($isAppeal): ?>
                                    <div style="margin-top: 6px; padding: 6px 8px; background: var(--amber-bg); border: 1px solid var(--amber-bdr); border-radius: 6px;">
                                        <div style="font-size: 9px; font-weight: 700; color: var(--amber); text-transform: uppercase; margin-bottom: 2px;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Late Filing / Policy Notice
                                        </div>
                                        <div style="font-size: 10px; color: var(--ink-2);"><?php echo htmlspecialchars($l['violation_note'] ?? ''); ?></div>
                                        <div style="margin-top: 4px; font-size: 10px; color: var(--ink-3);">
                                            <strong>Appeal Reason:</strong> <?php echo htmlspecialchars($l['appeal_reason'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($l['attachment_path'])): ?>
                                        <div style="margin-top: 4px;">
                                            <a href="<?php echo htmlspecialchars($l['attachment_path']); ?>" target="_blank" style="font-size: 10px; color: var(--teal); text-decoration: underline;">
                                                <i class="fa-solid fa-paperclip"></i> View Supporting Document
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" style="display: inline-flex; gap: 5px;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_leave_status"><input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>">
                                    <button name="status" value="Approved" class="icon-btn ico-green" onclick="return confirm('Approve<?php echo $isAppeal ? ' this appeal' : ''; ?>?')" style="width: 30px; height: 30px;" title="Approve"><i class="fa-solid fa-check"></i></button>
                                    <button name="status" value="Rejected" class="icon-btn ico-red" onclick="return confirm('Reject<?php echo $isAppeal ? ' this appeal' : ''; ?>?')" style="width: 30px; height: 30px;" title="Reject"><i class="fa-solid fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($hr_leaves)) echo "<tr><td colspan='5' style='text-align:center; padding:40px; color:var(--ink-4);'>No pending applications.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($is_hr): ?>
<!-- HR ONLY: EMPLOYEE LEAVE CREDITS MANAGEMENT PANEL -->
<div id="panel-hr-credits" class="panel" style="display:none;">
    <div class="card">
        <div class="card-head" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div class="card-title"><i class="fa-solid fa-coins"></i> Employee Leave Credits Manager</div>
                <p style="font-size: 11px; color: var(--ink-4); margin-top: 3px;">
                    <i class="fa-solid fa-lock"></i> Restricted to HR: Only HR can set, allocate, or adjust employee leave credits.
                </p>
            </div>
            <div style="font-size: 11px; color: var(--teal); font-weight: 700;">
                Total Active Employees: <?php echo count($all_employees_credits); ?>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th style="text-align: center;">SIL</th>
                            <th style="text-align: center;">VL</th>
                            <th style="text-align: center;">SL</th>
                            <th style="text-align: center;">Paternity</th>
                            <th style="text-align: center;">Maternity</th>
                            <th style="text-align: center;">Bereavement (Imm/Ext)</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_employees_credits as $ec): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($ec['first_name'] . ' ' . $ec['last_name']); ?></div>
                                <div style="font-size: 10px; color: var(--ink-4);">AC: <?php echo htmlspecialchars($ec['ac_no'] ?? '-'); ?></div>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: 'DM Mono';">
                                <span class="tag <?php echo ((float)$ec['sil_credits'] > 0) ? 'tag-green' : 'tag-amber'; ?>">
                                    <?php echo number_format((float)$ec['sil_credits'], 1); ?>d
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: 'DM Mono';">
                                <span class="tag tag-teal"><?php echo number_format((float)($ec['vl_credits'] ?? 0), 1); ?>d</span>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: 'DM Mono';">
                                <span class="tag tag-blue"><?php echo number_format((float)($ec['sl_credits'] ?? 0), 1); ?>d</span>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: 'DM Mono'; font-size: 11px;">
                                <?php echo number_format((float)($ec['paternity_credits'] ?? 7), 0); ?>d
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: 'DM Mono'; font-size: 11px;">
                                <?php echo number_format((float)($ec['maternity_credits'] ?? 105), 0); ?>d
                            </td>
                            <td style="text-align: center; font-size: 11px; font-weight: 700; font-family: 'DM Mono';">
                                <?php echo number_format((float)($ec['bereavement_immediate_credits'] ?? 5), 0); ?>d / <?php echo number_format((float)($ec['bereavement_extended_credits'] ?? 3), 0); ?>d
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn-primary" style="font-size: 10px; height: 26px; padding: 0 10px;" 
                                    onclick='openCreditEditModal(<?php echo json_encode($ec); ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i> Set Credits
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($all_employees_credits)) echo "<tr><td colspan='8' style='text-align:center; padding:30px; color:var(--ink-4);'>No active employees found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- HR CREDIT EDIT MODAL -->
<div id="creditEditModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); width:500px; max-width:95%; border-radius:12px; border:1px solid var(--border); box-shadow:var(--sh-lg); overflow:hidden;">
        <div style="padding:15px 20px; background:var(--bg-raised); border-bottom:1px solid var(--border-lt); display:flex; justify-content:space-between; align-items:center;">
            <div style="font-weight:700; font-size:14px; color:var(--ink-1);"><i class="fa-solid fa-coins" style="color:var(--amber);"></i> Set Employee Leave Credits</div>
            <button type="button" onclick="closeCreditEditModal()" style="border:none; background:none; font-size:16px; cursor:pointer; color:var(--ink-3);">&times;</button>
        </div>
        <form method="POST" style="padding:20px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_credits">
            <input type="hidden" name="target_emp_id" id="credit_modal_emp_id">

            <div style="margin-bottom:15px; font-weight:700; color:var(--teal);" id="credit_modal_emp_name">Employee Name</div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" style="color:var(--amber); font-weight:700;">SIL Credits</label>
                    <input type="number" step="0.5" name="sil_credits" id="cm_sil_credits" class="input-field" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Vacation Leave (VL)</label>
                    <input type="number" step="0.5" name="vl_credits" id="cm_vl_credits" class="input-field" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Sick Leave (SL)</label>
                    <input type="number" step="0.5" name="sl_credits" id="cm_sl_credits" class="input-field" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Paternity (Days)</label>
                    <input type="number" step="1" name="paternity_credits" id="cm_paternity_credits" class="input-field" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Maternity (Days)</label>
                    <input type="number" step="1" name="maternity_credits" id="cm_maternity_credits" class="input-field" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Bereavement Immediate</label>
                    <input type="number" step="1" name="bereavement_immediate_credits" id="cm_bereav_imm" class="input-field" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Bereavement Extended</label>
                    <input type="number" step="1" name="bereavement_extended_credits" id="cm_bereav_ext" class="input-field" required>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; padding-top:15px; border-top:1px solid var(--border-lt);">
                <button type="button" class="pill-btn" onclick="closeCreditEditModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> Save Credits</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const userCredits = <?php echo json_encode($creditsMap); ?>;

    function switchTab(tabName) {
        document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.nav-item').forEach(n => {
            n.classList.remove('active');
            n.style.borderBottomColor = 'transparent';
        });
        
        document.getElementById('panel-' + tabName).style.display = 'block';
        document.getElementById('tab-' + tabName).classList.add('active');
        document.getElementById('tab-' + tabName).style.borderBottomColor = 'var(--teal)';
    }

    /**
     * Client-side validation before submitting the standard leave form.
     */
    function validateLeaveFormBeforeSubmit() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const startDate = document.getElementById('leaveStartDate').value;
        const endDate = document.getElementById('leaveEndDate').value;

        if (!leaveType) {
            alert('Please select a leave type.');
            return false;
        }

        if (leaveType === 'Service Incentive Leave' && userCredits.sil <= 0) {
            alert('Insufficient SIL Credits: SIL cannot be selected without available SIL credits.');
            return false;
        }

        if (startDate && endDate) {
            const s = new Date(startDate);
            const e = new Date(endDate);
            if (e < s) {
                alert('End date cannot be earlier than start date.');
                return false;
            }
            const days = Math.floor((e - s) / (1000 * 60 * 60 * 24)) + 1;

            if (leaveType === 'Service Incentive Leave' && days > userCredits.sil) {
                alert('Insufficient SIL credits for this requested duration (' + days + ' days requested, ' + userCredits.sil + ' available).');
                return false;
            }
            if (leaveType === 'Bereavement (Immediate Family)' && days > 5) {
                alert('Bereavement leave for immediate family is capped at maximum 5 days under Philippine standard.');
                return false;
            }
            if (leaveType === 'Bereavement (Extended Family)' && days > 3) {
                alert('Bereavement leave for extended family is capped at maximum 3 days.');
                return false;
            }
            if (leaveType === 'Paternity Leave' && days > 7) {
                alert('Paternity leave is capped at maximum 7 days under Republic Act 8187.');
                return false;
            }
            if (leaveType.indexOf('Maternity') !== -1 && days > 105) {
                alert('Maternity leave is capped at maximum 105 days under Republic Act 11210.');
                return false;
            }
        }
        return true;
    }

    /**
     * Check leave filing rules (VL 3 days, SL 2 hours, caps).
     */
    function checkLeaveRules() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const startDate = document.getElementById('leaveStartDate').value;
        const endDate = document.getElementById('leaveEndDate').value || startDate;
        const warningBox = document.getElementById('leaveRuleWarning');
        const warningText = document.getElementById('ruleWarningText');
        const appealCard = document.getElementById('appeal-form-card');
        const noticeBox = document.getElementById('leaveTypeNotice');

        // Show statutory notice per leave type
        if (leaveType) {
            noticeBox.style.display = 'block';
            if (leaveType === 'Vacation Leave') {
                noticeBox.style.background = 'var(--teal-bg)';
                noticeBox.style.color = 'var(--teal-deep)';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-circle-info"></i> Notice:</strong> Vacation leave must be filed at least <strong>3 days in advance</strong>. If filing within 3 days, an appeal is required.';
            } else if (leaveType === 'Sick Leave') {
                noticeBox.style.background = '#eff6ff';
                noticeBox.style.color = '#1e40af';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-circle-info"></i> Notice:</strong> Sick leave must be filed at least <strong>2 hours before duty start</strong>. Otherwise, submit an appeal justification below.';
            } else if (leaveType === 'Bereavement (Immediate Family)') {
                noticeBox.style.background = '#f5f3ff';
                noticeBox.style.color = '#5b21b6';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-cross"></i> Bereavement (Immediate):</strong> Covers spouse, children, parents, siblings (<strong>Maximum 5 Days</strong>).';
            } else if (leaveType === 'Bereavement (Extended Family)') {
                noticeBox.style.background = '#f5f3ff';
                noticeBox.style.color = '#5b21b6';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-cross"></i> Bereavement (Extended):</strong> Covers grandparents, in-laws, cousins, aunts/uncles (<strong>Maximum 3 Days</strong>).';
            } else if (leaveType === 'Paternity Leave') {
                noticeBox.style.background = '#f0fdf4';
                noticeBox.style.color = '#166534';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-baby"></i> Republic Act 8187:</strong> <strong>7 Days</strong> with full pay for legitimate married spouses.';
            } else if (leaveType.indexOf('Maternity') !== -1) {
                noticeBox.style.background = '#fdf2f8';
                noticeBox.style.color = '#9d174d';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-baby-carriage"></i> Republic Act 11210:</strong> <strong>105 Days</strong> maternity benefit (company unpaid leave code).';
            } else if (leaveType === 'Service Incentive Leave') {
                noticeBox.style.background = 'var(--amber-bg)';
                noticeBox.style.color = 'var(--amber)';
                noticeBox.innerHTML = '<strong><i class="fa-solid fa-coins"></i> Labor Code Art. 95:</strong> Available credits: <strong>' + userCredits.sil.toFixed(1) + ' day(s)</strong>.';
            } else {
                noticeBox.style.display = 'none';
            }
        } else {
            noticeBox.style.display = 'none';
        }

        if (!leaveType || !startDate) {
            warningBox.style.display = 'none';
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const start = new Date(startDate + 'T00:00:00');
        const diffMs = start - today;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        let showWarning = false;
        let warnMsg = '';
        let violationNote = '';

        if (leaveType === 'Vacation Leave') {
            if (diffDays < 3) {
                showWarning = true;
                const label = diffDays <= 0 ? 'same day or past date' : diffDays + ' day(s) before start date';
                warnMsg = 'Vacation Leave must be filed at least 3 days in advance. Since this is within 3 days, submit an Appeal with reason below.';
                violationNote = 'Filed ' + label + ' (Minimum requirement: 3 days in advance)';
            }
        }

        if (leaveType === 'Sick Leave') {
            if (diffDays <= 0) {
                showWarning = true;
                warnMsg = 'Sick Leave must be filed at least 2 hours before scheduled duty. If filing late, submit an Appeal below.';
                violationNote = 'Filed same day / within 2 hours of duty start';
            }
        }

        if (showWarning) {
            warningBox.style.display = 'block';
            warningText.textContent = warnMsg;

            // Pre-populate appeal hidden fields
            document.getElementById('appealLeaveType').value = leaveType;
            document.getElementById('appealStartDate').value = startDate;
            document.getElementById('appealEndDate').value = endDate;
            document.getElementById('appealViolationNote').value = violationNote;
            appealCard.style.display = 'block';
        } else {
            warningBox.style.display = 'none';
            <?php if (!($show_appeal && $appeal_data)): ?>
            appealCard.style.display = 'none';
            <?php endif; ?>
        }
    }

    // Sync reason field to appeal form
    document.querySelector('#leaveForm textarea[name="reason"]')?.addEventListener('input', function() {
        document.getElementById('appealReason').value = this.value;
    });

    // HR Leave Credits Modal Functions
    function openCreditEditModal(emp) {
        document.getElementById('credit_modal_emp_id').value = emp.emp_id;
        document.getElementById('credit_modal_emp_name').textContent = (emp.first_name || '') + ' ' + (emp.last_name || '') + ' (AC: ' + (emp.ac_no || '-') + ')';
        document.getElementById('cm_sil_credits').value = emp.sil_credits !== null ? emp.sil_credits : '0.0';
        document.getElementById('cm_vl_credits').value = emp.vl_credits !== null ? emp.vl_credits : '0.0';
        document.getElementById('cm_sl_credits').value = emp.sl_credits !== null ? emp.sl_credits : '0.0';
        document.getElementById('cm_paternity_credits').value = emp.paternity_credits !== null ? emp.paternity_credits : '7';
        document.getElementById('cm_maternity_credits').value = emp.maternity_credits !== null ? emp.maternity_credits : '105';
        document.getElementById('cm_bereav_imm').value = emp.bereavement_immediate_credits !== null ? emp.bereavement_immediate_credits : '5';
        document.getElementById('cm_bereav_ext').value = emp.bereavement_extended_credits !== null ? emp.bereavement_extended_credits : '3';
        document.getElementById('creditEditModal').style.display = 'flex';
    }

    function closeCreditEditModal() {
        document.getElementById('creditEditModal').style.display = 'none';
    }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
