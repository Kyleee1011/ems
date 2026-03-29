<?php
$pageTitle = 'Schedule Manager — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

// Fix: Use $user_role which is passed from the controller
$is_dept_head = (strcasecmp($user_role, 'Dept Head') === 0 || strcasecmp($user_role, 'DeptHead') === 0);
$can_set_colors = ($is_dept_head || $is_hr || $is_ceo);
?>

<style>
    .doc-table { width: 100%; border-collapse: collapse; font-size: 8px; table-layout: fixed; }
    .doc-table th, .doc-table td { border: 1px solid var(--border); padding: 4px; text-align: center; word-wrap: break-word; }
    .doc-table th { background: var(--bg-raised); font-weight: 700; color: var(--ink-3); }
    .doc-table td { height: 32px; vertical-align: middle; }
    
    .cell-edit { cursor: pointer; transition: all 0.1s; }
    .cell-edit:hover { background-color: var(--teal-bg); color: var(--teal-deep); font-weight: 700; }
    
    /* SHIFT COLORS - Solid Light Colors for Visibility */
    .shift-morning, .shift-morning td { background-color: #dcfce7 !important; } 
    .shift-mid, .shift-mid td { background-color: #fef9c3 !important; }     
    .shift-night, .shift-night td { background-color: #dbeafe !important; }   
    .shift-reliever, .shift-reliever td { background-color: #f3e8ff !important; } 

    /* Cell Update Animation */
    @keyframes cellPulse {
        0% { background-color: var(--teal-bg); }
        100% { background-color: transparent; }
    }
    .cell-animate { animation: cellPulse 1s ease-out; }

    .employee-name { cursor: pointer; position: relative; font-weight: 800; text-align: left !important; color: var(--teal-deep); }
    .employee-name:hover { color: var(--teal); }
    
    /* Ensure the table wrap allows the dropdown to overflow when active */
    .table-wrap:has(.shift-dropdown.active) { overflow: visible !important; z-index: 100; }

    .shift-dropdown { 
        display: none; position: absolute; left: 0; top: 100%; 
        background: var(--bg-card); border: 1px solid var(--border); 
        border-radius: 6px; box-shadow: var(--sh-lg); z-index: 9999; 
        min-width: 140px; width: max-content; padding: 4px; 
        transform: translateY(2px);
    }
    .shift-dropdown.active { display: block; }
    .shift-opt { 
        padding: 6px 10px; font-size: 8px; cursor: pointer; 
        display: flex; align-items: center; gap: 8px; font-weight: 800; 
        border-radius: 4px; transition: background 0.2s;
        color: var(--ink-2);
        white-space: nowrap;
        text-transform: uppercase;
    }
    .shift-opt:hover { background: var(--bg-hover); color: var(--teal); }
    .dot { width: 8px; height: 8px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.1); flex-shrink: 0; }

    .dropdown-trigger {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; border-radius: 4px; border: 1px solid var(--border);
        background: var(--bg-raised); color: var(--ink-4); transition: all 0.2s;
        margin-left: auto;
    }
    .employee-name:hover .dropdown-trigger { color: var(--teal); border-color: var(--teal-border); background: var(--teal-bg); }

    /* SIGNATURE ANIMATIONS */
    .signature-img { height: 50px; width: auto; position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); z-index: 10; }
    @keyframes signEnter {
        0% { opacity: 0; transform: translateX(-50%) scale(2) rotate(-10deg); }
        100% { opacity: 1; transform: translateX(-50%) scale(1) rotate(0deg); }
    }
    .signature-animate { animation: signEnter 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }

    @media print {
        @page { size: A4 landscape; margin: 5mm; }
        .no-print { display: none !important; }
        body { background: #fff; padding: 0; }
        .app, .main { display: block !important; padding: 0 !important; margin: 0 !important; }
        .sidebar, .topbar, .page-header, .tabs-nav, .card-controls { display: none !important; }
        .document-page { 
            box-shadow: none !important; border: none !important; 
            width: 100% !important; padding: 0 10mm !important; 
            background: white !important;
        }
        .doc-table { border: 1px solid #000; }
        .doc-table th, .doc-table td { border: 1px solid #000; color: #000 !important; }
        .signature-img { height: 60px; }
    }
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-calendar-days"></i> Workforce Scheduling</h1>
        <p class="page-sub">Manage shifts and departmental rotations</p>
    </div>
    <div class="header-actions">
        <span id="status_display" class="tag tag-teal" style="padding: 5px 15px; display:none; font-weight: 800;">Draft</span>
        <button onclick="window.print()" class="pill-btn"><i class="fa-solid fa-print"></i> Print Schedule</button>
    </div>
</div>

<?php if ($is_admin_view): ?>
<div class="flex-row mb-20 no-print tabs-nav" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
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
    <div class="card no-print mb-20 card-controls">
        <div class="card-body flex-row" style="flex-wrap: wrap; gap: 20px;">
            <?php if($is_hr || $is_ceo): ?>
                <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                    <label class="form-label">Department</label>
                    <select id="dept_select" class="input-field" onchange="loadData()">
                        <option value="">-- Select Department --</option>
                        <?php foreach($depts as $d): ?><option value="<?php echo $d['dept_id']; ?>" <?php echo $d['dept_id'] == $user_dept ? 'selected' : ''; ?>><?php echo $d['dept_name']; ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" id="dept_select" value="<?php echo $user_dept; ?>">
            <?php endif; ?>
            
            <div class="form-group mb-0" style="flex: 1; min-width: 250px;">
                <label class="form-label">Cutoff Period</label>
                <select id="cutoff_select" class="input-field" onchange="loadData()">
                    <?php foreach($cutoff_options as $opt): ?>
                        <option value="<?php echo $opt['value']; ?>" <?php echo $opt['value'] === $default_cutoff ? 'selected' : ''; ?>><?php echo $opt['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="workflow_actions" class="flex-row" style="margin-top: 10px; flex: 100%; justify-content: center; display:none; gap: 10px;">
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
                <h2 id="doc_dept_name" style="font-weight: 800; font-size: 16px; margin-top: 10px; text-transform: uppercase; text-decoration: underline;">DEPARTMENT</h2>
                <p id="doc_cutoff_label" style="font-size: 11px; color: var(--ink-3); font-weight: 700; margin-top: 5px;">Cut-off Period: ...</p>
            </div>

            <div class="table-wrap" style="border: 1px solid var(--border);">
                <table class="doc-table">
                    <thead id="doc_table_head"></thead>
                    <tbody id="doc_table_body">
                        <tr><td colspan="32" style="padding: 50px; color: var(--ink-4);">Select parameters to load scheduling matrix...</td></tr>
                    </tbody>
                </table>
            </div>

            <p style="font-size: 8px; font-style: italic; margin-top: 10px; color: var(--ink-4);">Note: Subjected to changes for events/functions as required.</p>

            <div class="grid-3 mt-20" style="margin-top: 40px; text-align: center; gap: 40px;">
                <div style="position: relative;">
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px; text-align: left;">Prepared By:</p>
                    <div id="sig_img_prepared" style="height: 40px; margin-bottom: 5px; position: relative;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0;"></div>
                    <p id="name_prepared" style="font-size: 10px; font-weight: 800; margin-top: 5px;">-</p>
                    <p id="dept_prepared" style="font-size: 8px; font-weight: 600; color: var(--ink-3);">-</p>
                </div>
                <div style="position: relative;">
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px; text-align: left;">Checked By (HR):</p>
                    <div id="sig_img_checked" style="height: 40px; margin-bottom: 5px; position: relative;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0;"></div>
                    <p style="font-size: 10px; font-weight: 800; margin-top: 5px;"><?php echo htmlspecialchars($pageHrName); ?></p>
                    <p style="font-size: 8px; font-weight: 600; color: var(--ink-3);">Human Resources Department</p>
                </div>
                <div style="position: relative;">
                    <p style="font-size: 9px; font-weight: 700; color: var(--ink-4); text-transform: uppercase; margin-bottom: 30px; text-align: left;">Approved By (CEO):</p>
                    <div id="sig_img_approved" style="height: 40px; margin-bottom: 5px; position: relative;"></div>
                    <div style="border-bottom: 1px solid #000; margin: 0;"></div>
                    <p style="font-size: 10px; font-weight: 800; margin-top: 5px;"><?php echo htmlspecialchars($pageCeoName); ?></p>
                    <p style="font-size: 8px; font-weight: 600; color: var(--ink-3);">President & CEO</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SHIFT MODAL -->
<div id="modalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:100; display:none;" onclick="closeShiftModal()"></div>
<div id="shiftModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:650px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-lg); z-index:101; display:none; flex-direction:column; max-height:85vh;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; justify-content:space-between; background:var(--bg-raised); border-radius:16px 16px 0 0;">
        <h3 class="card-title">Assignment Selector</h3>
        <button onclick="closeShiftModal()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:20px; overflow-y:auto;">
        <?php foreach ($grouped_shifts as $groupName => $shifts): ?>
            <div class="section-hd"><span class="section-hd-label"><?php echo $groupName; ?></span><div class="section-hd-line"></div></div>
            <div class="grid-4 mb-20" style="grid-template-columns: repeat(4, 1fr); gap: 8px;">
                <?php foreach ($shifts as $s): ?>
                    <div onclick="saveShift('<?php echo $s['shift_code']; ?>')" class="pill-btn" style="justify-content:center; text-align:center; padding:12px 5px; cursor:pointer; flex-direction: column;">
                        <div style="font-weight:800; font-size:11px;"><?php echo $s['shift_code']; ?></div>
                        <?php if($s['time_in']): ?><div style="font-size:9px; color:var(--ink-4); font-weight:600;"><?php echo $s['time_in']->format('H:i');?>-<?php echo $s['time_out']->format('H:i');?></div><?php endif; ?>
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
let canSetColors = <?php echo ($can_set_colors ? 'true' : 'false'); ?>;

// Approval Configuration
const approvalConfig = {
    'Pending HR': { areaId: 'sig_img_prepared', btnId: 'btn_submit', originalHtml: 'Sign & Submit' },
    'Pending CEO': { areaId: 'sig_img_checked', btnId: 'btn_approve_hr', originalHtml: 'Approve (HR)' },
    'Approved': { areaId: 'sig_img_approved', btnId: 'btn_approve_ceo', originalHtml: 'Final Release' }
};

$(document).ready(() => {
    if(isAdminView) switchTab('dashboard'); else loadData();
});

function switchTab(tab) {
    $('.tab-content').hide(); $('.nav-item').removeClass('active').css('border-bottom-color', 'transparent');
    $('#tab_' + tab).show(); $('#btn-tab-' + tab).addClass('active').css('border-bottom-color', 'var(--teal)');
    if(tab === 'dashboard') loadDashboard();
}

function loadDashboard() {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_pending_approvals' }, function(res) {
        let html = '';
        if (res.approvals.length === 0) html = '<tr><td colspan="4" style="text-align:center; padding:40px; font-style:italic;">No pending schedules.</td></tr>';
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

    // Reset workflow states
    $('.btn-primary').data('confirming', false);
    Object.values(approvalConfig).forEach(c => $('#' + c.btnId).text(c.originalHtml).removeClass('btn-confirm'));

    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_employees_by_dept', dept_id: dept }, function(res) {
        $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'get_schedules', dept_id: dept, range: range }, function(data) {
            canEdit = data.can_edit;
            $('#status_display').text(data.status).show();
            $('#doc_dept_name').text(data.dept_name.toUpperCase() + ' DEPARTMENT');
            $('#dept_prepared').text(data.dept_name + ' Department');
            $('#name_prepared').text(data.signatories.prepared || '-');
            
            renderDocTable(res.employees, data.schedules, range);
            
            const renderSig = (id, path, show) => {
                if(show && path) $(`#sig_img_${id}`).html(`<img src="<?php echo baseUrl(''); ?>${path.replace('../', '')}" class="signature-img signature-animate" alt="Sig">`);
                else $(`#sig_img_${id}`).empty();
            };
            renderSig('prepared', data.signatories.prepared_sig, (data.status !== 'Draft' && data.status !== 'Not Started'));
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
    $('#doc_cutoff_label').text(`Period: ${sDate.toLocaleDateString('en-US', {month:'long', day:'numeric'})} – ${eDate.toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'})}`);

    let dates = []; let curr = new Date(sDate);
    let h1 = `<tr><th rowspan="2" style="text-align:left; width:150px;">Employee Name</th>`;
    let h2 = `<tr>`;
    while (curr <= eDate) {
        dates.push(new Date(curr));
        h1 += `<th>${curr.getDate()}</th>`;
        h2 += `<th>${curr.toLocaleDateString('en-US', {weekday:'short'}).charAt(0)}</th>`;
        curr.setDate(curr.getDate() + 1);
    }
    h1 += `<th>-</th></tr>`; h2 += `<th>-</th></tr>`;
    $('#doc_table_head').html(h1 + h2);

    let body = '';
    employees.forEach(emp => {
        const savedShift = localStorage.getItem(`shift_${emp.id}`) || '';
        body += `<tr data-emp-id="${emp.id}" class="${savedShift ? 'shift-'+savedShift : ''}">
            <td class="employee-name" onclick="toggleShiftDropdown(event, ${emp.id})">
                <div style="display:flex; align-items:center; width:100%;">
                    <span style="flex:1;">${emp.name}</span>
                    ${canSetColors ? '<div class="dropdown-trigger no-print"><i class="fa-solid fa-caret-down" style="font-size:8px;"></i></div>' : ''}
                </div>
                ${canSetColors ? `
                <div id="dropdown_${emp.id}" class="shift-dropdown no-print">
                    <div class="shift-opt" onclick="setRowShift(${emp.id}, 'morning')"><div class="dot" style="background:#10b981;"></div> Morning</div>
                    <div class="shift-opt" onclick="setRowShift(${emp.id}, 'mid')"><div class="dot" style="background:#f59e0b;"></div> Mid Shift</div>
                    <div class="shift-opt" onclick="setRowShift(${emp.id}, 'night')"><div class="dot" style="background:#3b82f6;"></div> Night Shift</div>
                    <div class="shift-opt" onclick="setRowShift(${emp.id}, 'reliever')"><div class="dot" style="background:#8b5cf6;"></div> Reliever</div>
                    <div class="shift-opt" style="border-top:1px solid var(--border-lt); margin-top:5px; color:var(--red);" onclick="setRowShift(${emp.id}, '')"><i class="fa-solid fa-eraser"></i> Clear Shift</div>
                </div>` : ''}
            </td>`;
        dates.forEach(d => {
            const dStr = d.toISOString().split('T')[0];
            const s = (schedules[emp.id] && schedules[emp.id][dStr]) ? schedules[emp.id][dStr] : {code:'', time:''};
            
            let display = '-';
            if (['OFF', 'HOLIDAY OFF', 'FLEX', 'LWOP', 'LWP'].includes(s.code)) {
                display = `<b style="color:var(--red);">${s.code}</b>`;
            } else if (s.time && s.time !== '-') {
                display = `<div style="font-size: 7px; line-height: 1;">${s.time}</div>`;
            } else if (s.code) {
                display = s.code;
            }
            
            body += `<td class="${canEdit?'cell-edit':''}" onclick="if(canEdit) openShiftModal(${emp.id}, '${dStr}')">${display}</td>`;
        });
        body += `<td>-</td></tr>`;
    });
    $('#doc_table_body').html(body);
    // Clear recently saved flag after rendering
    if (currentEditing.recentlySaved) {
        setTimeout(() => { currentEditing.recentlySaved = false; }, 500);
    }
}

function toggleShiftDropdown(e, id) { e.stopPropagation(); $('.shift-dropdown').not('#dropdown_'+id).removeClass('active'); $('#dropdown_'+id).toggleClass('active'); }
function setRowShift(id, type) { 
    const row = $(`tr[data-emp-id="${id}"]`); row.removeClass('shift-morning shift-mid shift-night shift-reliever');
    if(type) { row.addClass('shift-'+type); localStorage.setItem(`shift_${id}`, type); } else { localStorage.removeItem(`shift_${id}`); }
}
$(document).on('click', () => $('.shift-dropdown').removeClass('active'));

function openShiftModal(id, d) { currentEditing = {empId:id, date:d}; $('#shiftModal').css('display', 'flex'); $('#modalBackdrop').show(); }
function closeShiftModal() { $('#shiftModal').hide(); $('#modalBackdrop').hide(); }

function saveShift(code) {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'save_schedule', employee_id: currentEditing.empId, schedule_date: currentEditing.date, shift_code: code, dept_id: $('#dept_select').val(), range: $('#cutoff_select').val() }, function() { 
        $('#shiftModal').hide(); $('#modalBackdrop').hide();
        currentEditing.recentlySaved = true; // Trigger animation
        loadData(); 
    });
}

function handleWorkflowAction(status) {
    const config = approvalConfig[status];
    if(!config) { updateStatus(status); return; }
    if(!currentUserSigPath) { alert("Electronic signature not found in your profile."); return; }

    const btn = $('#' + config.btnId);
    if (btn.data('confirming')) { updateStatus(status); return; }

    // Visual Confirmation Step
    const sigImg = $(`<img src="<?php echo baseUrl(''); ?>${currentUserSigPath.replace('../', '')}" class="signature-img signature-animate" alt="Sig">`);
    $('#' + config.areaId).html(sigImg);
    
    btn.data('confirming', true).text('Click Again to Confirm').css('background', 'var(--teal-deep)');
    $('#workflow_actions button').not(btn).hide();
    $('<button id="btn_cancel_wf" class="pill-btn" onclick="loadData()">Cancel</button>').insertAfter(btn);
}

function updateStatus(status) {
    $.post('<?php echo baseUrl('schedule/api'); ?>', { action: 'update_status', dept_id: $('#dept_select').val(), range: $('#cutoff_select').val(), status: status }, function() { loadData(); if(isAdminView && status === 'Approved') switchTab('dashboard'); });
}
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
