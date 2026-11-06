<?php
$pageTitle = 'Overtime Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--multiple {
        border-color: var(--border);
        border-radius: var(--r-sm);
        min-height: 38px;
        background: var(--bg-card);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--teal-bg);
        border-color: var(--teal-border);
        color: var(--teal-deep);
        font-size: 11px;
        font-weight: 600;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-business-time"></i> Overtime Request</h1>
        <p class="page-sub"><?php echo $is_manager ? 'Submit and manage departmental overtime applications' : 'File and track your overtime requests'; ?></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="card mb-20" style="background: <?php echo $messageType == 'success' ? 'var(--green-bg)' : ($messageType == 'warning' ? '#fef3c7' : 'var(--red-bg)'); ?>;">
        <div class="card-body" style="color: <?php echo $messageType == 'success' ? 'var(--green)' : ($messageType == 'warning' ? '#92400e' : 'var(--red)'); ?>; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : ($messageType == 'warning' ? 'fa-triangle-exclamation' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid-2" style="grid-template-columns: 360px 1fr; gap: 25px; align-items: start;">
    <!-- REQUEST FORM (Accessible to both Managers and Employees) -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><?php echo $is_manager ? 'New OT Application (Batch/Self)' : 'Apply for Overtime'; ?></div>
        </div>
        <div class="card-body">
            <form method="POST" id="form-ot">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="apply_ot">
                
                <?php if ($is_manager): ?>
                    <div class="form-group">
                        <label class="form-label">Select Employee(s)</label>
                        <select name="ot_employees[]" id="ot_employees" multiple required style="width: 100%;">
                            <?php foreach ($deptEmployees as $emp): ?>
                                <option value="<?php echo $emp['emp_id']; ?>" <?php echo ($emp['emp_id'] == ($_SESSION['user_id'] ?? 0)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11px; color: var(--ink-4); display: block; margin-top: 4px;">Leave blank or keep yourself selected to apply for yourself.</small>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Applicant</label>
                        <input type="text" class="input-field" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Me'); ?>" readonly style="background: var(--bg-raised); cursor: not-allowed;">
                    </div>
                <?php endif; ?>

                <div class="grid-2" style="gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Work Date</label>
                        <input type="date" name="ot_date" required class="input-field" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Est. Hours</label>
                        <input type="number" name="ot_hours" step="0.5" min="0.5" max="16" required class="input-field" placeholder="0.0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Task Details</label>
                    <textarea name="reason" rows="4" required placeholder="Describe the specific tasks or project justification..." class="input-field" style="min-height: 100px;"></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px;">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Submit OT Request
                </button>
            </form>
        </div>
    </div>

    <!-- HISTORY -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><?php echo $is_manager ? 'Department OT History' : 'My Overtime History'; ?></div>
            <span class="tag tag-teal"><?php echo count($overtimes); ?> Records</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border:none;">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th style="text-align: center;">Hours</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($overtimes) > 0): foreach($overtimes as $ot): ?>
                        <tr>
                            <td style="font-weight: 700;">
                                <?php 
                                    $empName = trim(($ot['first_name'] ?? '') . ' ' . ($ot['last_name'] ?? ''));
                                    echo htmlspecialchars($empName ?: ($_SESSION['full_name'] ?? 'Employee')); 
                                ?>
                            </td>
                            <td style="font-size: 11px; color: var(--ink-4); white-space: nowrap;"><?php echo date('M d, Y', strtotime($ot['ot_date'])); ?></td>
                            <td style="text-align: center; font-family: 'DM Mono'; font-weight: 700; color: var(--teal-deep);"><?php echo number_format((float)$ot['ot_hours'], 1); ?>h</td>
                            <td style="font-size: 11px; color: var(--ink-3); max-width: 220px;" title="<?php echo htmlspecialchars($ot['reason'] ?? ''); ?>">
                                <?php 
                                    $reasonText = $ot['reason'] ?? '';
                                    echo htmlspecialchars(mb_strimwidth($reasonText, 0, 45, '...'));
                                ?>
                            </td>
                            <td>
                                <span class="tag <?php 
                                    echo $ot['status'] === 'Approved' ? 'tag-green' : ($ot['status'] === 'Rejected' ? 'tag-red' : 'tag-amber'); 
                                ?>"><?php echo htmlspecialchars($ot['status']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--ink-4);">No OT records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($is_hr): ?>
<!-- HR QUEUE -->
<div class="card mt-20">
    <div class="card-head">
        <div class="card-title">Pending Overtime Queue</div>
        <span class="tag tag-red"><?php echo count($hr_ots); ?> Awaiting Action</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrap" style="border:none;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th style="text-align: center;">Hours</th>
                        <th>Reason</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($hr_ots) > 0): foreach($hr_ots as $ot): ?>
                    <tr>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($ot['first_name'] . ' ' . $ot['last_name']); ?></td>
                        <td style="font-size: 10px; color: var(--ink-4);"><?php echo htmlspecialchars($ot['dept_name'] ?? '—'); ?></td>
                        <td style="font-size: 11px; white-space: nowrap;"><?php echo date('M d, Y', strtotime($ot['ot_date'])); ?></td>
                        <td style="text-align: center; font-family: 'DM Mono'; font-weight: 700; color: var(--teal-deep);"><?php echo number_format((float)$ot['ot_hours'], 1); ?>h</td>
                        <td style="font-size: 11px; color: var(--ink-3); max-width: 250px;"><?php echo htmlspecialchars($ot['reason']); ?></td>
                        <td style="text-align: right;">
                            <form method="POST" style="display: inline-flex; gap: 5px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_ot_status">
                                <input type="hidden" name="ot_id" value="<?php echo $ot['ot_id']; ?>">
                                <button name="status" value="Approved" onclick="return confirm('Approve overtime request?')" class="icon-btn ico-green" title="Approve"><i class="fa-solid fa-check"></i></button>
                                <button name="status" value="Rejected" onclick="return confirm('Reject overtime request?')" class="icon-btn ico-red" title="Reject"><i class="fa-solid fa-times"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:50px; color:var(--ink-4);">Queue is empty.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#ot_employees').length) {
            $('#ot_employees').select2({ placeholder: "Select employees...", width: '100%' });
        }
    });
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
