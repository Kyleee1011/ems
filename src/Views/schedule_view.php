<?php
$pageTitle = 'Schedule Manager — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

$is_dept_head = (strcasecmp($approval_role, 'Dept Head') === 0);
$can_set_colors = ($is_dept_head || $is_hr || $is_ceo);
?>

<style>
    .doc-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    .doc-table th, .doc-table td { border: 1px solid var(--border); padding: 4px; text-align: center; }
    .doc-table th { background: var(--bg-raised); font-weight: 700; color: var(--ink-3); }
    .cell-edit { cursor: pointer; transition: all 0.1s; }
    .cell-edit:hover { background-color: var(--teal-bg); color: var(--teal-deep); font-weight: 700; }
    
    /* SHIFT COLORS */
    .shift-morning { background-color: hsla(154, 52%, 34%, 0.1) !important; }
    .shift-mid { background-color: hsla(34, 90%, 44%, 0.1) !important; }
    .shift-night { background-color: hsla(216, 72%, 48%, 0.1) !important; }
    .shift-reliever { background-color: hsla(258, 58%, 54%, 0.1) !important; }

    .shift-dropdown { display: none; position: absolute; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--sh-md); z-index: 100; min-width: 140px; padding: 5px; }
    .shift-dropdown.active { display: block; }
    .shift-opt { padding: 8px 12px; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; border-radius: 4px; }
    .shift-opt:hover { background: var(--bg-hover); }
    .dot { width: 8px; height: 8px; border-radius: 50%; }

    @media print {
        .no-print { display: none !important; }
        body { background: #fff; padding: 0; }
        .main { padding: 0; animation: none; }
        .sidebar, .topbar { display: none !important; }
        .app { display: block; }
        .document-page { box-shadow: none !important; border: none !important; width: 100% !important; padding: 0 !important; }
        .doc-table { border: 1px solid #000; }
        .doc-table th, .doc-table td { border: 1px solid #000; color: #000 !important; }
    }
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-calendar-days"></i> Workforce Scheduling</h1>
        <p class="page-sub">Manage shifts and departmental rotations</p>
    </div>
    <div class="header-actions">
        <span id="status_display" class="tag tag-teal" style="padding: 5px 15px; display:none;">Draft</span>
        <button onclick="window.print()" class="pill-btn"><i class="fa-solid fa-print"></i> Print Schedule</button>
    </div>
</div>

<?php if ($is_admin_view): ?>
<!-- TABS NAVIGATION -->
<div class="flex-row mb-20 no-print" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <button onclick="switchTab('dashboard')" id="btn-tab-dashboard" class="nav-item active" style="padding: 10px 5px; border-bottom: 2px solid var(--teal); background: none;">Approvals Queue</button>
    <button onclick="switchTab('schedule')" id="btn-tab-schedule" class="nav-item" style="padding: 10px 5px; border-bottom: 2px solid transparent; background: none;">Schedule Matrix</button>
</div>

<div id="tab_dashboard" class="tab-content no-print">
    <div class="card">
        <div class="card-head"><div class="card-title">Pending Submissions</div></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border:none;">
                <table>
                    <thead>
                        <tr><th>Department</th><th>Period</th><th>Updated</th><th style="text-align:right;">Action</th></tr>
                    </thead>
                    <tbody id="approval_list">
                        <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--ink-4);">Checking for updates...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="tab_schedule" class="tab-content" <?php echo $is_admin_view ? 'style="display:none;"' : ''; ?>>
    <!-- CONTROLS -->
    <div class="card no-print mb-20">
        <div class="card-body flex-row" style="flex-wrap: wrap;">
            <?php if($is_hr || $is_ceo): ?>
                <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                    <label class="form-label">Department</label>
                    <select id="dept_select" class="input-field" onchange="loadData()">
                        <option value="">-- Select --</option>
                        <?php foreach($depts as $d): ?><option value="<?php echo $d['dept_id']; ?>" <?php echo $d['dept_id'] == $user_dept ? 'selected' : ''; ?>><?php echo $d['dept_name']; ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" id="dept_select" value="<?php echo $user_dept; ?>">
            <?php endif; ?>
            
            <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                <label class="form-label">Cutoff Period</label>
                <select id="cutoff_select" class="input-field" onchange="loadData()">
                    <?php foreach($cutoff_options as $opt): ?>
                        <option value="<?php echo $opt['value']; ?>" <?php echo $opt['value'] === $default_cutoff ? 'selected' : ''; ?>><?php echo $opt['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="workflow_actions" class="flex-row" style="margin-top: 20px; flex: 100%; justify-content: center; display:none;">
                <button id="btn_submit" class="btn-primary" onclick="handleWorkflowAction('Pending HR')" style="display:none;">Sign & Submit</button>
                <button id="btn_approve_hr" class="btn-primary" style="background:var(--blue); display:none;" onclick="handleWorkflowAction('Pending CEO')">Approve (HR)</button>
                <button id="btn_approve_ceo" class="btn-primary" onclick="handleWorkflowAction('Approved')" style="display:none;">Final Release</button>
                <button id="btn_reject" class="pill-btn" style="color:var(--red); border-color:var(--red-bdr); display:none;" onclick="updateStatus('Rejected')">Reject</button>
                <button id="btn_reset" class="pill-btn" style="display:none;" onclick="updateStatus('Draft')">Unlock Matrix</button>
            </div>
        </div>
    </div>

    <!-- DOCUMENT MATRIX -->
    <div class="card">
        <div class="card-body document-page" style="padding: 40px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="<?php echo baseUrl('azzurro1.png'); ?>" style="height: 50px; grayscale: 1;" alt="Logo">
                <h2 id="doc_dept_name" style="font-weight: 800; font-size: 16px; margin-top: 10px; text-transform: uppercase;">DEPARTMENT</h2>
                <p id="doc_cutoff_label" style="font-size: 11px; color: var(--ink-3); font-weight: 600;">Cut-off Period: ...</p>
            </div>

            <div class="table-wrap" style="border: 1px solid var(--border);">
                <table class="doc-table">
                    <thead id="doc_table_head"></thead>
                    <tbody id="doc_table_body">
                        <tr><td colspan="32" style="padding: 50px; color: var(--ink-4);">Loading scheduling data...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="grid-3 mt-20" style="margin-top: 40px; text-align: center;">
                <div>
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px;">Prepared By:</p>
                    <div id="sig_img_prepared" style="height: 40px; margin-bottom: 5px;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0 20px;"></div>
                    <p id="name_prepared" style="font-size: 10px; font-weight: 800; margin-top: 5px;">-</p>
                </div>
                <div>
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px;">Checked By (HR):</p>
                    <div id="sig_img_checked" style="height: 40px; margin-bottom: 5px;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0 20px;"></div>
                    <p style="font-size: 10px; font-weight: 800; margin-top: 5px;"><?php echo htmlspecialchars($pageHrName); ?></p>
                </div>
                <div>
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px;">Approved By (CEO):</p>
                    <div id="sig_img_approved" style="height: 40px; margin-bottom: 5px;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0 20px;"></div>
                    <p style="font-size: 10px; font-weight: 800; margin-top: 5px;"><?php echo htmlspecialchars($pageCeoName); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SHIFT MODAL -->
<div id="modalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:100; display:none;" onclick="closeShiftModal()"></div>
<div id="shiftModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:600px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column; max-height:80vh;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; justify-content:space-between; background:var(--bg-raised); border-radius:16px 16px 0 0;">
        <h3 class="card-title">Assignment Selector</h3>
        <button onclick="closeShiftModal()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:20px; overflow-y:auto;">
        <?php foreach ($grouped_shifts as $groupName => $shifts): ?>
            <div class="section-hd"><span class="section-hd-label"><?php echo $groupName; ?></span><div class="section-hd-line"></div></div>
            <div class="grid-3 mb-20" style="grid-template-columns: repeat(4, 1fr);">
                <?php foreach ($shifts as $s): ?>
                    <div onclick="saveShift('<?php echo $s['shift_code']; ?>')" class="pill-btn" style="justify-content:center; text-align:center; padding:10px 5px; cursor:pointer;">
                        <div style="font-weight:800; font-size:11px;"><?php echo $s['shift_code']; ?></div>
                        <?php if($s['time_in']): ?><div style="font-size:8px; color:var(--ink-4);"><?php echo $s['time_in']->format('H:i');?>-<?php echo $s['time_out']->format('H:i');?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
let currentEditing = { empId: null, date: null };
let canEdit = false;
let currentUserSigPath = "<?php echo $currentUserSigPath; ?>"; 
let isAdminView = <?php echo $is_admin_view ? 'true' : 'false'; ?>;

function switchTab(tab) {
    $('.tab-content').hide(); $('.nav-item').removeClass('active').css('border-bottom-color', 'transparent');
    $('#tab_' + tab).show(); $('#btn-tab-' + tab).addClass('active').css('border-bottom-color', 'var(--teal)');
    if(tab === 'dashboard') loadDashboard();
}

function loadDashboard() {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_pending_approvals' }, function(res) {
        let html = '';
        if (res.approvals.length === 0) html = '<tr><td colspan="4" style="text-align:center; padding:40px;">No pending schedules.</td></tr>';
        else res.approvals.forEach(r => {
            html += `<tr><td><b>${r.dept_name}</b></td><td>${r.cutoff_label}</td><td style="font-size:10px;">${r.last_updated}</td><td style="text-align:right;"><button class="btn-primary" style="font-size:10px; height:26px; padding:0 10px;" onclick="reviewBatch(${r.dept_id}, '${r.range_value}')">Review</button></td></tr>`;
        });
        $('#approval_list').html(html);
    });
}

function reviewBatch(id, range) { $('#dept_select').val(id); $('#cutoff_select').val(range); switchTab('schedule'); loadData(); }

function loadData() {
    const dept = $('#dept_select').val(); const range = $('#cutoff_select').val();
    if(!dept || !range) return;
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_employees_by_dept', dept_id: dept }, function(res) {
        $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_schedules', dept_id: dept, range: range }, function(data) {
            canEdit = data.can_edit;
            $('#status_display').text(data.status).show();
            $('#doc_dept_name').text(data.dept_name + ' DEPARTMENT');
            $('#name_prepared').text(data.signatories.prepared || '-');
            
            renderDocTable(res.employees, data.schedules, range);
            
            const renderSig = (id, path, show) => {
                if(show && path) $(`#sig_img_${id}`).html(`<img src="<?php echo baseUrl(''); ?>${path.replace('../', '')}" style="height:100%; object-fit:contain;">`);
                else $(`#sig_img_${id}`).empty();
            };
            renderSig('prepared', data.signatories.prepared_sig, data.status !== 'Draft');
            renderSig('checked', data.signatories.checked_sig, (data.status === 'Pending CEO' || data.status === 'Approved'));
            renderSig('approved', data.signatories.approved_sig, data.status === 'Approved');

            $('#workflow_actions').show(); $('#workflow_actions button').hide();
            if (canEdit) $('#btn_submit').show();
            if (data.can_approve_hr) { $('#btn_approve_hr').show(); $('#btn_reject').show(); }
            if (data.can_approve_ceo) { $('#btn_approve_ceo').show(); $('#btn_reject').show(); }
            if (data.can_reset) $('#btn_reset').show();
        });
    });
}

function renderDocTable(employees, schedules, range) {
    const [startStr, endStr] = range.split('|');
    const sDate = new Date(startStr); const eDate = new Date(endStr);
    $('#doc_cutoff_label').text(`Period: ${sDate.toLocaleDateString()} – ${eDate.toLocaleDateString()}`);

    let dates = []; let curr = new Date(sDate);
    let h1 = `<tr><th rowspan="2" style="text-align:left; width:150px;">Employee Name</th>`;
    let h2 = `<tr>`;
    while (curr <= eDate) {
        dates.push(new Date(curr));
        h1 += `<th>${curr.getDate()}</th>`;
        h2 += `<th>${curr.toLocaleDateString('en-US', {weekday:'short'}).charAt(0)}</th>`;
        curr.setDate(curr.getDate() + 1);
    }
    $('#doc_table_head').html(h1 + `</tr>` + h2 + `</tr>`);

    let body = '';
    employees.forEach(emp => {
        body += `<tr data-emp-id="${emp.id}"><td style="text-align:left; font-weight:700;">${emp.name}</td>`;
        dates.forEach(d => {
            const dStr = d.toISOString().split('T')[0];
            const s = schedules[emp.id] && schedules[emp.id][dStr] ? schedules[emp.id][dStr] : {code:''};
            body += `<td class="${canEdit?'cell-edit':''}" onclick="if(canEdit) openShiftModal(${emp.id}, '${dStr}')">${s.code || '-'}</td>`;
        });
        body += `</tr>`;
    });
    $('#doc_table_body').html(body);
}

function openShiftModal(id, d) { currentEditing = {empId:id, date:d}; $('#shiftModal').css('display', 'flex'); $('#modalBackdrop').show(); }
function closeShiftModal() { $('#shiftModal').hide(); $('#modalBackdrop').hide(); }
function saveShift(code) {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'save_schedule', employee_id: currentEditing.empId, schedule_date: currentEditing.date, shift_code: code, dept_id: $('#dept_select').val(), range: $('#cutoff_select').val() }, function() { closeShiftModal(); loadData(); });
}

function handleWorkflowAction(status) {
    if(!currentUserSigPath) { alert("Missing e-signature."); return; }
    if(confirm(`Apply signature and change status to ${status}?`)) updateStatus(status);
}

function updateStatus(status) {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'update_status', dept_id: $('#dept_select').val(), range: $('#cutoff_select').val(), status: status }, function() { loadData(); });
}

$(document).ready(() => { if(isAdminView) switchTab('dashboard'); else loadData(); });
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
