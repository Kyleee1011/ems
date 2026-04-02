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
    <div class="card mb-20" style="background: <?php echo $messageType == 'success' ? 'var(--green-bg)' : 'var(--red-bg)'; ?>;">
        <div class="card-body" style="color: <?php echo $messageType == 'success' ? 'var(--green)' : 'var(--red)'; ?>; font-weight: 600;">
            <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div id="panel-leave" class="panel active">
    <div class="grid-2" style="grid-template-columns: 350px 1fr; align-items: start;">
        <!-- REQUEST FORM -->
        <div class="card">
            <div class="card-head"><div class="card-title">New Application</div></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="apply_leave">
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type" required class="input-field select2">
                            <option value="">Select Type...</option>
                            <?php foreach($leaveTypes as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" required class="input-field"></div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" required class="input-field"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason / Particulars</label>
                        <textarea name="reason" class="input-field" placeholder="Briefly explain your leave..." style="min-height: 100px;"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px;">Submit Request</button>
                </form>
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
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($l['leave_type']); ?></td>
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
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?></div>
                                <div style="font-size: 10px; color: var(--amber); font-weight: 600;">SIL: <?php echo number_format($l['sil_credits'], 1); ?></div>
                            </td>
                            <td><span class="tag tag-teal"><?php echo htmlspecialchars($l['leave_type']); ?></span></td>
                            <td style="font-size: 11px;">
                                <?php echo $s->format('M d') . ' - ' . $e->format('M d'); ?>
                                <span style="color: var(--ink-4);">(<?php echo $days; ?>d)</span>
                            </td>
                            <td style="font-size: 11px; color: var(--ink-3); max-width: 200px;"><?php echo htmlspecialchars($l['reason']); ?></td>
                            <td style="text-align: right;">
                                <form method="POST" style="display: inline-flex; gap: 5px;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_leave_status"><input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>">
                                    <button name="status" value="Approved" class="icon-btn ico-green" onclick="return confirm('Approve?')" style="width: 30px; height: 30px;"><i class="fa-solid fa-check"></i></button>
                                    <button name="status" value="Rejected" class="icon-btn ico-red" onclick="return confirm('Reject?')" style="width: 30px; height: 30px;"><i class="fa-solid fa-times"></i></button>
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
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
