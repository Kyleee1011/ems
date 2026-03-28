<?php
$pageTitle = 'Allowance Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-gift"></i> Compensation & Allowances</h1>
        <p class="page-sub">Manage recurring and fixed employee benefits</p>
    </div>
    <div class="header-actions">
        <button onclick="$('#createModal').show(); $('#modalBackdrop').show();" class="btn-primary"><i class="fa-solid fa-plus"></i> New Allowance</button>
    </div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title">Active Allowance Types</div></div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrap" style="border:none;">
            <table>
                <thead>
                    <tr>
                        <th>Benefit Name</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Frequency</th>
                        <th style="text-align: center;">Absent Ded.</th>
                        <th style="text-align: center;">Headcount</th>
                        <th style="text-align: center;">Start Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allowanceTypes)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:50px; color:var(--ink-4);">No benefits configured.</td></tr>
                    <?php else: foreach ($allowanceTypes as $at): ?>
                    <tr>
                        <td style="font-weight:700;"><?php echo htmlspecialchars($at['name']); ?></td>
                        <td style="text-align: right; font-family:'DM Mono'; font-weight:700; color:var(--teal-deep);">₱<?php echo number_format((float)$at['amount'], 2); ?></td>
                        <td style="text-align: center;"><span class="tag tag-teal"><?php echo $at['frequency']; ?></span></td>
                        <td style="text-align: center; font-family:'DM Mono'; color:var(--red);">₱<?php echo number_format((float)$at['deduction_per_absent'], 2); ?></td>
                        <td style="text-align: center;">
                            <button onclick="showAssigned(<?php echo $at['allowance_id']; ?>, '<?php echo addslashes($at['name']); ?>')" class="pill-btn" style="padding:2px 8px; font-size:10px;">
                                <?php echo (int)$at['emp_count']; ?> <i class="fa-solid fa-users" style="margin-left:3px;"></i>
                            </button>
                        </td>
                        <td style="text-align: center; font-size:11px; color:var(--ink-4);"><?php echo $at['start_cutoff_date'] ? date('M d, Y', strtotime($at['start_cutoff_date'])) : '-'; ?></td>
                        <td style="text-align: right;">
                            <div class="flex-row" style="justify-content: flex-end; gap:4px;">
                                <button onclick="openAssignModal(<?php echo $at['allowance_id']; ?>, '<?php echo addslashes($at['name']); ?>')" class="icon-btn" title="Assign"><i class="fa-solid fa-user-plus"></i></button>
                                <button onclick="deleteAllowance(<?php echo $at['allowance_id']; ?>, '<?php echo addslashes($at['name']); ?>')" class="icon-btn" style="color:var(--red);"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODALS -->
<div id="modalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:100; display:none;" onclick="closeAllModals()"></div>

<!-- CREATE MODAL -->
<div id="createModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:400px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; justify-content:space-between; background:var(--bg-raised); border-radius:16px 16px 0 0;">
        <h3 class="card-title">New Allowance Configuration</h3>
        <button onclick="closeAllModals()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <form id="createForm">
        <div class="modal-body" style="padding:20px;">
            <div class="form-group"><label class="form-label">Allowance Name</label><input type="text" name="name" required class="input-field" placeholder="e.g. Rice Subsidy"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Amount (₱)</label><input type="number" name="amount" required step="0.01" class="input-field" placeholder="0.00"></div>
                <div class="form-group">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="input-field"><option value="Semi-Monthly">Semi-Monthly</option><option value="Monthly">Monthly</option><option value="Daily">Daily</option></select>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Absent Deduction</label><input type="number" name="deduction_per_absent" step="0.01" value="0" class="input-field"></div>
                <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_cutoff_date" class="input-field"></div>
            </div>
        </div>
        <div class="modal-footer" style="padding:15px 20px; border-top:1px solid var(--border-lt); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-raised); border-radius:0 0 16px 16px;">
            <button type="button" onclick="closeAllModals()" class="pill-btn">Cancel</button>
            <button type="submit" class="btn-primary">Save Type</button>
        </div>
    </form>
</div>

<!-- ASSIGN MODAL -->
<div id="assignModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:500px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column; max-height:90vh;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; justify-content:space-between; background:var(--bg-raised); border-radius:16px 16px 0 0;">
        <h3 class="card-title">Assign: <span id="assignName"></span></h3>
        <button onclick="closeAllModals()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:20px; overflow-y:auto;">
        <input type="hidden" id="assignAllowanceId">
        <label class="flex-row mb-20" style="background:var(--bg-subtle); padding:10px; border-radius:8px; cursor:pointer;">
            <input type="checkbox" id="assignAll" onchange="toggleSelectAll()"> <span style="font-weight:700; font-size:12px;">Assign to ALL active employees</span>
        </label>
        <div id="empListContainer">
            <label class="form-label">Search / Select Employees</label>
            <div style="border:1px solid var(--border); border-radius:8px; max-height:200px; overflow-y:auto; padding:10px;">
                <?php foreach ($employees as $emp): ?>
                <label class="flex-row" style="padding:5px; border-radius:4px; cursor:pointer; font-size:12px;">
                    <input type="checkbox" value="<?php echo $emp['emp_id']; ?>" class="emp-cb"> <span><?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group mt-20"><label class="form-label">Effective Start Date</label><input type="date" id="assignStartDate" class="input-field"></div>
    </div>
    <div class="modal-footer" style="padding:15px 20px; border-top:1px solid var(--border-lt); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-raised); border-radius:0 0 16px 16px;">
        <button type="button" onclick="closeAllModals()" class="pill-btn">Cancel</button>
        <button type="button" onclick="submitAssign()" class="btn-primary">Confirm Assignment</button>
    </div>
</div>

<!-- VIEW ASSIGNED MODAL -->
<div id="assignedModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:400px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column; max-height:80vh;">
    <div class="modal-header">
        <h3 class="card-title">Enrolled Employees</h3>
        <button onclick="closeAllModals()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="modal-body" id="assignedList" style="padding:10px; overflow-y:auto;"></div>
    <div class="modal-footer"><button onclick="closeAllModals()" class="pill-btn">Done</button></div>
</div>

<script>
const allData = <?php echo json_encode($allowanceTypes); ?>;

function closeAllModals() { $('.modal-container').hide(); $('#modalBackdrop').hide(); }

$('#createForm').on('submit', function(e) {
    e.preventDefault();
    $.post('', $(this).serialize() + '&action=create', res => { if(res.success) location.reload(); else alert(res.message); }, 'json');
});

function openAssignModal(id, name) { $('#assignAllowanceId').val(id); $('#assignName').text(name); $('#assignModal').show(); $('#modalBackdrop').show(); }

function toggleSelectAll() {
    const all = $('#assignAll').is(':checked');
    $('#empListContainer').css('opacity', all ? '0.3' : '1').css('pointer-events', all ? 'none' : 'auto');
}

function submitAssign() {
    const id = $('#assignAllowanceId').val(); const all = $('#assignAll').is(':checked'); const start = $('#assignStartDate').val();
    let ids = []; if(!all) $('.emp-cb:checked').each(function(){ ids.push($(this).val()); });
    $.post('', { action:'assign', allowance_id:id, assign_all:all?'1':'0', emp_ids:JSON.stringify(ids), start_cutoff_date:start }, res => { if(res.success) location.reload(); else alert(res.message); }, 'json');
}

function showAssigned(id, name) {
    const at = allData.find(a => a.allowance_id == id);
    const list = at && at.assigned ? at.assigned : [];
    let html = '';
    if(list.length === 0) html = '<p style="text-align:center; padding:20px; color:var(--ink-4);">No one enrolled.</p>';
    else list.forEach(e => {
        html += `<div class="flex-between" style="padding:8px; border-bottom:1px solid var(--border-lt);">
            <span style="font-size:12px; font-weight:600;">${e.last_name}, ${e.first_name}</span>
            <button onclick="removeAssignment(${e.emp_id}, ${id})" style="color:var(--red); border:none; background:none; font-size:10px; cursor:pointer;"><i class="fa-solid fa-trash-can"></i></button>
        </div>`;
    });
    $('#assignedList').html(html); $('#assignedModal').show(); $('#modalBackdrop').show();
}

function deleteAllowance(id, name) { if(confirm(`Delete benefit: ${name}?`)) $.post('', {action:'delete', allowance_id:id}, res => location.reload(), 'json'); }
function removeAssignment(eid, aid) { if(confirm('Remove employee from this benefit?')) $.post('', {action:'remove_assignment', emp_id:eid, allowance_id:aid}, res => location.reload(), 'json'); }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
