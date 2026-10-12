<?php
$pageTitle = 'Leave Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-person-walking-luggage"></i> Leave Requests</h1>
        <p class="page-sub">Submit and manage leave applications</p>
    </div>
    <div class="header-actions">
        <div class="pill-btn" style="background: var(--amber-bg); color: var(--amber); border-color: var(--amber-bdr);">
            Consumed SIL: <?php echo number_format($consumed_totals['Service Incentive Leave'] ?? 0, 1); ?>
        </div>
        <div class="pill-btn" style="background: var(--teal-bg); color: var(--teal); border-color: var(--teal-border);">
            Remaining SIL: <?php echo number_format($my_sil_credits, 1); ?>
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
    <div class="grid-2" style="grid-template-columns: 350px 1fr; align-items: start;">
        <!-- REQUEST FORM -->
        <div class="space-y-20">
            <div class="card" id="leave-form-card">
                <div class="card-head"><div class="card-title">New Application</div></div>
                <div class="card-body">
                    <form method="POST" id="leaveForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="apply_leave">
                        <div class="form-group">
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" required class="input-field select2" id="leaveTypeSelect" onchange="checkLeaveRules()">
                                <option value="">Select Type...</option>
                                <?php foreach($leaveTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" required class="input-field" id="leaveStartDate" onchange="checkLeaveRules()"></div>
                            <div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" required class="input-field" id="leaveEndDate"></div>
                        </div>

                        <!-- Dynamic rule warning -->
                        <div id="leaveRuleWarning" style="display:none; background: var(--amber-bg); border: 1px solid var(--amber-bdr); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                            <div style="display: flex; align-items: start; gap: 8px;">
                                <i class="fa-solid fa-triangle-exclamation" style="color: var(--amber); margin-top: 2px;"></i>
                                <div>
                                    <div style="font-weight: 700; color: var(--amber); font-size: 12px;" id="ruleWarningTitle">Filing Requirement Not Met</div>
                                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 2px;" id="ruleWarningText"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reason / Particulars</label>
                            <textarea name="reason" class="input-field" placeholder="Briefly explain your leave..." style="min-height: 100px;"></textarea>
                        </div>
                        <button type="submit" class="btn-primary" id="leaveSubmitBtn" style="width: 100%; justify-content: center; height: 40px;">Submit Request</button>
                    </form>
                </div>
            </div>

            <!-- APPEAL FORM (shown when rule violation occurs) -->
            <div class="card" id="appeal-form-card" style="display: <?php echo ($show_appeal && $appeal_data) ? 'block' : 'none'; ?>; border: 2px solid var(--amber-bdr);">
                <div class="card-head" style="background: var(--amber-bg);">
                    <div class="card-title" style="color: var(--amber);">
                        <i class="fa-solid fa-gavel"></i> Submit Leave Appeal
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($appeal_data): ?>
                    <div style="background: var(--amber-bg); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 11px;">
                        <strong style="color: var(--amber);">Violation:</strong>
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
                            <textarea name="appeal_reason" class="input-field" required placeholder="Explain why you are filing late (e.g., emergency, sudden illness, etc.)..." style="min-height: 80px;"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Supporting Document <span style="font-weight: 400; color: var(--ink-4);">(Optional — Medical cert, proof, etc.)</span></label>
                            <input type="file" name="appeal_attachment" class="input-field" accept=".png,.jpg,.jpeg,.pdf,.docx" style="padding: 6px;">
                        </div>

                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px; background: var(--amber); border-color: var(--amber-bdr);">
                            <i class="fa-solid fa-gavel"></i> Submit Appeal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- HISTORY -->
        <div class="space-y-20">
            <div class="card">
                <div class="card-head"><div class="card-title">My Leave History</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr><th>Type</th><th>Dates</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($my_leaves as $l): ?>
                                <tr>
                                    <td style="font-weight: 700;">
                                        <?php echo htmlspecialchars($l['leave_type']); ?>
                                        <?php if (!empty($l['is_appeal'])): ?>
                                            <span class="tag tag-amber" style="font-size: 9px; margin-left: 4px;">APPEAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($l['start_date'])); ?> - <?php echo date('M d', strtotime($l['end_date'])); ?></td>
                                    <td>
                                        <span class="tag <?php 
                                            echo $l['status'] === 'Approved' ? 'tag-green' : ($l['status'] === 'Rejected' ? 'tag-red' : 'tag-amber'); 
                                        ?>"><?php echo $l['status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($my_leaves)) echo "<tr><td colspan='3' style='text-align:center; padding:20px;'>No records.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div class="card-title">SIL Consumption (Current Year)</div></div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrap" style="border: none; border-radius: 0;">
                        <table>
                            <thead>
                                <tr><th>Date Range</th><th>Days</th><th>Reason</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($consumed_history as $h): ?>
                                <tr>
                                    <td style="font-size: 11px;"><?php echo date('M d', strtotime($h['start_date'])); ?> - <?php echo date('M d', strtotime($h['end_date'])); ?></td>
                                    <td style="font-weight: 700; font-family: 'DM Mono';"><?php echo $h['days_used']; ?>d</td>
                                    <td style="font-size: 11px; color: var(--ink-4);"><?php echo htmlspecialchars($h['reason']); ?></td>
                                </tr>
                                <?php endforeach; if(empty($consumed_history)) echo "<tr><td colspan='3' style='text-align:center; padding:20px;'>No credits used yet.</td></tr>"; ?>
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
                            <th>Reason</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($hr_leaves as $l): 
                            $s = new DateTime($l['start_date']); $e = new DateTime($l['end_date']);
                            $days = $s->diff($e)->days + 1;
                            $isAppeal = !empty($l['is_appeal']);
                        ?>
                        <tr style="<?php echo $isAppeal ? 'background: var(--amber-bg);' : ''; ?>">
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?></div>
                                <div style="font-size: 10px; color: var(--amber); font-weight: 600;">SIL: <?php echo number_format($l['sil_credits'], 1); ?></div>
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
                                <?php echo htmlspecialchars($l['reason']); ?>
                                <?php if ($isAppeal): ?>
                                    <div style="margin-top: 6px; padding: 6px 8px; background: var(--amber-bg); border: 1px solid var(--amber-bdr); border-radius: 6px;">
                                        <div style="font-size: 9px; font-weight: 700; color: var(--amber); text-transform: uppercase; margin-bottom: 2px;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Late Filing Violation
                                        </div>
                                        <div style="font-size: 10px; color: var(--ink-2);"><?php echo htmlspecialchars($l['violation_note'] ?? ''); ?></div>
                                        <div style="margin-top: 4px; font-size: 10px; color: var(--ink-3);">
                                            <strong>Appeal Reason:</strong> <?php echo htmlspecialchars($l['appeal_reason'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($l['attachment_path'])): ?>
                                        <div style="margin-top: 4px;">
                                            <a href="<?php echo htmlspecialchars($l['attachment_path']); ?>" target="_blank" style="font-size: 10px; color: var(--teal); text-decoration: underline;">
                                                <i class="fa-solid fa-paperclip"></i> View Attachment
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
                                    <button name="status" value="Approved" class="icon-btn ico-green" onclick="return confirm('Approve<?php echo $isAppeal ? ' this appeal' : ''; ?>?')" style="width: 30px; height: 30px;"><i class="fa-solid fa-check"></i></button>
                                    <button name="status" value="Rejected" class="icon-btn ico-red" onclick="return confirm('Reject<?php echo $isAppeal ? ' this appeal' : ''; ?>?')" style="width: 30px; height: 30px;"><i class="fa-solid fa-times"></i></button>
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

<script>
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
     * Client-side leave rule checking.
     * Shows a warning and reveals the appeal form when VL/SL filing rules are violated.
     */
    function checkLeaveRules() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const startDate = document.getElementById('leaveStartDate').value;
        const warningBox = document.getElementById('leaveRuleWarning');
        const warningText = document.getElementById('ruleWarningText');
        const appealCard = document.getElementById('appeal-form-card');

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
                const label = diffDays <= 0 ? 'same day or past date' : diffDays + ' day(s) before leave';
                warnMsg = 'Vacation Leave must be filed at least 3 days in advance. Please submit an appeal below with your justification.';
                violationNote = 'Filed ' + label + ' (Minimum: 3 days)';
            }
        }

        if (leaveType === 'Sick Leave') {
            // Client-side: we can only check date-level for SL (server does the precise 2-hour check)
            if (diffDays <= 0) {
                showWarning = true;
                warnMsg = 'Sick Leave must be filed at least 2 hours before duty start time. If you cannot meet this requirement, submit an appeal below.';
                violationNote = 'Filed on same day or past date — 2-hour rule may be violated';
            }
        }

        if (showWarning) {
            warningBox.style.display = 'block';
            warningText.textContent = warnMsg;

            // Pre-populate appeal hidden fields
            document.getElementById('appealLeaveType').value = leaveType;
            document.getElementById('appealStartDate').value = startDate;
            document.getElementById('appealEndDate').value = document.getElementById('leaveEndDate').value || startDate;
            document.getElementById('appealViolationNote').value = violationNote;
            appealCard.style.display = 'block';
        } else {
            warningBox.style.display = 'none';
            // Only hide appeal card if it wasn't shown by server redirect
            <?php if (!($show_appeal && $appeal_data)): ?>
            appealCard.style.display = 'none';
            <?php endif; ?>
        }
    }

    // Sync reason field to appeal form
    document.querySelector('#leaveForm textarea[name="reason"]')?.addEventListener('input', function() {
        document.getElementById('appealReason').value = this.value;
    });

    // Sync end date to appeal form
    document.getElementById('leaveEndDate')?.addEventListener('change', function() {
        document.getElementById('appealEndDate').value = this.value;
        checkLeaveRules();
    });
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
