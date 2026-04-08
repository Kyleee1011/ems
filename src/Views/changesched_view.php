<?php
$pageTitle = 'Change Schedule — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-calendar-day"></i> Change Schedule</h1>
        <p class="page-sub">Request and manage your shift changes</p>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="flex-row mb-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <button onclick="switchTab('changesched')" id="tab-changesched" class="nav-item active" style="padding: 10px 5px; border-bottom: 2px solid var(--teal); background: none;">Personal Service</button>
    <?php if ($is_hr): ?>
    <button onclick="switchTab('hr-changesched')" id="tab-hr-changesched" class="nav-item" style="padding: 10px 5px; border-bottom: 2px solid transparent; background: none;">
        Pending Approvals
        <?php if(count($hr_changes) > 0): ?><span class="tag tag-red" style="margin-left:5px;"><?php echo count($hr_changes); ?></span><?php endif; ?>
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

<?php if(isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
    <div class="card mb-20" style="background: var(--red-bg);">
        <div class="card-body" style="color: var(--red); font-weight: 600;">
            <i class="fa-solid fa-exclamation-circle"></i>
            Invalid security token. The form has expired, please try again.
        </div>
    </div>
<?php endif; ?>

<div id="panel-changesched" class="panel active">
    <div class="grid-2" style="grid-template-columns: 350px 1fr; align-items: start;">
        <!-- REQUEST FORM -->
        <div class="card">
            <div class="card-head"><div class="card-title">Request Change</div></div>
            <div class="card-body">
                <form method="POST" id="form-changesched">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="apply_change_sched">
                    
                    <div class="form-group">
                        <label class="form-label">Date of Shift</label>
                        <input type="date" name="sched_date" required class="input-field">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Shift / Code</label>
                        <select name="new_shift_code" required class="input-field select2">
                            <option value="">Select New Shift...</option>
                            <optgroup label="Regular Shifts">
                                <?php foreach($shifts as $s): ?>
                                    <option value="<?php echo $s['shift_code']; ?>"><?php echo htmlspecialchars($s['shift_name']); ?> (<?php echo substr($s['time_in'], 0, 5) . '-' . substr($s['time_out'], 0, 5); ?>)</option>
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
                    
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="input-field" required placeholder="Why do you need to change this shift?" style="min-height: 100px;"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px;">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- HISTORY -->
        <div class="card">
            <div class="card-head"><div class="card-title">My Change History</div></div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrap" style="border: none; border-radius: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Requested On</th>
                                <th>Target Date</th>
                                <th>New Shift</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($my_sched_changes as $sc): ?>
                            <tr>
                                <td style="font-size: 11px;"><?php echo date('M d, Y', strtotime($sc['created_at'])); ?></td>
                                <td style="font-weight: 700; font-family: 'DM Mono';"><?php echo date('M d, Y', strtotime($sc['schedule_date'])); ?></td>
                                <td><span class="tag tag-teal"><?php echo htmlspecialchars($sc['new_shift_code']); ?></span></td>
                                <td style="font-size: 11px; color: var(--ink-4);"><?php echo htmlspecialchars($sc['reason']); ?></td>
                                <td>
                                    <span class="tag <?php 
                                        echo $sc['status'] === 'Approved' ? 'tag-green' : ($sc['status'] === 'Rejected' ? 'tag-red' : 'tag-amber'); 
                                    ?>"><?php echo $sc['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; if(empty($my_sched_changes)) echo "<tr><td colspan='5' style='text-align:center; padding:40px; color:var(--ink-4);'>No records found.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($is_hr): ?>
<div id="panel-hr-changesched" class="panel" style="display:none;">
    <div class="card">
        <div class="card-head"><div class="card-title">Pending Applications Queue</div></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Dept</th>
                            <th>Target Date</th>
                            <th>New Shift</th>
                            <th>Reason</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($hr_changes as $rc): ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($rc['first_name'] . ' ' . $rc['last_name']); ?></td>
                            <td style="font-size: 11px;"><?php echo htmlspecialchars($rc['dept_name']); ?></td>
                            <td style="font-weight: 700; font-family: 'DM Mono';"><?php echo date('M d, Y', strtotime($rc['schedule_date'])); ?></td>
                            <td><span class="tag tag-teal"><?php echo htmlspecialchars($rc['new_shift_code']); ?></span></td>
                            <td style="font-size: 11px; color: var(--ink-3); max-width: 200px;"><?php echo htmlspecialchars($rc['reason']); ?></td>
                            <td style="text-align: right;">
                                <form method="POST" style="display: inline-flex; gap: 5px;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_change_status">
                                    <input type="hidden" name="req_id" value="<?php echo $rc['id']; ?>">
                                    <button name="status" value="Approved" class="icon-btn ico-green" onclick="return confirm('Approve?')" style="width: 30px; height: 30px;" title="Approve"><i class="fa-solid fa-check"></i></button>
                                    <button name="status" value="Rejected" class="icon-btn ico-red" onclick="return confirm('Reject?')" style="width: 30px; height: 30px;" title="Reject"><i class="fa-solid fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($hr_changes)) echo "<tr><td colspan='6' style='text-align:center; padding:40px; color:var(--ink-4);'>No pending applications.</td></tr>"; ?>
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
