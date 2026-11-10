<?php
$pageTitle = 'Overtime Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<style>
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--multiple {
        border-color: var(--border);
        border-radius: var(--r-sm);
        min-height: 42px;
        background: var(--bg-card);
        padding: 3px 6px;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--teal);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--teal-bg);
        border-color: var(--teal-border);
        color: var(--teal-deep);
        font-size: 12px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        margin-top: 5px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--teal-deep);
        margin-right: 5px;
    }
    .select2-dropdown {
        border-color: var(--border);
        background: var(--bg-card);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 9999;
    }
    .select2-results__option {
        padding: 8px 12px;
        font-size: 13px;
        color: var(--ink-1);
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--teal);
        color: white;
    }
    #ot_employees {
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        min-height: 42px;
        background: var(--bg-card);
        color: var(--ink-1);
        padding: 6px 10px;
        font-size: 13px;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-business-time"></i> Overtime Request</h1>
        <p class="page-sub"><?php echo ($is_manager || $is_hr) ? 'Submit and manage company/departmental overtime applications' : 'File and track your overtime applications'; ?></p>
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

<div class="grid-2" style="grid-template-columns: 380px 1fr; gap: 25px; align-items: start;">
    <!-- REQUEST FORM -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">New OT Application</div>
            <span class="tag tag-teal"><?php echo count($deptEmployees); ?> Available</span>
        </div>
        <div class="card-body">
            <form method="POST" id="form-ot">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="apply_ot">
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Select Employee(s) <span style="color: var(--red);">*</span></span>
                        <?php if (!$is_manager && !$is_hr): ?>
                            <span style="font-size: 11px; font-weight: normal; color: var(--teal-deep);">(You are pre-selected)</span>
                        <?php endif; ?>
                    </label>
                    <select name="ot_employees[]" id="ot_employees" class="select2" multiple required style="width: 100%;">
                        <?php 
                            $loggedId = $currentUserId ?? ($_SESSION['user_id'] ?? 0);
                            $userDeptId = $userDept ?? ($_SESSION['dept_id'] ?? 0);

                            // Partition into user's department vs other departments
                            $myDeptEmployees = [];
                            $otherDeptEmployees = [];

                            foreach ($deptEmployees as $emp) {
                                if (!empty($userDeptId) && $emp['dept_id'] == $userDeptId) {
                                    $myDeptEmployees[] = $emp;
                                } else {
                                    $dName = !empty($emp['dept_name']) ? $emp['dept_name'] : 'Other Departments';
                                    $otherDeptEmployees[$dName][] = $emp;
                                }
                            }
                        ?>

                        <?php if (!empty($myDeptEmployees)): ?>
                            <?php 
                                $myDeptName = $myDeptEmployees[0]['dept_name'] ?? 'My Department';
                                $groupLabel = ($is_manager || $is_hr) ? "My Department ({$myDeptName})" : "Department ({$myDeptName})";
                            ?>
                            <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                <?php foreach ($myDeptEmployees as $emp): 
                                    $isSelf = ($emp['emp_id'] == $loggedId);
                                    $selected = ($isSelf && !$is_manager && !$is_hr) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $emp['emp_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' (#' . $emp['ac_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>

                        <?php foreach ($otherDeptEmployees as $deptName => $emps): ?>
                            <optgroup label="<?php echo htmlspecialchars($deptName); ?>">
                                <?php foreach ($emps as $emp): 
                                    $isSelf = ($emp['emp_id'] == $loggedId);
                                    $selected = ($isSelf && !$is_manager && !$is_hr) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $emp['emp_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' (#' . $emp['ac_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <small style="font-size: 11px; color: var(--ink-4); display: block; margin-top: 4px;">
                        <?php if ($is_manager || $is_hr): ?>
                            Search and select one or more employees to file overtime for.
                        <?php else: ?>
                            Your name is selected. You may add team members if filing jointly.
                        <?php endif; ?>
                    </small>
                </div>

                <div class="grid-2" style="gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Work Date <span style="color: var(--red);">*</span></label>
                        <input type="date" name="ot_date" required class="input-field" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Est. Hours <span style="color: var(--red);">*</span></label>
                        <input type="number" name="ot_hours" step="0.5" min="0.5" max="16" required class="input-field" placeholder="0.0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Task Details <span style="color: var(--red);">*</span></label>
                    <textarea name="reason" rows="4" required placeholder="Describe the required tasks or project justification..." class="input-field" style="min-height: 100px;"></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 42px;">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Submit OT Request
                </button>
            </form>
        </div>
    </div>

    <!-- HISTORY -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><?php echo ($is_hr || ($userRole ?? '') === 'CEO') ? 'Recent Overtime Records' : ($is_manager ? 'Department OT History' : 'My Overtime History'); ?></div>
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
                                    if (!empty($ot['dept_name'])) {
                                        echo ' <span style="font-size: 10px; color: var(--ink-4); font-weight: normal;">(' . htmlspecialchars($ot['dept_name']) . ')</span>';
                                    }
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

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>

<script>
    $(document).ready(function() {
        if ($('#ot_employees').length && typeof $.fn.select2 !== 'undefined') {
            if ($('#ot_employees').hasClass('select2-hidden-accessible')) {
                $('#ot_employees').select2('destroy');
            }
            $('#ot_employees').select2({ 
                placeholder: "Search or select employee(s)...", 
                width: '100%',
                closeOnSelect: false,
                allowClear: true
            });
        }
    });
</script>
